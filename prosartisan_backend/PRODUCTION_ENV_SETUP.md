# Production Environment Setup

## Quick Start

1. Copy the environment file:
```bash
cp .env.example .env
```

2. Edit `.env` with your production values (see below)

3. Run the deployment script:
```bash
./deploy.sh
```

## Environment Variables Reference

### Critical Variables (Must Configure)

```bash
# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://prosartisan.net

# Database
DB_CONNECTION=pgsql
DB_HOST=your-database-host
DB_PORT=5432
DB_DATABASE=prosartisan_db
DB_USERNAME=prosartisan_user
DB_PASSWORD=your-secure-password
```

### Authentication & Security

```bash
# Sanctum - Leave empty for mobile API (token-based auth)
SANCTUM_STATEFUL_DOMAINS=""

# Session (not used for API, but required by Laravel)
SESSION_DRIVER=database
SESSION_LIFETIME=120
```

### Optional Services

#### SMS Service (Optional)
If you don't have an SMS provider, **leave these empty**. The app will work without SMS.

```bash
# Option 1: No SMS service (recommended for initial deployment)
LOCAL_SMS_API_URL=
LOCAL_SMS_API_KEY=
LOCAL_SMS_SENDER_ID=ProSartisan

# Option 2: With SMS service
LOCAL_SMS_API_URL=https://your-sms-provider.com/api
LOCAL_SMS_API_KEY=your-api-key-here
LOCAL_SMS_SENDER_ID=ProSartisan
```

**What happens without SMS?**
- Login and authentication work normally
- SMS notifications are skipped (logged as warnings)
- OTP features may not work (if implemented)
- All other features work normally

### Logging & Monitoring

```bash
LOG_CHANNEL=stack
LOG_LEVEL=error  # Use 'error' in production, 'debug' for troubleshooting
```

### Cache & Queue

```bash
CACHE_STORE=database
QUEUE_CONNECTION=database
```

## Complete Production .env Template

```bash
# Application
APP_NAME="ProSartisan"
APP_ENV=production
APP_KEY=base64:your-key-here
APP_DEBUG=false
APP_URL=https://prosartisan.net

APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr
APP_FAKER_LOCALE=fr_FR

# Database (PostgreSQL recommended)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=prosartisan_db
DB_USERNAME=prosartisan_user
DB_PASSWORD=your-secure-password

# Session
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

# Cache & Queue
CACHE_STORE=database
QUEUE_CONNECTION=database
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local

# Logging
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

# Mail (configure if needed)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="noreply@prosartisan.net"
MAIL_FROM_NAME="${APP_NAME}"

# Sanctum (Mobile API - token-based)
SANCTUM_STATEFUL_DOMAINS=""

# SMS Service (Optional - leave empty if not available)
LOCAL_SMS_API_URL=
LOCAL_SMS_API_KEY=
LOCAL_SMS_SENDER_ID=ProSartisan
```

## Deployment Checklist

- [ ] Copy `.env.example` to `.env`
- [ ] Configure database credentials
- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Set `APP_URL` to your domain
- [ ] Generate `APP_KEY` (run `php artisan key:generate`)
- [ ] Configure SMS (optional)
- [ ] Run `./deploy.sh`
- [ ] Test login endpoint
- [ ] Change admin password
- [ ] Enable HTTPS
- [ ] Configure firewall
- [ ] Set up backups

## Testing After Deployment

```bash
# Test health endpoint
curl https://your-domain.com/api/v1/health

# Test login
curl -X POST https://your-domain.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@prosartisan.sn","password":"Admin@2026"}'
```

## Common Issues

### Issue: "Cannot assign null to property"
**Solution:** Make sure all required environment variables are set in `.env`

### Issue: "CSRF token mismatch"
**Solution:** Ensure `SANCTUM_STATEFUL_DOMAINS=""` (empty) for mobile API

### Issue: SMS errors
**Solution:** If you don't have SMS service, leave SMS variables empty. The app will work without it.

### Issue: Database connection failed
**Solution:** 
- Verify database credentials
- Ensure PostgreSQL is running
- Check that PostGIS extension is installed

## Security Best Practices

1. **Never commit `.env` to version control**
2. **Use strong passwords** for database and admin account
3. **Enable HTTPS** in production
4. **Set `APP_DEBUG=false`** in production
5. **Regularly update dependencies**: `composer update`
6. **Monitor logs**: `tail -f storage/logs/laravel.log`
7. **Set up automated backups** for database
8. **Use environment-specific configurations**

## Support

For issues or questions:
- Check logs: `storage/logs/laravel.log`
- Review this documentation
- Check `PRODUCTION_DEPLOYMENT.md` for detailed steps
