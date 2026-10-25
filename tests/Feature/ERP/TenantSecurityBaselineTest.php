<?php

namespace Tests\Feature\ERP;

use App\Models\ERP\Central\Tenant;
use App\Services\ERP\TenantConnector;
use Tests\TestCase;

final class TenantSecurityBaselineTest extends TestCase
{
    public function test_tenant_provisioning_assigns_owner_to_created_branch(): void
    {
        // Regression contract: a freshly provisioned tenant must not be
        // locked out of warehouse workflows by an empty branch_user pivot.
        $source = file_get_contents(app_path('Console/Commands/ProvisionErpTenant.php'));
        $this->assertIsString($source);
        $this->assertStringContainsString("table('branches')->insertGetId", $source);
        $this->assertStringContainsString("table('branch_user')->insert", $source);
        $this->assertStringContainsString("'branch_id'=>\$headOfficeId", $source);
        $this->assertStringContainsString("'user_id'=>\$id", $source);
        $this->assertStringNotContainsString("'branch_id'=>1", $source);
    }

    public function test_development_env_template_disables_debug_and_encrypts_sessions(): void
    {
        $template = file_get_contents(base_path('.env.example'));
        $this->assertIsString($template);
        $this->assertMatchesRegularExpression('/^APP_DEBUG=false$/m', $template);
        $this->assertMatchesRegularExpression('/^SESSION_ENCRYPT=true$/m', $template);
        $this->assertMatchesRegularExpression('/^ERP_TRUSTED_PROXIES=$/m', $template);
        $this->assertMatchesRegularExpression('/^SESSION_DOMAIN=null$/m', $template);
        $this->assertStringNotContainsString('APP_KEY=base64:', $template);
    }

    public function test_tenant_login_throttle_separates_hosts_accounts_and_ip_budgets(): void
    {
        $limiter = \Illuminate\Support\Facades\RateLimiter::limiter('erp-tenant-login');
        $request = \Illuminate\Http\Request::create(
            'https://tenant-a.erp.test/erp/login', 'POST',
            ['email' => 'OWNER@example.test']
        );
        $request->server->set('REMOTE_ADDR', '192.0.2.10');
        $a = $limiter($request);
        $this->assertCount(2, $a);
        $this->assertSame(12, $a[0]->maxAttempts);
        $this->assertSame(6, $a[1]->maxAttempts);

        $otherHost = \Illuminate\Http\Request::create(
            'https://tenant-b.erp.test/erp/login', 'POST',
            ['email' => 'owner@example.test']
        );
        $otherHost->server->set('REMOTE_ADDR', '192.0.2.10');
        $b = $limiter($otherHost);
        $this->assertNotSame($a[0]->key, $b[0]->key);
        $this->assertNotSame($a[1]->key, $b[1]->key);

        $otherAccount = \Illuminate\Http\Request::create(
            'https://tenant-a.erp.test/erp/login', 'POST',
            ['email' => 'different@example.test']
        );
        $otherAccount->server->set('REMOTE_ADDR', '192.0.2.10');
        $c = $limiter($otherAccount);
        $this->assertSame($a[0]->key, $c[0]->key);
        $this->assertNotSame($a[1]->key, $c[1]->key);
        $this->assertStringNotContainsString('owner@example.test', $a[1]->key);
    }

    public function test_platform_login_has_independent_rate_limit_keys(): void
    {
        $limiter = \Illuminate\Support\Facades\RateLimiter::limiter('erp-platform-login');
        $request = \Illuminate\Http\Request::create(
            'https://platform.erp.test/platform/erp/login', 'POST',
            ['email' => 'admin@example.test']
        );
        $request->server->set('REMOTE_ADDR', '192.0.2.20');
        $limits = $limiter($request);
        $this->assertCount(2, $limits);
        $this->assertSame(10, $limits[0]->maxAttempts);
        $this->assertSame(5, $limits[1]->maxAttempts);
        $this->assertStringNotContainsString('admin@example.test', $limits[1]->key);
    }

