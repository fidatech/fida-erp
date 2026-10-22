@extends('erp.layout')
@section('content')
<div class="nexa-page-head">
 <div><h1>{{ __('erp::ui.dashboard') }}</h1><p>Here's what's happening across your business today.</p></div>
 <div class="nexa-page-tools"><span class="nexa-tool">▤ {{ app('erp.current_tenant')->business_name }}</span><span class="nexa-tool">▦ {{ now()->format('D, d M Y') }}</span><a href="{{ route('erp.sales.create') }}" class="nexa-tool primary">＋ {{ __('erp::ui.new_invoice') }}</a></div>
</div>
<div class="nexa-kpis">
 <a class="nexa-kpi" href="{{ route('erp.customers.index') }}"><span class="nexa-kpi-icon teal">♧</span><span class="nexa-kpi-body"><span class="nexa-kpi-label">{{ __('erp::ui.customers') }}</span><span class="nexa-kpi-value">{{ number_format($counts['customers'] ?? 0) }}</span><span class="nexa-kpi-sub">Customer records</span></span></a>
 <a class="nexa-kpi" href="{{ route('erp.products.index') }}"><span class="nexa-kpi-icon">⬡</span><span class="nexa-kpi-body"><span class="nexa-kpi-label">{{ __('erp::ui.products') }}</span><span class="nexa-kpi-value">{{ number_format($counts['products'] ?? 0) }}</span><span class="nexa-kpi-sub">Catalog items</span></span></a>
 <a class="nexa-kpi" href="{{ route('erp.sales.index') }}"><span class="nexa-kpi-icon">▤</span><span class="nexa-kpi-body"><span class="nexa-kpi-label">Posted invoices</span><span class="nexa-kpi-value">{{ number_format($counts['posted'] ?? 0) }}</span><span class="nexa-kpi-sub">Confirmed sales</span></span></a>
 <a class="nexa-kpi" href="{{ route('erp.sales.index') }}"><span class="nexa-kpi-icon">▧</span><span class="nexa-kpi-body"><span class="nexa-kpi-label">Draft invoices</span><span class="nexa-kpi-value">{{ number_format($counts['drafts'] ?? 0) }}</span><span class="nexa-kpi-sub">Awaiting posting</span></span></a>
