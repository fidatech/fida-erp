@extends('erp.layout')
@section('content')
<h1 class="erp-title">General ledger - {{ $account->code }} {{ $account->name }}</h1>
<p><a href="{{ route('erp.finance.index') }}">Back to financial statements</a></p>
<div class="erp-card">
<form method="GET" class="erp-flex">
<label class="erp-label">From<input type="date" class="erp-field" name="from" value="{{ $from }}"></label>
<label class="erp-label">To<input type="date" class="erp-field" name="through" value="{{ $through }}"></label>
<button class="erp-btn">Filter</button>
</form>
<p>Opening net debit balance before {{ $from }}: {{ \App\Services\ERP\Decimal::sub((string)$opening->debit,(string)$opening->credit) }}</p>
<div class="erp-table-scroll"><table class="erp-table">
<thead><tr><th>Date</th><th>Reference</th><th>Description</th><th>Currency</th><th>Original amount</th><th>Base debit</th><th>Base credit</th></tr></thead>
<tbody>@forelse($movements as $line)
<tr><td>{{ $line->date }}</td><td>{{ $line->reference }}</td><td>{{ $line->description }}</td><td>{{ $line->currency_code }}</td><td>{{ $line->currency_amount }}</td><td>{{ $line->base_debit }}</td><td>{{ $line->base_credit }}</td></tr>
@empty<tr><td colspan="7">No transactions for this period.</td></tr>@endforelse</tbody>
</table></div>{{ $movements->links() }}
</div>
@endsection
