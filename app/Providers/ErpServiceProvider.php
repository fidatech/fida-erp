<?php
namespace App\Providers;

use App\Http\Middleware\ResolveErpTenant;
use App\Console\Commands\ProvisionErpTenant;
use App\Console\Commands\PromoteErpPlatformAdmin;
use App\Console\Commands\CreateErpPlatformUser;
use App\Models\ERP\Tenant\TenantUser;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

final class ErpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('config/erp.php'), 'erp');
    }

    public function boot(): void
    {
        // A separate guard prevents a platform user session from authorizing a tenant request.
        config(['auth.guards.erp_tenant' => ['driver' => 'session', 'provider' => 'erp_tenant_users'],
                'auth.providers.erp_tenant_users' => ['driver' => 'eloquent', 'model' => TenantUser::class]]);
        if ($this->app->runningInConsole()) $this->commands([ProvisionErpTenant::class, PromoteErpPlatformAdmin::class, CreateErpPlatformUser::class]);
        Route::aliasMiddleware('erp.tenant', ResolveErpTenant::class);
        $this->loadViewsFrom(resource_path('views/erp'), 'erp');
        $this->loadTranslationsFrom(resource_path('lang'), 'erp');
    }
}
