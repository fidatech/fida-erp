<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Isolate login budgets by host and account; do not place plaintext
        // email addresses in shared cache keys or couple different tenants.
        RateLimiter::for('erp-tenant-login', function (Request $request) {
            $host = strtolower($request->getHost());
            $email = strtolower(trim((string) $request->input('email', '')));
            $identity = hash_hmac('sha256', $host.'|'.$email, (string) config('app.key'));
            return [
                Limit::perMinute(12)->by('erp-tenant-ip:'.$host.'|'.$request->ip()),
                Limit::perMinute(6)->by('erp-tenant-account:'.$identity),
            ];
        });

        RateLimiter::for('erp-platform-login', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email', '')));
            $identity = hash_hmac('sha256', $email, (string) config('app.key'));
            return [
                Limit::perMinute(10)->by('erp-platform-ip:'.$request->ip()),
                Limit::perMinute(5)->by('erp-platform-account:'.$identity),
            ];
        });
    }
}
