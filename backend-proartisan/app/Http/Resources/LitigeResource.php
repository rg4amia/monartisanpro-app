<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LitigeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $clientProofCount = $this->relationLoaded('preuves')
            ? $this->preuves->where('partie', 'client')->count()
            : $this->preuves()->where('partie', 'client')->count();

        $artisanProofCount = $this->relationLoaded('preuves')
            ? $this->preuves->where('partie', 'artisan')->count()
            : $this->preuves()->where('partie', 'artisan')->count();

        return [
            'id' => $this->id,
            'missionId' => $this->mission_id,
            'type' => $this->type,
            'motif' => $this->motif,
            'description' => $this->description,
            'statut' => $this->statut,
            'workflowStep' => $this->workflow_step,
            'decision' => $this->decision,
            'adminNotes' => $this->admin_notes,
            'resolutionReason' => $this->resolution_reason,
            'resolutionPayload' => $this->resolution_payload ?? [],
            'sanctions' => $this->sanctions_json ?? [],
            'fundsLockedAt' => $this->funds_locked_at?->toIso8601String(),
            'evidenceDeadlineAt' => $this->evidence_deadline_at?->toIso8601String(),
            'arbitrationStartedAt' => $this->arbitration_started_at?->toIso8601String(),
            'arbitrationDeadlineAt' => $this->arbitration_deadline_at?->toIso8601String(),
            'clientEvidenceSubmittedAt' => $this->client_evidence_submitted_at?->toIso8601String(),
            'artisanEvidenceSubmittedAt' => $this->artisan_evidence_submitted_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'resoluAt' => $this->resolu_at?->toIso8601String(),
            'evidenceCounts' => [
                'client' => $clientProofCount,
                'artisan' => $artisanProofCount,
                'clientRequired' => 2,
                'artisanRequired' => 1,
            ],
            'parties' => [
                'declencheur' => $this->whenLoaded('declencheur', fn () => [
                    'id' => $this->declencheur?->id,
                    'name' => $this->declencheur?->name,
                    'role' => $this->declencheur?->role,
                ]),
                'client' => $this->whenLoaded('mission', fn () => $this->mission?->client ? [
                    'id' => $this->mission->client->id,
                    'name' => $this->mission->client->name,
                    'phone' => $this->mission->client->phone,
                ] : null),
                'artisan' => $this->whenLoaded('mission', fn () => $this->mission?->artisan ? [
                    'id' => $this->mission->artisan->id,
                    'name' => $this->mission->artisan->name,
                    'phone' => $this->mission->artisan->phone,
                ] : null),
            ],
            'mission' => $this->whenLoaded('mission', fn () => [
                'id' => $this->mission?->id,
                'status' => $this->mission?->status,
                'montantTotal' => $this->mission?->montant_total,
                'montantMateriaux' => $this->mission?->montant_materiaux,
                'montantMo' => $this->mission?->montant_mo,
                'fundsFrozen' => $this->mission?->funds_frozen,
            ]),
            'preuves' => $this->whenLoaded('preuves', fn () => LitigeEvidenceResource::collection($this->preuves)),
            'nextAction' => $this->computeNextAction($clientProofCount, $artisanProofCount),
        ];
    }

    private function computeNextAction(int $clientProofCount, int $artisanProofCount): string
    {
        if ($this->statut === 'resolu') {
            return 'Aucune action requise.';
        }

        return match ($this->workflow_step) {
            'preuves' => $clientProofCount < 2
                ? 'Le client doit fournir au moins 2 photos probantes avant la fin du délai.'
                : ($artisanProofCount < 1
                    ? 'L’artisan doit répondre avec ses preuves avant la fin du délai.'
                    : 'Le dossier peut passer à l’arbitrage admin.'),
            'visite_referent' => 'Une visite terrain du référent est requise avant la décision finale.',
            default => 'Le dossier est en cours d’arbitrage administratif.',
        };
    }
}
