<?php
namespace App\Http\Controllers\ERP;
use App\Http\Controllers\Controller;
use App\Models\ERP\Tenant\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
final class CustomerController extends Controller
{
    public function index(Request $request){
        $q=mb_substr(trim((string)$request->query('q','')),0,80);
        $customers=Customer::query()->when($q,fn($s)=>$s->where(fn($x)=>$x->where('name','like',$q.'%')->orWhere('code','like',$q.'%')))
            ->orderBy('id','desc')->cursorPaginate(25)->withQueryString();
        return view('erp.customers.index',compact('customers','q'));
    }
    public function store(Request $request){
        $data=$request->validate(['name'=>'required|string|max:190','phone'=>'nullable|string|max:40','email'=>'nullable|email|max:255','address'=>'nullable|string|max:255']);
        Customer::create(array_merge($data,['code'=>'CUS-'.Str::ulid()]));return back()->with('success',__('erp::ui.customer_added'));
    }
    /** Cursor API designed for deep tables; avoids large OFFSET scans. */
    public function data(Request $request){
        $v=$request->validate(['q'=>'nullable|string|max:80','cursor'=>'nullable|string|max:2000']);
        $term=trim((string)($v['q']??''));
        $page=Customer::query()->select('id','code','name','phone','email')->when($term!=='',fn($s)=>$s->where(fn($x)=>$x->where('name','like',$term.'%')->orWhere('code','like',$term.'%')))
            ->orderByDesc('id')->cursorPaginate(50);
        return response()->json(['data'=>$page->items(),'next_cursor'=>$page->nextCursor()?->encode()]);
    }
    public function select2(Request $request){
        $term=mb_substr(trim((string)$request->query('q','')),0,80);
        $q=Customer::query()->select('id','name')->when($term!== '',fn($q)=>$q->where('name','like',$term.'%'))
            ->orderBy('name')->limit(21)->get();
        return response()->json(['results'=>$q->take(20)->map(fn($c)=>['id'=>$c->id,'text'=>$c->name])->values(),'pagination'=>['more'=>$q->count()>20]]);
    }
}
