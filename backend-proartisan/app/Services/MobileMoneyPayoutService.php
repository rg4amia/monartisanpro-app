<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\WalletType;
use App\Models\DriverCashout;
use App\Models\MobileMoneyPayout;
use App\Models\MobileMoneyPayoutEvent;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Admin\AdminActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Versements Mobile Money sortants (artisan, livreur) avec reprise sur échec.
 *
 * Invariant : le portefeuille du bénéficiaire n'est débité qu'une fois le
 * virement confirmé par l'opérateur. Auparavant, `releaseJalon` débitait
 * d'abord puis tentait le virement : un échec laissait la transaction « en
 * attente » pour toujours, sans relance, et les fonds ne figuraient plus nulle
 * part. Désormais un versement échoué reste sur le portefeuille, est relancé
 * automatiquement (délai croissant), peut être relancé par l'admin ou le
 * bénéficiaire, ou soldé par un versement manuel tracé. Chaque action est
 * consignée dans `mobile_money_payout_events` (append-only).
 */
class MobileMoneyPayoutService
{
    /** Relances automatiques au-delà desquelles seule une action humaine relance. */
    public const MAX_AUTOMATIC_ATTEMPTS = 5;

    /** Délai minimal entre deux relances à l'initiative du bénéficiaire. */
    public const BENEFICIARY_RETRY_COOLDOWN_SECONDS = 60;

    public function __construct(
        private WalletService $walletService,
        private WaveService $waveService,
        private OrangeMoneyService $orangeMoneyService,
        private NotificationService $notifications,
        private AdminActivityLogger $audit,
    ) {}

    /**
     * Crée le versement et sa transaction, puis tente le virement.
     *
     * @param  array  $transactionAttributes  type, wallet_source, wallet_dest, metadata…
     * @param  array  $ledgerMetadata  métadonnées de l'écriture de débit (mission_id, jalon_id, type…)
     * @param  int|null  $transferAmount  montant viré s'il diffère du montant débité (frais de retrait)
     * @param  string|null  $phone  numéro imposé (retrait livreur) ; sinon numéro de paiement courant
     * @param  bool  $attempt  faux pour un versement soldé ensuite manuellement (virement bancaire)
     */
    public function dispatch(
        User $beneficiary,
        WalletType $walletType,
        int $montant,
        string $context,
        string $provider,
        string $description,
        array $transactionAttributes,
        array $ledgerMetadata = [],
        ?int $transferAmount = null,
        ?string $phone = null,
        bool $attempt = true,
        ?User $actor = null,
    ): MobileMoneyPayout {
        if ($montant <= 0 || ($transferAmount !== null && ($transferAmount <= 0 || $transferAmount > $montant))) {
            throw new \InvalidArgumentException('Le montant du versement doit être supérieur à 0.');
        }

        $transaction = Transaction::create(array_merge([
            'user_id' => $beneficiary->id,
            'montant' => $transferAmount ?? $montant,
            'provider' => $provider,
            'statut' => PaymentStatus::EN_ATTENTE,
        ], $transactionAttributes));

        $payout = MobileMoneyPayout::create([
            'reference' => MobileMoneyPayout::generateReference(),
            'user_id' => $beneficiary->id,
            'wallet_type' => $walletType->value,
            'context' => $context,
            'montant' => $montant,
            'montant_transfere' => $transferAmount,
            'phone_locked' => $phone !== null,
            'provider' => $provider,
            'phone' => $phone ?? $this->beneficiaryPhone($beneficiary),
            'statut' => MobileMoneyPayout::STATUT_EN_COURS,
            'mission_id' => $ledgerMetadata['mission_id'] ?? null,
            'jalon_id' => $ledgerMetadata['jalon_id'] ?? null,
            'transaction_id' => $transaction->id,
            'description' => mb_substr($description, 0, 255),
            'ledger_metadata' => $ledgerMetadata,
        ]);

        $transaction->update([
            'metadata' => array_merge($transaction->metadata ?? [], ['payout_id' => $payout->id, 'payout_reference' => $payout->reference]),
        ]);

        if (! $attempt) {
            return $payout;
        }

        return $this->attempt($payout, MobileMoneyPayoutEvent::ACTION_TENTATIVE, $actor);
    }

