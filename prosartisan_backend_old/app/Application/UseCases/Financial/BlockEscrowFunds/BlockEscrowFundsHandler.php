<?php

namespace App\Application\UseCases\Financial\BlockEscrowFunds;

use App\Domain\Financial\Events\EscrowFragmented;
use App\Domain\Financial\Events\FundsBlocked;
use App\Domain\Financial\Models\Sequestre\Sequestre;
use App\Domain\Financial\Repositories\SequestreRepository;
use App\Domain\Financial\Services\EscrowFragmentationService;
use App\Domain\Financial\Services\MobileMoneyGateway;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\ValueObjects\DevisId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Shared\ValueObjects\Currency;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Handler for blocking funds in escrow
 *
 * Requirements: 4.1, 4.2, 4.3
 */
final class BlockEscrowFundsHandler
{
    public function __construct(
        private SequestreRepository $sequestreRepository,
        private EscrowFragmentationService $fragmentationService,
        private MobileMoneyGateway $mobileMoneyGateway
    ) {}

    public function handle(BlockEscrowFundsCommand $command): Sequestre
    {
        // Create value objects
        $missionId = MissionId::fromString($command->missionId);
        $devisId = DevisId::fromString($command->devisId);
        $clientId = UserId::fromString($command->clientId);
        $artisanId = UserId::fromString($command->artisanId);
        $totalAmount = MoneyAmount::fromCentimes($command->totalAmountCentimes, Currency::XOF());

        Log::info('Starting escrow fund blocking process', [
            'mission_id' => $command->missionId,
            'devis_id' => $command->devisId,
            'total_amount' => $command->totalAmountCentimes,
        ]);

        // Block funds via mobile money gateway
        $transactionResult = $this->mobileMoneyGateway->blockFunds($clientId, $totalAmount);

        if (! $transactionResult->isSuccessful()) {
            throw new \Exception('Failed to block funds: '.$transactionResult->getErrorMessage());
        }

        // Create sequestre
        $sequestre = Sequestre::create(
            $missionId,
            $clientId,
            $artisanId,
            $totalAmount
        );

        // Fragment the escrow (65% materials, 35% labor)
        $sequestre->fragment();

        // Save sequestre
        $this->sequestreRepository->save($sequestre);

        // Fire domain events
        Event::dispatch(new FundsBlocked(
            $sequestre->getId(),
            $missionId,
            $totalAmount,
            new \DateTime
        ));

        Event::dispatch(new EscrowFragmented(
            $sequestre->getId(),
            $sequestre->getMaterialsAmount(),
            $sequestre->getLaborAmount(),
            new \DateTime
        ));

        Log::info('Escrow fund blocking completed successfully', [
            'sequestre_id' => $sequestre->getId()->getValue(),
            'materials_amount' => $sequestre->getMaterialsAmount()->getAmountInCentimes(),
            'labor_amount' => $sequestre->getLaborAmount()->getAmountInCentimes(),
        ]);

        return $sequestre;
    }
}
