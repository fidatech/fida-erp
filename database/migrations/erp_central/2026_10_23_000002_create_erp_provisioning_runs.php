<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('mysql')->create('erp_provisioning_runs', function(Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->unique()->constrained('erp_registration_requests');
            $table->foreignId('tenant_id')->unique()->constrained('erp_tenants');
            $table->string('stage',30)->default('reserved')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error',190)->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('erp_provisioning_runs');
    }
};
