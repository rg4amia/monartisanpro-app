<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\KYCController;
use App\Http\Controllers\Api\V1\Marketplace\MissionController;
use App\Http\Controllers\Api\V1\Marketplace\QuoteController;
use App\Http\Controllers\Api\V1\Marketplace\ArtisanController;
use App\Http\Controllers\Api\V1\Financial\EscrowController;
use App\Http\Controllers\Api\V1\Financial\JetonController;
use App\Http\Controllers\Api\V1\Financial\TransactionController;
use App\Http\Controllers\Api\V1\Worksite\ChantierController;
use App\Http\Controllers\Api\V1\Worksite\JalonController;
use App\Http\Controllers\Api\V1\Reputation\ReputationController;
use App\Http\Controllers\Api\V1\Dispute\DisputeController;
use App\Http\Controllers\SecureFileController;
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
 Route::middleware(['auth:api'])->group(function () {

  // KYC document upload
  Route::post('/users/{id}/kyc', [KYCController::class, 'uploadKYC']);

  // Marketplace routes
  Route::prefix('missions')->group(function () {
   Route::get('/', [MissionController::class, 'index']);
   Route::post('/', [MissionController::class, 'store'])->middleware('role:' . \App\Domain\Identity\Models\ValueObjects\UserType::CLIENT->value);
   Route::get('/{id}', [MissionController::class, 'show']);
   Route::post('/{missionId}/quotes', [QuoteController::class, 'store'])->middleware(['kyc.required', 'role:' . \App\Domain\Identity\Models\ValueObjects\UserType::ARTISAN->value]);
   Route::get('/{missionId}/quotes', [QuoteController::class, 'index']);
  });

  Route::prefix('quotes')->group(function () {
   Route::post('/{id}/accept', [QuoteController::class, 'accept'])->middleware('role:' . \App\Domain\Identity\Models\ValueObjects\UserType::CLIENT->value);
  });

  Route::prefix('artisans')->group(function () {
   Route::get('/search', [ArtisanController::class, 'search']);
  });

  // Financial transaction routes
  Route::prefix('escrow')->group(function () {
   Route::post('/block', [EscrowController::class, 'block'])->middleware(['fraud.detection', 'role:' . \App\Domain\Identity\Models\ValueObjects\UserType::CLIENT->value]);
  });

  Route::prefix('jetons')->group(function () {
   Route::post('/generate', [JetonController::class, 'generate'])->middleware(['fraud.detection', 'kyc.required', 'role:' . \App\Domain\Identity\Models\ValueObjects\UserType::ARTISAN->value]);
   Route::post('/validate', [JetonController::class, 'validate'])->middleware(['fraud.detection', 'kyc.required', 'role:' . \App\Domain\Identity\Models\ValueObjects\UserType::FOURNISSEUR->value]);
  });

  Route::prefix('transactions')->group(function () {
   Route::get('/', [TransactionController::class, 'index']);
  });

  // Worksite management routes
  Route::prefix('chantiers')->group(function () {
   Route::get('/', [ChantierController::class, 'index']);
   Route::post('/', [ChantierController::class, 'store'])->middleware(['kyc.required', 'role:' . \App\Domain\Identity\Models\ValueObjects\UserType::ARTISAN->value]);
   Route::get('/{id}', [ChantierController::class, 'show']);
  });

  Route::prefix('jalons')->group(function () {
   Route::get('/{id}', [JalonController::class, 'show']);
   Route::post('/{id}/submit-proof', [JalonController::class, 'submitProof'])->middleware(['kyc.required', 'role:' . \App\Domain\Identity\Models\ValueObjects\UserType::ARTISAN->value]);
   Route::post('/{id}/validate', [JalonController::class, 'validate'])->middleware('role:' . \App\Domain\Identity\Models\ValueObjects\UserType::CLIENT->value);
   Route::post('/{id}/contest', [JalonController::class, 'contest'])->middleware('role:' . \App\Domain\Identity\Models\ValueObjects\UserType::CLIENT->value);
  });

  // Reputation management routes
  Route::prefix('artisans')->group(function () {
   Route::get('/{id}/reputation', [ReputationController::class, 'getArtisanReputation']);
   Route::get('/{id}/score-history', [ReputationController::class, 'getScoreHistory']);
   Route::get('/{id}/ratings', [ReputationController::class, 'getArtisanRatings']);
  });

  Route::prefix('missions')->group(function () {
   Route::post('/{id}/rate', [ReputationController::class, 'submitRating'])->middleware('role:' . \App\Domain\Identity\Models\ValueObjects\UserType::CLIENT->value);
  });

  // Dispute resolution routes
  Route::prefix('disputes')->group(function () {
   Route::get('/', [DisputeController::class, 'index']);
   Route::post('/', [DisputeController::class, 'store']);
   Route::get('/{id}', [DisputeController::class, 'show']);
   Route::post('/{id}/mediation/start', [DisputeController::class, 'startMediation']);
   Route::post('/{id}/mediation/message', [DisputeController::class, 'sendMediationMessage']);
   Route::post('/{id}/arbitration/render', [DisputeController::class, 'renderArbitration'])->middleware('role:' . \App\Domain\Identity\Models\ValueObjects\UserType::ADMIN->value . ',' . \App\Domain\Identity\Models\ValueObjects\UserType::REFERENT_ZONE->value);
  });

  // Admin dispute management routes
  Route::prefix('admin/disputes')->group(function () {
   Route::get('/', [DisputeController::class, 'adminIndex'])->middleware('role:' . \App\Domain\Identity\Models\ValueObjects\UserType::ADMIN->value);
  });
 });

 // Mobile Money Webhook routes (public - no auth required)
 Route::prefix('payments')->group(function () {
  Route::post('/webhook', [\App\Http\Controllers\Api\V1\Payment\WebhookController::class, 'handleGeneric']);
  Route::prefix('webhook')->group(function () {
   Route::post('/wave', [\App\Http\Controllers\Api\V1\Payment\WebhookController::class, 'handleWave']);
   Route::post('/orange', [\App\Http\Controllers\Api\V1\Payment\WebhookController::class, 'handleOrangeMoney']);
   Route::post('/mtn', [\App\Http\Controllers\Api\V1\Payment\WebhookController::class, 'handleMTN']);
  });
 });
});
