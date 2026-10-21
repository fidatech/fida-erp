<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::connection('mysql')->create('erp_resellers', function(Blueprint $t){
            $t->id(); $t->string('name'); $t->string('email')->unique(); $t->string('status',20)->default('active');
            $t->decimal('commission_percent',6,3)->default(0); $t->timestamps();
        });
        Schema::connection('mysql')->create('erp_plans', function(Blueprint $t){
            $t->id();$t->string('code')->unique();$t->string('name');$t->decimal('price',18,2);
            $t->char('currency_code',3)->default('USD');$t->string('interval',15)->default('monthly');
            $t->unsignedInteger('max_users')->default(5);$t->unsignedInteger('max_branches')->default(1);
            $t->json('features')->nullable();$t->boolean('active')->default(true);$t->timestamps();
        });
        Schema::connection('mysql')->create('erp_tenants', function(Blueprint $t){
            $t->id();$t->string('business_name');$t->string('slug')->unique();$t->string('domain')->unique();
            $t->string('database_name')->unique();$t->string('database_username');$t->text('database_password');$t->foreignId('reseller_id')->nullable()->constrained('erp_resellers')->nullOnDelete();
            $t->string('status',20)->default('active')->index();$t->timestamp('trial_ends_at')->nullable();
            $t->timestamp('suspended_at')->nullable();$t->timestamps();
        });
        Schema::connection('mysql')->create('erp_subscriptions', function(Blueprint $t){
            $t->id();$t->foreignId('tenant_id')->constrained('erp_tenants')->cascadeOnDelete();
            $t->foreignId('plan_id')->constrained('erp_plans');$t->string('status',20)->index();
            $t->timestamp('starts_at');$t->timestamp('ends_at')->nullable()->index();$t->timestamps();
        });
        Schema::connection('mysql')->create('erp_platform_invoices', function(Blueprint $t){
            $t->id();$t->foreignId('tenant_id')->constrained('erp_tenants');$t->foreignId('subscription_id')->nullable()->constrained('erp_subscriptions');
            $t->string('number')->unique();$t->decimal('total',20,6);$t->decimal('paid',20,6)->default(0);$t->char('currency_code',3);
            $t->string('status',20)->index();$t->date('due_date')->nullable();$t->timestamps();
        });
        Schema::connection('mysql')->create('erp_platform_payments', function(Blueprint $t){
            $t->id();$t->foreignId('invoice_id')->constrained('erp_platform_invoices');$t->decimal('amount',20,6);
            $t->char('currency_code',3);$t->string('method',40);$t->string('provider_reference')->nullable()->unique();
            $t->string('status',20)->default('pending');$t->timestamps();
        });
        Schema::connection('mysql')->create('erp_platform_audit_logs', function(Blueprint $t){
            $t->id();$t->string('actor')->nullable();$t->string('event',100);$t->foreignId('tenant_id')->nullable()->index();
            $t->json('context')->nullable();$t->timestamps();
        });
    }
    public function down(): void {foreach(['erp_platform_audit_logs','erp_platform_payments','erp_platform_invoices','erp_subscriptions','erp_tenants','erp_plans','erp_resellers'] as $t){Schema::connection('mysql')->dropIfExists($t);}}
};
