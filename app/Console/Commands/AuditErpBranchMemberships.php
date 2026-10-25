<?php

namespace App\Console\Commands;

use App\Models\ERP\Central\Tenant;
use App\Services\ERP\TenantConnector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Read-only audit: legacy users without branch assignments are denied
 * warehouse access by design. This command never grants permissions.
 */
final class AuditErpBranchMemberships extends Command
{
    protected $signature = 'erp:audit-branch-memberships {tenant-id : Central tenant ID}';
    protected $description = 'Report active users without any active branch assignment (read-only)';

    public function handle(TenantConnector $connector): int
    {
        $tenantId = filter_var($this->argument('tenant-id'), FILTER_VALIDATE_INT);
        if ($tenantId === false || $tenantId <= 0) {
            $this->error('A positive tenant ID is required.');
            return self::FAILURE;
        }

        try {
            $result = $connector->withinActiveTenant($tenantId, function (): array {
                $db = DB::connection('erp_tenant');
                $activeUsers = $db->table('users')->where('active', true)->count();
                $missing = $db->table('users as u')
                    ->where('u.active', true)
                    ->whereNotExists(function ($query): void {
                        $query->selectRaw('1')
                            ->from('branch_user as bu')
                            ->join('branches as b', 'b.id', '=', 'bu.branch_id')
                            ->whereColumn('bu.user_id', 'u.id')
                            ->where('b.active', true);
                    })->count();

                return ['active' => $activeUsers, 'missing' => $missing];
            });
            $this->table(['Check', 'Count'], [
                ['Active tenant users', $result['active']],
                ['Without active branch membership', $result['missing']],
            ]);
            if ($result['missing'] > 0) {
                $this->warn('Manual authorized branch assignments are required; no access was granted.');
            }
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Audit unavailable for this tenant; verify status, schema and connection.');
            return self::FAILURE;
        }
    }
}
