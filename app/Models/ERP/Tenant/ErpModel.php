<?php
namespace App\Models\ERP\Tenant;
use Illuminate\Database\Eloquent\Model;
abstract class ErpModel extends Model
{
    protected $connection = 'erp_tenant';
    protected $guarded = [];
}
