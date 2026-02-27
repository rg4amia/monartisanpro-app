<?php

use App\Http\Controllers\Api\V1\ArtisanController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DevisController;
use App\Http\Controllers\Api\V1\EvaluationController;
use App\Http\Controllers\Api\V1\JalonController;
use App\Http\Controllers\Api\V1\JCodeController;
use App\Http\Controllers\Api\V1\KycController;
use App\Http\Controllers\Api\V1\LitigeController;
use App\Http\Controllers\Api\V1\MissionController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\SectorController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ─────────────────────────────────────────────────────────────────────────
    // ROUTES PUBLIQUES (sans authentification)
    // ─────────────────────────────────────────────────────────────────────────

    Route::prefix('auth')->group(function () {
        Route::post('/send-otp',   [AuthController::class, 'sendOtp']);
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
        Route::post('/register',   [AuthController::class, 'register']);
    });

    // ─────────────────────────────────────────────────────────────────────────
    // ROUTES PROTÉGÉES (Sanctum token)
    // ─────────────────────────────────────────────────────────────────────────

    Route::middleware('auth:sanctum')->group(function () {

        // ── Auth ─────────────────────────────────────────────────────────────
        Route::prefix('auth')->group(function () {
            Route::get('/me',      [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });

        // ── KYC ──────────────────────────────────────────────────────────────
        Route::prefix('kyc')->group(function () {
            Route::post('/upload-cni',    [KycController::class, 'uploadCni']);
            Route::post('/upload-selfie', [KycController::class, 'uploadSelfie']);
            Route::get('/status',         [KycController::class, 'status']);
        });

        // ── Utilisateurs ─────────────────────────────────────────────────────
        Route::put('/users/{user}',          [UserController::class, 'update']);
        Route::put('/users/{user}/location', [UserController::class, 'updateLocation']);
        Route::put('/users/{user}/role',     [UserController::class, 'setRole']);

        // ── Artisans ──────────────────────────────────────────────────────────
        Route::get('/artisans',              [ArtisanController::class, 'nearby']);
        Route::get('/artisans/{user}',       [ArtisanController::class, 'show']);
        Route::get('/artisans/{user}/score', [ArtisanController::class, 'score']);

        // ── Secteurs & Métiers ────────────────────────────────────────────────
        Route::get('/sectors',                 [SectorController::class, 'index']);
        Route::get('/sectors/{sector}/trades', [SectorController::class, 'trades']);

        // ── Missions ──────────────────────────────────────────────────────────
        Route::get('/missions',                      [MissionController::class, 'index']);
        Route::post('/missions',                     [MissionController::class, 'store']);
        Route::get('/missions/{mission}',            [MissionController::class, 'show']);
        Route::post('/missions/estimate',            [MissionController::class, 'estimate']);
        Route::put('/missions/{mission}/status',     [MissionController::class, 'updateStatus']);

        // ── Devis ────────────────────────────────────────────────────────────
        Route::get('/missions/{mission}/devis',      [DevisController::class, 'index']);
        Route::post('/missions/{mission}/devis',     [DevisController::class, 'store']);
        Route::get('/devis/{devis}',                 [DevisController::class, 'show']);
        Route::put('/devis/{devis}',                 [DevisController::class, 'update']);
        Route::post('/devis/{devis}/accept',         [DevisController::class, 'accept']);
        Route::post('/devis/{devis}/refuse',         [DevisController::class, 'refuse']);

        // ── Jalons ────────────────────────────────────────────────────────────
        Route::get('/missions/{mission}/jalons',     [JalonController::class, 'index']);
        Route::put('/jalons/{jalon}/submit',         [JalonController::class, 'submit']);
        Route::post('/jalons/{jalon}/request-otp',   [JalonController::class, 'requestOtp']);
        Route::post('/jalons/{jalon}/validate-otp',  [JalonController::class, 'validateOtp']);

        // ── J-Codes ───────────────────────────────────────────────────────────
        Route::post('/jcodes',              [JCodeController::class, 'store']);
        Route::get('/jcodes/active',        [JCodeController::class, 'active']);
        Route::get('/jcodes/{jcode}',       [JCodeController::class, 'show']);
        Route::post('/jcodes/{jcode}/scan', [JCodeController::class, 'scan']);

        // ── Wallet & Transactions ─────────────────────────────────────────────
        Route::get('/transactions',    [TransactionController::class, 'index']);
        Route::get('/wallets/balance', [TransactionController::class, 'balance']);

        // ── Litiges ───────────────────────────────────────────────────────────
        Route::post('/litiges',          [LitigeController::class, 'store']);
        Route::get('/litiges/{litige}',  [LitigeController::class, 'show']);

        // ── Évaluations ───────────────────────────────────────────────────────
        Route::post('/evaluations', [EvaluationController::class, 'store']);

        // ── Notifications ─────────────────────────────────────────────────────
        Route::get('/notifications',                     [NotificationController::class, 'index']);
        Route::put('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::post('/notifications/mark-all-read',      [NotificationController::class, 'markAllRead']);
    });
});
