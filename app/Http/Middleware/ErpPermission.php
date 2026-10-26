<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
final class ErpPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = auth('erp_tenant')->user();
        abort_unless(
            $user && (bool) $user->getAttribute('active') && $user->allowed($permission),
            403
        );
        return $next($request);
    }
}
