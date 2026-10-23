<?php
namespace App\Http\Middleware;

use App\Models\ERP\Central\Tenant;
use App\Services\ERP\TenantConnector;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveErpTenant
{
    public function __construct(private readonly TenantConnector $connector) {}
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Tenant::query()->where('domain', strtolower($request->getHost()))->first();
        abort_unless($tenant && $tenant->status === 'active' && is_null($tenant->suspended_at), 404);
        if ($tenant->trial_ends_at && $tenant->trial_ends_at->isPast()) {
            $hasPaid = $tenant->subscriptions()->where('status','active')->where(function ($q) {$q->whereNull('ends_at')->orWhere('ends_at','>',now());})->exists();
            abort_unless($hasPaid, 403, 'Your free trial has expired. Please contact FidaERP support to renew.');
        }
        $priorGuard=Auth::getDefaultDriver();
        $this->connector->connect($tenant);
        Auth::forgetGuards();
        try { return $next($request); }
        finally { Auth::forgetGuards(); Auth::shouldUse($priorGuard); $this->connector->disconnect(); }
    }
}
