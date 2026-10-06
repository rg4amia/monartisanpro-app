<?php

namespace App\Services;

use App\Models\Mission;
use App\Models\MissionStateTransition;
use App\Models\User;
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
     * Un chantier relève du Référent s'il doit le visiter, l'a visité, ou si
     * son montant dépasse le seuil de contrôle (Règle d'or 5).
     */
    public function concernsReferent(Mission $mission, User $referent): bool
    {
        return (bool) $mission->referent_required
            || (int) $mission->referent_validated_by === (int) $referent->id
            || (int) $mission->montant_total > (int) config('prosartisan.mission.referent_threshold', 2000000);
    }

    /**
     * Un membre de l'équipe peut-il suivre la discussion et le flux d'un
     * chantier ? Un administrateur porteur de la capacité des missions, ou le
     * Référent dont le chantier relève — jamais le seul rôle (Règles d'or 36
     * et 105).
     */
    public function staffMayFollow(Mission $mission, User $user): bool
    {
        return $user->isAdminWith('admin.missions.view')
            || ($user->role === 'referent' && $this->concernsReferent($mission, $user));
    }

    /**
     * @return array{mission_id: int, current_state: string, current_state_label: string, transitions: list<array<string, mixed>>}
     */
    public function timeline(Mission $mission, bool $maskStaff = false, bool $maskParties = false): array
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
                    // Le Référent instruit un chantier : il ne voit le nom d'aucune des parties.
                    'name' => $maskParties || ($maskStaff && in_array($t->user->role, ['admin', 'referent'], true)) ? null : $t->user->name,
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
