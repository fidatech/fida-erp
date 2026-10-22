@extends('erp.layout')
@section('title','Add Sale')
@section('content')
<div class="fida-page-head"><div><p class="fida-chart-note">Sales &gt; Sales Invoices &gt; Add Sale</p><h1>Add Sale</h1><p>Create a new sales invoice for your customer.</p></div><a href="{{ route('erp.sales.index') }}" class="fida-filter">← Back to Sales</a></div>
<form id="fida-sale-form" method="POST" action="{{ route('erp.sales.store') }}">
@csrf<input type="hidden" name="request_key" value="{{ old('request_key',(string)\Illuminate\Support\Str::uuid()) }}">
<section class="fida-panel" style="margin-bottom:13px"><div class="fida-panel-heading"><h2>▤ Sale Information</h2></div><div class="fida-panel-body">
<div class="fida-form-grid">
<div><label class="erp-label">Customer *</label><select id="customer" name="customer_id" class="erp-field" required><option value="">Search and select customer...</option></select></div>
<div><label class="erp-label">Invoice Number</label><input readonly class="erp-field" value="Automatically generated"></div>
<div><label class="erp-label">Invoice Date</label><input readonly class="erp-field" value="{{ now()->format('d M Y') }}"></div>
<div><label class="erp-label">Warehouse *</label><select class="erp-field" name="warehouse_id" required><option value="">Select warehouse</option>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div>
<div><label class="erp-label">Currency *</label><select class="erp-field" name="currency_code" id="sale-currency" required>@foreach($currencies as $c)<option value="{{ $c->code }}" @selected($c->code===$base)>{{ $c->code }} · {{ $c->name }}</option>@endforeach</select></div>
<div><label class="erp-label">Base Exchange Rate ({{ $base }}) *</label><input class="erp-field" name="fx_rate" id="sale-rate" value="1" type="number" step="0.000000000001" min="0.000000000001" required></div>
</div><p class="fida-chart-note">Customer account details are loaded from your company's database. Exchange rate is recorded permanently with the invoice.</p></div></section>
<section class="fida-panel" style="margin-bottom:13px"><div class="fida-panel-heading"><h2>◇ Product Line Items</h2><button class="erp-btn" type="button" id="add-line">＋ Add Item</button></div>
<div class="fida-table-wrap"><table class="fida-table" style="min-width:850px"><thead><tr><th>#</th><th>Product / Item *</th><th>Qty *</th><th>Unit Price *</th><th>Discount</th><th>Tax</th><th>Amount</th><th>Actions</th></tr></thead><tbody id="invoice-lines"></tbody></table></div>
</section>
<div style="display:grid;grid-template-columns:minmax(0,1.5fr) minmax(260px,.7fr);gap:12px" class="fida-sale-bottom">
<section class="fida-panel"><div class="fida-panel-heading"><h2>Additional Information</h2></div><div class="fida-panel-body">
<label class="erp-label">Invoice Discount (flat amount)</label><input class="erp-field" type="number" name="discount" id="invoice-discount" value="{{ old('discount','0') }}" step="0.000001" min="0">
<p class="fida-chart-note">You can review and post the invoice from the Sales list. Posting records inventory movements and journal entries. Discounts and taxes are monetary amounts, not percentages.</p>
</div></section>
<section class="fida-panel"><div class="fida-panel-heading"><h2>▤ Order Summary</h2></div><div class="fida-panel-body">
<div class="fida-insight-row">Subtotal <b id="sale-subtotal">0.00</b></div><div class="fida-insight-row">Discount <b id="sale-discount">0.00</b></div><div class="fida-insight-row">Tax <b id="sale-tax">0.00</b></div><div class="fida-insight-row"><strong>Total Amount</strong><strong class="fida-kpi-value" id="sale-total">0.00</strong></div>
</div></section>
</div>
<div class="fida-panel" style="margin-top:13px;padding:12px;display:flex;justify-content:space-between;gap:12px">
<a class="erp-btn secondary" href="{{ route('erp.sales.index') }}">Cancel</a>
<button class="erp-btn" type="submit">Save Draft Invoice →</button>
</div></form>
@endsection
@section('scripts')
<style>@media(max-width:800px){.fida-sale-bottom{grid-template-columns:1fr!important}}#invoice-lines td{padding:7px}#invoice-lines .erp-field{min-width:65px}#invoice-lines .item-lookup{min-width:200px}</style>
<script>
(()=>{
 let n=0;const rows=document.getElementById('invoice-lines'),customerURL=@js(route('erp.customers.select2')),variantURL=@js(route('erp.products.select2')),base=@js($base);
 const lookup=async(url,q)=>{const res=await fetch(url+'?q='+encodeURIComponent(q),{credentials:'same-origin'});if(!res.ok)throw Error('Lookup failed');return (await res.json()).results};
 const selectLookup=(select,url)=>{
  if(window.jQuery?.fn?.select2){window.jQuery(select).select2({width:'100%',minimumInputLength:1,ajax:{url,delay:250,data:x=>({q:x.term||''}),processResults:d=>d}})}
  else{const f=document.createElement('input');f.className='erp-field';f.placeholder='Search...';select.before(f);let seq=0;f.oninput=async()=>{const current=++seq;try{const data=await lookup(url,f.value);if(seq!==current)return;select.replaceChildren(new Option('Choose',''));data.forEach(x=>{const o=new Option(x.text,x.id);o.dataset.price=x.price||'';select.add(o)})}catch(e){console.warn(e)}}}
 };
 const money=n=>(Number.isFinite(n)?n:0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
 function recalc(){
  let sub=0,discount=Number(document.getElementById('invoice-discount').value)||0,tax=0;
  for(const row of rows.children){const q=Number(row.querySelector('.item-qty').value)||0,p=Number(row.querySelector('.item-price').value)||0,d=Number(row.querySelector('.item-discount').value)||0,t=Number(row.querySelector('.item-tax').value)||0;sub+=q*p;discount+=d;tax+=t;row.querySelector('.item-total').textContent=money(q*p-d+t)}
  document.getElementById('sale-subtotal').textContent=money(sub);document.getElementById('sale-discount').textContent=money(discount);document.getElementById('sale-tax').textContent=money(tax);document.getElementById('sale-total').textContent=money(sub-discount+tax);
 }
 selectLookup(document.getElementById('customer'),customerURL);
 document.getElementById('sale-currency').onchange=e=>{if(e.target.value===base)document.getElementById('sale-rate').value='1'};
 function add(){
  const i=n++,row=document.createElement('tr');
  row.innerHTML='<td>'+(i+1)+'</td><td><select class="erp-field item-lookup" required name="lines['+i+'][product_variant_id]"><option value="">Search product...</option></select></td><td><input required class="erp-field item-qty" type="number" min="0.000001" step="0.000001" value="1" name="lines['+i+'][quantity]"></td><td><input required class="erp-field item-price" type="number" min="0" step="0.000001" value="0" name="lines['+i+'][unit_price]"></td><td><input class="erp-field item-discount" type="number" min="0" step="0.000001" value="0" name="lines['+i+'][discount]"></td><td><input class="erp-field item-tax" type="number" min="0" step="0.000001" value="0" name="lines['+i+'][tax]"></td><td class="item-total">0.00</td><td><button type="button" class="erp-btn secondary remove" aria-label="Remove line">×</button></td>';
  rows.appendChild(row);
  const select=row.querySelector('.item-lookup');selectLookup(select,variantURL);
  if(window.jQuery?.fn?.select2)window.jQuery(select).on('select2:select',event=>{row.querySelector('.item-price').value=event.params.data.price||0;recalc()});
  else select.onchange=()=>{row.querySelector('.item-price').value=select.selectedOptions[0]?.dataset.price||0;recalc()};
  row.addEventListener('input',recalc);row.querySelector('.remove').onclick=()=>{if(rows.children.length>1)row.remove();recalc()};recalc();
 }
 document.getElementById('invoice-discount').addEventListener('input',recalc);
 document.getElementById('add-line').addEventListener('click',add);add();
})();
</script>
@endsection
