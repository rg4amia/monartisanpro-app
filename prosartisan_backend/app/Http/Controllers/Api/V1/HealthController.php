<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Services\Monitoring\MetricsCollector;
use App\Infrastructure\Services\Monitoring\StructuredLogger;
use Illuminate\Http\JsonResponse;

/**
 * Health check controller for monitoring system status
 *
 * Provides endpoints for health checks, metrics, and system status
 */
class HealthController extends Controller
{
 /**
  * Basic health check endpoint
  *
  * Returns simple status for load balancers and monitoring tools
  */
 public function health(): JsonResponse
 {
  return response()->json([
   'status' => 'healthy',
   'timestamp' => now()->toISOString(),
   'service' => 'prosartisan-api',
   'version' => config('app.version', '1.0.0'),
  ]);
 }

 /**
  * Detailed health check with system metrics
  *
  * Returns comprehensive health information including database,
  * cache, disk space, and memory usage
  */
 public function detailed(): JsonResponse
 {
  try {
   $health = MetricsCollector::getHealthMetrics();

   $overallStatus = $this->determineOverallStatus($health);

   StructuredLogger::info('Health check requested', [
    'overall_status' => $overallStatus,
    'endpoint' => 'detailed',
   ], 'health_check');

   return response()->json([
    'status' => $overallStatus,
    'timestamp' => now()->toISOString(),
    'service' => 'prosartisan-api',
    'version' => config('app.version', '1.0.0'),
    'checks' => $health,
   ]);
  } catch (\Exception $e) {
   StructuredLogger::error('Health check failed', [
    'error' => $e->getMessage(),
    'endpoint' => 'detailed',
   ], 'health_check');

   return response()->json([
    'status' => 'unhealthy',
    'timestamp' => now()->toISOString(),
    'service' => 'prosartisan-api',
    'error' => 'Health check failed',
   ], 503);
  }
 }

 /**
  * Get application metrics
  *
  * Returns current application metrics for monitoring dashboards
  */
 public function metrics(): JsonResponse
 {
  try {
   $metrics = MetricsCollector::getAllMetrics();

   StructuredLogger::info('Metrics requested', [
    'metrics_count' => count($metrics),
   ], 'metrics');

   return response()->json([
    'timestamp' => now()->toISOString(),
    'service' => 'prosartisan-api',
    'metrics' => $metrics,
   ]);
  } catch (\Exception $e) {
   StructuredLogger::error('Metrics retrieval failed', [
    'error' => $e->getMessage(),
   ], 'metrics');

   return response()->json([
    'error' => 'Failed to retrieve metrics',
    'timestamp' => now()->toISOString(),
   ], 500);
  }
 }

 /**
  * Get specific metric
  *
  * Returns data for a specific metric by name
  */
 public function metric(string $name): JsonResponse
 {
  try {
   $metric = MetricsCollector::getMetric($name);

   if (!$metric) {
    return response()->json([
     'error' => 'Metric not found',
     'metric_name' => $name,
     'timestamp' => now()->toISOString(),
    ], 404);
   }

   StructuredLogger::info('Specific metric requested', [
    'metric_name' => $name,
   ], 'metrics');

   return response()->json([
    'timestamp' => now()->toISOString(),
    'service' => 'prosartisan-api',
    'metric_name' => $name,
    'data' => $metric,
   ]);
  } catch (\Exception $e) {
   StructuredLogger::error('Specific metric retrieval failed', [
    'error' => $e->getMessage(),
    'metric_name' => $name,
   ], 'metrics');

   return response()->json([
    'error' => 'Failed to retrieve metric',
    'metric_name' => $name,
    'timestamp' => now()->toISOString(),
   ], 500);
  }
 }

 /**
  * Clear all metrics (for testing/debugging)
  *
  * Only available in non-production environments
  */
 public function clearMetrics(): JsonResponse
 {
  if (app()->environment('production')) {
   return response()->json([
    'error' => 'Not available in production',
    'timestamp' => now()->toISOString(),
   ], 403);
  }

  try {
   MetricsCollector::clearMetrics();

   StructuredLogger::info('Metrics cleared', [], 'metrics');

   return response()->json([
    'message' => 'All metrics cleared',
    'timestamp' => now()->toISOString(),
   ]);
  } catch (\Exception $e) {
   StructuredLogger::error('Failed to clear metrics', [
    'error' => $e->getMessage(),
   ], 'metrics');

   return response()->json([
    'error' => 'Failed to clear metrics',
    'timestamp' => now()->toISOString(),
   ], 500);
  }
 }

 /**
  * Determine overall system status based on health checks
  */
 private function determineOverallStatus(array $health): string
 {
  // Critical checks that make the system unhealthy
  if ($health['database']['status'] !== 'healthy') {
   return 'unhealthy';
  }

  // Warning conditions that make the system degraded
  $warnings = [];

  if ($health['cache']['status'] !== 'healthy') {
   $warnings[] = 'cache';
  }

  if ($health['disk_space']['used_percent'] > 90) {
   $warnings[] = 'disk_space';
  }

  if (
   isset($health['memory_usage']['limit_bytes']) &&
   $health['memory_usage']['limit_bytes'] > 0
  ) {
   $memoryUsagePercent = ($health['memory_usage']['current_usage_bytes'] / $health['memory_usage']['limit_bytes']) * 100;

   if ($memoryUsagePercent > 90) {
    $warnings[] = 'memory';
   }
  }

  if (!empty($warnings)) {
   return 'degraded';
  }

  return 'healthy';
 }
}
