@php
$rtl = in_array(app()->getLocale(), ['fa','ps'], true);
$nav = [
 ['key'=>'home','label'=>'Home','icon'=>'⌂','items'=>[
  ['label'=>'Dashboard','route'=>'erp.dashboard'],['label'=>'My Workspace','route'=>'erp.profile'],['label'=>'Customers','route'=>'erp.customers.index'],['label'=>'Tasks','route'=>null],['label'=>'Notifications','route'=>null],['label'=>'Master Data','route'=>'erp.products.index'],['label'=>'Configuration','route'=>'erp.profile']]],
 ['key'=>'finance','label'=>'Finance','icon'=>'▣','items'=>[['label'=>'Overview','route'=>'erp.dashboard'],['label'=>'Customers','route'=>'erp.customers.index'],['label'=>'Invoices','route'=>'erp.sales.index'],['label'=>'Accounting','route'=>'erp.finance.index'],['label'=>'Currencies','route'=>null]]],
 ['key'=>'inventory','label'=>'Inventory','icon'=>'◈','items'=>[['label'=>'Stock Overview','route'=>'erp.stock.index'],['label'=>'Products','route'=>'erp.products.index'],['label'=>'Suppliers','route'=>'erp.stock.index'],['label'=>'Warehouse transfers','route'=>'erp.stock.transfers']]],
 ['key'=>'procurement','label'=>'Procurement','icon'=>'🛒','items'=>[['label'=>'Purchasing','route'=>'erp.stock.index'],['label'=>'Suppliers','route'=>'erp.stock.index'],['label'=>'Purchase Orders','route'=>null]]],
 ['key'=>'sales','label'=>'Sales & POS','icon'=>'▤','items'=>[['label'=>'Sales','route'=>'erp.sales.index'],['label'=>'New Invoice','route'=>'erp.sales.create'],['label'=>'Customers','route'=>'erp.customers.index'],['label'=>'POS','route'=>null]]],
 ['key'=>'hr','label'=>'HR','icon'=>'♧','items'=>[['label'=>'Employees','route'=>null],['label'=>'Attendance','route'=>null],['label'=>'Leave','route'=>null]]],
 ['key'=>'payroll','label'=>'Payroll','icon'=>'▧','items'=>[['label'=>'Payroll Overview','route'=>null],['label'=>'Payslips','route'=>null]]],
 ['key'=>'manufacturing','label'=>'Manufacturing','icon'=>'⚙','items'=>[['label'=>'Production','route'=>null],['label'=>'BOM','route'=>null]]],
 ['key'=>'logistics','label'=>'Logistics','icon'=>'▱','items'=>[['label'=>'Deliveries','route'=>null],['label'=>'Transfers','route'=>'erp.stock.transfers']]],
 ['key'=>'reports','label'=>'Reports','icon'=>'▥','items'=>[['label'=>'Sales Reports','route'=>'erp.sales.index'],['label'=>'Inventory Reports','route'=>'erp.stock.index'],['label'=>'Financial Reports','route'=>'erp.finance.index']]],
 ['key'=>'settings','label'=>'Settings','icon'=>'⚙','items'=>[['label'=>'My Profile','route'=>'erp.profile'],['label'=>'Company Settings','route'=>null],['label'=>'Users & Permissions','route'=>null]]],
];
$currentRoute = request()->route()?->getName() ?? '';
$section = match (true) {
 str_starts_with($currentRoute, 'erp.sales.') => 'sales',
 str_starts_with($currentRoute, 'erp.finance.') => 'finance',
 str_starts_with($currentRoute, 'erp.stock.'), str_starts_with($currentRoute, 'erp.products.') => 'inventory',
 str_starts_with($currentRoute, 'erp.customers.') => 'finance',
 $currentRoute === 'erp.profile' => 'settings',
 default => 'home',
};
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ?? 'Dashboard' }} | FidaERP</title>
<link rel="stylesheet" href="{{ asset('fida-erp/erp.css') }}">
@if(file_exists(public_path('fida-admin/vendor/select2/select2.min.css')))<link rel="stylesheet" href="{{ asset('fida-admin/vendor/select2/select2.min.css') }}">@endif
@yield('head')
</head>
<body>
<div class="nexa-shell" id="nexa-shell">
<header class="nexa-header">
  <div class="nexa-brand"><span class="nexa-brand-mark" aria-hidden="true"><img src="{{ asset('fida-erp/fidaerp-mark.svg') }}" alt="" width="42" height="42"></span><span><strong>FidaERP</strong><small>Enterprise • People • Progress</small></span></div>
  <button type="button" class="nexa-mobile-menu" id="nexa-menu" aria-label="Toggle navigation" aria-expanded="false">☰</button>
  <div class="nexa-global-search"><span aria-hidden="true">⌕</span><input id="nexa-search" type="search" placeholder="Search modules, transactions, customers, products..." aria-label="Search navigation"><kbd>⌘ K</kbd></div>
  <div class="nexa-header-actions"><span class="nexa-header-icon" title="Notifications">♧</span><span class="nexa-header-icon" title="Help">ⓘ</span><span class="nexa-header-icon" title="Applications">▦</span><div class="nexa-profile"><span class="nexa-avatar">{{ strtoupper(substr(auth('erp_tenant')->user()?->name ?? 'U',0,1)) }}</span><span><strong>{{ auth('erp_tenant')->user()?->name ?? 'User' }}</strong><small>{{ app('erp.current_tenant')->business_name }}</small></span></div></div>
