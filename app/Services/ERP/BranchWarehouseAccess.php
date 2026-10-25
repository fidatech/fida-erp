<?php

namespace App\Services\ERP;

use Illuminate\Support\Facades\DB;

final class BranchWarehouseAccess
{
    /** @return list<int> */
    public function allowedWarehouseIds(): array
    {
        $user = auth('erp_tenant')->user();

        if (!$user || !$user->active) {
            return [];
        }

        return DB::connection('erp_tenant')->table('warehouses as w')
            ->join('branch_user as bu', 'bu.branch_id', '=', 'w.branch_id')
            ->join('branches as b', 'b.id', '=', 'w.branch_id')
            ->where('bu.user_id', $user->getAuthIdentifier())
            ->where('b.active', true)
            ->pluck('w.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function allows(int $warehouseId): bool
    {
        $user = auth('erp_tenant')->user();

        if ($warehouseId <= 0 || !$user || !$user->active) {
            return false;
        }

        // Direct existence check avoids loading every permitted warehouse
        // when authorizing a single sales, stock or PDF request.
        return DB::connection('erp_tenant')->table('warehouses as w')
            ->join('branch_user as bu', 'bu.branch_id', '=', 'w.branch_id')
            ->join('branches as b', 'b.id', '=', 'w.branch_id')
            ->where('w.id', $warehouseId)
            ->where('bu.user_id', $user->getAuthIdentifier())
            ->where('b.active', true)
            ->exists();
    }
}
