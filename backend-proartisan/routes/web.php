<?php

use App\Http\Controllers\Admin\AuthenticatedSessionController;
use App\Http\Controllers\Admin\BackofficeController;
use App\Http\Controllers\Admin\LlmAdminController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    });

    Route::middleware(['auth', 'admin.only'])->group(function () {
        Route::get('/', fn() => redirect()->route('admin.dashboard'))->name('index');

        Route::get('/dashboard', [BackofficeController::class, 'dashboard'])->name('dashboard');
        Route::get('/kyc', [BackofficeController::class, 'kyc'])->name('kyc');
        Route::get('/missions', [BackofficeController::class, 'missions'])->name('missions');
        Route::get('/litiges', [BackofficeController::class, 'litiges'])->name('litiges');
        Route::get('/users', [BackofficeController::class, 'users'])->name('users');
        Route::post('/users', [BackofficeController::class, 'storeUser'])->name('users.store');
        Route::put('/users/{user}', [BackofficeController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{user}', [BackofficeController::class, 'destroyUser'])->name('users.destroy');
        Route::post('/users/{user}/toggle-status', [BackofficeController::class, 'toggleUserStatus'])->name('users.toggle-status');
        Route::get('/transactions', [BackofficeController::class, 'transactions'])->name('transactions');
        Route::get('/llm-admin', [BackofficeController::class, 'llmAdmin'])->name('llm-admin');
        Route::get('/settings', [BackofficeController::class, 'settings'])->name('settings');
        Route::put('/settings/{setting}', [BackofficeController::class, 'updateSetting'])->name('settings.update');

        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
        Route::post('/kyc/{user}/review', [BackofficeController::class, 'reviewKyc'])->name('kyc.review');
        Route::post('/litiges/{litige}/resolve', [BackofficeController::class, 'resolveLitige'])->name('litiges.resolve');
        Route::post('/fournisseurs/{fournisseur}/review', [BackofficeController::class, 'reviewFournisseur'])->name('fournisseurs.review');

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

// Trigger deploy: SSH test 7
