<?php
namespace App\Http\Controllers\ERP;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
final class AuthController extends Controller
{
    public function show(){return view('erp.auth.login');}
    public function login(Request $request){
        $credentials=$request->validate(['email'=>['required','email'],'password'=>['required','string']]);
        $credentials['active']=true;
        if(!Auth::guard('erp_tenant')->attempt($credentials))throw ValidationException::withMessages(['email'=>__('erp::ui.invalid_login')]);
        $request->session()->regenerate();
        return redirect()->route('erp.dashboard');
    }
    public function logout(Request $request){Auth::guard('erp_tenant')->logout();$request->session()->invalidate();$request->session()->regenerateToken();return redirect()->route('erp.login');}
}