</div>
<div class="nexa-dashboard-grid">
 <section class="nexa-panel">
  <div class="nexa-panel-head"><h2>Sales Status</h2><a href="{{ route('erp.sales.index') }}">View Details →</a></div>
  <div class="nexa-panel-body"><div class="nexa-donut-layout">
   @php($posted = (int)($counts['posted'] ?? 0))
   @php($drafts = (int)($counts['drafts'] ?? 0))
   @php($allSales = $posted + $drafts)
   @php($postedPercent = $allSales ? round($posted * 100 / $allSales) : 0)
   <div class="nexa-donut" style="background:conic-gradient(#12ad95 0 {{ $postedPercent }}%, #3985f7 {{ $postedPercent }}% 100%)"><div class="nexa-donut-inner"><strong>{{ number_format($allSales) }}</strong><small>Total invoices</small></div></div>
   <div class="nexa-legend"><div class="nexa-legend-line"><span class="nexa-dot" style="background:#12ad95"></span>Posted<strong>{{ number_format($posted) }}</strong></div><div class="nexa-legend-line"><span class="nexa-dot" style="background:#3985f7"></span>Draft<strong>{{ number_format($drafts) }}</strong></div></div>
  </div></div>
 </section>
 <section class="nexa-panel">
  <div class="nexa-panel-head"><h2>Business Workspace</h2><a href="{{ route('erp.stock.index') }}">View Stock →</a></div>
  <div class="nexa-panel-body">
   <a href="{{ route('erp.stock.index') }}" class="nexa-alert-row"><span class="nexa-alert-symbol">⬡</span>Stock & receiving<strong>Open →</strong></a>
   <a href="{{ route('erp.products.index') }}" class="nexa-alert-row"><span class="nexa-alert-symbol">▦</span>Product catalog<strong>Open →</strong></a>
   <a href="{{ route('erp.customers.index') }}" class="nexa-alert-row"><span class="nexa-alert-symbol">♧</span>Customer directory<strong>Open →</strong></a>
   <a href="{{ route('erp.sales.create') }}" class="nexa-alert-row"><span class="nexa-alert-symbol">▤</span>Create sales invoice<strong>Open →</strong></a>
  </div>
 </section>
 <section class="nexa-panel nexa-calendar-panel">
  <div class="nexa-panel-head"><h2>Calendar & Tasks</h2><span class="erp-muted">Calendar preview</span></div>
  <div class="nexa-panel-body">
   <div class="nexa-calendar-month">{{ now()->format('F Y') }}</div>
   <div class="nexa-weekdays"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>
   <div class="nexa-calendar-dates">@for($i=0;$i<now()->startOfMonth()->dayOfWeek;$i++)<span></span>@endfor @for($day=1;$day<=now()->daysInMonth;$day++)<span class="{{ $day===now()->day ? 'today' : '' }}">{{ $day }}</span>@endfor</div>
   <div class="nexa-task"><strong>Task management</strong><small>Scheduling and approval workflows are planned for a later module release.</small></div>
  </div>
 </section>
 <section class="nexa-panel">
  <div class="nexa-panel-head"><h2>Sales Summary</h2><a href="{{ route('erp.sales.index') }}">View Details →</a></div>
  <div class="nexa-panel-body"><div class="nexa-kpi-value">{{ number_format($allSales) }}</div><p class="erp-muted">Total recorded invoices</p><div style="height:9px;border-radius:12px;background:#e7edf6;overflow:hidden"><div style="height:100%;width:{{ $postedPercent }}%;background:#1677ef"></div></div><div class="nexa-legend-line"><span class="nexa-dot" style="background:#1677ef"></span>Posted invoices<strong>{{ number_format($posted) }}</strong></div><div class="nexa-legend-line"><span class="nexa-dot" style="background:#e7edf6"></span>Draft invoices<strong>{{ number_format($drafts) }}</strong></div></div>
 </section>
 <section class="nexa-panel">
  <div class="nexa-panel-head"><h2>Module Overview</h2><span class="erp-muted">Current implementation</span></div>
  <div class="nexa-panel-body">
   <a href="{{ route('erp.sales.index') }}" class="nexa-alert-row"><span class="nexa-alert-symbol">▤</span>Sales and invoicing<strong>Available</strong></a>
   <a href="{{ route('erp.stock.index') }}" class="nexa-alert-row"><span class="nexa-alert-symbol">⬡</span>Stock receiving<strong>Available</strong></a>
   <div class="nexa-alert-row"><span class="nexa-alert-symbol">◷</span>Approval queue<strong>Planned</strong></div>
   <div class="nexa-alert-row"><span class="nexa-alert-symbol">♧</span>Payroll & HR<strong>Planned</strong></div>
  </div>
 </section>
 <section class="nexa-panel nexa-calendar-panel">
  <div class="nexa-panel-head"><h2>Quick Actions</h2><span class="erp-muted">Shortcuts</span></div>
  <div class="nexa-panel-body" style="display:grid;gap:9px">
   <a href="{{ route('erp.sales.create') }}" class="erp-btn">＋ New Invoice</a>
   <a href="{{ route('erp.customers.index') }}" class="erp-btn secondary">Customer Management</a>
   <a href="{{ route('erp.products.index') }}" class="erp-btn secondary">Product Management</a>
   <a href="{{ route('erp.stock.index') }}" class="erp-btn secondary">Inventory Management</a>
  </div>
 </section>
 <section class="nexa-panel nexa-panel-wide">
  <div class="nexa-panel-head"><h2>{{ __('erp::ui.recent_invoices') }}</h2><a href="{{ route('erp.sales.index') }}">View All →</a></div>
  <div class="nexa-data-tools"><input id="nexa-invoice-filter" type="search" placeholder="Search by invoice number, customer..."><select id="nexa-invoice-status"><option value="">All statuses</option><option value="posted">Posted</option><option value="draft">Draft</option></select><a href="{{ route('erp.sales.index') }}" class="nexa-tool" style="min-width:auto">All invoices →</a></div>
  <div class="nexa-table-scroll"><table class="nexa-table"><thead><tr><th>Invoice No.</th><th>Customer</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead><tbody id="nexa-invoice-rows">
   @forelse($recent as $sale)<tr data-status="{{ strtolower($sale->status) }}"><td><a href="{{ route('erp.sales.index') }}">{{ $sale->number }}</a></td><td>{{ $sale->customer }}</td><td>{{ $sale->total }} {{ $sale->currency_code }}</td><td><span class="nexa-status {{ $sale->status }}">{{ ucfirst($sale->status) }}</span></td><td><a href="{{ route('erp.sales.index') }}">View →</a></td></tr>@empty<tr><td colspan="5">No invoices yet. Create your first invoice to get started.</td></tr>@endforelse
  </tbody></table></div>
 </section>
</div>
@endsection
@section('scripts')
<script>
(function(){const q=document.getElementById('nexa-invoice-filter'),s=document.getElementById('nexa-invoice-status');function filter(){document.querySelectorAll('#nexa-invoice-rows tr').forEach(r=>{r.hidden=!(r.textContent.toLowerCase().includes(q.value.toLowerCase())&&(!s.value||r.dataset.status===s.value));});}q?.addEventListener('input',filter);s?.addEventListener('change',filter);})();
</script>
@endsection
