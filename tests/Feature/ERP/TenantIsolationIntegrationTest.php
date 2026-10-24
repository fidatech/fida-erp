<?php

namespace Tests\Feature\ERP;

use App\Models\ERP\Central\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Two-database integration tests. Requires isolated, disposable MySQL databases.
 * Never point ERP_TEST_CENTRAL_DATABASE or ERP_TEST_TENANT_*_DATABASE at live data.
 */
final class TenantIsolationIntegrationTest extends TestCase
{
    private array $databases = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->markTestSkipped('FERP-011 integration harness disabled until tenant connector purge/transaction cleanup is proven safe.');
        if (getenv('ERP_RUN_ISOLATION_TESTS') !== '1') {
            $this->markTestSkipped('Set ERP_RUN_ISOLATION_TESTS=1 with disposable MySQL databases.');
        }
        foreach (['ERP_TEST_CENTRAL_DATABASE','ERP_TEST_TENANT_A_DATABASE','ERP_TEST_TENANT_B_DATABASE'] as $key) {
            $value = (string) env($key, '');
            if (!preg_match('/^erp_test_[a-zA-Z0-9_]+$/D', $value)) {
                $this->fail("{$key} must identify a disposable erp_test_* database.");
            }
            $this->databases[$key] = $value;
        }
        $this->assertCount(3, array_unique($this->databases), 'Test databases must be distinct.');
        $this->assertSame('testing', app()->environment(), 'Never run integration tests outside testing.');
        $mysql = config('database.connections.mysql');
        $mysql['driver'] = 'mysql';
        $mysql['database'] = $this->databases['ERP_TEST_CENTRAL_DATABASE'];
        $mysql['username'] = getenv('ERP_TEST_CENTRAL_USER');
        $mysql['password'] = getenv('ERP_TEST_CENTRAL_PASSWORD');
        $mysql['host'] = getenv('ERP_TEST_DB_HOST', '127.0.0.1');
        $mysql['port'] = getenv('ERP_TEST_DB_PORT', '3306');
        config(['database.connections.mysql' => $mysql, 'session.driver' => 'array', 'session.domain' => null]);
        DB::purge('mysql');
        // Fail closed: the suite never creates, drops or migrates databases.
        // Test fixtures must be provisioned in disposable databases beforehand.
        $central = Schema::connection('mysql');
        if (!$central->hasTable('erp_tenants') || !$central->hasTable('erp_test_sentinel')) {
            $this->fail('Central database requires ERP schema and erp_test_sentinel.');
        }
        if (DB::connection('mysql')->table('erp_tenants')->exists()) {
            $this->fail('Central test tenants table must start empty.');
        }
        foreach (['A','B'] as $suffix) {
            $connection = 'erp_test_'.$suffix;
            config(["database.connections.{$connection}" => array_replace($mysql, [
                'database' => $this->databases["ERP_TEST_TENANT_{$suffix}_DATABASE"],
                'username' => getenv("ERP_TEST_TENANT_{$suffix}_USER"),
                'password' => getenv("ERP_TEST_TENANT_{$suffix}_PASSWORD"),
            ])]);
            DB::purge($connection);
            $schema = Schema::connection($connection);
            if (!$schema->hasTable('users') || !$schema->hasTable('customers') || !$schema->hasTable('erp_test_sentinel')) {
                $this->fail("{$connection} requires ERP schema and erp_test_sentinel.");
            }
            if (DB::connection($connection)->table('users')->exists() || DB::connection($connection)->table('customers')->exists()) {
                $this->fail("{$connection} users and customers must start empty.");
            }
        }
        DB::connection('mysql')->beginTransaction();
        foreach (['A','B'] as $suffix) DB::connection('erp_test_'.$suffix)->beginTransaction();
        foreach (['A','B'] as $suffix) {
            $connection = 'erp_test_'.$suffix;
            DB::connection($connection)->table('users')->insert([
                'name'=>'Owner '.$suffix,'email'=>'owner@example.test',
                'password'=>Hash::make('SecureTestPassword123!'),'active'=>1,'locale'=>'en',
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            DB::connection($connection)->table('customers')->insert([
                'name'=>'Customer '.$suffix,'code'=>'CUSTOMER-'.$suffix,'created_at'=>now(),'updated_at'=>now(),
            ]);
            Tenant::create([
                'business_name'=>'Tenant '.$suffix,'slug'=>'tenant-'.strtolower($suffix),
                'domain'=>'tenant-'.strtolower($suffix).'.erp.test',
                'database_name'=>$this->databases["ERP_TEST_TENANT_{$suffix}_DATABASE"],
                'database_username'=>(string) getenv("ERP_TEST_TENANT_{$suffix}_USER"),
                'database_password'=>(string) getenv("ERP_TEST_TENANT_{$suffix}_PASSWORD"),
                'status'=>'active',
            ]);
        }
    }

    protected function tearDown(): void
    {
        try {
            foreach (['erp_test_A','erp_test_B'] as $connection) {
                if (DB::connection($connection)->transactionLevel() > 0) DB::connection($connection)->rollBack();
            }
            if (DB::connection('mysql')->transactionLevel() > 0) DB::connection('mysql')->rollBack();
        } finally {
            foreach (['erp_tenant','erp_test_A','erp_test_B','mysql'] as $connection) DB::purge($connection);
            parent::tearDown();
        }
    }

    public function test_login_succeeds_against_each_tenant_database(): void
    {
        $a = $this->withServerVariables(['HTTP_HOST'=>'tenant-a.erp.test'])
            ->post('/erp/login', ['email'=>'owner@example.test','password'=>'SecureTestPassword123!']);
        $a->assertRedirect(route('erp.dashboard'));
        auth('erp_tenant')->logout();
        $this->flushSession();
        $b = $this->withServerVariables(['HTTP_HOST'=>'tenant-b.erp.test'])
            ->post('/erp/login', ['email'=>'owner@example.test','password'=>'SecureTestPassword123!']);
        $b->assertRedirect(route('erp.dashboard'));
    }

    public function test_suspended_tenant_cannot_access_login(): void
    {
        Tenant::query()->where('slug','tenant-b')->update(['status'=>'suspended','suspended_at'=>now()]);
        $this->withServerVariables(['HTTP_HOST'=>'tenant-b.erp.test'])
            ->get('/erp/login')->assertNotFound();
    }

    public function test_unknown_hostname_cannot_resolve_tenant(): void
    {
        $this->withServerVariables(['HTTP_HOST'=>'unknown.erp.test'])
            ->get('/erp/login')->assertNotFound();
    }
}