    /**
     * Première tentative d'un versement créé sans tentative immédiate.
     */
    public function attemptNow(MobileMoneyPayout $payout, ?User $actor = null): MobileMoneyPayout
    {
        return $this->attempt($payout, MobileMoneyPayoutEvent::ACTION_TENTATIVE, $actor);
    }

    /**
     * Relance manuelle depuis le backoffice.
     */
    public function retryByAdmin(MobileMoneyPayout $payout, User $admin): MobileMoneyPayout
    {
        $this->assertRetryable($payout);

        $payout = $this->attempt($payout, MobileMoneyPayoutEvent::ACTION_RELANCE_MANUELLE, $admin);

        $this->audit->log('payout.retried', $payout, [
            'statut' => $payout->statut,
            'attempts' => $payout->attempts,
        ], $payout->reference, $admin);

        return $payout;
    }

    /**
     * Relance à l'initiative du bénéficiaire (après correction de son numéro
     * de paiement, par exemple). Propriété vérifiée ici (Règle d'or 36).
     */
    public function retryByBeneficiary(MobileMoneyPayout $payout, User $user): MobileMoneyPayout
    {
        if ($payout->user_id !== $user->id) {
            throw new \DomainException('Ce versement ne vous appartient pas.', 403);
        }

        $this->assertRetryable($payout);

        if ($payout->updated_at && $payout->updated_at->gt(now()->subSeconds(self::BENEFICIARY_RETRY_COOLDOWN_SECONDS))) {
            throw new \InvalidArgumentException('Une tentative vient d\'être effectuée. Patientez une minute avant de relancer.');
        }

        return $this->attempt($payout, MobileMoneyPayoutEvent::ACTION_RELANCE_BENEFICIAIRE, $user);
    }

    /**
     * Relance automatique des versements échoués arrivés à échéance.
     *
     * @return array{retried: int, paid: int, failed: int}
     */
    public function retryDue(): array
    {
        $due = MobileMoneyPayout::where('statut', MobileMoneyPayout::STATUT_ECHOUE)
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->orderBy('next_retry_at')
            ->limit(100)
            ->get();

        $paid = 0;
        $failed = 0;

        foreach ($due as $payout) {
            try {
                $result = $this->attempt($payout, MobileMoneyPayoutEvent::ACTION_RELANCE_AUTOMATIQUE);
                $result->isPaid() ? $paid++ : $failed++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error("[Versements] Relance automatique impossible pour {$payout->reference} : {$e->getMessage()}");
            }
        }

        return ['retried' => $due->count(), 'paid' => $paid, 'failed' => $failed];
    }

    /**
     * Solde un versement réglé hors plateforme (virement bancaire, espèces…)
     * sur justificatif : le portefeuille est alors débité comme pour un
     * virement réussi.
     */
    public function markPaidManually(MobileMoneyPayout $payout, User $admin, string $externalReference, ?string $note = null): MobileMoneyPayout
    {
        return DB::transaction(function () use ($payout, $admin, $externalReference, $note) {
            $payout = MobileMoneyPayout::lockForUpdate()->findOrFail($payout->id);

            if (! in_array($payout->statut, MobileMoneyPayout::STATUTS_NON_ABOUTIS, true)) {
                throw new \InvalidArgumentException('Seul un versement non abouti peut être soldé manuellement.');
            }

            $this->assertWalletCovers($payout);

            $before = $payout->statut;
            $this->settle($payout, $externalReference);

            $this->recordEvent($payout, MobileMoneyPayoutEvent::ACTION_VERSEMENT_MANUEL, $before, $note, $admin, [
                'external_reference' => $externalReference,
            ]);

            $this->audit->log('payout.paid_manually', $payout, [
                'external_reference' => $externalReference,
                'montant' => $payout->montant,
                'note' => $note,
            ], $payout->reference, $admin);

            return $payout->fresh();
        });
    }

