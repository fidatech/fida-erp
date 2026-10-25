<?php

namespace Tests\Feature\ERP;

use App\Models\ERP\Central\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class TenantHttpIsolationTest extends TestCase
{
    public function test_separate_tenant_hosts_authenticate_against_separate_databases(): void
    {
        if (getenv('ERP_RUN_ISOLATION_TESTS') !== '1') {
            $this->markTestSkipped('Requires disposable CI MySQL databases.');
        }
        $this->assertSame('testing', app()->environment());
        $this->assertSame('127.0.0.1', getenv('ERP_TEST_DB_HOST'));
        $names = [
            (string) getenv('ERP_TEST_CENTRAL_DATABASE'),
            (string) getenv('ERP_TEST_TENANT_A_DATABASE'),
            (string) getenv('ERP_TEST_TENANT_B_DATABASE'),
        ];
        $this->assertCount(3, array_unique($names));
        foreach ($names as $name) $this->assertMatchesRegularExpression('/^erp_test_[a-zA-Z0-9_]+$/D', $name);

        $config = config('database.connections.mysql');
        $config['host'] = '127.0.0.1';
        $config['port'] = '3306';
        $config['database'] = $names[0];
        $config['username'] = getenv('ERP_TEST_CENTRAL_USER');
        $config['password'] = getenv('ERP_TEST_CENTRAL_PASSWORD');
        // Exercise a persistent filesystem-backed session store rather than
        // Laravel's in-memory array session driver.
        $sessionPath = storage_path('framework/erp-ci-sessions');
        if (! is_dir($sessionPath) && ! mkdir($sessionPath, 0700, true) && ! is_dir($sessionPath)) {
            throw new \RuntimeException('Unable to create disposable session directory.');
        }
        config([
            'database.connections.mysql' => $config,
            'session.driver' => 'file',
            'session.files' => $sessionPath,
            'session.domain' => null,
            'session.http_only' => true,
            'session.same_site' => 'lax',
        ]);
        DB::purge('mysql');

        // Production HTTPS must explicitly emit Secure session cookies.
        // HTTP-only fixture requests below intentionally exercise a separate
        // cookie configuration and must not be mistaken for HTTPS coverage.
        $this->assertNull(config('session.domain'));

        // Host-only cookies prevent sibling tenant subdomains from
        // automatically receiving the same browser session cookie.
        $this->assertNull(config('session.domain'));
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));

        $tenants = [];
        try {
            foreach (['A', 'B'] as $suffix) {
                $db = $names[$suffix === 'A' ? 1 : 2];
                $connection = 'fixture_'.$suffix;
                config(["database.connections.{$connection}" => array_replace($config, [
                    'database' => $db,
                    'username' => getenv("ERP_TEST_TENANT_{$suffix}_USER"),
                    'password' => getenv("ERP_TEST_TENANT_{$suffix}_PASSWORD"),
                ])]);
                DB::purge($connection);
                DB::connection($connection)->table('users')->insert([
                    'name' => 'Test Owner '.$suffix, 'email' => 'owner@example.test',
                    'password' => Hash::make('TestPassword123!'), 'active' => 1,
                    'locale' => 'en', 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::connection($connection)->table('customers')->insert([
                    'code' => 'ONLY_'.$suffix, 'name' => 'Only tenant '.$suffix,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $userId = DB::connection($connection)->table('users')
                    ->where('email', 'owner@example.test')->value('id');
                $roleId = DB::connection($connection)->table('roles')
                    ->insertGetId(['name' => 'Customer manager']);
                $permissionId = DB::connection($connection)->table('permissions')
                    ->insertGetId(['name' => 'customers.manage']);
                DB::connection($connection)->table('role_user')->insert([
                    'user_id' => $userId, 'role_id' => $roleId,
                ]);
                DB::connection($connection)->table('permission_role')->insert([
                    'role_id' => $roleId, 'permission_id' => $permissionId,
                ]);
                $tenants[] = Tenant::create([
                    'business_name' => 'Test '.$suffix, 'slug' => 'ci-'.strtolower($suffix),
                    'domain' => 'tenant-'.strtolower($suffix).'.erp.test',
                    'database_name' => $db,
                    'database_username' => getenv("ERP_TEST_TENANT_{$suffix}_USER"),
                    'database_password' => getenv("ERP_TEST_TENANT_{$suffix}_PASSWORD"),
                    'status' => 'active',
                ]);
            }

            // Prove the tenant connection selects only its own customer records.
            // These assertions deliberately bypass HTTP authorization; they check
            // physical database separation independently of user permissions.
            $connector = app(\App\Services\ERP\TenantConnector::class);
            foreach (['A', 'B'] as $index => $suffix) {
                $connector->connect($tenants[$index]);
                try {
                    $codes = DB::connection('erp_tenant')->table('customers')->pluck('code')->all();
                    $this->assertContains('ONLY_'.$suffix, $codes);
                    $this->assertNotContains('ONLY_'.($suffix === 'A' ? 'B' : 'A'), $codes);
                } finally {
                    $connector->disconnect();
                }
                $this->assertFalse(app()->bound('erp.current_tenant'));
            }

            foreach (['A', 'B'] as $suffix) {
                // Independent browser sessions: tenant A's session must not
                // accidentally be reused for tenant B's separate login.
                $this->flushSession();
                $host = 'tenant-'.strtolower($suffix).'.erp.test';
                $response = $this->withServerVariables(['HTTP_HOST' => $host])
                    ->post('http://'.$host.'/erp/login', [
                        'email' => 'owner@example.test', 'password' => 'TestPassword123!',
                    ]);
                $response->assertRedirect();
                $this->assertStringContainsString('/erp', $response->headers->get('Location', ''));
                // Inspect the actual Set-Cookie response header, not just
                // Laravel's session configuration values.
                $sessionCookies = array_values(array_filter(
                    $response->headers->getCookies(),
                    fn ($cookie) => $cookie->getName() === config('session.cookie')
                ));
                $this->assertCount(1, $sessionCookies);
                $this->assertNull($sessionCookies[0]->getDomain());
                $this->assertTrue($sessionCookies[0]->isHttpOnly());
                $this->assertSame('lax', strtolower((string) $sessionCookies[0]->getSameSite()));
                $this->assertFalse(app()->bound('erp.current_tenant'));
            }

            // Tenant B's authenticated session must not authorize a request
            // on tenant A, even though both fixtures use user ID 1.
            $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                ->getJson('http://tenant-a.erp.test/erp/customers/data')
                ->assertUnauthorized();
            $this->assertFalse(app()->bound('erp.current_tenant'));

            // Exercise the reverse direction with a fresh tenant A login:
            // a tenant A session must also be rejected on tenant B's host.
            $this->flushSession();
            $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                ->post('http://tenant-a.erp.test/erp/login', [
                    'email' => 'owner@example.test', 'password' => 'TestPassword123!',
                ])->assertRedirect();
            $this->withServerVariables(['HTTP_HOST' => 'tenant-b.erp.test'])
                ->getJson('http://tenant-b.erp.test/erp/customers/data')
                ->assertUnauthorized();
            $this->assertFalse(app()->bound('erp.current_tenant'));

            // Positive authorization: each logged-in user sees only the
            // customer physically stored in their own tenant database.
            foreach (['A', 'B'] as $suffix) {
                $this->flushSession();
                $host = 'tenant-'.strtolower($suffix).'.erp.test';
                $this->withServerVariables(['HTTP_HOST' => $host])
                    ->post('http://'.$host.'/erp/login', [
                        'email' => 'owner@example.test', 'password' => 'TestPassword123!',
                    ])->assertRedirect();
                $response = $this->withServerVariables(['HTTP_HOST' => $host])
                    ->getJson('http://'.$host.'/erp/customers/data');
                $response->assertOk();
                $codes = array_column($response->json('data'), 'code');
                $this->assertContains('ONLY_'.$suffix, $codes);
                $this->assertNotContains('ONLY_'.($suffix === 'A' ? 'B' : 'A'), $codes);
                $this->assertFalse(app()->bound('erp.current_tenant'));

                // The same authenticated session must remain usable for
                // multiple requests on its own host, without reconnect leaks.
                $second = $this->withServerVariables(['HTTP_HOST' => $host])
                    ->getJson('http://'.$host.'/erp/customers/data');
                $second->assertOk();
                $this->assertContains('ONLY_'.$suffix, array_column($second->json('data'), 'code'));
                $this->assertFalse(app()->bound('erp.current_tenant'));

                // Logout invalidates the tenant session; a subsequent
                // protected request must be rejected.
                $this->withServerVariables(['HTTP_HOST' => $host])
                    ->post('http://'.$host.'/erp/logout')->assertRedirect();
                $this->withServerVariables(['HTTP_HOST' => $host])
                    ->getJson('http://'.$host.'/erp/customers/data')->assertUnauthorized();
                $this->assertFalse(app()->bound('erp.current_tenant'));
            }
            $this->flushSession();

            // Earlier fixture logins can exhaust the shared throttle bucket.
            // Keep authentication and tenant middleware enabled while
            // excluding rate limiting from the remaining isolation cases.
            $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

            // Revoking a user must invalidate an already authenticated
            // session without requiring the user to log out manually.
            $this->flushSession();
            $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                ->post('http://tenant-a.erp.test/erp/login', [
                    'email' => 'owner@example.test', 'password' => 'TestPassword123!',
                ])->assertRedirect();
            DB::connection('fixture_A')->table('users')
                ->where('email', 'owner@example.test')->update(['active' => 0]);
            try {
                $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                    ->getJson('http://tenant-a.erp.test/erp/customers/data')
                    ->assertUnauthorized();
                $this->assertFalse(app()->bound('erp.current_tenant'));
            } finally {
                DB::connection('fixture_A')->table('users')
                    ->where('email', 'owner@example.test')->update(['active' => 1]);
            }
            $this->flushSession();

            // The central SaaS platform uses the web guard, never the
            // tenant guard. A tenant login cannot authenticate platform APIs.
            config(['erp.platform_host' => 'platform.erp.test']);
            $this->withServerVariables(['HTTP_HOST' => 'tenant-b.erp.test'])
                ->post('http://tenant-b.erp.test/erp/login', [
                    'email' => 'owner@example.test', 'password' => 'TestPassword123!',
                ])->assertRedirect();
            $this->withServerVariables(['HTTP_HOST' => 'platform.erp.test'])
                ->getJson('http://platform.erp.test/platform/erp')
                ->assertUnauthorized();
            $this->assertFalse(app()->bound('erp.current_tenant'));
            // The platform login endpoint is not available on tenant hosts.
            $this->withServerVariables(['HTTP_HOST' => 'tenant-b.erp.test'])
                ->get('http://tenant-b.erp.test/platform/erp/login')
                ->assertNotFound();
            // A platform host must not resolve as a tenant host.
            $this->withServerVariables(['HTTP_HOST' => 'platform.erp.test'])
                ->get('http://platform.erp.test/erp/login')
                ->assertNotFound();
            $this->flushSession();

            // A real HTTPS request must receive a Secure session cookie.
            config(['session.secure' => true]);
            $this->flushSession();
            // Earlier login scenarios have exhausted the 6/minute test
            // throttle. Disable only throttle middleware for the remaining
            // HTTPS cookie assertion; authentication remains enabled.
            $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
            $httpsHost = 'tenant-a.erp.test';
            $secureLogin = $this->withServerVariables([
                'HTTP_HOST' => $httpsHost,
                'HTTPS' => 'on',
                'SERVER_PORT' => '443',
            ])->post('https://'.$httpsHost.'/erp/login', [
                'email' => 'owner@example.test',
                'password' => 'TestPassword123!',
            ]);
            $secureLogin->assertRedirect();
            $secureCookies = array_values(array_filter(
                $secureLogin->headers->getCookies(),
                fn ($cookie) => $cookie->getName() === config('session.cookie')
            ));
            $this->assertCount(1, $secureCookies);
            $this->assertTrue($secureCookies[0]->isSecure());
            $this->assertTrue($secureCookies[0]->isHttpOnly());
            $this->assertNull($secureCookies[0]->getDomain());
            $this->flushSession();

            // Exception responses must not leak a tenant connection or
            // tenant context into the next request in the same process.
            $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                ->getJson('http://tenant-a.erp.test/erp/customers/data')
                ->assertUnauthorized();
            $this->assertFalse(app()->bound('erp.current_tenant'));
            $this->withServerVariables(['HTTP_HOST' => 'tenant-b.erp.test'])
                ->getJson('http://tenant-b.erp.test/erp/customers/data')
                ->assertUnauthorized();
            $this->assertFalse(app()->bound('erp.current_tenant'));

            // Untrusted forwarded host headers cannot select another tenant.
            // The HTTP Host remains authoritative without a trusted proxy.
            $this->withServerVariables([
                'HTTP_HOST' => 'unknown.erp.test',
                'HTTP_X_FORWARDED_HOST' => 'tenant-a.erp.test',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ])->get('http://unknown.erp.test/erp/login')->assertNotFound();
            $this->assertFalse(app()->bound('erp.current_tenant'));

            $this->withServerVariables(['HTTP_HOST' => 'unknown.erp.test'])
                ->get('http://unknown.erp.test/erp/login')->assertNotFound();

            // A fresh, unauthenticated session cannot read the customer API,
            // even when a tenant hostname resolves successfully.
            auth('erp_tenant')->logout();
            auth()->forgetGuards();
            $this->flushSession();
            foreach (['A', 'B'] as $suffix) {
                $host = 'tenant-'.strtolower($suffix).'.erp.test';
                $this->withServerVariables(['HTTP_HOST' => $host])
                    ->getJson('http://'.$host.'/erp/customers/data')
                    ->assertUnauthorized();
                $this->assertFalse(app()->bound('erp.current_tenant'));
            }

            // Suspending a tenant must deny both public login and protected
            // APIs, even if its database still contains active users.
            $tenants[1]->update(['suspended_at' => now()]);
            $this->withServerVariables(['HTTP_HOST' => 'tenant-b.erp.test'])
                ->getJson('http://tenant-b.erp.test/erp/customers/data')
                ->assertNotFound();
            $this->assertFalse(app()->bound('erp.current_tenant'));
            $this->withServerVariables(['HTTP_HOST' => 'tenant-b.erp.test'])
                ->get('http://tenant-b.erp.test/erp/login')->assertNotFound();
        } finally {
            foreach (['A', 'B'] as $suffix) {
                $connection = 'fixture_'.$suffix;
                if (config("database.connections.{$connection}")) {
                    DB::connection($connection)->table('customers')->where('code', 'ONLY_'.$suffix)->delete();
                    DB::connection($connection)->table('users')->where('email', 'owner@example.test')->delete();
                    DB::purge($connection);
                }
            }
            foreach ($tenants as $tenant) $tenant->delete();
            DB::purge('mysql');
        }
    }
}
