<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ErpBranchAccess
{
    public function handle(Request $request, Closure $next, string $parameter = 'branch_id'): Response
    {
        $user = auth('erp_tenant')->user();
        $branchId = $request->route($parameter) ?? $request->input($parameter);

        abort_unless($user && (bool) $user->getAttribute('active'), 403);
        abort_unless(
            is_scalar($branchId) && ctype_digit((string) $branchId)
                && $user->canAccessBranch((int) $branchId),
            403
        );

        return $next($request);
    }
}
