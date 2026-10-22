<?php
namespace App\Console\Commands;
use App\Models\ERP\Central\Tenant;
use App\Services\ERP\TenantConnector;
use Database\Seeders\ErpDemoTenantSeeder;
use Illuminate\Console\Command;
use Throwable;

final class SeedErpDemoTenant extends Command
{
    protected $signature='erp:demo-seed {slug} {--confirm}';
    protected $description='Fill an isolated staging demo tenant with business transactions through services';
    public function handle(TenantConnector $connector):int
    {
        $slug=(string)$this->argument('slug');
        if(!app()->environment(['staging','local','testing'])||!$this->option('confirm')||
            !preg_match('/^demo-[a-z0-9-]+$/D',$slug)){
            $this->error('Only confirmed demo-* tenants in staging/local/testing can be seeded.');
            return self::FAILURE;
        }
        $tenant=Tenant::query()->where('slug',$slug)->where('status','active')->first();
        if(!$tenant){$this->error('Provision an active demo tenant first.');return self::FAILURE;}
        try{
            $connector->connect($tenant);
            (new ErpDemoTenantSeeder)->setCommand($this)->run();
            return self::SUCCESS;
        }catch(Throwable $e){$this->error('Refused or failed: '.$e->getMessage());return self::FAILURE;}
        finally{$connector->disconnect();}
    }
}
