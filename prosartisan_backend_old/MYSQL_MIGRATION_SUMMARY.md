# PostgreSQL to MySQL Migration Summary

## Overview
All database migrations have been successfully converted from PostgreSQL to MySQL and tested. This document summarizes the changes made.

## Migration Status: ✅ COMPLETED

All migrations ran successfully on MySQL 8.0+

## Key Changes

### 1. PostGIS Extension Removed
**File:** `database/migrations/0000_00_00_000000_enable_postgis_extension.php`
- Removed PostgreSQL PostGIS extension setup
- MySQL has built-in spatial support, no extension needed

### 2. Geography Columns Converted to Latitude/Longitude
All PostGIS `GEOGRAPHY(POINT, 4326)` columns have been replaced with separate `latitude` and `longitude` decimal columns.

#### Affected Tables:
- **artisan_profiles**
  - Removed: `location GEOGRAPHY(POINT, 4326)`
  - Added: `latitude DECIMAL(10,8)`, `longitude DECIMAL(11,8)`
  - Added composite index: `idx_artisan_location`

- **fournisseur_profiles**
  - Removed: `shop_location GEOGRAPHY(POINT, 4326)`
  - Added: `shop_latitude DECIMAL(10,8)`, `shop_longitude DECIMAL(11,8)`
  - Added composite index: `idx_fournisseur_location`

- **referent_zone_profiles**
  - Removed: `coverage_area GEOGRAPHY(POINT, 4326)`
  - Added: `coverage_latitude DECIMAL(10,8)`, `coverage_longitude DECIMAL(11,8)`
  - Added composite index: `idx_referent_coverage`

- **missions** (both main and marketplace versions)
  - Removed: `location GEOGRAPHY(POINT, 4326)`
  - Added: `latitude DECIMAL(10,8)`, `longitude DECIMAL(11,8)`
  - Added composite index: `idx_mission_location`

### 3. JSONB Changed to JSON
**File:** `database/migrations/2026_01_16_163200_create_artisan_profiles_table.php`
- Changed `jsonb('kyc_documents')` to `json('kyc_documents')`
- MySQL uses `json` type (no separate `jsonb` type)

### 4. GIST Indexes Removed
**File:** `database/migrations/2026_01_18_092513_add_performance_indexes_to_tables.php`
- Removed all PostgreSQL-specific GIST spatial indexes
- Replaced with standard composite indexes on latitude/longitude columns
- Fixed duplicate index issues by removing indexes already created in base migrations

### 5. Index Name Length Fixed
- MySQL has a 64-character limit on index names
- All spatial indexes now use explicit short names (e.g., `idx_artisan_location`)

### 6. Database Configuration Updated
**File:** `config/database.php`
- Changed default connection from `pgsql` to `mysql`

## Migration Files Modified

### Main Migrations
1. `0000_00_00_000000_enable_postgis_extension.php` - Disabled PostGIS setup
2. `2026_01_16_163200_create_artisan_profiles_table.php` - Geography → Lat/Long, jsonb → json
3. `2026_01_16_163300_create_fournisseur_profiles_table.php` - Geography → Lat/Long
4. `2026_01_16_163500_create_referent_zone_profiles_table.php` - Geography → Lat/Long
5. `2026_01_17_083559_create_missions_table.php` - Geography → Lat/Long
6. `2026_01_18_092513_add_performance_indexes_to_tables.php` - Removed GIST indexes, fixed duplicates

### Marketplace Migrations
1. `marketplace/2026_01_17_100000_create_missions_table.php` - Geography → Lat/Long

### Other Migrations
All other migration files (devis, sequestres, jetons, transactions, chantiers, jalons, litiges, reputation, etc.) required no changes as they didn't use PostgreSQL-specific features.

## Code Changes Required

### Repository Layer
The following repository files need to be updated to work with lat/long columns instead of PostGIS geography:

1. **PostgresUserRepository.php**
   - Update location queries to use lat/long calculations
   - Replace PostGIS functions with MySQL spatial functions or Haversine formula

2. **PostgresChantierRepository.php**
3. **PostgresMissionRepository.php**
4. **DefaultArtisanSearchService.php**

### Value Objects
**GPS_Coordinates.php**
- Update `toPostGISPoint()` method to return lat/long array or MySQL POINT format
- Update distance calculations if using PostGIS-specific functions

## Distance Calculation Options for MySQL

### Option 1: Haversine Formula (Pure SQL)
```sql
SELECT *, (
    6371 * acos(
        cos(radians(?)) * cos(radians(latitude)) * 
        cos(radians(longitude) - radians(?)) + 
        sin(radians(?)) * sin(radians(latitude))
    )
) AS distance
FROM table_name
HAVING distance < ?
ORDER BY distance
```

### Option 2: MySQL Spatial Functions (Recommended)
```sql
SELECT *, ST_Distance_Sphere(
    POINT(longitude, latitude),
    POINT(?, ?)
) AS distance
FROM table_name
WHERE ST_Distance_Sphere(
    POINT(longitude, latitude),
    POINT(?, ?)
) < ?
ORDER BY distance
```

## Testing Results

✅ All migrations ran successfully
✅ All tables created without errors
✅ All indexes created successfully
✅ Seeders ran successfully
✅ No duplicate index errors
✅ No index name length errors

## Environment Configuration

Your `.env` file is configured correctly:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=8889
DB_DATABASE=lv_monartisanpro
DB_USERNAME=root
DB_PASSWORD=root
```

## Next Steps

1. ✅ Migrations completed successfully
2. ⚠️ Update repository classes to use MySQL-compatible spatial queries
3. ⚠️ Test all location-based features
4. ⚠️ Update any raw SQL queries that used PostgreSQL-specific syntax
5. ⚠️ Verify all functionality works correctly

## Notes

- MySQL's spatial support is sufficient for the application's needs
- Lat/Long columns provide better portability across databases
- Performance should be comparable with proper indexing
- MySQL 8.0+ provides excellent spatial function support
- All migrations are now MySQL-compatible and tested

