<?php
namespace App\Http\Controllers\ERP;
use App\Http\Controllers\Controller;
use App\Services\ERP\SaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use DomainException;
final class SalesController extends Controller
{
    private function allowedWarehouseIds(): array
    {
        return DB::connection('erp_tenant')->table('warehouses as w')
            ->join('branch_user as bu', 'bu.branch_id', '=', 'w.branch_id')
            ->join('branches as b', 'b.id', '=', 'w.branch_id')
            ->where('bu.user_id', auth('erp_tenant')->id())->where('b.active', true)
            ->pluck('w.id')->map(fn ($id) => (int) $id)->all();
    }

    public function index(){
        $rows=DB::connection('erp_tenant')->table('sales_invoices as s')->join('customers as c','c.id','=','s.customer_id')
            ->whereIn('s.warehouse_id', $this->allowedWarehouseIds())
            ->select('s.id','s.number','s.total','s.currency_code','s.status','s.created_at','c.name as customer')
            ->orderByDesc('s.id')->cursorPaginate(25);
        return view('erp.sales.index',['rows'=>$rows]);
    }
    public function create(){return view('erp.sales.create',['warehouses'=>DB::connection('erp_tenant')->table('warehouses')->whereIn('id', $this->allowedWarehouseIds())->orderBy('name')->get(),
        'currencies'=>DB::connection('erp_tenant')->table('currencies')->where('active',1)->get(),
        'base'=>DB::connection('erp_tenant')->table('company_settings')->value('base_currency')]);}
    public function store(Request $request,SaleService $service){
        $input=$request->validate(['request_key'=>'required|uuid', 'customer_id'=>'required|integer|exists:erp_tenant.customers,id',
            'warehouse_id'=>'required|integer|exists:erp_tenant.warehouses,id', 'currency_code'=>'required|string|size:3',
            'fx_rate'=>'required|numeric|gt:0','discount'=>'nullable|numeric|min:0',
            'lines'=>'required|array|min:1|max:100', 'lines.*.product_variant_id'=>'required|integer|exists:erp_tenant.product_variants,id',
            'lines.*.quantity'=>'required|numeric|gt:0','lines.*.unit_price'=>'required|numeric|min:0',
            'lines.*.discount'=>'nullable|numeric|min:0','lines.*.tax'=>'nullable|numeric|min:0']);
        abort_unless(in_array((int) $input['warehouse_id'], $this->allowedWarehouseIds(), true), 403);
        try{$id=$service->draft($input);}catch(DomainException|\InvalidArgumentException $e){return back()->withErrors(['sale'=>$e->getMessage()])->withInput();}
        return redirect()->route('erp.sales.index')->with('success',__('erp::ui.invoice_drafted').' #'.$id);
    }
    public function post(int $id,SaleService $service){
        $warehouseId = DB::connection('erp_tenant')->table('sales_invoices')->where('id', $id)->value('warehouse_id');
        abort_unless($warehouseId && in_array((int) $warehouseId, $this->allowedWarehouseIds(), true), 403);
        try{$service->post($id);}catch(DomainException|\InvalidArgumentException $e){return back()->withErrors(['sale'=>$e->getMessage()]);}
        return back()->with('success',__('erp::ui.invoice_posted'));
    }
}
