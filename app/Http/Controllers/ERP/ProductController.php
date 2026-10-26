<?php
namespace App\Http\Controllers\ERP;
use App\Http\Controllers\Controller;
use App\Models\ERP\Tenant\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
final class ProductController extends Controller
{
    public function index(Request $request){
        $q=mb_substr(trim((string)$request->query('q','')),0,80);
        $products=Product::query()->when($q,fn($x)=>$x->where(fn($s)=>$s->where('name','like',$q.'%')->orWhere('sku','like',$q.'%')))
            ->orderByDesc('id')->cursorPaginate(25)->withQueryString();
        return view('erp.products.index',compact('products','q'));
    }
    public function store(Request $request){
        $data=$request->validate(['name'=>'required|string|max:190','sku'=>'nullable|string|max:80',
            'sale_price'=>'required|numeric|min:0','barcode'=>'nullable|string|max:80']);
        $created=DB::connection('erp_tenant')->transaction(function()use($data){
            $sku=$data['sku']??('SKU-'.Str::ulid());
            $p=Product::create(['name'=>$data['name'],'sku'=>$sku,'active'=>true]);
            $db=DB::connection('erp_tenant');
            $variantId=$db->table('product_variants')->insertGetId(['product_id'=>$p->id,'sku'=>$sku.'-STD','barcode'=>$data['barcode']??null,
              'sale_price'=>$data['sale_price'],'average_cost'=>'0','active'=>1,'created_at'=>now(),'updated_at'=>now()]);
            return ['id'=>$variantId,'text'=>$p->name.' / '.$sku.'-STD','price'=>$data['sale_price']];
        });
        if($request->expectsJson())return response()->json($created,201);
        return back()->with('success',__('erp::ui.product_added'));
    }
    public function data(Request $request){
        $v=$request->validate(['q'=>'nullable|string|max:80','cursor'=>'nullable|string|max:2000']);
        $term=trim((string)($v['q']??''));
        $page=Product::query()->select('id','sku','name')->when($term!=='',fn($s)=>$s->where(fn($x)=>$x->where('name','like',$term.'%')->orWhere('sku','like',$term.'%')))
            ->orderByDesc('id')->cursorPaginate(50);
        return response()->json(['data'=>$page->items(),'next_cursor'=>$page->nextCursor()?->encode()]);
    }
    public function select2(Request $request){
        $q=mb_substr(trim((string)$request->query('q','')),0,80);
        $rows=DB::connection('erp_tenant')->table('product_variants as v')->join('products as p','p.id','=','v.product_id')
            ->where('v.active',1)->when($q!== '',fn($s)=>$s->where(fn($w)=>$w->where('p.name','like',$q.'%')->orWhere('v.sku','like',$q.'%')->orWhere('v.barcode',$q)))
            ->select('v.id','v.sku','v.sale_price','p.name')->orderBy('v.id')->limit(21)->get();
        return response()->json(['results'=>$rows->take(20)->map(fn($p)=>['id'=>$p->id,'text'=>$p->name.' / '.$p->sku,'price'=>$p->sale_price])->values(),
            'pagination'=>['more'=>$rows->count()>20]]);
    }
}
