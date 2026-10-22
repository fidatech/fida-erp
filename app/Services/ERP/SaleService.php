<?php
namespace App\Services\ERP;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SaleService
{
    public function __construct(private readonly StockService $stocks,private readonly JournalService $journals){}

    /** The purchase currency -> base-currency rate is an explicit historic snapshot, never a live revaluation. */
    public function draft(array $input): int
    {
        return DB::connection('erp_tenant')->transaction(function()use($input){
            $db=DB::connection('erp_tenant');
            $existing=$db->table('sales_invoices')->where('request_key',$input['request_key'])->value('id');
            if($existing)return (int)$existing;
            $company=$db->table('company_settings')->first();
            if (!$company) throw new DomainException('Company settings missing');
            $fx=Decimal::valid((string)$input['fx_rate'],true);
            if ($input['currency_code']===$company->base_currency && Decimal::cmp($fx,'1')!==0) throw new DomainException('Base-currency FX rate must equal 1');
            if (!$db->table('currencies')->where('code',$input['currency_code'])->where('active',1)->exists()) throw new DomainException('Unsupported currency');
            $sub='0';$lineDiscount='0';$tax='0';$lines=[];
            foreach($input['lines'] as $row){
                $q=Decimal::fixed((string)$row['quantity'],true);
                $price=Decimal::fixed((string)$row['unit_price']);
                $discount=Decimal::fixed((string)($row['discount']??'0'));
                $lineTax=Decimal::fixed((string)($row['tax']??'0'));
                if(Decimal::cmp($price,'0')<0||Decimal::cmp($discount,'0')<0||Decimal::cmp($lineTax,'0')<0) throw new DomainException('Negative price, discount or tax');
                $gross=Decimal::mul($q,$price);
                if(Decimal::cmp($discount,$gross)>0)throw new DomainException('Discount exceeds line amount');
                $sub=Decimal::add($sub,$gross);$lineDiscount=Decimal::add($lineDiscount,$discount);$tax=Decimal::add($tax,$lineTax);
                $variant=$db->table('product_variants')->where('id',$row['product_variant_id'])->where('active',1)->first();
                if(!$variant)throw new DomainException('Missing product variant');
                $lines[]=['product_variant_id'=>$variant->id,'quantity'=>$q,'unit_price'=>$price,'discount'=>$discount,'tax'=>$lineTax,'cost_base'=>$variant->average_cost];
            }
            $invoiceDiscount=Decimal::fixed((string)($input['discount']??'0'));
            if(Decimal::cmp($invoiceDiscount,'0')<0||Decimal::cmp($invoiceDiscount,Decimal::sub($sub,$lineDiscount))>0) throw new DomainException('Invalid invoice discount');
            $total=Decimal::add(Decimal::sub(Decimal::sub($sub,$lineDiscount),$invoiceDiscount),$tax);
            if (Decimal::cmp($total,'0')<=0) throw new DomainException('Invoice total must be positive');
            $id=$db->table('sales_invoices')->insertGetId(['number'=>'INV-'.Str::ulid(),
                'request_key'=>$input['request_key'],'customer_id'=>$input['customer_id'],'warehouse_id'=>$input['warehouse_id'],
                'currency_code'=>$input['currency_code'],'fx_rate'=>$fx,'status'=>'draft',
                'subtotal'=>$sub,'discount'=>Decimal::add($invoiceDiscount,$lineDiscount),'tax'=>$tax,'total'=>$total,
                'paid'=>'0','created_at'=>now(),'updated_at'=>now()]);
            foreach($lines as $line){$line['sales_invoice_id']=$id;$line['created_at']=now();$line['updated_at']=now();$db->table('sales_invoice_lines')->insert($line);}
            return $id;
        },3);
    }

    public function post(int $id): void
    {
        DB::connection('erp_tenant')->transaction(function()use($id){
            $db=DB::connection('erp_tenant');
            $sale=$db->table('sales_invoices')->where('id',$id)->lockForUpdate()->first();
            if(!$sale||$sale->status!=='draft')throw new DomainException('Only draft invoices can be posted');
            $lines=$db->table('sales_invoice_lines')->where('sales_invoice_id',$id)->orderBy('product_variant_id')->orderBy('id')->get();
            if($lines->isEmpty())throw new DomainException('No invoice lines');
            $cost='0';
            foreach($lines as $line){
                $currentCost=(string)$db->table('product_variants')->where('id',$line->product_variant_id)->lockForUpdate()->value('average_cost');
                $db->table('sales_invoice_lines')->where('id',$line->id)->update(['cost_base'=>$currentCost,'updated_at'=>now()]);
                $this->stocks->move((int)$sale->warehouse_id,(int)$line->product_variant_id,
                  '-'.ltrim((string)$line->quantity,'+'), 'invoice:'.$id.':line:'.$line->id,'sales_invoice',$id,$currentCost);
                $cost=Decimal::add($cost,Decimal::mul((string)$line->quantity,$currentCost));
            }
            $rate=(string)$sale->fx_rate; $cur=$sale->currency_code;
            $baseTotal=Decimal::mul((string)$sale->total,$rate);
            $baseRevenue=Decimal::mul(Decimal::sub(Decimal::sub((string)$sale->subtotal,(string)$sale->discount),'0'),$rate);
            $baseTax=Decimal::sub($baseTotal,$baseRevenue); // Absorb rounding residual into tax leg, if present.
            $make=fn($code,$original,$debit,$credit)=>['account_code'=>$code,'currency_code'=>$cur,
                'currency_amount'=>$original,'fx_rate'=>$rate,'debit'=>$debit,'credit'=>$credit];
            $j=[ $make('1100',(string)$sale->total,$baseTotal,'0') ];
            if (Decimal::cmp($baseRevenue,'0')>0) $j[]=$make('4100',Decimal::sub((string)$sale->subtotal,(string)$sale->discount),'0',$baseRevenue);
            if(Decimal::cmp($baseTax,'0')>0) $j[]=$make('2200',(string)$sale->tax,'0',$baseTax);
            if(Decimal::cmp($cost,'0')>0){ $j[]=['account_code'=>'5100','currency_code'=>$db->table('company_settings')->value('base_currency'),'currency_amount'=>$cost,'fx_rate'=>'1','debit'=>$cost,'credit'=>'0']; $j[]=['account_code'=>'1300','currency_code'=>$db->table('company_settings')->value('base_currency'),'currency_amount'=>$cost,'fx_rate'=>'1','debit'=>'0','credit'=>$cost];}
            $this->journals->post('sales_invoice',$id,now()->toDateString(),$j,'Posted sales invoice '.$sale->number);
            $db->table('sales_invoices')->where('id',$id)->update(['status'=>'posted','posted_at'=>now(),'updated_at'=>now()]);
            $db->table('audit_logs')->insert(['user_id'=>auth('erp_tenant')->id(),'event'=>'invoice.posted',
                'entity_type'=>'sales_invoice','entity_id'=>$id,'created_at'=>now(),'updated_at'=>now()]);
        },3);
    }
}
