<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Console command to analyze database performance and suggest optimizations
 *
 * Requirements: 17.5
 */
class AnalyzeDatabasePerformance extends Command
{
 /**
  * The name and signature of the console command.
  *
  * @var string
  */
 protected $signature = 'db:analyze-performance {--table= : Analyze specific table}';

 /**
  * The console command description.
  *
  * @var string
  */
 protected $description = 'Analyze database performance and suggest optimizations';

 /**
  * Execute the console command.
  */
 public function handle(): int
 {
  $this->info('🔍 Analyzing database performance...');

  $specificTable = $this->option('table');

  if ($specificTable) {
   $this->analyzeTable($specificTable);
  } else {
   $this->analyzeAllTables();
  }

  return Command::SUCCESS;
 }

 /**
  * Analyze all important tables
  */
 private function analyzeAllTables(): void
 {
  $tables = [
   'users',
   'artisan_profiles',
   'missions',
   'devis',
   'transactions',
   'sequestres',
   'jetons_materiel',
   'chantiers',
   'jalons'
  ];

  foreach ($tables as $table) {
   if (DB::getSchemaBuilder()->hasTable($table)) {
    $this->analyzeTable($table);
   }
  }

  $this->showPerformanceRecommendations();
 }

 /**
  * Analyze a specific table
  */
 private function analyzeTable(string $table): void
 {
  $this->info("\n📊 Analyzing table: {$table}");

  try {
   // Get table size and row count
   $stats = $this->getTableStats($table);

   $this->table(
    ['Metric', 'Value'],
    [
     ['Row Count', number_format($stats['row_count'])],
     ['Table Size', $stats['table_size']],
     ['Index Size', $stats['index_size']],
     ['Total Size', $stats['total_size']],
    ]
   );

   // Show indexes
   $this->showTableIndexes($table);

   // Show slow queries if available
   $this->checkSlowQueries($table);
  } catch (\Exception $e) {
   $this->error("Error analyzing table {$table}: " . $e->getMessage());
  }
 }

 /**
  * Get table statistics
  */
 private function getTableStats(string $table): array
 {
  if (DB::getDriverName() === 'pgsql') {
   $result = DB::select("
                SELECT
                    schemaname,
                    tablename,
                    attname,
                    n_distinct,
                    correlation
                FROM pg_stats
                WHERE tablename = ?
                LIMIT 1
            ", [$table]);

   $sizeResult = DB::select("
                SELECT
                    pg_size_pretty(pg_total_relation_size(?)) as total_size,
                    pg_size_pretty(pg_relation_size(?)) as table_size,
                    pg_size_pretty(pg_total_relation_size(?) - pg_relation_size(?)) as index_size
            ", [$table, $table, $table, $table]);

   $countResult = DB::select("SELECT COUNT(*) as count FROM {$table}");

   return [
    'row_count' => $countResult[0]->count ?? 0,
    'table_size' => $sizeResult[0]->table_size ?? 'N/A',
    'index_size' => $sizeResult[0]->index_size ?? 'N/A',
    'total_size' => $sizeResult[0]->total_size ?? 'N/A',
   ];
  } else {
   // For SQLite/MySQL
   $countResult = DB::select("SELECT COUNT(*) as count FROM {$table}");

   return [
    'row_count' => $countResult[0]->count ?? 0,
    'table_size' => 'N/A (SQLite)',
    'index_size' => 'N/A (SQLite)',
    'total_size' => 'N/A (SQLite)',
   ];
  }
 }

 /**
  * Show table indexes
  */
 private function showTableIndexes(string $table): void
 {
  if (DB::getDriverName() === 'pgsql') {
   $indexes = DB::select("
                SELECT
                    indexname,
                    indexdef
                FROM pg_indexes
                WHERE tablename = ?
                ORDER BY indexname
            ", [$table]);

   if (!empty($indexes)) {
    $this->info("📋 Indexes for {$table}:");
    foreach ($indexes as $index) {
     $this->line("  • {$index->indexname}");
    }
   }
  } else {
   $this->info("📋 Index analysis not available for SQLite");
  }
 }

 /**
  * Check for slow queries related to the table
  */
 private function checkSlowQueries(string $table): void
 {
  if (DB::getDriverName() === 'pgsql') {
   // This would require pg_stat_statements extension
   $this->info("💡 Enable pg_stat_statements extension for query performance analysis");
  }
 }

 /**
  * Show performance recommendations
  */
 private function showPerformanceRecommendations(): void
 {
  $this->info("\n🚀 Performance Recommendations:");

  $recommendations = [
   "✅ Ensure frequently queried columns have indexes",
   "✅ Use composite indexes for multi-column WHERE clauses",
   "✅ Consider partial indexes for filtered queries",
   "✅ Monitor query execution plans with EXPLAIN",
   "✅ Use PostGIS spatial indexes for location queries",
   "✅ Implement connection pooling for high concurrency",
   "✅ Consider read replicas for read-heavy workloads",
   "✅ Regular VACUUM and ANALYZE on PostgreSQL",
   "✅ Monitor slow query logs",
   "✅ Use appropriate data types (UUID vs BIGINT)",
  ];

  foreach ($recommendations as $recommendation) {
   $this->line($recommendation);
  }

  $this->info("\n📈 Monitoring Commands:");
  $this->line("• php artisan db:analyze-performance --table=users");
  $this->line("• EXPLAIN ANALYZE SELECT ... (in psql)");
  $this->line("• SELECT * FROM pg_stat_user_tables; (PostgreSQL)");
 }
}
