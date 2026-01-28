<?php

namespace App\Http\Resources\Reputation;

use App\Domain\Reputation\Models\ReputationProfile\ReputationProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReputationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ReputationProfile $this */
        return [
            'id' => $this->getId()->getValue(),
            'artisan_id' => $this->getArtisanId()->getValue(),
            'current_score' => $this->getCurrentScore()->getValue(),
            'is_eligible_for_micro_credit' => $this->isEligibleForMicroCredit(),
            'metrics' => [
                'reliability_score' => $this->getMetrics()->getReliabilityScore(),
                'integrity_score' => $this->getMetrics()->getIntegrityScore(),
                'quality_score' => $this->getMetrics()->getQualityScore(),
                'reactivity_score' => $this->getMetrics()->getReactivityScore(),
                'completed_projects' => $this->getMetrics()->getCompletedProjects(),
                'accepted_projects' => $this->getMetrics()->getAcceptedProjects(),
                'average_rating' => $this->getMetrics()->getAverageRating(),
                'average_response_time_hours' => $this->getMetrics()->getAverageResponseTimeHours(),
                'fraud_attempts' => $this->getMetrics()->getFraudAttempts(),
            ],
            'last_calculated_at' => $this->getLastCalculatedAt()->format('Y-m-d H:i:s'),
            'created_at' => $this->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $this->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}
