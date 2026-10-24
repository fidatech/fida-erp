<?php

namespace Tests\Feature\ERP;

use App\Models\ERP\Central\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
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
        if (env('ERP_RUN_ISOLATION_TESTS') !== '1') {
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
        $mysql = config('database.connections.mysql');
        $mysql['driver'] = 'mysql';
        $mysql['database'] = $this->databases['ERP_TEST_CENTRAL_DATABASE'];
        $mysql['username'] = env('ERP_TEST_CENTRAL_USER');
        $mysql['password'] = env('ERP_TEST_CENTRAL_PASSWORD');
        $mysql['host'] = env('ERP_TEST_DB_HOST', '127.0.0.1');
        $mysql['port'] = env('ERP_TEST_DB_PORT', '3306');
        config(['database.connections.mysql' => $mysql, 'session.driver' => 'array', 'session.domain' => null]);
        DB::purge('mysql');
        // Never use migrate:fresh here: these tests only create tables if absent
        // and are designed to run in disposable databases exclusively.
        $central = Schema::connection('mysql');
        if ($central->hasTable('erp_tenants')) {
            $this->fail('Central test database must start empty.');
        }
        $central->create('erp_tenants', function (Blueprint $t) {
            $t->id(); $t->string('domain')->unique(); $t->string('slug')->unique();
            $t->string('business_name'); $t->string('database_name'); $t->string('database_username');
            $t->text('database_password'); $t->string('status'); $t->timestamp('suspended_at')->nullable();
            $t->timestamp('trial_ends_at')->nullable(); $t->timestamps();
        });
        foreach (['A','B'] as $suffix) {
            $connection = 'erp_test_'.$suffix;
            config(["database.connections.{$connection}" => array_replace($mysql, [
                'database' => $this->databases["ERP_TEST_TENANT_{$suffix}_DATABASE"],
                'username' => env("ERP_TEST_TENANT_{$suffix}_USER"),
                'password' => env("ERP_TEST_TENANT_{$suffix}_PASSWORD"),
            ])]);
            DB::purge($connection);
            $schema = Schema::connection($connection);
            if ($schema->hasTable('users')) $this->fail("{$connection} must start empty.");
            $schema->create('users', function (Blueprint $t) {
                $t->id(); $t->string('name'); $t->string('email')->unique(); $t->string('password');
                $t->boolean('active')->default(true); $t->string('locale')->default('en');
                $t->rememberToken(); $t->timestamps();
            });
            $schema->create('customers', function (Blueprint $t) {
                $t->id(); $t->string('name'); $t->string('code')->unique(); $t->timestamps();
            });
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
                'database_username'=>(string) env("ERP_TEST_TENANT_{$suffix}_USER"),
                'database_password'=>(string) env("ERP_TEST_TENANT_{$suffix}_PASSWORD"),
                'status'=>'active',
            ]);
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
