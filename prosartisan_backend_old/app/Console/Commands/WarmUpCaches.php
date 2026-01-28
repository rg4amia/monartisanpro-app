<?php

namespace App\Console\Commands;

use App\Infrastructure\Services\Cache\StaticDataCacheService;
use Illuminate\Console\Command;

/**
 * Console command to warm up application caches
 *
 * Requirements: 17.3
 */
class WarmUpCaches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:warm-up {--clear : Clear existing caches before warming up}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warm up application caches for better performance';

    public function __construct(
        private StaticDataCacheService $staticDataCacheService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Warming up application caches...');

        if ($this->option('clear')) {
            $this->info('Clearing existing caches...');
            $this->staticDataCacheService->clearCaches();
        }

        // Warm up static data caches
        $this->info('Warming up static data caches...');
        $this->staticDataCacheService->warmUpCaches();

        $this->info('✅ Cache warm-up completed successfully!');
        $this->table(
            ['Cache Type', 'TTL', 'Status'],
            [
                ['Trade Categories', '1 hour', '✅ Cached'],
                ['Mission Statuses', '1 hour', '✅ Cached'],
                ['Devis Statuses', '1 hour', '✅ Cached'],
                ['Artisan Profiles', '5 minutes', '⏳ On-demand'],
            ]
        );

        return Command::SUCCESS;
    }
}
