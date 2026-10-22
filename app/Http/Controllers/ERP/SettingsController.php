<?php
namespace App\Http\Controllers\ERP;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class SettingsController extends Controller
{
    public function index()
    {
        $db=DB::connection('erp_tenant');
        return view('erp.settings.index',[
            'company'=>$db->table('company_settings')->first(),
            'branches'=>$db->table('branches')->orderBy('name')->get(),
            'periods'=>$db->table('fiscal_periods')->orderByDesc('starts_on')->get(),
            'currency'=>$db->table('company_settings')->value('base_currency'),
            'usersCount'=>$db->table('users')->count()
        ]);
    }

    public function updateCompany(Request $request)
    {
        $input=$request->validate([
            'name'=>'required|string|max:190',
            'locale'=>['required',Rule::in(['en','fa','ps'])],
            'timezone'=>['required',Rule::in(['Asia/Kabul','UTC','Asia/Dubai','Asia/Muscat'])]
        ]);
        DB::connection('erp_tenant')->table('company_settings')->update([
            ...$input,'updated_at'=>now()
        ]);
        return back()->with('success','Company settings updated');
    }

    public function addBranch(Request $request)
    {
        $input=$request->validate([
            'code'=>'required|string|max:24|regex:/^[A-Z0-9_-]+$/|unique:erp_tenant.branches,code',
            'name'=>'required|string|max:190'
        ]);
        DB::connection('erp_tenant')->table('branches')->insert([
            ...$input,'active'=>1,'created_at'=>now(),'updated_at'=>now()
        ]);
        return back()->with('success','Branch created');
    }
}
