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
        //
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
} elseif (is_dir(dirname(__DIR__).'/../staging/public') && dirname(__DIR__) === '/home/fidaerp/staging/application') {
    $app->usePublicPath('/home/fidaerp/staging/public');
} elseif (is_dir(dirname(__DIR__).'/../public_html')) {
    $app->usePublicPath(dirname(__DIR__).'/../public_html');
}

return $app;
