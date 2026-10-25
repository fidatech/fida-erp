<?php
namespace App\Http\Controllers\ERP;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
final class PlatformController extends Controller
{
 public function index(){
  $db=DB::connection('mysql');
  return view('erp.platform.index',['summary'=>[
    'tenants'=>$db->table('erp_tenants')->count(),
    'active'=>$db->table('erp_tenants')->where('status','active')->count(),
    'resellers'=>$db->table('erp_resellers')->count(),
    'unpaid'=>$db->table('erp_platform_invoices')->where('status','!=','paid')->count(),
  ],'tenants'=>$db->table('erp_tenants as t')->leftJoin('erp_resellers as r','r.id','=','t.reseller_id')
       ->select('t.*','r.name as reseller')->orderByDesc('t.id')->simplePaginate(25),
  'plans'=>$db->table('erp_plans')->orderBy('price')->get(),
  'resellers'=>$db->table('erp_resellers')->orderBy('name')->get(),
  'invoices'=>$db->table('erp_platform_invoices')->orderByDesc('id')->limit(50)->get()]);
 }
 public function plan(Request $request){
  $v=$request->validate(['code'=>'required|string|max:40|unique:mysql.erp_plans,code',
      'name'=>'required|string|max:150','price'=>'required|numeric|min:0','currency_code'=>'required|size:3|in:USD,AFN,EUR,AED',
      'interval'=>'required|in:monthly,yearly','max_users'=>'required|integer|min:1','max_branches'=>'required|integer|min:1']);
  DB::connection('mysql')->table('erp_plans')->insert([...$v,'active'=>1,'created_at'=>now(),'updated_at'=>now()]);
  return back()->with('success','Plan created');
 }
 public function reseller(Request $request){
  $v=$request->validate(['name'=>'required|string|max:180','email'=>'required|email|unique:mysql.erp_resellers,email',
      'commission_percent'=>'required|numeric|min:0|max:100']);
  DB::connection('mysql')->table('erp_resellers')->insert([...$v,'status'=>'active','created_at'=>now(),'updated_at'=>now()]);
  return back()->with('success','Reseller created');
 }
 public function subscribe(Request $request){
  $v=$request->validate(['tenant_id'=>'required|integer|exists:mysql.erp_tenants,id','plan_id'=>'required|integer|exists:mysql.erp_plans,id',
      'starts_at'=>'required|date','ends_at'=>'required|date|after:starts_at']);
  $db=DB::connection('mysql');
  $db->transaction(function()use($v,$db){
    $db->table('erp_subscriptions')->where('tenant_id',$v['tenant_id'])->where('status','active')->update(['status'=>'superseded','updated_at'=>now()]);
    $db->table('erp_subscriptions')->insert([...$v,'status'=>'active','created_at'=>now(),'updated_at'=>now()]);
  });return back()->with('success','Subscription assigned');
 }
 public function suspend(Request $request,int $id){
  $request->validate(['action'=>'required|in:suspend,activate']);
  DB::connection('mysql')->transaction(function () use ($request, $id): void {
    $db = DB::connection('mysql');
    $tenant = $db->table('erp_tenants')->where('id', $id)->lockForUpdate()->first();
    abort_unless($tenant, 404);
    $db->table('erp_tenants')->where('id', $id)->update($request->action === 'suspend'
      ? ['status' => 'suspended', 'suspended_at' => now(), 'updated_at' => now()]
      : ['status' => 'active', 'suspended_at' => null, 'updated_at' => now()]);
    $db->table('erp_platform_audit_logs')->insert([
      'actor' => (string) $request->user()->getAuthIdentifier(),
      'event' => 'tenant.'.$request->action, 'tenant_id' => $id,
      'created_at' => now(), 'updated_at' => now(),
    ]);
  });
  return back()->with('success','Tenant status changed');
 }
 public function invoice(Request $request){
  $v=$request->validate(['tenant_id'=>'required|integer|exists:mysql.erp_tenants,id',
      'total'=>'required|numeric|gt:0','currency_code'=>'required|in:USD,AFN,EUR,AED','due_date'=>'nullable|date']);
  DB::connection('mysql')->table('erp_platform_invoices')->insert(['tenant_id'=>$v['tenant_id'],'number'=>'BILL-'.Str::ulid(),
      'total'=>$v['total'],'paid'=>'0','currency_code'=>$v['currency_code'],'status'=>'unpaid','due_date'=>$v['due_date']??null,
      'created_at'=>now(),'updated_at'=>now()]);
  return back()->with('success','Platform invoice created');
 }
 public function payment(Request $request){
  $v=$request->validate(['invoice_id'=>'required|integer|exists:mysql.erp_platform_invoices,id',
      'amount'=>'required|numeric|gt:0','method'=>'required|in:bank,cash,other','reference'=>'required|string|min:5|max:190']);
  $db=DB::connection('mysql');
  $db->transaction(function() use($v,$db,$request){
   $bill=$db->table('erp_platform_invoices')->where('id',$v['invoice_id'])->lockForUpdate()->first();
   if(!$bill)throw ValidationException::withMessages(['invoice_id'=>'Invoice not found']);
   if($db->table('erp_platform_payments')->where('provider_reference',$v['reference'])->exists())
     throw ValidationException::withMessages(['reference'=>'Payment reference already used']);
   $new=bcadd((string)$bill->paid,(string)$v['amount'],6);
   if(bccomp($new,(string)$bill->total,6)>0)throw ValidationException::withMessages(['amount'=>'Payment would exceed invoice total']);
   $db->table('erp_platform_payments')->insert(['invoice_id'=>$bill->id,'amount'=>$v['amount'],'currency_code'=>$bill->currency_code,
      'method'=>$v['method'],'provider_reference'=>$v['reference'],'status'=>'confirmed','created_at'=>now(),'updated_at'=>now()]);
   $db->table('erp_platform_invoices')->where('id',$bill->id)->update(['paid'=>$new,
      'status'=>bccomp($new,(string)$bill->total,6)===0?'paid':'partial','updated_at'=>now()]);
   $db->table('erp_platform_audit_logs')->insert(['actor'=>(string)$request->user()->getAuthIdentifier(),
      'event'=>'platform.payment.recorded','tenant_id'=>$bill->tenant_id,
      'context'=>json_encode(['invoice_id'=>$bill->id,'method'=>$v['method'],'reference'=>$v['reference']]),
      'created_at'=>now(),'updated_at'=>now()]);
  },3);
  return back()->with('success','Manual platform payment recorded');
 }

}
