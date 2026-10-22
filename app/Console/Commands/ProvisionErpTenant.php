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
    protected $signature = 'erp:provision {slug} {domain} {database} {--business=Company} {--email=} {--db-user=} {--registration=}';
    protected $description = 'Install a tenant into its pre-created MySQL database';
    public function handle(TenantConnector $connector): int
    {
        $slug=$this->argument('slug');$domain=strtolower($this->argument('domain'));$database=$this->argument('database');
        if(!preg_match('/^[a-z0-9][a-z0-9-]{1,49}$/D',$slug)||!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/D',$database)||!preg_match('/^(?=.{4,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z]{2,63}$/D',$domain)){
            $this->error('Invalid slug, hostname or database');return self::FAILURE;
        }
         $dbUser=$this->option('db-user')?:$this->ask('Tenant database username');
        if(!preg_match('/^[a-zA-Z0-9_]{1,32}$/D',(string)$dbUser)){$this->error('Invalid database username');return self::FAILURE;}
        $dbPassword=$this->secret('Tenant database password');
        if(!$dbPassword){$this->error('Database password cannot be blank');return self::FAILURE;}
        $centralHost=parse_url((string)config('app.url'),PHP_URL_HOST);
        if($centralHost && $domain===strtolower($centralHost)){
            $this->error('Tenant domain must not equal the platform hostname');return self::FAILURE;
        }
        if(Tenant::query()->where('domain',$domain)->orWhere('slug',$slug)->orWhere('database_name',$database)->exists()){
            $this->error('Tenant domain, slug or database is already registered');return self::FAILURE;
        }
        $registrationId=$this->option('registration');
        $registration=null;
        if($registrationId!==null){
            if(!ctype_digit((string)$registrationId)){$this->error('Invalid registration ID');return self::FAILURE;}
            $registration=DB::connection(config('erp.central_connection','mysql'))->table('erp_registration_requests')->where('id',(int)$registrationId)->first();
            if(!$registration || $registration->status!=='reviewed'){$this->error('Registration must exist and be reviewed before provisioning');return self::FAILURE;}
            if($this->option('email') && strcasecmp((string)$this->option('email'),$registration->email)!==0){$this->error('Owner email differs from reviewed registration');return self::FAILURE;}
            if(DB::connection(config('erp.central_connection','mysql'))->table('erp_tenants')->where('domain',$domain)->orWhere('slug',$slug)->exists()){$this->error('Tenant slug or domain already exists');return self::FAILURE;}
        }
        $email=$registration ? $registration->email : ($this->option('email')?:$this->ask('Tenant owner email'));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)){$this->error('Invalid email');return self::FAILURE;}
        $password=$registration ? null : $this->secret('New tenant owner password (not passed on command line)');
        if(!$registration && strlen((string)$password)<12){$this->error('Use a password with at least 12 characters');return self::FAILURE;}
        $name=$registration ? (string)$registration->company : (string)$this->option('business');
        $tenant=new Tenant(['business_name'=>$name,'slug'=>$slug,'domain'=>$domain,'database_name'=>$database,'database_username'=>$dbUser,'database_password'=>$dbPassword,'status'=>'provisioning']);
        try {
            // Insert metadata only after tenant DB connectivity is checked.
            $connector->connect($tenant);
            DB::connection('erp_tenant')->getPdo();
            $tenant->save();
            $migrationExit=Artisan::call('migrate',['--database'=>'erp_tenant','--path'=>'database/migrations/erp_tenant','--force'=>true]);
            if($migrationExit!==self::SUCCESS){
                throw new \RuntimeException('Tenant database migrations failed; tenant remains in provisioning status');
            }
            $db=DB::connection('erp_tenant');
            $db->transaction(function()use($db,$name,$email,$password,$registration){
                $db->table('company_settings')->insert(['name'=>$name,'base_currency'=>'AFN','locale'=>'en','timezone'=>'Asia/Kabul','created_at'=>now(),'updated_at'=>now()]);
                $db->table('branches')->insert(['code'=>'HQ','name'=>'Head Office','active'=>1,'created_at'=>now(),'updated_at'=>now()]);
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
                $db->table('warehouses')->insert(['branch_id'=>1,'code'=>'MAIN','name'=>'Main Warehouse','created_at'=>now(),'updated_at'=>now()]);
                $roleId=$db->table('roles')->insertGetId(['name'=>'owner','created_at'=>now(),'updated_at'=>now()]);
                foreach(['dashboard.view','customers.manage','products.manage','stock.manage','sales.manage','sales.post','reports.view','accounts.view','settings.manage'] as $perm){
                    $id=$db->table('permissions')->insertGetId(['name'=>$perm]);$db->table('permission_role')->insert(['role_id'=>$roleId,'permission_id'=>$id]);
                }
                $id=$db->table('users')->insertGetId(['name'=>'Business Owner','email'=>$email,'password'=>$registration ? $registration->password_hash : Hash::make($password),'locale'=>'en','active'=>1,'created_at'=>now(),'updated_at'=>now()]);
                $db->table('role_user')->insert(['role_id'=>$roleId,'user_id'=>$id]);
            });
            if($registration){
                DB::connection(config('erp.central_connection','mysql'))->transaction(function()use($registration,$tenant){
                    $db=DB::connection(config('erp.central_connection','mysql'));
                    $updated=$db->table('erp_registration_requests')->where('id',$registration->id)->where('status','reviewed')->update(['status'=>'approved','updated_at'=>now()]);
                    if($updated!==1) throw new \RuntimeException('Registration status changed during provisioning');
                    $db->table('erp_platform_audit_logs')->insert(['actor'=>'artisan:erp:provision','event'=>'registration.provisioned','tenant_id'=>$tenant->id,'context'=>json_encode(['registration_id'=>$registration->id,'email'=>$registration->email]),'created_at'=>now(),'updated_at'=>now()]);
                });
            }
            $tenant->status='active';$tenant->save();
            $this->info('Tenant created: https://'.$domain.'/erp/login');
            return self::SUCCESS;
        }catch(Throwable $e){
            $this->error('Provisioning failed: '.$e->getMessage().' (remove or inspect provisioning tenant and database before retrying)');
            return self::FAILURE;
        }finally{$connector->disconnect();}
    }
}
