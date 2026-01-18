<?php

namespace App\Infrastructure\Services\Monitoring;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

/**
 * Alerting service for critical system failures
 *
 * Sends alerts when critical thresholds are exceeded or
 * when critical system failures occur
 */
class AlertingService
{
 private const ALERT_COOLDOWN = 300; // 5 minutes cooldown between same alerts

 /**
  * Send critical alert
  */
 public static function sendCriticalAlert(string $alertType, string $message, array $context = []): void
 {
  // Check cooldown to prevent spam
  if (self::isInCooldown($alertType)) {
   return;
  }

  $alert = [
   'type' => $alertType,
   'severity' => 'critical',
   'message' => $message,
   'context' => $context,
   'timestamp' => now()->toISOString(),
   'environment' => app()->environment(),
   'server' => gethostname(),
  ];

  // Log the alert
  StructuredLogger::error("CRITICAL ALERT: {$alertType}", $alert, 'alerting');

  // Send email alert if configured
  self::sendEmailAlert($alert);

  // Send to external monitoring service if configured
  self::sendToExternalMonitoring($alert);

  // Set cooldown
  self::setCooldown($alertType);
 }

 /**
  * Send warning alert
  */
 public static function sendWarningAlert(string $alertType, string $message, array $context = []): void
 {
  // Check cooldown to prevent spam
  if (self::isInCooldown($alertType)) {
   return;
  }

  $alert = [
   'type' => $alertType,
   'severity' => 'warning',
   'message' => $message,
   'context' => $context,
   'timestamp' => now()->toISOString(),
   'environment' => app()->environment(),
   'server' => gethostname(),
  ];

  // Log the alert
  StructuredLogger::warning("WARNING ALERT: {$alertType}", $alert, 'alerting');

  // Send to external monitoring service if configured
  self::sendToExternalMonitoring($alert);

  // Set cooldown
  self::setCooldown($alertType);
 }

 /**
  * Check if alert type is in cooldown period
  */
 private static function isInCooldown(string $alertType): bool
 {
  $lastAlertTime = cache()->get("alert_cooldown:{$alertType}");

  if (!$lastAlertTime) {
   return false;
  }

  return (time() - $lastAlertTime) < self::ALERT_COOLDOWN;
 }

 /**
  * Set cooldown for alert type
  */
 private static function setCooldown(string $alertType): void
 {
  cache()->put("alert_cooldown:{$alertType}", time(), self::ALERT_COOLDOWN);
 }

 /**
  * Send email alert
  */
 private static function sendEmailAlert(array $alert): void
 {
  try {
   $recipients = config('monitoring.alert_emails', []);

   if (empty($recipients)) {
    return;
   }

   // In a real implementation, you would create a proper email template
   // For now, we'll just log that an email would be sent
   StructuredLogger::info("Email alert would be sent", [
    'recipients' => $recipients,
    'alert' => $alert,
   ], 'alerting');
  } catch (\Exception $e) {
   Log::error("Failed to send email alert: " . $e->getMessage());
  }
 }

 /**
  * Send to external monitoring service
  */
 private static function sendToExternalMonitoring(array $alert): void
 {
  try {
   $webhookUrl = config('monitoring.webhook_url');

   if (!$webhookUrl) {
    return;
   }

   // In a real implementation, you would send HTTP request to monitoring service
   // For now, we'll just log that it would be sent
   StructuredLogger::info("Alert would be sent to external monitoring", [
    'webhook_url' => $webhookUrl,
    'alert' => $alert,
   ], 'alerting');
  } catch (\Exception $e) {
   Log::error("Failed to send alert to external monitoring: " . $e->getMessage());
  }
 }

 /**
  * Predefined alert types with thresholds
  */
 public static function checkSystemHealth(): void
 {
  $health = MetricsCollector::getHealthMetrics();

  // Check database health
  if ($health['database']['status'] === 'unhealthy') {
   self::sendCriticalAlert(
    'database_down',
    'Database is not responding',
    $health['database']
   );
  }

  // Check cache health
  if ($health['cache']['status'] === 'unhealthy') {
   self::sendWarningAlert(
    'cache_down',
    'Cache system is not responding',
    $health['cache']
   );
  }

  // Check disk space
  if ($health['disk_space']['used_percent'] > 90) {
   self::sendCriticalAlert(
    'disk_space_low',
    "Disk space usage is at {$health['disk_space']['used_percent']}%",
    $health['disk_space']
   );
  } elseif ($health['disk_space']['used_percent'] > 80) {
   self::sendWarningAlert(
    'disk_space_warning',
    "Disk space usage is at {$health['disk_space']['used_percent']}%",
    $health['disk_space']
   );
  }

  // Check memory usage
  $memoryUsagePercent = ($health['memory_usage']['current_usage_bytes'] / $health['memory_usage']['limit_bytes']) * 100;

  if ($memoryUsagePercent > 90) {
   self::sendCriticalAlert(
    'memory_usage_high',
    "Memory usage is at {$memoryUsagePercent}%",
    $health['memory_usage']
   );
  }
 }

 /**
  * Check business metrics for anomalies
  */
 public static function checkBusinessMetrics(): void
 {
  // Check error rates
  $errorMetrics = MetricsCollector::getMetric('errors');

  if ($errorMetrics && isset($errorMetrics['total_errors'])) {
   $errorRate = $errorMetrics['total_errors'] / max($errorMetrics['total_requests'] ?? 1, 1);

   if ($errorRate > 0.1) { // 10% error rate
    self::sendCriticalAlert(
     'high_error_rate',
     "Error rate is {$errorRate}%",
     $errorMetrics
    );
   }
  }

  // Check response times
  $responseMetrics = MetricsCollector::getMetric('response_time');

  if ($responseMetrics && isset($responseMetrics['average_time'])) {
   if ($responseMetrics['average_time'] > 5000) { // 5 seconds
    self::sendWarningAlert(
     'slow_response_times',
     "Average response time is {$responseMetrics['average_time']}ms",
     $responseMetrics
    );
   }
  }
 }
}
