<?php

namespace App\Providers;

use App\Infrastructure\Services\Monitoring\MetricsCollector;
use App\Infrastructure\Services\Monitoring\StructuredLogger;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for monitoring and logging services
 */
class MonitoringServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register monitoring services as singletons
        $this->app->singleton(StructuredLogger::class);
        $this->app->singleton(MetricsCollector::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Set up database query monitoring
        if (config('monitoring.performance.track_database_queries', true)) {
            DB::listen(function (QueryExecuted $query) {
                $this->trackDatabaseQuery($query);
            });
        }

        // Set up correlation ID for each request
        if (app()->runningInConsole() === false) {
            StructuredLogger::setCorrelationId();
        }
    }

    /**
     * Track database query performance
     */
    private function trackDatabaseQuery(QueryExecuted $query): void
    {
        $executionTime = $query->time;

        // Record the metric
        MetricsCollector::recordDatabaseQuery($query->sql, $executionTime);

        // Log slow queries
        $slowQueryThreshold = config('monitoring.metrics.slow_query_threshold_ms', 100);

        if ($executionTime > $slowQueryThreshold) {
            StructuredLogger::warning('Slow database query detected', [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'execution_time_ms' => $executionTime,
                'connection' => $query->connectionName,
                'threshold_ms' => $slowQueryThreshold,
            ], 'database');
        }

        // Log all queries in debug mode
        if (config('app.debug') && config('logging.level') === 'debug') {
            StructuredLogger::debug('Database query executed', [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'execution_time_ms' => $executionTime,
                'connection' => $query->connectionName,
            ], 'database');
        }
    }
}
