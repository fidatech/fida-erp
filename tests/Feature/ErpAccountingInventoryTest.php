<?php
namespace Tests\Feature;

use App\Services\ERP\FinancialReportService;
use App\Services\ERP\GoodsReceiptService;
use App\Services\ERP\JournalService;
use App\Services\ERP\ManualJournalService;
use App\Services\ERP\SaleService;
use App\Services\ERP\SettlementService;
use App\Services\ERP\StockTransferService;
use DomainException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ErpAccountingInventoryTest extends TestCase
{
    private int $warehouseA;
    private int $warehouseB;
    private int $variant;
    private int $customer;
    private int $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.erp_tenant', [
            'driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true
        ]);
        DB::purge('erp_tenant');
        $exit=Artisan::call('migrate',[
            '--database'=>'erp_tenant',
            '--path'=>database_path('migrations/erp_tenant'),
            '--realpath'=>true,'--force'=>true
        ]);
        $this->assertSame(0,$exit,Artisan::output());
        $db=DB::connection('erp_tenant');
        $db->table('company_settings')->insert(['name'=>'Test Business','base_currency'=>'AFN',
            'locale'=>'en','timezone'=>'Asia/Kabul','created_at'=>now(),'updated_at'=>now()]);
        $db->table('currencies')->insert(['code'=>'AFN','name'=>'Afghani','minor_units'=>2,'active'=>1]);
        $db->table('fiscal_periods')->insert(['name'=>'FY '.date('Y'),'starts_on'=>date('Y').'-01-01',
            'ends_on'=>date('Y').'-12-31','is_closed'=>0,'created_at'=>now(),'updated_at'=>now()]);
        foreach ([
            ['1100','Receivables','asset'], ['1200','Cash','asset'], ['1300','Inventory','asset'],
            ['2100','Payables','liability'], ['2200','Sales tax','liability'], ['3100','Contributed capital','equity'],
            ['4100','Sales revenue','income'], ['5100','Cost of goods sold','expense']
        ] as [$code,$name,$type]) {
            $db->table('accounts')->insert(['code'=>$code,'name'=>$name,'type'=>$type,
                'is_postable'=>1,'active'=>1,'created_at'=>now(),'updated_at'=>now()]);
        }
        $branch=$db->table('branches')->insertGetId(['code'=>'HQ','name'=>'HQ','active'=>1,
            'created_at'=>now(),'updated_at'=>now()]);
        $this->warehouseA=$db->table('warehouses')->insertGetId(['branch_id'=>$branch,'code'=>'MAIN',
            'name'=>'Main','created_at'=>now(),'updated_at'=>now()]);
        $this->warehouseB=$db->table('warehouses')->insertGetId(['branch_id'=>$branch,'code'=>'SECOND',
            'name'=>'Second','created_at'=>now(),'updated_at'=>now()]);
        $db->table('units')->insert(['code'=>'PCS','name'=>'Piece','type'=>'count','factor_to_base'=>1,
            'created_at'=>now(),'updated_at'=>now()]);
        $product=$db->table('products')->insertGetId(['sku'=>'P100','name'=>'Test Product','base_unit_id'=>1,
            'active'=>1,'created_at'=>now(),'updated_at'=>now()]);
        $this->variant=$db->table('product_variants')->insertGetId(['product_id'=>$product,'sku'=>'P100-1',
            'sale_price'=>40,'average_cost'=>0,'active'=>1,'created_at'=>now(),'updated_at'=>now()]);
        $this->customer=$db->table('customers')->insertGetId(['code'=>'CUS-1','name'=>'Buyer',
            'active'=>1,'created_at'=>now(),'updated_at'=>now()]);
        $this->supplier=$db->table('suppliers')->insertGetId(['code'=>'SUP-1','name'=>'Supplier',
            'active'=>1,'created_at'=>now(),'updated_at'=>now()]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('erp_tenant');
        parent::tearDown();
    }

    private function receipt(): int
    {
        return app(GoodsReceiptService::class)->receive([
            'request_key'=>(string)Str::uuid(),'supplier_id'=>$this->supplier,
            'warehouse_id'=>$this->warehouseA,'currency_code'=>'AFN','fx_rate'=>'1',
            'lines'=>[['product_variant_id'=>$this->variant,'quantity'=>'10','unit_cost'=>'25']]
        ]);
    }

    public function test_goods_receipt_invoice_and_statements_reconcile(): void
    {
        $this->receipt();
        $sale=app(SaleService::class)->draft([
            'request_key'=>(string)Str::uuid(),'customer_id'=>$this->customer,
            'warehouse_id'=>$this->warehouseA,'currency_code'=>'AFN','fx_rate'=>'1',
            'lines'=>[['product_variant_id'=>$this->variant,'quantity'=>'3','unit_price'=>'40']]
        ]);
        app(SaleService::class)->post($sale);
        $db=DB::connection('erp_tenant');
        $this->assertSame('7',(string)(float)$db->table('stock_balances')->where('warehouse_id',$this->warehouseA)->value('quantity'));
        $this->assertSame(2,$db->table('journals')->count());
        $trial=app(FinancialReportService::class)->trialBalance(now()->toDateString());
        $this->assertTrue($trial['balanced']);
        $pnl=app(FinancialReportService::class)->incomeStatement(date('Y').'-01-01',now()->toDateString());
        $this->assertSame('45.000000',$pnl['profit']);
        $balance=app(FinancialReportService::class)->balanceSheet(now()->toDateString());
        $this->assertTrue($balance['balanced']);
        $this->assertSame('295.000000',$balance['totals']['asset']);
    }

    public function test_closed_period_and_unbalanced_manual_journal_rollback(): void
    {
        $input=[
            'request_key'=>(string)Str::uuid(),'date'=>now()->toDateString(),'description'=>'Test balancing',
            'lines'=>[
                ['account_code'=>'1300','debit'=>'100','credit'=>'0'],
                ['account_code'=>'2100','debit'=>'0','credit'=>'99'],
            ]
        ];
        try {
            app(ManualJournalService::class)->post($input);
            $this->fail('Unbalanced journal should fail');
        } catch (DomainException $e) {
            $this->assertSame('Unbalanced journal',$e->getMessage());
        }
        $db=DB::connection('erp_tenant');
        $this->assertSame(0,$db->table('manual_vouchers')->count());
        $db->table('fiscal_periods')->update(['is_closed'=>1]);
        $input['lines'][1]['credit']='100';
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('No open fiscal period');
        app(ManualJournalService::class)->post($input);
    }

    public function test_manual_journal_and_stock_transfer_are_idempotent(): void
    {
        $key=(string)Str::uuid();
        $input=[
            'request_key'=>$key,'date'=>now()->toDateString(),'description'=>'Owner adjustment',
            'lines'=>[
                ['account_code'=>'1300','debit'=>'100','credit'=>'0'],
                ['account_code'=>'2100','debit'=>'0','credit'=>'100'],
            ]
        ];
        $first=app(ManualJournalService::class)->post($input);
        $this->assertSame($first,app(ManualJournalService::class)->post($input));
        $this->assertSame(1,DB::connection('erp_tenant')->table('journals')->count());
        $this->receipt();
        $transfer=[
            'request_key'=>(string)Str::uuid(),'from_warehouse_id'=>$this->warehouseA,
            'to_warehouse_id'=>$this->warehouseB,'product_variant_id'=>$this->variant,'quantity'=>'4'
        ];
        $id=app(StockTransferService::class)->transfer($transfer);
        $this->assertSame($id,app(StockTransferService::class)->transfer($transfer));
        $db=DB::connection('erp_tenant');
        $this->assertSame(1,$db->table('stock_transfers')->count());
        $this->assertSame(3,$db->table('stock_movements')->count());
        $this->assertSame('6',(string)(float)$db->table('stock_balances')->where('warehouse_id',$this->warehouseA)->value('quantity'));
        $this->assertSame('4',(string)(float)$db->table('stock_balances')->where('warehouse_id',$this->warehouseB)->value('quantity'));
    }

    public function test_insufficient_stock_transfer_rolls_back_both_sides(): void
    {
        $this->receipt();
        $input=[
            'request_key'=>(string)Str::uuid(),'from_warehouse_id'=>$this->warehouseA,
            'to_warehouse_id'=>$this->warehouseB,'product_variant_id'=>$this->variant,'quantity'=>'11'
        ];
        try {
            app(StockTransferService::class)->transfer($input);
            $this->fail('Insufficient stock should fail');
        } catch (DomainException $e) {
            $this->assertSame('Insufficient available stock',$e->getMessage());
        }
        $db=DB::connection('erp_tenant');
        $this->assertSame(0,$db->table('stock_transfers')->count());
        $this->assertSame(1,$db->table('stock_movements')->count());
        $this->assertSame(0,$db->table('stock_balances')->where('warehouse_id',$this->warehouseB)->count());
    }

    public function test_customer_receipts_support_partial_payment_and_prevent_duplicate_posting(): void
    {
        $this->receipt();
        $sale=app(SaleService::class)->draft([
            'request_key'=>(string)Str::uuid(),'customer_id'=>$this->customer,
            'warehouse_id'=>$this->warehouseA,'currency_code'=>'AFN','fx_rate'=>'1',
            'lines'=>[['product_variant_id'=>$this->variant,'quantity'=>'3','unit_price'=>'40']]
        ]);
        app(SaleService::class)->post($sale);
        $service=app(SettlementService::class);
        $key=(string)Str::uuid();
        $first=$service->receiveCustomerPayment($sale,'50',$key);
        $this->assertSame($first,$service->receiveCustomerPayment($sale,'50',$key));
        $service->receiveCustomerPayment($sale,'70',(string)Str::uuid());
        $db=DB::connection('erp_tenant');
        $this->assertSame('120',(string)(float)$db->table('sales_invoices')->where('id',$sale)->value('paid'));
        $this->assertSame(2,$db->table('customer_payments')->count());
        $this->assertTrue(app(FinancialReportService::class)->balanceSheet(now()->toDateString())['balanced']);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Payment exceeds outstanding invoice');
        $service->receiveCustomerPayment($sale,'1',(string)Str::uuid());
    }

    public function test_supplier_settlement_enforces_remaining_payable_and_cash_balance(): void
    {
        $receipt=$this->receipt();
        app(ManualJournalService::class)->post([
            'request_key'=>(string)Str::uuid(),'date'=>now()->toDateString(),'description'=>'Owner cash contribution',
            'lines'=>[
                ['account_code'=>'1200','debit'=>'120','credit'=>'0'],
                ['account_code'=>'3100','debit'=>'0','credit'=>'120']
            ]
        ]);
        $service=app(SettlementService::class);
        $key=(string)Str::uuid();
        $payment=$service->paySupplier($receipt,'100',$key);
        $this->assertSame($payment,$service->paySupplier($receipt,'100',$key));
        $db=DB::connection('erp_tenant');
        $this->assertSame(1,$db->table('supplier_payments')->count());
        $this->assertTrue(app(FinancialReportService::class)->balanceSheet(now()->toDateString())['balanced']);
        try {
            $service->paySupplier($receipt,'21',(string)Str::uuid());
            $this->fail('Must refuse disbursement without enough cash');
        } catch (DomainException $e) {
            $this->assertSame('Insufficient cash ledger balance',$e->getMessage());
        }
        $this->assertSame(1,$db->table('supplier_payments')->count());
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Payment exceeds supplier payable');
        $service->paySupplier($receipt,'151',(string)Str::uuid());
    }
}
