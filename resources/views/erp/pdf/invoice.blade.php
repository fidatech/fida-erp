<!doctype html><html lang="{{ $locale }}" dir="{{ $rtl?'rtl':'ltr' }}"><head><meta charset="utf-8"><style>
body{font-family:{{ $rtl?'vazirmatn':'dejavusans' }},sans-serif;color:#142b45;font-size:11pt}h1{font-size:19pt;margin-bottom:7mm}.top{border-bottom:3px solid #e98a28;padding-bottom:6mm;margin-bottom:7mm}.muted{color:#586a7c}table{width:100%;border-collapse:collapse;margin-top:7mm}th,td{border-bottom:1px solid #d9e1e8;padding:7px 5px;text-align:{{ $rtl?'right':'left' }}}th{background:#ecf1f5}h3{text-align:{{ $rtl?'left':'right' }}}
</style></head><body><div class="top"><b>{{ $company->name }}</b><h1>{{ __('erp::ui.sales', [], $locale) }}</h1><div class="muted">{{ $invoice->number }} — {{ $invoice->status }} — {{ $invoice->created_at }}</div></div>
<div><b>{{ __('erp::ui.customers', [], $locale) }}:</b> {{ $invoice->customer_name }}</div><div>{{ $invoice->customer_address }}</div>
<table><thead><tr><th>{{ __('erp::ui.products', [], $locale) }}</th><th>{{ __('erp::ui.quantity', [], $locale) }}</th><th>{{ __('erp::ui.unit_price', [], $locale) }}</th><th>{{ __('erp::ui.discount', [], $locale) }}</th></tr></thead><tbody>
@foreach($lines as $line)<tr><td>{{ $line->product_name }} ({{ $line->sku }})</td><td>{{ $line->quantity }}</td><td>{{ $line->unit_price }}</td><td>{{ $line->discount }}</td></tr>@endforeach
</tbody></table><h3>{{ __('erp::ui.total', [], $locale) }}: {{ $invoice->total }} {{ $invoice->currency_code }}</h3>
<div class="muted">{{ __('erp::ui.tax', [], $locale) }}: {{ $invoice->tax }}</div></body></html>