</header>
<div class="nexa-body">
<nav class="nexa-rail" aria-label="ERP modules">
@foreach($nav as $group)
<button type="button" data-section="{{ $group['key'] }}" class="nexa-rail-item {{ $section === $group['key'] ? 'active' : '' }}" aria-label="{{ $group['label'] }}"><span class="nexa-rail-icon">{{ $group['icon'] }}</span><span>{{ $group['label'] }}</span></button>
@endforeach
</nav>
<aside class="nexa-subnav" id="nexa-subnav" aria-label="Module navigation">
@foreach($nav as $group)
<div class="nexa-subnav-group" data-nav-section="{{ $group['key'] }}" @if($section !== $group['key']) hidden @endif>
<div class="nexa-subnav-heading">{{ $group['label'] }}</div>
@foreach($group['items'] as $item)
@if($item['route'])
<a class="nexa-subnav-link {{ $section === $group['key'] && $currentRoute === $item['route'] ? 'active' : '' }}" href="{{ route($item['route']) }}"><span class="nexa-link-icon">◇</span>{{ $item['label'] }}</a>
@else
<span class="nexa-subnav-link nexad-disabled" title="Module planned"><span class="nexa-link-icon">◇</span>{{ $item['label'] }}<small>Coming soon</small></span>
@endif
@endforeach
</div>
@endforeach
<div class="nexa-subnav-bottom"><form action="{{ route('erp.locale') }}" method="POST">@csrf<select class="erp-lang" name="locale" aria-label="Language" onchange="this.form.submit()"><option value="en" @selected(app()->getLocale()==='en')>English</option><option value="fa" @selected(app()->getLocale()==='fa')>دری</option><option value="ps" @selected(app()->getLocale()==='ps')>پښتو</option></select></form><form method="POST" action="{{ route('erp.logout') }}">@csrf<button class="nexa-signout">Sign out ↗</button></form></div>
</aside>
<main class="nexa-main" id="main-content">
@if(session('success'))<div class="erp-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="erp-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
</main>
</div></div>
@if(file_exists(public_path('fida-admin/vendor/jquery/jquery.min.js')))<script src="{{ asset('fida-admin/vendor/jquery/jquery.min.js') }}"></script>@endif
@if(file_exists(public_path('fida-admin/vendor/select2/select2.min.js')))<script src="{{ asset('fida-admin/vendor/select2/select2.min.js') }}"></script>@endif
<script>
(function(){
 const shell=document.getElementById('nexa-shell');
 const rail=[...document.querySelectorAll('[data-section]')];
 const panels=[...document.querySelectorAll('[data-nav-section]')];
 rail.forEach(button=>button.addEventListener('click',()=>{
  rail.forEach(x=>x.classList.toggle('active',x===button));
  panels.forEach(x=>x.hidden=x.dataset.navSection!==button.dataset.section);
  shell.classList.remove('mobile-open');
 }));
 document.getElementById('nexa-menu')?.addEventListener('click',function(){
  const opened=shell.classList.toggle('mobile-open');this.setAttribute('aria-expanded',String(opened));
 });
 const search=document.getElementById('nexa-search');
 document.addEventListener('keydown',e=>{if((e.metaKey||e.ctrlKey)&&e.key.toLowerCase()==='k'){e.preventDefault();search?.focus();}if(e.key==='Escape'){search?.blur();shell.classList.remove('mobile-open');}});
 search?.addEventListener('input',()=>{
  const q=search.value.trim().toLowerCase();
  if(q){panels.forEach(p=>{p.hidden=false;p.querySelectorAll('.nexa-subnav-link').forEach(link=>link.hidden=!link.textContent.toLowerCase().includes(q));});}
  else {panels.forEach(p=>{p.hidden=!document.querySelector('.nexa-rail-item.active[data-section="'+p.dataset.navSection+'"]');p.querySelectorAll('.nexa-subnav-link').forEach(link=>link.hidden=false);});}
 });
})();
</script>
@yield('scripts')
</body></html>
