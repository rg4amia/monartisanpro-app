<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\KYCController;
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
 });
});
