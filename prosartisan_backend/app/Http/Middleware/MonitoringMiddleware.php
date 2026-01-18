<?php

namespace App\Http\Middleware;

use App\Infrastructure\Services\Monitoring\StructuredLogger;
use App\Infrastructure\Services\Monitoring\MetricsCollector;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for monitoring and logging HTTP requests
 *
 * Automatically adds correlation IDs, tracks response times,
 * and logs request/response information
 */
class MonitoringMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Set correlation ID
        $correlationId = $request->header('X-Correlation-ID') ?? StructuredLogger::setCorrelationId();

        // Start timing
        $timing = StructuredLogger::startTiming($request->getMethod() . ' ' . $request->getPathInfo());

        // Log incoming request
        StructuredLogger::info('Incoming request', [
            'method' => $request->getMethod(),
            'url' => $request->fullUrl(),
            'path' => $request->getPathInfo(),
            'query_params' => $request->query(),
            'headers' => $this->sanitizeHeaders($request->headers->all()),
            'body_size' => strlen($request->getContent()),
        ], 'http');

        try {
            $response = $next($request);

            // End timing and record metrics
            StructuredLogger::endTiming($timing, [
                'status_code' => $response->getStatusCode(),
                'response_size' => strlen($response->getContent()),
            ]);

            // Record response time metric
            $responseTime = (microtime(true) - $timing['start_time']) * 1000;
            MetricsCollector::recordResponseTime(
                $request->getMethod() . ' ' . $request->getPathInfo(),
                $responseTime
            );

            // Log successful response
            StructuredLogger::info('Request completed', [
                'status_code' => $response->getStatusCode(),
                'response_size' => strlen($response->getContent()),
                'duration_ms' => $responseTime,
            ], 'http');

            // Add correlation ID to response headers
            $response->headers->set('X-Correlation-ID', $correlationId);

            return $response;
        } catch (\Exception $e) {
            // End timing for failed requests
            StructuredLogger::endTiming($timing, [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);

            // Record error metric
            MetricsCollector::recordError(
                get_class($e),
                $request->getMethod() . ' ' . $request->getPathInfo(),
                $e->getMessage()
            );

            // Log error
            StructuredLogger::error('Request failed', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ], 'http');

            throw $e;
        }
    }

    /**
     * Sanitize headers to remove sensitive information
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = [
            'authorization',
            'x-api-key',
            'cookie',
            'x-auth-token',
        ];

        $sanitized = [];

        foreach ($headers as $key => $value) {
            if (in_array(strtolower($key), $sensitiveHeaders)) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
