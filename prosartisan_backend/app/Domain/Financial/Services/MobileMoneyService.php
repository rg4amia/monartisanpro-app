<?php

namespace App\Domain\Financial\Services;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use App\Domain\Financial\Models\Transaction\Transaction;
use App\Domain\Financial\Models\ValueObjects\TransactionType;
use App\Domain\Financial\Repositories\TransactionRepository;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Mobile Money Service with retry logic and provider selection
 *
 * Orchestrates mobile money operations across different providers
 * with automatic retry logic and exponential backoff.
 *
 * Requirements: 4.4, 4.5, 15.4, 15.5
 */
final class MobileMoneyService
{
 private array $gateways;
 private TransactionRepository $transactionRepository;
 private int $maxRetries;
 private int $baseDelaySeconds;

 public function __construct(
  array $gateways,
  TransactionRepository $transactionRepository,
  int $maxRetries = 3,
  int $baseDelaySeconds = 2
 ) {
  $this->gateways = $gateways;
  $this->transactionRepository = $transactionRepository;
  $this->maxRetries = $maxRetries;
  $this->baseDelaySeconds = $baseDelaySeconds;
 }

 /**
  * Block funds with retry logic
  *
  * Requirement 4.5: Retry up to 3 times with exponential backoff
  */
 public function blockFunds(
  UserId $userId,
  PhoneNumber $phoneNumber,
  MoneyAmount $amount,
  string $reference
 ): MobileMoneyTransactionResult {
  $gateway = $this->selectGateway($phoneNumber);
  if (!$gateway) {
   return MobileMoneyTransactionResult::failure(
    'Aucun fournisseur de mobile money disponible pour ce numéro',
    'NO_PROVIDER_AVAILABLE'
   );
  }

  // Create transaction record
  $transaction = Transaction::create(
   $userId,
   null, // No recipient for escrow block
   $amount,
   TransactionType::escrowBlock(),
   'Blocage de fonds pour séquestre',
   [
    'phone_number' => $phoneNumber->getValue(),
    'provider' => $gateway->getProviderName(),
    'reference' => $reference,
   ]
  );

  $this->transactionRepository->save($transaction);

  return $this->executeWithRetry(
   fn() => $gateway->blockFunds($userId, $phoneNumber, $amount, $reference),
   $transaction,
   'blockFunds'
  );
 }

 /**
  * Transfer funds with retry logic
  */
 public function transferFunds(
  UserId $fromUserId,
  PhoneNumber $fromPhone,
  UserId $toUserId,
  PhoneNumber $toPhone,
  MoneyAmount $amount,
  string $reference,
  TransactionType $transactionType
 ): MobileMoneyTransactionResult {
  $gateway = $this->selectGateway($toPhone);
  if (!$gateway) {
   return MobileMoneyTransactionResult::failure(
    'Aucun fournisseur de mobile money disponible pour ce numéro',
    'NO_PROVIDER_AVAILABLE'
   );
  }

  // Create transaction record
  $transaction = Transaction::create(
   $fromUserId,
   $toUserId,
   $amount,
   $transactionType,
   'Transfert de fonds via ' . $gateway->getProviderName(),
   [
    'from_phone' => $fromPhone->getValue(),
    'to_phone' => $toPhone->getValue(),
    'provider' => $gateway->getProviderName(),
    'reference' => $reference,
   ]
  );

  $this->transactionRepository->save($transaction);

  return $this->executeWithRetry(
   fn() => $gateway->transferFunds($fromUserId, $fromPhone, $toUserId, $toPhone, $amount, $reference),
   $transaction,
   'transferFunds'
  );
 }

 /**
  * Refund funds with retry logic
  */
 public function refundFunds(
  UserId $userId,
  PhoneNumber $phoneNumber,
  MoneyAmount $amount,
  string $reference
 ): MobileMoneyTransactionResult {
  $gateway = $this->selectGateway($phoneNumber);
  if (!$gateway) {
   return MobileMoneyTransactionResult::failure(
    'Aucun fournisseur de mobile money disponible pour ce numéro',
    'NO_PROVIDER_AVAILABLE'
   );
  }

  // Create transaction record
  $transaction = Transaction::create(
   null, // System refund
   $userId,
   $amount,
   TransactionType::refund(),
   'Remboursement via ' . $gateway->getProviderName(),
   [
    'phone_number' => $phoneNumber->getValue(),
    'provider' => $gateway->getProviderName(),
    'reference' => $reference,
   ]
  );

  $this->transactionRepository->save($transaction);

  return $this->executeWithRetry(
   fn() => $gateway->refundFunds($userId, $phoneNumber, $amount, $reference),
   $transaction,
   'refundFunds'
  );
 }

