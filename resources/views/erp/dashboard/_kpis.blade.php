@php
$cards=[
 ['label'=>'Total Revenue','value'=>$metrics['revenue'],'icon'=>'shopping-cart','tone'=>'green','link'=>'erp.finance.index','hint'=>'Posted income this month','money'=>true],
 ['label'=>'Total Expenses','value'=>$metrics['expenses'],'icon'=>'wallet','tone'=>'','link'=>'erp.finance.index','hint'=>'Posted expenses this month','money'=>true],
 ['label'=>'Net Profit','value'=>$metrics['profit'],'icon'=>'chart-pie','tone'=>'violet','link'=>'erp.finance.index','hint'=>'Current month earnings','money'=>true],
 ['label'=>'Active Employees','value'=>$metrics['employees'],'icon'=>'users','tone'=>'','link'=>null,'hint'=>'Current employee records','money'=>false],
 ['label'=>'Inventory Value','value'=>$metrics['inventory'],'icon'=>'package','tone'=>'','link'=>'erp.stock.index','hint'=>'Stock at weighted average','money'=>true],
 ['label'=>'Open Receivables','value'=>$metrics['outstanding'],'icon'=>'file-text','tone'=>'amber','link'=>'erp.sales.index','hint'=>'Unpaid posted invoices','money'=>true],
];
$heights=collect($metrics['monthly'])->map(fn($month)=>max(7,min(100,round(abs($month['revenue']) / max(1,collect($metrics['monthly'])->max(fn($m)=>abs($m['revenue']))) * 100))));
@endphp
<div class="fida-kpi-grid">
@foreach($cards as $card)
<div class="fida-kpi">
 <div class="fida-kpi-top"><span class="fida-kpi-icon {{ $card['tone'] }}"><svg class="fi"><use href="{{ asset('fida-erp/ui-icons.svg') }}#{{ $card['icon'] }}"></use></svg></span>
 <span><span class="fida-kpi-label">{{ $card['label'] }}</span><strong class="fida-kpi-value">{{ $card['money'] ? number_format((float)$card['value'],2) : number_format((int)$card['value']) }}</strong></span></div>
 <div class="fida-spark {{ $card['tone']==='green'?'green':'' }}" aria-hidden="true">@foreach($heights as $h)<span style="height:{{ $h }}%"></span>@endforeach</div>
 <div class="fida-kpi-footer"><span>{{ $card['money'] ? $metrics['currency'].' · '.$metrics['period'] : $card['hint'] }}</span>
 @if($card['link'])<a href="{{ route($card['link']) }}" style="color:#0877f6">Details →</a>@endif</div>
</div>
@endforeach
</div>
