<?php
namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessErpRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class RegistrationController extends Controller {
 private function database() { return DB::connection(config('erp.central_connection','mysql')); }
 private function sendVerification(int $id,string $email): void {
  $token=Str::random(64);
  $this->database()->table('erp_registration_requests')->where('id',$id)->update([
   'verification_token_hash'=>hash('sha256',$token),
   'verification_expires_at'=>now()->addMinutes(60),
   'verification_sent_at'=>now(),'updated_at'=>now()
  ]);
  $link=route('erp.register.verify',['id'=>$id,'token'=>$token]);
  Mail::raw("Welcome to FidaERP. Verify your business email within 60 minutes:\n\n".$link."\n\nIf you did not register, ignore this email.",function($message)use($email){
   $message->to($email)->subject('Verify your FidaERP business email');
  });
 }
 public function show(Request $request){return view('erp.auth.register',['registrationProgress'=>$request->session()->get('erp_registration_id')]);}
 public function progress(Request $request){
  $id=(int)$request->session()->get('erp_registration_id',0);
  if(!$id)return response()->json(['state'=>'not_started']);
  $db=$this->database();
  $registration=$db->table('erp_registration_requests')->where('id',$id)->first();
  if(!$registration)return response()->json(['state'=>'not_started']);
  $run=$db->table('erp_provisioning_runs')->where('registration_id',$id)->first();
  $state=$registration->status;
  $url=null;
  if($state==='approved' && $run){
   $tenant=$db->table('erp_tenants')->where('id',$run->tenant_id)->first();
   if($tenant && $tenant->status==='active' && str_ends_with($tenant->domain,'.'.config('erp.platform_host')))$url='https://'.$tenant->domain.'/erp/login';
  }
  return response()->json(['state'=>$state,'email_verified'=>!is_null($registration->email_verified_at),'provisioning_stage'=>$run?->stage,'login_url'=>$url])->header('Cache-Control','no-store');
 }
 public function store(Request $request){
  $data=$request->validate([
   'name'=>'required|string|max:120','email'=>'required|email|max:190',
   'company'=>'required|string|max:190','phone'=>'required|string|max:40',
   'country'=>'required|string|max:90','password'=>'required|string|min:12|confirmed','terms'=>'accepted',
   'website'=>'nullable|size:0'
  ]);
  $key='erp-registration:'.$request->ip();
  if(RateLimiter::tooManyAttempts($key,3))return back()->withErrors(['email'=>'Too many requests. Please try again later.']);
  RateLimiter::hit($key,3600);
  $data['email']=Str::lower(trim($data['email']));
  $db=$this->database();
  if($db->table('erp_registration_requests')->where('email',$data['email'])->whereIn('status',['pending','reviewed','provisioning','approved'])->exists()){
   return back()->withErrors(['email'=>'A registration request already exists for this email.']);
  }
  $id=$db->table('erp_registration_requests')->insertGetId([
   'name'=>$data['name'],'email'=>$data['email'],'company'=>$data['company'],
   'phone'=>$data['phone'],'country'=>$data['country'],
   'password_hash'=>bcrypt($data['password']),'status'=>'pending',
   'created_at'=>now(),'updated_at'=>now()
  ]);
  $request->session()->put('erp_registration_id',$id);
  try{$this->sendVerification($id,$data['email']);}
  catch(\Throwable $e){report($e);return redirect()->route('erp.register')->with('success','Registration saved, but the verification email could not be sent. Please request a new link.');}
  return redirect()->route('erp.register')->with('success','Check your email for the verification link. It expires in 60 minutes.');
 }
 public function verify(Request $request,int $id,string $token){
  if(!preg_match('/^[a-zA-Z0-9]{64}$/D',$token))abort(404);
  $db=$this->database();
  $updated=$db->table('erp_registration_requests')->where('id',$id)->where('status','pending')
   ->where('verification_token_hash',hash('sha256',$token))
   ->where('verification_expires_at','>',now())
   ->update(['status'=>'provisioning','email_verified_at'=>now(),
    'verification_token_hash'=>null,'verification_expires_at'=>null,'updated_at'=>now()]);
  if(!$updated)return redirect()->route('erp.register')->withErrors(['email'=>'Verification link is invalid, expired or already used.']);
  $request->session()->put('erp_registration_id',$id);
  ProcessErpRegistration::dispatch($id);
  return redirect()->route('erp.register')->with('success','Email verified. Your workspace is being prepared.');
 }
 public function resend(Request $request){
  $data=$request->validate(['email'=>'required|email|max:190']);
  $key='erp-resend:'.$request->ip();
  if(RateLimiter::tooManyAttempts($key,3))return back()->withErrors(['email'=>'Please try again later.']);
  RateLimiter::hit($key,3600);
  $row=$this->database()->table('erp_registration_requests')->where('email',Str::lower(trim($data['email'])))->where('status','pending')->first();
  if($row && (!$row->verification_sent_at || now()->diffInSeconds($row->verification_sent_at)>=60)){
   try{$this->sendVerification($row->id,$row->email);}catch(\Throwable $e){report($e);}
  }
  return redirect()->route('erp.register')->with('success','If an unverified registration exists, a new link will be sent.');
 }
}
