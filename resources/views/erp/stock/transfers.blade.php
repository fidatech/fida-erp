@extends('erp.layout')
@section('content')
<h1 class="erp-title">Warehouse stock transfers</h1>
<div class="erp-card">
<h3>Transfer stock between warehouses</h3>
<p>Moves physical quantities between two warehouses without changing total company inventory or cost valuation.</p>
<form method="POST" action="{{ route('erp.stock.transfer.store') }}">@csrf
<input type="hidden" name="request_key" value="{{ old('request_key', (string)\Illuminate\Support\Str::uuid()) }}">
<div class="erp-form-grid">
<label class="erp-label">Source warehouse<select class="erp-field" name="from_warehouse_id" required><option value="">Select</option>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></label>
<label class="erp-label">Destination warehouse<select class="erp-field" name="to_warehouse_id" required><option value="">Select</option>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></label>
<label class="erp-label">Product / variant<select class="erp-field" name="product_variant_id" id="transfer-product" required><option value="">Search for product</option></select></label>
<label class="erp-label">Quantity<input type="number" class="erp-field" name="quantity" required min="0.000001" step="0.000001" value="1"></label>
</div>
<button class="erp-btn" type="submit">Complete transfer</button>
</form>
</div>
<div class="erp-card"><h3>Transfer history</h3><div class="erp-table-scroll"><table class="erp-table">
<thead><tr><th>Reference</th><th>Product</th><th>From</th><th>To</th><th>Quantity</th><th>Date</th></tr></thead><tbody>
@forelse($rows as $row)<tr><td>{{ $row->number }}</td><td>{{ $row->sku }}</td><td>{{ $row->source }}</td><td>{{ $row->destination }}</td><td>{{ $row->quantity }}</td><td>{{ $row->created_at }}</td></tr>
@empty<tr><td colspan="6">No transfers recorded.</td></tr>@endforelse
</tbody></table></div>{{ $rows->links() }}</div>
@endsection
@section('scripts')
<script>
(()=>{
const el=document.getElementById('transfer-product'),url=@js(route('erp.products.select2'));
if(window.jQuery?.fn?.select2)window.jQuery(el).select2({width:'100%',minimumInputLength:1,ajax:{url,delay:250,data:p=>({q:p.term||''}),processResults:d=>d}});
else{const search=document.createElement('input');search.className='erp-field';search.placeholder='Type product name or SKU';el.before(search);
let seq=0;search.oninput=async()=>{const current=++seq;const res=await fetch(url+'?q='+encodeURIComponent(search.value));const data=await res.json();if(current!==seq)return;el.replaceChildren(new Option('Select product',''));for(const o of data.results)el.add(new Option(o.text,o.id));};}
})();
</script>
@endsection
