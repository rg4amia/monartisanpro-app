<?php

namespace App\Console\Commands;

use App\Domain\Worksite\Services\AutoValidationService;
use Illuminate\Console\Command;

/**
 * Console command to process auto-validations for milestones
 *
 * Runs every hour to check for milestones that need auto-validation
 * Requirements: 6.5
 */
class ProcessAutoValidations extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'worksite:process-auto-validations';

    /**
     * The console command description.
     */
    protected $description = 'Process auto-validations for milestones that have exceeded their 48-hour deadline';

    private AutoValidationService $autoValidationService;

    public function __construct(AutoValidationService $autoValidationService)
    {
        parent::__construct();
        $this->autoValidationService = $autoValidationService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting auto-validation process...');

        try {
            $this->autoValidationService->processAutoValidations();
            $this->info('Auto-validation process completed successfully.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Auto-validation process failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
