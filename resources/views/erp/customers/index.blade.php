@extends('erp.layout')
@section('content')
<h1 class="erp-title">{{ __('erp::ui.customers') }}</h1>
<div class="erp-card"><h3>{{ __('erp::ui.add_customer') }}</h3><form action="{{ route('erp.customers.store') }}" method="POST">@csrf<div class="erp-form-grid">
@foreach(['name','phone','email','address'] as $f)<div><label class="erp-label">{{ __('erp::ui.'.$f) }}</label><input class="erp-field" name="{{ $f }}" value="{{ old($f) }}" @required($f==='name')></div>@endforeach
</div><p><button class="erp-btn">{{ __('erp::ui.save') }}</button></p></form></div>
<div class="erp-card"><form class="erp-flex" method="GET"><input class="erp-field" style="max-width:280px" name="q" value="{{ $q }}" placeholder="{{ __('erp::ui.search') }}"><button class="erp-btn">{{ __('erp::ui.search') }}</button></form>
<div class="erp-table-scroll"><table class="erp-table erp-responsive-list"><thead><tr><th>ID</th><th>{{ __('erp::ui.name') }}</th><th>{{ __('erp::ui.phone') }}</th><th>{{ __('erp::ui.email') }}</th></tr></thead><tbody>
@forelse($customers as $c)<tr><td data-label="ID">{{ $c->code }}</td><td data-label="{{ __('erp::ui.name') }}">{{ $c->name }}</td><td data-label="{{ __('erp::ui.phone') }}">{{ $c->phone }}</td><td data-label="{{ __('erp::ui.email') }}">{{ $c->email }}</td></tr>@empty<tr><td colspan="4" class="erp-empty-state">—</td></tr>@endforelse
</tbody></table></div><div class="erp-pagination">{{ $customers->links() }}</div></div>
@endsection
