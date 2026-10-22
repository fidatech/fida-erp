<?php
namespace App\Http\Controllers\ERP;
use App\Http\Controllers\Controller;
use App\Services\ERP\GoodsReceiptService;
use App\Services\ERP\SettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use DomainException;
final class StockController extends Controller
{
 public function index(){
  $db=DB::connection('erp_tenant');
  return view('erp.stock.index',['warehouses'=>$db->table('warehouses')->get(),'currencies'=>$db->table('currencies')->where('active',1)->get(),
      'suppliers'=>$db->table('suppliers')->select('id','name')->orderBy('name')->limit(200)->get(),
      'base'=>$db->table('company_settings')->value('base_currency'),
      'receipts'=>$db->table('goods_receipts as g')->join('suppliers as su','su.id','=','g.supplier_id')
        ->select('g.id','g.number','g.total','g.currency_code','su.name as supplier')
        ->selectRaw('(SELECT COALESCE(SUM(amount),0) FROM supplier_payments WHERE goods_receipt_id=g.id) as paid')
        ->orderByDesc('g.id')->limit(15)->get(),
            'rows'=>$db->table('stock_balances as s')->join('product_variants as v','v.id','=','s.product_variant_id')
         ->join('products as p','p.id','=','v.product_id')->join('warehouses as w','w.id','=','s.warehouse_id')
         ->select('p.name','v.sku','w.name as warehouse','s.quantity','v.average_cost')->orderBy('s.id','desc')->simplePaginate(25)]);
 }
 public function addSupplier(Request $request){
  $input=$request->validate(['name'=>'required|string|max:190','phone'=>'nullable|string|max:40']);
  DB::connection('erp_tenant')->table('suppliers')->insert([...$input,'code'=>'SUP-'.Str::ulid(),'active'=>1,'created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Supplier added');
 }
 public function paySupplier(Request $request, int $id, SettlementService $settlements){
  $data=$request->validate(['request_key'=>'required|uuid','amount'=>'required|numeric|gt:0']);
  try{$payment=$settlements->paySupplier($id,(string)$data['amount'],$data['request_key']);}
  catch(DomainException|\InvalidArgumentException $e){return back()->withErrors(['payment'=>$e->getMessage()]);}
  return back()->with('success','Supplier payment #'.$payment.' posted');
 }
 public function receive(Request $request,GoodsReceiptService $service){
  $data=$request->validate(['request_key'=>'required|uuid', 'supplier_id'=>'required|integer|exists:erp_tenant.suppliers,id',
    'warehouse_id'=>'required|integer|exists:erp_tenant.warehouses,id','currency_code'=>'required|string|size:3',
    'fx_rate'=>'required|numeric|gt:0','lines'=>'required|array|min:1|max:100',
    'lines.*.product_variant_id'=>'required|integer|exists:erp_tenant.product_variants,id',
    'lines.*.quantity'=>'required|numeric|gt:0','lines.*.unit_cost'=>'required|numeric|gt:0']);
  try{$id=$service->receive($data);}catch(DomainException|\InvalidArgumentException $e){return back()->withErrors(['stock'=>$e->getMessage()]);}
  return back()->with('success','Goods received #'.$id);
 }
}
