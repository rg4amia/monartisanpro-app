<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        App\Providers\RouteServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Configure API middleware to exclude CSRF
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Replace default CSRF middleware with our custom one
        $middleware->web(replace: [
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class => \App\Http\Middleware\VerifyCsrfToken::class,
        ]);

        // Register custom middleware aliases
        $middleware->alias([
            'backoffice.auth' => \App\Http\Middleware\BackofficeAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
