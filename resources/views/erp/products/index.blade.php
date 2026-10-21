@extends('erp.layout')
@section('content')
<h1 class="erp-title">{{ __('erp::ui.products') }}</h1>
<div class="erp-card"><h3>{{ __('erp::ui.add_product') }}</h3><div class="erp-note">{{ __('erp::ui.stock_warning') }}</div><form action="{{ route('erp.products.store') }}" method="POST">@csrf<div class="erp-form-grid">
@foreach(['name','sku','barcode','sale_price'] as $f)<div><label class="erp-label">{{ __('erp::ui.'.$f) }}</label><input class="erp-field" name="{{ $f }}" value="{{ old($f) }}" @required(in_array($f,['name','sale_price']))></div>@endforeach
</div><p><button class="erp-btn">{{ __('erp::ui.save') }}</button></p></form></div>
<div class="erp-card"><form class="erp-flex" method="GET"><input class="erp-field" style="max-width:280px" name="q" value="{{ $q }}" placeholder="{{ __('erp::ui.search') }}"><button class="erp-btn">{{ __('erp::ui.search') }}</button></form>
<div class="erp-table-scroll"><table class="erp-table"><thead><tr><th>ID</th><th>{{ __('erp::ui.name') }}</th><th>{{ __('erp::ui.sku') }}</th></tr></thead><tbody>
@foreach($products as $p)<tr><td>{{ $p->id }}</td><td>{{ $p->name }}</td><td>{{ $p->sku }}</td></tr>@endforeach</tbody></table></div><div class="erp-pagination">{{ $products->links() }}</div></div>
@endsection
