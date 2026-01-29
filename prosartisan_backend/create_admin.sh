#!/bin/bash

echo "👤 Creating Admin Account"
echo "========================="
echo ""

cd prosartisan_backend

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo "❌ Error: Must be run from prosartisan_backend directory"
    exit 1
fi

echo "🔐 Creating admin account..."
php artisan db:seed --class=AdminSeeder

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Admin account created successfully!"
else
    echo ""
    echo "❌ Failed to create admin account. Check the error messages above."
    exit 1
fi