    public function test_retryable_job_exception_scrubs_tenant_credentials_before_retry(): void
    {
        config(['database.connections.erp_tenant' => [
            'driver' => 'mysql', 'database' => 'previous_tenant',
            'username' => 'previous_user', 'password' => 'previous_secret',
        ]]);
        app()->instance('erp.current_tenant', new \stdClass());

        $job = new \Illuminate\Queue\Jobs\SyncJob(
            app(), '{"uuid":"retry-exception","displayName":"retry-exception","job":"retry-exception","data":{}}', 'sync', 'default'
        );
        app('events')->dispatch(new \Illuminate\Queue\Events\JobExceptionOccurred(
            'sync', $job, new \RuntimeException('Retryable job error')
        ));

        $this->assertNull(config('database.connections.erp_tenant'));
        $this->assertFalse(app()->bound('erp.current_tenant'));
    }

    public function test_queue_completion_and_failure_both_scrub_tenant_credentials(): void
    {
        $job = new \Illuminate\Queue\Jobs\SyncJob(
            app(), '{"uuid":"cleanup-events","displayName":"cleanup-events","job":"cleanup-events","data":{}}', 'sync', 'default'
        );

        foreach (['processed', 'failed'] as $outcome) {
            config(['database.connections.erp_tenant' => [
                'driver' => 'mysql', 'database' => 'previous_tenant',
                'username' => 'previous_user', 'password' => 'sensitive_secret',
            ]]);
            app()->instance('erp.current_tenant', new \stdClass());

            $event = $outcome === 'processed'
                ? new \Illuminate\Queue\Events\JobProcessed('sync', $job)
                : new \Illuminate\Queue\Events\JobFailed(
                    'sync', $job, new \RuntimeException('Simulated failure')
                );
            app('events')->dispatch($event);

            $this->assertNull(
                config('database.connections.erp_tenant'),
                "Tenant credentials persisted after {$outcome} event."
            );
            $this->assertFalse(app()->bound('erp.current_tenant'));
        }
    }

    public function test_queue_before_event_removes_stale_database_credentials(): void
    {
        config(['database.connections.erp_tenant' => [
            'driver' => 'mysql', 'database' => 'tenant_previous',
            'username' => 'previous_user', 'password' => 'previous_secret',
        ]]);
        app()->instance('erp.current_tenant', new \stdClass());

        $job = new \Illuminate\Queue\Jobs\SyncJob(
            app(), '{"uuid":"next-job","displayName":"next-job","job":"next-job","data":{}}', 'sync', 'default'
        );
        app('events')->dispatch(new \Illuminate\Queue\Events\JobProcessing('sync', $job));

        $this->assertNull(config('database.connections.erp_tenant'));
        $this->assertFalse(app()->bound('erp.current_tenant'));
    }

    public function test_sequential_background_tenant_scopes_do_not_reuse_previous_credentials(): void
    {
        $connector = app(TenantConnector::class);
        $seen = [];

        foreach ([
            ['first_tenant', 'first_user', 'first_secret'],
            ['second_tenant', 'second_user', 'second_secret'],
        ] as [$database, $username, $password]) {
            $tenant = new Tenant();
            $tenant->database_name = $database;
            $tenant->database_username = $username;
            $tenant->database_password = $password;

            $connector->withinTenant($tenant, function () use (&$seen): void {
                $seen[] = [
                    config('database.connections.erp_tenant.database'),
                    config('database.connections.erp_tenant.username'),
                    config('database.connections.erp_tenant.password'),
                ];
                $this->assertTrue(app()->bound('erp.current_tenant'));
            });

            $this->assertNull(config('database.connections.erp_tenant'));
            $this->assertFalse(app()->bound('erp.current_tenant'));
        }

        $this->assertSame([
            ['first_tenant', 'first_user', 'first_secret'],
            ['second_tenant', 'second_user', 'second_secret'],
        ], $seen);
    }

