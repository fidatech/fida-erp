<?php
namespace App\Providers;

use App\Http\Middleware\ResolveErpTenant;
use App\Console\Commands\ProvisionErpTenant;
use App\Console\Commands\PromoteErpPlatformAdmin;
use App\Console\Commands\CreateErpPlatformUser;
use App\Models\ERP\Tenant\TenantUser;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Event;
use Illuminate\Queue\Events\JobExceptionOccurred;
use App\Services\ERP\TenantConnector;

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
        // Long-lived queue workers must never retain tenant PDO connections,
        // authentication guards or tenant context between successive jobs.
        Queue::before(function (): void {
            // Every attempt, including retries, begins without the previous
            // job's tenant connection or cached authentication guards.
            app(TenantConnector::class)->resetWorkerContext();
            app('auth')->forgetGuards();
        });
        Queue::after(function (): void {
            app(TenantConnector::class)->resetWorkerContext();
            app('auth')->forgetGuards();
        });
        Queue::failing(function (): void {
            app(TenantConnector::class)->resetWorkerContext();
            app('auth')->forgetGuards();
        });
        // Retryable exceptions are not necessarily permanently failed jobs.
        // Clean up immediately on every thrown job exception, before retry.
        Event::listen(JobExceptionOccurred::class, function (): void {
            app(TenantConnector::class)->resetWorkerContext();
            app('auth')->forgetGuards();
        });
        $this->loadViewsFrom(resource_path('views/erp'), 'erp');
        $this->loadTranslationsFrom(resource_path('lang'), 'erp');
    }
}
