<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::connection('erp_tenant')->create('manual_vouchers',function(Blueprint $t){
            $t->id();$t->uuid('request_key')->unique();$t->string('description',250);
            $t->foreignId('journal_id')->nullable()->constrained('journals');
            $t->foreignId('created_by')->nullable();$t->timestamp('posted_at');$t->timestamps();
        });
        Schema::connection('erp_tenant')->create('stock_transfers',function(Blueprint $t){
            $t->id();$t->string('number')->unique();$t->uuid('request_key')->unique();
            $t->foreignId('from_warehouse_id')->constrained('warehouses');
            $t->foreignId('to_warehouse_id')->constrained('warehouses');
            $t->foreignId('product_variant_id')->constrained('product_variants');
            $t->decimal('quantity',24,6);$t->decimal('unit_cost',24,6);
            $t->foreignId('created_by')->nullable();$t->timestamps();
        });
        $db=DB::connection('erp_tenant');
        foreach(['accounts.post','stock.transfer'] as $name) {
            $id=$db->table('permissions')->where('name',$name)->value('id');
            if(!$id) $id=$db->table('permissions')->insertGetId(['name'=>$name]);
            $owner=$db->table('roles')->where('name','owner')->value('id');
            if($owner) $db->table('permission_role')->insertOrIgnore(['permission_id'=>$id,'role_id'=>$owner]);
        }
    }
    public function down(): void
    {
        Schema::connection('erp_tenant')->dropIfExists('stock_transfers');
        Schema::connection('erp_tenant')->dropIfExists('manual_vouchers');
    }
};
