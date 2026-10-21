<?php
namespace App\Models\ERP\Tenant;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

final class TenantUser extends Authenticatable
{
    use Notifiable;
    protected $connection = 'erp_tenant';
    protected $table = 'users';
    protected $fillable=['name','email','password','locale','active'];
    protected $hidden=['password','remember_token'];
    protected $casts=['password'=>'hashed','active'=>'boolean'];
    public function roles() { return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id'); }
    public function allowed(string $permission): bool
    {
        return $this->roles()->whereHas('permissions', fn($q)=>$q->where('name',$permission))->exists();
    }
}
