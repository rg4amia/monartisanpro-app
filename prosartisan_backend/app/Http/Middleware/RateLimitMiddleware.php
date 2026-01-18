<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate Limiting Middleware
 *
 * Limits API requests to 100 requests per minute per user
 *
 * Requirements: 13.4
 */
class RateLimitMiddleware
{
    private const MAX_REQUESTS_PER_MINUTE = 100;
    private const RATE_LIMIT_WINDOW_MINUTES = 1;
    private const CACHE_PREFIX = 'rate_limit:';

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get user identifier (IP address for unauthenticated, user ID for authenticated)
        $identifier = $this->getIdentifier($request);

        // Create cache key
        $cacheKey = self::CACHE_PREFIX . $identifier . ':' . now()->format('Y-m-d-H-i');

        // Get current request count
        $currentCount = Cache::get($cacheKey, 0);

        // Check if limit exceeded
        if ($currentCount >= self::MAX_REQUESTS_PER_MINUTE) {
            Log::warning('Rate limit exceeded', [
                'identifier' => $identifier,
                'current_count' => $currentCount,
                'limit' => self::MAX_REQUESTS_PER_MINUTE,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'endpoint' => $request->getPathInfo(),
            ]);

            return response()->json([
                'error' => 'RATE_LIMIT_EXCEEDED',
                'message' => 'Trop de requêtes. Veuillez réessayer dans une minute.',
                'status_code' => 429,
                'details' => [
                    'limit' => self::MAX_REQUESTS_PER_MINUTE,
                    'window' => self::RATE_LIMIT_WINDOW_MINUTES . ' minute',
                    'retry_after' => 60 - now()->second,
                ],
                'timestamp' => now()->toISOString(),
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ], 429, [
                'Retry-After' => 60 - now()->second,
                'X-RateLimit-Limit' => self::MAX_REQUESTS_PER_MINUTE,
                'X-RateLimit-Remaining' => max(0, self::MAX_REQUESTS_PER_MINUTE - $currentCount - 1),
                'X-RateLimit-Reset' => now()->addMinute()->timestamp,
            ]);
        }

        // Increment request count
        Cache::put($cacheKey, $currentCount + 1, now()->addMinutes(self::RATE_LIMIT_WINDOW_MINUTES));

        // Add rate limit headers to response
        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', self::MAX_REQUESTS_PER_MINUTE);
        $response->headers->set('X-RateLimit-Remaining', max(0, self::MAX_REQUESTS_PER_MINUTE - $currentCount - 1));
        $response->headers->set('X-RateLimit-Reset', now()->addMinute()->timestamp);

        return $response;
    }

    /**
     * Get unique identifier for rate limiting
     *
     * @param Request $request
     * @return string
     */
    private function getIdentifier(Request $request): string
    {
        // Use user ID if authenticated
        $userId = $request->attributes->get('user_id');
        if ($userId !== null) {
            return 'user:' . $userId;
        }

        // Fall back to IP address for unauthenticated requests
        return 'ip:' . $request->ip();
    }
}
