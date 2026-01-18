<?php

return [
 /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for application monitoring, logging, and alerting
    |
    */

 /*
    |--------------------------------------------------------------------------
    | Structured Logging
    |--------------------------------------------------------------------------
    |
    | Enable/disable structured logging with correlation IDs
    |
    */
 'structured_logging' => [
  'enabled' => env('STRUCTURED_LOGGING_ENABLED', true),
  'correlation_id_header' => 'X-Correlation-ID',
  'include_request_body' => env('LOG_REQUEST_BODY', false),
  'include_response_body' => env('LOG_RESPONSE_BODY', false),
 ],

 /*
    |--------------------------------------------------------------------------
    | Metrics Collection
    |--------------------------------------------------------------------------
    |
    | Configuration for metrics collection and storage
    |
    */
 'metrics' => [
  'enabled' => env('METRICS_ENABLED', true),
  'ttl' => env('METRICS_TTL', 3600), // 1 hour
  'slow_query_threshold_ms' => env('SLOW_QUERY_THRESHOLD', 100),
  'slow_response_threshold_ms' => env('SLOW_RESPONSE_THRESHOLD', 2000),
 ],

 /*
    |--------------------------------------------------------------------------
    | Alerting Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for system alerts and notifications
    |
    */
 'alerting' => [
  'enabled' => env('ALERTING_ENABLED', true),
  'cooldown_seconds' => env('ALERT_COOLDOWN', 300), // 5 minutes

  // Email alerts
  'email' => [
   'enabled' => env('EMAIL_ALERTS_ENABLED', false),
   'recipients' => explode(',', env('ALERT_EMAIL_RECIPIENTS', '')),
   'from_address' => env('ALERT_FROM_EMAIL', 'alerts@prosartisan.com'),
   'from_name' => env('ALERT_FROM_NAME', 'ProSartisan Alerts'),
  ],

  // Webhook alerts (for external monitoring services)
  'webhook' => [
   'enabled' => env('WEBHOOK_ALERTS_ENABLED', false),
   'url' => env('ALERT_WEBHOOK_URL'),
   'timeout' => env('ALERT_WEBHOOK_TIMEOUT', 10),
   'headers' => [
    'Content-Type' => 'application/json',
    'User-Agent' => 'ProSartisan-Monitoring/1.0',
   ],
  ],
 ],

 /*
    |--------------------------------------------------------------------------
    | Health Check Thresholds
    |--------------------------------------------------------------------------
    |
    | Thresholds for various health checks and alerts
    |
    */
 'thresholds' => [
  'disk_space' => [
   'warning_percent' => env('DISK_WARNING_THRESHOLD', 80),
   'critical_percent' => env('DISK_CRITICAL_THRESHOLD', 90),
  ],

  'memory_usage' => [
   'warning_percent' => env('MEMORY_WARNING_THRESHOLD', 80),
   'critical_percent' => env('MEMORY_CRITICAL_THRESHOLD', 90),
  ],

  'response_time' => [
   'warning_ms' => env('RESPONSE_WARNING_THRESHOLD', 2000),
   'critical_ms' => env('RESPONSE_CRITICAL_THRESHOLD', 5000),
  ],

  'error_rate' => [
   'warning_percent' => env('ERROR_WARNING_THRESHOLD', 5),
   'critical_percent' => env('ERROR_CRITICAL_THRESHOLD', 10),
  ],

  'database' => [
   'response_time_warning_ms' => env('DB_RESPONSE_WARNING_THRESHOLD', 100),
   'response_time_critical_ms' => env('DB_RESPONSE_CRITICAL_THRESHOLD', 500),
  ],
 ],

 /*
    |--------------------------------------------------------------------------
    | Business Metrics
    |--------------------------------------------------------------------------
    |
    | Configuration for business-specific metrics tracking
    |
    */
 'business_metrics' => [
  'enabled' => env('BUSINESS_METRICS_ENABLED', true),

  // Track these business events
  'events' => [
   'user_registration',
   'mission_created',
   'quote_submitted',
   'quote_accepted',
   'payment_processed',
   'milestone_validated',
   'dispute_reported',
   'jeton_generated',
   'jeton_validated',
  ],

  // Financial metrics
  'financial' => [
   'track_amounts' => env('TRACK_FINANCIAL_AMOUNTS', true),
   'currency' => 'XOF',
  ],
 ],

 /*
    |--------------------------------------------------------------------------
    | Performance Monitoring
    |--------------------------------------------------------------------------
    |
    | Configuration for performance monitoring and profiling
    |
    */
 'performance' => [
  'enabled' => env('PERFORMANCE_MONITORING_ENABLED', true),
  'sample_rate' => env('PERFORMANCE_SAMPLE_RATE', 1.0), // 100% sampling
  'track_database_queries' => env('TRACK_DB_QUERIES', true),
  'track_external_requests' => env('TRACK_EXTERNAL_REQUESTS', true),
  'track_queue_jobs' => env('TRACK_QUEUE_JOBS', true),
 ],

 /*
    |--------------------------------------------------------------------------
    | Security Monitoring
    |--------------------------------------------------------------------------
    |
    | Configuration for security event monitoring
    |
    */
 'security' => [
  'enabled' => env('SECURITY_MONITORING_ENABLED', true),

  // Events to monitor
  'events' => [
   'failed_login_attempts',
   'suspicious_activity',
   'rate_limit_exceeded',
   'unauthorized_access',
   'fraud_detection',
  ],

  // Thresholds
  'failed_login_threshold' => env('FAILED_LOGIN_THRESHOLD', 5),
  'rate_limit_threshold' => env('RATE_LIMIT_THRESHOLD', 100),
 ],

 /*
    |--------------------------------------------------------------------------
    | Log Retention
    |--------------------------------------------------------------------------
    |
    | Configuration for log retention and cleanup
    |
    */
 'log_retention' => [
  'enabled' => env('LOG_RETENTION_ENABLED', true),
  'days' => env('LOG_RETENTION_DAYS', 30),
  'cleanup_schedule' => env('LOG_CLEANUP_SCHEDULE', '0 2 * * *'), // Daily at 2 AM
 ],
];
