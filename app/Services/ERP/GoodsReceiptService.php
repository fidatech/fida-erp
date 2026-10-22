<?php
namespace App\Services\ERP;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class GoodsReceiptService
{
    public function __construct(private readonly StockService $stock,private readonly JournalService $journals){}
    public function receive(array $input): int
    {
        return DB::connection('erp_tenant')->transaction(function()use($input){
            $db=DB::connection('erp_tenant');
            $existing=$db->table('goods_receipts')->where('request_key',$input['request_key'])->value('id');
            if($existing)return (int)$existing;
            $company=$db->table('company_settings')->first();
            if(!$company)throw new DomainException('Missing company settings');
            $rate=Decimal::valid((string)$input['fx_rate'],true);$cur=$input['currency_code'];
            if($cur===$company->base_currency && Decimal::cmp($rate,'1')!==0)throw new DomainException('Base currency rate must equal 1');
            if(!$db->table('currencies')->where('code',$cur)->where('active',1)->exists())throw new DomainException('Unsupported currency');
            $total='0';$prepared=[];$seen=[];
            $items=collect($input['lines'])->sortBy('product_variant_id')->values();
            foreach($items as $item){
                if(isset($seen[$item['product_variant_id']]))throw new DomainException('Duplicate variants in receipt'); $seen[$item['product_variant_id']]=true;
                $q=Decimal::fixed((string)$item['quantity'],true);$unit=Decimal::fixed((string)$item['unit_cost']);
                if(Decimal::cmp($unit,'0')<=0)throw new DomainException('Cost must be positive');
                $variant=$db->table('product_variants')->where('id',$item['product_variant_id'])->lockForUpdate()->first();
                if(!$variant || !$variant->active)throw new DomainException('Invalid product variant');
                $total=Decimal::add($total,Decimal::mul($q,$unit));
                $prepared[]=['product_variant_id'=>$variant->id,'quantity'=>$q,'unit_cost'=>$unit,'base_unit_cost'=>Decimal::mul($unit,$rate),'previous_average_cost'=>(string)$variant->average_cost];
            }
            $id=$db->table('goods_receipts')->insertGetId(['number'=>'GRN-'.Str::ulid(),
                'request_key'=>$input['request_key'],'supplier_id'=>$input['supplier_id'],'warehouse_id'=>$input['warehouse_id'],'currency_code'=>$cur,'fx_rate'=>$rate,
                'total'=>$total,'status'=>'posted','posted_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
            foreach($prepared as $line){
                $db->table('goods_receipt_lines')->insert(['goods_receipt_id'=>$id,'product_variant_id'=>$line['product_variant_id'],
                    'quantity'=>$line['quantity'],'unit_cost'=>$line['unit_cost'],'base_unit_cost'=>$line['base_unit_cost'],
                    'created_at'=>now(),'updated_at'=>now()]);
                // Weighted average across all warehouses for this variant. Serialize receipts by variant lock.
                $oldStock=(string)($db->table('stock_balances')->where('product_variant_id',$line['product_variant_id'])->selectRaw('COALESCE(SUM(quantity), 0) as total')->value('total') ?? '0');
                $denom=Decimal::add($oldStock,$line['quantity']);
                $newCost=Decimal::div(Decimal::add(Decimal::mul($oldStock,$line['previous_average_cost']),
                    Decimal::mul($line['quantity'],$line['base_unit_cost'])),$denom);
                $this->stock->move((int)$input['warehouse_id'],(int)$line['product_variant_id'],$line['quantity'],
                    'receipt:'.$id.':variant:'.$line['product_variant_id'].':'.Str::ulid(),'goods_receipt',$id,$line['base_unit_cost']);
                $db->table('product_variants')->where('id',$line['product_variant_id'])->update(['average_cost'=>$newCost,'updated_at'=>now()]);
            }
            $base=Decimal::mul($total,$rate);
            $this->journals->post('goods_receipt',$id,now()->toDateString(),[
                ['account_code'=>'1300','currency_code'=>$company->base_currency,'currency_amount'=>$base,'fx_rate'=>'1','debit'=>$base,'credit'=>'0'],
                ['account_code'=>'2100','currency_code'=>$cur,'currency_amount'=>$total,'fx_rate'=>$rate,'debit'=>'0','credit'=>$base],
            ],'Goods received '.$id);
            $db->table('audit_logs')->insert(['user_id'=>auth('erp_tenant')->id(),'event'=>'stock.received','entity_type'=>'goods_receipt','entity_id'=>$id,'created_at'=>now(),'updated_at'=>now()]);
            return $id;
        },3);
    }
}
