<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        $s = Schema::connection('erp_tenant');
        $s->create('company_settings',function(Blueprint $t){$t->id();$t->string('name');$t->char('base_currency',3)->default('AFN');$t->string('locale',5)->default('en');$t->string('timezone')->default('Asia/Kabul');$t->timestamps();});
        $s->create('branches',function(Blueprint $t){$t->id();$t->string('code')->unique();$t->string('name');$t->boolean('active')->default(true);$t->timestamps();});
        $s->create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('email')->unique();$t->timestamp('email_verified_at')->nullable();$t->string('password');$t->string('locale',5)->default('en');$t->boolean('active')->default(true);$t->rememberToken();$t->timestamps();});
        $s->create('roles',function(Blueprint $t){$t->id();$t->string('name')->unique();$t->timestamps();});
        $s->create('permissions',function(Blueprint $t){$t->id();$t->string('name')->unique();});
        $s->create('role_user',function(Blueprint $t){$t->foreignId('role_id')->constrained('roles')->cascadeOnDelete();$t->foreignId('user_id')->constrained('users')->cascadeOnDelete();$t->primary(['role_id','user_id']);});
        $s->create('permission_role',function(Blueprint $t){$t->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();$t->foreignId('role_id')->constrained('roles')->cascadeOnDelete();$t->primary(['permission_id','role_id']);});
        $s->create('currencies',function(Blueprint $t){$t->char('code',3)->primary();$t->string('name');$t->unsignedTinyInteger('minor_units')->default(2);$t->boolean('active')->default(true);});
        $s->create('exchange_rates',function(Blueprint $t){$t->id();$t->char('from_currency',3);$t->char('to_currency',3);$t->decimal('rate',24,12);$t->string('source',50)->default('manual');$t->timestamp('valid_at');$t->foreignId('created_by')->nullable();$t->timestamps();$t->index(['from_currency','to_currency','valid_at'],'fx_pair_date_idx');});
        $s->create('accounts',function(Blueprint $t){$t->id();$t->string('code',40)->unique();$t->string('name');$t->string('type',20)->index();$t->foreignId('parent_id')->nullable()->constrained('accounts');$t->boolean('is_postable')->default(true);$t->boolean('active')->default(true);$t->timestamps();});
        $s->create('fiscal_periods',function(Blueprint $t){$t->id();$t->string('name');$t->date('starts_on');$t->date('ends_on');$t->boolean('is_closed')->default(false);$t->timestamps();});
        $s->create('journals',function(Blueprint $t){$t->id();$t->string('reference')->unique();$t->date('date')->index();$t->string('source_type',40);$t->unsignedBigInteger('source_id');$t->string('description')->nullable();$t->foreignId('created_by')->nullable();$t->timestamps();$t->unique(['source_type','source_id']);});
        $s->create('journal_lines',function(Blueprint $t){$t->id();$t->foreignId('journal_id')->constrained('journals');$t->foreignId('account_id')->constrained('accounts');$t->char('currency_code',3);$t->decimal('currency_amount',24,6);$t->decimal('fx_rate',24,12);$t->decimal('base_debit',24,6)->default(0);$t->decimal('base_credit',24,6)->default(0);$t->timestamps();$t->index(['account_id','journal_id']);});
        $s->create('audit_logs',function(Blueprint $t){$t->id();$t->foreignId('user_id')->nullable();$t->string('event',80);$t->string('entity_type',60);$t->unsignedBigInteger('entity_id');$t->json('changes')->nullable();$t->timestamps();$t->index(['entity_type','entity_id']);});
    }
    public function down(): void {foreach(['audit_logs','journal_lines','journals','fiscal_periods','accounts','exchange_rates','currencies','permission_role','role_user','permissions','roles','users','branches','company_settings'] as $t){Schema::connection('erp_tenant')->dropIfExists($t);}}
};
