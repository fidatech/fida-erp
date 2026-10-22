<?php
namespace Tests\Feature;

use App\Services\ERP\FinancialReportService;
use Database\Seeders\ErpDemoTenantSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ErpDemoTenantSeederTest extends TestCase
{
    protected function setUp():void
    {
        parent::setUp();
        config()->set('database.connections.erp_tenant',[
            'driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true
        ]);
        DB::purge('erp_tenant');
        $this->assertSame(0,Artisan::call('migrate',[
            '--database'=>'erp_tenant',
            '--path'=>database_path('migrations/erp_tenant'),
            '--realpath'=>true,'--force'=>true
        ]),Artisan::output());
        app()->instance('erp.current_tenant',(object)['slug'=>'demo-integration','business_name'=>'FidaERP Demo']);
        $db=DB::connection('erp_tenant');$now=now();
        $db->table('company_settings')->insert(['name'=>'Demo Business','base_currency'=>'AFN','locale'=>'en',
            'timezone'=>'Asia/Kabul','created_at'=>$now,'updated_at'=>$now]);
        $branch=$db->table('branches')->insertGetId(['code'=>'HQ','name'=>'Main Branch','active'=>1,
            'created_at'=>$now,'updated_at'=>$now]);
        $db->table('warehouses')->insert(['branch_id'=>$branch,'code'=>'MAIN','name'=>'Main Warehouse',
            'created_at'=>$now,'updated_at'=>$now]);
        foreach([['AFN','Afghan Afghani',2],['USD','US Dollar',2]] as [$code,$name,$minor])
            $db->table('currencies')->insert(['code'=>$code,'name'=>$name,'minor_units'=>$minor,'active'=>1]);
        $db->table('fiscal_periods')->insert(['name'=>date('Y'),'starts_on'=>date('Y').'-01-01',
            'ends_on'=>date('Y').'-12-31','is_closed'=>0,'created_at'=>$now,'updated_at'=>$now]);
        foreach([['1000','Assets','asset',false],['1100','Receivables','asset',true],
            ['1200','Cash','asset',true],['1300','Inventory','asset',true],
            ['2000','Liabilities','liability',false],['2100','Payables','liability',true],
            ['2200','Tax Payable','liability',true],['3000','Equity','equity',false],
            ['3100','Capital','equity',true],['4000','Income','income',false],
            ['4100','Revenue','income',true],['5000','Expenses','expense',false],
            ['5100','COGS','expense',true]] as [$code,$name,$type,$postable])
            $db->table('accounts')->insert(['code'=>$code,'name'=>$name,'type'=>$type,
                'is_postable'=>$postable,'active'=>1,'created_at'=>$now,'updated_at'=>$now]);
    }

    protected function tearDown():void
    {
        DB::disconnect('erp_tenant');app()->forgetInstance('erp.current_tenant');
        parent::tearDown();
    }

    public function test_demo_seed_creates_posted_records_and_is_idempotent():void
    {
        $seed=new ErpDemoTenantSeeder;
        $seed->run();
        $db=DB::connection('erp_tenant');
        $this->assertSame(25,$db->table('products')->count());
        $this->assertSame(45,$db->table('sales_invoices')->count());
        $this->assertSame(17,$db->table('goods_receipts')->count());
        $this->assertSame(15,$db->table('customers')->count());
        $this->assertSame(20,$db->table('employees')->count());
        $this->assertSame(1,$db->table('demo_seed_runs')->count());
        $this->assertTrue(app(FinancialReportService::class)->trialBalance(now()->toDateString())['balanced']);
        $this->assertTrue(app(FinancialReportService::class)->balanceSheet(now()->toDateString())['balanced']);
        $this->assertGreaterThan(0,$db->table('customer_payments')->count());
        $this->assertGreaterThan(0,$db->table('stock_movements')->count());
        $seed->run();
        $this->assertSame(45,$db->table('sales_invoices')->count());
    }

    public function test_reference_dashboard_and_reporting_screens_render_from_seeded_data():void
    {
        (new ErpDemoTenantSeeder)->run();
        $db=DB::connection('erp_tenant');$now=now();
        $id=$db->table('users')->insertGetId([
            'name'=>'Demo Owner','email'=>'demo-owner@example.test',
            'password'=>\Illuminate\Support\Facades\Hash::make('SafeDemoPassword123!'),
            'locale'=>'en','active'=>1,'created_at'=>$now,'updated_at'=>$now
        ]);
        $owner=\App\Models\ERP\Tenant\TenantUser::query()->findOrFail($id);
        auth('erp_tenant')->login($owner);
        \Illuminate\Support\Facades\View::share('errors',new \Illuminate\Support\ViewErrorBag());
        $metrics=app(\App\Services\ERP\BusinessMetricsService::class)->snapshot();
        $dashboard=view('erp.dashboard',compact('metrics'))->render();
        $this->assertStringContainsString('Revenue & Expense Trend',$dashboard);
        $this->assertStringContainsString('Recent Transactions',$dashboard);
        $this->assertStringContainsString('Quick Actions',$dashboard);
        $this->assertStringContainsString('FidaERP',$dashboard);
        $kpi=view('erp.kpi.index',compact('metrics'))->render();
        $this->assertStringContainsString('KPI Overview',$kpi);
        $analytics=view('erp.analytics.index',compact('metrics'))->render();
        $this->assertStringContainsString('Charts & Analytics',$analytics);
        $profile=view('erp.profile')->render();
        $this->assertStringContainsString('Personal Information',$profile);
        $settings=view('erp.settings.index',[
            'company'=>$db->table('company_settings')->first(),
            'branches'=>$db->table('branches')->get(),
            'periods'=>$db->table('fiscal_periods')->get(),
            'currency'=>'AFN','usersCount'=>1
        ])->render();
        $this->assertStringContainsString('Branding & Appearance',$settings);
        auth('erp_tenant')->logout();
    }

    public function test_non_demo_slug_is_rejected_before_any_records_are_written():void
    {
        app()->instance('erp.current_tenant',(object)['slug'=>'customer-actual','business_name'=>'Actual']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Only dedicated demo');
        (new ErpDemoTenantSeeder)->run();
    }
}