    public function test_scoped_background_execution_cleans_up_after_callback_exception(): void
    {
        $tenant = new Tenant();
        $tenant->database_name = 'valid_tenant';
        $tenant->database_username = 'valid_user';
        $tenant->database_password = 'temporary_secret';

        try {
            app(TenantConnector::class)->withinTenant($tenant, function (): void {
                $this->assertTrue(app()->bound('erp.current_tenant'));
                $this->assertSame('valid_tenant', config('database.connections.erp_tenant.database'));
                throw new \RuntimeException('Simulated scheduled task failure');
            });
            $this->fail('Expected scheduled task exception.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated scheduled task failure', $e->getMessage());
        }

        $this->assertFalse(app()->bound('erp.current_tenant'));
        $this->assertNull(config('database.connections.erp_tenant'));
    }

    public function test_scoped_background_execution_cleans_up_when_tenant_is_invalid(): void
    {
        $tenant = new Tenant();
        $tenant->database_name = 'invalid-name';
        $tenant->database_username = 'valid_user';
        $tenant->database_password = 'placeholder';

        try {
            app(TenantConnector::class)->withinTenant($tenant, function (): void {
                $this->fail('Invalid tenant must not execute background callback.');
            });
            $this->fail('Invalid tenant connection was accepted.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Invalid tenant database identifier', $e->getMessage());
        }

        $this->assertFalse(app()->bound('erp.current_tenant'));
        $this->assertNull(config('database.connections.erp_tenant'));
    }

    public function test_worker_cleanup_removes_previous_tenant_database_credentials(): void
    {
        config(['database.connections.erp_tenant' => [
            'driver' => 'mysql', 'database' => 'tenant_previous',
            'username' => 'previous_user', 'password' => 'previous_secret',
        ]]);
        app()->instance('erp.current_tenant', new \stdClass());

        app(TenantConnector::class)->resetWorkerContext();

        $this->assertFalse(app()->bound('erp.current_tenant'));
        $this->assertNull(config('database.connections.erp_tenant'));
        app(TenantConnector::class)->resetWorkerContext();
        $this->assertNull(config('database.connections.erp_tenant'));
    }

    public function test_queue_before_event_clears_previous_job_tenant_context(): void
    {
        app()->instance('erp.current_tenant', new \stdClass());

        $job = new \Illuminate\Queue\Jobs\SyncJob(
            app(), '{"uuid":"retry-test","displayName":"cleanup-retry-test","job":"cleanup-retry-test","data":{}}', 'sync', 'default'
        );
        app('events')->dispatch(new \Illuminate\Queue\Events\JobProcessing('sync', $job));

        $this->assertFalse(app()->bound('erp.current_tenant'));
    }

    public function test_queue_failed_event_invokes_registered_tenant_cleanup(): void
    {
        app()->instance('erp.current_tenant', new \stdClass());

        $job = new \Illuminate\Queue\Jobs\SyncJob(
            app(), '{"uuid":"failed-test","displayName":"cleanup-failed-test","job":"cleanup-failed-test","data":{}}', 'sync', 'default'
        );
        app('events')->dispatch(new \Illuminate\Queue\Events\JobFailed(
            'sync', $job, new \RuntimeException('Simulated failed job')
        ));

        $this->assertFalse(app()->bound('erp.current_tenant'));
    }

    public function test_queue_after_event_invokes_registered_tenant_cleanup(): void
    {
        app()->instance('erp.current_tenant', new \stdClass());

        $event = new \Illuminate\Queue\Events\JobProcessed(
            'sync',
            new \Illuminate\Queue\Jobs\SyncJob(
                app(), '{"uuid":"test","displayName":"cleanup-test","job":"cleanup-test","data":{}}', 'sync', 'default'
            )
        );
        app('events')->dispatch($event);

        $this->assertFalse(app()->bound('erp.current_tenant'));
    }

    public function test_tenant_disconnect_is_idempotent_across_worker_job_boundaries(): void
    {
        $connector = app(TenantConnector::class);

        foreach ([1, 2, 3] as $jobNumber) {
            app()->instance('erp.current_tenant', (object) ['job' => $jobNumber]);
            $this->assertTrue(app()->bound('erp.current_tenant'));
            $connector->disconnect();
            $this->assertFalse(
                app()->bound('erp.current_tenant'),
                "Tenant context leaked after simulated job {$jobNumber}."
            );
        }

        // Repeated cleanup must also be safe after a failed job has
        // already disconnected its tenant connection.
        $connector->disconnect();
        $this->assertFalse(app()->bound('erp.current_tenant'));
    }

