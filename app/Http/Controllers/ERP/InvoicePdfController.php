<?php
namespace App\Http\Controllers\ERP;
use App\Http\Controllers\Controller;
use App\Services\ERP\InvoicePdfService;
use Illuminate\Http\Request;
use RuntimeException;
use Illuminate\Support\Facades\DB;
final class InvoicePdfController extends Controller
{
 public function __invoke(int $id,Request $request,InvoicePdfService $pdf){
  $authorized = DB::connection('erp_tenant')->table('sales_invoices as s')
   ->join('warehouses as w','w.id','=','s.warehouse_id')
   ->join('branches as b','b.id','=','w.branch_id')
   ->join('branch_user as bu','bu.branch_id','=','b.id')
   ->where('s.id',$id)->where('bu.user_id',auth('erp_tenant')->id())
   ->where('b.active',true)->exists();
  abort_unless($authorized,404);
  $locale=auth('erp_tenant')->user()?->locale??'en';
  try{[$data,$number]=$pdf->render($id,$locale);}catch(RuntimeException $e){return back()->withErrors(['pdf'=>$e->getMessage()]);}
  return response($data,200,['Content-Type'=>'application/pdf','Content-Disposition'=>'inline; filename="'.preg_replace('/[^a-zA-Z0-9-]/','',$number).'.pdf"',
      'Cache-Control'=>'private, no-store']);
 }
}
