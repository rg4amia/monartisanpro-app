#!/bin/bash

echo "🚀 ProsArtisan Platform Seeding Script"
echo "======================================"
echo ""

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo "❌ Error: Must be run from prosartisan_backend directory"
    exit 1
fi

# Ask for confirmation
echo "⚠️  This will reset the database and seed with test data."
read -p "Continue? (y/n) " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Cancelled."
    exit 0
fi

echo ""
echo "📦 Running migrations and seeding..."
php artisan migrate:fresh --seed

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Seeding completed successfully!"
    echo ""
    echo "📊 Database Summary:"
    php artisan tinker --execute="
        echo 'Users: ' . \App\Models\User::count() . PHP_EOL;
        echo 'Artisans: ' . \App\Models\User::where('role', 'ARTISAN')->count() . PHP_EOL;
        echo 'Clients: ' . \App\Models\User::where('role', 'CLIENT')->count() . PHP_EOL;
        echo 'Fournisseurs: ' . \App\Models\User::where('role', 'FOURNISSEUR')->count() . PHP_EOL;
        echo 'Referents: ' . \App\Models\User::where('role', 'REFERENT_ZONE')->count() . PHP_EOL;
    "
    echo ""
    echo "🔑 Test Credentials:"
    echo "   Email: artisan1@prosartisan.sn"
    echo "   Password: password"
    echo ""
    echo "📖 See SEEDING_GUIDE.md for more information"
else
    echo ""
    echo "❌ Seeding failed. Check the error messages above."
    exit 1
fi
