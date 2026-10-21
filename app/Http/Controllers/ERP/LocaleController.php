<?php
namespace App\Http\Controllers\ERP;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
final class LocaleController extends Controller
{
 public function __invoke(Request $request){
  $request->validate(['locale'=>'required|in:en,fa,ps']);
  $user=auth('erp_tenant')->user();$user->locale=$request->locale;$user->save();
  return back();
 }
}
