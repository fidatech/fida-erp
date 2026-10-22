<?php
namespace App\Http\Controllers\ERP;
use App\Http\Controllers\Controller;
use App\Services\ERP\FinancialReportService;
use App\Services\ERP\ManualJournalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use DomainException;
use InvalidArgumentException;

final class FinanceController extends Controller
{
    public function index(Request $request, FinancialReportService $reports)
    {
        $dates=$request->validate(['from'=>'nullable|date_format:Y-m-d','through'=>'nullable|date_format:Y-m-d']);
        $through=$dates['through']??now()->toDateString();
        $from=$dates['from']??now()->startOfYear()->toDateString();
        if($from>$through) return back()->withErrors(['from'=>'Start date cannot exceed end date']);
        $db=DB::connection('erp_tenant');
        return view('erp.finance.index',[
            'from'=>$from,'through'=>$through,'currency'=>$db->table('company_settings')->value('base_currency'),
            'accounts'=>$db->table('accounts')->where('is_postable',1)->where('active',1)->orderBy('code')->get(),
            'trial'=>$reports->trialBalance($through),
            'income'=>$reports->incomeStatement($from,$through),
            'balance'=>$reports->balanceSheet($through),
        ]);
    }

    public function ledger(Request $request, int $accountId)
    {
        $dates=$request->validate(['from'=>'nullable|date_format:Y-m-d','through'=>'nullable|date_format:Y-m-d']);
        $from=$dates['from']??now()->startOfYear()->toDateString();
        $through=$dates['through']??now()->toDateString();
        abort_if($from>$through,422,'Invalid date range');
        $db=DB::connection('erp_tenant');
        $account=$db->table('accounts')->find($accountId);
        abort_unless($account,404);
        $opening=$db->table('journal_lines as l')->join('journals as j','j.id','=','l.journal_id')
            ->where('l.account_id',$accountId)->where('j.date','<',$from)
            ->selectRaw('COALESCE(SUM(l.base_debit),0) AS debit, COALESCE(SUM(l.base_credit),0) AS credit')->first();
        $movements=$db->table('journal_lines as l')->join('journals as j','j.id','=','l.journal_id')
            ->where('l.account_id',$accountId)->whereBetween('j.date',[$from,$through])
            ->select('j.date','j.reference','j.description','l.base_debit','l.base_credit',
                'l.currency_code','l.currency_amount')
            ->orderBy('j.date')->orderBy('l.id')->paginate(50)->withQueryString();
        return view('erp.finance.ledger',compact('account','from','through','opening','movements'));
    }

    public function postJournal(Request $request, ManualJournalService $service)
    {
        $input=$request->validate([
            'request_key'=>'required|uuid','date'=>'required|date_format:Y-m-d',
            'description'=>'required|string|max:250',
            'lines'=>'required|array|min:2|max:50',
            'lines.*.account_code'=>'required|string|max:40',
            'lines.*.debit'=>'required|numeric|min:0',
            'lines.*.credit'=>'required|numeric|min:0'
        ]);
        try{$id=$service->post($input);}
        catch(DomainException|InvalidArgumentException $e){return back()->withErrors(['journal'=>$e->getMessage()])->withInput();}
        return redirect()->route('erp.finance.index')->with('success','Journal #'.$id.' posted');
    }
}
