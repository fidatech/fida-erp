<?php
namespace App\Http\Controllers\ERP;
use App\Http\Controllers\Controller;
use App\Services\ERP\BranchWarehouseAccess;
use App\Services\ERP\InvoicePdfService;
use Illuminate\Http\Request;
use RuntimeException;
use Illuminate\Support\Facades\DB;
final class InvoicePdfController extends Controller
{
 public function __invoke(int $id,Request $request,InvoicePdfService $pdf){
  $warehouseId = DB::connection('erp_tenant')->table('sales_invoices')
   ->where('id', $id)->value('warehouse_id');
  abort_unless($warehouseId && app(BranchWarehouseAccess::class)->allows((int) $warehouseId), 404);
  $locale=auth('erp_tenant')->user()?->locale??'en';
  try{[$data,$number]=$pdf->render($id,$locale);}catch(RuntimeException $e){return back()->withErrors(['pdf'=>$e->getMessage()]);}
  return response($data,200,['Content-Type'=>'application/pdf','Content-Disposition'=>'inline; filename="'.preg_replace('/[^a-zA-Z0-9-]/','',$number).'.pdf"',
      'Cache-Control'=>'private, no-store']);
 }
}
