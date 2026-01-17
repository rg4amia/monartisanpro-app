<?php

namespace App\Application\UseCases\Financial\GenerateJeton;

use App\Domain\Financial\Models\JetonMateriel\JetonMateriel;
use App\Domain\Financial\Repositories\SequestreRepository;
use App\Domain\Financial\Repositories\JetonRepository;
use App\Domain\Financial\Services\JetonFactory;
use App\Domain\Financial\Events\JetonGenerated;
use App\Domain\Financial\Models\ValueObjects\SequestreId;
use App\Domain\Identity\Models\ValueObjects\UserId;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Handler for generating jeton
 *
 * Requirements: 5.1, 5.2
 */
final class GenerateJetonHandler
{
 public function __construct(
  private SequestreRepository $sequestreRepository,
  private JetonRepository $jetonRepository,
  private JetonFactory $jetonFactory
 ) {}

 public function handle(GenerateJetonCommand $command): JetonMateriel
 {
  // Create value objects
  $sequestreId = SequestreId::fromString($command->sequestreId);
  $artisanId = UserId::fromString($command->artisanId);
  $supplierIds = array_map(fn($id) => UserId::fromString($id), $command->supplierIds);

  Log::info('Starting jeton generation process', [
   'sequestre_id' => $command->sequestreId,
   'artisan_id' => $command->artisanId,
   'supplier_count' => count($command->supplierIds)
  ]);

  // Get sequestre
  $sequestre = $this->sequestreRepository->findById($sequestreId);
  if (!$sequestre) {
   throw new \Exception('Sequestre not found');
  }

  // Check if sequestre has materials available
  if ($sequestre->getRemainingMaterials()->getAmountInCentimes() <= 0) {
   throw new \Exception('No materials funds available in sequestre');
  }

  // Generate jeton using factory
  $jeton = $this->jetonFactory->createJeton(
   $sequestre,
   $artisanId,
   $supplierIds
  );

  // Save jeton
  $this->jetonRepository->save($jeton);

  // Fire domain event
  Event::dispatch(new JetonGenerated(
   $jeton->getId(),
   $artisanId,
   $jeton->getCode()->toString(),
   $jeton->getTotalAmount(),
   $jeton->getExpiresAt(),
   new \DateTime()
  ));

  Log::info('Jeton generation completed successfully', [
   'jeton_id' => $jeton->getId()->getValue(),
   'jeton_code' => $jeton->getCode()->toString(),
   'total_amount' => $jeton->getTotalAmount()->getAmountInCentimes(),
   'expires_at' => $jeton->getExpiresAt()->format('Y-m-d H:i:s')
  ]);

  return $jeton;
 }
}
