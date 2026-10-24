<?php

namespace Tests\Feature\ERP;

use App\Models\ERP\Central\Tenant;
use App\Services\ERP\TenantConnector;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

final class TenantSecurityBaselineTest extends TestCase
{
    public function test_tenant_auth_guard_is_registered_separately_from_platform_guard(): void
    {
        $this->assertSame('session', config('auth.guards.erp_tenant.driver'));
        $this->assertSame('erp_tenant_users', config('auth.guards.erp_tenant.provider'));
        $this->assertSame('web', config('auth.defaults.guard'));
        $this->assertNotSame(config('auth.guards.web.provider'), config('auth.guards.erp_tenant.provider'));
    }

    public function test_tenant_connection_rejects_invalid_database_identifiers_before_connecting(): void
    {
        $tenant = new Tenant();
        $tenant->database_name = 'bad-db-name';
        $tenant->database_username = 'valid_user';
        $tenant->database_password = 'placeholder';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid tenant database identifier');

        app(TenantConnector::class)->connect($tenant);
    }

    public function test_tenant_connection_rejects_invalid_database_usernames_before_connecting(): void
    {
        $tenant = new Tenant();
        $tenant->database_name = 'valid_database';
        $tenant->database_username = 'bad-user';
        $tenant->database_password = 'placeholder';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid tenant database username');

        app(TenantConnector::class)->connect($tenant);
    }

    public function test_tenant_disconnect_clears_request_scoped_tenant_context(): void
    {
        app()->instance('erp.current_tenant', new \stdClass());

        app(TenantConnector::class)->disconnect();

        $this->assertFalse(app()->bound('erp.current_tenant'));
    }

    public function test_tenant_routes_require_tenant_resolution_middleware(): void
    {
        $routes = app('router')->getRoutes();
        foreach (['erp.login', 'erp.dashboard', 'erp.customers.index', 'erp.sales.index'] as $name) {
            $route = $routes->getByName($name);
            $this->assertNotNull($route, "Missing route {$name}");
            $this->assertContains('erp.tenant', $route->gatherMiddleware());
        }
    }
}
