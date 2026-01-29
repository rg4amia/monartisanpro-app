#!/bin/bash

echo "🧪 Testing Trade Seeder"
echo "======================="
echo ""

cd prosartisan_backend

# Check if CSV file exists
if [ ! -f "../base_secteur_activite_metier.csv" ]; then
    echo "❌ CSV file not found at ../base_secteur_activite_metier.csv"
    exit 1
fi

echo "✓ CSV file found"
echo ""

# Count lines in CSV
lines=$(wc -l < ../base_secteur_activite_metier.csv)
echo "📊 CSV contains $lines lines (including header)"
echo "   Expected: ~142 trade records"
echo ""

# Run the seeder
echo "🌱 Running TradeSeeder..."
php artisan db:seed --class=TradeSeeder

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Seeder completed successfully!"
    echo ""
    echo "📊 Verification:"
    php artisan tinker --execute="
        echo 'Sectors: ' . \App\Models\Sector::count() . PHP_EOL;
        echo 'Trades: ' . \App\Models\Trade::count() . PHP_EOL;
        echo PHP_EOL;
        echo 'Sample Sectors:' . PHP_EOL;
        \App\Models\Sector::take(5)->get()->each(function(\$s) {
            echo '  - ' . \$s->name . ' (Code: ' . (\$s->code ?? 'N/A') . ')' . PHP_EOL;
        });
        echo PHP_EOL;
        echo 'Sample Trades:' . PHP_EOL;
        \App\Models\Trade::with('sector')->take(5)->get()->each(function(\$t) {
            echo '  - ' . \$t->name . ' [' . \$t->sector->name . ']' . PHP_EOL;
        });
    "
else
    echo ""
    echo "❌ Seeder failed. Check the error messages above."
    exit 1
fi
