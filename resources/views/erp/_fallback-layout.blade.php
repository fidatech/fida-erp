@php
$activeRoute = request()->route()?->getName() ?? '';
$groups = [
['id'=>'home','label'=>'Home','icon'=>'home','links'=>[
 ['label'=>'Dashboard','route'=>'erp.dashboard','icon'=>'layout-dashboard'],
 ['label'=>'My Workspace','route'=>'erp.profile','icon'=>'user-round'],
 ['label'=>'KPI Overview','route'=>'erp.kpi.index','icon'=>'chart-column'],
 ['label'=>'Charts & Analytics','route'=>'erp.analytics.index','icon'=>'chart-pie'],
 ['label'=>'Notifications','route'=>null,'icon'=>'bell'],
 ['label'=>'Configuration','route'=>'erp.settings.index','icon'=>'settings']]],
['id'=>'finance','label'=>'Finance','icon'=>'wallet','links'=>[
 ['label'=>'Financial Overview','route'=>'erp.finance.index','icon'=>'chart-column'],
 ['label'=>'General Ledger','route'=>'erp.finance.index','icon'=>'book-open'],
 ['label'=>'Trial Balance','route'=>'erp.finance.index','icon'=>'scale'],
 ['label'=>'Sales Invoices','route'=>'erp.sales.index','icon'=>'file-text'],
 ['label'=>'Cash & Banking','route'=>null,'icon'=>'landmark']]],
['id'=>'inventory','label'=>'Inventory','icon'=>'package','links'=>[
 ['label'=>'Inventory Overview','route'=>'erp.stock.index','icon'=>'package'],
 ['label'=>'Products & Variants','route'=>'erp.products.index','icon'=>'boxes'],
 ['label'=>'Goods Receiving','route'=>'erp.stock.index','icon'=>'truck'],
 ['label'=>'Warehouse Transfers','route'=>'erp.stock.transfers','icon'=>'arrow-left-right'],
 ['label'=>'Stock Counts','route'=>null,'icon'=>'clipboard-list']]],
['id'=>'procurement','label'=>'Procurement','icon'=>'shopping-cart','links'=>[
 ['label'=>'Suppliers & Receiving','route'=>'erp.stock.index','icon'=>'truck'],
 ['label'=>'Purchase Orders','route'=>null,'icon'=>'file-text']]],
['id'=>'sales','label'=>'Sales','icon'=>'receipt','links'=>[
 ['label'=>'Sales Overview','route'=>'erp.sales.index','icon'=>'chart-column'],
 ['label'=>'Sales Invoices','route'=>'erp.sales.index','icon'=>'receipt'],
 ['label'=>'Create Sale','route'=>'erp.sales.create','icon'=>'plus'],
 ['label'=>'Customers','route'=>'erp.customers.index','icon'=>'users'],
 ['label'=>'Point of Sale','route'=>null,'icon'=>'monitor']]],
['id'=>'hr','label'=>'HR','icon'=>'users','links'=>[
 ['label'=>'Employees','route'=>null,'icon'=>'users'],
 ['label'=>'Attendance','route'=>null,'icon'=>'calendar'],
 ['label'=>'Leave','route'=>null,'icon'=>'calendar']]],
['id'=>'payroll','label'=>'Payroll','icon'=>'briefcase-business','links'=>[
 ['label'=>'Payroll Overview','route'=>null,'icon'=>'wallet']]],
['id'=>'manufacturing','label'=>'Manufacturing','icon'=>'factory','links'=>[
 ['label'=>'Production Orders','route'=>null,'icon'=>'factory']]],
['id'=>'logistics','label'=>'Logistics','icon'=>'truck','links'=>[
 ['label'=>'Stock Transfers','route'=>'erp.stock.transfers','icon'=>'arrow-left-right']]],
['id'=>'reports','label'=>'Reports','icon'=>'chart-no-axes-column','links'=>[
 ['label'=>'KPI Overview','route'=>'erp.kpi.index','icon'=>'chart-column'],
 ['label'=>'Charts & Analytics','route'=>'erp.analytics.index','icon'=>'chart-pie'],
 ['label'=>'Financial Reports','route'=>'erp.finance.index','icon'=>'file-text']]],
['id'=>'settings','label'=>'Settings','icon'=>'settings','links'=>[
 ['label'=>'Company Profile','route'=>'erp.settings.index','icon'=>'building-2'],
 ['label'=>'Branches','route'=>'erp.settings.index','icon'=>'git-branch'],
 ['label'=>'Financial Periods','route'=>'erp.settings.index','icon'=>'calendar'],
 ['label'=>'My Profile','route'=>'erp.profile','icon'=>'user-round']]],
];
$section = match(true) {
 str_starts_with($activeRoute,'erp.finance.') => 'finance',
 str_starts_with($activeRoute,'erp.stock.') || str_starts_with($activeRoute,'erp.products.') => 'inventory',
 str_starts_with($activeRoute,'erp.sales.') || str_starts_with($activeRoute,'erp.customers.') => 'sales',
 str_starts_with($activeRoute,'erp.kpi.') || str_starts_with($activeRoute,'erp.analytics.') => 'reports',
 str_starts_with($activeRoute,'erp.settings.') || str_starts_with($activeRoute,'erp.profile') || str_starts_with($activeRoute,'erp.password.') => 'settings',
 default => 'home'
};
$user=auth('erp_tenant')->user();
$tenant=app('erp.current_tenant');
$rtl=in_array(app()->getLocale(),['fa','ps'],true);
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Workspace') | FidaERP</title>
<link rel="stylesheet" href="{{ asset('fida-erp/erp.css') }}">
<link rel="stylesheet" href="{{ asset('fida-erp/reference-ui.css') }}">
@if(file_exists(public_path('fida-admin/vendor/select2/select2.min.css')))<link rel="stylesheet" href="{{ asset('fida-admin/vendor/select2/select2.min.css') }}">@endif
@yield('head')
</head>
<body>
<div id="fida-shell" class="fida-shell">
<header class="fida-topbar">
 <a href="{{ route('erp.dashboard') }}" class="fida-brand" aria-label="FidaERP dashboard">
  <img src="{{ asset('fida-erp/fidaerp-mark.svg') }}" width="43" height="43" alt="">
  <span class="fida-brand-text"><strong>FidaERP</strong><small>Enterprise · People · Progress</small></span>
 </a>
 <button class="fida-mobile-toggle" id="fida-mobile-toggle" aria-label="Open menu" aria-expanded="false" type="button"><svg class="fi"><use href="{{ asset('fida-erp/ui-icons.svg') }}#menu"></use></svg></button>
 <div class="fida-search" role="search">
  <svg class="fi"><use href="{{ asset('fida-erp/ui-icons.svg') }}#search"></use></svg>
  <input id="fida-global-search" type="search" placeholder="Search modules, transactions, vendors, employees, documents..." aria-label="Search navigation">
  <kbd>⌘ K</kbd>
  <div id="fida-search-results" class="fida-search-results" hidden></div>
 </div>
 <div class="fida-top-actions">
  <button type="button" class="fida-icon-button" id="fida-actions-toggle" aria-label="Open quick actions" title="Quick actions"><svg class="fi"><use href="{{ asset('fida-erp/ui-icons.svg') }}#grid-2x2"></use></svg></button>
  <a href="{{ route('erp.profile') }}" class="fida-icon-button" title="Profile" aria-label="Profile"><svg class="fi"><use href="{{ asset('fida-erp/ui-icons.svg') }}#circle-help"></use></svg></a>
  <button type="button" class="fida-icon-button" id="fida-alert-toggle" aria-label="Notifications" title="Notifications"><svg class="fi"><use href="{{ asset('fida-erp/ui-icons.svg') }}#bell"></use></svg></button>
  <a href="{{ route('erp.profile') }}" class="fida-account"><span class="fida-avatar">{{ mb_strtoupper(mb_substr($user?->name ?? 'U',0,1)) }}</span><span><strong>{{ $user?->name ?? 'User' }}</strong><small>{{ $tenant->business_name }}</small></span><svg class="fi fi-xs"><use href="{{ asset('fida-erp/ui-icons.svg') }}#chevron-down"></use></svg></a>
 </div>
