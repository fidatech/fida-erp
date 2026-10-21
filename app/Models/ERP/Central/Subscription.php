<?php
namespace App\Models\ERP\Central;
use Illuminate\Database\Eloquent\Model;
final class Subscription extends Model
{
    protected $connection='mysql'; protected $table='erp_subscriptions'; protected $guarded=[];
    protected $casts=['starts_at'=>'datetime','ends_at'=>'datetime'];
}
