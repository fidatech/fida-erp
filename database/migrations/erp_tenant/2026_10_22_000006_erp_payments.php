<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('erp_tenant')->create('customer_payments',function(Blueprint $t){
            $t->id();$t->uuid('request_key')->unique();
            $t->foreignId('sales_invoice_id')->constrained('sales_invoices');
            $t->decimal('amount',24,6);$t->char('currency_code',3);$t->decimal('fx_rate',24,12);
            $t->foreignId('journal_id')->nullable()->constrained('journals');
            $t->timestamps();$t->index(['sales_invoice_id','id']);
        });
        Schema::connection('erp_tenant')->create('supplier_payments',function(Blueprint $t){
            $t->id();$t->uuid('request_key')->unique();
            $t->foreignId('goods_receipt_id')->constrained('goods_receipts');
            $t->decimal('amount',24,6);$t->char('currency_code',3);$t->decimal('fx_rate',24,12);
            $t->foreignId('journal_id')->nullable()->constrained('journals');
            $t->timestamps();$t->index(['goods_receipt_id','id']);
        });
        // Existing tenants are given the cash and contributed-capital accounts.
        // New tenants receive these during provisioning after the migrations.
        $db=DB::connection('erp_tenant');
        if($db->table('accounts')->exists()) {
            foreach([['1200','Cash','asset','1000'],['3100','Contributed Capital','equity','3000']] as [$code,$name,$type,$parent]) {
                if(!$db->table('accounts')->where('code',$code)->exists()) {
                    $db->table('accounts')->insert(['code'=>$code,'name'=>$name,'type'=>$type,
                        'parent_id'=>$db->table('accounts')->where('code',$parent)->value('id'),
                        'is_postable'=>1,'active'=>1,'created_at'=>now(),'updated_at'=>now()]);
                }
            }
        }
    }
    public function down(): void
    {
        Schema::connection('erp_tenant')->dropIfExists('supplier_payments');
        Schema::connection('erp_tenant')->dropIfExists('customer_payments');
    }
};
