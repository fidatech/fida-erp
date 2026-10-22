@extends('erp.layout')
@section('title','Dashboard')
@section('content')
@php
$posted=(int)$metrics['postedCount'];$draft=(int)$metrics['draftCount'];$all=max(1,$posted+$draft);
$postedPct=round($posted*100/$all);$draftPct=round($draft*100/$all);
$categoryTotal=max(0.000001,(float)$metrics['categories']->sum('amount'));
$colors=['#1379f8','#04bda4','#ffbc50','#ff713c','#a858f7'];
$gradient=[];$start=0;
foreach($metrics['categories'] as $i=>$category){$end=$start+max(0,100*(float)$category->amount/$categoryTotal);$gradient[]=$colors[$i%5].' '.round($start,3).'% '.round($end,3).'%';$start=$end;}
if($start<100)$gradient[]='#edf2f9 '.round($start,3).'% 100%';
$maxBar=max(1,...array_map(fn($m)=>max($m['revenue'],$m['expenses']),$metrics['monthly']));
@endphp
<div class="fida-page-head">
<div><h1>Good {{ now()->hour<12?'morning':(now()->hour<17?'afternoon':'evening') }}, {{ strtok(auth('erp_tenant')->user()->name,' ') }}</h1><p>Here's what's happening across your enterprise today.</p></div>
<div class="fida-page-controls"><span class="fida-filter"><svg class="fi"><use href="{{ asset('fida-erp/ui-icons.svg') }}#building-2"></use></svg>{{ app('erp.current_tenant')->business_name }}</span><span class="fida-filter"><svg class="fi"><use href="{{ asset('fida-erp/ui-icons.svg') }}#calendar"></use></svg>{{ now()->format('D, d M Y') }}</span><button class="fida-action-primary" type="button" id="fida-dashboard-actions"><svg class="fi"><use href="{{ asset('fida-erp/ui-icons.svg') }}#plus"></use></svg> Quick Actions</button></div>
</div>
@include('erp.dashboard._kpis')
<div class="fida-board">
<section class="fida-panel">
<div class="fida-panel-heading"><h2>Sales by Product Category</h2><a href="{{ route('erp.analytics.index') }}">View Details →</a></div>
<div class="fida-panel-body fida-chart-donut">
 <div class="fida-donut" style="background:conic-gradient({{ implode(',',$gradient) }})"><div class="fida-donut-hole"><strong>{{ number_format((float)$metrics['categories']->sum('amount'),0) }}</strong><small>{{ $metrics['currency'] }} · Total sales</small></div></div>
 <div class="fida-legend">@forelse($metrics['categories'] as $i=>$category)<div class="fida-legend-item"><span class="fida-color-dot" style="background:{{ $colors[$i%5] }}"></span>{{ $category->name }}<strong>{{ round(100*(float)$category->amount/$categoryTotal) }}%</strong></div>@empty<p class="fida-chart-note">No posted product sales yet.</p>@endforelse</div>
