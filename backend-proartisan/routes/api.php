<?php

use App\Http\Controllers\Api\V1\ArtisanController;
use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AdminRolePermissionController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DevisController;
use App\Http\Controllers\Api\V1\DeliveryController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\EvaluationController;
use App\Http\Controllers\Api\V1\JalonController;
use App\Http\Controllers\Api\V1\JCodeController;
use App\Http\Controllers\Api\V1\KycController;
use App\Http\Controllers\Api\V1\LitigeController;
use App\Http\Controllers\Api\V1\LitigeJuryController;
use App\Http\Controllers\Api\V1\ParrainageController;
use App\Http\Controllers\Api\V1\MissionController;
use App\Http\Controllers\Api\V1\MicroCreditController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\SectorController;
use App\Http\Controllers\Api\V1\SmsController;
use App\Http\Controllers\Api\V1\SupplierCatalogController;
use App\Http\Controllers\Api\V1\Supplier\SupplierDashboardController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\UploadController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Controllers\Admin\LlmAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // LLM Assistant Public routes
    Route::post('/chat', [LlmAdminController::class, 'chat']);
    Route::post('/search', [LlmAdminController::class, 'search']);

    // ─────────────────────────────────────────────────────────────────────────
    // ROUTES PUBLIQUES (sans authentification)
    // ─────────────────────────────────────────────────────────────────────────

    Route::prefix('auth')->middleware('throttle:auth')->group(function () {
        Route::post('/send-otp',   [AuthController::class, 'sendOtp']);
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
        Route::post('/register',   [AuthController::class, 'register']);
        Route::post('/reset-phone-request', [AuthController::class, 'requestResetPhoneLost']);
        Route::post('/reset-phone-confirm', [AuthController::class, 'confirmResetPhoneLost']);
    });

    Route::get('/settings/app-access', [\App\Http\Controllers\Api\V1\SettingController::class, 'getAppAccess']);

    // ── Webhooks (sans authentification pour les callbacks externes) ─────────
    Route::prefix('webhooks')->middleware('throttle:webhook')->group(function () {
        Route::post('/wave',         [WebhookController::class, 'wave']);
        Route::post('/orange-money', [WebhookController::class, 'orangeMoney']);
    });

    // ─────────────────────────────────────────────────────────────────────────
    // ROUTES PROTÉGÉES (Sanctum token)
    // ─────────────────────────────────────────────────────────────────────────

    Route::middleware(['auth:sanctum', 'account.active', 'throttle:api'])->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Api\V1\DashboardController::class, 'index']);

        // ── Auth ─────────────────────────────────────────────────────────────
        Route::prefix('auth')->group(function () {
            Route::get('/me',      [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/change-phone', [AuthController::class, 'changePhoneConnected']);
            Route::post('/accept-cgu', [AuthController::class, 'acceptCgu']);
        });

        // ── Upload générique fileshare ──────────────────────────────────────────
        Route::post('/upload', [UploadController::class, 'upload']);

        // ── KYC ──────────────────────────────────────────────────────────────
        Route::prefix('kyc')->group(function () {
            Route::post('/upload-cni',    [KycController::class, 'uploadCni']);
            Route::post('/upload-selfie', [KycController::class, 'uploadSelfie']);
            Route::get('/status',         [KycController::class, 'status']);
        });

        // ── Commandes Catalogue E-Commerce ───────────────────────────────────
        Route::apiResource('orders', OrderController::class)->only(['index', 'store', 'show']);
        Route::post('/orders/{order}/prepared', [OrderController::class, 'markPrepared']);
        Route::post('/orders/{order}/verify-pickup', [OrderController::class, 'verifyPickup']);
        Route::post('/orders/{order}/verify-delivery', [OrderController::class, 'verifyDelivery']);

        // ── Logistique & Livraisons (Courses) ──────────────────────────────────
        Route::get('/deliveries/available', [DeliveryController::class, 'available'])->middleware('kyc.verified');
        Route::post('/deliveries/{order}/accept', [DeliveryController::class, 'accept'])->middleware('kyc.verified');

        // ── Utilisateurs ─────────────────────────────────────────────────────
        Route::put('/users/{user}',          [UserController::class, 'update']);
        Route::put('/users/{user}/location', [UserController::class, 'updateLocation']);
        Route::put('/users/{user}/role',     [UserController::class, 'setRole']);

        // ── Artisans ──────────────────────────────────────────────────────────
        Route::get('/artisans',              [ArtisanController::class, 'nearby'])->middleware('kyc.verified');
        Route::get('/artisans/{user}',       [ArtisanController::class, 'show']);
        Route::get('/artisans/{user}/score', [ArtisanController::class, 'score']);
        Route::get('/artisans/{user}/report', [ArtisanController::class, 'downloadReport']);
        
        Route::apiResource('artisan-stock', \App\Http\Controllers\Api\V1\ArtisanStockController::class)->except(['show']);

        // ── Secteurs & Métiers ────────────────────────────────────────────────
        Route::get('/sectors',                 [SectorController::class, 'index']);
        Route::get('/sectors/{sector}/trades', [SectorController::class, 'trades']);

        // ── Fournisseurs & catalogue ─────────────────────────────────────────
        Route::get('/fournisseurs', [SupplierCatalogController::class, 'suppliers'])->middleware('kyc.verified');
        Route::get('/fournisseurs/{user}/articles', [SupplierCatalogController::class, 'supplierProducts']);
        Route::get('/supplier-products', [SupplierCatalogController::class, 'myProducts']);
        Route::post('/supplier-products', [SupplierCatalogController::class, 'store'])->middleware(['can:supplier-products.manage', 'kyc.verified']);
        Route::put('/supplier-products/{supplierProduct}', [SupplierCatalogController::class, 'update'])->middleware(['can:supplier-products.manage', 'kyc.verified']);
        Route::delete('/supplier-products/{supplierProduct}', [SupplierCatalogController::class, 'destroy'])->middleware(['can:supplier-products.manage', 'kyc.verified']);
        Route::get('/supplier/dashboard', [SupplierDashboardController::class, 'dashboard'])->middleware('supplier.only');

        // ── Missions ──────────────────────────────────────────────────────────
        Route::get('/missions',                      [MissionController::class, 'index']);
        Route::post('/missions',                     [MissionController::class, 'store'])->middleware(['can:mission.create', 'kyc.verified']);
        Route::get('/missions/{mission}',            [MissionController::class, 'show']);
        Route::post('/missions/estimate',            [MissionController::class, 'estimate'])->middleware('can:mission.estimate');
        Route::put('/missions/{mission}/status',     [MissionController::class, 'updateStatus']);
        Route::post('/missions/{mission}/accept-request', [MissionController::class, 'acceptRequest']);
        Route::post('/missions/{mission}/reject-request', [MissionController::class, 'rejectRequest']);
        Route::post('/missions/{mission}/referent-validate', [\App\Http\Controllers\Api\V1\ReferentController::class, 'validateMission'])->middleware(['can:mission.referent-validate', 'kyc.verified']);

        // ── Devis ────────────────────────────────────────────────────────────
        Route::get('/missions/{mission}/devis',      [DevisController::class, 'index']);
        Route::post('/missions/{mission}/devis',     [DevisController::class, 'store'])->middleware(['can:devis.create', 'kyc.verified']);
        Route::get('/devis/{devis}',                 [DevisController::class, 'show']);
        Route::put('/devis/{devis}',                 [DevisController::class, 'update'])->middleware('can:devis.update');
        Route::post('/devis/{devis}/accept',         [DevisController::class, 'accept'])->middleware('can:devis.accept');
        Route::post('/devis/{devis}/refuse',         [DevisController::class, 'refuse'])->middleware('can:devis.refuse');

        // ── Jalons ────────────────────────────────────────────────────────────
        Route::get('/missions/{mission}/jalons',     [JalonController::class, 'index']);
        Route::put('/jalons/{jalon}/submit',         [JalonController::class, 'submit'])->middleware(['can:jalon.submit', 'kyc.verified']);
        Route::post('/jalons/{jalon}/photos',        [JalonController::class, 'uploadPhotos'])->middleware(['can:jalon.upload-photos', 'kyc.verified']);
        Route::post('/jalons/{jalon}/request-otp',   [JalonController::class, 'requestOtp'])->middleware(['can:jalon.request-otp', 'kyc.verified']);
        Route::post('/jalons/{jalon}/validate-otp',  [JalonController::class, 'validateOtp'])->middleware(['can:jalon.validate-otp', 'kyc.verified']);

        // ── J-Codes ───────────────────────────────────────────────────────────
        Route::post('/jcodes',                       [JCodeController::class, 'store'])->middleware(['can:jcode.create', 'kyc.verified']);
        Route::get('/jcodes/active',                 [JCodeController::class, 'active']);
        Route::get('/jcodes/{jcode}',                [JCodeController::class, 'show']);
        Route::post('/jcodes/{jcode}/scan',          [JCodeController::class, 'scan'])->middleware(['can:jcode.scan', 'kyc.verified']);
        Route::post('/jcodes/{jcode}/photo-materiaux', [JCodeController::class, 'uploadPhotoMateriaux'])->middleware('can:jcode.upload-photo-materials');

        // ── Paiements (Wave & Orange Money) ───────────────────────────────────
        Route::prefix('payments')->group(function () {
            Route::post('/initiate',               [PaymentController::class, 'initiatePayment']);
            Route::post('/jalons/{jalon}/pay',     [PaymentController::class, 'initiateJalonPayment']);
            Route::get('/{transaction}/status',    [PaymentController::class, 'checkStatus']);
            Route::get('/history',                 [PaymentController::class, 'history']);
        });

        // ── Micro-crédit ───────────────────────────────────────────────────────
        Route::prefix('micro-credit')->group(function () {
            Route::get('/eligibility', [MicroCreditController::class, 'eligibility']);
            Route::post('/apply', [MicroCreditController::class, 'apply']);
        });

        // ── Wallet & Transactions ─────────────────────────────────────────────
        Route::get('/transactions',    [TransactionController::class, 'index']);
        Route::get('/wallets/balance', [TransactionController::class, 'balance']);

        // ── Litiges ───────────────────────────────────────────────────────────
        Route::get('/litiges',               [LitigeController::class, 'index']);
        Route::post('/litiges',              [LitigeController::class, 'store']);
        Route::get('/litiges/{litige}',      [LitigeController::class, 'show']);
        Route::post('/litiges/{litige}/preuves', [LitigeController::class, 'storeEvidence']);
        Route::post('/litiges/{litige}/evaluate-sla', [LitigeController::class, 'evaluateSla']);
        Route::put('/litiges/{litige}/arbitrage', [LitigeController::class, 'arbitrage']);
        Route::post('/litiges/{litige}/jury/assign', [LitigeJuryController::class, 'assign']);
        Route::post('/litiges/{litige}/jury/vote', [LitigeJuryController::class, 'vote']);
        Route::post('/litiges/{litige}/llm-mediation', [LlmAdminController::class, 'llmMediation']);

        // ── Évaluations ───────────────────────────────────────────────────────
        Route::post('/evaluations', [EvaluationController::class, 'store']);

        // ── Commandes e-Commerce (Fournisseurs & Livraison) ─────────────────────
        Route::prefix('orders')->group(function () {
            Route::get('/',                                  [\App\Http\Controllers\Api\V1\OrderController::class, 'index']);
            Route::post('/',                                 [\App\Http\Controllers\Api\V1\OrderController::class, 'store']);
            Route::get('/{order}',                           [\App\Http\Controllers\Api\V1\OrderController::class, 'show']);
            Route::post('/{order}/prepared',                 [\App\Http\Controllers\Api\V1\OrderController::class, 'markPrepared']);
            Route::post('/{order}/verify-pickup',            [\App\Http\Controllers\Api\V1\OrderController::class, 'verifyPickup']);
            Route::post('/{order}/verify-delivery',          [\App\Http\Controllers\Api\V1\OrderController::class, 'verifyDelivery']);
            Route::post('/{order}/dispute',                  [\App\Http\Controllers\Api\V1\OrderController::class, 'dispute']);
            Route::post('/{order}/waiting-surge',            [\App\Http\Controllers\Api\V1\OrderController::class, 'applyWaitingSurge']);
        });

        // ── Parrainages ────────────────────────────────────────────────────────
        Route::post('/parrainages', [ParrainageController::class, 'store']);
        Route::get('/parrainages', [ParrainageController::class, 'index']);

        // ── Notifications ─────────────────────────────────────────────────────
        Route::get('/notifications',                     [NotificationController::class, 'index']);
        Route::put('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::post('/notifications/mark-all-read',      [NotificationController::class, 'markAllRead']);

        // ── SMS (Admin/Testing) ───────────────────────────────────────────────
        Route::prefix('sms')->group(function () {
            Route::post('/send',        [SmsController::class, 'send']);
            Route::get('/',             [SmsController::class, 'viewAll']);
            Route::get('/{uid}',        [SmsController::class, 'view']);
        });

        // ── Administration ─────────────────────────────────────────────────────
        Route::prefix('admin')->middleware('admin.only')->group(function () {
            Route::get('/dashboard', [AdminController::class, 'dashboard']);

            // Gestion des Rôles & Permissions
            Route::get('/roles-permissions', [AdminRolePermissionController::class, 'index']);
            Route::get('/permissions', [AdminRolePermissionController::class, 'listPermissions']);
            Route::post('/roles-permissions/assign', [AdminRolePermissionController::class, 'assign']);
            Route::post('/roles-permissions/revoke', [AdminRolePermissionController::class, 'revoke']);
            
            Route::put('/settings/app-access', [\App\Http\Controllers\Api\V1\SettingController::class, 'updateAppAccess']);

            Route::get('/kyc/pending', [AdminController::class, 'pendingKyc']);
            Route::post('/kyc/{user}/review', [AdminController::class, 'reviewKyc']);

            Route::get('/missions', [AdminController::class, 'missions']);
            Route::get('/litiges', [AdminController::class, 'litiges']);
            Route::post('/litiges/{litige}/resolve', [AdminController::class, 'resolveLitige']);

            Route::get('/fournisseurs/pending', [AdminController::class, 'pendingFournisseurs']);
            Route::post('/fournisseurs/{fournisseur}/review', [AdminController::class, 'reviewFournisseur']);

            Route::get('/users', [AdminController::class, 'users']);
            Route::get('/transactions', [AdminController::class, 'transactions']);
        });
    });
});
