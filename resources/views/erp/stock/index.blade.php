@extends('erp.layout')
@section('content')
<h1 class="erp-title">{{ __('erp::ui.stock') }}</h1>
<div class="erp-card"><h3>{{ __('erp::ui.add_supplier') }}</h3><form method="POST" action="{{ route('erp.stock.suppliers') }}" class="erp-flex">@csrf<input class="erp-field" style="max-width:250px" name="name" required placeholder="{{ __('erp::ui.name') }}"><input class="erp-field" style="max-width:250px" name="phone" placeholder="{{ __('erp::ui.phone') }}"><button class="erp-btn">{{ __('erp::ui.save') }}</button></form></div>
<div class="erp-card"><h3>{{ __('erp::ui.receive_goods') }}</h3><form action="{{ route('erp.stock.receive') }}" method="POST">@csrf<input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
<div class="erp-form-grid"><div><label class="erp-label">{{ __('erp::ui.supplier') }}</label><select required name="supplier_id" class="erp-field"><option value=""></option>@foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
<div><label class="erp-label">{{ __('erp::ui.warehouse') }}</label><select required name="warehouse_id" class="erp-field">@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div>
<div><label class="erp-label">{{ __('erp::ui.currency') }}</label><select name="currency_code" class="erp-field">@foreach($currencies as $c)<option value="{{ $c->code }}" @selected($c->code===$base)>{{ $c->code }}</option>@endforeach</select></div>
<div><label class="erp-label">{{ __('erp::ui.base_rate') }} ({{ $base }})</label><input class="erp-field" name="fx_rate" value="1" type="number" step="any" min="0.000000000001" required></div></div>
<div class="erp-table-scroll" style="margin-top:20px"><table class="erp-table"><thead><tr><th>{{ __('erp::ui.products') }}</th><th>{{ __('erp::ui.quantity') }}</th><th>{{ __('erp::ui.unit_cost') }}</th><th></th></tr></thead><tbody id="receipt-lines"></tbody></table></div>
<p><button class="erp-btn secondary" type="button" id="add-receipt-line">+ {{ __('erp::ui.add_line') }}</button></p><button class="erp-btn">{{ __('erp::ui.receive_goods') }}</button></form></div>
<div class="erp-card"><h3>{{ __('erp::ui.stock') }}</h3><div class="erp-table-scroll"><table class="erp-table"><thead><tr><th>{{ __('erp::ui.products') }}</th><th>{{ __('erp::ui.sku') }}</th><th>{{ __('erp::ui.warehouse') }}</th><th>{{ __('erp::ui.quantity') }}</th><th>{{ __('erp::ui.unit_cost') }}</th></tr></thead><tbody>
@foreach($rows as $s)<tr><td>{{ $s->name }}</td><td>{{ $s->sku }}</td><td>{{ $s->warehouse }}</td><td>{{ $s->quantity }}</td><td>{{ $s->average_cost }}</td></tr>@endforeach
</tbody></table></div><div class="erp-pagination">{{ $rows->links() }}</div></div>
@endsection
@section('scripts')<script>
(()=>{let i=0;const body=document.getElementById('receipt-lines'),url=@js(route('erp.products.select2'));
function add(){let n=i++,tr=document.createElement('tr');tr.innerHTML=`<td><select name="lines[${n}][product_variant_id]" required class="erp-field lookup"><option></option></select></td><td><input name="lines[${n}][quantity]" required class="erp-field" type="number" min=".000001" step="any" value="1"></td><td><input name="lines[${n}][unit_cost]" required class="erp-field" type="number" min=".000001" step="any" value="1"></td><td><button class="erp-btn secondary del" type="button">×</button></td>`;body.append(tr);let sel=tr.querySelector('select');
if(window.jQuery?.fn?.select2)window.jQuery(sel).select2({width:'100%',minimumInputLength:1,ajax:{url,delay:250,data:p=>({q:p.term??''}),processResults:d=>d}});
else{let search=document.createElement('input');search.className='erp-field';search.placeholder='Search...';sel.before(search);let last=0;search.oninput=async()=>{const num=++last;let r=await fetch(url+'?q='+encodeURIComponent(search.value)),d=await r.json();if(num!==last)return;sel.replaceChildren(new Option('Choose',''));d.results.forEach(x=>sel.add(new Option(x.text,x.id)));};}
tr.querySelector('.del').onclick=()=>{if(body.children.length>1)tr.remove();};}
document.getElementById('add-receipt-line').onclick=add;add();
})();
</script>@endsection
