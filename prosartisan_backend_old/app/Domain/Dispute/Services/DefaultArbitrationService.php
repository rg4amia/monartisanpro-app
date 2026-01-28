<?php

namespace App\Domain\Dispute\Services;

use App\Domain\Dispute\Models\Litige\Litige;
use App\Domain\Dispute\Models\ValueObjects\ArbitrationDecision;
use App\Domain\Dispute\Models\ValueObjects\DecisionType;
use App\Domain\Financial\Models\ValueObjects\SequestreId;
use App\Domain\Financial\Repositories\SequestreRepository;
use App\Domain\Financial\Services\MobileMoneyGateway;
use InvalidArgumentException;

/**
 * Default implementation of ArbitrationService
 *
 * Handles arbitration decision rendering and execution
 *
 * Requirements: 9.4, 9.6
 */
final class DefaultArbitrationService implements ArbitrationService
{
    public function __construct(
        private SequestreRepository $sequestreRepository,
        private MobileMoneyGateway $mobileMoneyGateway
    ) {}

    /**
     * Render arbitration decision based on dispute analysis
     *
     * Requirement 9.6: Render arbitration decision
     */
    public function renderDecision(Litige $litige): ArbitrationDecision
    {
        // In a real implementation, this would involve:
        // - Analyzing evidence
        // - Reviewing communications
        // - Applying business rules
        // - Considering precedents

        // For now, we'll return a basic decision based on dispute type
        return $this->getRecommendedDecision($litige);
    }

    /**
     * Execute arbitration decision on sequestre
     *
     * Requirement 9.6: Execute decision (refund, payment, or fund freeze)
     */
    public function executeDecision(ArbitrationDecision $decision, SequestreId $sequestreId): void
    {
        $sequestre = $this->sequestreRepository->findById($sequestreId);

        if (! $sequestre) {
            throw new InvalidArgumentException("Sequestre not found: {$sequestreId->getValue()}");
        }

        switch ($decision->getType()->getValue()) {
            case DecisionType::REFUND_CLIENT:
                $this->executeRefundClient($decision, $sequestre);
                break;

            case DecisionType::PAY_ARTISAN:
                $this->executePayArtisan($decision, $sequestre);
                break;

            case DecisionType::PARTIAL_REFUND:
                $this->executePartialRefund($decision, $sequestre);
                break;

            case DecisionType::FREEZE_FUNDS:
                $this->executeFreezeFunds($sequestre);
                break;

            default:
                throw new InvalidArgumentException("Unknown decision type: {$decision->getType()->getValue()}");
        }

        $this->sequestreRepository->save($sequestre);
    }

    /**
     * Check if dispute can be escalated to arbitration
     */
    public function canEscalateToArbitration(Litige $litige): bool
    {
        // Can escalate if:
        // 1. Currently in mediation
        // 2. Mediation has been attempted (has communications)
        // 3. Not already resolved

        if (! $litige->getStatus()->isInMediation()) {
            return false;
        }

        $mediation = $litige->getMediation();
        if (! $mediation) {
            return false;
        }

        // Require at least some mediation attempt
        return $mediation->getCommunicationsCount() > 0;
    }

    /**
     * Get recommended decision based on dispute analysis
     */
    public function getRecommendedDecision(Litige $litige): ArbitrationDecision
    {
        $sequestre = $this->sequestreRepository->findByMissionId($litige->getMissionId());

        if (! $sequestre) {
            return ArbitrationDecision::freezeFunds();
        }

        // Basic decision logic based on dispute type
        switch ($litige->getType()->getValue()) {
            case 'QUALITY':
                // Quality issues often favor client
                return ArbitrationDecision::refundClient($sequestre->getRemainingTotal());

            case 'PAYMENT':
                // Payment disputes often favor artisan if work was done
                return ArbitrationDecision::payArtisan($sequestre->getRemainingTotal());

            case 'DELAY':
                // Delay disputes might result in partial compensation
                $partialAmount = $sequestre->getRemainingTotal()->percentage(50);

                return ArbitrationDecision::partialRefund($partialAmount);

            default:
                // For other disputes, freeze funds pending further investigation
                return ArbitrationDecision::freezeFunds();
        }
    }

    private function executeRefundClient(ArbitrationDecision $decision, $sequestre): void
    {
        $amount = $decision->getAmount() ?? $sequestre->getRemainingTotal();

        // Refund to client via mobile money
        $this->mobileMoneyGateway->refundFunds($sequestre->getClientId(), $amount);

        // Update sequestre
        $sequestre->refund($amount);
    }

    private function executePayArtisan(ArbitrationDecision $decision, $sequestre): void
    {
        $amount = $decision->getAmount() ?? $sequestre->getRemainingTotal();

        // Pay artisan via mobile money
        $this->mobileMoneyGateway->transferFunds(
            $sequestre->getClientId(),
            $sequestre->getArtisanId(),
            $amount
        );

        // Release funds from sequestre
        $laborAmount = $amount->percentage(35); // Assume labor portion
        $materialsAmount = $amount->subtract($laborAmount);

        if ($materialsAmount->toCentimes() > 0) {
            $sequestre->releaseMaterials($materialsAmount);
        }

        if ($laborAmount->toCentimes() > 0) {
            $sequestre->releaseLabor($laborAmount);
        }
    }

    private function executePartialRefund(ArbitrationDecision $decision, $sequestre): void
    {
        if (! $decision->hasAmount()) {
            throw new InvalidArgumentException('Partial refund decision must specify amount');
        }

        $refundAmount = $decision->getAmount();
        $remainingAmount = $sequestre->getRemainingTotal()->subtract($refundAmount);

        // Refund partial amount to client
        $this->mobileMoneyGateway->refundFunds($sequestre->getClientId(), $refundAmount);

        // Pay remaining to artisan
        if ($remainingAmount->toCentimes() > 0) {
            $this->mobileMoneyGateway->transferFunds(
                $sequestre->getClientId(),
                $sequestre->getArtisanId(),
                $remainingAmount
            );
        }

        // Update sequestre
        $sequestre->refund($refundAmount);

        // Release remaining as labor (simplified)
        if ($remainingAmount->toCentimes() > 0) {
            $sequestre->releaseLabor($remainingAmount);
        }
    }

    private function executeFreezeFunds($sequestre): void
    {
        // Funds remain frozen in sequestre
        // No action needed, just log the decision
        // In a real implementation, this might:
        // - Set a special frozen status
        // - Schedule review
        // - Notify relevant parties
    }
}
