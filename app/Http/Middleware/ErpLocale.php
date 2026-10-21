<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
final class ErpLocale
{
 public function handle(Request $request,Closure $next): Response
 {
  $locale=auth('erp_tenant')->user()?->locale??'en';
  app()->setLocale(in_array($locale,['en','fa','ps'],true)?$locale:'en');
  return $next($request);
 }
}
