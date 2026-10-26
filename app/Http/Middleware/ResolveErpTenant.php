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
        // Sessions are scoped to a tenant even if a browser or reverse proxy
        // incorrectly sends the same session cookie to multiple tenant hosts.
        // This middleware runs after the web session middleware.
        try {
        if ($request->hasSession()) {
            $session = $request->session();
            $boundTenant = $session->get('erp.bound_tenant_id');
            if ($boundTenant !== null && (string) $boundTenant !== (string) $tenant->getKey()) {
                // The guard may already have resolved an identically numbered
                // user from another tenant during a previous request.
                Auth::guard('erp_tenant')->logout();
                Auth::forgetGuards();
                $session->invalidate();
                $session->regenerateToken();
                abort(401, 'Session belongs to a different tenant.');
            }
            $session->put('erp.bound_tenant_id', (string) $tenant->getKey());
        }
        // A previously authenticated account may have been suspended since
        // login. Recheck its status on every tenant request.
        $tenantGuard = Auth::guard('erp_tenant');
        $user = $tenantGuard->user();
        if ($user && ! (bool) $user->getAttribute('active')) {
            $tenantGuard->logout();
            Auth::forgetGuards();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            abort(401, 'Tenant user account inactive.');
        }
        return $next($request);
        } finally { Auth::forgetGuards(); Auth::shouldUse($priorGuard); $this->connector->disconnect(); }
    }
}
