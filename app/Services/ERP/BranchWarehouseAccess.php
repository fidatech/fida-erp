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
        return in_array($warehouseId, $this->allowedWarehouseIds(), true);
    }
}
