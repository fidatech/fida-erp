<?php
namespace App\Http\Controllers\ERP;
use App\Http\Controllers\Controller;
use App\Services\ERP\SaleService;
use App\Services\ERP\SettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use DomainException;
final class SalesController extends Controller
{
    public function index(Request $request){
        $input=$request->validate([
            'q'=>'nullable|string|max:100',
            'status'=>'nullable|in:draft,posted',
            'from'=>'nullable|date_format:Y-m-d',
            'to'=>'nullable|date_format:Y-m-d'
        ]);
        $filters=[
            'q'=>trim((string)($input['q']??'')),
            'status'=>$input['status']??'',
            'from'=>$input['from']??'',
            'to'=>$input['to']??''
        ];
        $db=DB::connection('erp_tenant');
        $query=$db->table('sales_invoices as s')->join('customers as c','c.id','=','s.customer_id');
        if($filters['q']!==''){
            $term=$filters['q'];
            $query->where(fn($q)=>$q->where('s.number','like',$term.'%')->orWhere('c.name','like',$term.'%'));
        }
        if($filters['status']!=='')$query->where('s.status',$filters['status']);
        if($filters['from']!=='')$query->whereDate('s.created_at','>=',$filters['from']);
        if($filters['to']!=='')$query->whereDate('s.created_at','<=',$filters['to']);
        $rows=(clone $query)->select('s.id','s.number','s.total','s.paid','s.currency_code','s.status','s.created_at','c.name as customer')
            ->orderByDesc('s.id')->cursorPaginate(25)->withQueryString();
        $posted=$db->table('sales_invoices')->where('status','posted');
        $totals=(clone $posted)->selectRaw('COALESCE(SUM(total*fx_rate),0) as value, COALESCE(SUM((total-paid)*fx_rate),0) as due')->first();
        $stats=[
            'all'=>$db->table('sales_invoices')->count(),
            'posted'=>(clone $posted)->count(),
            'value'=>(float)$totals->value,
            'outstanding'=>(float)$totals->due,
            'currency'=>$db->table('company_settings')->value('base_currency')
        ];
        return view('erp.sales.index',compact('rows','filters','stats'));
    }
    public function create(){return view('erp.sales.create',['warehouses'=>DB::connection('erp_tenant')->table('warehouses')->orderBy('name')->get(),'branches'=>DB::connection('erp_tenant')->table('branches')->orderBy('name')->get(),
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
    public function receivePayment(Request $request, int $id, SettlementService $settlements){
        $data=$request->validate(['request_key'=>'required|uuid','amount'=>'required|numeric|gt:0']);
        try{$paymentId=$settlements->receiveCustomerPayment($id,(string)$data['amount'],$data['request_key']);}
        catch(DomainException|\InvalidArgumentException $e){return back()->withErrors(['payment'=>$e->getMessage()]);}
        return back()->with('success','Customer payment #'.$paymentId.' posted');
    }
    public function post(int $id,SaleService $service){
        try{$service->post($id);}catch(DomainException|\InvalidArgumentException $e){return back()->withErrors(['sale'=>$e->getMessage()]);}
        return back()->with('success',__('erp::ui.invoice_posted'));
    }
}
