<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::connection('mysql')->table('erp_registration_requests', function(Blueprint $table){
   $table->string('verification_token_hash',64)->nullable()->index();
   $table->timestamp('verification_expires_at')->nullable();
   $table->timestamp('email_verified_at')->nullable();
   $table->timestamp('verification_sent_at')->nullable();
  });
 }
 public function down(): void {
  Schema::connection('mysql')->table('erp_registration_requests',function(Blueprint $table){
   $table->dropColumn(['verification_token_hash','verification_expires_at','email_verified_at','verification_sent_at']);
  });
 }
};