    /**
     * Annule un versement non abouti : les fonds restent sur le portefeuille.
     * Réservé aux retraits livreur (demande rejetée) — un paiement d'étape de
     * chantier ou de litige est dû à l'artisan et ne s'annule pas.
     */
    public function cancel(MobileMoneyPayout $payout, ?User $actor, string $reason): MobileMoneyPayout
    {
        if ($payout->context !== MobileMoneyPayout::CONTEXT_RETRAIT_LIVREUR) {
            throw new \InvalidArgumentException('Un versement dû à l\'artisan ne peut pas être annulé : relancez-le ou soldez-le manuellement.');
        }

        return DB::transaction(function () use ($payout, $actor, $reason) {
            $payout = MobileMoneyPayout::lockForUpdate()->findOrFail($payout->id);

            if (! in_array($payout->statut, MobileMoneyPayout::STATUTS_NON_ABOUTIS, true)) {
                throw new \InvalidArgumentException('Ce versement n\'est plus annulable.');
            }

            $before = $payout->statut;
            $payout->update(['statut' => MobileMoneyPayout::STATUT_ANNULE, 'next_retry_at' => null]);
            $payout->transaction?->update([
                'statut' => PaymentStatus::ECHOUE,
                'failed_at' => now(),
                'error_message' => 'Versement annulé : '.$reason,
            ]);

            $this->recordEvent($payout, MobileMoneyPayoutEvent::ACTION_ANNULATION, $before, $reason, $actor);

            return $payout->fresh();
        });
    }

    /**
     * Montant des versements non aboutis d'une mission, encore présents sur
     * le portefeuille mais déjà dus : réservé dans le séquestre de la mission.
     */
    public function reservedForMission(int $missionId, WalletType $walletType): int
    {
        return (int) MobileMoneyPayout::where('mission_id', $missionId)
            ->where('wallet_type', $walletType->value)
            ->whereIn('statut', MobileMoneyPayout::STATUTS_NON_ABOUTIS)
            ->sum('montant');
    }

