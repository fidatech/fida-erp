@extends('erp.layout')
@section('content')
<h1 class="erp-title">{{ __('erp::ui.new_invoice') }}</h1>
<form method="POST" action="{{ route('erp.sales.store') }}" class="erp-card">@csrf<input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
<div class="erp-form-grid"><div><label class="erp-label">{{ __('erp::ui.customers') }}</label><select required id="customer" name="customer_id" class="erp-field"><option value="">{{ __('erp::ui.search') }}</option></select></div>
<div><label class="erp-label">{{ __('erp::ui.warehouse') }}</label><select name="warehouse_id" required class="erp-field"><option value=""></option>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div>
<div><label class="erp-label">{{ __('erp::ui.currency') }}</label><select name="currency_code" required class="erp-field">@foreach($currencies as $c)<option value="{{ $c->code }}" @selected($c->code===$base)>{{ $c->code }} — {{ $c->name }}</option>@endforeach</select></div>
<div><label class="erp-label">{{ __('erp::ui.base_rate') }} ({{ $base }})</label><input class="erp-field" name="fx_rate" value="1.000000" required type="number" min="0.000000000001" step="any"></div></div>
<div class="erp-table-scroll" style="margin-top:20px"><table class="erp-table"><thead><tr><th>{{ __('erp::ui.products') }}</th><th>{{ __('erp::ui.quantity') }}</th><th>{{ __('erp::ui.unit_price') }}</th><th>{{ __('erp::ui.discount') }}</th><th>{{ __('erp::ui.tax') }}</th><th> </th></tr></thead><tbody id="invoice-lines"></tbody></table></div>
<p><button type="button" class="erp-btn secondary" id="add-line">+ {{ __('erp::ui.add_line') }}</button></p>
<div class="erp-form-grid"><div><label class="erp-label">{{ __('erp::ui.discount') }}</label><input name="discount" value="0" class="erp-field" type="number" min="0" step="any"></div></div>
<div class="erp-note">{{ __('erp::ui.accounting_warning') }}</div><button class="erp-btn">{{ __('erp::ui.save') }} {{ __('erp::ui.draft') }}</button>
</form>
@endsection
@section('scripts')
<script>
(()=>{let i=0;const rows=document.getElementById('invoice-lines');
const customerURL=@js(route('erp.customers.select2'));const variantURL=@js(route('erp.products.select2'));
async function search(url,q){let r=await fetch(url+'?q='+encodeURIComponent(q),{credentials:'same-origin'});if(!r.ok)throw Error('Lookup failed');return (await r.json()).results;}
function setupNative(select,url){let filter=document.createElement('input');filter.className='erp-field';filter.placeholder='Type to search...';select.before(filter);let pending=0;filter.oninput=async()=>{const my=++pending;let list=await search(url,filter.value);if(my!==pending)return;select.replaceChildren(new Option('Choose...', ''));list.forEach(x=>{let o=new Option(x.text,x.id);o.dataset.price=x.price??'';select.add(o);});};}
function setupSelect(select,url){if(window.jQuery?.fn?.select2){window.jQuery(select).select2({width:'100%',ajax:{url,delay:250,data:p=>({q:p.term??''}),processResults:data=>data},minimumInputLength:1});}else setupNative(select,url);}
setupSelect(document.getElementById('customer'),customerURL);
function add(){let n=i++;let tr=document.createElement('tr');tr.innerHTML=`<td><select required class="erp-field lookup" name="lines[${n}][product_variant_id]"><option value=""></option></select></td><td><input required class="erp-field" type="number" min=".000001" step="any" value="1" name="lines[${n}][quantity]"></td><td><input required class="erp-field price" type="number" min="0" step="any" value="0" name="lines[${n}][unit_price]"></td><td><input class="erp-field" type="number" min="0" step="any" value="0" name="lines[${n}][discount]"></td><td><input class="erp-field" type="number" min="0" step="any" value="0" name="lines[${n}][tax]"></td><td><button type="button" class="erp-btn secondary remove">×</button></td>`;
rows.append(tr);const sel=tr.querySelector('.lookup');setupSelect(sel,variantURL);
if(window.jQuery?.fn?.select2){window.jQuery(sel).on('select2:select',e=>{tr.querySelector('.price').value=e.params.data.price??0;});}
else sel.onchange=()=>tr.querySelector('.price').value=sel.selectedOptions[0]?.dataset.price??0;
tr.querySelector('.remove').onclick=()=>{if(rows.children.length>1)tr.remove();};}
document.getElementById('add-line').addEventListener('click',add);add();
})();
</script>
@endsection
