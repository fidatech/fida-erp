<?php
namespace App\Http\Controllers\ERP;
use App\Http\Controllers\Controller;
use App\Services\ERP\SaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use DomainException;
final class SalesController extends Controller
{
    public function index(){
        $rows=DB::connection('erp_tenant')->table('sales_invoices as s')->join('customers as c','c.id','=','s.customer_id')
            ->select('s.id','s.number','s.total','s.currency_code','s.status','s.created_at','c.name as customer')
            ->orderByDesc('s.id')->cursorPaginate(25);
        return view('erp.sales.index',['rows'=>$rows]);
    }
    public function create(){return view('erp.sales.create',['warehouses'=>DB::connection('erp_tenant')->table('warehouses')->orderBy('name')->get(),
        'currencies'=>DB::connection('erp_tenant')->table('currencies')->where('active',1)->get(),
        'base'=>DB::connection('erp_tenant')->table('company_settings')->value('base_currency')]);}
    public function store(Request $request,SaleService $service){
        $input=$request->validate(['request_key'=>'required|uuid', 'customer_id'=>'required|integer|exists:erp_tenant.customers,id',
            'warehouse_id'=>'required|integer|exists:erp_tenant.warehouses,id', 'currency_code'=>'required|string|size:3',
            'fx_rate'=>'required|numeric|gt:0','discount'=>'nullable|numeric|min:0',
            'lines'=>'required|array|min:1|max:100', 'lines.*.product_variant_id'=>'required|integer|exists:erp_tenant.product_variants,id',
            'lines.*.quantity'=>'required|numeric|gt:0','lines.*.unit_price'=>'required|numeric|min:0',
            'lines.*.discount'=>'nullable|numeric|min:0','lines.*.tax'=>'nullable|numeric|min:0']);
        try{$id=$service->draft($input);}catch(DomainException|\InvalidArgumentException $e){return back()->withErrors(['sale'=>$e->getMessage()])->withInput();}
        return redirect()->route('erp.sales.index')->with('success',__('erp::ui.invoice_drafted').' #'.$id);
    }
    public function post(int $id,SaleService $service){
        try{$service->post($id);}catch(DomainException|\InvalidArgumentException $e){return back()->withErrors(['sale'=>$e->getMessage()]);}
        return back()->with('success',__('erp::ui.invoice_posted'));
    }
}
