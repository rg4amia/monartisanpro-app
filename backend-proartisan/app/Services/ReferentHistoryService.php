<?php

namespace App\Services;

use App\Models\Litige;
use App\Models\Mission;
use App\Models\User;
use App\States\Mission\MissionState;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Historique du Référent de zone : inspections qu'il a réalisées et litiges
 * des chantiers qu'il doit visiter ou a visités (Chantier 20).
 *
 * Aucun nom, téléphone ni coordonnée des parties n'est renvoyé : le Référent
 * instruit un chantier, pas des personnes (Règle d'or 22).
 */
class ReferentHistoryService
{
    public const LITIGE_STATUS_LABELS = [
        'ouvert' => 'Ouvert',
        'en_cours' => 'En cours',
        'resolu' => 'Résolu',
    ];

    public const DECISION_LABELS = [
        'client' => 'En faveur du client',
        'artisan' => "En faveur de l'artisan",
        'mixte' => 'Responsabilité partagée',
        'gel' => 'Fonds gelés, visite demandée',
    ];

    /**
     * Missions dont le Référent connecté a enregistré la visite.
     */
    public function inspections(User $referent, int $perPage = 20): LengthAwarePaginator
    {
        $page = Mission::query()
            ->where('referent_validated_by', $referent->id)
            ->orderByDesc('referent_validated_at')
            ->paginate($perPage);

        $page->getCollection()->transform(fn (Mission $mission) => $this->missionSummary($mission) + [
            'inspected_at' => $mission->referent_validated_at?->toIso8601String(),
        ]);

        return $page;
    }

    /**
     * Litiges des chantiers que le Référent doit visiter ou a visités.
     */
    public function litiges(User $referent, ?string $statut = null, int $perPage = 20): LengthAwarePaginator
    {
        $page = Litige::query()
            ->with('mission')
            ->when($statut, fn ($q) => $q->where('statut', $statut))
            ->where(function ($outer) use ($referent): void {
                $outer
                    // Visite demandée et pas encore faite : tout Référent peut s'en charger.
                    ->where(function ($pending): void {
                        $pending->where('statut', '!=', 'resolu')
                            ->where(function ($q): void {
                                $q->where('workflow_step', 'visite_referent')
                                    ->orWhereHas('mission', fn ($m) => $m->where('referent_required', true));
                            });
                    })
                    // Visite faite par ce Référent : le dossier reste dans son historique.
                    ->orWhereHas('mission', fn ($m) => $m->where('referent_validated_by', $referent->id));
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $page->getCollection()->transform(function (Litige $litige) use ($referent) {
            $mission = $litige->mission;

            return [
                'id' => $litige->id,
                'motif' => $litige->motif,
                'description' => $litige->description,
                'statut' => $litige->statut,
                'statut_label' => self::LITIGE_STATUS_LABELS[$litige->statut] ?? $litige->statut,
                'decision' => $litige->decision,
                'decision_label' => $litige->decision ? (self::DECISION_LABELS[$litige->decision] ?? $litige->decision) : null,
                'created_at' => $litige->created_at?->toIso8601String(),
                'resolu_at' => $litige->resolu_at ? Carbon::parse($litige->resolu_at)->toIso8601String() : null,
                'mission' => $mission ? $this->missionSummary($mission) : null,
                'visit_required' => $mission !== null && $mission->referent_validated_at === null && $litige->statut !== 'resolu',
                'visited_by_me' => $mission !== null && (int) $mission->referent_validated_by === (int) $referent->id,
                'visited_at' => $mission?->referent_validated_at?->toIso8601String(),
            ];
        });

        return $page;
    }

    /**
     * @return array<string, mixed>
     */
    private function missionSummary(Mission $mission): array
    {
        $status = (string) $mission->status;

        return [
            'id' => $mission->id,
            'description' => $mission->description,
            'address' => $mission->client_address,
            'montant_total' => (int) $mission->montant_total,
            'status' => $status,
            'status_label' => MissionState::labelFor($status),
        ];
    }
}
