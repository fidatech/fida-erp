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
<div><label class="erp-label">Warehouse *</label><select class="erp-field" name="warehouse_id" id="sale-warehouse" required><option value="">Select warehouse</option>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div>
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
<dialog id="quick-customer-modal" style="border:1px solid #dbe5ef;border-radius:14px;padding:25px;width:min(480px,95vw)">
<form id="quick-customer-form" action="{{ route('erp.customers.store') }}" method="POST">@csrf
<h2>Add customer</h2><p id="quick-customer-error" style="color:#b42332" role="alert"></p>
@foreach(['name'=>'Customer name','phone'=>'Phone','email'=>'Email','address'=>'Address'] as $field=>$label)
<p><label class="erp-label" for="quick-{{ $field }}">{{ $label }}</label><input class="erp-field" id="quick-{{ $field }}" name="{{ $field }}" @required($field==='name')></p>
@endforeach
<button type="button" class="erp-btn secondary" id="quick-customer-cancel">Cancel</button><button class="erp-btn" type="submit">Save customer</button>
</form></dialog>
<dialog id="quick-product-modal" style="border:1px solid #dbe5ef;border-radius:14px;padding:25px;width:min(480px,95vw)">
<form id="quick-product-form" method="POST" action="{{ route('erp.products.store') }}">@csrf
<h2>Add product</h2><p id="quick-product-error" style="color:#b42332" role="alert"></p>
<p><label class="erp-label">Product name *</label><input class="erp-field" name="name" required></p>
<p><label class="erp-label">SKU</label><input class="erp-field" name="sku"></p>
<p><label class="erp-label">Barcode</label><input class="erp-field" name="barcode"></p>
<p><label class="erp-label">Selling price *</label><input class="erp-field" name="sale_price" type="number" step="0.000001" min="0" value="0" required></p>
<button class="erp-btn secondary" type="button" id="quick-product-cancel">Cancel</button><button class="erp-btn" type="submit">Save product</button>
</form></dialog>
<dialog id="quick-warehouse-modal" style="border:1px solid #dbe5ef;border-radius:14px;padding:25px;width:min(480px,95vw)">
<form class="fida-quick-form" data-target="sale-warehouse" method="POST" action="{{ route('erp.quick.warehouse') }}">@csrf
<h2>Add warehouse</h2><p class="fida-quick-error" role="alert" style="color:#b42332"></p>
<p><label class="erp-label">Warehouse name *</label><input class="erp-field" name="name" required maxlength="190"></p>
<p><label class="erp-label">Warehouse code *</label><input class="erp-field" name="code" required maxlength="40" pattern="[A-Za-z0-9_-]+"></p>
<p><label class="erp-label">Branch *</label><select class="erp-field" name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></p>
<button class="erp-btn secondary fida-quick-cancel" type="button">Cancel</button><button class="erp-btn" type="submit">Save warehouse</button>
</form></dialog>
<dialog id="quick-currency-modal" style="border:1px solid #dbe5ef;border-radius:14px;padding:25px;width:min(480px,95vw)">
<form class="fida-quick-form" data-target="sale-currency" method="POST" action="{{ route('erp.quick.currency') }}">@csrf
<h2>Add currency</h2><p class="fida-quick-error" role="alert" style="color:#b42332"></p>
<p><label class="erp-label">ISO currency code *</label><input class="erp-field" name="code" required minlength="3" maxlength="3" pattern="[A-Za-z]{3}" placeholder="USD"></p>
<p><label class="erp-label">Currency name *</label><input class="erp-field" name="name" required maxlength="190"></p>
<p><label class="erp-label">Decimal places *</label><input class="erp-field" name="minor_units" type="number" min="0" max="4" value="2" required></p>
<button class="erp-btn secondary fida-quick-cancel" type="button">Cancel</button><button class="erp-btn" type="submit">Save currency</button>
</form></dialog>
@endsection
@section('scripts')
<style>@media(max-width:800px){.fida-sale-bottom{grid-template-columns:1fr!important}}#invoice-lines td{padding:7px}#invoice-lines .erp-field{min-width:65px}#invoice-lines .item-lookup{min-width:200px}</style>
<script>
(()=>{
 let n=0;const rows=document.getElementById('invoice-lines'),customerURL=@js(route('erp.customers.select2')),variantURL=@js(route('erp.products.select2')),base=@js($base);
 const lookup=async(url,q)=>{const res=await fetch(url+'?q='+encodeURIComponent(q),{credentials:'same-origin'});if(!res.ok)throw Error('Lookup failed');return (await res.json()).results};
 const selectLookup=(select,url)=>{
  if(window.jQuery?.fn?.select2){window.jQuery(select).select2({width:'100%',minimumInputLength:0,ajax:{url,delay:250,data:x=>({q:x.term||''}),processResults:d=>{if(select.id==='customer')d.results.unshift({id:'__add_customer__',text:'＋ Add new customer...'});else if(select.classList.contains('item-lookup'))d.results.unshift({id:'__add_product__',text:'＋ Add new product...'});return d}}})}
  else{const f=document.createElement('input');f.className='erp-field';f.placeholder='Search...';select.before(f);let seq=0;f.oninput=async()=>{const current=++seq;try{const data=await lookup(url,f.value);if(seq!==current)return;select.replaceChildren(new Option('Choose',''));data.forEach(x=>{const o=new Option(x.text,x.id);o.dataset.price=x.price||'';select.add(o)})}catch(e){console.warn(e)}}}
 };
 const money=n=>(Number.isFinite(n)?n:0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
 function recalc(){
  let sub=0,discount=Number(document.getElementById('invoice-discount').value)||0,tax=0;
  for(const row of rows.children){const q=Number(row.querySelector('.item-qty').value)||0,p=Number(row.querySelector('.item-price').value)||0,d=Number(row.querySelector('.item-discount').value)||0,t=Number(row.querySelector('.item-tax').value)||0;sub+=q*p;discount+=d;tax+=t;row.querySelector('.item-total').textContent=money(q*p-d+t)}
  document.getElementById('sale-subtotal').textContent=money(sub);document.getElementById('sale-discount').textContent=money(discount);document.getElementById('sale-tax').textContent=money(tax);document.getElementById('sale-total').textContent=money(sub-discount+tax);
 }
 selectLookup(document.getElementById('customer'),customerURL);
const customerSelect=document.getElementById('customer');
const customerModal=document.getElementById('quick-customer-modal');
const customerForm=document.getElementById('quick-customer-form');
const openCustomer=()=>{customerForm.reset();document.getElementById('quick-customer-error').textContent='';customerModal.showModal()};
document.getElementById('quick-customer-cancel').onclick=()=>customerModal.close();
if(window.jQuery?.fn?.select2)window.jQuery(customerSelect).on('select2:select',e=>{
 if(String(e.params.data.id)==='__add_customer__'){window.jQuery(customerSelect).val(null).trigger('change');openCustomer()}
});
customerForm.addEventListener('submit',async e=>{
 e.preventDefault();const button=customerForm.querySelector('[type=submit]');button.disabled=true;
 try{const response=await fetch(customerForm.action,{method:'POST',body:new FormData(customerForm),credentials:'same-origin',headers:{Accept:'application/json'}});
 const data=await response.json();if(!response.ok)throw Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'Unable to save customer');
 customerSelect.add(new Option(data.text,data.id,true,true));if(window.jQuery?.fn?.select2)window.jQuery(customerSelect).trigger('change');customerModal.close();
 }catch(err){document.getElementById('quick-customer-error').textContent=err.message}finally{button.disabled=false}
});

 document.getElementById('sale-currency').onchange=e=>{if(e.target.value===base)document.getElementById('sale-rate').value='1'};
 let pendingProductSelect=null;
 const productModal=document.getElementById('quick-product-modal'),productForm=document.getElementById('quick-product-form');
 document.getElementById('quick-product-cancel').onclick=()=>productModal.close();
 productForm.onsubmit=async event=>{
  event.preventDefault();const button=productForm.querySelector('[type=submit]');button.disabled=true;
  try{const res=await fetch(productForm.action,{method:'POST',body:new FormData(productForm),credentials:'same-origin',headers:{Accept:'application/json'}});
   const data=await res.json();if(!res.ok)throw Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'Unable to create product');
   if(pendingProductSelect){pendingProductSelect.add(new Option(data.text,data.id,true,true));window.jQuery(pendingProductSelect).trigger('change');const row=pendingProductSelect.closest('tr');row.querySelector('.item-price').value=data.price;recalc()}
   productModal.close();
  }catch(err){document.getElementById('quick-product-error').textContent=err.message}finally{button.disabled=false}
 };
 function add(){
  const i=n++,row=document.createElement('tr');
  row.innerHTML='<td>'+(i+1)+'</td><td><select class="erp-field item-lookup" required name="lines['+i+'][product_variant_id]"><option value="">Search product...</option></select></td><td><input required class="erp-field item-qty" type="number" min="0.000001" step="0.000001" value="1" name="lines['+i+'][quantity]"></td><td><input required class="erp-field item-price" type="number" min="0" step="0.000001" value="0" name="lines['+i+'][unit_price]"></td><td><input class="erp-field item-discount" type="number" min="0" step="0.000001" value="0" name="lines['+i+'][discount]"></td><td><input class="erp-field item-tax" type="number" min="0" step="0.000001" value="0" name="lines['+i+'][tax]"></td><td class="item-total">0.00</td><td><button type="button" class="erp-btn secondary remove" aria-label="Remove line">×</button></td>';
  rows.appendChild(row);
  const select=row.querySelector('.item-lookup');selectLookup(select,variantURL);
  if(window.jQuery?.fn?.select2)window.jQuery(select).on('select2:select',event=>{if(event.params.data.id==='__add_product__'){pendingProductSelect=select;window.jQuery(select).val(null).trigger('change');productForm.reset();document.getElementById('quick-product-error').textContent='';productModal.showModal();return;}row.querySelector('.item-price').value=event.params.data.price||0;recalc()});
  else select.onchange=()=>{row.querySelector('.item-price').value=select.selectedOptions[0]?.dataset.price||0;recalc()};
  row.addEventListener('input',recalc);row.querySelector('.remove').onclick=()=>{if(rows.children.length>1)row.remove();recalc()};recalc();
 }
 const quickSelects=[['sale-warehouse','quick-warehouse-modal','＋ Add new warehouse...'],['sale-currency','quick-currency-modal','＋ Add new currency...']];
 for(const [selectId,modalId,label] of quickSelects){
  const el=document.getElementById(selectId),dialog=document.getElementById(modalId);
  if(!el||!dialog)continue;
  if(window.jQuery?.fn?.select2){
   const $el=window.jQuery(el);
   if($el.hasClass('select2-hidden-accessible'))$el.select2('destroy');
   $el.select2({width:'100%',minimumResultsForSearch:0,templateResult:data=>data.id==='__create__'?window.jQuery('<strong>').text(label):data.text});
   $el.on('select2:opening',()=>{el.querySelector('option[value="__create__"]')?.remove();el.add(new Option(label,'__create__'),0)});
   $el.on('select2:select',event=>{if(event.params.data.id==='__create__'){$el.val(null).trigger('change');dialog.querySelector('form').reset();dialog.showModal()}});
  }
 }
 for(const form of document.querySelectorAll('.fida-quick-form')){
  const dialog=form.closest('dialog'),select=document.getElementById(form.dataset.target),error=form.querySelector('.fida-quick-error');
  form.querySelector('.fida-quick-cancel').onclick=()=>dialog.close();
  form.onsubmit=async event=>{
   event.preventDefault();error.textContent='';const button=form.querySelector('[type=submit]');button.disabled=true;
   try{const res=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{Accept:'application/json'},credentials:'same-origin'});
    const data=await res.json();if(!res.ok)throw Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'Unable to save');
    select.querySelector('option[value="__create__"]')?.remove();select.add(new Option(data.text,data.id,true,true));
    if(window.jQuery?.fn?.select2)window.jQuery(select).trigger('change');else select.dispatchEvent(new Event('change'));
    dialog.close();
   }catch(err){error.textContent=err.message}finally{button.disabled=false}
  };
 }
 document.getElementById('invoice-discount').addEventListener('input',recalc);
 document.getElementById('add-line').addEventListener('click',add);add();
})();
</script>
@endsection
