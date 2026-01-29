#!/bin/bash

echo "🧪 Testing Complete Platform Seeding with Real Trades"
echo "====================================================="
echo ""

cd prosartisan_backend

# Check if CSV file exists
if [ ! -f "../base_secteur_activite_metier.csv" ]; then
    echo "❌ CSV file not found at ../base_secteur_activite_metier.csv"
    exit 1
fi

echo "✓ CSV file found"
echo ""

# Run complete seeding
echo "🌱 Running complete platform seeding..."
echo "   This will:"
echo "   1. Drop all tables"
echo "   2. Run migrations"
echo "   3. Seed trades from CSV (142 trades)"
echo "   4. Seed complete platform data (~500 records)"
echo ""

read -p "Continue? (y/n) " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Cancelled."
    exit 0
fi

php artisan migrate:fresh --seed

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Seeding completed successfully!"
    echo ""
    echo "📊 Database Summary:"
    php artisan tinker --execute="
        echo '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━' . PHP_EOL;
        echo 'TRADES & SECTORS' . PHP_EOL;
        echo '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━' . PHP_EOL;
        echo 'Sectors: ' . \App\Models\Sector::count() . PHP_EOL;
        echo 'Trades: ' . \App\Models\Trade::count() . PHP_EOL;
        echo PHP_EOL;
        
        echo '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━' . PHP_EOL;
        echo 'USERS' . PHP_EOL;
        echo '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━' . PHP_EOL;
        echo 'Total Users: ' . \App\Models\User::count() . PHP_EOL;
        echo 'Artisans: ' . \App\Models\User::where('role', 'ARTISAN')->count() . PHP_EOL;
        echo 'Clients: ' . \App\Models\User::where('role', 'CLIENT')->count() . PHP_EOL;
        echo 'Fournisseurs: ' . \App\Models\User::where('role', 'FOURNISSEUR')->count() . PHP_EOL;
        echo 'Referents: ' . \App\Models\User::where('role', 'REFERENT_ZONE')->count() . PHP_EOL;
        echo PHP_EOL;
        
        echo '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━' . PHP_EOL;
        echo 'MARKETPLACE' . PHP_EOL;
        echo '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━' . PHP_EOL;
        echo 'Missions: ' . DB::table('missions')->count() . PHP_EOL;
        echo 'Devis: ' . DB::table('devis')->count() . PHP_EOL;
        echo 'Chantiers: ' . DB::table('chantiers')->count() . PHP_EOL;
        echo PHP_EOL;
        
        echo '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━' . PHP_EOL;
        echo 'SAMPLE ARTISAN TRADES (First 10)' . PHP_EOL;
        echo '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━' . PHP_EOL;
        DB::table('artisan_profiles')
            ->join('users', 'users.id', '=', 'artisan_profiles.user_id')
            ->select('users.name', 'artisan_profiles.trade_category')
            ->limit(10)
            ->get()
            ->each(function(\$a) {
                echo '  • ' . \$a->name . ': ' . \$a->trade_category . PHP_EOL;
            });
        echo PHP_EOL;
        
        echo '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━' . PHP_EOL;
        echo 'TRADE DISTRIBUTION IN ARTISAN PROFILES' . PHP_EOL;
        echo '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━' . PHP_EOL;
        DB::table('artisan_profiles')
            ->select('trade_category', DB::raw('COUNT(*) as count'))
            ->groupBy('trade_category')
            ->orderByDesc('count')
            ->get()
            ->each(function(\$t) {
                echo '  • ' . \$t->trade_category . ': ' . \$t->count . ' artisan(s)' . PHP_EOL;
            });
        echo PHP_EOL;
        
        echo '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━' . PHP_EOL;
        echo 'TRADE DISTRIBUTION IN MISSIONS' . PHP_EOL;
        echo '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━' . PHP_EOL;
        DB::table('missions')
            ->select('trade_category', DB::raw('COUNT(*) as count'))
            ->groupBy('trade_category')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->each(function(\$t) {
                echo '  • ' . \$t->trade_category . ': ' . \$t->count . ' mission(s)' . PHP_EOL;
            });
        echo PHP_EOL;
    "
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "🔑 TEST CREDENTIALS"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "Email: artisan1@prosartisan.sn (or client1, fournisseur1, referent1)"
    echo "Password: password"
    echo ""
    echo "📖 See documentation:"
    echo "   • SEEDING_GUIDE.md"
    echo "   • SEEDED_DATA_REFERENCE.md"
    echo "   • SEEDER_TRADES_UPDATE.md"
    
else
    echo ""
    echo "❌ Seeding failed. Check the error messages above."
    exit 1
fi
