@extends('erp.layout')
@section('content')
<div class="erp-between"><h1 class="erp-title">{{ __('erp::ui.sales') }}</h1><a href="{{ route('erp.sales.create') }}" class="erp-btn">+ {{ __('erp::ui.new_invoice') }}</a></div>
<div class="erp-card"><div class="erp-table-scroll"><table class="erp-table erp-responsive-list"><thead><tr><th>{{ __('erp::ui.number') }}</th><th>{{ __('erp::ui.customers') }}</th><th>{{ __('erp::ui.total') }}</th><th>{{ __('erp::ui.status') }}</th><th>{{ __('erp::ui.actions') }}</th></tr></thead><tbody>
@forelse($rows as $s)<tr><td data-label="{{ __('erp::ui.number') }}">{{ $s->number }}</td><td data-label="{{ __('erp::ui.customers') }}">{{ $s->customer }}</td><td data-label="{{ __('erp::ui.total') }}">{{ $s->total }} {{ $s->currency_code }}</td><td data-label="{{ __('erp::ui.status') }}">{{ __('erp::ui.'.$s->status) }}</td><td data-label="{{ __('erp::ui.actions') }}" class="erp-row-actions"><a class="erp-btn secondary" href="{{ route('erp.sales.pdf',$s->id) }}">PDF</a>
@if($s->status==='draft')<form method="POST" action="{{ route('erp.sales.post',$s->id) }}" onsubmit="return confirm(@js(__('erp::ui.accounting_warning')))" >@csrf<button class="erp-btn">{{ __('erp::ui.post') }}</button></form>@endif
</td></tr>@empty<tr><td colspan="5" class="erp-empty-state">{{ __('erp::ui.sales') }} — {{ __('erp::ui.search') }}: 0</td></tr>@endforelse</tbody></table></div><div class="erp-pagination">{{ $rows->links() }}</div></div>
@endsection
