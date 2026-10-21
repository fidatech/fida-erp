<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::connection('erp_tenant')->create('goods_receipts',function(Blueprint $t){
   $t->id();$t->string('number')->unique();$t->foreignId('supplier_id')->constrained('suppliers');
   $t->foreignId('warehouse_id')->constrained('warehouses');$t->char('currency_code',3);
   $t->decimal('fx_rate',24,12);$t->decimal('total',24,6);$t->string('status',20)->default('posted');
   $t->timestamp('posted_at');$t->timestamps();$t->index(['created_at','id']);
  });
  Schema::connection('erp_tenant')->create('goods_receipt_lines',function(Blueprint $t){
   $t->id();$t->foreignId('goods_receipt_id')->constrained('goods_receipts');
   $t->foreignId('product_variant_id')->constrained('product_variants');
   $t->decimal('quantity',24,6);$t->decimal('unit_cost',24,6);$t->decimal('base_unit_cost',24,6);
   $t->timestamps();
  });
 }
 public function down(): void {Schema::connection('erp_tenant')->dropIfExists('goods_receipt_lines');Schema::connection('erp_tenant')->dropIfExists('goods_receipts');}
};
