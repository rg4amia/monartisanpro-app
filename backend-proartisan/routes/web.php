<?php

use App\Http\Controllers\Admin\AdminCashoutController;
use App\Http\Controllers\Admin\AdminCollectionController;
use App\Http\Controllers\Admin\AdminDocumentController;
use App\Http\Controllers\Admin\AdminFraudController;
use App\Http\Controllers\Admin\AdminPayoutController;
use App\Http\Controllers\Admin\AdminTerritoryController;
use App\Http\Controllers\Admin\AuthenticatedSessionController;
use App\Http\Controllers\Admin\BackofficeController;
use App\Http\Controllers\Admin\FaqAdminController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\LlmAdminController;
use App\Http\Controllers\Admin\RecruitmentAdminController;
use App\Http\Controllers\Admin\VitrineAdminController;
use App\Http\Controllers\Api\V1\DeliveryTrackingController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\KycDocumentController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\RecruitmentVoiceNoteController;
use App\Http\Controllers\UserPhotoController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    $frontUrl = config('prosartisan.front_url');

    $frontHost = parse_url($frontUrl, PHP_URL_HOST);
    $currentHost = request()->getHost();

    if ($currentHost === $frontHost) {
        return Inertia::render('welcome');
    }

    return redirect($frontUrl);
})->name('home');

// Consultation d'une pièce KYC stockée sur le disque privé. L'URL est signée
// et expire : cf. KycDocument::fileUrl. Elle remplace les anciennes URL
// publiques permanentes sous /storage/fileshare/kyc.
//
// Note d'audit sécurité : restreindre cette route à `admin.only` a été
// envisagé (une signature valide ne prouve pas l'identité de l'appelant),
// mais KycDocumentPrivacyTest::test_a_valid_signed_url_serves_the_file
// documente explicitement un accès légitime du titulaire du dossier
// lui-même, sans session admin — ajouter cette restriction casserait ce
// parcours voulu. Le modèle de sécurité retenu ici est celui, standard,
// d'une URL signée à courte durée de vie (15 min, cf. KycDocument::VIEW_URL_TTL_MINUTES) :
// la signature EST l'autorisation, comme documenté par Laravel pour les
// "temporary signed routes".
Route::get('/kyc/documents/{document}/file', [KycDocumentController::class, 'show'])
    ->middleware('signed')
    ->name('kyc.document.file');

// Reçu PDF d'une transaction (Chantier 11) : même principe, lien délivré au
// seul titulaire par GET /api/v1/transactions/{transaction}/receipt-link.
Route::get('/receipts/transactions/{transaction}', [ReceiptController::class, 'show'])
    ->middleware('signed')
    ->name('receipts.transaction.file');

// Note vocale de candidature au recrutement : même principe
// (RecruitmentApplication::voiceNoteUrl), servie uniquement une fois validée
// sans coordonnées.
Route::get('/recruitment/voice-notes/{application}/file', [RecruitmentVoiceNoteController::class, 'show'])
    ->middleware('signed')
    ->name('recruitment.voice-note.file');

// Photo de profil : même principe que la route ci-dessus (User::photoUrl).
Route::get('/users/{user}/photo', [UserPhotoController::class, 'show'])
    ->middleware('signed')
    ->name('users.photo.file');

Route::inertia('/cgu', 'cgu', ['defaultTab' => 'cgu'])->name('cgu');
Route::inertia('/politique-confidentialite', 'cgu', ['defaultTab' => 'privacy'])->name('privacy');
Route::inertia('/privacy', 'cgu', ['defaultTab' => 'privacy']);