</header>
<div class="fida-workspace">
<nav class="fida-rail" id="fida-rail" aria-label="Main modules">
@foreach($groups as $group)
<button type="button" class="fida-rail-item {{ $section===$group['id']?'active':'' }}" data-group="{{ $group['id'] }}" aria-label="{{ $group['label'] }}" aria-pressed="{{ $section===$group['id']?'true':'false' }}">
<svg class="fi"><use href="{{ asset('fida-erp/ui-icons.svg') }}#{{ $group['icon'] }}"></use></svg><span>{{ $group['label'] }}</span>
</button>
@endforeach
</nav>
<aside class="fida-sidenav" id="fida-sidenav" aria-label="Section navigation">
@foreach($groups as $group)
<div class="fida-nav-group" data-nav-group="{{ $group['id'] }}" @if($section!==$group['id']) hidden @endif>
 <div class="fida-nav-heading">{{ $group['label'] }}</div>
 @foreach($group['links'] as $link)
 @if($link['route'])
 <a class="fida-nav-link {{ $link['route']===$activeRoute?'active':'' }}" href="{{ route($link['route']) }}"><svg class="fi"><use href="{{ asset('fida-erp/ui-icons.svg') }}#{{ $link['icon'] }}"></use></svg><span>{{ $link['label'] }}</span></a>
 @else
 <span class="fida-nav-link fida-nav-disabled" title="Not implemented yet"><svg class="fi"><use href="{{ asset('fida-erp/ui-icons.svg') }}#{{ $link['icon'] }}"></use></svg><span>{{ $link['label'] }}</span><small>Planned</small></span>
 @endif
 @endforeach
