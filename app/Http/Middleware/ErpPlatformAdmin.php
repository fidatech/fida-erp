<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
final class ErpPlatformAdmin
{
 public function handle(Request $request,Closure $next): Response
 {
  abort_unless(strtolower($request->getHost()) === strtolower(config('erp.platform_host')), 404);
  abort_unless($request->user() && (bool)$request->user()->getAttribute('erp_platform_admin'), 403);
  return $next($request);
 }
}
