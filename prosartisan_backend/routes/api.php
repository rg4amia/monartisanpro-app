<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\KYCController;
use App\Http\Controllers\Api\V1\Marketplace\MissionController;
use App\Http\Controllers\Api\V1\Marketplace\QuoteController;
use App\Http\Controllers\Api\V1\Marketplace\ArtisanController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// API Version 1
Route::prefix('v1')->group(function () {

 // Authentication routes (public)
 Route::prefix('auth')->group(function () {
  Route::post('/register', [AuthController::class, 'register']);
  Route::post('/login', [AuthController::class, 'login']);
  Route::post('/otp/generate', [AuthController::class, 'generateOTP']);
  Route::post('/otp/verify', [AuthController::class, 'verifyOTP']);
 });

 // Protected routes (require authentication)
 Route::middleware('auth:api')->group(function () {

  // KYC document upload
  Route::post('/users/{id}/kyc', [KYCController::class, 'uploadKYC']);

  // Marketplace routes
  Route::prefix('missions')->group(function () {
   Route::get('/', [MissionController::class, 'index']);
   Route::post('/', [MissionController::class, 'store']);
   Route::get('/{id}', [MissionController::class, 'show']);
   Route::post('/{missionId}/quotes', [QuoteController::class, 'store']);
   Route::get('/{missionId}/quotes', [QuoteController::class, 'index']);
  });

  Route::prefix('quotes')->group(function () {
   Route::post('/{id}/accept', [QuoteController::class, 'accept']);
  });

  Route::prefix('artisans')->group(function () {
   Route::get('/search', [ArtisanController::class, 'search']);
  });
 });
});