    public function test_tenant_auth_guard_is_registered_separately_from_platform_guard(): void
    {
        $this->assertSame('session', config('auth.guards.erp_tenant.driver'));
        $this->assertSame('erp_tenant_users', config('auth.guards.erp_tenant.provider'));
        $this->assertNotSame(config('auth.guards.web.provider'), config('auth.guards.erp_tenant.provider'));
    }

    public function test_tenant_connection_rejects_invalid_database_identifiers_before_connecting(): void
    {
        $tenant = new Tenant();
        $tenant->database_name = 'bad-db-name';
        $tenant->database_username = 'valid_user';
        $tenant->database_password = 'placeholder';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid tenant database identifier');

        app(TenantConnector::class)->connect($tenant);
    }

    public function test_tenant_connection_rejects_invalid_database_usernames_before_connecting(): void
    {
        $tenant = new Tenant();
        $tenant->database_name = 'valid_database';
        $tenant->database_username = 'bad-user';
        $tenant->database_password = 'placeholder';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid tenant database username');

        app(TenantConnector::class)->connect($tenant);
    }

    public function test_tenant_disconnect_clears_request_scoped_tenant_context(): void
    {
        app()->instance('erp.current_tenant', new \stdClass());

        app(TenantConnector::class)->disconnect();

        $this->assertFalse(app()->bound('erp.current_tenant'));
    }

    public function test_tenant_connection_rejects_invalid_username_without_binding_context(): void
    {
        $tenant = new Tenant();
        $tenant->database_name = 'valid_database';
        $tenant->database_username = 'invalid-user';
        $tenant->database_password = 'placeholder';

        try {
            app(TenantConnector::class)->connect($tenant);
            $this->fail('Invalid tenant username was accepted.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Invalid tenant database username', $e->getMessage());
        }

        $this->assertFalse(app()->bound('erp.current_tenant'));
    }

    public function test_tenant_permission_middleware_denies_unauthenticated_requests(): void
    {
        $request = \Illuminate\Http\Request::create('/erp/customers', 'GET');
        $middleware = app(\App\Http\Middleware\ErpPermission::class);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $middleware->handle($request, fn () => response('unexpected'), 'customers.manage');
    }

    public function test_platform_routes_require_web_auth_and_admin_middleware(): void
    {
        $routes = app('router')->getRoutes();
        foreach (['erp.platform.index', 'erp.platform.plans', 'erp.platform.tenant.status'] as $name) {
            $route = $routes->getByName($name);
            $this->assertNotNull($route, "Missing route {$name}");
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth:web', $middleware);
            $this->assertContains(\App\Http\Middleware\ErpPlatformAdmin::class, $middleware);
        }
    }

    public function test_sensitive_tenant_routes_require_permissions(): void
    {
        $expected = [
            'erp.customers.index' => 'customers.manage',
            'erp.customers.store' => 'customers.manage',
            'erp.products.index' => 'products.manage',
            'erp.products.store' => 'products.manage',
            'erp.stock.index' => 'stock.manage',
            'erp.sales.index' => 'sales.manage',
            'erp.sales.store' => 'sales.manage',
            'erp.sales.post' => 'sales.post',
        ];

        $routes = app('router')->getRoutes();
        foreach ($expected as $name => $permission) {
            $route = $routes->getByName($name);
            $this->assertNotNull($route, "Missing route {$name}");
            $this->assertContains('auth:erp_tenant', $route->gatherMiddleware());
            $this->assertContains(
                \App\Http\Middleware\ErpPermission::class.':'.$permission,
                $route->gatherMiddleware(),
                "Missing permission {$permission} on {$name}"
            );
        }
    }

    public function test_platform_login_is_rate_limited_and_tenant_login_is_rate_limited(): void
    {
        $routes = app('router')->getRoutes();
        foreach ([
            'erp.login.submit' => 'throttle:erp-tenant-login',
            'erp.platform.login.submit' => 'throttle:erp-platform-login',
        ] as $name => $throttle) {
            $route = $routes->getByName($name);
            $this->assertNotNull($route, "Missing route {$name}");
            $this->assertContains($throttle, $route->gatherMiddleware());
        }
    }

