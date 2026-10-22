<?php
namespace Tests\Feature;

use App\Services\ERP\AutomaticTenantProvisioner;
use App\Services\ERP\TenantDomainVerifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

final class ErpAutomaticProvisioningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('erp.central_connection','mysql');
        config()->set('database.connections.mysql',[
            'driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true
        ]);
        DB::purge('mysql');
        Schema::connection('mysql')->create('erp_registration_requests',function(Blueprint $t){
            $t->id();$t->string('status');$t->string('company');$t->string('name');
            $t->string('email');$t->string('password_hash');$t->timestamps();
        });
    }
    protected function tearDown():void
    {
        DB::disconnect('mysql');
        parent::tearDown();
    }
    private function registration(string $status):int
    {
        return DB::connection('mysql')->table('erp_registration_requests')->insertGetId([
            'status'=>$status,'name'=>'Owner','company'=>'My Trading','email'=>'test@example.test',
            'password_hash'=>'not-for-login','created_at'=>now(),'updated_at'=>now()
        ]);
    }
    public function test_pending_registration_cannot_provision_before_administrator_review():void
    {
        $id=$this->registration('pending');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Administrator review required');
        app(AutomaticTenantProvisioner::class)->provision($id);
    }
    public function test_approved_registration_is_idempotent():void
    {
        $id=$this->registration('approved');
        app(AutomaticTenantProvisioner::class)->provision($id);
        $this->assertSame('approved',DB::connection('mysql')->table('erp_registration_requests')->value('status'));
    }
    public function test_tenant_domain_verifier_refuses_platform_hostname_and_arbitrary_hosts():void
    {
        config()->set('app.url','https://stage.fidaerp.com');
        $verifier=app(TenantDomainVerifier::class);
        $this->assertFalse($verifier->isReady('stage.fidaerp.com'));
        $this->assertFalse($verifier->isReady('evil.example.com'));
        $this->assertFalse($verifier->isReady('stage.fidaerp.com.attacker.com'));
    }
}
