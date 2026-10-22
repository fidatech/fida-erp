<?php
namespace App\Services\ERP;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class StockTransferService
{
    public function __construct(private readonly StockService $stock) {}
    public function transfer(array $input): int
    {
        return DB::connection('erp_tenant')->transaction(function() use ($input) {
            $db=DB::connection('erp_tenant');
            $existing=$db->table('stock_transfers')->where('request_key',$input['request_key'])->value('id');
            if($existing) return (int)$existing;
            $from=(int)$input['from_warehouse_id'];$to=(int)$input['to_warehouse_id'];
            if($from===$to) throw new DomainException('Choose different source and destination warehouses');
            $variant=(int)$input['product_variant_id'];
            $quantity=Decimal::fixed((string)$input['quantity'],true);
            $product=$db->table('product_variants')->where('id',$variant)->where('active',1)->lockForUpdate()->first();
            if(!$product) throw new DomainException('Product unavailable');
            $id=$db->table('stock_transfers')->insertGetId([
                'number'=>'TRF-'.Str::ulid(),'request_key'=>$input['request_key'],
                'from_warehouse_id'=>$from,'to_warehouse_id'=>$to,
                'product_variant_id'=>$variant,'quantity'=>$quantity,
                'unit_cost'=>$product->average_cost,'created_by'=>auth('erp_tenant')->id(),
                'created_at'=>now(),'updated_at'=>now()
            ]);
            $this->stock->move($from,$variant,'-'.ltrim($quantity,'+'),
                'transfer:'.$id.':out','stock_transfer',$id,(string)$product->average_cost);
            $this->stock->move($to,$variant,$quantity,
                'transfer:'.$id.':in','stock_transfer',$id,(string)$product->average_cost);
            $db->table('audit_logs')->insert(['user_id'=>auth('erp_tenant')->id(),
                'event'=>'stock.transferred','entity_type'=>'stock_transfer','entity_id'=>$id,
                'created_at'=>now(),'updated_at'=>now()]);
            return $id;
        },3);
    }
}
