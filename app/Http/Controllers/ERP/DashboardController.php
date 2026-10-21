<?php
namespace App\Http\Controllers\ERP;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
final class DashboardController extends Controller
{
    public function __invoke(){
        $db=DB::connection('erp_tenant');
        return view('erp.dashboard',['counts'=>[
            'customers'=>$db->table('customers')->count(),'products'=>$db->table('products')->count(),
            'drafts'=>$db->table('sales_invoices')->where('status','draft')->count(),
            'posted'=>$db->table('sales_invoices')->where('status','posted')->count(),
        ],'recent'=>$db->table('sales_invoices')->join('customers','sales_invoices.customer_id','=','customers.id')
            ->select('sales_invoices.id','sales_invoices.number','sales_invoices.total','sales_invoices.currency_code','sales_invoices.status','customers.name as customer')
            ->orderByDesc('sales_invoices.id')->limit(8)->get()]);
    }
}
