<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::connection('erp_tenant')->table('sales_invoices',function(Blueprint $t){$t->uuid('request_key')->nullable()->unique();});
  Schema::connection('erp_tenant')->table('goods_receipts',function(Blueprint $t){$t->uuid('request_key')->nullable()->unique();});
 }
 public function down(): void {
  Schema::connection('erp_tenant')->table('goods_receipts',function(Blueprint $t){$t->dropUnique(['request_key']);$t->dropColumn('request_key');});
  Schema::connection('erp_tenant')->table('sales_invoices',function(Blueprint $t){$t->dropUnique(['request_key']);$t->dropColumn('request_key');});
 }
};
