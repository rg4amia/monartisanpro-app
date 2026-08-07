<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;

try {
    echo "Running migrations...<br>";
    $output = Artisan::call('migrate', ['--force' => true]);
    echo "Exit code: " . $output . "<br>";
    echo "<pre>" . htmlspecialchars(Artisan::output()) . "</pre>";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
