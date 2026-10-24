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
        config(['database.connections.mysql' => $config, 'session.driver' => 'array', 'session.domain' => null]);
        DB::purge('mysql');

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
            }

            foreach (['A', 'B'] as $suffix) {
                $host = 'tenant-'.strtolower($suffix).'.erp.test';
                $response = $this->withServerVariables(['HTTP_HOST' => $host])
                    ->post('http://'.$host.'/erp/login', [
                        'email' => 'owner@example.test', 'password' => 'TestPassword123!',
                    ]);
                $response->assertRedirect();
                $this->assertStringContainsString('/erp', $response->headers->get('Location', ''));
                $this->assertFalse(app()->bound('erp.current_tenant'));
            }

            $this->withServerVariables(['HTTP_HOST' => 'unknown.erp.test'])
                ->get('http://unknown.erp.test/erp/login')->assertNotFound();

            $tenants[1]->update(['suspended_at' => now()]);
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
