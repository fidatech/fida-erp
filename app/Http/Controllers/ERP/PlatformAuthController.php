<?php
namespace App\Http\Controllers\ERP;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
final class PlatformAuthController extends Controller
{
 public function show(Request $request){abort_unless($request->getHost()===config('erp.platform_host'),404);return view('erp.platform.login');}
 public function login(Request $request){
  abort_unless($request->getHost()===config('erp.platform_host'),404);
  $v=$request->validate(['email'=>'required|email','password'=>'required|string']);
  if(!Auth::guard('web')->attempt([...$v,'erp_platform_admin'=>1]))throw ValidationException::withMessages(['email'=>'Invalid administrator credentials']);
  $request->session()->regenerate();return redirect()->route('erp.platform.index');
 }
 public function logout(Request $request){abort_unless($request->getHost()===config('erp.platform_host'),404);Auth::guard('web')->logout();$request->session()->invalidate();$request->session()->regenerateToken();return redirect()->route('erp.platform.login');}
}
