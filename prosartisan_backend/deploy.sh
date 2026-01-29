#!/bin/bash

# ProSartisan Backend Deployment Script
# This script helps deploy the application to production

set -e

echo "🚀 ProSartisan Backend Deployment"
echo "=================================="
echo ""

# Check if .env exists
if [ ! -f .env ]; then
    echo "❌ Error: .env file not found!"
    echo "   Please copy .env.example to .env and configure it."
    exit 1
fi

# Check if APP_KEY is set
if ! grep -q "APP_KEY=base64:" .env; then
    echo "⚠️  Warning: APP_KEY not set. Generating..."
    php artisan key:generate
fi

echo "📦 Installing dependencies..."
composer install --no-dev --optimize-autoloader

echo "🗄️  Running migrations..."
php artisan migrate --force

echo "👤 Checking admin account..."
php artisan db:seed --class=AdminSeeder

echo "⚡ Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "🔐 Setting permissions..."
chmod -R 755 storage bootstrap/cache

echo ""
echo "✅ Deployment completed successfully!"
echo ""
echo "📝 Next steps:"
echo "   1. Test the API: curl -X POST https://your-domain.com/api/v1/auth/login"
echo "   2. Change admin password from default"
echo "   3. Configure SMS service (optional)"
echo ""
echo "Default admin credentials:"
echo "   Email: admin@prosartisan.sn"
echo "   Password: Admin@2026"
echo ""
echo "⚠️  IMPORTANT: Change the admin password immediately!"
