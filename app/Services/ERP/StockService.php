<?php
namespace App\Services\ERP;

use Illuminate\Support\Facades\DB;
use DomainException;

final class StockService
{
    /** One event_key per business source line. Must be inside the caller's transaction. */
    public function move(int $warehouseId,int $variantId,string $delta,string $eventKey,string $sourceType,int $sourceId,string $unitCost='0'): void
    {
        Decimal::valid($delta);
        $db=DB::connection('erp_tenant');
        if($db->table('stock_movements')->where('event_key',$eventKey)->exists()) {
            throw new DomainException('Stock event already processed');
        }
        $db->table('stock_balances')->insertOrIgnore(['warehouse_id'=>$warehouseId,'product_variant_id'=>$variantId,
            'quantity'=>'0','created_at'=>now(),'updated_at'=>now()]);
        $row=$db->table('stock_balances')->where('warehouse_id',$warehouseId)->where('product_variant_id',$variantId)->lockForUpdate()->first();
        $new=Decimal::add((string)$row->quantity,$delta);
        if(Decimal::cmp($new,'0')<0) throw new DomainException('Insufficient available stock');
        $db->table('stock_movements')->insert(['warehouse_id'=>$warehouseId,'product_variant_id'=>$variantId,
            'quantity_delta'=>$delta,'unit_cost'=>$unitCost,'source_type'=>$sourceType,'source_id'=>$sourceId,
            'event_key'=>$eventKey,'user_id'=>auth('erp_tenant')->id(),'created_at'=>now(),'updated_at'=>now()]);
        $db->table('stock_balances')->where('id',$row->id)->update(['quantity'=>$new,'updated_at'=>now()]);
    }
}