</div>
@endforeach
<div class="fida-sidenav-bottom">
<a class="fida-nav-link" href="{{ route('erp.settings.index') }}"><svg class="fi"><use href="{{ asset('fida-erp/ui-icons.svg') }}#settings"></use></svg>Settings</a>
<form action="{{ route('erp.locale') }}" method="POST">@csrf<label class="sr-only" for="fida-language">Language</label><select id="fida-language" class="erp-field" name="locale" onchange="this.form.submit()"><option value="en" @selected(app()->getLocale()==='en')>English</option><option value="fa" @selected(app()->getLocale()==='fa')>دری</option><option value="ps" @selected(app()->getLocale()==='ps')>پښتو</option></select></form>
<form method="POST" action="{{ route('erp.logout') }}">@csrf<button class="fida-logout" type="submit">Sign out ↗</button></form>
</div>
</aside>
<main class="fida-content" id="main-content">
@if(session('success'))<div class="erp-success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="erp-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
</main>
</div>
</div>
<div class="fida-drawer-shade" id="fida-drawer-shade" hidden></div>
<aside class="fida-drawer" id="fida-drawer" aria-label="Quick actions" aria-hidden="true">
 <div class="fida-drawer-header"><div><h2>Quick Actions</h2><p>Go straight to common workflows</p></div><button type="button" class="fida-icon-button" id="fida-drawer-close" aria-label="Close quick actions">×</button></div>
 @foreach([
 ['Create Sales Invoice','erp.sales.create','receipt','Record a new customer invoice'],
 ['Receive Goods','erp.stock.index','package','Enter a supplier receipt'],
 ['Record Journal','erp.finance.index','book-open','Post an accounting entry'],
 ['Transfer Stock','erp.stock.transfers','arrow-left-right','Move items between warehouses'],
 ['Add Customer','erp.customers.index','users','Manage customer records'],
 ['Add Product','erp.products.index','boxes','Maintain your product catalog'],
 ['Generate Reports','erp.analytics.index','chart-column','View live performance analytics']
 ] as $action)
 <a href="{{ route($action[1]) }}" class="fida-drawer-link"><span class="fida-action-icon"><svg class="fi"><use href="{{ asset('fida-erp/ui-icons.svg') }}#{{ $action[2] }}"></use></svg></span><span><strong>{{ $action[0] }}</strong><small>{{ $action[3] }}</small></span><svg class="fi fi-xs"><use href="{{ asset('fida-erp/ui-icons.svg') }}#chevron-right"></use></svg></a>
 @endforeach
</aside>
@if(file_exists(public_path('fida-admin/vendor/jquery/jquery.min.js')))<script src="{{ asset('fida-admin/vendor/jquery/jquery.min.js') }}"></script>@endif
@if(file_exists(public_path('fida-admin/vendor/select2/select2.min.js')))<script src="{{ asset('fida-admin/vendor/select2/select2.min.js') }}"></script>@endif
<script src="{{ asset('fida-erp/ui-shell.js') }}" defer></script>
<script>
window.FidaERP = {searchItems:@json(collect($groups)->flatMap(fn($g)=>collect($g['links'])->filter(fn($l)=>!is_null($l['route']))->map(fn($l)=>['label'=>$l['label'],'group'=>$g['label'],'url'=>route($l['route'])]))->values())};
</script>
@yield('scripts')
</body>
</html>
