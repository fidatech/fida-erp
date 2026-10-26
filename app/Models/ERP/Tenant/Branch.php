<?php
namespace App\Models\ERP\Tenant;

final class Branch extends ErpModel
{
    protected $table = 'branches';

    public function users()
    {
        return $this->belongsToMany(TenantUser::class, 'branch_user', 'branch_id', 'user_id')
            ->withTimestamps();
    }
}
