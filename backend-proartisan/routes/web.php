<?php

use App\Http\Controllers\Admin\AuthenticatedSessionController;
use App\Http\Controllers\Admin\BackofficeController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\LlmAdminController;
use App\Http\Controllers\Admin\VitrineAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $frontUrl = env('FRONT_URL', 'https://www.prosartisan.net');
    
    $frontHost = parse_url($frontUrl, PHP_URL_HOST);
    $currentHost = request()->getHost();

    if ($currentHost === $frontHost) {
        return \Inertia\Inertia::render('welcome');
    }

    return redirect($frontUrl);
})->name('home');

Route::inertia('/cgu', 'cgu', ['defaultTab' => 'cgu'])->name('cgu');
Route::inertia('/politique-confidentialite', 'cgu', ['defaultTab' => 'privacy'])->name('privacy');
Route::inertia('/privacy', 'cgu', ['defaultTab' => 'privacy']);

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
        Route::get('/login/verify-2fa', [AuthenticatedSessionController::class, 'showVerify2fa'])->name('login.verify-2fa');
        Route::post('/login/verify-2fa', [AuthenticatedSessionController::class, 'verify2fa'])->name('login.verify-2fa.store');
    });
    // Fin d'usurpation : accessible au compte usurpé (non-admin), donc hors « admin.only ».
    Route::middleware('auth')->post('/stop-impersonating', [ImpersonationController::class, 'stop'])->name('stop-impersonating');

    Route::middleware(['auth', 'admin.only'])->group(function () {
        Route::get('/', fn() => redirect()->route('admin.dashboard'))->name('index');

        // Accès ouvert à tout administrateur (Chantier C6 / P2-10).
        Route::get('/dashboard', [BackofficeController::class, 'dashboard'])->name('dashboard');
        Route::get('/notifications', [BackofficeController::class, 'notifications'])->name('notifications');
        Route::post('/notifications/{notification}/read', [BackofficeController::class, 'markNotificationRead'])->name('notifications.read');
        Route::post('/notifications/mark-all-read', [BackofficeController::class, 'markAllNotificationsRead'])->name('notifications.mark-all-read');
        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

        // KYC & vérifications
        Route::get('/kyc', [BackofficeController::class, 'kyc'])->middleware('can:admin.kyc.view')->name('kyc');
        Route::post('/kyc/bulk-review', [BackofficeController::class, 'bulkReviewKyc'])->middleware('can:admin.kyc.review')->name('kyc.bulk-review');
        Route::post('/kyc/{user}/review', [BackofficeController::class, 'reviewKyc'])->middleware('can:admin.kyc.review')->name('kyc.review');
        Route::post('/kyc/{user}/cnmci-review', [BackofficeController::class, 'reviewCnmci'])->middleware('can:admin.kyc.review')->name('kyc.cnmci-review');
        Route::post('/fournisseurs/{fournisseur}/review', [BackofficeController::class, 'reviewFournisseur'])->middleware('can:admin.fournisseurs.review')->name('fournisseurs.review');

        // Missions
        Route::get('/missions', [BackofficeController::class, 'missions'])->middleware('can:admin.missions.view')->name('missions');

        // Litiges
        Route::get('/litiges', [BackofficeController::class, 'litiges'])->middleware('can:admin.litiges.view')->name('litiges');
        Route::get('/litiges/{litige}/invoice', [BackofficeController::class, 'downloadInvoice'])->middleware('can:admin.litiges.view')->name('litiges.invoice');
        Route::post('/litiges/{litige}/resolve', [BackofficeController::class, 'resolveLitige'])->middleware('can:admin.litiges.arbitrate')->name('litiges.resolve');

        // Utilisateurs
        Route::get('/users', [BackofficeController::class, 'users'])->middleware('can:admin.users.view')->name('users');
        Route::post('/users', [BackofficeController::class, 'storeUser'])->middleware('can:admin.users.manage')->name('users.store');
        Route::put('/users/{user}', [BackofficeController::class, 'updateUser'])->middleware('can:admin.users.manage')->name('users.update');
        Route::post('/users/{user}/toggle-score-freeze', [BackofficeController::class, 'toggleScoreFreeze'])->middleware('can:admin.users.manage')->name('users.toggle-score-freeze');
        Route::post('/users/{user}/toggle-status', [BackofficeController::class, 'toggleUserStatus'])->middleware('can:admin.users.manage')->name('users.toggle-status');
        Route::post('/users/bulk-status', [BackofficeController::class, 'bulkUserStatus'])->middleware('can:admin.users.manage')->name('users.bulk-status');
        Route::delete('/users/{user}', [BackofficeController::class, 'destroyUser'])->middleware('can:admin.users.delete')->name('users.destroy');
        Route::post('/users/{user}/impersonate', [ImpersonationController::class, 'start'])->middleware('can:admin.users.impersonate')->name('users.impersonate');

        // RGPD (Chantier C6 / P2-11)
        Route::get('/users/{user}/personal-data', [BackofficeController::class, 'personalData'])->middleware('can:admin.rgpd.view')->name('users.personal-data');
        Route::get('/users/{user}/personal-data/export', [BackofficeController::class, 'exportPersonalData'])->middleware('can:admin.rgpd.view')->name('users.personal-data.export');
        Route::post('/users/{user}/anonymize', [BackofficeController::class, 'anonymizeUser'])->middleware('can:admin.rgpd.manage')->name('users.anonymize');

        // Finance
        Route::get('/transactions', [BackofficeController::class, 'transactions'])->middleware('can:admin.transactions.view')->name('transactions');
        Route::get('/exports/{resource}', [BackofficeController::class, 'exportCsv'])->middleware('can:admin.exports')->name('exports');

        // Qualité
        Route::get('/evaluations', [BackofficeController::class, 'evaluations'])->middleware('can:admin.evaluations.view')->name('evaluations');

        // Plateforme
        Route::get('/settings', [BackofficeController::class, 'settings'])->middleware('can:admin.settings.manage')->name('settings');
        Route::put('/settings/{setting}', [BackofficeController::class, 'updateSetting'])->middleware('can:admin.settings.manage')->name('settings.update');
        Route::post('/sectors', [BackofficeController::class, 'storeSector'])->middleware('can:admin.taxonomy.manage')->name('sectors.store');
        Route::put('/sectors/{sector}', [BackofficeController::class, 'updateSector'])->middleware('can:admin.taxonomy.manage')->name('sectors.update');
        Route::post('/trades', [BackofficeController::class, 'storeTrade'])->middleware('can:admin.taxonomy.manage')->name('trades.store');
        Route::put('/trades/{trade}', [BackofficeController::class, 'updateTrade'])->middleware('can:admin.taxonomy.manage')->name('trades.update');
        Route::get('/roles-permissions', [BackofficeController::class, 'rolesPermissions'])->middleware('can:admin.roles.manage')->name('roles-permissions');
        Route::post('/admins/{user}/permissions', [BackofficeController::class, 'syncAdminPermissions'])->middleware('can:admin.roles.manage')->name('admins.permissions');
        Route::get('/audit-logs', [BackofficeController::class, 'auditLogs'])->middleware('can:admin.audit.view')->name('audit-logs');
        Route::get('/observability', [BackofficeController::class, 'observability'])->middleware('can:admin.observability.view')->name('observability');
        Route::post('/observability/retry-failed-jobs', [BackofficeController::class, 'retryFailedJobs'])->middleware('can:admin.observability.manage')->name('observability.retry-jobs');
        Route::post('/observability/flush-failed-jobs', [BackofficeController::class, 'flushFailedJobs'])->middleware('can:admin.observability.manage')->name('observability.flush-jobs');

        // Intelligence
        Route::get('/llm-admin', [BackofficeController::class, 'llmAdmin'])->middleware('can:admin.llm.manage')->name('llm-admin');
        Route::get('/ai-dashboard', [BackofficeController::class, 'aiDashboard'])->middleware('can:admin.ai.manage')->name('ai-dashboard');
        Route::post('/ai-dashboard/settings', [BackofficeController::class, 'updateAiSettings'])->middleware('can:admin.ai.manage')->name('ai-dashboard.settings.update');

        // Marketing
        Route::get('/promo-codes', [BackofficeController::class, 'promoCodes'])->middleware('can:admin.promo.manage')->name('promo-codes');
        Route::post('/promo-codes', [BackofficeController::class, 'storePromoCode'])->middleware('can:admin.promo.manage')->name('promo-codes.store');
        Route::put('/promo-codes/{promoCode}', [BackofficeController::class, 'updatePromoCode'])->middleware('can:admin.promo.manage')->name('promo-codes.update');
        Route::delete('/promo-codes/{promoCode}', [BackofficeController::class, 'destroyPromoCode'])->middleware('can:admin.promo.manage')->name('promo-codes.destroy');
        Route::post('/promo-codes/{promoCode}/toggle', [BackofficeController::class, 'togglePromoCode'])->middleware('can:admin.promo.manage')->name('promo-codes.toggle');

        // Communication
        Route::middleware('can:admin.communications.manage')->group(function () {
            Route::get('/communications', [BackofficeController::class, 'communications'])->name('communications');
            Route::post('/communications', [BackofficeController::class, 'storeCommunication'])->name('communications.store');
            Route::put('/communications/{communication}', [BackofficeController::class, 'updateCommunication'])->name('communications.update');
            Route::delete('/communications/{communication}', [BackofficeController::class, 'destroyCommunication'])->name('communications.destroy');
            Route::post('/communications/{communication}/publish', [BackofficeController::class, 'publishCommunication'])->name('communications.publish');
            Route::post('/communications/{communication}/cloturer', [BackofficeController::class, 'cloturerCommunication'])->name('communications.cloturer');
        });

        // Vitrine CMS (Gestion du Front Office)
        Route::get('/vitrine', [BackofficeController::class, 'vitrine'])->middleware('can:admin.vitrine.manage')->name('vitrine');

        Route::prefix('vitrine')->name('vitrine.')->middleware('can:admin.vitrine.manage')->group(function () {
            Route::post('/slides', [VitrineAdminController::class, 'storeSlide'])->name('slides.store');
            Route::match(['post', 'put'], '/slides/{slide}', [VitrineAdminController::class, 'updateSlide'])->name('slides.update');
            Route::delete('/slides/{slide}', [VitrineAdminController::class, 'destroySlide'])->name('slides.destroy');

            Route::post('/artisan-du-mois', [VitrineAdminController::class, 'storeArtisanDuMois'])->name('artisan-du-mois.store');
            Route::delete('/artisan-du-mois/{adm}', [VitrineAdminController::class, 'destroyArtisanDuMois'])->name('artisan-du-mois.destroy');

            Route::post('/articles', [VitrineAdminController::class, 'storeArticle'])->name('articles.store');
            Route::match(['post', 'put'], '/articles/{article}', [VitrineAdminController::class, 'updateArticle'])->name('articles.update');
            Route::delete('/articles/{article}', [VitrineAdminController::class, 'destroyArticle'])->name('articles.destroy');

            Route::post('/videos', [VitrineAdminController::class, 'storeVideo'])->name('videos.store');
            Route::match(['post', 'put'], '/videos/{video}', [VitrineAdminController::class, 'updateVideo'])->name('videos.update');
            Route::delete('/videos/{video}', [VitrineAdminController::class, 'destroyVideo'])->name('videos.destroy');

            Route::post('/formations', [VitrineAdminController::class, 'storeFormation'])->name('formations.store');
            Route::match(['post', 'put'], '/formations/{formation}', [VitrineAdminController::class, 'updateFormation'])->name('formations.update');
            Route::delete('/formations/{formation}', [VitrineAdminController::class, 'destroyFormation'])->name('formations.destroy');

            Route::post('/recrutements', [VitrineAdminController::class, 'storeRecrutement'])->name('recrutements.store');
            Route::match(['post', 'put'], '/recrutements/{recrutement}', [VitrineAdminController::class, 'updateRecrutement'])->name('recrutements.update');
            Route::delete('/recrutements/{recrutement}', [VitrineAdminController::class, 'destroyRecrutement'])->name('recrutements.destroy');

            Route::post('/popups', [VitrineAdminController::class, 'storePopup'])->name('popups.store');
            Route::match(['post', 'put'], '/popups/{popup}', [VitrineAdminController::class, 'updatePopup'])->name('popups.update');
            Route::delete('/popups/{popup}', [VitrineAdminController::class, 'destroyPopup'])->name('popups.destroy');

            Route::post('/settings', [VitrineAdminController::class, 'updateSettings'])->name('settings.update');

            Route::match(['post', 'put'], '/contacts/{contact}', [VitrineAdminController::class, 'updateContact'])->name('contacts.update');
            Route::post('/contacts/{contact}/reply', [VitrineAdminController::class, 'replyContact'])->name('contacts.reply');
            Route::delete('/contacts/{contact}', [VitrineAdminController::class, 'destroyContact'])->name('contacts.destroy');
        });

        Route::prefix('api/llm')->name('api.llm.')->middleware('can:admin.llm.manage')->group(function () {
            Route::get('/staging', [LlmAdminController::class, 'getStaging'])->name('staging.index');
            Route::post('/staging', [LlmAdminController::class, 'storeStaging'])->name('staging.store');
            Route::put('/staging/{id}', [LlmAdminController::class, 'updateStaging'])->name('staging.update');
            Route::post('/staging/{id}/approve', [LlmAdminController::class, 'approveStaging'])->name('staging.approve');
            Route::post('/staging/{id}/reject', [LlmAdminController::class, 'rejectStaging'])->name('staging.reject');
            Route::delete('/staging/{id}', [LlmAdminController::class, 'destroyStaging'])->name('staging.destroy');

            Route::get('/production', [LlmAdminController::class, 'getProduction'])->name('production.index');

            Route::get('/imports', [LlmAdminController::class, 'getImports'])->name('imports.index');
            Route::post('/imports', [LlmAdminController::class, 'storeImport'])->name('imports.store');
            Route::put('/imports/{id}', [LlmAdminController::class, 'updateImport'])->name('imports.update');
            Route::delete('/imports', [LlmAdminController::class, 'clearImports'])->name('imports.clear');
            Route::post('/upload', [LlmAdminController::class, 'upload'])->name('upload');

            Route::get('/config/professions', [LlmAdminController::class, 'getProfessions'])->name('config.professions');
            Route::get('/config/categories', [LlmAdminController::class, 'getCategories'])->name('config.categories');
            Route::get('/config/contexts', [LlmAdminController::class, 'getContexts'])->name('config.contexts');

            Route::post('/search', [LlmAdminController::class, 'search'])->name('search');
            Route::post('/chat', [LlmAdminController::class, 'chat'])->name('chat');
        });
    });
});

Route::get('/pay', [\App\Http\Controllers\Api\V1\PaymentController::class, 'showMockPay'])->name('payment.mock.pay');
Route::post('/pay/validate', [\App\Http\Controllers\Api\V1\PaymentController::class, 'validateMockPay'])->name('payment.mock.validate');

// Trigger deploy: SSH test 7
