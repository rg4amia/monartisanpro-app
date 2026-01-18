<?php

namespace App\Infrastructure\Services\Monitoring;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Metrics collection service for monitoring application performance
 *
 * Collects and tracks various application metrics including:
 * - Response times
 * - Error rates
 * - Database query performance
 * - Business metrics
 */
class MetricsCollector
{
  private const METRICS_PREFIX = 'metrics:';
  private const METRICS_TTL = 3600; // 1 hour

  /**
   * Record response time metric
   */
  public static function recordResponseTime(string $endpoint, float $responseTimeMs): void
  {
    $key = self::METRICS_PREFIX . 'response_time:' . $endpoint;

    // Track the key for non-Redis stores
    self::trackMetricKey($key);

    // Get existing metrics
    $metrics = Cache::get($key, [
      'count' => 0,
      'total_time' => 0,
      'min_time' => PHP_FLOAT_MAX,
      'max_time' => 0,
      'last_updated' => now()->toISOString(),
    ]);

    // Update metrics
    $metrics['count']++;
    $metrics['total_time'] += $responseTimeMs;
    $metrics['min_time'] = min($metrics['min_time'], $responseTimeMs);
    $metrics['max_time'] = max($metrics['max_time'], $responseTimeMs);
    $metrics['average_time'] = $metrics['total_time'] / $metrics['count'];
    $metrics['last_updated'] = now()->toISOString();

    Cache::put($key, $metrics, self::METRICS_TTL);

    // Log if response time is slow (> 2 seconds)
    if ($responseTimeMs > 2000) {
      StructuredLogger::warning("Slow response detected", [
        'endpoint' => $endpoint,
        'response_time_ms' => $responseTimeMs,
        'threshold_ms' => 2000,
      ], 'performance');
    }
  }

  /**
   * Record error occurrence
   */
  public static function recordError(string $errorType, string $endpoint, ?string $errorMessage = null): void
  {
    $key = self::METRICS_PREFIX . 'errors:' . $endpoint;

    $metrics = Cache::get($key, [
      'total_errors' => 0,
      'error_types' => [],
      'last_error' => null,
      'last_updated' => now()->toISOString(),
    ]);

    $metrics['total_errors']++;
    $metrics['error_types'][$errorType] = ($metrics['error_types'][$errorType] ?? 0) + 1;
    $metrics['last_error'] = [
      'type' => $errorType,
      'message' => $errorMessage,
      'timestamp' => now()->toISOString(),
    ];
    $metrics['last_updated'] = now()->toISOString();

    Cache::put($key, $metrics, self::METRICS_TTL);

    StructuredLogger::error("Error recorded", [
      'error_type' => $errorType,
      'endpoint' => $endpoint,
      'error_message' => $errorMessage,
    ], 'error_tracking');
  }

  /**
   * Record database query performance
   */
  public static function recordDatabaseQuery(string $query, float $executionTimeMs): void
  {
    $key = self::METRICS_PREFIX . 'database:queries';

    $metrics = Cache::get($key, [
      'total_queries' => 0,
      'total_time' => 0,
      'slow_queries' => 0,
      'last_updated' => now()->toISOString(),
    ]);

    $metrics['total_queries']++;
    $metrics['total_time'] += $executionTimeMs;
    $metrics['average_time'] = $metrics['total_time'] / $metrics['total_queries'];

    // Track slow queries (> 100ms)
    if ($executionTimeMs > 100) {
      $metrics['slow_queries']++;

      StructuredLogger::warning("Slow database query detected", [
        'query' => substr($query, 0, 200), // Truncate long queries
        'execution_time_ms' => $executionTimeMs,
        'threshold_ms' => 100,
      ], 'database');
    }

    $metrics['last_updated'] = now()->toISOString();
    Cache::put($key, $metrics, self::METRICS_TTL);
  }

  /**
   * Record business metric
   */
  public static function recordBusinessMetric(string $metricName, $value, array $tags = []): void
  {
    $key = self::METRICS_PREFIX . 'business:' . $metricName;

    $metrics = Cache::get($key, [
      'count' => 0,
      'total_value' => 0,
      'last_value' => null,
      'tags' => [],
      'last_updated' => now()->toISOString(),
    ]);

    $metrics['count']++;

    if (is_numeric($value)) {
      $metrics['total_value'] += $value;
      $metrics['average_value'] = $metrics['total_value'] / $metrics['count'];
    }

    $metrics['last_value'] = $value;
    $metrics['tags'] = array_merge($metrics['tags'], $tags);
    $metrics['last_updated'] = now()->toISOString();

    Cache::put($key, $metrics, self::METRICS_TTL);

    StructuredLogger::businessEvent($metricName, [
      'value' => $value,
      'tags' => $tags,
    ], 'business_metrics');
  }

