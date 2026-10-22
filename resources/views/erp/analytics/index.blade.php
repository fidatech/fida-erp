@extends('erp.layout')
@section('title','Charts & Analytics')
@section('content')
@php($maxChart=max(1,...array_map(fn($m)=>max($m['revenue'],$m['expenses']),$metrics['monthly'])))
<div class="fida-page-head"><div><h1>Charts & Analytics</h1><p>Monitor recorded revenue, expenses, stock and product performance.</p></div><div class="fida-page-controls"><span class="fida-filter">{{ $metrics['period'] }}</span><a class="fida-action-primary" href="{{ route('erp.finance.index') }}">Financial Reports →</a></div></div>
@include('erp.dashboard._kpis')
<div class="fida-board">
<section class="fida-panel fida-span-2">
<div class="fida-panel-heading"><h2>Revenue vs Expenses Trend</h2><a href="{{ route('erp.finance.index') }}">View Details →</a></div>
<div class="fida-panel-body"><p class="fida-chart-note">Base currency ({{ $metrics['currency'] }}), historical posted journals</p><div class="fida-chart-bars" style="height:250px">
@foreach($metrics['monthly'] as $m)<div class="fida-chart-group"><span style="--bar-height:{{ max(1,round(88*max(0,$m['revenue'])/$maxChart)) }}%"></span><span class="expense" style="--bar-height:{{ max(1,round(88*max(0,$m['expenses'])/$maxChart)) }}%"></span><em>{{ $m['label'] }}</em></div>@endforeach
</div><div class="fida-row-between" style="margin-top:10px;font-size:11px"><span>■ <span style="color:#1677ed">Revenue</span></span><span>■ <span class="fida-good">Expenses</span></span></div></div>
</section>
<section class="fida-panel"><div class="fida-panel-heading"><h2>Sales by Category</h2></div><div class="fida-panel-body">
@php($categorySum=max(1,(float)$metrics['categories']->sum('amount')))
@forelse($metrics['categories'] as $c)<div class="fida-insight-row"><span style="min-width:120px">{{ $c->name }}</span><div style="flex:1;background:#e9f0f8;border-radius:10px;height:8px"><div style="height:100%;background:#1280f8;border-radius:10px;width:{{ max(0,min(100,100*(float)$c->amount/$categorySum)) }}%"></div></div><b>{{ number_format((float)$c->amount,0) }}</b></div>@empty<div class="fida-empty">No category data available.</div>@endforelse
</div></section>
<section class="fida-panel fida-span-2"><div class="fida-panel-heading"><h2>Top Performing Products</h2><a href="{{ route('erp.products.index') }}">View All →</a></div><div class="fida-table-wrap"><table class="fida-table"><thead><tr><th>#</th><th>Product</th><th>SKU</th><th>Units Sold</th><th>Revenue ({{ $metrics['currency'] }})</th></tr></thead><tbody>
@forelse($metrics['topProducts'] as $i=>$p)<tr><td>{{ $i+1 }}</td><td>{{ $p->name }}</td><td>{{ $p->sku }}</td><td>{{ number_format((float)$p->units,2) }}</td><td>{{ number_format((float)$p->revenue,2) }}</td></tr>@empty<tr><td colspan="5" class="fida-empty">No sales yet.</td></tr>@endforelse
</tbody></table></div></section>
<section class="fida-panel"><div class="fida-panel-heading"><h2>Inventory & Finance Insights</h2></div><div class="fida-panel-body">
<div class="fida-insight-row">Inventory valuation <b>{{ number_format($metrics['inventory'],2) }} {{ $metrics['currency'] }}</b></div>
<div class="fida-insight-row">Accounts receivable <b>{{ number_format($metrics['outstanding'],2) }} {{ $metrics['currency'] }}</b></div>
<div class="fida-insight-row">Low-stock records <b>{{ $metrics['lowStock'] }}</b></div>
<div class="fida-insight-row">Products in catalog <b>{{ $metrics['products'] }}</b></div>
</div></section>
</div>
@endsection
