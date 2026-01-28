<?php

namespace App\Domain\Dispute\Services;

use App\Domain\Dispute\Models\Litige\Litige;
use App\Domain\Dispute\Models\ValueObjects\LitigeId;
use App\Domain\Dispute\Repositories\LitigeRepository;
use App\Domain\Financial\Repositories\SequestreRepository;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Identity\Models\ValueObjects\UserType;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use InvalidArgumentException;

/**
 * Default implementation of MediationService
 *
 * Handles mediator assignment based on dispute value and facilitates communication
 *
 * Requirements: 9.3, 9.5
 */
final class DefaultMediationService implements MediationService
{
    private const HIGH_VALUE_THRESHOLD_XOF = 2000000; // 2M XOF

    public function __construct(
        private UserRepository $userRepository,
        private LitigeRepository $litigeRepository,
        private SequestreRepository $sequestreRepository
    ) {}

    /**
     * Assign mediator based on chantier value
     *
     * Requirement 9.3: High-value disputes (> 2M XOF) get Référent_de_Zone,
     * lower values get admin-based mediation
     */
    public function assignMediator(Litige $litige): UserId
    {
        $disputeValue = $this->getDisputeValue($litige);

        if ($this->isHighValueDispute($disputeValue)) {
            return $this->assignReferentZone($litige);
        }

        return $this->assignAdmin($litige);
    }

    /**
     * Facilitate dialogue between parties
     *
     * Requirement 9.5: Notify both parties and provide communication channel
     */
    public function facilitateDialogue(LitigeId $litigeId): void
    {
        $litige = $this->litigeRepository->findById($litigeId);

        if (! $litige) {
            throw new InvalidArgumentException("Litige not found: {$litigeId->getValue()}");
        }

        if (! $litige->getStatus()->isInMediation()) {
            throw new InvalidArgumentException('Dispute is not in mediation');
        }

        // In a real implementation, this would:
        // 1. Send notifications to both parties
        // 2. Set up communication channels (chat, email, etc.)
        // 3. Schedule mediation sessions if needed

        // For now, we just validate the state
    }

    /**
     * Check if a user can serve as mediator
     */
    public function canMediate(UserId $mediatorId, Litige $litige): bool
    {
        $mediator = $this->userRepository->findById($mediatorId);

        if (! $mediator) {
            return false;
        }

        // Mediator cannot be involved in the dispute
        if ($litige->involvesUser($mediatorId)) {
            return false;
        }

        $userType = $mediator->getType();

        // Check if user type can mediate
        return $userType->isAdmin() || $userType->isReferentZone();
    }

    /**
     * Get available mediators for a dispute
     */
    public function getAvailableMediators(Litige $litige): array
    {
        $disputeValue = $this->getDisputeValue($litige);

        if ($this->isHighValueDispute($disputeValue)) {
            // For high-value disputes, get Référent_de_Zone users
            return $this->userRepository->findByType(UserType::referentZone());
        }

        // For regular disputes, get admin users
        return $this->userRepository->findByType(UserType::admin());
    }

    private function getDisputeValue(Litige $litige): MoneyAmount
    {
        // Get the sequestre associated with the mission
        $sequestre = $this->sequestreRepository->findByMissionId($litige->getMissionId());

        if (! $sequestre) {
            // If no sequestre found, assume low value
            return MoneyAmount::fromCentimes(0);
        }

        return $sequestre->getTotalAmount();
    }

    private function isHighValueDispute(MoneyAmount $value): bool
    {
        return $value->toCentimes() > (self::HIGH_VALUE_THRESHOLD_XOF * 100); // Convert to centimes
    }

    private function assignReferentZone(Litige $litige): UserId
    {
        $referents = $this->userRepository->findByType(UserType::referentZone());

        if (empty($referents)) {
            throw new InvalidArgumentException('No Référent de Zone available for high-value dispute mediation');
        }

        // For now, assign the first available referent
        // In a real implementation, this could consider:
        // - Geographic proximity
        // - Workload
        // - Specialization
        return $referents[0]->getId();
    }

    private function assignAdmin(Litige $litige): UserId
    {
        $admins = $this->userRepository->findByType(UserType::admin());

        if (empty($admins)) {
            throw new InvalidArgumentException('No admin available for dispute mediation');
        }

        // For now, assign the first available admin
        // In a real implementation, this could consider workload distribution
        return $admins[0]->getId();
    }
}
