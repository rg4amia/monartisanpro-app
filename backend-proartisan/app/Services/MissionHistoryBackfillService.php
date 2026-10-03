<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Mission;
use App\Models\MissionStateTransition;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reconstitue l'historique des missions financées ou clôturées avant le
 * Chantier 19 : leurs changements d'état écrivaient la colonne directement
 * et n'ont laissé aucune ligne dans `mission_state_transitions`.
 *
 * Chaque ligne ajoutée est déduite d'un fait enregistré (paiement confirmé,
 * étape validée, litige ouvert ou résolu) et datée de ce fait. Elle est
 * marquée `reconstitue` et n'a pas d'acteur : l'historique distingue ce qui a
 * été consigné sur le moment de ce qui a été déduit. Une date qui n'a pas été
 * conservée n'est jamais inventée : la ligne est marquée `date_inconnue`.
 *
 * Idempotent : un état déjà présent dans l'historique d'une mission n'est pas
 * ajouté une seconde fois.
 */
class MissionHistoryBackfillService
{
    /** États atteints seulement après financement, clôture, litige ou annulation. */
    private const TARGET_STATES = ['funded_locked', 'in_progress', 'pending_approval', 'completed', 'disputed', 'cancelled'];

    /**
     * @return array{missions: int, incomplete: int, lines: int}
     */
    public function run(bool $write): array
    {
        $report = ['missions' => 0, 'incomplete' => 0, 'lines' => 0];

        Mission::query()
            ->whereIn('status', self::TARGET_STATES)
            ->with(['jalons', 'litiges', 'transactions', 'stateTransitions'])
            ->chunkById(200, function ($missions) use ($write, &$report): void {
                foreach ($missions as $mission) {
                    $report['missions']++;
                    $lines = $this->missingLines($mission);

                    if ($lines === []) {
                        continue;
                    }

                    $report['incomplete']++;
                    $report['lines'] += count($lines);

                    if ($write) {
                        DB::transaction(fn () => MissionStateTransition::insert($lines));
                    }
                }
            });

        return $report;
    }

    /**
     * Lignes manquantes de l'historique d'une mission, prêtes à être insérées.
     *
     * @return list<array<string, mixed>>
     */
    public function missingLines(Mission $mission): array
    {
        $recorded = $mission->stateTransitions
            ->map(fn (MissionStateTransition $t) => ['to' => $t->to_state, 'at' => $t->created_at, 'new' => false])
            ->all();
        $known = array_column($recorded, 'to');

        $derived = [];
        foreach ($this->facts($mission) as $fact) {
            // Un litige peut se répéter : seul le premier passage par un état
            // absent de l'historique est reconstitué.
            if (in_array($fact['to'], $known, true)) {
                continue;
            }
            $known[] = $fact['to'];
            $derived[] = $fact + ['new' => true];
        }

        // L'état courant termine la chaîne, même sans fait daté pour l'expliquer.
        $current = (string) $mission->status;
        if (! in_array($current, $known, true)) {
            $derived[] = [
                'to' => $current,
                'at' => $mission->updated_at ?? now(),
                'reason' => 'État constaté à la reconstitution',
                'unknown_date' => true,
                'new' => true,
            ];
        }

        if ($derived === []) {
            return [];
        }

        // Chaîne complète dans l'ordre chronologique : l'état de départ de
        // chaque ligne ajoutée est l'état d'arrivée de la précédente.
        $chain = [...$recorded, ...$derived];
        usort($chain, fn (array $a, array $b) => [$a['at']->getTimestamp(), $a['new']] <=> [$b['at']->getTimestamp(), $b['new']]);

        $lines = [];
        $previous = $mission->stateTransitions->sortBy('created_at')->first()?->from_state ?? 'draft';

        foreach ($chain as $step) {
            if ($step['new'] && $previous !== $step['to']) {
                $lines[] = [
                    'mission_id' => $mission->id,
                    'from_state' => $previous,
                    'to_state' => $step['to'],
                    'user_id' => null,
                    'reason' => $step['reason'],
                    'metadata_json' => json_encode(array_filter([
                        'reconstitue' => true,
                        'date_inconnue' => $step['unknown_date'] ?? null,
                        'source' => $step['source'] ?? null,
                    ])),
                    'created_at' => $step['at'],
                ];
            }
            $previous = $step['to'];
        }

        return $lines;
    }

    /**
     * Changements d'état attestés par les données de la mission.
     *
     * @return list<array{to: string, at: Carbon, reason: string, source?: string, unknown_date?: bool}>
     */
    private function facts(Mission $mission): array
    {
        $facts = [];

        // Financement : premier paiement confirmé de la mission.
        $payment = $mission->transactions
            ->filter(fn ($t) => $t->statut === PaymentStatus::CONFIRME && $t->type === 'acompte')
            ->sortBy('created_at')
            ->first();

        if ($payment) {
            $facts[] = ['to' => 'pending_funding', 'at' => $payment->created_at, 'reason' => "Paiement de l'acompte initié", 'source' => 'transaction#'.$payment->id];
            $facts[] = ['to' => 'funded_locked', 'at' => $payment->updated_at ?? $payment->created_at, 'reason' => 'Acompte confirmé', 'source' => 'transaction#'.$payment->id];
        }

        // Démarrage : première étape validée (la date de soumission n'est pas conservée).
        $firstStep = $mission->jalons->whereNotNull('valide_at')->sortBy('valide_at')->first()
            ?? $mission->jalons->whereNotNull('paye_at')->sortBy('paye_at')->first();

        if ($firstStep) {
            $facts[] = ['to' => 'in_progress', 'at' => $firstStep->valide_at ?? $firstStep->paye_at, 'reason' => 'Chantier démarré (première étape validée)', 'source' => 'jalon#'.$firstStep->id];
        }

        // Litiges : ouverture, puis issue.
        foreach ($mission->litiges->sortBy('created_at') as $litige) {
            $facts[] = ['to' => 'disputed', 'at' => $litige->created_at, 'reason' => 'Litige ouvert', 'source' => 'litige#'.$litige->id];

            if ($litige->resolu_at && in_array($litige->decision, ['client', 'artisan', 'mixte'], true)) {
                $facts[] = [
                    'to' => $litige->decision === 'client' ? 'cancelled' : 'completed',
                    'at' => Carbon::parse($litige->resolu_at),
                    'reason' => 'Litige arbitré',
                    'source' => 'litige#'.$litige->id,
                ];
            }
        }

        // Clôture sans litige : dernière étape payée. Avant le Chantier 19, la
        // mission se clôturait d'elle-même à ce moment, sans validation finale.
        $steps = $mission->jalons;
        $isCompleted = (string) $mission->status === 'completed';
        $closedByDispute = collect($facts)->contains(fn ($fact) => $fact['to'] === 'completed');

        if ($isCompleted && ! $closedByDispute && $steps->isNotEmpty() && $steps->every(fn ($j) => $j->paye_at !== null)) {
            $last = $steps->sortByDesc('paye_at')->first();
            $facts[] = ['to' => 'completed', 'at' => $last->paye_at, 'reason' => 'Dernière étape payée', 'source' => 'jalon#'.$last->id];
        }

        return $facts;
    }
}
