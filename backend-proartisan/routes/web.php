<?php

use App\Http\Controllers\Admin\AuthenticatedSessionController;
use App\Http\Controllers\Admin\BackofficeController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    });

    Route::middleware(['auth', 'admin.only'])->group(function () {
        Route::get('/', fn () => redirect()->route('admin.dashboard'))->name('index');

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
        Route::get('/settings', [BackofficeController::class, 'settings'])->name('settings');

        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
        Route::post('/kyc/{user}/review', [BackofficeController::class, 'reviewKyc'])->name('kyc.review');
        Route::post('/litiges/{litige}/resolve', [BackofficeController::class, 'resolveLitige'])->name('litiges.resolve');
        Route::post('/fournisseurs/{fournisseur}/review', [BackofficeController::class, 'reviewFournisseur'])->name('fournisseurs.review');
    });
});

// Trigger deploy: SSH test 3

