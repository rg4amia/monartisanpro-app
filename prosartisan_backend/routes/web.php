<?php

use App\Http\Controllers\Backoffice\AuthController;
use App\Http\Controllers\Backoffice\DashboardController;
use App\Http\Controllers\Backoffice\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('backoffice.login');
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

        // Dispute management routes
        Route::get('/disputes', [App\Http\Controllers\Backoffice\DisputeController::class, 'index'])->name('backoffice.disputes.index');
        Route::get('/disputes/{dispute}', [App\Http\Controllers\Backoffice\DisputeController::class, 'show'])->name('backoffice.disputes.show');
        Route::post('/disputes/{dispute}/assign-mediator', [App\Http\Controllers\Backoffice\DisputeController::class, 'assignMediator'])->name('backoffice.disputes.assign-mediator');
        Route::post('/disputes/{dispute}/render-decision', [App\Http\Controllers\Backoffice\DisputeController::class, 'renderDecision'])->name('backoffice.disputes.render-decision');
        Route::post('/disputes/{dispute}/communications', [App\Http\Controllers\Backoffice\DisputeController::class, 'addCommunication'])->name('backoffice.disputes.add-communication');

        // Reputation management routes
        Route::get('/reputation', [App\Http\Controllers\Backoffice\ReputationController::class, 'index'])->name('backoffice.reputation.index');
        Route::get('/reputation/{artisan}', [App\Http\Controllers\Backoffice\ReputationController::class, 'show'])->name('backoffice.reputation.show');
        Route::post('/reputation/{artisan}/adjust-score', [App\Http\Controllers\Backoffice\ReputationController::class, 'adjustScore'])->name('backoffice.reputation.adjust-score');
        Route::get('/reputation/export-transactions', [App\Http\Controllers\Backoffice\ReputationController::class, 'exportTransactions'])->name('backoffice.reputation.export-transactions');

        // Transaction management routes
        Route::get('/transactions', [App\Http\Controllers\Backoffice\TransactionController::class, 'index'])->name('backoffice.transactions.index');
        Route::get('/transactions/{transaction}', [App\Http\Controllers\Backoffice\TransactionController::class, 'show'])->name('backoffice.transactions.show');
        Route::get('/transactions/jetons/index', [App\Http\Controllers\Backoffice\TransactionController::class, 'jetons'])->name('backoffice.transactions.jetons');
        Route::get('/transactions/sequestres/index', [App\Http\Controllers\Backoffice\TransactionController::class, 'sequestres'])->name('backoffice.transactions.sequestres');
        Route::get('/transactions/export', [App\Http\Controllers\Backoffice\TransactionController::class, 'export'])->name('backoffice.transactions.export');

        // Analytics routes
        Route::get('/analytics', [App\Http\Controllers\Backoffice\AnalyticsController::class, 'index'])->name('backoffice.analytics.index');
        Route::get('/analytics/revenue', [App\Http\Controllers\Backoffice\AnalyticsController::class, 'revenue'])->name('backoffice.analytics.revenue');
        Route::get('/analytics/users', [App\Http\Controllers\Backoffice\AnalyticsController::class, 'users'])->name('backoffice.analytics.users');
        Route::get('/analytics/performance', [App\Http\Controllers\Backoffice\AnalyticsController::class, 'performance'])->name('backoffice.analytics.performance');

        // KYC management routes
        Route::get('/kyc', [App\Http\Controllers\Backoffice\KYCController::class, 'index'])->name('backoffice.kyc.index');
        Route::get('/kyc/pending', [App\Http\Controllers\Backoffice\KYCController::class, 'pending'])->name('backoffice.kyc.pending');
        Route::get('/kyc/approved', [App\Http\Controllers\Backoffice\KYCController::class, 'approved'])->name('backoffice.kyc.approved');
        Route::get('/kyc/rejected', [App\Http\Controllers\Backoffice\KYCController::class, 'rejected'])->name('backoffice.kyc.rejected');
        Route::get('/kyc/{verification}', [App\Http\Controllers\Backoffice\KYCController::class, 'show'])->name('backoffice.kyc.show');
        Route::post('/kyc/{verification}/approve', [App\Http\Controllers\Backoffice\KYCController::class, 'approve'])->name('backoffice.kyc.approve');
        Route::post('/kyc/{verification}/reject', [App\Http\Controllers\Backoffice\KYCController::class, 'reject'])->name('backoffice.kyc.reject');
        Route::post('/kyc/bulk-approve', [App\Http\Controllers\Backoffice\KYCController::class, 'bulkApprove'])->name('backoffice.kyc.bulk-approve');
        Route::get('/kyc/export', [App\Http\Controllers\Backoffice\KYCController::class, 'export'])->name('backoffice.kyc.export');

        // Parameters management routes
        Route::get('/parameters', [App\Http\Controllers\Backoffice\ParametersController::class, 'index'])->name('backoffice.parameters.index');
        Route::get('/parameters/categories', [App\Http\Controllers\Backoffice\ParametersController::class, 'categories'])->name('backoffice.parameters.categories');
        Route::get('/parameters/export', [App\Http\Controllers\Backoffice\ParametersController::class, 'export'])->name('backoffice.parameters.export');
        Route::post('/parameters', [App\Http\Controllers\Backoffice\ParametersController::class, 'store'])->name('backoffice.parameters.store');
        Route::get('/parameters/{parameter}', [App\Http\Controllers\Backoffice\ParametersController::class, 'show'])->name('backoffice.parameters.show');
        Route::put('/parameters/{parameter}', [App\Http\Controllers\Backoffice\ParametersController::class, 'update'])->name('backoffice.parameters.update');
        Route::patch('/parameters/{parameter}/value', [App\Http\Controllers\Backoffice\ParametersController::class, 'updateValue'])->name('backoffice.parameters.update-value');
        Route::post('/parameters/bulk-update', [App\Http\Controllers\Backoffice\ParametersController::class, 'bulkUpdate'])->name('backoffice.parameters.bulk-update');
        Route::delete('/parameters/{parameter}', [App\Http\Controllers\Backoffice\ParametersController::class, 'destroy'])->name('backoffice.parameters.destroy');

        // Redirect root backoffice to dashboard
        Route::get('/', function () {
            return redirect()->route('backoffice.dashboard');
        });
    });
});

//import api.php
Route::prefix('api')->group(__DIR__.'/api.php');
