@extends('erp.layout')
@section('content')
<div class="erp-between"><h1 class="erp-title">{{ __('erp::ui.sales') }}</h1><a href="{{ route('erp.sales.create') }}" class="erp-btn">+ {{ __('erp::ui.new_invoice') }}</a></div>
<div class="erp-card"><div class="erp-table-scroll"><table class="erp-table"><thead><tr><th>{{ __('erp::ui.number') }}</th><th>{{ __('erp::ui.customers') }}</th><th>{{ __('erp::ui.total') }}</th><th>{{ __('erp::ui.status') }}</th><th>{{ __('erp::ui.actions') }}</th></tr></thead><tbody>
@foreach($rows as $s)<tr><td>{{ $s->number }}</td><td>{{ $s->customer }}</td><td>{{ $s->total }} {{ $s->currency_code }}</td><td>{{ __('erp::ui.'.$s->status) }}</td><td><a class="erp-btn secondary" href="{{ route('erp.sales.pdf',$s->id) }}">PDF</a>
@if($s->status==='draft')<form method="POST" action="{{ route('erp.sales.post',$s->id) }}" onsubmit="return confirm(@js(__('erp::ui.accounting_warning')))" >@csrf<button class="erp-btn">{{ __('erp::ui.post') }}</button></form>@endif
@if($s->status==='posted' && \App\Services\ERP\Decimal::cmp((string)$s->total,(string)$s->paid)>0)
<form method="POST" action="{{ route('erp.sales.payments',$s->id) }}" style="display:inline-flex;align-items:center;gap:6px;flex-wrap:wrap">
@csrf<input type="hidden" name="request_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
<label>Receive <input type="number" class="erp-field" name="amount" step="0.000001" min="0.000001" max="{{ \App\Services\ERP\Decimal::sub((string)$s->total,(string)$s->paid) }}" value="{{ \App\Services\ERP\Decimal::sub((string)$s->total,(string)$s->paid) }}" required style="max-width:130px"></label>
<button class="erp-btn" type="submit">Record payment</button></form>
@endif</td></tr>@endforeach</tbody></table></div><div class="erp-pagination">{{ $rows->links() }}</div></div>
@endsection
