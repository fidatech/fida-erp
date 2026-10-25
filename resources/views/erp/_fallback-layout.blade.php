@php($rtl = in_array(app()->getLocale(), ['fa','ps'], true))
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ $title ?? 'Fida ERP' }} | Fida ERP</title>
<link rel="stylesheet" href="{{ asset('fida-erp/erp.css') }}">
@if(file_exists(public_path('fida-admin/vendor/select2/select2.min.css')))<link rel="stylesheet" href="{{ asset('fida-admin/vendor/select2/select2.min.css') }}">@endif
@yield('head')
</head>
<body>
<div class="erp-mobile-overlay" id="erp-menu-overlay" aria-hidden="true"></div>
<div class="erp-shell">
<aside class="erp-sidebar" id="erp-sidebar" aria-label="ERP navigation"><div class="erp-brand">Fida<span>.</span> ERP</div>
<nav class="erp-nav">
<a href="{{ route('erp.dashboard') }}" class="{{ request()->routeIs('erp.dashboard')?'active':'' }}">◧ {{ __('erp::ui.dashboard') }}</a>
<a href="{{ route('erp.customers.index') }}" class="{{ request()->routeIs('erp.customers.*')?'active':'' }}">♙ {{ __('erp::ui.customers') }}</a>
<a href="{{ route('erp.products.index') }}" class="{{ request()->routeIs('erp.products.*')?'active':'' }}">▦ {{ __('erp::ui.products') }}</a>
<a href="{{ route('erp.stock.index') }}" class="{{ request()->routeIs('erp.stock.*')?'active':'' }}">▥ {{ __('erp::ui.stock') }}</a>
<a href="{{ route('erp.sales.index') }}" class="{{ request()->routeIs('erp.sales.*')?'active':'' }}">▤ {{ __('erp::ui.sales') }}</a>
<a href="{{ route('erp.profile') }}" class="{{ request()->routeIs('erp.profile*')?'active':'' }}">◎ {{ __('erp::ui.profile') }}</a>
</nav></aside>
<div class="erp-main"><header class="erp-top"><div class="erp-flex"><button class="erp-btn secondary erp-mobile" type="button" id="erp-menu-toggle" aria-controls="erp-sidebar" aria-expanded="false" aria-label="Toggle navigation">☰</button><b>Fida ERP</b><span class="erp-muted">{{ app('erp.current_tenant')->business_name }}</span></div>
<div class="erp-flex"><form action="{{ route('erp.locale') }}" method="POST">@csrf<select class="erp-lang" name="locale" onchange="this.form.submit()"><option value="en" @selected(app()->getLocale()==='en')>English</option><option value="fa" @selected(app()->getLocale()==='fa')>دری</option><option value="ps" @selected(app()->getLocale()==='ps')>پښتو</option></select></form>
<span class="erp-muted">{{ auth('erp_tenant')->user()?->name }}</span><form action="{{ route('erp.logout') }}" method="POST">@csrf<button class="erp-btn secondary">{{ __('erp::ui.logout') }}</button></form></div></header>
<main class="erp-content">@if(session('success'))<div class="erp-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="erp-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')</main></div></div>
@if(file_exists(public_path('fida-admin/vendor/jquery/jquery.min.js')))<script src="{{ asset('fida-admin/vendor/jquery/jquery.min.js') }}"></script>@endif
@if(file_exists(public_path('fida-admin/vendor/select2/select2.min.js')))<script src="{{ asset('fida-admin/vendor/select2/select2.min.js') }}"></script>@endif
<script>
(()=>{const sidebar=document.getElementById('erp-sidebar');
const toggle=document.getElementById('erp-menu-toggle');
const overlay=document.getElementById('erp-menu-overlay');
if(!sidebar||!toggle||!overlay)return;
function setOpen(open){sidebar.classList.toggle('open',open);
document.body.classList.toggle('erp-menu-open',open);
toggle.setAttribute('aria-expanded',String(open));}
toggle.addEventListener('click',()=>setOpen(!sidebar.classList.contains('open')));
overlay.addEventListener('click',()=>setOpen(false));
document.addEventListener('keydown',e=>{if(e.key==='Escape')setOpen(false);});
sidebar.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>setOpen(false)));
window.addEventListener('resize',()=>{if(window.innerWidth>820)setOpen(false);});
})();
</script>
@yield('scripts')
</body></html>
