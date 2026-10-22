<?php
namespace App\Services\ERP;

use DomainException;
use Illuminate\Support\Facades\DB;

final class SettlementService
{
    public function __construct(private readonly JournalService $journals) {}

    /** Receive a full or partial payment against one posted sales invoice. */
    public function receiveCustomerPayment(int $invoiceId, string $amount, string $key): int
    {
        return DB::connection('erp_tenant')->transaction(function() use ($invoiceId,$amount,$key) {
            $db=DB::connection('erp_tenant');
            $existing=$db->table('customer_payments')->where('request_key',$key)->first();
            if($existing) {
                if((int)$existing->sales_invoice_id!==$invoiceId) throw new DomainException('Payment key belongs to another invoice');
                return (int)$existing->id;
            }
            $invoice=$db->table('sales_invoices')->where('id',$invoiceId)->lockForUpdate()->first();
            if(!$invoice || $invoice->status!=='posted') throw new DomainException('Invoice must be posted before payment');
            $value=Decimal::fixed($amount,true);
            $due=Decimal::sub((string)$invoice->total,(string)$invoice->paid);
            if(Decimal::cmp($value,$due)>0) throw new DomainException('Payment exceeds outstanding invoice');
            $base=Decimal::mul($value,(string)$invoice->fx_rate);
            $id=$db->table('customer_payments')->insertGetId([
                'request_key'=>$key,'sales_invoice_id'=>$invoiceId,
                'amount'=>$value,'currency_code'=>$invoice->currency_code,
                'fx_rate'=>$invoice->fx_rate,'created_at'=>now(),'updated_at'=>now()
            ]);
            $journal=$this->journals->post('customer_payment',$id,now()->toDateString(),[
                ['account_code'=>'1200','currency_code'=>$invoice->currency_code,'currency_amount'=>$value,
                    'fx_rate'=>$invoice->fx_rate,'debit'=>$base,'credit'=>'0'],
                ['account_code'=>'1100','currency_code'=>$invoice->currency_code,'currency_amount'=>$value,
                    'fx_rate'=>$invoice->fx_rate,'debit'=>'0','credit'=>$base],
            ],'Customer payment against '.$invoice->number);
            $db->table('customer_payments')->where('id',$id)->update(['journal_id'=>$journal]);
            $db->table('sales_invoices')->where('id',$invoiceId)->update(['paid'=>Decimal::add((string)$invoice->paid,$value),'updated_at'=>now()]);
            $db->table('audit_logs')->insert(['user_id'=>auth('erp_tenant')->id(),'event'=>'customer.payment.posted',
                'entity_type'=>'customer_payment','entity_id'=>$id,'created_at'=>now(),'updated_at'=>now()]);
            return $id;
        },3);
    }

    /** Pay a supplier against a received and posted goods receipt. */
    public function paySupplier(int $receiptId, string $amount, string $key): int
    {
        return DB::connection('erp_tenant')->transaction(function() use ($receiptId,$amount,$key) {
            $db=DB::connection('erp_tenant');
            $existing=$db->table('supplier_payments')->where('request_key',$key)->first();
            if($existing) {
                if((int)$existing->goods_receipt_id!==$receiptId) throw new DomainException('Payment key belongs to another receipt');
                return (int)$existing->id;
            }
            $receipt=$db->table('goods_receipts')->where('id',$receiptId)->lockForUpdate()->first();
            if(!$receipt || $receipt->status!=='posted') throw new DomainException('Receipt must be posted');
            $value=Decimal::fixed($amount,true);
            $paid=(string)($db->table('supplier_payments')->where('goods_receipt_id',$receiptId)->sum('amount') ?? '0');
            if(Decimal::cmp($value,Decimal::sub((string)$receipt->total,$paid))>0)
                throw new DomainException('Payment exceeds supplier payable');
            $base=Decimal::mul($value,(string)$receipt->fx_rate);
            // Serialize cash disbursements on the cash ledger account.
            $cash=$db->table('accounts')->where('code','1200')->lockForUpdate()->first();
            if(!$cash || !$cash->active) throw new DomainException('Cash ledger unavailable');
            $totals=$db->table('journal_lines')->where('account_id',$cash->id)
                ->selectRaw('COALESCE(SUM(base_debit),0) as debit, COALESCE(SUM(base_credit),0) as credit')->first();
            if(Decimal::cmp(Decimal::sub((string)$totals->debit,(string)$totals->credit),$base)<0)
                throw new DomainException('Insufficient cash ledger balance');
            $id=$db->table('supplier_payments')->insertGetId([
                'request_key'=>$key,'goods_receipt_id'=>$receiptId,
                'amount'=>$value,'currency_code'=>$receipt->currency_code,
                'fx_rate'=>$receipt->fx_rate,'created_at'=>now(),'updated_at'=>now()
            ]);
            $journal=$this->journals->post('supplier_payment',$id,now()->toDateString(),[
                ['account_code'=>'2100','currency_code'=>$receipt->currency_code,'currency_amount'=>$value,
                    'fx_rate'=>$receipt->fx_rate,'debit'=>$base,'credit'=>'0'],
                ['account_code'=>'1200','currency_code'=>$receipt->currency_code,'currency_amount'=>$value,
                    'fx_rate'=>$receipt->fx_rate,'debit'=>'0','credit'=>$base],
            ],'Supplier payment against '.$receipt->number);
            $db->table('supplier_payments')->where('id',$id)->update(['journal_id'=>$journal]);
            $db->table('audit_logs')->insert(['user_id'=>auth('erp_tenant')->id(),'event'=>'supplier.payment.posted',
                'entity_type'=>'supplier_payment','entity_id'=>$id,'created_at'=>now(),'updated_at'=>now()]);
            return $id;
        },3);
    }
}
