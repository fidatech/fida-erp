<?php
namespace App\Http\Controllers\ERP;
use App\Http\Controllers\Controller;
use App\Services\ERP\BranchWarehouseAccess;
use App\Services\ERP\GoodsReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use DomainException;
final class StockController extends Controller
{
    private function allowedWarehouseIds(): array
    {
        return app(BranchWarehouseAccess::class)->allowedWarehouseIds();
    }

 public function index(){
  $db=DB::connection('erp_tenant');
  return view('erp.stock.index',['warehouses'=>$db->table('warehouses')->whereIn('id',$this->allowedWarehouseIds())->get(),'currencies'=>$db->table('currencies')->where('active',1)->get(),
      'suppliers'=>$db->table('suppliers')->select('id','name')->orderBy('name')->limit(200)->get(),
      'base'=>$db->table('company_settings')->value('base_currency'),
      'rows'=>$db->table('stock_balances as s')->join('product_variants as v','v.id','=','s.product_variant_id')
         ->join('products as p','p.id','=','v.product_id')->join('warehouses as w','w.id','=','s.warehouse_id')->whereIn('s.warehouse_id',$this->allowedWarehouseIds())
         ->select('p.name','v.sku','w.name as warehouse','s.quantity','v.average_cost')->orderBy('s.id','desc')->simplePaginate(25)]);
 }
 public function addSupplier(Request $request){
  $input=$request->validate(['name'=>'required|string|max:190','phone'=>'nullable|string|max:40']);
  DB::connection('erp_tenant')->table('suppliers')->insert([...$input,'code'=>'SUP-'.Str::ulid(),'active'=>1,'created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Supplier added');
 }
 public function receive(Request $request,GoodsReceiptService $service){
  $warehouseId=$request->input('warehouse_id');
  abort_unless(is_scalar($warehouseId) && ctype_digit((string)$warehouseId)
   && app(BranchWarehouseAccess::class)->allows((int)$warehouseId),403);
  $data=$request->validate(['request_key'=>'required|uuid', 'supplier_id'=>'required|integer|exists:erp_tenant.suppliers,id',
    'warehouse_id'=>'required|integer|exists:erp_tenant.warehouses,id','currency_code'=>'required|string|size:3',
    'fx_rate'=>'required|numeric|gt:0','lines'=>'required|array|min:1|max:100',
    'lines.*.product_variant_id'=>'required|integer|exists:erp_tenant.product_variants,id',
    'lines.*.quantity'=>'required|numeric|gt:0','lines.*.unit_cost'=>'required|numeric|gt:0']);
  try{$id=$service->receive($data);}catch(DomainException|\InvalidArgumentException $e){return back()->withErrors(['stock'=>$e->getMessage()]);}
  return back()->with('success','Goods received #'.$id);
 }
}
