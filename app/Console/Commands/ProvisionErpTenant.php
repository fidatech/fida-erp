<?php
namespace App\Console\Commands;

use App\Models\ERP\Central\Tenant;
use App\Services\ERP\TenantConnector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/** Provision into an existing database. No schema creation from web requests. */
final class ProvisionErpTenant extends Command
{
    protected $signature = 'erp:provision {slug} {domain} {database} {--business=Company} {--email=} {--db-user=}';
    protected $description = 'Install a tenant into its pre-created MySQL database';
    public function handle(TenantConnector $connector): int
    {
        $slug=$this->argument('slug');$domain=strtolower($this->argument('domain'));$database=$this->argument('database');
        if(!preg_match('/^[a-z0-9][a-z0-9-]{1,49}$/D',$slug)||!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/D',$database)||!filter_var('https://'.$domain,FILTER_VALIDATE_URL)){
            $this->error('Invalid slug, hostname or database');return self::FAILURE;
        }
         $dbUser=$this->option('db-user')?:$this->ask('Tenant database username');
        if(!preg_match('/^[a-zA-Z0-9_]{1,32}$/D',(string)$dbUser)){$this->error('Invalid database username');return self::FAILURE;}
        $dbPassword=$this->secret('Tenant database password');
        if(!$dbPassword){$this->error('Database password cannot be blank');return self::FAILURE;}
        $email=$this->option('email')?:$this->ask('Tenant owner email');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)){$this->error('Invalid email');return self::FAILURE;}
        $password=$this->secret('New tenant owner password (not passed on command line)');
        if(strlen((string)$password)<12){$this->error('Use a password with at least 12 characters');return self::FAILURE;}
        $name=(string)$this->option('business');
        $tenant=new Tenant(['business_name'=>$name,'slug'=>$slug,'domain'=>$domain,'database_name'=>$database,'database_username'=>$dbUser,'database_password'=>$dbPassword,'status'=>'provisioning']);
        try {
            // Insert metadata only after tenant DB connectivity is checked.
            $connector->connect($tenant);
            DB::connection('erp_tenant')->getPdo();
            $tenant->save();
            Artisan::call('migrate',['--database'=>'erp_tenant','--path'=>'database/migrations/erp_tenant','--force'=>true]);
            $db=DB::connection('erp_tenant');
            $db->transaction(function()use($db,$name,$email,$password){
                $db->table('company_settings')->insert(['name'=>$name,'base_currency'=>'AFN','locale'=>'en','timezone'=>'Asia/Kabul','created_at'=>now(),'updated_at'=>now()]);
                $headOfficeId=$db->table('branches')->insertGetId(['code'=>'HQ','name'=>'Head Office','active'=>1,'created_at'=>now(),'updated_at'=>now()]);
                foreach([['AFN','Afghan Afghani',2],['USD','US Dollar',2],['PKR','Pakistani Rupee',2],['IRR','Iranian Rial',0],['EUR','Euro',2],['AED','UAE Dirham',2]] as [$code,$currency,$minor]) {
                    $db->table('currencies')->insert(['code'=>$code,'name'=>$currency,'minor_units'=>$minor,'active'=>1]);
                }
                foreach([['1000','Assets','asset',null,0],['1100','Trade Receivables','asset','1000',1],['1300','Inventory','asset','1000',1],
                    ['2000','Liabilities','liability',null,0],['2100','Trade Payables','liability','2000',1],['2200','Sales Tax Payable','liability','2000',1],
                    ['3000','Equity','equity',null,0],['4000','Income','income',null,0],['4100','Sales Revenue','income','4000',1],
                    ['5000','Expenses','expense',null,0],['5100','Cost of Goods Sold','expense','5000',1]] as [$code,$n,$type,$parent,$postable]){
                    $parentId=$parent?$db->table('accounts')->where('code',$parent)->value('id'):null;
                    $db->table('accounts')->insert(['code'=>$code,'name'=>$n,'type'=>$type,'parent_id'=>$parentId,'is_postable'=>$postable,'active'=>1,'created_at'=>now(),'updated_at'=>now()]);
                }
                $year=(int)date('Y');$db->table('fiscal_periods')->insert(['name'=>(string)$year,'starts_on'=>$year.'-01-01','ends_on'=>$year.'-12-31','is_closed'=>0,'created_at'=>now(),'updated_at'=>now()]);
                $db->table('warehouses')->insert(['branch_id'=>$headOfficeId,'code'=>'MAIN','name'=>'Main Warehouse','created_at'=>now(),'updated_at'=>now()]);
                $roleId=$db->table('roles')->insertGetId(['name'=>'owner','created_at'=>now(),'updated_at'=>now()]);
                foreach(['dashboard.view','customers.manage','products.manage','stock.manage','sales.manage','sales.post','reports.view','accounts.view','settings.manage'] as $perm){
                    $id=$db->table('permissions')->insertGetId(['name'=>$perm]);$db->table('permission_role')->insert(['role_id'=>$roleId,'permission_id'=>$id]);
                }
                $id=$db->table('users')->insertGetId(['name'=>'Business Owner','email'=>$email,'password'=>Hash::make($password),'locale'=>'en','active'=>1,'created_at'=>now(),'updated_at'=>now()]);
                $db->table('role_user')->insert(['role_id'=>$roleId,'user_id'=>$id]);
                // Explicitly assign the new owner to the new HQ branch.
                // Do not grant memberships to other users or existing tenants.
                $db->table('branch_user')->insert(['branch_id'=>$headOfficeId,'user_id'=>$id,'created_at'=>now(),'updated_at'=>now()]);
            });
            $tenant->status='active';$tenant->save();
            $this->info('Tenant created: https://'.$domain.'/erp/login');
            return self::SUCCESS;
        }catch(Throwable $e){
            $this->error('Provisioning failed: '.$e->getMessage().' (remove or inspect provisioning tenant and database before retrying)');
            return self::FAILURE;
        }finally{$connector->disconnect();}
    }
}
