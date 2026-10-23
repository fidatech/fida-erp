<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToPriorityList(\Illuminate\Auth\Middleware\Authenticate::class, \App\Http\Middleware\ResolveErpTenant::class);
        $middleware->redirectGuestsTo(function (Request $request) {
            return $request->is('erp') || $request->is('erp/*')
                ? route('erp.login') : route('erp.platform.login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

// cPanel hosts the Laravel public directory outside the application root.
// Keep the default Laravel public directory for local development and CI.
$publicPath = $_SERVER['FIDAERP_PUBLIC_PATH'] ?? $_ENV['FIDAERP_PUBLIC_PATH'] ?? getenv('FIDAERP_PUBLIC_PATH');
if (is_string($publicPath) && $publicPath !== '') {
    $app->usePublicPath($publicPath);
} elseif (dirname(__DIR__) === '/home/fidaerp/staging/application' && is_dir(dirname(__DIR__).'/../public')) {
    $app->usePublicPath(dirname(__DIR__).'/../public');
} elseif (is_dir(dirname(__DIR__).'/../public_html')) {
    $app->usePublicPath(dirname(__DIR__).'/../public_html');
}

return $app;
