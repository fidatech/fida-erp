@extends('erp.layout')
@section('title','KPI Overview')
@section('content')
<div class="fida-page-head"><div><h1>KPI Overview</h1><p>Business indicators calculated from your actual transactions.</p></div><div class="fida-page-controls"><span class="fida-filter">{{ $metrics['period'] }}</span><a href="{{ route('erp.analytics.index') }}" class="fida-action-primary">View Analytics →</a></div></div>
@include('erp.dashboard._kpis')
<div class="fida-board">
<section class="fida-panel fida-span-2"><div class="fida-panel-heading"><h2>Business Performance — 6 Months</h2><a href="{{ route('erp.finance.index') }}">Financial Statements →</a></div><div class="fida-panel-body">
<div class="fida-table-wrap"><table class="fida-table"><thead><tr><th>Month</th><th>Revenue ({{ $metrics['currency'] }})</th><th>Expenses</th><th>Profit</th><th>Margin</th></tr></thead><tbody>
@foreach($metrics['monthly'] as $m)<tr><td>{{ $m['month'] }}</td><td>{{ number_format($m['revenue'],2) }}</td><td>{{ number_format($m['expenses'],2) }}</td><td>{{ number_format($m['profit'],2) }}</td><td>{{ $m['revenue']>0?number_format(100*$m['profit']/$m['revenue'],1).'%' : '—' }}</td></tr>@endforeach
</tbody></table></div></div></section>
<section class="fida-panel"><div class="fida-panel-heading"><h2>Operational Scorecard</h2></div><div class="fida-panel-body">
<div class="fida-insight-row">Customers <b>{{ number_format($metrics['customers']) }}</b></div><div class="fida-insight-row">Active employees <b>{{ number_format($metrics['employees']) }}</b></div><div class="fida-insight-row">Sales invoices posted <b>{{ $metrics['postedCount'] }}</b></div><div class="fida-insight-row">Pending drafts <b>{{ $metrics['draftCount'] }}</b></div><div class="fida-insight-row">Recorded goods receipts <b>{{ $metrics['purchaseReceipts'] }}</b></div>
</div></section>
<section class="fida-panel fida-full"><div class="fida-panel-heading"><h2>Best Selling Products</h2><a href="{{ route('erp.products.index') }}">Product Catalog →</a></div><div class="fida-table-wrap"><table class="fida-table"><thead><tr><th>Product</th><th>SKU</th><th>Units</th><th>Revenue ({{ $metrics['currency'] }})</th></tr></thead><tbody>
@forelse($metrics['topProducts'] as $p)<tr><td>{{ $p->name }}</td><td>{{ $p->sku }}</td><td>{{ number_format((float)$p->units,2) }}</td><td>{{ number_format((float)$p->revenue,2) }}</td></tr>@empty<tr><td colspan="4" class="fida-empty">No posted product sales yet.</td></tr>@endforelse
</tbody></table></div></section>
</div>
@endsection
