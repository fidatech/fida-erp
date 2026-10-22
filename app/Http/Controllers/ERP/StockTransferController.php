<?php
namespace App\Http\Controllers\ERP;
use App\Http\Controllers\Controller;
use App\Services\ERP\StockTransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use DomainException;
use InvalidArgumentException;

final class StockTransferController extends Controller
{
    public function index()
    {
        $db=DB::connection('erp_tenant');
        return view('erp.stock.transfers',[
            'warehouses'=>$db->table('warehouses')->orderBy('name')->get(),
            'rows'=>$db->table('stock_transfers as t')
                ->join('warehouses as a','a.id','=','t.from_warehouse_id')
                ->join('warehouses as b','b.id','=','t.to_warehouse_id')
                ->join('product_variants as p','p.id','=','t.product_variant_id')
                ->select('t.number','t.quantity','t.created_at','a.name as source','b.name as destination','p.sku')
                ->orderByDesc('t.id')->paginate(25)
        ]);
    }
    public function store(Request $request, StockTransferService $service)
    {
        $data=$request->validate([
            'request_key'=>'required|uuid',
            'from_warehouse_id'=>'required|integer|exists:erp_tenant.warehouses,id',
            'to_warehouse_id'=>'required|integer|different:from_warehouse_id|exists:erp_tenant.warehouses,id',
            'product_variant_id'=>'required|integer|exists:erp_tenant.product_variants,id',
            'quantity'=>'required|numeric|gt:0'
        ]);
        try{$id=$service->transfer($data);}
        catch(DomainException|InvalidArgumentException $e){return back()->withErrors(['transfer'=>$e->getMessage()])->withInput();}
        return redirect()->route('erp.stock.transfers')->with('success','Stock transfer #'.$id.' completed');
    }
}