</div>
</section>
<section class="fida-panel">
<div class="fida-panel-heading"><h2>Revenue & Expense Trend</h2><a href="{{ route('erp.analytics.index') }}">View Details →</a></div>
<div class="fida-panel-body"><div class="fida-row" style="justify-content:flex-end;font-size:11px;gap:15px"><span><span class="fida-color-dot" style="display:inline-block;background:#1482f8"></span> Revenue</span><span><span class="fida-color-dot" style="display:inline-block;background:#14bca2"></span> Expenses</span></div>
<div class="fida-chart-bars">@foreach($metrics['monthly'] as $month)<div class="fida-chart-group" title="{{ $month['label'] }}: Revenue {{ number_format($month['revenue'],2) }}, Expenses {{ number_format($month['expenses'],2) }}"><span style="--bar-height:{{ max(1,round(max(0,$month['revenue'])/$maxBar*88)) }}%"></span><span class="expense" style="--bar-height:{{ max(1,round(max(0,$month['expenses'])/$maxBar*88)) }}%"></span><em>{{ $month['label'] }}</em></div>@endforeach</div>
<p class="fida-chart-note">{{ $metrics['currency'] }} · Posted journal totals (six months)</p></div>
</section>
<section class="fida-panel">
<div class="fida-panel-heading"><h2>Calendar & Tasks</h2><a href="{{ route('erp.profile') }}">View Profile →</a></div>
<div class="fida-panel-body"><div class="fida-calendar-month">{{ now()->format('F Y') }}</div><div class="fida-calendar-grid">
@foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d)<span class="weekday">{{ $d }}</span>@endforeach
@for($n=0;$n<now()->copy()->startOfMonth()->dayOfWeek;$n++)<span></span>@endfor
@for($d=1;$d<=now()->daysInMonth;$d++)<span class="{{ $d===now()->day?'current':'' }}">{{ $d }}</span>@endfor
</div><div class="fida-mini-task"><strong>Tasks and approvals</strong><small>Module scheduled for implementation. No sample events are shown.</small></div></div>
</section>
<section class="fida-panel">
<div class="fida-panel-heading"><h2>Order Fulfillment Status</h2><a href="{{ route('erp.sales.index') }}">View Details →</a></div>
<div class="fida-panel-body fida-chart-donut"><div class="fida-donut" style="background:conic-gradient(#08bb9c 0 {{ $postedPct }}%,#3990f8 {{ $postedPct }}% 100%)"><div class="fida-donut-hole"><strong>{{ number_format($posted+$draft) }}</strong><small>Total invoices</small></div></div>
<div class="fida-legend"><div class="fida-legend-item"><span class="fida-color-dot" style="background:#0abb9b"></span>Posted<strong>{{ $posted }} ({{ $postedPct }}%)</strong></div><div class="fida-legend-item"><span class="fida-color-dot" style="background:#3990f8"></span>Draft<strong>{{ $draft }} ({{ $draftPct }}%)</strong></div><p class="fida-chart-note">Invoice posting status only. Delivery fulfillment tracking is not available.</p></div>
</div>
</section>
<section class="fida-panel">
<div class="fida-panel-heading"><h2>Alerts & Notifications</h2><a href="{{ route('erp.stock.index') }}">Inventory →</a></div>
<div class="fida-panel-body">
<div class="fida-insight-row"><svg class="fi fida-traffic"><use href="{{ asset('fida-erp/ui-icons.svg') }}#alert-triangle"></use></svg>Low-stock balances <b class="fida-traffic">{{ $metrics['lowStock'] }} records</b></div>
<div class="fida-insight-row"><svg class="fi"><use href="{{ asset('fida-erp/ui-icons.svg') }}#receipt"></use></svg>Unposted sales invoices <b>{{ $draft }} drafts</b></div>
<div class="fida-insight-row"><svg class="fi"><use href="{{ asset('fida-erp/ui-icons.svg') }}#wallet"></use></svg>Outstanding receivables <b>{{ number_format($metrics['outstanding'],0) }} {{ $metrics['currency'] }}</b></div>
<div class="fida-insight-row"><svg class="fi"><use href="{{ asset('fida-erp/ui-icons.svg') }}#truck"></use></svg>Recorded goods receipts <b>{{ $metrics['purchaseReceipts'] }}</b></div>
</div>
</section>
<section class="fida-panel">
<div class="fida-panel-heading"><h2>Workspace Overview</h2><a href="{{ route('erp.settings.index') }}">Settings →</a></div>
<div class="fida-panel-body">
<div class="fida-insight-row">Customers <b>{{ number_format($metrics['customers']) }}</b></div>
<div class="fida-insight-row">Suppliers <b>{{ number_format($metrics['suppliers']) }}</b></div>
<div class="fida-insight-row">Products <b>{{ number_format($metrics['products']) }}</b></div>
<div class="fida-insight-row">Active employees <b>{{ number_format($metrics['employees']) }}</b></div>
</div>
</section>
<section class="fida-panel fida-full">
<div class="fida-panel-heading"><h2>Recent Transactions</h2><a href="{{ route('erp.sales.index') }}">View All →</a></div>
<div class="fida-table-wrap"><table class="fida-table"><thead><tr><th>Ref No.</th><th>Date</th><th>Type</th><th>Party / Customer</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@forelse($metrics['recent'] as $sale)
<tr><td><a href="{{ route('erp.sales.index') }}">{{ $sale->number }}</a></td><td>{{ \Illuminate\Support\Carbon::parse($sale->created_at)->format('d M Y') }}</td><td>Sales Invoice</td><td>{{ $sale->customer }}</td><td>{{ number_format((float)$sale->total,2) }} {{ $sale->currency_code }}</td><td><span class="fida-pill {{ $sale->status }}">{{ ucfirst($sale->status) }}</span></td><td><a href="{{ route('erp.sales.pdf',$sale->id) }}">PDF</a></td></tr>
@empty<tr><td colspan="7" class="fida-empty">No transactions. Use Quick Actions to create your first sale.</td></tr>@endforelse
</tbody></table></div>
</section>
</div>
@endsection
@section('scripts')
<script>document.getElementById('fida-dashboard-actions')?.addEventListener('click',()=>document.getElementById('fida-actions-toggle')?.click())</script>
@endsection