 /**
  * Check transaction status across all providers
  *
  * Requirement 15.5: Query payment status via API
  */
 public function checkTransactionStatus(string $providerTransactionId, ?string $providerName = null): MobileMoneyTransactionStatus
 {
  if ($providerName) {
   $gateway = $this->getGatewayByName($providerName);
   if ($gateway) {
    return $gateway->checkTransactionStatus($providerTransactionId);
   }
  }

  // Try all gateways if provider not specified
  foreach ($this->gateways as $gateway) {
   try {
    $status = $gateway->checkTransactionStatus($providerTransactionId);
    if (!$status->isFailed()) {
     return $status;
    }
   } catch (Exception $e) {
    Log::warning('Failed to check status with provider', [
     'provider' => $gateway->getProviderName(),
     'transaction_id' => $providerTransactionId,
     'error' => $e->getMessage(),
    ]);
    continue;
   }
  }

  return new MobileMoneyTransactionStatus(
   'FAILED',
   $providerTransactionId,
   null,
   'Impossible de vérifier le statut de la transaction'
  );
 }

 /**
  * Get available providers for a phone number
  */
 public function getAvailableProviders(PhoneNumber $phoneNumber): array
 {
  $providers = [];
  foreach ($this->gateways as $gateway) {
   if ($gateway->supportsPhoneNumber($phoneNumber)) {
    $providers[] = $gateway->getProviderName();
   }
  }
  return $providers;
 }

 /**
  * Execute operation with exponential backoff retry logic
  *
  * Requirement 4.5: Retry up to 3 times with exponential backoff
  */
 private function executeWithRetry(
  callable $operation,
  Transaction $transaction,
  string $operationType
 ): MobileMoneyTransactionResult {
  $lastResult = null;

  for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
   try {
    Log::info("Mobile money operation attempt {$attempt}/{$this->maxRetries}", [
     'operation' => $operationType,
     'transaction_id' => $transaction->getId()->getValue(),
    ]);

    $result = $operation();

    if ($result->isSuccess()) {
     // Update transaction with success
     $transaction->complete($result->getProviderTransactionId());
     $this->transactionRepository->save($transaction);

     Log::info('Mobile money operation succeeded', [
      'operation' => $operationType,
      'transaction_id' => $transaction->getId()->getValue(),
      'attempt' => $attempt,
      'provider_transaction_id' => $result->getProviderTransactionId(),
     ]);

     return $result;
    }

    $lastResult = $result;

    // Log the failure
    Log::warning("Mobile money operation failed on attempt {$attempt}", [
     'operation' => $operationType,
     'transaction_id' => $transaction->getId()->getValue(),
     'error' => $result->getErrorMessage(),
     'error_code' => $result->getErrorCode(),
    ]);

    // Don't retry on certain error types
    if ($this->shouldNotRetry($result)) {
     break;
    }

    // Wait before retry (exponential backoff)
    if ($attempt < $this->maxRetries) {
     $delay = $this->baseDelaySeconds * pow(2, $attempt - 1);
     sleep($delay);
    }
   } catch (Exception $e) {
    Log::error("Mobile money operation exception on attempt {$attempt}", [
     'operation' => $operationType,
     'transaction_id' => $transaction->getId()->getValue(),
     'error' => $e->getMessage(),
    ]);

    $lastResult = MobileMoneyTransactionResult::failure(
     'Erreur technique: ' . $e->getMessage(),
     'TECHNICAL_ERROR'
    );

    // Wait before retry
    if ($attempt < $this->maxRetries) {
     $delay = $this->baseDelaySeconds * pow(2, $attempt - 1);
     sleep($delay);
    }
   }
  }

  // All retries failed
  $transaction->fail($lastResult ? $lastResult->getErrorMessage() : 'Échec après tous les essais');
  $this->transactionRepository->save($transaction);

  Log::error('Mobile money operation failed after all retries', [
   'operation' => $operationType,
   'transaction_id' => $transaction->getId()->getValue(),
   'max_retries' => $this->maxRetries,
  ]);

  return $lastResult ?? MobileMoneyTransactionResult::failure(
   'Échec après tous les essais',
   'MAX_RETRIES_EXCEEDED'
  );
 }

 /**
  * Select the appropriate gateway for a phone number
  */
 private function selectGateway(PhoneNumber $phoneNumber): ?MobileMoneyGateway
 {
  foreach ($this->gateways as $gateway) {
   if ($gateway->supportsPhoneNumber($phoneNumber)) {
    return $gateway;
   }
  }
  return null;
 }

 /**
  * Get gateway by provider name
  */
 private function getGatewayByName(string $providerName): ?MobileMoneyGateway
 {
  foreach ($this->gateways as $gateway) {
   if ($gateway->getProviderName() === $providerName) {
    return $gateway;
   }
  }
  return null;
 }

 /**
  * Determine if an error should not be retried
  */
 private function shouldNotRetry(MobileMoneyTransactionResult $result): bool
 {
  $nonRetryableErrors = [
   'INSUFFICIENT_FUNDS',
   'INVALID_PHONE_NUMBER',
   'ACCOUNT_BLOCKED',
   'INVALID_AMOUNT',
   'DUPLICATE_TRANSACTION',
  ];

  return in_array($result->getErrorCode(), $nonRetryableErrors, true);
 }
}
