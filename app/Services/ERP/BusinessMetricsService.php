<?php
namespace App\Services\ERP;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * All dashboard figures come from this tenant's own posted business records.
 * Presentation helpers may round numbers, but the ledger remains authoritative.
 */
final class BusinessMetricsService
{
    public function snapshot(): array
    {
        $db = DB::connection('erp_tenant');
        $now = CarbonImmutable::now('Asia/Kabul');
        $periodStart = $now->startOfMonth()->toDateString();
        $end = $now->toDateString();
        $ledger = $db->table('journal_lines as l')
            ->join('journals as j','j.id','=','l.journal_id')
            ->join('accounts as a','a.id','=','l.account_id')
            ->whereBetween('j.date', [$periodStart, $end])
            ->whereIn('a.type',['income','expense'])
            ->selectRaw("a.type, SUM(l.base_debit) AS debits, SUM(l.base_credit) AS credits")
            ->groupBy('a.type')->get()->keyBy('type');
        $revenue = (float)($ledger->get('income')?->credits ?? 0)
            - (float)($ledger->get('income')?->debits ?? 0);
        $expense = (float)($ledger->get('expense')?->debits ?? 0)
            - (float)($ledger->get('expense')?->credits ?? 0);

        $months = [];
        for($i = 5; $i >= 0; $i--) {
            $start = $now->startOfMonth()->subMonths($i);
            $through = $start->endOfMonth()->toDateString();
            $from = $start->toDateString();
            $totals = $db->table('journal_lines as l')->join('journals as j','j.id','=','l.journal_id')
                ->join('accounts as a','a.id','=','l.account_id')
                ->whereBetween('j.date',[$from,$through])->whereIn('a.type',['income','expense'])
                ->selectRaw('a.type, SUM(l.base_debit) AS debits, SUM(l.base_credit) AS credits')
                ->groupBy('a.type')->get()->keyBy('type');
            $r = (float)($totals->get('income')?->credits ?? 0) - (float)($totals->get('income')?->debits ?? 0);
            $e = (float)($totals->get('expense')?->debits ?? 0) - (float)($totals->get('expense')?->credits ?? 0);
            $months[] = ['label'=>$start->format('M'), 'month'=>$start->format('Y-m'),
                'revenue'=>$r,'expenses'=>$e,'profit'=>$r-$e];
        }

        $statusCounts = $db->table('sales_invoices')->select('status')
            ->selectRaw('COUNT(*) AS n')->groupBy('status')->pluck('n','status');
        $salesCount = (int)$statusCounts->sum();
        $postedCount = (int)($statusCounts['posted'] ?? 0);
        $inventory = (float)$db->table('stock_balances as s')
            ->join('product_variants as v','v.id','=','s.product_variant_id')
            ->selectRaw('COALESCE(SUM(s.quantity * v.average_cost), 0) AS v')->value('v');
        $outstanding = (float)$db->table('sales_invoices')->where('status','posted')
            ->selectRaw('COALESCE(SUM((total - paid) * fx_rate),0) AS v')->value('v');
        $lowStock = (int)$db->table('stock_balances')->where('quantity','<=',5)->count();
        $categories = $db->table('sales_invoice_lines as l')
            ->join('sales_invoices as s','s.id','=','l.sales_invoice_id')
            ->join('product_variants as v','v.id','=','l.product_variant_id')
            ->join('products as p','p.id','=','v.product_id')
            ->leftJoin('categories as c','c.id','=','p.category_id')
            ->where('s.status','posted')
            ->selectRaw("COALESCE(c.name, 'Uncategorized') AS name, SUM((l.quantity * l.unit_price - l.discount) * s.fx_rate) AS amount")
            ->groupBy('c.name')->orderByDesc('amount')->limit(5)->get();
        $topProducts = $db->table('sales_invoice_lines as l')
            ->join('sales_invoices as s','s.id','=','l.sales_invoice_id')
            ->join('product_variants as v','v.id','=','l.product_variant_id')
            ->join('products as p','p.id','=','v.product_id')
            ->where('s.status','posted')
            ->select('p.name','v.sku')->selectRaw('SUM(l.quantity) AS units, SUM((l.quantity*l.unit_price-l.discount)*s.fx_rate) AS revenue')
            ->groupBy('p.name','v.sku')->orderByDesc('revenue')->limit(6)->get();

        return [
            'company'=>$db->table('company_settings')->first(),
            'currency'=>(string)$db->table('company_settings')->value('base_currency'),
            'period'=>$now->format('F Y'),
            'revenue'=>$revenue, 'expenses'=>$expense,'profit'=>$revenue-$expense,
            'inventory'=>$inventory,'outstanding'=>$outstanding,
            'customers'=>$db->table('customers')->count(),
            'products'=>$db->table('products')->count(),
            'employees'=>$db->table('employees')->where('status','active')->count(),
            'suppliers'=>$db->table('suppliers')->count(),
            'purchaseReceipts'=>$db->table('goods_receipts')->count(),
            'salesCount'=>$salesCount,'postedCount'=>$postedCount,'draftCount'=>(int)($statusCounts['draft']??0),
            'lowStock'=>$lowStock,'monthly'=>$months,
            'categories'=>$categories,'topProducts'=>$topProducts,
            'recent'=>$db->table('sales_invoices as s')->join('customers as c','c.id','=','s.customer_id')
                ->select('s.id','s.number','s.total','s.currency_code','s.status','s.created_at','c.name as customer')
                ->orderByDesc('s.id')->limit(12)->get(),
        ];
    }
}
