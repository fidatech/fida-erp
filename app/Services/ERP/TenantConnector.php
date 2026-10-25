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
        // A central DATABASE_URL/DB_URL can override explicit database and
        // credentials in Laravel's MySQL connector. Never inherit that DSN
        // when constructing an isolated tenant connection.
        unset($source['url']);
        config(['database.connections.erp_tenant' => array_replace($source,['database'=>$tenant->database_name,'username'=>$tenant->database_username,'password'=>$tenant->database_password])]);
        try {
            DB::purge('erp_tenant');
            app()->instance('erp.current_tenant', $tenant);
        } catch (\Throwable $e) {
            config()->offsetUnset('database.connections.erp_tenant');
            app()->forgetInstance('erp.current_tenant');
            throw $e;
        }
    }

    /**
     * Clear all tenant connection configuration at a background-job boundary.
     * HTTP requests use disconnect() because session teardown may still need
     * the connection definition after middleware has returned.
     */
    public function resetWorkerContext(): void
    {
        try {
            $this->disconnect();
        } finally {
            config()->offsetUnset('database.connections.erp_tenant');
            app()->forgetInstance('erp.current_tenant');
        }
    }

    /**
     * Run tenant-specific background work with an explicit connection scope.
     * Never reuse an implicit tenant from a prior command or queue job.
     */
    public function withinTenant(Tenant $tenant, callable $callback): mixed
    {
        $this->resetWorkerContext();
        try {
            $this->connect($tenant);
            return $callback();
        } finally {
            $this->resetWorkerContext();
        }
    }

    /**
     * Public entry point for tenant background work. Resolve the tenant from
     * the central database at execution time, not from stale queued payloads.
     */
    public function withinActiveTenant(int $tenantId, callable $callback): mixed
    {
        $this->resetWorkerContext();
        try {
            $tenant = Tenant::query()->find($tenantId);
            if (! $tenant || $tenant->status !== 'active' || $tenant->suspended_at !== null) {
                throw new \Illuminate\Auth\Access\AuthorizationException('Tenant is inactive or suspended.');
            }
            return $this->withinTenant($tenant, $callback);
        } finally {
            $this->resetWorkerContext();
        }
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
                // Keep the connection definition available until Laravel has
                // finished the request/session lifecycle; purge removes the
                // active PDO connection and prevents cross-tenant reuse.
                app()->forgetInstance('erp.current_tenant');
            }
        }
    }
}
