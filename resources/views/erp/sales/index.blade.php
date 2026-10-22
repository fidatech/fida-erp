@extends('erp.layout')
@section('title','Sales Orders & Invoices')
@section('content')
<div class="fida-page-head"><div><h1>Sales Orders & Invoices</h1><p>Manage sales invoices, payment status and customer transactions.</p></div>
<a class="fida-action-primary" href="{{ route('erp.sales.create') }}"><svg class="fi"><use href="{{ asset('fida-erp/ui-icons.svg') }}#plus"></use></svg>Create Sales Invoice</a></div>
<div class="fida-kpi-grid" style="grid-template-columns:repeat(4,minmax(0,1fr))">
@foreach([
 ['label'=>'Total Invoices','value'=>number_format($stats['all']),'icon'=>'receipt','hint'=>'All statuses'],
 ['label'=>'Posted Invoice Value','value'=>number_format($stats['value'],2).' '.$stats['currency'],'icon'=>'wallet','hint'=>'Converted to base currency'],
 ['label'=>'Posted Invoices','value'=>number_format($stats['posted']),'icon'=>'check-circle','hint'=>'Confirmed invoices'],
 ['label'=>'Open Receivables','value'=>number_format($stats['outstanding'],2).' '.$stats['currency'],'icon'=>'calendar','hint'=>'Customer balances'],
] as $tile)
<div class="fida-kpi"><div class="fida-kpi-top"><span class="fida-kpi-icon"><svg class="fi"><use href="{{ asset('fida-erp/ui-icons.svg') }}#{{ $tile['icon'] }}"></use></svg></span><div><span class="fida-kpi-label">{{ $tile['label'] }}</span><strong class="fida-kpi-value">{{ $tile['value'] }}</strong></div></div><div class="fida-kpi-footer">{{ $tile['hint'] }}</div></div>
@endforeach
</div>
<section class="fida-panel" style="margin-bottom:12px">
<form method="GET" action="{{ route('erp.sales.index') }}" class="fida-filter-panel">
<input class="erp-field" type="search" name="q" placeholder="Search by Invoice No. or Customer" value="{{ $filters['q'] }}">
<select class="erp-field" name="status"><option value="">All Invoice Statuses</option><option value="posted" @selected($filters['status']==='posted')>Posted</option><option value="draft" @selected($filters['status']==='draft')>Draft</option></select>
<label class="sr-only" for="sales-from">From date</label><input class="erp-field" type="date" name="from" id="sales-from" value="{{ $filters['from'] }}">
<label class="sr-only" for="sales-to">To date</label><input class="erp-field" type="date" name="to" id="sales-to" value="{{ $filters['to'] }}">
<button type="submit" class="erp-btn">Search</button>
</form></section>
<section class="fida-panel"><div class="fida-panel-heading"><h2>Sales Invoices ({{ number_format($stats['all']) }} total)</h2><span class="fida-chart-note">Amounts use each invoice's transaction currency</span></div>
<div class="fida-table-wrap"><table class="fida-table">
<thead><tr><th>Invoice No.</th><th>Date</th><th>Customer</th><th>Amount</th><th>Paid</th><th>Payment Status</th><th>Invoice Status</th><th>Actions</th></tr></thead><tbody>
@forelse($rows as $s)
<tr><td><a href="{{ route('erp.sales.pdf',$s->id) }}">{{ $s->number }}</a></td><td>{{ \Illuminate\Support\Carbon::parse($s->created_at)->format('d M Y') }}</td><td>{{ $s->customer }}</td><td>{{ number_format((float)$s->total,2) }} {{ $s->currency_code }}</td><td>{{ number_format((float)$s->paid,2) }}</td>
<td>@if($s->status==='draft')<span class="fida-pill orange">Not Posted</span>@elseif(\App\Services\ERP\Decimal::cmp((string)$s->paid,(string)$s->total)>=0)<span class="fida-pill green">Paid</span>@elseif(\App\Services\ERP\Decimal::cmp((string)$s->paid,'0')>0)<span class="fida-pill orange">Partial</span>@else<span class="fida-pill red">Unpaid</span>@endif</td>
<td><span class="fida-pill {{ $s->status }}">{{ ucfirst($s->status) }}</span></td>
<td><div class="fida-row" style="flex-wrap:wrap">
<a class="erp-btn secondary" href="{{ route('erp.sales.pdf',$s->id) }}">PDF</a>
@if($s->status==='draft')<form method="POST" action="{{ route('erp.sales.post',$s->id) }}" onsubmit="return confirm('Post invoice and reserve stock ledger entries?')">@csrf<button class="erp-btn" type="submit">Post</button></form>@endif
@if($s->status==='posted' && \App\Services\ERP\Decimal::cmp((string)$s->total,(string)$s->paid)>0)
<button class="erp-btn secondary" type="button" data-open-modal="payment-{{ $s->id }}">Receive</button>
<dialog class="fida-modal" id="payment-{{ $s->id }}"><form action="{{ route('erp.sales.payments',$s->id) }}" method="POST">@csrf
<input type="hidden" name="request_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
<div class="fida-modal-header"><h2>Receive Payment</h2><button type="button" class="fida-icon-button" data-close-modal aria-label="Close">×</button></div>
<div class="fida-modal-body"><p>Invoice {{ $s->number }} · {{ $s->customer }}</p>
<label class="erp-label">Amount to receive ({{ $s->currency_code }}) *</label><input class="erp-field" type="number" name="amount" min="0.000001" step="0.000001" max="{{ \App\Services\ERP\Decimal::sub((string)$s->total,(string)$s->paid) }}" value="{{ \App\Services\ERP\Decimal::sub((string)$s->total,(string)$s->paid) }}" required>
<p class="fida-chart-note">Records a cash receipt using the invoice's historical exchange rate.</p></div>
<div class="fida-modal-footer"><button type="button" class="erp-btn secondary" data-close-modal>Cancel</button><button class="erp-btn" type="submit">Record Payment</button></div></form></dialog>
@endif
</div></td></tr>
@empty<tr><td colspan="8" class="fida-empty">No matching sales invoices found.</td></tr>@endforelse
</tbody></table></div>
<div style="padding:10px 15px">{{ $rows->links() }}</div>
</section>
@endsection