    public function test_session_security_defaults_prevent_shared_subdomain_cookies(): void
    {
        $this->assertNull(config('session.domain'));
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', strtolower((string) config('session.same_site')));
    }

    public function test_platform_admin_gate_rejects_wrong_host_before_authorization(): void
    {
        config(['erp.platform_host' => 'platform.erp.test']);
        $request = \Illuminate\Http\Request::create('http://tenant-a.erp.test/platform/erp', 'GET');
        $middleware = app(\App\Http\Middleware\ErpPlatformAdmin::class);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $middleware->handle($request, fn () => response('unexpected'));
    }

    public function test_platform_admin_gate_rejects_non_admin_on_platform_host(): void
    {
        config(['erp.platform_host' => 'platform.erp.test']);
        $request = \Illuminate\Http\Request::create('http://platform.erp.test/platform/erp', 'GET');
        $nonAdmin = new \Illuminate\Foundation\Auth\User();
        $nonAdmin->setAttribute('name', 'Not an administrator');
        $nonAdmin->setAttribute('erp_platform_admin', false);
        $request->setUserResolver(fn () => $nonAdmin);
        $middleware = app(\App\Http\Middleware\ErpPlatformAdmin::class);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $middleware->handle($request, fn () => response('unexpected'));
    }

    public function test_platform_admin_gate_allows_admin_on_correct_host(): void
    {
        config(['erp.platform_host' => 'platform.erp.test']);
        $request = \Illuminate\Http\Request::create('http://platform.erp.test/platform/erp', 'GET');
        $admin = new \Illuminate\Foundation\Auth\User();
        $admin->setAttribute('erp_platform_admin', true);
        $request->setUserResolver(fn () => $admin);
        $response = app(\App\Http\Middleware\ErpPlatformAdmin::class)
            ->handle($request, fn () => response('allowed', 200));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('allowed', $response->getContent());
    }

    public function test_platform_admin_gate_rejects_anonymous_request_on_platform_host(): void
    {
        config(['erp.platform_host' => 'platform.erp.test']);
        $request = \Illuminate\Http\Request::create('http://platform.erp.test/platform/erp', 'GET');
        $request->setUserResolver(fn () => null);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Http\Middleware\ErpPlatformAdmin::class)
            ->handle($request, fn () => response('unexpected'));
    }

    public function test_tenant_connection_rejects_missing_mysql_configuration_without_context_leak(): void
    {
        $original = config('database.connections.mysql');
        $tenant = new Tenant();
        $tenant->database_name = 'valid_database';
        $tenant->database_username = 'valid_user';
        $tenant->database_password = 'placeholder';

        config(['database.connections.mysql' => ['driver' => 'sqlite']]);
        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('MySQL central connection required');
            app(TenantConnector::class)->connect($tenant);
        } finally {
            config(['database.connections.mysql' => $original]);
            $this->assertFalse(app()->bound('erp.current_tenant'));
        }
    }

    public function test_tenant_connection_rejects_missing_mysql_connection_without_binding_context(): void
    {
        $original = config('database.connections.mysql');
        $tenant = new Tenant();
        $tenant->database_name = 'valid_database';
        $tenant->database_username = 'valid_user';
        $tenant->database_password = 'placeholder';

        config()->offsetUnset('database.connections.mysql');
        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('MySQL central connection required');
            app(TenantConnector::class)->connect($tenant);
        } finally {
            config(['database.connections.mysql' => $original]);
            $this->assertFalse(app()->bound('erp.current_tenant'));
        }
    }

    public function test_tenant_routes_require_tenant_resolution_middleware(): void
    {
        $routes = app('router')->getRoutes();
        foreach (['erp.login', 'erp.dashboard', 'erp.customers.index', 'erp.sales.index'] as $name) {
            $route = $routes->getByName($name);
            $this->assertNotNull($route, "Missing route {$name}");
            $this->assertContains('erp.tenant', $route->gatherMiddleware());
        }
    }
}
