<?php

namespace App\Console\Commands;

use App\Infrastructure\Services\Monitoring\AlertingService;
use App\Infrastructure\Services\Monitoring\MetricsCollector;
use App\Infrastructure\Services\Monitoring\StructuredLogger;
use Illuminate\Console\Command;

/**
 * Console command for monitoring system health
 *
 * Runs health checks and sends alerts if thresholds are exceeded
 * Should be run periodically via cron job
 */
class MonitorSystemHealth extends Command
{
 /**
  * The name and signature of the console command.
  */
 protected $signature = 'monitor:health {--alert : Send alerts if issues are found}';

 /**
  * The console command description.
  */
 protected $description = 'Monitor system health and send alerts if necessary';

 /**
  * Execute the console command.
  */
 public function handle(): int
 {
  StructuredLogger::info('Starting system health monitoring', [], 'monitoring');

  try {
   // Get health metrics
   $health = MetricsCollector::getHealthMetrics();

   $this->info('System Health Check Results:');
   $this->displayHealthMetrics($health);

   // Check for issues and send alerts if requested
   if ($this->option('alert')) {
    $this->info('Checking for alert conditions...');

    AlertingService::checkSystemHealth();
    AlertingService::checkBusinessMetrics();

    $this->info('Alert checks completed.');
   }

   // Display current metrics
   $this->displayCurrentMetrics();

   StructuredLogger::info('System health monitoring completed', [
    'health_status' => $this->getOverallHealthStatus($health),
   ], 'monitoring');

   return Command::SUCCESS;
  } catch (\Exception $e) {
   StructuredLogger::error('System health monitoring failed', [
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
   ], 'monitoring');

   $this->error('Health monitoring failed: ' . $e->getMessage());

   return Command::FAILURE;
  }
 }

 /**
  * Display health metrics in console
  */
 private function displayHealthMetrics(array $health): void
 {
  // Database health
  $dbStatus = $health['database']['status'];
  $dbIcon = $dbStatus === 'healthy' ? '✅' : '❌';
  $this->line("Database: {$dbIcon} {$dbStatus}");

  if (isset($health['database']['response_time_ms'])) {
   $this->line("  Response time: {$health['database']['response_time_ms']}ms");
  }

  // Cache health
  $cacheStatus = $health['cache']['status'];
  $cacheIcon = $cacheStatus === 'healthy' ? '✅' : '❌';
  $this->line("Cache: {$cacheIcon} {$cacheStatus}");

  // Disk space
  $diskPercent = $health['disk_space']['used_percent'];
  $diskIcon = $diskPercent > 90 ? '❌' : ($diskPercent > 80 ? '⚠️' : '✅');
  $this->line("Disk Space: {$diskIcon} {$diskPercent}% used");
  $this->line("  Free: " . $this->formatBytes($health['disk_space']['free_bytes']));

  // Memory usage
  $memoryUsed = $health['memory_usage']['current_usage_bytes'];
  $memoryPeak = $health['memory_usage']['peak_usage_bytes'];
  $this->line("Memory: ✅ Current: " . $this->formatBytes($memoryUsed) .
   ", Peak: " . $this->formatBytes($memoryPeak));
 }

 /**
  * Display current application metrics
  */
 private function displayCurrentMetrics(): void
 {
  $this->info('Current Application Metrics:');

  $metrics = MetricsCollector::getAllMetrics();

  if (empty($metrics)) {
   $this->line('No metrics available.');
   return;
  }

  foreach ($metrics as $name => $data) {
   $this->line("📊 {$name}:");

   if (isset($data['count'])) {
    $this->line("  Count: {$data['count']}");
   }

   if (isset($data['average_time'])) {
    $this->line("  Average time: {$data['average_time']}ms");
   }

   if (isset($data['total_errors'])) {
    $this->line("  Total errors: {$data['total_errors']}");
   }

   if (isset($data['last_updated'])) {
    $this->line("  Last updated: {$data['last_updated']}");
   }
  }
 }

 /**
  * Get overall health status
  */
 private function getOverallHealthStatus(array $health): string
 {
  $issues = [];

  if ($health['database']['status'] !== 'healthy') {
   $issues[] = 'database';
  }

  if ($health['cache']['status'] !== 'healthy') {
   $issues[] = 'cache';
  }

  if ($health['disk_space']['used_percent'] > 90) {
   $issues[] = 'disk_space';
  }

  return empty($issues) ? 'healthy' : 'issues_detected';
 }

 /**
  * Format bytes to human readable format
  */
 private function formatBytes(int $bytes): string
 {
  $units = ['B', 'KB', 'MB', 'GB', 'TB'];

  for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
   $bytes /= 1024;
  }

  return round($bytes, 2) . ' ' . $units[$i];
 }
}
