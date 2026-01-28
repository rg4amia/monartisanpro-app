# Database Performance and Indexing Strategy

## Overview

This document outlines the database performance optimization strategy for the ProSartisan platform, including indexing guidelines, query optimization, and monitoring practices.

**Requirements**: 17.5 - Index frequently queried fields and add PostGIS spatial indexes

## Indexing Strategy

### Primary Indexes

All tables have the following standard indexes:

1. **Primary Keys**: UUID columns with clustered indexes
2. **Foreign Keys**: All foreign key columns are indexed
3. **Status Fields**: All status enum columns are indexed
4. **Timestamp Fields**: `created_at`, `updated_at`, and other date fields

### Composite Indexes

Strategic composite indexes for common query patterns:

#### Users Table
```sql
-- Active users by type
CREATE INDEX idx_users_type_status ON users(user_type, account_status);

-- Phone number lookups by type
CREATE INDEX idx_users_phone_type ON users(phone_number, user_type);
```

#### Missions Table
```sql
-- Open missions by category
CREATE INDEX idx_missions_status_category ON missions(status, trade_category);

-- Client missions by status
CREATE INDEX idx_missions_client_status ON missions(client_id, status);
```

#### Transactions Table
```sql
-- User transaction history
CREATE INDEX idx_transactions_from_user_date ON transactions(from_user_id, created_at);
CREATE INDEX idx_transactions_to_user_date ON transactions(to_user_id, created_at);

-- Status-based queries
CREATE INDEX idx_transactions_status_date ON transactions(status, created_at);
```

### Spatial Indexes (PostGIS)

For geospatial queries using PostGIS:

```sql
-- Artisan location searches
CREATE INDEX idx_artisan_location ON artisan_profiles USING GIST(location);

-- Mission location searches
CREATE INDEX idx_missions_location ON missions USING GIST(location);

-- Supplier location searches
CREATE INDEX idx_fournisseur_location ON fournisseur_profiles USING GIST(shop_location);
```

### Specialized Indexes

#### Performance-Critical Indexes

1. **Jeton Expiration**: For cleanup jobs
   ```sql
   CREATE INDEX idx_jetons_status_expires ON jetons_materiel(status, expires_at);
   ```

2. **Auto-Validation Deadline**: For cron jobs
   ```sql
   CREATE INDEX idx_jalons_auto_validation ON jalons(auto_validation_deadline);
   ```

3. **KYC Verification Status**: For access control
   ```sql
   CREATE INDEX idx_kyc_user_status ON kyc_verifications(user_id, verification_status);
   ```

## Query Optimization Guidelines

### 1. Use Appropriate WHERE Clauses

**Good**: Use indexed columns in WHERE clauses
```sql
SELECT * FROM missions 
WHERE status = 'OPEN' 
  AND trade_category = 'PLUMBER'
  AND created_at > '2024-01-01';
```

**Bad**: Functions on indexed columns
```sql
-- This prevents index usage
SELECT * FROM missions WHERE UPPER(status) = 'OPEN';
```

### 2. Leverage Composite Indexes

**Good**: Match composite index column order
```sql
-- Uses idx_missions_status_category
SELECT * FROM missions 
WHERE status = 'OPEN' AND trade_category = 'PLUMBER';
```

**Bad**: Wrong column order
```sql
-- May not use the composite index efficiently
SELECT * FROM missions 
WHERE trade_category = 'PLUMBER' AND status = 'OPEN';
```

### 3. Spatial Query Optimization

**Good**: Use PostGIS functions with spatial indexes
```sql
SELECT * FROM artisan_profiles 
WHERE ST_DWithin(
    location, 
    ST_SetSRID(ST_MakePoint(-4.0083, 5.3600), 4326)::geography, 
    1000
);
```

**Bad**: Calculate distance without spatial functions
```sql
-- This won't use spatial indexes
SELECT * FROM artisan_profiles 
WHERE latitude BETWEEN 5.35 AND 5.37 
  AND longitude BETWEEN -4.02 AND -4.00;
```

