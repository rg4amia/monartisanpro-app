# ProSartisan Backend Setup Guide

## Prerequisites

- PHP 8.2 or higher
- Composer 2.x
- PostgreSQL 15+ with PostGIS extension
- Redis 7+
- Node.js 18+ and npm (for frontend assets)

## Installation Steps

### 1. Install PostgreSQL with PostGIS

#### macOS (using Homebrew)
```bash
brew install postgresql@15 postgis
brew services start postgresql@15
```

#### Ubuntu/Debian
```bash
sudo apt-get update
sudo apt-get install postgresql-15 postgresql-15-postgis-3
sudo systemctl start postgresql
```

#### Create Database
```bash
# Connect to PostgreSQL
psql -U postgres

# Create database and enable PostGIS
CREATE DATABASE prosartisan;
\c prosartisan
CREATE EXTENSION postgis;
CREATE EXTENSION postgis_topology;
\q
```

### 2. Install Redis

#### macOS
```bash
brew install redis
brew services start redis
```

#### Ubuntu/Debian
```bash
sudo apt-get install redis-server
sudo systemctl start redis
```

### 3. Clone and Setup Backend

```bash
cd prosartisan_backend

# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Update .env with your database credentials
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=prosartisan
# DB_USERNAME=postgres
# DB_PASSWORD=your_password

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Install Node dependencies
npm install

# Build frontend assets
npm run build
```

### 4. Run Development Server

```bash
# Option 1: Run all services concurrently
composer dev

# Option 2: Run services separately
# Terminal 1: Laravel server
php artisan serve

# Terminal 2: Queue worker
php artisan queue:listen

# Terminal 3: Frontend dev server
npm run dev

# Terminal 4: Logs
php artisan pail
```

## Testing

### Run All Tests
```bash
composer test
```

### Run Specific Test Suites
```bash
# Unit tests only
php artisan test --testsuite=Unit

# Feature tests only
php artisan test --testsuite=Feature

# With coverage
php artisan test --coverage --min=80
```

### Run Pest Tests
```bash
./vendor/bin/pest
```

## Code Quality

### Run Code Style Fixer
```bash
./vendor/bin/pint
```

### Check Code Style
```bash
./vendor/bin/pint --test
```

## Project Structure

```
prosartisan_backend/
├── app/
│   ├── Domain/              # Domain layer (entities, value objects, services)
│   │   ├── Identity/        # User management context
│   │   ├── Marketplace/     # Mission and quote context
│   │   ├── Financial/       # Escrow and payment context
│   │   ├── Worksite/        # Project tracking context
│   │   ├── Reputation/      # Score calculation context
│   │   ├── Dispute/         # Conflict resolution context
│   │   └── Shared/          # Shared value objects
│   ├── Application/         # Application layer (use cases, DTOs, handlers)
│   ├── Infrastructure/      # Infrastructure layer (repositories, external services)
│   └── Http/                # Presentation layer (controllers, requests, resources)
├── database/
│   └── migrations/          # Database migrations
├── tests/
│   ├── Unit/                # Unit tests
│   └── Feature/             # Integration tests
└── config/                  # Configuration files
```

## Environment Variables

Key environment variables to configure:

```env
# Application
APP_NAME=ProSartisan
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=prosartisan
DB_USERNAME=postgres
DB_PASSWORD=

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Queue
QUEUE_CONNECTION=redis

# Cache
CACHE_STORE=redis

# Session
SESSION_DRIVER=redis
```

## Troubleshooting

### PostGIS Extension Not Found
```bash
# Verify PostGIS is installed
psql -U postgres -d prosartisan -c "SELECT PostGIS_version();"

# If not installed, install it
psql -U postgres -d prosartisan -c "CREATE EXTENSION postgis;"
```

### Permission Errors
```bash
chmod -R 777 storage bootstrap/cache
```

### Composer Memory Limit
```bash
COMPOSER_MEMORY_LIMIT=-1 composer install
```

## Next Steps

1. Review the [Design Document](.kiro/specs/prosartisan-platform-implementation/design.md)
2. Check the [Task List](.kiro/specs/prosartisan-platform-implementation/tasks.md)
3. Start implementing features following the DDD architecture
4. Write tests alongside implementation
5. Run tests frequently to ensure correctness

## Additional Resources

- [Laravel Documentation](https://laravel.com/docs)
- [PostGIS Documentation](https://postgis.net/documentation/)
- [Pest Testing Framework](https://pestphp.com/)
- [Domain-Driven Design](https://martinfowler.com/bliki/DomainDrivenDesign.html)
