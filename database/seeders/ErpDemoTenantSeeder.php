<?php
namespace Database\Seeders;

use App\Services\ERP\GoodsReceiptService;
use App\Services\ERP\ManualJournalService;
use App\Services\ERP\SaleService;
use App\Services\ERP\SettlementService;
use App\Services\ERP\StockTransferService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class ErpDemoTenantSeeder extends Seeder
{
    public const VERSION=1;
    public function run():void
    {
        if(!app()->environment(['local','staging','testing']))throw new RuntimeException('Demo seeding forbidden in production');
        if(!app()->bound('erp.current_tenant') || !preg_match('/^demo-[a-z0-9-]+$/D',(string)app('erp.current_tenant')->slug))
            throw new RuntimeException('Only dedicated demo-* tenants are allowed');
        $db=DB::connection('erp_tenant');
        if($db->table('demo_seed_runs')->where('seed_name','showcase')->exists()){ $this->command?->info('Already seeded');return; }
        foreach(['sales_invoices','goods_receipts','journals','stock_movements','customer_payments','supplier_payments'] as $table)
            if($db->table($table)->exists())throw new RuntimeException('Nonempty ledger: '.$table);
        foreach(['1100','1200','1300','2100','2200','3100','4100','5100'] as $code)
            if(!$db->table('accounts')->where('code',$code)->exists())throw new RuntimeException('Missing account '.$code);
        $db->transaction(function()use($db){
            $now=now();
            $db->table('company_settings')->update(['name'=>'FidaERP Demo Trading Co.','base_currency'=>'AFN','updated_at'=>$now]);
            $warehouse=$db->table('warehouses')->first();
            if(!$warehouse)throw new RuntimeException('Provision tenant and warehouse before demo seeding');
            $secondary=$db->table('warehouses')->insertGetId(['branch_id'=>$warehouse->branch_id,
                'code'=>'DEMO-WH2','name'=>'Demo Secondary Warehouse','created_at'=>$now,'updated_at'=>$now]);
            $categories=[];
            foreach(['IT Hardware','Office Equipment','Raw Materials','Safety Equipment','Packaging'] as $name)
                $categories[]=$db->table('categories')->insertGetId(['name'=>$name,'created_at'=>$now,'updated_at'=>$now]);
            $items=['Laptop Pro','Wireless Mouse','Office Chair','Printer Toner','USB-C Adapter','Computer Monitor',
                'Ethernet Cable','Desk Lamp','Steel Fastener','Packing Carton','Food Container','Safety Helmet',
                'Paper Bundle','Power Strip','Industrial Solvent','Mouse Pad','Keyboard','Barcode Scanner',
                'Portable SSD','Cleaning Solution','Shipping Label','Network Router','Office Desk','HDMI Cable','Safety Gloves'];
            $variants=[];
            foreach($items as $i=>$name){
                $code='DEMO-P'.str_pad((string)($i+1),3,'0',STR_PAD_LEFT);
                $p=$db->table('products')->insertGetId(['sku'=>$code,'name'=>$name,
                    'category_id'=>$categories[$i%5],'active'=>1,'created_at'=>$now,'updated_at'=>$now]);
                $cost=20+($i%8)*12;
                $variants[]=['id'=>$db->table('product_variants')->insertGetId([
                    'product_id'=>$p,'sku'=>$code.'-STD','barcode'=>'90000'.str_pad((string)$i,7,'0',STR_PAD_LEFT),
                    'sale_price'=>$cost*1.9,'average_cost'=>0,'active'=>1,
                    'created_at'=>$now,'updated_at'=>$now]),'cost'=>$cost];
            }
            $customers=[];$suppliers=[];
            for($i=0;$i<15;$i++){
                $customers[]=$db->table('customers')->insertGetId([
                    'code'=>'DEMO-C'.str_pad((string)$i,3,'0',STR_PAD_LEFT),
                    'name'=>'Demo Customer '.($i+1),'email'=>'customer'.$i.'@example.test',
                    'active'=>1,'created_at'=>$now,'updated_at'=>$now]);
            }
            for($i=0;$i<8;$i++)$suppliers[]=$db->table('suppliers')->insertGetId([
                'code'=>'DEMO-S'.str_pad((string)$i,3,'0',STR_PAD_LEFT),
                'name'=>'Demo Supplier '.($i+1),'active'=>1,'country_code'=>'AF',
                'created_at'=>$now,'updated_at'=>$now]);
            for($i=0;$i<20;$i++)$db->table('employees')->insert([
                'number'=>'DEMO-EMP'.($i+1),'name'=>'Demo Employee '.($i+1),
                'branch_id'=>$warehouse->branch_id,'hired_on'=>now()->subMonths($i%10)->toDateString(),
                'base_salary'=>15000+$i*650,'salary_currency'=>'AFN',
                'status'=>'active','created_at'=>$now,'updated_at'=>$now]);
            app(ManualJournalService::class)->post([
                'request_key'=>(string)Str::uuid(),'date'=>now()->toDateString(),
                'description'=>'DEMO owner opening capital','lines'=>[
                    ['account_code'=>'1200','debit'=>'1000000','credit'=>'0'],
                    ['account_code'=>'3100','debit'=>'0','credit'=>'1000000']]]);
            $receipts=[];
            for($i=0;$i<17;$i++){
                $lines=[];
                for($j=0;$j<3;$j++){$v=$variants[($i*3+$j)%count($variants)];
                    $lines[]=['product_variant_id'=>$v['id'],'quantity'=>'70','unit_cost'=>(string)$v['cost']];}
                $receipts[]=app(GoodsReceiptService::class)->receive([
                    'request_key'=>(string)Str::uuid(),'supplier_id'=>$suppliers[$i%8],
                    'warehouse_id'=>$warehouse->id,'currency_code'=>'AFN','fx_rate'=>'1','lines'=>$lines]);
            }
            for($i=0;$i<45;$i++){
                $a=$variants[$i%count($variants)];$b=$variants[($i+7)%count($variants)];
                $usd=$i%7===0;
                $sale=app(SaleService::class)->draft([
                    'request_key'=>(string)Str::uuid(),'customer_id'=>$customers[$i%15],
                    'warehouse_id'=>$warehouse->id,
                    'currency_code'=>$usd?'USD':'AFN','fx_rate'=>$usd?'70':'1',
                    'lines'=>[['product_variant_id'=>$a['id'],'quantity'=>'2',
                        'unit_price'=>(string)($usd?5:$a['cost']*2)],
                        ['product_variant_id'=>$b['id'],'quantity'=>'1',
                        'unit_price'=>(string)($usd?6:$b['cost']*2)]]]);
                if($i%9!==0){
                    app(SaleService::class)->post($sale);
                    if($i%3!==0){
                        $invoice=$db->table('sales_invoices')->find($sale);
                        $amount=$i%4===0?((float)$invoice->total/2):(float)$invoice->total;
                        app(SettlementService::class)->receiveCustomerPayment($sale,(string)$amount,(string)Str::uuid());
                    }
                }
                $date=now()->subMonths($i%6)->toDateString();
                $db->table('sales_invoices')->where('id',$sale)->update(['created_at'=>$date]);
                $db->table('journals')->where('source_type','sales_invoice')->where('source_id',$sale)->update(['date'=>$date]);
            }
            app(StockTransferService::class)->transfer([
                'request_key'=>(string)Str::uuid(),'from_warehouse_id'=>$warehouse->id,
                'to_warehouse_id'=>$secondary,'product_variant_id'=>$variants[0]['id'],'quantity'=>'9']);
            foreach(array_slice($receipts,0,3) as $receipt){
                $total=(float)$db->table('goods_receipts')->where('id',$receipt)->value('total');
                app(SettlementService::class)->paySupplier($receipt,(string)min($total,500),(string)Str::uuid());
            }
            $db->table('demo_seed_runs')->insert(['seed_name'=>'showcase','version'=>self::VERSION,
                'created_at'=>$now,'updated_at'=>$now]);
        },3);
        $this->command?->info('Demo dataset seeded safely in isolated tenant.');
    }
}