## Performance Monitoring

### 1. Query Analysis Commands

```bash
# Analyze database performance
php artisan db:analyze-performance

# Analyze specific table
php artisan db:analyze-performance --table=missions

# Warm up caches
php artisan cache:warm-up
```

### 2. PostgreSQL Monitoring Queries

```sql
-- Check index usage
SELECT schemaname, tablename, attname, n_distinct, correlation
FROM pg_stats 
WHERE tablename = 'missions';

-- Check table sizes
SELECT 
    schemaname,
    tablename,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) as size
FROM pg_tables 
WHERE schemaname = 'public'
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;

-- Check slow queries (requires pg_stat_statements)
SELECT query, calls, total_time, mean_time
FROM pg_stat_statements 
ORDER BY mean_time DESC 
LIMIT 10;
```

### 3. Index Usage Statistics

```sql
-- Check index usage
SELECT 
    schemaname,
    tablename,
    indexname,
    idx_tup_read,
    idx_tup_fetch
FROM pg_stat_user_indexes
ORDER BY idx_tup_read DESC;
```

## Caching Strategy

### 1. Application-Level Caching

- **Artisan Profiles**: 5-minute TTL
- **Static Data**: 1-hour TTL (trade categories, statuses)
- **Search Results**: No caching (dynamic location-based)

### 2. Database Query Caching

- Use Laravel's query result caching for expensive operations
- Cache aggregated data (counts, statistics)
- Implement cache invalidation on data changes

### 3. Connection Pooling

```php
// config/database.php
'pgsql' => [
    'driver' => 'pgsql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '5432'),
    'database' => env('DB_DATABASE', 'forge'),
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8',
    'prefix' => '',
    'prefix_indexes' => true,
    'schema' => 'public',
    'sslmode' => 'prefer',
    'options' => [
        PDO::ATTR_PERSISTENT => true, // Connection pooling
    ],
],
```

## Performance Benchmarks

### Target Performance Metrics

1. **Artisan Search**: < 2 seconds for 10km radius
2. **Mission List**: < 1 second for 20 items
3. **Transaction History**: < 1 second for 20 items
4. **Database Connections**: Max 100 concurrent
5. **Response Time**: 95% of requests < 3 seconds

### Load Testing

```bash
# Use Apache Bench for basic load testing
ab -n 1000 -c 10 http://localhost:8000/api/v1/artisans/search?latitude=5.36&longitude=-4.01

# Use Laravel Dusk for browser testing
php artisan dusk --group=performance
```

## Maintenance Tasks

### Daily Tasks

1. **Monitor slow queries**
2. **Check cache hit rates**
3. **Review error logs**

### Weekly Tasks

1. **Analyze query performance**
2. **Review index usage statistics**
3. **Check database size growth**

### Monthly Tasks

1. **VACUUM and ANALYZE** (PostgreSQL)
2. **Review and optimize indexes**
3. **Performance benchmark testing**

## Troubleshooting

### Common Performance Issues

1. **Missing Indexes**: Use `EXPLAIN ANALYZE` to identify
2. **Inefficient Queries**: Review query plans
3. **Lock Contention**: Monitor `pg_locks` table
4. **Connection Limits**: Check `max_connections` setting

### Emergency Procedures

1. **High CPU Usage**: Identify and kill long-running queries
2. **Disk Space**: Clean up logs and temporary files
3. **Connection Exhaustion**: Restart application servers
4. **Index Corruption**: Rebuild affected indexes

## Future Optimizations

### Planned Improvements

1. **Read Replicas**: For read-heavy workloads
2. **Partitioning**: For large transaction tables
3. **Materialized Views**: For complex aggregations
4. **Full-Text Search**: For mission descriptions
5. **Database Sharding**: For horizontal scaling

### Monitoring Tools

1. **pg_stat_statements**: Query performance tracking
2. **pgBadger**: Log analysis
3. **New Relic**: Application performance monitoring
4. **Grafana**: Database metrics visualization
