<?php

namespace App\Application\UseCases\Financial\ValidateJeton;

use App\Domain\Financial\Repositories\JetonRepository;
use App\Domain\Financial\Repositories\JetonValidationRepository;
use App\Domain\Financial\Models\JetonValidation\JetonValidation;
use App\Domain\Financial\Services\AntiFraudService;
use App\Domain\Financial\Events\JetonValidated;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use App\Domain\Shared\ValueObjects\Currency;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Handler for validating jeton
 *
 * Requirements: 5.3, 5.4
 */
final class ValidateJetonHandler
{
 public function __construct(
  private JetonRepository $jetonRepository,
  private AntiFraudService $antiFraudService
 ) {}

 public function handle(ValidateJetonCommand $command): array
 {
  Log::info('Starting jeton validation process', [
   'jeton_code' => $command->jetonCode,
   'fournisseur_id' => $command->fournisseurId,
   'amount' => $command->amountCentimes
  ]);

  // Find jeton by code
  $jeton = $this->jetonRepository->findByCode($command->jetonCode);
  if (!$jeton) {
   throw new \Exception('Jeton not found');
  }

  // Check if jeton is expired
  if ($jeton->isExpired()) {
   throw new \Exception('Jeton has expired');
  }

  // Create value objects
  $fournisseurId = UserId::fromString($command->fournisseurId);
  $amount = MoneyAmount::fromCentimes($command->amountCentimes, Currency::XOF());
  $artisanLocation = GPS_Coordinates::fromLatLng($command->artisanLatitude, $command->artisanLongitude);
  $supplierLocation = GPS_Coordinates::fromLatLng($command->supplierLatitude, $command->supplierLongitude);

  // Verify GPS proximity (must be within 100m)
  if (!$this->antiFraudService->verifyProximity($artisanLocation, $supplierLocation, 100.0)) {
   throw new \Exception('Artisan and supplier must be within 100 meters of each other');
  }

  // Check if supplier is authorized
  if (!in_array($fournisseurId->getValue(), $jeton->getAuthorizedSuppliers())) {
   throw new \Exception('Supplier is not authorized for this jeton');
  }

  // Check if amount is available
  if ($amount->getAmountInCentimes() > $jeton->getRemainingAmount()->getAmountInCentimes()) {
   throw new \Exception('Requested amount exceeds remaining jeton balance');
  }

  // Validate the jeton
  $validationId = $jeton->validate($fournisseurId, $amount, $artisanLocation, $supplierLocation);

  // Save updated jeton
  $this->jetonRepository->save($jeton);

  // Fire domain event
  Event::dispatch(new JetonValidated(
   $jeton->getId(),
   $fournisseurId,
   $amount,
   $supplierLocation,
   new \DateTime()
  ));

  Log::info('Jeton validation completed successfully', [
   'jeton_code' => $command->jetonCode,
   'validation_id' => $validationId,
   'amount_used' => $command->amountCentimes,
   'remaining_amount' => $jeton->getRemainingAmount()->getAmountInCentimes()
  ]);

  return [
   'validation_id' => $validationId,
   'amount_used' => $amount->getAmountInCentimes(),
   'remaining_amount' => $jeton->getRemainingAmount()->getAmountInCentimes(),
   'validated_at' => (new \DateTime())->format('Y-m-d H:i:s')
  ];
 }
}
