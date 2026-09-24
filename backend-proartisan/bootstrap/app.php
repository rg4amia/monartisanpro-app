<?php

use App\Http\Middleware\AccountActive;
use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\KycVerified;
use App\Http\Middleware\SanitizeRequests;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\SupplierOnly;
use App\Http\Middleware\VerifyGatewayRequest;
use App\Http\Middleware\VerifySmsproWebhook;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append([
            SanitizeRequests::class,
            SecurityHeadersMiddleware::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.login');
            }

            return route('home');
        });

        $middleware->redirectUsersTo(function (Request $request): string {
            $user = $request->user();
            if ($user && $user->role === 'fournisseur') {
                return route('supplier.dashboard');
            }

            return route('admin.dashboard');
        });

        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'kyc.verified' => KycVerified::class,
            'admin.only' => AdminOnly::class,
            'account.active' => AccountActive::class,
            'supplier.only' => SupplierOnly::class,
            'gateway.verified' => VerifyGatewayRequest::class,
            'smspro.signed' => VerifySmsproWebhook::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'admin/api/llm/*',
            'api/v1/*',
            'supplier/*',
            'admin/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié.',
                ], 401);
            }

            if ($request->is('admin') || $request->is('admin/*')) {
                return redirect()->route('admin.login');
            }

            return redirect('/');
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données invalides.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ressource introuvable.',
                ], 404);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Action non autorisée.',
                ], 403);
            }
        });
    })->create();
