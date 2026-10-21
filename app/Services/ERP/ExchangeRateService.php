<?php
namespace App\Services\ERP;
use Illuminate\Support\Facades\DB;
use DomainException;
/** Historic rate retrieval. Do NOT scrape xe.com; production provider access requires approved API terms. */
final class ExchangeRateService
{
    public function latest(string $from,string $to): string
    {
        if($from===$to)return '1.000000000000';
        $rate=DB::connection('erp_tenant')->table('exchange_rates')->where('from_currency',$from)
            ->where('to_currency',$to)->where('valid_at','<=',now())->orderByDesc('valid_at')->first();
        if(!$rate)throw new DomainException('No approved exchange rate for '.$from.'/'.$to);
        return (string)$rate->rate;
    }
}
