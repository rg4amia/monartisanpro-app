<?php

namespace App\Console\Commands;

use App\Infrastructure\Services\MobileMoney\MobileMoneyWebhookHandler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Console command to query pending mobile money transactions
 *
 * This command should be run periodically (e.g., every 5 minutes)
 * to check the status of transactions that haven't received webhooks.
 *
 * Requirement 15.5: Query payment status via API if webhook not received
 */
class QueryPendingTransactions extends Command
{
 protected $signature = 'mobile-money:query-pending';
 protected $description = 'Query status of pending mobile money transactions';

 private MobileMoneyWebhookHandler $webhookHandler;

 public function __construct(MobileMoneyWebhookHandler $webhookHandler)
 {
  parent::__construct();
  $this->webhookHandler = $webhookHandler;
 }

 public function handle(): int
 {
  $this->info('Querying pending mobile money transactions...');

  try {
   $this->webhookHandler->queryPendingTransactions();
   $this->info('Successfully queried pending transactions');
   return 0;
  } catch (\Exception $e) {
   $this->error('Error querying pending transactions: ' . $e->getMessage());
   Log::error('QueryPendingTransactions command failed', [
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
   ]);
   return 1;
  }
 }
}
