<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth:api' => \App\Http\Middleware\Auth\AuthenticateAPI::class,
            'backoffice.auth' => \App\Http\Middleware\BackofficeAuth::class,
            'role' => \App\Http\Middleware\Role\RoleBasedAccess::class,
            'rate.limit' => \App\Http\Middleware\RateLimitMiddleware::class,
            'kyc.required' => \App\Http\Middleware\KYC\RequireKYCVerification::class,
        ]);

        // Apply rate limiting to all API routes
        $middleware->group('api', [
            \App\Http\Middleware\RateLimitMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
