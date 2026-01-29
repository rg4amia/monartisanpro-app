<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\KYCController;
use App\Http\Controllers\Api\V1\Dispute\DisputeController;
use App\Http\Controllers\Api\V1\Documentation\OpenApiController;
use App\Http\Controllers\Api\V1\Financial\EscrowController;
use App\Http\Controllers\Api\V1\Financial\JetonController;
use App\Http\Controllers\Api\V1\Financial\TransactionController;
use App\Http\Controllers\Api\V1\GPSValidationController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Marketplace\ArtisanController;
use App\Http\Controllers\Api\V1\Marketplace\MissionController;
use App\Http\Controllers\Api\V1\Marketplace\QuoteController;
use App\Http\Controllers\Api\V1\Reputation\ReputationController;
use App\Http\Controllers\Api\V1\StaticDataController;
use App\Http\Controllers\Api\V1\Worksite\ChantierController;
use App\Http\Controllers\Api\V1\Worksite\JalonController;
use App\Http\Controllers\SecureFileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Secure file serving (outside versioned API)
Route::get('/secure-file', [SecureFileController::class, 'serve'])->name('secure-file.serve');

// API Version 1
Route::prefix('v1')->group(function () {

    // Health check routes (public)
    Route::prefix('health')->group(function () {
        Route::get('/', [HealthController::class, 'health']);
        Route::get('/detailed', [HealthController::class, 'detailed']);
        Route::get('/metrics', [HealthController::class, 'metrics']);
        Route::get('/metrics/{name}', [HealthController::class, 'metric']);
        Route::delete('/metrics', [HealthController::class, 'clearMetrics']);
    });

    // API Documentation routes (public)
    Route::prefix('docs')->group(function () {
        Route::get('/spec', [OpenApiController::class, 'getSpec']);
        Route::get('/', [OpenApiController::class, 'getSwaggerUI']);
    });

    // Static data routes (public - cached)
    Route::prefix('static')->group(function () {
        Route::get('/trade-categories', [StaticDataController::class, 'tradeCategories']);
        Route::get('/mission-statuses', [StaticDataController::class, 'missionStatuses']);
        Route::get('/devis-statuses', [StaticDataController::class, 'devisStatuses']);
        Route::get('/all', [StaticDataController::class, 'all']);
    });

    // Reference data routes (public)
    Route::prefix('reference')->group(function () {
        Route::get('/trades', [\App\Http\Controllers\Api\V1\ReferenceDataController::class, 'index']);
    });

    // Parameters API routes
    Route::get('/parameters/public', [App\Http\Controllers\Api\ParametersController::class, 'public']);
    Route::get('/parameters/category/{category}', [App\Http\Controllers\Api\ParametersController::class, 'byCategory']);

    // Authentication routes (public)
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/otp/generate', [AuthController::class, 'generateOTP']);
        Route::post('/otp/verify', [AuthController::class, 'verifyOTP']);

        // Protected auth routes
        Route::middleware(['auth:api'])->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/refresh', [AuthController::class, 'refresh']);
        });
    });

    // Protected routes (require authentication)
    Route::middleware(['auth:api'])->group(function () {

        // KYC document upload
        Route::post('/users/{id}/kyc', [KYCController::class, 'uploadKYC']);

        // Marketplace routes
        Route::prefix('missions')->group(function () {
            Route::get('/', [MissionController::class, 'index']);
            Route::post('/', [MissionController::class, 'store'])->middleware('role:CLIENT');
            Route::get('/{id}', [MissionController::class, 'show']);
            Route::post('/{missionId}/quotes', [QuoteController::class, 'store'])->middleware(['kyc.required', 'role:ARTISAN']);
            Route::get('/{missionId}/quotes', [QuoteController::class, 'index']);
        });

        Route::prefix('quotes')->group(function () {
            Route::post('/{id}/accept', [QuoteController::class, 'accept'])->middleware('role:CLIENT');
        });

        Route::prefix('artisans')->group(function () {
            Route::get('/search', [ArtisanController::class, 'search']);
        });

        // Financial transaction routes
        Route::prefix('escrow')->group(function () {
            Route::post('/block', [EscrowController::class, 'block'])->middleware(['fraud.detection', 'role:CLIENT']);
        });

        Route::prefix('jetons')->group(function () {
            Route::post('/generate', [JetonController::class, 'generate'])->middleware(['fraud.detection', 'kyc.required', 'role:ARTISAN']);
            Route::post('/validate', [JetonController::class, 'validate'])->middleware(['fraud.detection', 'kyc.required', 'role:FOURNISSEUR']);
        });

        Route::prefix('transactions')->group(function () {
            Route::get('/', [TransactionController::class, 'index']);
        });

        // Worksite management routes
        Route::prefix('chantiers')->group(function () {
            Route::get('/', [ChantierController::class, 'index']);
            Route::post('/', [ChantierController::class, 'store'])->middleware(['kyc.required', 'role:ARTISAN']);
            Route::get('/{id}', [ChantierController::class, 'show']);
        });

        Route::prefix('jalons')->group(function () {
            Route::get('/{id}', [JalonController::class, 'show']);
            Route::post('/{id}/submit-proof', [JalonController::class, 'submitProof'])->middleware(['kyc.required', 'role:ARTISAN']);
            Route::post('/{id}/validate', [JalonController::class, 'validate'])->middleware('role:CLIENT');
            Route::post('/{id}/contest', [JalonController::class, 'contest'])->middleware('role:CLIENT');
        });

        // Reputation management routes
        Route::prefix('artisans')->group(function () {
            Route::get('/{id}/reputation', [ReputationController::class, 'getArtisanReputation']);
            Route::get('/{id}/score-history', [ReputationController::class, 'getScoreHistory']);
            Route::get('/{id}/ratings', [ReputationController::class, 'getArtisanRatings']);
        });

        Route::prefix('missions')->group(function () {
            Route::post('/{id}/rate', [ReputationController::class, 'submitRating'])->middleware('role:CLIENT');
        });

        // Dispute resolution routes
        Route::prefix('disputes')->group(function () {
            Route::get('/', [DisputeController::class, 'index']);
            Route::post('/', [DisputeController::class, 'store']);
            Route::get('/{id}', [DisputeController::class, 'show']);
            Route::post('/{id}/mediation/start', [DisputeController::class, 'startMediation']);
            Route::post('/{id}/mediation/message', [DisputeController::class, 'sendMediationMessage']);
            Route::post('/{id}/arbitration/render', [DisputeController::class, 'renderArbitration'])->middleware('role:ADMIN,REFERENT_ZONE');
        });

        // Admin dispute management routes
        Route::prefix('admin/disputes')->group(function () {
            Route::get('/', [DisputeController::class, 'adminIndex'])->middleware('role:ADMIN');
        });

        // GPS validation routes
        Route::prefix('gps')->group(function () {
            Route::post('/validate-proximity', [GPSValidationController::class, 'validateProximity']);
            Route::post('/verify-otp', [GPSValidationController::class, 'verifyOTP']);
            Route::post('/calculate-distance', [GPSValidationController::class, 'calculateDistance']);
            Route::post('/generate-otp', [GPSValidationController::class, 'generateOTP']);
        });
    });

    // Mobile Money Webhook routes (public - no auth required)
    Route::prefix('payments')->group(function () {
        Route::post('/webhook', [\App\Http\Controllers\Api\V1\Payment\WebhookController::class, 'handleGeneric']);
        Route::prefix('webhook')->group(function () {
            Route::post('/wave', [\App\Http\Controllers\Api\V1\Payment\WebhookController::class, 'handleWave']);
            Route::post('/orange', [\App\Http\Controllers\Api\V1\Payment\WebhookController::class, 'handleOrangeMoney']);
            Route::post('/mtn', [\App\Http\Controllers\Api\V1\Payment\WebhookController::class, 'handleMTN']);
        });
    });
});
