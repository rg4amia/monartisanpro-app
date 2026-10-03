<?php

namespace App\Services;

use App\Models\Mission;
use App\Models\MissionStateTransition;
use App\States\Mission\MissionState;

/**
 * Lecture de l'historique des états d'une mission, pour l'application et
 * pour le backoffice (Règle d'or 89).
 */
class MissionHistoryService
{
    private const ROLE_LABELS = [
        'client' => 'Client',
        'artisan' => 'Artisan',
        'admin' => 'Administrateur',
        'referent' => 'Référent',
        'fournisseur' => 'Fournisseur',
        'livreur' => 'Livreur',
    ];

    /**
     * @return array{mission_id: int, current_state: string, current_state_label: string, transitions: list<array<string, mixed>>}
     */
    public function timeline(Mission $mission, bool $maskStaff = false): array
    {
        $transitions = $mission->stateTransitions()
            ->with('user:id,name,phone,role')
            ->get()
            ->map(fn (MissionStateTransition $t) => [
                'role_label' => $t->user ? (self::ROLE_LABELS[$t->user->role] ?? $t->user->role) : null,
                'id' => $t->id,
                'from_state' => $t->from_state,
                'from_state_label' => MissionState::labelFor($t->from_state),
                'to_state' => $t->to_state,
                'to_state_label' => MissionState::labelFor($t->to_state),
                'user' => $t->user ? [
                    'id' => $t->user->id,
                    // Le client et l'artisan voient le rôle, jamais le nom d'un agent ProsArtisan.
                    'name' => $maskStaff && in_array($t->user->role, ['admin', 'referent'], true) ? null : $t->user->name,
                    'role' => $t->user->role,
                ] : null,
                'reason' => $t->reason,
                // Ligne déduite après coup des faits enregistrés (Chantier 19, lot 7).
                'reconstituted' => (bool) ($t->metadata_json['reconstitue'] ?? false),
                // La date du changement n'a pas été conservée : celle affichée est celle du constat.
                'unknown_date' => (bool) ($t->metadata_json['date_inconnue'] ?? false),
                'metadata' => $t->metadata_json,
                'transitioned_at' => $t->created_at->toIso8601String(),
            ])
            ->values()
            ->all();

        $current = $mission->status->getValue();

        return [
            'mission_id' => $mission->id,
            'current_state' => $current,
            'current_state_label' => MissionState::labelFor($current),
            'transitions' => $transitions,
        ];
    }
}
