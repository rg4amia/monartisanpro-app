<?php

use App\Http\Controllers\Admin\AuthenticatedSessionController;
use App\Http\Controllers\Admin\BackofficeController;
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
    Route::middleware(['auth', 'admin.only'])->group(function () {
        Route::get('/', fn() => redirect()->route('admin.dashboard'))->name('index');

        Route::get('/dashboard', [BackofficeController::class, 'dashboard'])->name('dashboard');
        Route::get('/kyc', [BackofficeController::class, 'kyc'])->name('kyc');
        Route::get('/missions', [BackofficeController::class, 'missions'])->name('missions');
        Route::get('/litiges', [BackofficeController::class, 'litiges'])->name('litiges');
        Route::get('/users', [BackofficeController::class, 'users'])->name('users');
        Route::get('/evaluations', [BackofficeController::class, 'evaluations'])->name('evaluations');
        Route::post('/users/{user}/toggle-score-freeze', [BackofficeController::class, 'toggleScoreFreeze'])->name('users.toggle-score-freeze');
        Route::post('/users', [BackofficeController::class, 'storeUser'])->name('users.store');
        Route::put('/users/{user}', [BackofficeController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{user}', [BackofficeController::class, 'destroyUser'])->name('users.destroy');
        Route::post('/users/{user}/toggle-status', [BackofficeController::class, 'toggleUserStatus'])->name('users.toggle-status');
        Route::get('/transactions', [BackofficeController::class, 'transactions'])->name('transactions');
        Route::get('/llm-admin', [BackofficeController::class, 'llmAdmin'])->name('llm-admin');
        Route::get('/settings', [BackofficeController::class, 'settings'])->name('settings');
        Route::get('/ai-dashboard', [BackofficeController::class, 'aiDashboard'])->name('ai-dashboard');
        Route::post('/ai-dashboard/settings', [BackofficeController::class, 'updateAiSettings'])->name('ai-dashboard.settings.update');
        Route::get('/roles-permissions', [BackofficeController::class, 'rolesPermissions'])->name('roles-permissions');
        Route::get('/communications', [BackofficeController::class, 'communications'])->name('communications');
        Route::get('/notifications', [BackofficeController::class, 'notifications'])->name('notifications');
        Route::get('/promo-codes', [BackofficeController::class, 'promoCodes'])->name('promo-codes');
        Route::post('/communications', [BackofficeController::class, 'storeCommunication'])->name('communications.store');
        Route::put('/communications/{communication}', [BackofficeController::class, 'updateCommunication'])->name('communications.update');
        Route::delete('/communications/{communication}', [BackofficeController::class, 'destroyCommunication'])->name('communications.destroy');
        Route::post('/communications/{communication}/publish', [BackofficeController::class, 'publishCommunication'])->name('communications.publish');
        Route::post('/communications/{communication}/cloturer', [BackofficeController::class, 'cloturerCommunication'])->name('communications.cloturer');
        Route::put('/settings/{setting}', [BackofficeController::class, 'updateSetting'])->name('settings.update');
        Route::post('/sectors', [BackofficeController::class, 'storeSector'])->name('sectors.store');
        Route::put('/sectors/{sector}', [BackofficeController::class, 'updateSector'])->name('sectors.update');
        Route::post('/trades', [BackofficeController::class, 'storeTrade'])->name('trades.store');
        Route::put('/trades/{trade}', [BackofficeController::class, 'updateTrade'])->name('trades.update');

        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
        Route::post('/kyc/{user}/review', [BackofficeController::class, 'reviewKyc'])->name('kyc.review');
        Route::post('/kyc/{user}/cnmci-review', [BackofficeController::class, 'reviewCnmci'])->name('kyc.cnmci-review');
        Route::post('/litiges/{litige}/resolve', [BackofficeController::class, 'resolveLitige'])->name('litiges.resolve');
        Route::get('/litiges/{litige}/invoice', [BackofficeController::class, 'downloadInvoice'])->name('litiges.invoice');
        Route::post('/fournisseurs/{fournisseur}/review', [BackofficeController::class, 'reviewFournisseur'])->name('fournisseurs.review');
        Route::post('/notifications/{notification}/read', [BackofficeController::class, 'markNotificationRead'])->name('notifications.read');
        Route::post('/notifications/mark-all-read', [BackofficeController::class, 'markAllNotificationsRead'])->name('notifications.mark-all-read');
        Route::post('/promo-codes', [BackofficeController::class, 'storePromoCode'])->name('promo-codes.store');
        Route::put('/promo-codes/{promoCode}', [BackofficeController::class, 'updatePromoCode'])->name('promo-codes.update');
        Route::delete('/promo-codes/{promoCode}', [BackofficeController::class, 'destroyPromoCode'])->name('promo-codes.destroy');
        Route::post('/promo-codes/{promoCode}/toggle', [BackofficeController::class, 'togglePromoCode'])->name('promo-codes.toggle');

        // Vitrine CMS (Gestion du Front Office)
        Route::get('/vitrine', [BackofficeController::class, 'vitrine'])->name('vitrine');

        Route::prefix('vitrine')->name('vitrine.')->group(function () {
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

        Route::prefix('api/llm')->name('api.llm.')->group(function () {
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
