<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::connection('erp_tenant')->create('demo_seed_runs',function(Blueprint $t){$t->id();$t->string('seed_name',60)->unique();$t->unsignedInteger('version');$t->timestamps();});}
 public function down():void {Schema::connection('erp_tenant')->dropIfExists('demo_seed_runs');}
};
