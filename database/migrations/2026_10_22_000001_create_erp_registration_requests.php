<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::connection('mysql')->create('erp_registration_requests',function(Blueprint $table){
   $table->id();$table->string('name',120);$table->string('email',190)->index();
   $table->string('company',190);$table->string('phone',40);$table->string('country',90);
   $table->string('password_hash');$table->string('status',24)->default('pending')->index();$table->timestamps();
  });
 }
 public function down(): void { Schema::connection('mysql')->dropIfExists('erp_registration_requests'); }
};
