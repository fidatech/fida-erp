<?php
namespace App\Services\ERP;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use DomainException;

final class JournalService
{
    /**
     * $lines: [['account_code'=>'1100','currency_code'=>'AFN','currency_amount'=>'1.00',
     * 'fx_rate'=>'1','debit'=>'1.00','credit'=>'0'], ...].
     * debit/credit are in BASE currency. Must be invoked in business DB transaction.
     */
    public function post(string $sourceType,int $sourceId,string $date,array $lines,string $description=''): int
    {
        if (count($lines)<2) throw new DomainException('A journal requires at least two lines');
        $db=DB::connection('erp_tenant');
        if ($db->table('journals')->where('source_type',$sourceType)->where('source_id',$sourceId)->exists()) {
            throw new DomainException('This business event was already posted');
        }
        $period=$db->table('fiscal_periods')->where('starts_on','<=',$date)->where('ends_on','>=',$date)->first();
        if (!$period || $period->is_closed) throw new DomainException('No open fiscal period for posting date');
        $debit='0'; $credit='0';$prepared=[];
        foreach($lines as $line){
            $account=$db->table('accounts')->where('code',$line['account_code'])->where('active',1)->where('is_postable',1)->first();
            if(!$account) throw new DomainException('Missing or inactive ledger account '.$line['account_code']);
            $d=Decimal::valid((string)$line['debit']);$c=Decimal::valid((string)$line['credit']);
            if(Decimal::cmp($d,'0')<0||Decimal::cmp($c,'0')<0||(Decimal::cmp($d,'0')>0&&Decimal::cmp($c,'0')>0)) throw new DomainException('Invalid debit/credit');
            if(Decimal::cmp($d,'0')===0&&Decimal::cmp($c,'0')===0) throw new DomainException('Zero journal line');
            $debit=Decimal::add($debit,$d);$credit=Decimal::add($credit,$c);
            $prepared[]=['account_id'=>$account->id,'currency_code'=>$line['currency_code'],
                'currency_amount'=>Decimal::valid((string)$line['currency_amount']),
                'fx_rate'=>Decimal::valid((string)$line['fx_rate'],true),
                'base_debit'=>$d,'base_credit'=>$c,'created_at'=>now(),'updated_at'=>now()];
        }
        if(Decimal::cmp($debit,$credit)!==0) throw new DomainException('Unbalanced journal');
        $id=$db->table('journals')->insertGetId(['reference'=>'JRN-'.Str::ulid(),'date'=>$date,
            'source_type'=>$sourceType,'source_id'=>$sourceId,'description'=>$description,
            'created_by'=>auth('erp_tenant')->id(),'created_at'=>now(),'updated_at'=>now()]);
        foreach($prepared as &$line) $line['journal_id']=$id;
        $db->table('journal_lines')->insert($prepared);
        return $id;
    }
}
