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
        $priorGuard=Auth::getDefaultDriver();
        $this->connector->connect($tenant);
        Auth::forgetGuards();
        // Sessions are scoped to a tenant even if a browser or reverse proxy
        // incorrectly sends the same session cookie to multiple tenant hosts.
        // This middleware runs after the web session middleware.
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
            }
            $session->put('erp.bound_tenant_id', (string) $tenant->getKey());
        }
        try { return $next($request); }
        finally { Auth::forgetGuards(); Auth::shouldUse($priorGuard); $this->connector->disconnect(); }
    }
}
