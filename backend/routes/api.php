<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\KycController;
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

        // Legacy route for backward compatibility
        Route::get('/user', function (Request $request) {
            return $request->user();
        });
    });
});
