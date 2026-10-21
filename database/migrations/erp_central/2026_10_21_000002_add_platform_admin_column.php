<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::connection('mysql')->table('users',function(Blueprint $t){$t->boolean('erp_platform_admin')->default(false)->index();}); }
 public function down(): void { Schema::connection('mysql')->table('users',function(Blueprint $t){$t->dropColumn('erp_platform_admin');}); }
};
