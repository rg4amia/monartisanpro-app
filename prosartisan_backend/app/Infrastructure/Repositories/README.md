# Repository Implementations

## PostgresUserRepository

The `PostgresUserRepository` is the PostgreSQL implementation of the `UserRepository` interface from the Identity domain.

### Features

- **Polymorphic User Storage**: Handles all user types (Client, Artisan, Fournisseur, ReferentZone) in a single users table with type-specific profile tables
- **PostGIS Integration**: Uses PostgreSQL PostGIS extension for efficient geospatial queries
- **Transaction Safety**: All save operations are wrapped in database transactions
- **KYC Document Management**: Separate table for KYC verification data
- **Account Security**: Persists failed login attempts and account lock status

### Database Schema

The repository manages the following tables:

1. **users** - Base user data for all user types
2. **artisan_profiles** - Artisan-specific data (trade category, location, KYC status)
3. **fournisseur_profiles** - Supplier-specific data (business name, shop location)
4. **referent_zone_profiles** - Referent de zone data (zone, coverage area)
5. **kyc_verifications** - KYC document verification records

### PostGIS Spatial Queries

The repository uses PostGIS for location-based queries:

- **ST_SetSRID**: Sets the spatial reference system (SRID 4326 for WGS84)
- **ST_MakePoint**: Creates point geometries from latitude/longitude
- **ST_DWithin**: Efficient proximity search within a radius
- **ST_Distance**: Calculates distance between two points
- **GIST indexes**: Spatial indexes for fast geospatial queries

### Usage Example

```php
use App\Infrastructure\Repositories\PostgresUserRepository;
use App\Domain\Identity\Models\Artisan\Artisan;
use App\Domain\Identity\Models\ValueObjects\Email;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;

$repository = new PostgresUserRepository();

// Save an artisan
$artisan = Artisan::createArtisan(
    Email::fromString('artisan@example.com'),
    HashedPassword::fromPlainPassword('password'),
    PhoneNumber::fromString('+2250123456789'),
    TradeCategory::PLUMBER(),
    new GPS_Coordinates(5.3600, -4.0083)
);
$repository->save($artisan);

// Find artisans near a location (within 5km)
$location = new GPS_Coordinates(5.3600, -4.0083);
$nearbyArtisans = $repository->findArtisansNearLocation($location, 5.0);

// Find user by email
$user = $repository->findByEmail(Email::fromString('artisan@example.com'));
```

### Implementation Notes

1. **UUID Primary Keys**: All tables use UUID primary keys for better distribution and security
2. **Cascade Deletes**: Profile tables have ON DELETE CASCADE for automatic cleanup
3. **Reflection for Private Properties**: Uses PHP reflection to restore failed login attempts and lock status
4. **JSONB for KYC**: KYC documents are stored as JSONB in artisan_profiles for flexibility
5. **Separate KYC Table**: Full KYC verification records are stored in kyc_verifications table

### Performance Considerations

- Spatial indexes (GIST) on all location columns for fast proximity queries
- Regular B-tree indexes on frequently queried fields (email, user_type, account_status)
- Geography type (not geometry) for accurate distance calculations on Earth's surface
- Efficient ST_DWithin query for proximity search (uses spatial index)

### Testing

See `tests/Unit/Infrastructure/PostgresUserRepositoryTest.php` for comprehensive test coverage.

**Note**: Tests require PostgreSQL with PostGIS extension. See `tests/Unit/Infrastructure/README.md` for setup instructions.
