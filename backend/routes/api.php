<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DisputeController;
use App\Http\Controllers\Api\V1\KycController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\MilestoneController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\QuoteController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\ScoreController;
use App\Http\Controllers\Api\V1\TokenController;
use Illuminate\Http\Request;
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

// API Version 1
Route::prefix('v1')->group(function () {
    // Public routes - Authentication
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/send-otp', [AuthController::class, 'sendPhoneOtp']);
    Route::post('/auth/verify-otp', [AuthController::class, 'verifyPhoneOtp']);

    // Public routes - Sectors and Trades
    Route::get('/sectors', [\App\Http\Controllers\Api\V1\TradeController::class, 'getSectors']);
    Route::get('/sectors/{sectorId}/trades', [\App\Http\Controllers\Api\V1\TradeController::class, 'getTradesBySector']);
    Route::get('/trades', [\App\Http\Controllers\Api\V1\TradeController::class, 'getAllTrades']);

    // Public artisan search
    Route::post('/artisans/search', [\App\Http\Controllers\Api\V1\TradeController::class, 'searchArtisans']);

    // Public payment webhook (CinetPay callback)
    Route::post('/payments/webhook', [PaymentController::class, 'webhook']);

    // Protected routes (require Sanctum authentication)
    Route::middleware('auth:sanctum')->group(function () {
        // Authentication
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::put('/auth/profile', [AuthController::class, 'updateProfile']);

        // KYC
        Route::post('/kyc/upload', [KycController::class, 'uploadDocuments']);
        Route::get('/kyc/status', [KycController::class, 'getStatus']);
        Route::get('/kyc/documents', [KycController::class, 'getDocuments']);

        // Projects (Phase 3)
        Route::get('/projects/my', [ProjectController::class, 'myProjects']);
        Route::get('/projects/search/location', [ProjectController::class, 'search']);
        Route::apiResource('projects', ProjectController::class);

        // Quotes (Phase 3)
        Route::apiResource('quotes', QuoteController::class);
        Route::post('/quotes/{id}/send', [QuoteController::class, 'send']);
        Route::post('/quotes/{id}/accept', [QuoteController::class, 'accept']);
        Route::post('/quotes/{id}/reject', [QuoteController::class, 'reject']);

        // Payments & Escrow (Phase 3)
        Route::post('/payments/initialize', [PaymentController::class, 'initialize']);
        Route::get('/payments/verify/{transactionRef}', [PaymentController::class, 'verify']);

        // Material Tokens (Phase 4)
        Route::get('/tokens', [TokenController::class, 'index']);
        Route::get('/tokens/{code}', [TokenController::class, 'show']);
        Route::post('/tokens/validate', [TokenController::class, 'validate']);
        Route::post('/tokens/redeem', [TokenController::class, 'redeem']);
        Route::get('/tokens/{code}/redemptions', [TokenController::class, 'redemptions']);

        // Milestones (Phase 5)
        Route::get('/milestones', [MilestoneController::class, 'index']);
        Route::post('/milestones', [MilestoneController::class, 'store']);
        Route::get('/milestones/{id}', [MilestoneController::class, 'show']);
        Route::put('/milestones/{id}', [MilestoneController::class, 'update']);
        Route::delete('/milestones/{id}', [MilestoneController::class, 'destroy']);
        Route::post('/milestones/{id}/complete', [MilestoneController::class, 'complete']);
        Route::post('/milestones/{id}/send-otp', [MilestoneController::class, 'sendOtp']);
        Route::post('/milestones/{id}/validate', [MilestoneController::class, 'validate']);

        // N'Zassa Scoring (Phase 5)
        Route::get('/scores/{artisanId}', [ScoreController::class, 'show']);
        Route::post('/scores/{artisanId}/calculate', [ScoreController::class, 'calculate']);
        Route::get('/scores/{artisanId}/history', [ScoreController::class, 'history']);

        // Reviews (Phase 5)
        Route::get('/reviews', [ReviewController::class, 'index']);
        Route::post('/reviews', [ReviewController::class, 'store']);
        Route::get('/reviews/{id}', [ReviewController::class, 'show']);
        Route::post('/reviews/{id}/respond', [ReviewController::class, 'respond']);
        Route::post('/reviews/upload-photos', [ReviewController::class, 'uploadPhotos']);

        // Disputes (Phase 6)
        Route::get('/disputes', [DisputeController::class, 'index']);
        Route::get('/disputes/{id}', [DisputeController::class, 'show']);
        Route::post('/disputes', [DisputeController::class, 'store']);
        Route::post('/disputes/{disputeId}/messages', [DisputeController::class, 'sendMessage']);

        // Messages / Chat (Phase 6)
        Route::get('/messages/conversations', [MessageController::class, 'getConversations']);
        Route::get('/projects/{projectId}/messages', [MessageController::class, 'getProjectMessages']);
        Route::post('/projects/{projectId}/messages', [MessageController::class, 'sendMessage']);
        Route::post('/messages/{messageId}/read', [MessageController::class, 'markAsRead']);

        // Vendor endpoints
        Route::prefix('vendors')->group(function () {
            Route::get('/stats', [\App\Http\Controllers\Api\V1\VendorController::class, 'stats']);
            Route::get('/redemptions', [\App\Http\Controllers\Api\V1\VendorController::class, 'redemptions']);
            Route::get('/transactions', [\App\Http\Controllers\Api\V1\VendorController::class, 'transactions']);
            Route::get('/analytics', [\App\Http\Controllers\Api\V1\VendorController::class, 'analytics']);
        });

        // Artisan endpoints
        Route::prefix('artisans')->group(function () {
            Route::get('/stats', [\App\Http\Controllers\Api\V1\ArtisanController::class, 'stats']);
            Route::get('/quotes', [\App\Http\Controllers\Api\V1\ArtisanController::class, 'quotes']);
            Route::get('/transactions', [\App\Http\Controllers\Api\V1\ArtisanController::class, 'transactions']);
            Route::get('/score', [\App\Http\Controllers\Api\V1\ArtisanController::class, 'score']);
        });

        // Legacy route for backward compatibility
        Route::get('/user', function (Request $request) {
            return $request->user();
        });
    });
});
