<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth:api' => \App\Http\Middleware\Auth\AuthenticateAPI::class,
            'backoffice.auth' => \App\Http\Middleware\BackofficeAuth::class,
            'role' => \App\Http\Middleware\Role\RoleBasedAccess::class,
            'rate.limit' => \App\Http\Middleware\RateLimitMiddleware::class,
            'kyc.required' => \App\Http\Middleware\KYC\RequireKYCVerification::class,
            'fraud.detection' => \App\Http\Middleware\Security\FraudDetectionMiddleware::class,
            'locale' => \App\Http\Middleware\LocaleMiddleware::class,
            'error.formatter' => \App\Http\Middleware\ErrorResponseFormatter::class,
            'monitoring' => \App\Http\Middleware\MonitoringMiddleware::class,
        ]);

        // Apply monitoring, rate limiting, locale detection, and error formatting to all API routes
        $middleware->group('api', [
            \App\Http\Middleware\MonitoringMiddleware::class,
            \App\Http\Middleware\RateLimitMiddleware::class,
            \App\Http\Middleware\LocaleMiddleware::class,
            \App\Http\Middleware\ErrorResponseFormatter::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Force API routes to use our custom exception handler
        $exceptions->render(function (Throwable $e, $request) {
            // Only handle API routes
            if ($request->is('api/*') || $request->expectsJson()) {
                $handler = new \App\Exceptions\Handler(app());

                return $handler->render($request, $e);
            }

            return null; // Let Laravel handle non-API routes normally
        });
    })->create();
