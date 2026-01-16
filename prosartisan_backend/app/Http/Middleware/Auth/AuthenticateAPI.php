<?php

namespace App\Http\Middleware\Auth;

use App\Domain\Identity\Exceptions\InvalidTokenException;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Identity\Services\AuthenticationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Authentication Middleware
 *
 * Validates JWT tokens for API requests
 *
 * Requirements: 13.2
 */
class AuthenticateAPI
{
 public function __construct(
  private AuthenticationService $authService,
  private UserRepository $userRepository
 ) {}

 /**
  * Handle an incoming request.
  *
  * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
  */
 public function handle(Request $request, Closure $next): Response
 {
  try {
   // Get token from Authorization header
   $authHeader = $request->header('Authorization');

   if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
    return response()->json([
     'error' => 'UNAUTHORIZED',
     'message' => 'Token d\'authentification manquant',
     'status_code' => 401,
    ], 401);
   }

   // Extract token
   $token = substr($authHeader, 7);

   // Verify token
   $userId = $this->authService->verifyToken($token);

   // Find user
   $user = $this->userRepository->findById(UserId::fromString($userId));

   if ($user === null) {
    return response()->json([
     'error' => 'UNAUTHORIZED',
     'message' => 'Utilisateur non trouvé',
     'status_code' => 401,
    ], 401);
   }

   // Check if account is suspended
   if ($user->isSuspended()) {
    return response()->json([
     'error' => 'ACCOUNT_SUSPENDED',
     'message' => 'Votre compte a été suspendu',
     'status_code' => 403,
    ], 403);
   }

   // Attach user to request
   $request->attributes->set('user', $user);
   $request->attributes->set('user_id', $userId);

   return $next($request);
  } catch (InvalidTokenException $e) {
   return response()->json([
    'error' => 'INVALID_TOKEN',
    'message' => 'Token d\'authentification invalide ou expiré',
    'status_code' => 401,
   ], 401);
  } catch (\Exception $e) {
   Log::error('Authentication middleware error', [
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
   ]);

   return response()->json([
    'error' => 'AUTHENTICATION_FAILED',
    'message' => 'Erreur lors de l\'authentification',
    'status_code' => 500,
   ], 500);
  }
 }
}
