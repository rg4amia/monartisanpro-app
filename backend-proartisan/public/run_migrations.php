<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;

try {
    echo "Running target migrations individually...<br>";
    
    $paths = [
        'database/migrations/2026_08_07_120000_create_intervention_types_table.php',
        'database/migrations/2026_08_07_120100_add_intervention_fields_to_devis_table.php',
        'database/migrations/2026_08_07_120200_seed_commission_artisan_stock_setting.php',
        'database/migrations/2026_08_07_130000_add_commission_service_ratio_to_devis_table.php'
    ];
    
    foreach ($paths as $path) {
        echo "Running: $path ... ";
        try {
            $output = Artisan::call('migrate', [
                '--force' => true,
                '--path' => $path
            ]);
            echo "Success! (Exit: " . $output . ")<br>";
            echo "<pre>" . htmlspecialchars(Artisan::output()) . "</pre><hr>";
        } catch (\Exception $eInner) {
            echo "Failed: " . htmlspecialchars($eInner->getMessage()) . "<br><hr>";
        }
    }
    
    echo "All target migrations executed.";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
