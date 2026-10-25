@extends('erp.layout')
@section('content')
<div class="erp-between"><div><h1 class="erp-title">{{ __('erp::ui.dashboard') }}</h1><p class="erp-muted">{{ __('erp::ui.dashboard_intro') }}</p></div><a class="erp-btn" href="{{ route('erp.sales.create') }}">+ {{ __('erp::ui.new_invoice') }}</a></div>
<div class="erp-grid">
@foreach(['customers'=>'customers','products'=>'products','drafts'=>'draft','posted'=>'posted'] as $key=>$label)
<div class="erp-card"><div class="erp-muted">{{ __('erp::ui.'.$label) }}</div><div class="erp-stat">{{ number_format($counts[$key]) }}</div></div>
@endforeach</div>
<div class="erp-card"><h3>{{ __('erp::ui.recent_invoices') }}</h3><div class="erp-table-scroll"><table class="erp-table erp-responsive-list"><thead><tr><th>{{ __('erp::ui.number') }}</th><th>{{ __('erp::ui.customers') }}</th><th>{{ __('erp::ui.total') }}</th><th>{{ __('erp::ui.status') }}</th></tr></thead><tbody>
@forelse($recent as $sale)<tr><td data-label="{{ __('erp::ui.number') }}">{{ $sale->number }}</td><td data-label="{{ __('erp::ui.customers') }}">{{ $sale->customer }}</td><td data-label="{{ __('erp::ui.total') }}">{{ $sale->total }} {{ $sale->currency_code }}</td><td data-label="{{ __('erp::ui.status') }}"><span class="erp-badge">{{ __('erp::ui.'.$sale->status) }}</span></td></tr>@empty<tr><td colspan="4" class="erp-empty-state">—</td></tr>@endforelse
</tbody></table></div></div>
@endsection
