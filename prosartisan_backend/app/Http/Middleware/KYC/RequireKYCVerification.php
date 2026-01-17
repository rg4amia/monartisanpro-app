<?php

namespace App\Http\Middleware\KYC;

use App\Domain\Identity\Models\ValueObjects\UserType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * KYC Verification Middleware
 *
 * Ensures artisans and fournisseurs have completed KYC verification
 * before accessing certain protected endpoints
 *
 * Requirements: 1.2, 1.4
 */
class RequireKYCVerification
{
 /**
  * Handle an incoming request.
  *
  * @param Request $request
  * @param Closure $next
  * @return Response
  */
 public function handle(Request $request, Closure $next): Response
 {
  // Get authenticated user from request (set by AuthenticateAPI middleware)
  $user = $request->attributes->get('user');

  if ($user === null) {
   return response()->json([
    'error' => 'UNAUTHORIZED',
    'message' => 'Utilisateur non authentifié',
    'status_code' => 401,
    'timestamp' => now()->toISOString(),
    'request_id' => $request->header('X-Request-ID', uniqid()),
   ], 401);
  }

  // Check if user type requires KYC verification
  $userType = $user->getType();
  $requiresKYC = in_array($userType->getValue(), [
   UserType::ARTISAN->value,
   UserType::FOURNISSEUR->value,
  ]);

  if ($requiresKYC && !$user->isKYCVerified()) {
   return response()->json([
    'error' => 'KYC_VERIFICATION_REQUIRED',
    'message' => 'Vérification KYC requise pour accéder à cette fonctionnalité',
    'status_code' => 403,
    'details' => [
     'user_type' => $userType->getValue(),
     'kyc_status' => 'not_verified',
     'action_required' => 'Veuillez compléter votre vérification KYC',
    ],
    'timestamp' => now()->toISOString(),
    'request_id' => $request->header('X-Request-ID', uniqid()),
   ], 403);
  }

  return $next($request);
 }
}
