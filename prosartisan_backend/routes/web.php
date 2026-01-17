<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Backoffice\AuthController;
use App\Http\Controllers\Backoffice\DashboardController;
use App\Http\Controllers\Backoffice\UserController;

Route::get('/', function () {
    return view('welcome');
});

// Backoffice Routes
Route::prefix('backoffice')->group(function () {
    // Authentication routes
    Route::get('/login', [AuthController::class, 'showLogin'])->name('backoffice.login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->name('backoffice.logout');

    // Protected backoffice routes
    Route::middleware(['auth', 'backoffice.auth'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('backoffice.dashboard');

        // User management routes
        Route::get('/users', [UserController::class, 'index'])->name('backoffice.users.index');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('backoffice.users.show');
        Route::post('/users/{user}/suspend', [UserController::class, 'suspend'])->name('backoffice.users.suspend');
        Route::post('/users/{user}/activate', [UserController::class, 'activate'])->name('backoffice.users.activate');
        Route::post('/users/{user}/approve-kyc', [UserController::class, 'approveKyc'])->name('backoffice.users.approve-kyc');
        Route::post('/users/{user}/reject-kyc', [UserController::class, 'rejectKyc'])->name('backoffice.users.reject-kyc');

        // Redirect root backoffice to dashboard
        Route::get('/', function () {
            return redirect()->route('backoffice.dashboard');
        });
    });
});
