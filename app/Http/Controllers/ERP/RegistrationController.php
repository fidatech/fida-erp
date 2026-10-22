<?php
namespace App\Http\Controllers\ERP;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
class RegistrationController extends Controller {
 public function show(){ return view('erp.auth.register'); }
 public function store(Request $request){
  $data=$request->validate([
   'name'=>'required|string|max:120',
   'email'=>'required|email|max:190',
   'company'=>'required|string|max:190',
   'phone'=>'required|string|max:40',
   'country'=>'required|string|max:90',
   'password'=>'required|string|min:12|confirmed',
   'terms'=>'accepted',
  ]);
  $key='erp-registration:'.$request->ip();
  if(RateLimiter::tooManyAttempts($key,3)) return back()->withErrors(['email'=>'Too many requests. Please try again later.']);
  RateLimiter::hit($key,3600);
  $data['email']=Str::lower(trim($data['email']));
  $db=DB::connection(config('erp.central_connection','mysql'));
  $exists=$db->table('erp_registration_requests')->where('email',$data['email'])->whereIn('status',['pending','reviewed','provisioning','approved'])->exists();
  if($exists) return back()->withErrors(['email'=>'A registration request already exists for this email.']);
  $db->table('erp_registration_requests')->insert([
   'name'=>$data['name'],'email'=>$data['email'],'company'=>$data['company'],
   'phone'=>$data['phone'],'country'=>$data['country'],
   'password_hash'=>bcrypt($data['password']),'status'=>'pending',
   'created_at'=>now(),'updated_at'=>now(),
  ]);
  return redirect()->route('erp.register')->with('success','Your business registration request was received. An administrator will review it before the workspace is activated.');
 }
}
