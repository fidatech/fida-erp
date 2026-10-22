@extends('erp.layout')
@section('content')
<h1 class="erp-title">Finance and accounting</h1>
<p>Posted ledgers and financial statements in {{ $currency }}. Amounts use historical posted journal entries.</p>
<div class="erp-card">
  <form method="GET" action="{{ route('erp.finance.index') }}" class="erp-flex">
    <label class="erp-label">Period begins <input class="erp-field" type="date" name="from" value="{{ $from }}" required></label>
    <label class="erp-label">Through <input class="erp-field" type="date" name="through" value="{{ $through }}" required></label>
    <button class="erp-btn" type="submit">Apply dates</button>
  </form>
</div>
<div class="erp-card">
<h3>Trial balance as of {{ $through }}</h3>
<div class="erp-table-scroll"><table class="erp-table">
<thead><tr><th>Account</th><th>Type</th><th>Debit ({{ $currency }})</th><th>Credit ({{ $currency }})</th></tr></thead><tbody>
@foreach($trial['rows'] as $row)
<tr><td><a href="{{ route('erp.finance.ledger',['accountId'=>$row['id'],'from'=>$from,'through'=>$through]) }}">{{ $row['code'] }} - {{ $row['name'] }}</a></td><td>{{ ucfirst($row['type']) }}</td><td>{{ $row['balance_debit'] }}</td><td>{{ $row['balance_credit'] }}</td></tr>
@endforeach
<tr><th>Total</th><th>{{ $trial['balanced'] ? 'Balanced' : 'OUT OF BALANCE - investigate' }}</th><th>{{ $trial['debit'] }}</th><th>{{ $trial['credit'] }}</th></tr>
</tbody></table></div>
</div>
<div class="erp-card">
<h3>Profit and loss: {{ $from }} to {{ $through }}</h3>
<div class="erp-table-scroll"><table class="erp-table"><thead><tr><th>Category / account</th><th>Amount ({{ $currency }})</th></tr></thead><tbody>
<tr><th colspan="2">Income</th></tr>
@foreach($income['income'] as $row)<tr><td>{{ $row['code'] }} - {{ $row['name'] }}</td><td>{{ $row['amount'] }}</td></tr>@endforeach
<tr><th>Total income</th><th>{{ $income['revenue'] }}</th></tr>
<tr><th colspan="2">Expenses</th></tr>
@foreach($income['expenses'] as $row)<tr><td>{{ $row['code'] }} - {{ $row['name'] }}</td><td>{{ $row['amount'] }}</td></tr>@endforeach
<tr><th>Total expenses</th><th>{{ $income['cost'] }}</th></tr>
<tr><th>Net profit / loss</th><th>{{ $income['profit'] }}</th></tr>
</tbody></table></div>
</div>
<div class="erp-card">
<h3>Balance sheet as of {{ $through }}</h3>
<div class="erp-table-scroll"><table class="erp-table"><thead><tr><th>Account</th><th>Amount ({{ $currency }})</th></tr></thead><tbody>
@foreach(['asset'=>'Assets','liability'=>'Liabilities','equity'=>'Equity'] as $type=>$label)
<tr><th colspan="2">{{ $label }}</th></tr>
@foreach($balance['groups'][$type] as $row)<tr><td>{{ $row['code'] }} - {{ $row['name'] }}</td><td>{{ $row['amount'] }}</td></tr>@endforeach
<tr><th>Total {{ strtolower($label) }}</th><th>{{ $balance['totals'][$type] }}</th></tr>
@endforeach
<tr><td>Unclosed earnings (cumulative)</td><td>{{ $balance['earnings'] }}</td></tr>
<tr><th>Total liabilities, equity and earnings</th><th>{{ $balance['right'] }}</th></tr>
<tr><th>Accounting equation</th><th>{{ $balance['balanced'] ? 'Balanced' : 'OUT OF BALANCE - investigate' }}</th></tr>
</tbody></table></div>
</div>
@if(auth('erp_tenant')->user()->allowed('accounts.post'))
<div class="erp-card">
<h3>Post manual journal</h3>
<p>Base currency only. A posted journal cannot be edited or deleted. Corrections require a reversal entry.</p>
<form method="POST" action="{{ route('erp.finance.journal.post') }}">@csrf
<input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
<div class="erp-form-grid">
<label class="erp-label">Posting date<input class="erp-field" type="date" name="date" value="{{ old('date', now()->toDateString()) }}" required></label>
<label class="erp-label">Description<input class="erp-field" name="description" value="{{ old('description') }}" maxlength="250" required></label>
</div>
<div class="erp-table-scroll"><table class="erp-table"><thead><tr><th>Ledger account</th><th>Debit</th><th>Credit</th><th></th></tr></thead><tbody id="journal-lines"></tbody></table></div>
<p><button type="button" id="add-journal-line" class="erp-btn secondary">Add account line</button></p>
<button type="submit" class="erp-btn" onclick="return confirm('Post this balanced journal permanently?')">Post journal</button>
</form>
</div>
@endif
@endsection
@section('scripts')
<script>
(()=>{
const body=document.getElementById('journal-lines');if(!body)return;
const options=@json($accounts->map(fn($a)=>['code'=>$a->code,'label'=>$a->code.' - '.$a->name])->values());
let n=0;
function add(){
 const i=n++,tr=document.createElement('tr'),select=document.createElement('select');
 select.name='lines['+i+'][account_code]';select.required=true;select.className='erp-field';
 select.add(new Option('Select account',''));
 for(const o of options)select.add(new Option(o.label,o.code));
 const cell=document.createElement('td');cell.appendChild(select);tr.appendChild(cell);
 for(const field of ['debit','credit']){
  const td=document.createElement('td'),inp=document.createElement('input');
  inp.className='erp-field';inp.name='lines['+i+']['+field+']';inp.type='number';inp.step='0.000001';inp.min='0';inp.value='0';inp.required=true;td.appendChild(inp);tr.appendChild(td);
 }
 const td=document.createElement('td'),del=document.createElement('button');
 del.type='button';del.className='erp-btn secondary';del.textContent='Remove';
 del.onclick=()=>{if(body.children.length>2)tr.remove();};
 td.appendChild(del);tr.appendChild(td);body.appendChild(tr);
}
document.getElementById('add-journal-line').onclick=add;add();add();
})();
</script>
@endsection
