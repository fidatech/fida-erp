<?php
namespace Tests\Feature;

use App\Services\ERP\TenantBootstrapService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ErpTenantBootstrapTest extends TestCase
{
    protected function setUp():void
    {
        parent::setUp();
        config()->set('database.connections.erp_tenant', [
            'driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true
        ]);
        DB::purge('erp_tenant');
        $this->assertSame(0,Artisan::call('migrate',[
            '--database'=>'erp_tenant','--path'=>database_path('migrations/erp_tenant'),
            '--realpath'=>true,'--force'=>true
        ]),Artisan::output());
    }

    protected function tearDown():void
    {
        DB::disconnect('erp_tenant');
        parent::tearDown();
    }

    public function test_bootstrap_reuses_migration_permissions_and_is_idempotent():void
    {
        $db=DB::connection('erp_tenant');
        $this->assertTrue($db->table('permissions')->where('name','stock.transfer')->exists());
        $registration=(object)[
            'name'=>'Staging QA Owner','company'=>'Test Trading',
            'email'=>'qa@example.test','password_hash'=>'password-not-used-in-test'
        ];
        $service=app(TenantBootstrapService::class);
        $service->initialize($registration);
        $service->initialize($registration);
        $this->assertSame(1,$db->table('company_settings')->count());
        $this->assertSame(1,$db->table('users')->count());
        $this->assertSame(1,$db->table('warehouses')->count());
        $this->assertSame(1,$db->table('roles')->where('name','owner')->count());
        $this->assertSame(1,$db->table('permissions')->where('name','stock.transfer')->count());
        $this->assertSame(11,$db->table('permission_role')->count());
        $this->assertSame(1,$db->table('role_user')->count());
    }
}