    /**
     * Versements du bénéficiaire, les non aboutis en tête.
     */
    public function listFor(User $user, int $limit = 50): array
    {
        return MobileMoneyPayout::where('user_id', $user->id)
            ->orderByRaw("CASE WHEN statut = 'echoue' THEN 0 WHEN statut = 'en_cours' THEN 1 ELSE 2 END")
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (MobileMoneyPayout $payout) => $this->present($payout))
            ->values()
            ->all();
    }

    /**
     * Vue backoffice : versements à traiter (échoués ou en cours) avec leur
     * historique, les derniers versements aboutis et les indicateurs.
     */
    public function adminOverview(): array
    {
        $pending = MobileMoneyPayout::with('user:id,name,phone,role')
            ->whereIn('statut', MobileMoneyPayout::STATUTS_NON_ABOUTIS)
            ->orderByRaw("CASE WHEN statut = 'echoue' THEN 0 ELSE 1 END")
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (MobileMoneyPayout $payout) => $this->present($payout));

        $recent = MobileMoneyPayout::with('user:id,name,phone,role')
            ->whereIn('statut', [MobileMoneyPayout::STATUT_VERSE, MobileMoneyPayout::STATUT_ANNULE])
            ->latest('updated_at')
            ->limit(20)
            ->get()
            ->map(fn (MobileMoneyPayout $payout) => $this->present($payout));

        return [
            'pending' => $pending->values()->all(),
            'recent' => $recent->values()->all(),
            'stats' => [
                'failed_count' => MobileMoneyPayout::where('statut', MobileMoneyPayout::STATUT_ECHOUE)->count(),
                'failed_amount' => (int) MobileMoneyPayout::where('statut', MobileMoneyPayout::STATUT_ECHOUE)->sum('montant'),
                'exhausted_count' => MobileMoneyPayout::where('statut', MobileMoneyPayout::STATUT_ECHOUE)->whereNull('next_retry_at')->count(),
                'paid_today' => (int) MobileMoneyPayout::where('statut', MobileMoneyPayout::STATUT_VERSE)->where('paid_at', '>=', now()->startOfDay())->sum('montant'),
            ],
        ];
    }

    /**
     * Présentation d'un versement et de son historique pour l'API mobile et
     * le backoffice.
     */
    public function present(MobileMoneyPayout $payout, bool $withEvents = true): array
    {
        $data = [
            'id' => $payout->id,
            'reference' => $payout->reference,
            'context' => $payout->context,
            'context_label' => $payout->contextLabel(),
            'montant' => $payout->montant,
            'montant_transfere' => $payout->transferAmount(),
            'provider' => $payout->provider,
            'phone' => $payout->phone,
            'statut' => $payout->statut,
            'statut_label' => $payout->statutLabel(),
            'attempts' => $payout->attempts,
            'last_error' => $payout->last_error,
            'next_retry_at' => $payout->next_retry_at?->toIso8601String(),
            'paid_at' => $payout->paid_at?->toIso8601String(),
            'external_reference' => $payout->external_reference,
            'mission_id' => $payout->mission_id,
            'description' => $payout->description,
            'beneficiary' => $payout->relationLoaded('user') && $payout->user ? [
                'id' => $payout->user->id,
                'name' => $payout->user->name,
                'phone' => $payout->user->phone,
                'role' => $payout->user->role,
            ] : null,
            'can_retry' => $payout->isRetryable(),
            'created_at' => $payout->created_at?->toIso8601String(),
        ];

        if ($withEvents) {
            $data['events'] = $payout->events()->with('actor:id,name')->get()->map(fn (MobileMoneyPayoutEvent $event) => [
                'id' => $event->id,
                'action' => $event->action,
                'action_label' => $event->actionLabel(),
                'statut_avant' => $event->statut_avant,
                'statut_apres' => $event->statut_apres,
                'message' => $event->message,
                'actor' => $event->actor?->name,
                'created_at' => $event->created_at?->toIso8601String(),
            ])->values()->all();
        }

        return $data;
    }

    // ── Mécanique interne ───────────────────────────────────────────────

    private function attempt(MobileMoneyPayout $payout, string $action, ?User $actor = null): MobileMoneyPayout
    {
        $payout = MobileMoneyPayout::lockForUpdate()->findOrFail($payout->id);

        if (! in_array($payout->statut, MobileMoneyPayout::STATUTS_NON_ABOUTIS, true)) {
            return $payout;
        }

        $beneficiary = $payout->user;
        $before = $payout->statut;

        // Un bénéficiaire qui corrige son numéro de paiement doit voir la
        // relance partir vers le nouveau numéro, pas vers celui de l'échec.
        $phone = $payout->phone_locked ? $payout->phone : $this->beneficiaryPhone($beneficiary);
        if ($phone && $phone !== $payout->phone) {
            $this->recordEvent($payout, MobileMoneyPayoutEvent::ACTION_CHANGEMENT_NUMERO, $before, "Nouveau numéro : {$phone}", $actor, [
                'ancien' => $payout->phone,
                'nouveau' => $phone,
            ]);
            $payout->phone = $phone;
        }

        $payout->attempts++;
        $payout->save();

        $this->recordEvent($payout, $action, $before, null, $actor, ['tentative' => $payout->attempts]);

        try {
            $this->assertWalletCovers($payout);

            if (! $payout->phone) {
                throw new \RuntimeException('Aucun numéro de paiement Mobile Money renseigné pour le bénéficiaire.');
            }

            $result = $this->transfer($payout->provider, $payout->phone, $payout->transferAmount(), (string) $payout->description);
        } catch (\Throwable $e) {
            return $this->markFailed($payout, $e->getMessage(), $actor);
        }

        return DB::transaction(function () use ($payout, $result) {
            $this->settle($payout, $result['id'] ?? $result['txnid'] ?? null);
            $this->recordEvent($payout, MobileMoneyPayoutEvent::ACTION_SUCCES, MobileMoneyPayout::STATUT_EN_COURS, null, null, [
                'external_reference' => $payout->external_reference,
            ]);

            return $payout->fresh();
        });
    }

    /**
     * Répercute l'issue d'un versement sur l'objet métier qui l'a engagé.
     */
    private function afterSettlement(MobileMoneyPayout $payout): void
    {
        if ($payout->context === MobileMoneyPayout::CONTEXT_RETRAIT_LIVREUR) {
            $cashout = DriverCashout::where('payout_id', $payout->id)->first();
            if ($cashout) {
                app(DriverCashoutService::class)->syncWithPayout($cashout);
            }
        }
    }

    /**
     * Débite le portefeuille et clôt le versement et sa transaction.
     */
    private function settle(MobileMoneyPayout $payout, ?string $externalReference): void
    {
        $metadata = array_merge($payout->ledger_metadata ?? [], [
            'payout_id' => $payout->id,
            'payout_reference' => $payout->reference,
        ]);

        $this->walletService->debit(
            $payout->user,
            WalletType::from($payout->wallet_type),
            $payout->montant,
            (string) $payout->description,
            $metadata
        );

        $payout->update([
            'statut' => MobileMoneyPayout::STATUT_VERSE,
            'paid_at' => now(),
            'external_reference' => $externalReference,
            'next_retry_at' => null,
            'last_error' => null,
        ]);

        $payout->transaction?->update([
            'statut' => PaymentStatus::CONFIRME,
            'reference_externe' => $externalReference,
            'paid_at' => now(),
            'failed_at' => null,
            'error_message' => null,
        ]);

        $this->afterSettlement($payout);
    }

    private function markFailed(MobileMoneyPayout $payout, string $error, ?User $actor): MobileMoneyPayout
    {
        $firstFailure = $payout->attempts === 1;
        $nextRetry = $payout->attempts < self::MAX_AUTOMATIC_ATTEMPTS
            ? now()->addMinutes(15 * (2 ** ($payout->attempts - 1)))
            : null;

        $payout->update([
            'statut' => MobileMoneyPayout::STATUT_ECHOUE,
            'last_error' => mb_substr($error, 0, 1000),
            'next_retry_at' => $nextRetry,
        ]);

        $payout->transaction?->update([
            'statut' => PaymentStatus::ECHOUE,
            'failed_at' => now(),
            'error_message' => mb_substr($error, 0, 1000),
        ]);

        $this->recordEvent($payout, MobileMoneyPayoutEvent::ACTION_ECHEC, MobileMoneyPayout::STATUT_EN_COURS, $error, $actor, [
            'prochaine_relance' => $nextRetry?->toIso8601String(),
        ]);

        Log::warning("[Versements] Échec du virement {$payout->reference}", [
            'user_id' => $payout->user_id,
            'montant' => $payout->montant,
            'attempts' => $payout->attempts,
            'error' => $error,
        ]);

        if ($firstFailure) {
            try {
                $this->notifications->send(
                    $payout->user,
                    'payment',
                    'Virement Mobile Money en attente',
                    'Le virement de '.number_format($payout->transferAmount(), 0, ',', ' ')." FCFA ({$payout->contextLabel()}) n'a pas abouti. Les fonds restent sur votre portefeuille et le virement sera relancé automatiquement. Vérifiez votre numéro de paiement."
                );
            } catch (\Throwable $e) {
                Log::warning('[Versements] Notification d\'échec non envoyée : '.$e->getMessage());
            }
        }

        return $payout->fresh();
    }

    private function assertRetryable(MobileMoneyPayout $payout): void
    {
        if (! $payout->isRetryable()) {
            throw new \InvalidArgumentException('Seul un versement échoué peut être relancé.');
        }
    }

    private function assertWalletCovers(MobileMoneyPayout $payout): void
    {
        $balance = $payout->user->getWalletBalance(WalletType::from($payout->wallet_type));

        if ($balance < $payout->montant) {
            throw new \RuntimeException("Solde du portefeuille insuffisant pour ce versement ({$balance} FCFA disponibles).");
        }
    }

    private function beneficiaryPhone(User $user): ?string
    {
        return $user->payment_phone ?: $user->phone;
    }

    private function transfer(string $provider, string $phone, int $montant, string $description): array
    {
        if ($provider === 'orange_money') {
            return $this->orangeMoneyService->transferToMobileMoney($phone, $montant, $description);
        }

        return $this->waveService->transferToMobileMoney($phone, $montant, $description);
    }

    private function recordEvent(
        MobileMoneyPayout $payout,
        string $action,
        ?string $before,
        ?string $message,
        ?User $actor,
        array $metadata = [],
    ): void {
        MobileMoneyPayoutEvent::create([
            'payout_id' => $payout->id,
            'action' => $action,
            'statut_avant' => $before,
            'statut_apres' => $payout->statut,
            'message' => $message !== null ? mb_substr($message, 0, 2000) : null,
            'actor_id' => $actor?->id,
            'metadata' => $metadata ?: null,
        ]);
    }
}
