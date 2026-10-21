<?php
namespace App\Http\Controllers\ERP;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
final class ProfileController extends Controller
{
 public function index(){return view('erp.profile');}
 public function save(Request $request){
  $data=$request->validate(['name'=>'required|string|max:190','email'=>'required|email|max:255']);
  $user=auth('erp_tenant')->user();
  if($user->email!==$data['email'] && $user->newQuery()->where('email',$data['email'])->exists())return back()->withErrors(['email'=>'Email already used']);
  $user->fill($data)->save();return back()->with('success','Profile updated');
 }
 public function password(Request $request){
  $data=$request->validate(['current_password'=>'required|string','password'=>'required|string|min:12|confirmed']);
  $user=auth('erp_tenant')->user();
  if(!Hash::check($data['current_password'],$user->password))return back()->withErrors(['current_password'=>'Current password incorrect']);
  $user->password=Hash::make($data['password']);$user->save();
  $request->session()->regenerate();return back()->with('success','Password changed');
 }
}
