<?php
namespace App\Services\ERP;
use Illuminate\Support\Facades\DB;
final class FinancialReportService
{
    /** All values are calculated exclusively from posted journals in base currency. */
    public function balances(string $until, ?string $from = null): array
    {
        $db = DB::connection('erp_tenant');
        $query = $db->table('journal_lines as l')->join('journals as j','j.id','=','l.journal_id')
            ->select('l.account_id')->selectRaw('SUM(l.base_debit) AS debits, SUM(l.base_credit) AS credits')
            ->where('j.date','<=',$until);
        if ($from !== null) $query->where('j.date','>=',$from);
        $totals = $query->groupBy('l.account_id')->get()->keyBy('account_id');
        $rows = [];
        foreach ($db->table('accounts')->where('is_postable',1)->orderBy('code')->get() as $account) {
            $total = $totals->get($account->id);
            $debit = (string)($total->debits ?? '0');
            $credit = (string)($total->credits ?? '0');
            $rows[] = [
                'id'=>$account->id, 'code'=>$account->code, 'name'=>$account->name,
                'type'=>$account->type, 'debit'=>$debit, 'credit'=>$credit,
                'net'=>Decimal::sub($debit,$credit),
            ];
        }
        return $rows;
    }

    public function trialBalance(string $until): array
    {
        $rows=[];$debit='0';$credit='0';
        foreach($this->balances($until) as $row) {
            $net=$row['net'];
            $row['balance_debit']=Decimal::cmp($net,'0')>0?$net:'0';
            $row['balance_credit']=Decimal::cmp($net,'0')<0?Decimal::abs($net):'0';
            $debit=Decimal::add($debit,$row['balance_debit']);
            $credit=Decimal::add($credit,$row['balance_credit']);
            $rows[]=$row;
        }
        return ['rows'=>$rows,'debit'=>$debit,'credit'=>$credit,'balanced'=>Decimal::cmp($debit,$credit)===0];
    }

    public function incomeStatement(string $from, string $until): array
    {
        $income=[];$expenses=[];$revenue='0';$cost='0';
        foreach($this->balances($until,$from) as $row) {
            if($row['type']==='income') {
                $row['amount']=Decimal::sub($row['credit'],$row['debit']);
                $income[]=$row;$revenue=Decimal::add($revenue,$row['amount']);
            } elseif($row['type']==='expense') {
                $row['amount']=$row['net'];
                $expenses[]=$row;$cost=Decimal::add($cost,$row['amount']);
            }
        }
        return ['income'=>$income,'expenses'=>$expenses,'revenue'=>$revenue,'cost'=>$cost,
            'profit'=>Decimal::sub($revenue,$cost)];
    }

    /** Unclosed earnings are presented separately in equity to preserve the equation. */
    public function balanceSheet(string $until): array
    {
        $groups=['asset'=>[],'liability'=>[],'equity'=>[]];
        $totals=['asset'=>'0','liability'=>'0','equity'=>'0'];$earnings='0';
        foreach($this->balances($until) as $row) {
            $type=$row['type'];
            if(isset($groups[$type])) {
                $row['amount']=$type==='asset'?$row['net']:Decimal::sub($row['credit'],$row['debit']);
                $groups[$type][]=$row;$totals[$type]=Decimal::add($totals[$type],$row['amount']);
            } elseif($type==='income') {
                $earnings=Decimal::add($earnings,Decimal::sub($row['credit'],$row['debit']));
            } elseif($type==='expense') {
                $earnings=Decimal::sub($earnings,$row['net']);
            }
        }
        $right=Decimal::add(Decimal::add($totals['liability'],$totals['equity']),$earnings);
        return ['groups'=>$groups,'totals'=>$totals,'earnings'=>$earnings,
            'right'=>$right,'balanced'=>Decimal::cmp($totals['asset'],$right)===0];
    }
}