// Assistant IA Chantier (WebView mobile). La page HTML + ses assets vivent dans
// public/ ; servir via une route évite la copie manuelle au docroot (qui dérivait).
Route::get('/assistant', function () {
    return response()->file(public_path('client.html'), [
        'Content-Type' => 'text/html; charset=UTF-8',
        // 'no-cache' (et non 'no-store') autorise la WebView à conserver le
        // corps de la réponse et à le revalider par une requête conditionnelle
        // (If-Modified-Since / 304) plutôt que de retélécharger les ~10 Ko de
        // HTML à chaque ouverture — le contenu ne dépend d'aucune donnée
        // utilisateur (le jeton Sanctum voyage dans le fragment d'URL, jamais
        // envoyé au serveur ni intégré au HTML).
        'Cache-Control' => 'no-cache, must-revalidate',
    ]);
})->name('assistant');

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
        Route::get('/', fn () => redirect()->route('admin.dashboard'))->name('index');

        // Accès ouvert à tout administrateur (Chantier C6 / P2-10).
        Route::get('/dashboard', [BackofficeController::class, 'dashboard'])->name('dashboard');
        Route::get('/notifications', [BackofficeController::class, 'notifications'])->name('notifications');
        Route::post('/notifications/{notification}/read', [BackofficeController::class, 'markNotificationRead'])->name('notifications.read');
        Route::post('/notifications/mark-all-read', [BackofficeController::class, 'markAllNotificationsRead'])->name('notifications.mark-all-read');
        Route::get('/manuel', [BackofficeController::class, 'userManual'])->name('manual');
        Route::get('/manuel/document', [BackofficeController::class, 'userManualDocument'])->name('manual.document');
        Route::get('/manuel/telecharger', [BackofficeController::class, 'downloadUserManual'])->name('manual.download');
        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

        // KYC & vérifications
        Route::get('/kyc', [BackofficeController::class, 'kyc'])->middleware('can:admin.kyc.view')->name('kyc');
        Route::post('/kyc/bulk-review', [BackofficeController::class, 'bulkReviewKyc'])->middleware('can:admin.kyc.review')->name('kyc.bulk-review');
        Route::post('/kyc/{user}/review', [BackofficeController::class, 'reviewKyc'])->middleware('can:admin.kyc.review')->name('kyc.review');
        Route::post('/kyc/{user}/cnmci-review', [BackofficeController::class, 'reviewCnmci'])->middleware('can:admin.kyc.review')->name('kyc.cnmci-review');
        Route::post('/fournisseurs/{fournisseur}/review', [BackofficeController::class, 'reviewFournisseur'])->middleware('can:admin.fournisseurs.review')->name('fournisseurs.review');

        // Missions & Cartographie
        Route::get('/missions', [BackofficeController::class, 'missions'])->middleware('can:admin.missions.view')->name('missions');
        Route::get('/cartographie', [BackofficeController::class, 'cartography'])->middleware('can:admin.territory.view')->name('cartography');
        Route::get('/cartographie/stats', [AdminTerritoryController::class, 'stats'])->middleware('can:admin.territory.view')->name('cartography.stats');
        Route::get('/deliveries/fleet-map', [DeliveryTrackingController::class, 'getFleetOverview'])->middleware('can:admin.missions.view')->name('deliveries.fleet-map');

        // Litiges
        Route::get('/litiges', [BackofficeController::class, 'litiges'])->middleware('can:admin.litiges.view')->name('litiges');
        Route::get('/litiges/{litige}/invoice', [BackofficeController::class, 'downloadInvoice'])->middleware('can:admin.litiges.view')->name('litiges.invoice');
        Route::post('/litiges/{litige}/resolve', [BackofficeController::class, 'resolveLitige'])->middleware('can:admin.litiges.arbitrate')->name('litiges.resolve');
        Route::post('/litiges/{litige}/tele-expertise', [BackofficeController::class, 'teleExpertiseLitige'])->middleware('can:admin.litiges.arbitrate')->name('litiges.tele-expertise');
        Route::post('/litiges/{litige}/assign-jury', [BackofficeController::class, 'assignJuryLitige'])->middleware('can:admin.litiges.arbitrate')->name('litiges.assign-jury');

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

        // Finance & Cash-Out Quincaillerie
        Route::get('/transactions', [BackofficeController::class, 'transactions'])->middleware('can:admin.transactions.view')->name('transactions');
        Route::post('/ledger/verify-integrity', [BackofficeController::class, 'verifyLedgerIntegrity'])->middleware('can:admin.transactions.view')->name('ledger.verify-integrity');
        Route::post('/cashouts', [AdminCashoutController::class, 'store'])->middleware('can:admin.transactions.manage')->name('cashouts.store');
        Route::post('/cashouts/batch', [AdminCashoutController::class, 'createBatch'])->middleware('can:admin.transactions.manage')->name('cashouts.batch');
        Route::post('/cashouts/reconcile-batch', [AdminCashoutController::class, 'reconcileBatch'])->middleware('can:admin.transactions.manage')->name('cashouts.reconcile-batch');
        Route::get('/cashouts/export-batch/{batchReference}', [AdminCashoutController::class, 'exportBatchCsv'])->middleware('can:admin.transactions.view')->name('cashouts.export-batch');
        Route::post('/cashouts/{cashout}/approve', [AdminCashoutController::class, 'approve'])->middleware('can:admin.transactions.manage')->name('cashouts.approve');
        Route::post('/cashouts/{cashout}/complete', [AdminCashoutController::class, 'complete'])->middleware('can:admin.transactions.manage')->name('cashouts.complete');
        Route::post('/cashouts/{cashout}/reject', [AdminCashoutController::class, 'reject'])->middleware('can:admin.transactions.manage')->name('cashouts.reject');
        // Versements Mobile Money & retraits livreur (Chantier 10)
        Route::post('/payouts/{payout}/retry', [AdminPayoutController::class, 'retry'])->middleware('can:admin.transactions.manage')->name('payouts.retry');
        Route::post('/payouts/{payout}/mark-paid', [AdminPayoutController::class, 'markPaid'])->middleware('can:admin.transactions.manage')->name('payouts.mark-paid');
        Route::post('/driver-cashouts/{cashout}/approve', [AdminPayoutController::class, 'approveCashout'])->middleware('can:admin.transactions.manage')->name('driver-cashouts.approve');
        Route::post('/driver-cashouts/{cashout}/pay', [AdminPayoutController::class, 'payCashout'])->middleware('can:admin.transactions.manage')->name('driver-cashouts.pay');
        Route::post('/driver-cashouts/{cashout}/reject', [AdminPayoutController::class, 'rejectCashout'])->middleware('can:admin.transactions.manage')->name('driver-cashouts.reject');
        // Chantier 11 — encaissements : courses impayées, restrictions, virements de commande.
        Route::post('/collections/orders/{order}/remind', [AdminCollectionController::class, 'remindFare'])->middleware('can:admin.transactions.manage')->name('collections.remind');
        Route::post('/collections/users/{user}/lift-restriction', [AdminCollectionController::class, 'liftRestriction'])->middleware('can:admin.transactions.manage')->name('collections.lift-restriction');
        Route::post('/collections/transactions/{transaction}/confirm-bank-transfer', [AdminCollectionController::class, 'confirmBankTransfer'])->middleware('can:admin.transactions.manage')->name('collections.confirm-bank-transfer');
        Route::get('/exports/{resource}', [BackofficeController::class, 'exportCsv'])->middleware('can:admin.exports')->name('exports');

        // Documents & Reçus de Décaissement
        Route::get('/documents', [AdminDocumentController::class, 'index'])->middleware('can:admin.transactions.view')->name('documents.index');
        Route::get('/documents/{document}/download', [AdminDocumentController::class, 'download'])->middleware('can:admin.transactions.view')->name('documents.download');
        Route::post('/documents/sync', [AdminDocumentController::class, 'sync'])->middleware('can:admin.transactions.manage')->name('documents.sync');
        Route::get('/transactions/{transaction}/receipt', [AdminDocumentController::class, 'receiptForTransaction'])->middleware('can:admin.transactions.view')->name('transactions.receipt');
        Route::get('/cashouts/{cashout}/receipt', [AdminDocumentController::class, 'receiptForCashout'])->middleware('can:admin.transactions.view')->name('cashouts.receipt');

        // Sécurité & Anti-Fraude (Lot 3)
        Route::post('/fraud-alerts/{fraudAlert}/hold', [AdminFraudController::class, 'hold'])->middleware('can:admin.fraud.manage')->name('fraud-alerts.hold');
        Route::post('/fraud-alerts/{fraudAlert}/release', [AdminFraudController::class, 'release'])->middleware('can:admin.fraud.manage')->name('fraud-alerts.release');
        Route::post('/fraud-alerts/{fraudAlert}/confirm', [AdminFraudController::class, 'confirm'])->middleware('can:admin.fraud.manage')->name('fraud-alerts.confirm');
        Route::post('/fraud-alerts/{fraudAlert}/dismiss', [AdminFraudController::class, 'dismiss'])->middleware('can:admin.fraud.manage')->name('fraud-alerts.dismiss');

        // Qualité
        Route::get('/evaluations', [BackofficeController::class, 'evaluations'])->middleware('can:admin.evaluations.view')->name('evaluations');

        // Plateforme
        Route::get('/settings', [BackofficeController::class, 'settings'])->middleware('can:admin.settings.manage')->name('settings');
        // Déclarée avant /settings/{setting}, qui intercepterait « bank-transfer ».
        Route::put('/settings/bank-transfer', [BackofficeController::class, 'updateBankTransfer'])->middleware('can:admin.settings.manage')->name('settings.bank-transfer.update');
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
        Route::put('/ai-dashboard/quotas/{user}', [BackofficeController::class, 'updateAiUserQuota'])->middleware('can:admin.ai.manage')->name('ai-dashboard.quotas.update');

        // Marketing
        Route::get('/promo-codes', [BackofficeController::class, 'promoCodes'])->middleware('can:admin.promo.manage')->name('promo-codes');
        Route::post('/promo-codes', [BackofficeController::class, 'storePromoCode'])->middleware('can:admin.promo.manage')->name('promo-codes.store');
        Route::put('/promo-codes/{promoCode}', [BackofficeController::class, 'updatePromoCode'])->middleware('can:admin.promo.manage')->name('promo-codes.update');
        Route::delete('/promo-codes/{promoCode}', [BackofficeController::class, 'destroyPromoCode'])->middleware('can:admin.promo.manage')->name('promo-codes.destroy');
        Route::post('/promo-codes/{promoCode}/toggle', [BackofficeController::class, 'togglePromoCode'])->middleware('can:admin.promo.manage')->name('promo-codes.toggle');

        // Marketing — Campagnes de parrainage client (distinct du parrainage artisan)
        Route::get('/campagnes-parrainage', [BackofficeController::class, 'campagnesParrainage'])->middleware('can:admin.parrainage.manage')->name('campagnes-parrainage');
        Route::post('/campagnes-parrainage', [BackofficeController::class, 'storeCampagneParrainage'])->middleware('can:admin.parrainage.manage')->name('campagnes-parrainage.store');
        Route::put('/campagnes-parrainage/{campagne}', [BackofficeController::class, 'updateCampagneParrainage'])->middleware('can:admin.parrainage.manage')->name('campagnes-parrainage.update');
        Route::delete('/campagnes-parrainage/{campagne}', [BackofficeController::class, 'destroyCampagneParrainage'])->middleware('can:admin.parrainage.manage')->name('campagnes-parrainage.destroy');
        Route::post('/campagnes-parrainage/{campagne}/toggle', [BackofficeController::class, 'toggleCampagneParrainage'])->middleware('can:admin.parrainage.manage')->name('campagnes-parrainage.toggle');

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
        Route::get('/whatsapp', [BackofficeController::class, 'whatsapp'])->middleware('can:admin.whatsapp.manage')->name('whatsapp');

        // FAQ « Aide et support » (app mobile — client/artisan/livreur/fournisseur)
        Route::get('/faq', [BackofficeController::class, 'faq'])->middleware('can:admin.faq.manage')->name('faq');
        Route::prefix('faq')->name('faq.')->middleware('can:admin.faq.manage')->group(function () {
            Route::post('/', [FaqAdminController::class, 'store'])->name('store');
            Route::match(['post', 'put'], '/{faq}', [FaqAdminController::class, 'update'])->name('update');
            Route::delete('/{faq}', [FaqAdminController::class, 'destroy'])->name('destroy');
        });

        // Module Recrutement (offres publiées par admin/client/fournisseur)
        Route::get('/recruitment', [BackofficeController::class, 'recruitment'])->middleware('can:admin.recruitment.manage')->name('recruitment');
        Route::prefix('recruitment')->name('recruitment.')->middleware('can:admin.recruitment.manage')->group(function () {
            Route::post('/{offer}/approve', [RecruitmentAdminController::class, 'approve'])->name('approve');
            Route::post('/{offer}/reject', [RecruitmentAdminController::class, 'reject'])->name('reject');
            Route::post('/settings', [RecruitmentAdminController::class, 'updateSettings'])->name('settings');
            Route::get('/{offer}/applications', [RecruitmentAdminController::class, 'applications'])->name('applications');
            Route::post('/{offer}/applications/{application}/status', [RecruitmentAdminController::class, 'updateApplicationStatus'])->name('applications.status');
        });

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

Route::get('/pay', [PaymentController::class, 'showMockPay'])->name('payment.mock.pay');
Route::post('/pay/validate', [PaymentController::class, 'validateMockPay'])->name('payment.mock.validate');

// Trigger deploy: SSH test 7
