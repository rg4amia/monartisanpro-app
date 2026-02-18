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
        Route::apiResource('projects', ProjectController::class);
        Route::get('/projects/search/location', [ProjectController::class, 'search']);

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

        // Legacy route for backward compatibility
        Route::get('/user', function (Request $request) {
            return $request->user();
        });
    });
});
