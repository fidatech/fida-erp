<?php

namespace Tests\Feature\ERP;

use App\Models\ERP\Central\Tenant;
use App\Services\ERP\TenantConnector;
use Tests\TestCase;

final class TenantSecurityBaselineTest extends TestCase
{
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
        foreach (['erp.login.submit', 'erp.platform.login.submit'] as $name) {
            $route = $routes->getByName($name);
            $this->assertNotNull($route, "Missing route {$name}");
            $this->assertContains('throttle:6,1', $route->gatherMiddleware());
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
        $request->setUserResolver(fn () => new \Illuminate\Foundation\Auth\User([
            'name' => 'Not an administrator',
        ]));
        $middleware = app(\App\Http\Middleware\ErpPlatformAdmin::class);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $middleware->handle($request, fn () => response('unexpected'));
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
