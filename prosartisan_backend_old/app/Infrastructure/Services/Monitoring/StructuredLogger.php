<?php

namespace App\Infrastructure\Services\Monitoring;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Structured logging service with correlation IDs
 *
 * Provides consistent logging format across the application
 * with correlation IDs for request tracing
 */
class StructuredLogger
{
    private static ?string $correlationId = null;

    /**
     * Set correlation ID for current request
     */
    public static function setCorrelationId(?string $correlationId = null): string
    {
        self::$correlationId = $correlationId ?? Str::uuid()->toString();

        return self::$correlationId;
    }

    /**
     * Get current correlation ID
     */
    public static function getCorrelationId(): ?string
    {
        return self::$correlationId;
    }

    /**
     * Log info message with structured context
     */
    public static function info(string $message, array $context = [], ?string $component = null): void
    {
        self::log('info', $message, $context, $component);
    }

    /**
     * Log error message with structured context
     */
    public static function error(string $message, array $context = [], ?string $component = null): void
    {
        self::log('error', $message, $context, $component);
    }

    /**
     * Log warning message with structured context
     */
    public static function warning(string $message, array $context = [], ?string $component = null): void
    {
        self::log('warning', $message, $context, $component);
    }

    /**
     * Log debug message with structured context
     */
    public static function debug(string $message, array $context = [], ?string $component = null): void
    {
        self::log('debug', $message, $context, $component);
    }

    /**
     * Log business event with structured context
     */
    public static function businessEvent(string $eventName, array $context = [], ?string $component = null): void
    {
        $context['event_type'] = 'business';
        $context['event_name'] = $eventName;

        self::log('info', "Business event: {$eventName}", $context, $component);
    }

    /**
     * Log performance metrics
     */
    public static function performance(string $operation, float $durationMs, array $context = []): void
    {
        $context['operation'] = $operation;
        $context['duration_ms'] = $durationMs;
        $context['event_type'] = 'performance';

        self::log('info', "Performance: {$operation} took {$durationMs}ms", $context, 'performance');
    }

    /**
     * Log security event
     */
    public static function security(string $event, array $context = []): void
    {
        $context['event_type'] = 'security';
        $context['security_event'] = $event;

        self::log('warning', "Security event: {$event}", $context, 'security');
    }

    /**
     * Core logging method with structured format
     */
    private static function log(string $level, string $message, array $context = [], ?string $component = null): void
    {
        $structuredContext = [
            'correlation_id' => self::$correlationId,
            'component' => $component,
            'timestamp' => now()->toISOString(),
            'environment' => app()->environment(),
            'user_id' => auth()->id(),
            'request_id' => request()->header('X-Request-ID'),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        // Merge with provided context
        $structuredContext = array_merge($structuredContext, $context);

        // Remove null values
        $structuredContext = array_filter($structuredContext, fn ($value) => $value !== null);

        Log::$level($message, $structuredContext);
    }

    /**
     * Start timing an operation
     */
    public static function startTiming(string $operation): array
    {
        return [
            'operation' => $operation,
            'start_time' => microtime(true),
            'correlation_id' => self::$correlationId,
        ];
    }

    /**
     * End timing an operation and log performance
     */
    public static function endTiming(array $timing, array $additionalContext = []): void
    {
        $duration = (microtime(true) - $timing['start_time']) * 1000; // Convert to milliseconds

        self::performance($timing['operation'], $duration, $additionalContext);
    }
}
