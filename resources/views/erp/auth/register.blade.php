<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Register Business | FidaERP</title><style>*{box-sizing:border-box}body{margin:0;background:linear-gradient(130deg,#eaf3ff,#f8fbff);color:#102b57;font:14px Inter,Segoe UI,system-ui,sans-serif}.top{padding:22px 6%;background:#ffffffd9;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #dfe8f4}.logo{font-size:25px;font-weight:800;color:#082e61;text-decoration:none}.top a:not(.logo){color:#086ce6;text-decoration:none}.wrap{min-height:calc(100vh - 75px);display:grid;grid-template-columns:1fr 1.1fr;gap:5vw;align-items:center;max-width:1480px;margin:auto;padding:45px 5%}.intro h1{font-size:clamp(36px,4vw,62px);letter-spacing:-2px;line-height:1.12;margin:0 0 20px}.intro h1 span{color:#1478ed}.intro p{color:#536b91;font-size:19px;line-height:1.6;max-width:570px}.perk{display:flex;align-items:center;gap:13px;margin:25px 0;font-weight:700}.perk b{display:grid;place-items:center;width:48px;height:48px;border-radius:12px;background:#dceaff;color:#0976eb;font-size:23px}.card{background:white;border:1px solid #e1eaf6;border-radius:17px;padding:40px;box-shadow:0 18px 50px #0c326010}.card h2{font-size:30px;margin:0 0 8px}.card p{color:#627694;margin:0 0 20px}.fields{display:grid;grid-template-columns:1fr 1fr;gap:14px 18px}label{display:block;font-size:12px;font-weight:700;margin-bottom:7px}input,select{width:100%;border:1px solid #d5e0ee;border-radius:8px;padding:12px 13px;font:inherit;min-height:46px;background:#fff}.wide{grid-column:1/-1}.terms{display:flex;align-items:center;gap:10px;margin:18px 0;color:#455f82}.terms input{width:17px;min-height:17px}.submit{width:100%;background:#082e61;color:#fff;border:0;border-radius:8px;padding:16px;font-weight:700;cursor:pointer}.note{background:#edf5ff;color:#375a82;padding:13px;border-radius:8px;font-size:12px;margin:16px 0}.error{background:#fff1f1;color:#a52234;padding:12px;border-radius:8px;margin:12px 0}.success{background:#e8faf2;color:#087b57;padding:12px;border-radius:8px;margin:12px 0}@media(max-width:850px){.wrap{grid-template-columns:1fr}.intro{display:none}.card{padding:25px}.fields{grid-template-columns:1fr}}</style></head><body><header class="top"><a class="logo" href="/" style="display:inline-flex;align-items:center;gap:10px"><img src="{{ asset('fida-erp/fidaerp-mark.svg') }}" width="42" height="42" alt="">FidaERP</a><span>Already have an account? <a href="{{ route('erp.platform.login') }}">Platform Sign In</a></span></header><main class="wrap"><section class="intro"><h1>Streamline. Automate.<br>Grow with <span>FidaERP.</span></h1><p>Manage finance, sales, inventory, procurement, human resources and more from one secure workspace.</p><div class="perk"><b>▥</b> All-in-one business management</div><div class="perk"><b>✓</b> Secure company data isolation</div><div class="perk"><b>♧</b> Built for teams and growing companies</div></section>@if($registrationProgress)
<section class="card" style="grid-column:2" id="erp-wizard">
<h2>Setting up your workspace</h2>
<p id="erp-wizard-message">Checking your registration...</p>
<ol style="line-height:2.7;font-size:15px">
<li>Business details submitted</li>
<li id="erp-step-email">Email verification pending</li>
<li id="erp-step-db">Private database provisioning pending</li>
<li id="erp-step-ready">Tenant login pending</li>
</ol>
<a id="erp-tenant-link" href="#" hidden style="display:none;background:#082e61;color:white;padding:14px;border-radius:8px;text-decoration:none">Open your tenant login →</a>
<p id="erp-wizard-note">This page updates automatically. Verify your email using the link we sent.</p>
</section>
<script>
(function(){
const message=document.getElementById('erp-wizard-message');
const link=document.getElementById('erp-tenant-link');
async function poll(){
try{
const r=await fetch('{{ route('erp.register.progress') }}',{credentials:'same-origin',cache:'no-store'});
if(!r.ok)return;
const d=await r.json();
if(d.email_verified){
document.getElementById('erp-step-email').textContent='Email verified ✓';
message.textContent='Your company workspace is being prepared.';
}
if(d.provisioning_stage){
document.getElementById('erp-step-db').textContent='Database setup: '+d.provisioning_stage;
}
if(d.login_url){
document.getElementById('erp-step-db').textContent='Private database ready ✓';
document.getElementById('erp-step-ready').textContent='Workspace ready ✓';
message.textContent='Your tenant is ready. Sign in with your registered credentials.';
link.href=d.login_url;link.hidden=false;link.style.display='inline-block';
clearInterval(window.erpWizardTimer);
}else if(d.provisioning_stage==='awaiting_domain'){
document.getElementById('erp-wizard-note').textContent='Waiting for secure domain activation and SSL certificate.';
}
}catch(e){}
}
poll();window.erpWizardTimer=setInterval(poll,8000);
})();
</script>
@else
<form class="card" method="POST" action="{{ route('erp.register.submit') }}">@csrf<h2>Create Your Account</h2><p>Create your business workspace with a 7-day free trial. Verify your email to begin.</p>@if(session('success'))<div class="success">{{ session('success') }}</div>@endif @if($errors->any())<div class="error">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif<div style="position:absolute;left:-9999px" aria-hidden="true"><label for="website">Website (leave blank)</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div><div class="fields"><div><label for="name">Full Name *</label><input id="name" name="name" required value="{{ old('name') }}" autocomplete="name"></div><div><label for="email">Work Email *</label><input id="email" type="email" name="email" required value="{{ old('email') }}" autocomplete="email"></div><div><label for="company">Company Name *</label><input id="company" name="company" required value="{{ old('company') }}"></div><div><label for="phone">Phone Number *</label><input id="phone" name="phone" required value="{{ old('phone') }}" autocomplete="tel"></div><div class="wide"><label for="country">Country *</label><select id="country" name="country"><option value="Afghanistan">Afghanistan</option><option value="Oman">Oman</option><option value="United Arab Emirates">United Arab Emirates</option><option value="Other">Other</option></select></div><div><label for="password">Password (minimum 12 characters) *</label><input id="password" type="password" name="password" required minlength="12" autocomplete="new-password"></div><div><label for="password_confirmation">Confirm Password *</label><input id="password_confirmation" type="password" name="password_confirmation" required minlength="12" autocomplete="new-password"></div></div><div class="note">We will email you a secure verification link. Your workspace will be provisioned after you verify your email.</div><label class="terms"><input type="checkbox" name="terms" value="1" required> I agree to the terms and privacy policy.</label><button class="submit">Create Account &amp; Verify Email →</button></form>@endif<form class="card" method="POST" action="{{ route('erp.register.resend') }}" style="grid-column:2;padding:24px">@csrf<h2 style="font-size:20px">Resend verification link</h2><p>Did not receive your email? Request a new link.</p><label for="resend_email">Email address</label><input id="resend_email" type="email" name="email" required><button class="submit" style="margin-top:12px">Resend verification email</button></form></main></body></html>