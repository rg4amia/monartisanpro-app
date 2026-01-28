# Infrastructure Tests

## PostgreSQL Requirement

The infrastructure tests, particularly the `PostgresUserRepositoryTest`, require a PostgreSQL database with PostGIS extension. These tests cannot run with SQLite.

### Setup for Testing

1. **Create a test database:**
   ```bash
   createdb prosartisan_test
   ```

2. **Enable PostGIS extension:**
   ```bash
   psql prosartisan_test -c "CREATE EXTENSION IF NOT EXISTS postgis;"
   psql prosartisan_test -c "CREATE EXTENSION IF NOT EXISTS postgis_topology;"
   ```

3. **Configure test environment:**
   
   Create a `.env.testing` file in the project root:
   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=prosartisan_test
   DB_USERNAME=postgres
   DB_PASSWORD=your_password
   ```

4. **Run migrations on test database:**
   ```bash
   php artisan migrate --env=testing
   ```

5. **Run the tests:**
   ```bash
   php artisan test --filter=PostgresUserRepositoryTest --env=testing
   ```

### Alternative: Docker PostgreSQL

You can use Docker to run PostgreSQL with PostGIS:

```bash
docker run --name prosartisan-test-db \
  -e POSTGRES_PASSWORD=secret \
  -e POSTGRES_DB=prosartisan_test \
  -p 5432:5432 \
  -d postgis/postgis:15-3.3
```

Then update your `.env.testing` accordingly.

## Test Coverage

The `PostgresUserRepositoryTest` covers:

- ✅ Saving and retrieving all user types (Client, Artisan, Fournisseur, ReferentZone)
- ✅ Finding users by ID and email
- ✅ Updating existing users
- ✅ KYC document persistence
- ✅ PostGIS proximity search for artisans
- ✅ Filtering active artisans in search results
- ✅ User deletion with cascade
- ✅ Failed login attempts and account locking
- ✅ Location updates for artisans

## Notes

- Tests use `RefreshDatabase` trait to ensure a clean state
- PostGIS spatial queries are tested with real coordinates (Abidjan, Côte d'Ivoire)
- Distance calculations use meters for precision
