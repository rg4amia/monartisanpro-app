<?php

namespace App\Http\Middleware\Security;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Shared\Services\FraudDetectionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fraud Detection Middleware
 *
 * Checks for suspicious activity patterns and blocks high-risk requests
 *
 * Requirements: 13.3
 */
class FraudDetectionMiddleware
{
 private const HIGH_RISK_THRESHOLD = 80;
 private const MEDIUM_RISK_THRESHOLD = 60;

 public function __construct(
  private FraudDetectionService $fraudDetectionService
 ) {}

 /**
  * Handle an incoming request.
  *
  * @param Request $request
  * @param Closure $next
  * @return Response
  */
 public function handle(Request $request, Closure $next): Response
 {
  // Get authenticated user
  $user = $request->attributes->get('user');

  if ($user === null) {
   // No user, skip fraud detection
   return $next($request);
  }

  $userId = $user->getId();

  // Check if account is already flagged
  if ($this->fraudDetectionService->isAccountFlagged($userId)) {
   Log::warning('Blocked request from flagged account', [
    'user_id' => $userId->getValue(),
    'endpoint' => $request->getPathInfo(),
    'method' => $request->getMethod(),
    'ip' => $request->ip(),
   ]);

   return response()->json([
    'error' => 'ACCOUNT_UNDER_REVIEW',
    'message' => 'Votre compte fait l\'objet d\'un examen de sécurité. Contactez le support.',
    'status_code' => 403,
    'details' => [
     'reason' => 'Account flagged for suspicious activity',
     'contact_support' => true,
    ],
    'timestamp' => now()->toISOString(),
    'request_id' => $request->header('X-Request-ID', uniqid()),
   ], 403);
  }

  // Analyze current activity
  $activityResult = $this->fraudDetectionService->detectSuspiciousActivity($userId);

  if ($activityResult->isSuspicious()) {
   $riskScore = $activityResult->getRiskScore();

   // Block high-risk requests
   if ($riskScore >= self::HIGH_RISK_THRESHOLD) {
    Log::warning('Blocked high-risk request', [
     'user_id' => $userId->getValue(),
     'risk_score' => $riskScore,
     'flags' => $activityResult->getFlags(),
     'endpoint' => $request->getPathInfo(),
     'method' => $request->getMethod(),
     'ip' => $request->ip(),
    ]);

    // Flag account for review
    $this->fraudDetectionService->flagAccountForReview(
     $userId,
     'High-risk activity detected',
     [
      'risk_score' => $riskScore,
      'flags' => $activityResult->getFlags(),
      'endpoint' => $request->getPathInfo(),
      'ip' => $request->ip(),
     ]
    );

    return response()->json([
     'error' => 'HIGH_RISK_ACTIVITY',
     'message' => 'Activité suspecte détectée. Votre compte a été temporairement restreint.',
     'status_code' => 403,
     'details' => [
      'risk_score' => $riskScore,
      'contact_support' => true,
     ],
     'timestamp' => now()->toISOString(),
     'request_id' => $request->header('X-Request-ID', uniqid()),
    ], 403);
   }

   // Log medium-risk activity but allow request
   if ($riskScore >= self::MEDIUM_RISK_THRESHOLD) {
    Log::info('Medium-risk activitydetected', [
     'user_id' => $userId->getValue(),
     'risk_score' => $riskScore,
     'flags' => $activityResult->getFlags(),
     'endpoint' => $request->getPathInfo(),
     'method' => $request->getMethod(),
     'ip' => $request->ip(),
    ]);

    // Add warning header
    $response = $next($request);
    $response->headers->set('X-Security-Warning', 'Medium risk activity detected');
    return $response;
   }
  }

  return $next($request);
 }
}
