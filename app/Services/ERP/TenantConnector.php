<?php
namespace App\Services\ERP;

use App\Models\ERP\Central\Tenant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class TenantConnector
{
    public function connect(Tenant $tenant): void
    {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/D', $tenant->database_name)) {
            throw new InvalidArgumentException('Invalid tenant database identifier');
        }
        if (!preg_match('/^[a-zA-Z0-9_]{1,32}$/D', $tenant->database_username)) {
            throw new InvalidArgumentException('Invalid tenant database username');
        }
        $source = config('database.connections.mysql');
        if (!is_array($source) || ($source['driver'] ?? '') !== 'mysql') {
            throw new InvalidArgumentException('MySQL central connection required');
        }
        config(['database.connections.erp_tenant' => array_replace($source,['database'=>$tenant->database_name,'username'=>$tenant->database_username,'password'=>$tenant->database_password])]);
        DB::purge('erp_tenant');
        app()->instance('erp.current_tenant',$tenant);
    }

    public function disconnect(): void
    {
        try {
            DB::disconnect('erp_tenant');
        } finally {
            // Purge even if disconnect throws, so a reused worker never
            // retains the previous tenant's configured credentials.
            try {
                DB::purge('erp_tenant');
            } finally {
                config()->offsetUnset('database.connections.erp_tenant');
                app()->forgetInstance('erp.current_tenant');
            }
        }
    }
}
