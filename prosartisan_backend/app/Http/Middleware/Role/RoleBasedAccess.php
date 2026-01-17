<?php

namespace App\Http\Middleware\Role;

use App\Domain\Identity\Models\ValueObjects\UserType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role-Based Access Control Middleware
 *
 * Restricts API access based on user roles (client, artisan, admin)
 *
 * Requirements: 13.2
 */
class RoleBasedAccess
{
 /**
  * Handle an incoming request.
  *
  * @param Request $request
  * @param Closure $next
  * @param string ...$allowedRoles List of allowed user types
  * @return Response
  */
 public function handle(Request $request, Closure $next, string ...$allowedRoles): Response
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

  // Check if user's role is in allowed roles
  $userType = $user->getType()->getValue();

  if (!in_array($userType, $allowedRoles)) {
   return response()->json([
    'error' => 'FORBIDDEN',
    'message' => 'Accès interdit pour votre type de compte',
    'status_code' => 403,
    'details' => [
     'user_type' => $userType,
     'allowed_roles' => $allowedRoles,
    ],
    'timestamp' => now()->toISOString(),
    'request_id' => $request->header('X-Request-ID', uniqid()),
   ], 403);
  }

  return $next($request);
 }
}
