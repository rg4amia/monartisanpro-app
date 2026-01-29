# Production Deployment Guide

## Environment Configuration

### Required Environment Variables

Copy `.env.example` to `.env` and configure the following:

```bash
# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database (use PostgreSQL in production)
DB_CONNECTION=pgsql
DB_HOST=your-db-host
DB_PORT=5432
DB_DATABASE=your-database
DB_USERNAME=your-username
DB_PASSWORD=your-password

# Sanctum (for mobile API)
SANCTUM_STATEFUL_DOMAINS=""

# SMS Service (Optional)
# If you don't have an SMS service, leave these empty
# The application will log warnings but continue to work
LOCAL_SMS_API_URL=https://your-sms-api.com/api
LOCAL_SMS_API_KEY=your-api-key
LOCAL_SMS_SENDER_ID=ProSartisan
```

### SMS Service Configuration

The SMS service is **optional**. If not configured:
- SMS sending will be skipped
- Warnings will be logged
- The application will continue to function normally
- OTP verification features may not work

To enable SMS:
1. Set `LOCAL_SMS_API_URL` to your SMS provider's API endpoint
2. Set `LOCAL_SMS_API_KEY` to your API key
3. Optionally set `LOCAL_SMS_SENDER_ID` (defaults to "ProSartisan")

## Deployment Steps

1. **Clone the repository**
   ```bash
   git clone your-repo-url
   cd prosartisan_backend
   ```

2. **Install dependencies**
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   # Edit .env with your production values
   php artisan key:generate
   ```

4. **Run migrations**
   ```bash
   php artisan migrate --force
   ```

5. **Seed admin account**
   ```bash
   php artisan db:seed --class=AdminSeeder
   ```
   Default credentials:
   - Email: `admin@prosartisan.sn`
   - Password: `Admin@2026`
   
   **⚠️ Change the password immediately after first login!**

6. **Optimize for production**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

7. **Set proper permissions**
   ```bash
   chmod -R 755 storage bootstrap/cache
   chown -R www-data:www-data storage bootstrap/cache
   ```

## Testing the API

Test the login endpoint:
```bash
curl -X POST https://your-domain.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@prosartisan.sn","password":"Admin@2026"}'
```

Expected response:
```json
{
  "message": "Connexion réussie",
  "data": {
    "user": {...},
    "token": "eyJ0eXAiOiJKV1Q...",
    "token_type": "Bearer"
  }
}
```

## Troubleshooting

### CSRF Token Mismatch
- Ensure `SANCTUM_STATEFUL_DOMAINS` is empty for mobile API
- Clear config cache: `php artisan config:clear`

### SMS Service Errors
- Check that environment variables are set correctly
- If SMS is not needed, leave the variables empty
- Check logs: `tail -f storage/logs/laravel.log`

### Database Connection Issues
- Verify database credentials in `.env`
- Ensure database exists and is accessible
- Check PostgreSQL extensions (PostGIS required)

## Security Checklist

- [ ] `APP_DEBUG=false` in production
- [ ] Strong `APP_KEY` generated
- [ ] Database credentials secured
- [ ] Admin password changed from default
- [ ] HTTPS enabled
- [ ] Firewall configured
- [ ] Regular backups scheduled
