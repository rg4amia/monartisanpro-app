# Deployment Guide - Hostinger Production

## Server Information
- **Host**: 82.29.185.47
- **Port**: 65002
- **Username**: u398732316
- **Deployment Path**: `/home/u398732316/domains/prosartisan.net/public_html/monartisanpro-app/prosartisan_backend`
- **Live URL**: https://prosartisan.net

## Prerequisites

### GitHub Secrets Required
Configure these secrets in your GitHub repository settings:

1. `USERNAME` - SSH username (u398732316)
2. `SERVER_PASSWORD` - SSH password for the server

### Server Requirements
- PHP 8.2+
- PostgreSQL database
- Composer
- Node.js 20+
- Required PHP extensions: mbstring, xml, ctype, iconv, intl, pdo, pgsql, dom, filter, gd, json

## Deployment Process

### Automatic Deployment (CI/CD)
The workflow automatically deploys when you push to the `master` branch:

```bash
git push origin master
```

### Manual Deployment
If you need to deploy manually:

1. **Build locally**:
```bash
cd prosartisan_backend
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

2. **Create archive**:
```bash
tar -czf deploy.tar.gz \
  --exclude='node_modules' \
  --exclude='.git' \
  --exclude='tests' \
  --exclude='.env' \
  .
```

3. **Upload to server**:
```bash
scp -P 65002 deploy.tar.gz u398732316@82.29.185.47:/home/u398732316/
```

4. **Extract and configure**:
```bash
ssh -p 65002 u398732316@82.29.185.47
cd /home/u398732316/domains/prosartisan.net/public_html/monartisanpro-app/prosartisan_backend
tar -xzf ~/deploy.tar.gz
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Environment Configuration

### Production .env File
Store your production `.env` file at `/home/u398732316/.env.prod` on the server. It will be automatically copied during deployment.

Required environment variables:
```env
APP_NAME="ProSartisan"
APP_ENV=production
APP_KEY=base64:YOUR_KEY_HERE
APP_DEBUG=false
APP_URL=https://prosartisan.net

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Add other required variables
```

## Post-Deployment Checklist

1. **Verify .env file**:
```bash
ssh -p 65002 u398732316@82.29.185.47
cat /home/u398732316/domains/prosartisan.net/public_html/monartisanpro-app/prosartisan_backend/.env
```

2. **Check permissions**:
```bash
ls -la storage/
ls -la bootstrap/cache/
```

3. **Test database connection**:
```bash
php artisan migrate:status
```

4. **Clear all caches**:
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

5. **Verify site is accessible**:
Visit https://prosartisan.net

## Troubleshooting

### 500 Internal Server Error
1. Check Laravel logs: `storage/logs/laravel.log`
2. Verify .env file exists and is configured correctly
3. Check file permissions on storage and bootstrap/cache
4. Ensure APP_KEY is set: `php artisan key:generate`

### Database Connection Issues
1. Verify database credentials in .env
2. Check PostgreSQL is running
3. Test connection: `php artisan tinker` then `DB::connection()->getPdo();`

### Assets Not Loading
1. Verify build directory exists: `ls -la public/build/`
2. Check .htaccess files are present
3. Clear browser cache

### Permission Denied Errors
```bash
chmod -R 755 storage bootstrap/cache
chmod -R 775 storage/logs
find storage -type d -exec chmod 775 {} \;
find storage -type f -exec chmod 664 {} \;
```

## Rollback Procedure

Backups are automatically created in `/home/u398732316/backups/` with timestamps.

To rollback:
```bash
ssh -p 65002 u398732316@82.29.185.47
cd /home/u398732316/domains/prosartisan.net/public_html/monartisanpro-app
rm -rf prosartisan_backend
cp -r /home/u398732316/backups/backup-YYYYMMDD-HHMMSS prosartisan_backend
```

## Monitoring

### Check Application Status
```bash
php artisan about
```

### View Recent Logs
```bash
tail -f storage/logs/laravel.log
```

### Database Performance
```bash
php artisan db:monitor
```

## Security Notes

1. Never commit `.env` files to version control
2. Keep backups of production `.env` file
3. Regularly update dependencies: `composer update` (test first!)
4. Monitor logs for suspicious activity
5. Keep PHP and server software updated

## Support

For deployment issues, check:
1. GitHub Actions logs
2. Server logs: `storage/logs/laravel.log`
3. Web server error logs (ask hosting provider for location)

