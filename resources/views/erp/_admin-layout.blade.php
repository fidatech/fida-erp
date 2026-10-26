<x-admin.layout :title="$title ?? 'Fida ERP'">
<link rel="stylesheet" href="{{ asset('fida-erp/erp.css') }}">
<div class="erp-between erp-admin-toolbar" style="margin-bottom:20px"><nav class="erp-flex erp-admin-nav" aria-label="ERP sections">
<a href="{{ route('erp.dashboard') }}" class="erp-btn secondary">{{ __('erp::ui.dashboard') }}</a>
<a href="{{ route('erp.customers.index') }}" class="erp-btn secondary">{{ __('erp::ui.customers') }}</a>
<a href="{{ route('erp.products.index') }}" class="erp-btn secondary">{{ __('erp::ui.products') }}</a>
<a href="{{ route('erp.stock.index') }}" class="erp-btn secondary">{{ __('erp::ui.stock') }}</a>
<a href="{{ route('erp.sales.index') }}" class="erp-btn secondary">{{ __('erp::ui.sales') }}</a>
<a href="{{ route('erp.profile') }}" class="erp-btn secondary">{{ __('erp::ui.profile') }}</a>
</nav><form action="{{ route('erp.locale') }}" method="POST">@csrf<select class="erp-lang" name="locale" onchange="this.form.submit()"><option value="en" @selected(app()->getLocale()==='en')>English</option><option value="fa" @selected(app()->getLocale()==='fa')>دری</option><option value="ps" @selected(app()->getLocale()==='ps')>پښتو</option></select></form></div>
@if(session('success'))<div class="erp-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="erp-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
@yield('scripts')
</x-admin.layout>
