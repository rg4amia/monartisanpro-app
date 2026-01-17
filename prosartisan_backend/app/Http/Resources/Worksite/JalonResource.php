<?php

namespace App\Http\Resources\Worksite;

use App\Domain\Worksite\Models\Jalon\Jalon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for Jalon (Milestone)
 *
 * Transforms Jalon domain entity to JSON response
 * Requirements: 6.2, 6.3
 */
class JalonResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        /** @var Jalon $jalon */
        $jalon = $this->resource;

        $proof = $jalon->getProof();

        return [
            'id' => $jalon->getId()->getValue(),
            'chantier_id' => $jalon->getChantierId()->getValue(),
            'description' => $jalon->getDescription(),
            'sequence_number' => $jalon->getSequenceNumber(),
            'status' => $jalon->getStatus()->getValue(),
            'status_label' => $jalon->getStatus()->getFrenchLabel(),

            // Labor amount
            'labor_amount' => [
                'centimes' => $jalon->getLaborAmount()->toCentimes(),
                'formatted' => $jalon->getLaborAmount()->format(),
                'currency' => 'XOF'
            ],

            // Proof of delivery
            'proof' => $proof ? [
                'photo_url' => $proof->getPhotoUrl(),
                'location' => [
                    'latitude' => $proof->getLocation()->getLatitude(),
                    'longitude' => $proof->getLocation()->getLongitude(),
                    'accuracy' => $proof->getLocation()->getAccuracy(),
                ],
                'captured_at' => $proof->getCapturedAt()->format('Y-m-d H:i:s'),
                'exif_data' => $proof->getExifData(),
                'integrity_verified' => $proof->verifyIntegrity(),
            ] : null,

            // Status flags
            'is_completed' => $jalon->isCompleted(),
            'can_be_validated' => $jalon->canBeValidated(),
            'is_auto_validation_due' => $jalon->isAutoValidationDue(),

            // Auto-validation information
            'auto_validation_deadline' => $jalon->getAutoValidationDeadline()?->format('Y-m-d H:i:s'),
            'hours_until_auto_validation' => $jalon->getHoursUntilAutoValidation(),

            // Contest information
            'contest_reason' => $jalon->getContestReason(),

            // Timestamps
            'created_at' => $jalon->getCreatedAt()->format('Y-m-d H:i:s'),
            'submitted_at' => $jalon->getSubmittedAt()?->format('Y-m-d H:i:s'),
            'validated_at' => $jalon->getValidatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
