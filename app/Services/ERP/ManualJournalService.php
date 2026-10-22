<?php
namespace App\Services\ERP;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ManualJournalService
{
    public function __construct(private readonly JournalService $journals) {}
    public function post(array $input): int
    {
        return DB::connection('erp_tenant')->transaction(function() use ($input) {
            $db=DB::connection('erp_tenant');
            $existing=$db->table('manual_vouchers')->where('request_key',$input['request_key'])->first();
            if($existing) return (int)$existing->journal_id;
            $currency=$db->table('company_settings')->value('base_currency');
            if(!$currency) throw new DomainException('Base currency missing');
            $lines=[];
            foreach($input['lines'] as $line) {
                $d=Decimal::fixed((string)($line['debit']??'0'));
                $c=Decimal::fixed((string)($line['credit']??'0'));
                $lines[]=['account_code'=>$line['account_code'],'currency_code'=>$currency,
                    'currency_amount'=>Decimal::add($d,$c),'fx_rate'=>'1','debit'=>$d,'credit'=>$c];
            }
            $id=$db->table('manual_vouchers')->insertGetId([
                'request_key'=>$input['request_key'],'description'=>$input['description'],
                'created_by'=>auth('erp_tenant')->id(),'posted_at'=>now(),
                'created_at'=>now(),'updated_at'=>now()
            ]);
            $journal=$this->journals->post('manual_voucher',$id,$input['date'],$lines,$input['description']);
            $db->table('manual_vouchers')->where('id',$id)->update(['journal_id'=>$journal]);
            $db->table('audit_logs')->insert(['user_id'=>auth('erp_tenant')->id(),
                'event'=>'journal.posted','entity_type'=>'journal','entity_id'=>$journal,
                'created_at'=>now(),'updated_at'=>now()]);
            return $journal;
        },3);
    }
}