  /**
   * Get all metrics
   */
  public static function getAllMetrics(): array
  {
    try {
      // Check if we're using Redis cache
      if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
        $keys = Cache::getRedis()->keys(self::METRICS_PREFIX . '*');
      } else {
        // For non-Redis stores, we'll need to track keys manually
        // This is a simplified approach - in production you might want to use a dedicated metrics store
        $keys = self::getTrackedMetricKeys();
      }

      $metrics = [];

      foreach ($keys as $key) {
        $metricName = str_replace(self::METRICS_PREFIX, '', $key);
        $metrics[$metricName] = Cache::get($key);
      }

      return $metrics;
    } catch (\Exception $e) {
      // If we can't get metrics, return empty array
      return [];
    }
  }

  /**
   * Get specific metric
   */
  public static function getMetric(string $metricName): ?array
  {
    return Cache::get(self::METRICS_PREFIX . $metricName);
  }

  /**
   * Clear all metrics
   */
  public static function clearMetrics(): void
  {
    try {
      if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
        $keys = Cache::getRedis()->keys(self::METRICS_PREFIX . '*');
        foreach ($keys as $key) {
          Cache::forget($key);
        }
      } else {
        $keys = self::getTrackedMetricKeys();
        foreach ($keys as $key) {
          Cache::forget($key);
        }
        // Clear the keys index
        Cache::forget(self::METRICS_PREFIX . 'keys');
      }
    } catch (\Exception $e) {
      // If we can't clear metrics, just continue
    }
  }

  /**
   * Get tracked metric keys (for non-Redis cache stores)
   */
  private static function getTrackedMetricKeys(): array
  {
    // This is a simplified implementation
    // In production, you might want to maintain a separate index of metric keys
    return Cache::get(self::METRICS_PREFIX . 'keys', []);
  }

  /**
   * Track a metric key (for non-Redis cache stores)
   */
  private static function trackMetricKey(string $key): void
  {
    if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
      return; // Redis doesn't need key tracking
    }

    $keys = Cache::get(self::METRICS_PREFIX . 'keys', []);
    if (!in_array($key, $keys)) {
      $keys[] = $key;
      Cache::put(self::METRICS_PREFIX . 'keys', $keys, self::METRICS_TTL);
    }
  }

  /**
   * Get system health metrics
   */
  public static function getHealthMetrics(): array
  {
    return [
      'database' => self::getDatabaseHealth(),
      'cache' => self::getCacheHealth(),
      'disk_space' => self::getDiskSpaceHealth(),
      'memory_usage' => self::getMemoryUsage(),
      'timestamp' => now()->toISOString(),
    ];
  }

  /**
   * Check database health
   */
  private static function getDatabaseHealth(): array
  {
    try {
      $start = microtime(true);
      DB::select('SELECT 1');
      $responseTime = (microtime(true) - $start) * 1000;

      return [
        'status' => 'healthy',
        'response_time_ms' => $responseTime,
      ];
    } catch (\Exception $e) {
      return [
        'status' => 'unhealthy',
        'error' => $e->getMessage(),
      ];
    }
  }

  /**
   * Check cache health
   */
  private static function getCacheHealth(): array
  {
    try {
      $testKey = 'health_check_' . time();
      Cache::put($testKey, 'test', 10);
      $value = Cache::get($testKey);
      Cache::forget($testKey);

      return [
        'status' => $value === 'test' ? 'healthy' : 'unhealthy',
      ];
    } catch (\Exception $e) {
      return [
        'status' => 'unhealthy',
        'error' => $e->getMessage(),
      ];
    }
  }

  /**
   * Check disk space
   */
  private static function getDiskSpaceHealth(): array
  {
    $freeBytes = disk_free_space('/');
    $totalBytes = disk_total_space('/');
    $usedPercent = (($totalBytes - $freeBytes) / $totalBytes) * 100;

    return [
      'free_bytes' => $freeBytes,
      'total_bytes' => $totalBytes,
      'used_percent' => round($usedPercent, 2),
      'status' => $usedPercent > 90 ? 'warning' : 'healthy',
    ];
  }

  /**
   * Get memory usage
   */
  private static function getMemoryUsage(): array
  {
    return [
      'current_usage_bytes' => memory_get_usage(true),
      'peak_usage_bytes' => memory_get_peak_usage(true),
      'limit_bytes' => self::getMemoryLimit(),
    ];
  }

  /**
   * Get memory limit in bytes
   */
  private static function getMemoryLimit(): int
  {
    $limit = ini_get('memory_limit');

    if ($limit === '-1') {
      return -1; // No limit
    }

    $unit = strtolower(substr($limit, -1));
    $value = (int) substr($limit, 0, -1);

    switch ($unit) {
      case 'g':
        return $value * 1024 * 1024 * 1024;
      case 'm':
        return $value * 1024 * 1024;
      case 'k':
        return $value * 1024;
      default:
        return (int) $limit;
    }
  }
}
