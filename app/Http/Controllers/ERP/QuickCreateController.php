<?php
namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class QuickCreateController extends Controller
{
    public function warehouse(Request $request)
    {
        $data=$request->validate([
            'name'=>'required|string|max:190',
            'code'=>['required','string','max:40','regex:/^[A-Za-z0-9_-]+$/','unique:erp_tenant.warehouses,code'],
            'branch_id'=>'required|integer|exists:erp_tenant.branches,id',
        ]);
        $id=DB::connection('erp_tenant')->table('warehouses')->insertGetId([...$data,'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['id'=>$id,'text'=>$data['name']],201);
    }
    public function currency(Request $request)
    {
        $data=$request->validate([
            'code'=>['required','string','size:3','regex:/^[A-Za-z]{3}$/','unique:erp_tenant.currencies,code'],
            'name'=>'required|string|max:190',
            'minor_units'=>'required|integer|between:0,4',
        ]);
        $data['code']=strtoupper($data['code']);
        $db=DB::connection('erp_tenant');
        if($db->table('currencies')->where('code',$data['code'])->exists()){
            return response()->json(['message'=>'Currency already exists','errors'=>['code'=>['Currency already exists']]],422);
        }
        $db->table('currencies')->insert([...$data,'active'=>1]);
        return response()->json(['id'=>$data['code'],'text'=>$data['code'].' · '.$data['name']],201);
    }
}
