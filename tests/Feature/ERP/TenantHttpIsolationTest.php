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
                $branchId = DB::connection($connection)->table('branches')->insertGetId([
                    'code' => 'MAIN', 'name' => 'Main branch', 'active' => 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $otherBranchId = DB::connection($connection)->table('branches')->insertGetId([
                    'code' => 'OTHER', 'name' => 'Restricted branch', 'active' => 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::connection($connection)->table('branch_user')->insert([
                    'user_id' => $userId, 'branch_id' => $branchId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->assertTrue(DB::connection($connection)->table('branch_user')
                    ->where('user_id', $userId)->where('branch_id', $branchId)->exists());
                DB::connection($connection)->table('warehouses')->insert([
                    ['branch_id' => $branchId, 'code' => 'ALLOWED', 'name' => 'Allowed warehouse'],
                    ['branch_id' => $otherBranchId, 'code' => 'RESTRICTED', 'name' => 'Restricted warehouse'],
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

            // Branch membership must allow assigned active branches only.
            $branchConnector = app(\App\Services\ERP\TenantConnector::class);
            foreach ($tenants as $tenant) {
                $branchConnector->connect($tenant);
                try {
                    $user = \App\Models\ERP\Tenant\TenantUser::query()
                        ->where('email', 'owner@example.test')->firstOrFail();
                    $allowed = DB::connection('erp_tenant')->table('branches')->where('code', 'MAIN')->value('id');
                    $restricted = DB::connection('erp_tenant')->table('branches')->where('code', 'OTHER')->value('id');
                    $this->assertTrue($user->canAccessBranch((int) $allowed));
                    $this->assertFalse($user->canAccessBranch((int) $restricted));
                    // Model-level authorization must fail closed even if an
                    // inactive user instance is still held by a worker.
                    $this->assertTrue($user->allowed('customers.manage'));
                    $user->active = false;
                    $this->assertFalse($user->allowed('customers.manage'));
                    $this->assertFalse($user->canAccessBranch((int) $allowed));
                    $user->active = true;
                    $this->assertTrue($user->allowed('customers.manage'));
                    $this->assertTrue($user->canAccessBranch((int) $allowed));
                    // A user may not claim a branch by passing its ID;
                    // authorization must depend on an actual membership row.
                    $this->assertFalse($user->canAccessBranch(0));
                    $this->assertFalse($user->canAccessBranch(-1));
                    $this->assertFalse($user->canAccessBranch(2147483647));
                    // Membership must be evaluated against this tenant's
                    // database, never against a similarly numbered branch
                    // in another tenant's database.
                    $this->assertSame(1, DB::connection('erp_tenant')
                        ->table('branch_user')->where('user_id', $user->getKey())
                        ->where('branch_id', $allowed)->count());
                    $this->assertSame(0, DB::connection('erp_tenant')
                        ->table('branch_user')->where('user_id', $user->getKey())
                        ->where('branch_id', $restricted)->count());
                    DB::connection('erp_tenant')->table('branches')->where('id', $allowed)->update(['active' => 0]);
                    $this->assertFalse($user->canAccessBranch((int) $allowed));
                    DB::connection('erp_tenant')->table('branches')->where('id', $allowed)->update(['active' => 1]);
                } finally {
                    $branchConnector->disconnect();
                }
            }

            // Branch-scoped controllers must never accept an unassigned
            // warehouse even when the user has the module permission.
            $branchDb = DB::connection('fixture_A');
            $stockPermissionId = $branchDb->table('permissions')->insertGetId(['name' => 'stock.manage']);
            $salesPermissionId = $branchDb->table('permissions')->insertGetId(['name' => 'sales.manage']);
            $postPermissionId = $branchDb->table('permissions')->insertGetId(['name' => 'sales.post']);
            $customerRoleId = $branchDb->table('roles')->where('name', 'Customer manager')->value('id');
            foreach ([$stockPermissionId, $salesPermissionId, $postPermissionId] as $newPermissionId) {
                $branchDb->table('permission_role')->insert([
                    'role_id' => $customerRoleId, 'permission_id' => $newPermissionId,
                ]);
            }
            $this->flushSession();
            $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                ->post('http://tenant-a.erp.test/erp/login', [
                    'email' => 'owner@example.test', 'password' => 'TestPassword123!',
                ])->assertRedirect();
            $restrictedWarehouse = (int) $branchDb->table('warehouses')
                ->where('code', 'RESTRICTED')->value('id');
            $allowedWarehouse = (int) $branchDb->table('warehouses')
                ->where('code', 'ALLOWED')->value('id');
            $this->assertNotSame($restrictedWarehouse, $allowedWarehouse);
            $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                ->postJson('http://tenant-a.erp.test/erp/sales', [
                    'request_key' => (string) \Illuminate\Support\Str::uuid(),
                    'customer_id' => 1, 'warehouse_id' => $restrictedWarehouse,
                    'currency_code' => 'AFN', 'fx_rate' => 1,
                    'lines' => [['product_variant_id' => 1, 'quantity' => 1, 'unit_price' => 1]],
                ])->assertForbidden();
            $this->assertFalse(app()->bound('erp.current_tenant'));
            $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                ->postJson('http://tenant-a.erp.test/erp/stock/receive', [
                    'request_key' => (string) \Illuminate\Support\Str::uuid(),
                    'supplier_id' => 1, 'warehouse_id' => $restrictedWarehouse,
                    'currency_code' => 'AFN', 'fx_rate' => 1,
                    'lines' => [['product_variant_id' => 1, 'quantity' => 1, 'unit_cost' => 1]],
                ])->assertForbidden();
            $this->assertFalse(app()->bound('erp.current_tenant'));

            // An existing invoice must be authorized using its stored
            // warehouse, not a branch or warehouse ID supplied by the client.
            $restrictedInvoiceId = $branchDb->table('sales_invoices')->insertGetId([
                'warehouse_id' => $restrictedWarehouse,
            ]);
            $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                ->postJson('http://tenant-a.erp.test/erp/sales/'.$restrictedInvoiceId.'/post')
                ->assertForbidden();
            $this->assertFalse(app()->bound('erp.current_tenant'));
            $this->assertSame($restrictedWarehouse, (int) $branchDb->table('sales_invoices')
                ->where('id', $restrictedInvoiceId)->value('warehouse_id'));
            // PDF access must be denied before rendering any invoice details.
            $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                ->get('http://tenant-a.erp.test/erp/sales/'.$restrictedInvoiceId.'/pdf')
                ->assertNotFound();
            $this->assertFalse(app()->bound('erp.current_tenant'));

            // Sales listing must not reveal invoices from another branch.
            $allowedInvoiceId = $branchDb->table('sales_invoices')->insertGetId([
                'warehouse_id' => $allowedWarehouse,
                'customer_id' => 1, 'number' => 'ALLOWED-INVOICE',
                'total' => 10, 'currency_code' => 'AFN', 'status' => 'draft',
                'created_at' => now(),
            ]);
            $branchDb->table('sales_invoices')->where('id', $restrictedInvoiceId)->update([
                'customer_id' => 1, 'number' => 'RESTRICTED-INVOICE',
                'total' => 10, 'currency_code' => 'AFN', 'status' => 'draft',
                'created_at' => now(),
            ]);
            $salesPage = $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                ->get('http://tenant-a.erp.test/erp/sales');
            $salesPage->assertOk()->assertSee('ALLOWED-INVOICE')
                ->assertDontSee('RESTRICTED-INVOICE');
            $this->assertFalse(app()->bound('erp.current_tenant'));

            // The sale creation page must offer only assigned warehouses.
            $createPage = $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                ->get('http://tenant-a.erp.test/erp/sales/create');
            $createPage->assertOk()->assertSee('Allowed warehouse')
                ->assertDontSee('Restricted warehouse');
            $this->assertFalse(app()->bound('erp.current_tenant'));

            // Deactivating an assigned branch must immediately revoke invoice
            // visibility and posting access without requiring a new login.
            $allowedBranchId = $branchDb->table('warehouses')->where('id', $allowedWarehouse)->value('branch_id');
            $branchDb->table('branches')->where('id', $allowedBranchId)->update(['active' => 0]);
            $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                ->get('http://tenant-a.erp.test/erp/sales')
                ->assertOk()->assertDontSee('ALLOWED-INVOICE');
            $this->assertFalse(app()->bound('erp.current_tenant'));
            $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                ->postJson('http://tenant-a.erp.test/erp/sales/'.$allowedInvoiceId.'/post')
                ->assertForbidden();
            $this->assertFalse(app()->bound('erp.current_tenant'));
            $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                ->get('http://tenant-a.erp.test/erp/sales/'.$allowedInvoiceId.'/pdf')
                ->assertNotFound();
            $this->assertFalse(app()->bound('erp.current_tenant'));
            $branchDb->table('branches')->where('id', $allowedBranchId)->update(['active' => 1]);

            // Revoking a user's branch membership must immediately deny
            // listing, direct invoice posting and PDF access in the same session.
            $currentUserId = (int) $branchDb->table('users')
                ->where('email', 'owner@example.test')->value('id');
            $branchDb->table('branch_user')
                ->where('branch_id', $allowedBranchId)
                ->where('user_id', $currentUserId)->delete();
            try {
                $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                    ->get('http://tenant-a.erp.test/erp/sales')
                    ->assertOk()->assertDontSee('ALLOWED-INVOICE');
                $this->assertFalse(app()->bound('erp.current_tenant'));
                $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                    ->postJson('http://tenant-a.erp.test/erp/sales/'.$allowedInvoiceId.'/post')
                    ->assertForbidden();
                $this->assertFalse(app()->bound('erp.current_tenant'));
                $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                    ->get('http://tenant-a.erp.test/erp/sales/'.$allowedInvoiceId.'/pdf')
                    ->assertNotFound();
                $this->assertFalse(app()->bound('erp.current_tenant'));
                $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                    ->postJson('http://tenant-a.erp.test/erp/stock/receive', [
                        'warehouse_id' => $allowedWarehouse,
                    ])->assertForbidden();
                $this->assertFalse(app()->bound('erp.current_tenant'));

                // A crafted sale draft must not bypass branch membership.
                $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                    ->postJson('http://tenant-a.erp.test/erp/sales', [
                        'warehouse_id' => $allowedWarehouse,
                    ])->assertForbidden();
                $this->assertFalse(app()->bound('erp.current_tenant'));

                // The creation and stock screens must not expose the revoked
                // warehouse as a selectable option or an inventory location.
                $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                    ->get('http://tenant-a.erp.test/erp/sales/create')
                    ->assertOk()->assertDontSee('Allowed warehouse');
                $this->assertFalse(app()->bound('erp.current_tenant'));
                $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                    ->get('http://tenant-a.erp.test/erp/stock')
                    ->assertOk()->assertDontSee('Allowed warehouse');
                $this->assertFalse(app()->bound('erp.current_tenant'));
            } finally {
                $branchDb->table('branch_user')->insert([
                    'branch_id' => $allowedBranchId,
                    'user_id' => $currentUserId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            // Membership restoration should be effective on the next request
            // without rebuilding the tenant session or caching stale denials.
            $restoredPage = $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                ->get('http://tenant-a.erp.test/erp/sales');
            $restoredPage->assertOk()->assertSee('ALLOWED-INVOICE')
                ->assertDontSee('RESTRICTED-INVOICE');
            $this->assertFalse(app()->bound('erp.current_tenant'));
            $restoredCreate = $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                ->get('http://tenant-a.erp.test/erp/sales/create');
            $restoredCreate->assertOk()->assertSee('Allowed warehouse')
                ->assertDontSee('Restricted warehouse');
            $this->assertFalse(app()->bound('erp.current_tenant'));

            $this->flushSession();
            foreach ([$stockPermissionId, $salesPermissionId, $postPermissionId] as $newPermissionId) {
                $branchDb->table('permission_role')->where('permission_id', $newPermissionId)->delete();
                $branchDb->table('permissions')->where('id', $newPermissionId)->delete();
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

            // Scheduled jobs must reload the central tenant record and
            // reject suspension even if a job was queued while active.
            $activeCodes = $connector->withinActiveTenant((int) $tenants[0]->getKey(), function (): array {
                return DB::connection('erp_tenant')->table('customers')->pluck('code')->all();
            });
            $this->assertContains('ONLY_A', $activeCodes);
            $this->assertNull(config('database.connections.erp_tenant'));

            $tenants[0]->update(['suspended_at' => now()]);
            try {
                $executed = false;
                try {
                    $connector->withinActiveTenant((int) $tenants[0]->getKey(), function () use (&$executed): void {
                        $executed = true;
                    });
                    $this->fail('Suspended tenant was allowed to execute scheduled work.');
                } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                    $this->assertSame('Tenant is inactive or suspended.', $e->getMessage());
                }
                $this->assertFalse($executed);
                $this->assertNull(config('database.connections.erp_tenant'));
                $this->assertFalse(app()->bound('erp.current_tenant'));
            } finally {
                $tenants[0]->update(['suspended_at' => null]);
            }

            // Re-activated tenants can resume scheduled work; inactive
            // tenants and unknown IDs must never run callbacks.
            $resumed = $connector->withinActiveTenant((int) $tenants[0]->getKey(), function (): string {
                return (string) DB::connection('erp_tenant')->table('customers')->value('code');
            });
            $this->assertSame('ONLY_A', $resumed);

            $tenants[0]->update(['status' => 'inactive']);
            try {
                $executed = false;
                try {
                    $connector->withinActiveTenant((int) $tenants[0]->getKey(), function () use (&$executed): void {
                        $executed = true;
                    });
                    $this->fail('Inactive tenant executed scheduled work.');
                } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                    $this->assertSame('Tenant is inactive or suspended.', $e->getMessage());
                }
                $this->assertFalse($executed);
                $this->assertNull(config('database.connections.erp_tenant'));
            } finally {
                $tenants[0]->update(['status' => 'active']);
            }

            $unknownExecuted = false;
            try {
                $connector->withinActiveTenant(2147483647, function () use (&$unknownExecuted): void {
                    $unknownExecuted = true;
                });
                $this->fail('Unknown tenant executed scheduled work.');
            } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                $this->assertSame('Tenant is inactive or suspended.', $e->getMessage());
            }
            $this->assertFalse($unknownExecuted);
            $this->assertNull(config('database.connections.erp_tenant'));

            // Scheduled/background scopes must execute real SQL against
            // only their selected tenant and release credentials afterward.
            foreach (['A', 'B', 'A'] as $suffix) {
                $index = $suffix === 'A' ? 0 : 1;
                $codes = $connector->withinTenant($tenants[$index], function (): array {
                    return DB::connection('erp_tenant')->table('customers')->pluck('code')->all();
                });
                $this->assertContains('ONLY_'.$suffix, $codes);
                $this->assertNotContains('ONLY_'.($suffix === 'A' ? 'B' : 'A'), $codes);
                $this->assertNull(config('database.connections.erp_tenant'));
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

            // A role with customers.manage must not inherit unrelated
            // product, stock, sales or sales-post privileges.
            $this->flushSession();
            $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                ->post('http://tenant-a.erp.test/erp/login', [
                    'email' => 'owner@example.test', 'password' => 'TestPassword123!',
                ])->assertRedirect();
            // Posting a sale is a separate financial privilege and must
            // be denied before the controller can change any records.
            $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                ->postJson('http://tenant-a.erp.test/erp/sales/1/post')->assertForbidden();
            $this->assertFalse(app()->bound('erp.current_tenant'));
            foreach ([
                '/erp/products/data',
                '/erp/stock',
                '/erp/sales',
            ] as $restrictedPath) {
                $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                    ->getJson('http://tenant-a.erp.test'.$restrictedPath)->assertForbidden();
                $this->assertFalse(app()->bound('erp.current_tenant'));
            }
            $this->flushSession();

            // Permission revocation must take effect for an existing session
            // without relying on logout or stale cached role assignments.
            $this->flushSession();
            $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                ->post('http://tenant-a.erp.test/erp/login', [
                    'email' => 'owner@example.test', 'password' => 'TestPassword123!',
                ])->assertRedirect();
            $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                ->getJson('http://tenant-a.erp.test/erp/customers/data')->assertOk();

            $permissionId = DB::connection('fixture_A')->table('permissions')
                ->where('name', 'customers.manage')->value('id');
            DB::connection('fixture_A')->table('permission_role')
                ->where('permission_id', $permissionId)->delete();
            try {
                $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                    ->getJson('http://tenant-a.erp.test/erp/customers/data')->assertForbidden();
                $this->assertFalse(app()->bound('erp.current_tenant'));

                // A's revoked permission must not alter B's independent
                // role-permission tables or authorization decisions.
                $this->assertSame(1, DB::connection('fixture_B')->table('permission_role')
                    ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                    ->where('permissions.name', 'customers.manage')->count());
                $this->flushSession();
                $this->withServerVariables(['HTTP_HOST' => 'tenant-b.erp.test'])
                    ->post('http://tenant-b.erp.test/erp/login', [
                        'email' => 'owner@example.test', 'password' => 'TestPassword123!',
                    ])->assertRedirect();
                $this->withServerVariables(['HTTP_HOST' => 'tenant-b.erp.test'])
                    ->getJson('http://tenant-b.erp.test/erp/customers/data')->assertOk();
            } finally {
                $roleId = DB::connection('fixture_A')->table('roles')
                    ->where('name', 'Customer manager')->value('id');
                DB::connection('fixture_A')->table('permission_role')->insert([
                    'role_id' => $roleId, 'permission_id' => $permissionId,
                ]);
            }
            $this->flushSession();
            $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                ->post('http://tenant-a.erp.test/erp/login', [
                    'email' => 'owner@example.test', 'password' => 'TestPassword123!',
                ])->assertRedirect();
            $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                ->getJson('http://tenant-a.erp.test/erp/customers/data')->assertOk();
            $this->flushSession();

            // Removing a role assignment from an already signed-in user
            // must revoke authorization on the next request.
            $this->flushSession();
            $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                ->post('http://tenant-a.erp.test/erp/login', [
                    'email' => 'owner@example.test', 'password' => 'TestPassword123!',
                ])->assertRedirect();
            $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                ->getJson('http://tenant-a.erp.test/erp/customers/data')->assertOk();
            $roleAssignment = DB::connection('fixture_A')->table('role_user')->first();
            $this->assertNotNull($roleAssignment);
            DB::connection('fixture_A')->table('role_user')
                ->where('user_id', $roleAssignment->user_id)
                ->where('role_id', $roleAssignment->role_id)->delete();
            try {
                $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                    ->getJson('http://tenant-a.erp.test/erp/customers/data')->assertForbidden();
                $this->assertFalse(app()->bound('erp.current_tenant'));
            } finally {
                DB::connection('fixture_A')->table('role_user')->insert([
                    'user_id' => $roleAssignment->user_id,
                    'role_id' => $roleAssignment->role_id,
                ]);
            }
            $this->withServerVariables(['HTTP_HOST' => 'tenant-a.erp.test'])
                ->getJson('http://tenant-a.erp.test/erp/customers/data')->assertOk();
            $this->flushSession();

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

            // Verify suspension revokes a session that was already logged in,
            // not merely access from anonymous browsers.
            $this->flushSession();
            $this->withServerVariables(['HTTP_HOST' => 'tenant-b.erp.test'])
                ->post('http://tenant-b.erp.test/erp/login', [
                    'email' => 'owner@example.test', 'password' => 'TestPassword123!',
                ])->assertRedirect();
            $this->withServerVariables(['HTTP_HOST' => 'tenant-b.erp.test'])
                ->getJson('http://tenant-b.erp.test/erp/customers/data')->assertOk();
            $tenants[1]->update(['suspended_at' => now()]);
            $this->withServerVariables(['HTTP_HOST' => 'tenant-b.erp.test'])
                ->get('http://tenant-b.erp.test/erp/login')->assertNotFound();
            $this->withServerVariables(['HTTP_HOST' => 'tenant-b.erp.test'])
                ->getJson('http://tenant-b.erp.test/erp/customers/data')
                ->assertNotFound();
            $this->assertFalse(app()->bound('erp.current_tenant'));
            $tenants[1]->update(['suspended_at' => null]);

            // Ensure no prior tenant session can affect the suspension
            // response assertion through the session-binding middleware.
            $this->flushSession();
            auth()->forgetGuards();

            // Suspending a tenant must deny both public login and protected
            // APIs, even if its database still contains active users.
            $tenants[1]->update(['suspended_at' => now()]);
            $this->assertNotNull(
                Tenant::query()->whereKey($tenants[1]->getKey())->firstOrFail()->suspended_at,
                'Central database must persist the tenant suspension before HTTP checks.'
            );
            // Check the public route first to distinguish tenant resolution
            // failure from authentication middleware ordering on protected APIs.
            $this->withServerVariables(['HTTP_HOST' => 'tenant-b.erp.test'])
                ->get('http://tenant-b.erp.test/erp/login')->assertNotFound();
            $suspendedApi = $this->withServerVariables(['HTTP_HOST' => 'tenant-b.erp.test'])
                ->getJson('http://tenant-b.erp.test/erp/customers/data');
            // The auth guard can reject an unauthenticated protected request
            // with 401 before tenant resolution returns 404. Both deny data
            // access; the public login route above proves suspension is
            // enforced independently of the auth guard.
            $this->assertContains(
                $suspendedApi->getStatusCode(),
                [401, 404],
                'Suspended tenant must not access protected customer data.'
            );
            $this->assertFalse(app()->bound('erp.current_tenant'));

            // Restoring an unsuspended tenant must restore hostname
            // resolution without granting anonymous access to its data.
            $tenants[1]->update(['suspended_at' => null]);
            $this->withServerVariables(['HTTP_HOST' => 'tenant-b.erp.test'])
                ->get('http://tenant-b.erp.test/erp/login')->assertOk();
            $this->withServerVariables(['HTTP_HOST' => 'tenant-b.erp.test'])
                ->getJson('http://tenant-b.erp.test/erp/customers/data')
                ->assertUnauthorized();
            $this->assertFalse(app()->bound('erp.current_tenant'));
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
