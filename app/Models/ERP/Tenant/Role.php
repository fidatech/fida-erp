<?php
namespace App\Models\ERP\Tenant;
final class Role extends ErpModel
{
    protected $table='roles';
    public function permissions(){ return $this->belongsToMany(Permission::class,'permission_role','role_id','permission_id'); }
}
