<?php
namespace App\Models\ERP\Central;

use Illuminate\Database\Eloquent\Model;

final class Tenant extends Model
{
    protected $connection = 'mysql';
    protected $table = 'erp_tenants';
    protected $guarded = [];
    protected $casts = ['trial_ends_at' => 'datetime', 'suspended_at' => 'datetime', 'database_password' => 'encrypted'];
    public function subscriptions() { return $this->hasMany(Subscription::class, 'tenant_id'); }
}
