<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('erp_tenant')->create('branch_user', function (Blueprint $table) {
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['branch_id', 'user_id']);
            $table->index(['user_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('erp_tenant')->dropIfExists('branch_user');
    }
};
