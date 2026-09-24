<?php

namespace App\Services\Admin;

use App\Models\FraudAlert;
use App\Models\Jalon;
use App\Models\User;
use App\Services\ScoreService;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Arbitrage des alertes de fraude depuis le backoffice : gel conservatoire,
 * levée du gel (libération des jalons suspendus), confirmation de fraude
 * (sanction de score) et classement sans suite. Chaque décision est auditée.
 */
class FraudAlertAdminService
{
    public function __construct(
        private WalletService $walletService,
        private ScoreService $scoreService,
        private AdminActivityLogger $audit,
    ) {}

    public function hold(FraudAlert $alert, User $admin, ?string $notes = null): void
    {
        $alert->update([
            'action_taken' => 'payment_hold',
            'statut' => 'en_analyse',
            'resolution_notes' => $notes ?? $alert->resolution_notes,
        ]);

        Log::warning("[ADMIN FRAUD HOLD] Alerte #{$alert->reference} mise en gel par admin #{$admin->id}");
        $this->audit->log('fraud_alert.hold', $alert, ['notes' => $notes], $alert->reference, $admin);
    }

    /**
     * Lève le gel et traite les jalons suspendus de la mission comme une
     * validation client ordinaire : au-delà du seuil Référent (Règle d'or 5),
     * le jalon passe à « valide » mais reste impayé jusqu'à la validation
     * physique ; en deçà, il est payé immédiatement.
     *
     * @return array{paid: int, awaiting_referent: int}
     */
    public function release(FraudAlert $alert, User $admin, ?string $notes = null): array
    {
        $mission = $alert->mission;

        if ($mission?->isFundsFrozen()) {
            throw ValidationException::withMessages([
                'fraud_alert' => ['Les fonds de cette mission sont gelés par un litige : arbitrez le litige avant de lever le gel.'],
            ]);
        }

        $result = DB::transaction(function () use ($alert, $admin, $notes, $mission) {
            $alert->update([
                'action_taken' => 'none',
                'statut' => 'classee',
                'resolved_by' => $admin->id,
                'resolved_at' => now(),
                'resolution_notes' => $notes ?? 'Fonds libérés après vérification administrative.',
            ]);

            $result = ['paid' => 0, 'awaiting_referent' => 0];
            if ($mission === null) {
                return $result;
            }

            $seuil = config('prosartisan.mission.referent_threshold', 2000000);
            $referentRequired = $mission->montant_total > $seuil;

            $suspended = Jalon::where('mission_id', $mission->id)
                ->where('statut', 'valide_suspendu')
                ->lockForUpdate()
                ->get();

            foreach ($suspended as $jalon) {
                $jalon->update(['statut' => 'valide']);

                if ($referentRequired) {
                    $mission->update(['referent_required' => true]);
                    $result['awaiting_referent']++;
                    Log::info("[ADMIN FRAUD RELEASE] Jalon #{$jalon->id} validé, paiement en attente du Référent (mission > {$seuil} FCFA).");

                    continue;
                }

                $this->walletService->releaseJalon($jalon);
                $result['paid']++;
                Log::info("[ADMIN FRAUD RELEASE] Jalon #{$jalon->id} libéré suite à la levée d'alerte #{$alert->reference}");
            }

            return $result;
        });

        $this->audit->log('fraud_alert.released', $alert, ['notes' => $notes] + $result, $alert->reference, $admin);

        return $result;
    }

    public function confirm(FraudAlert $alert, User $admin, string $notes): void
    {
        DB::transaction(function () use ($alert, $admin, $notes) {
            $alert->update([
                'statut' => 'confirmee',
                'resolved_by' => $admin->id,
                'resolved_at' => now(),
                'resolution_notes' => $notes,
            ]);

            $user = $alert->user;
            if ($user && in_array($user->role, ['artisan', 'fournisseur'], true)) {
                $this->scoreService->recordEvent(
                    artisan: $user,
                    eventType: 'dispute_fraud',
                    missionId: $alert->mission_id,
                    description: "Fraude confirmée par arbitrage admin (#{$alert->reference}) : {$notes}"
                );
            }
        });

        $this->audit->log('fraud_alert.confirmed', $alert, ['notes' => $notes], $alert->reference, $admin);
    }

    public function dismiss(FraudAlert $alert, User $admin, ?string $notes = null): void
    {
        $alert->update([
            'statut' => 'rejetee',
            'action_taken' => 'none',
            'resolved_by' => $admin->id,
            'resolved_at' => now(),
            'resolution_notes' => $notes ?? 'Faux positif classé sans suite.',
        ]);

        $this->audit->log('fraud_alert.dismissed', $alert, ['notes' => $notes], $alert->reference, $admin);
    }
}
