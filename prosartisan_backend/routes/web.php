<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Backoffice\AuthController;
use App\Http\Controllers\Backoffice\DashboardController;

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

        // Redirect root backoffice to dashboard
        Route::get('/', function () {
            return redirect()->route('backoffice.dashboard');
        });
    });
});
