<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
final class ErpPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        abort_unless(auth('erp_tenant')->check() && auth('erp_tenant')->user()->allowed($permission), 403);
        return $next($request);
    }
}
