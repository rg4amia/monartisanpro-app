<?php

namespace App\Services;

use App\Enums\WalletOperation;
use App\Enums\WalletType;
use App\Models\Devis;
use App\Models\Jalon;
use App\Models\Litige;
use App\Models\Mission;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\States\Mission\CompletedState;
use App\States\Mission\FundedLockedState;
use App\States\Mission\InProgressState;
use App\States\Mission\PendingApprovalState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WalletService
{
    public function __construct(
        private WaveService $waveService,
        private OrangeMoneyService $orangeMoneyService
    ) {}

    /**
     * Créditer un wallet utilisateur
     */
    public function credit(
        User $user,
        WalletType $walletType,
        int $montant,
        ?string $description = null,
        array $metadata = []
    ): WalletTransaction {
        if ($montant <= 0) {
            throw new \InvalidArgumentException('Le montant doit être supérieur à 0');
        }

        return DB::transaction(function () use ($user, $walletType, $montant, $description, $metadata) {
            // Lock user row pour éviter les race conditions
            $user = User::lockForUpdate()->findOrFail($user->id);

            $columnName = $walletType->columnName();
            $soldeAvant = $user->{$columnName} ?? 0;
            $soldeApres = $soldeAvant + $montant;

            // Mise à jour de l'utilisateur
            $user->update([$columnName => $soldeApres]);

            // Création de la transaction de wallet
            $walletTransaction = WalletTransaction::create([
                'user_id' => $user->id,
                'wallet_type' => $walletType->value,
                'operation' => WalletOperation::CREDIT,
                'montant' => $montant,
                'mission_id' => $metadata['mission_id'] ?? null,
                'jalon_id' => $metadata['jalon_id'] ?? null,
                'transaction_id' => $metadata['transaction_id'] ?? null,
                'reference' => 'WTX-'.strtoupper(Str::random(12)),
                'cle_idempotence' => $metadata['idempotency_key'] ?? (string) Str::uuid(),
                'solde_avant' => $soldeAvant,
                'solde_apres' => $soldeApres,
                'description' => $description ?? "Crédit wallet {$walletType->label()}",
                'metadata' => $metadata,
            ]);

            Log::info('Wallet crédité', [
                'user_id' => $user->id,
                'wallet_type' => $walletType->value,
                'montant' => $montant,
                'reference' => $walletTransaction->reference,
            ]);

            return $walletTransaction;
        });
    }

    /**
     * Débiter un wallet utilisateur
     */
    public function debit(
        User $user,
        WalletType $walletType,
        int $montant,
        ?string $description = null,
        array $metadata = []
    ): WalletTransaction {
        if ($montant <= 0) {
            throw new \InvalidArgumentException('Le montant doit être supérieur à 0');
        }

        return DB::transaction(function () use ($user, $walletType, $montant, $description, $metadata) {
            // Lock user row pour éviter les race conditions
            $user = User::lockForUpdate()->findOrFail($user->id);

            $columnName = $walletType->columnName();
            $soldeAvant = $user->{$columnName} ?? 0;

            if ($soldeAvant < $montant) {
                throw new \Exception("Solde insuffisant dans le wallet {$walletType->label()}");
            }

            $soldeApres = $soldeAvant - $montant;

            // Mise à jour de l'utilisateur
            $user->update([$columnName => $soldeApres]);
            // Création de la transaction de wallet avec clé d'idempotence
            $walletTransaction = WalletTransaction::create([
                'user_id' => $user->id,
                'wallet_type' => $walletType->value,
                'operation' => WalletOperation::DEBIT,
                'montant' => $montant,
                'mission_id' => $metadata['mission_id'] ?? null,
                'jalon_id' => $metadata['jalon_id'] ?? null,
                'transaction_id' => $metadata['transaction_id'] ?? null,
                'reference' => 'WTX-'.strtoupper(Str::random(12)),
                'cle_idempotence' => $metadata['idempotency_key'] ?? (string) Str::uuid(),
                'solde_avant' => $soldeAvant,
                'solde_apres' => $soldeApres,
                'description' => $description ?? "Débit wallet {$walletType->label()}",
                'metadata' => $metadata,
            ]);
            Log::info('Wallet débité', [
                'user_id' => $user->id,
                'wallet_type' => $walletType->value,
                'montant' => $montant,
                'reference' => $walletTransaction->reference,
            ]);

            return $walletTransaction;
        });
    }

    /**
     * Transférer des fonds d'un wallet à un autre
     */
    public function transfer(
        User $sourceUser,
        WalletType $sourceWalletType,
        User $destUser,
        WalletType $destWalletType,
        int $montant,
        ?string $description = null,
        array $metadata = []
    ): array {
        return DB::transaction(function () use (
            $sourceUser,
            $sourceWalletType,
            $destUser,
            $destWalletType,
            $montant,
            $description,
            $metadata
        ) {
            // Débit du wallet source
            $debitTx = $this->debit(
                $sourceUser,
                $sourceWalletType,
                $montant,
                $description ?? "Transfert vers {$destUser->phone}",
                array_merge($metadata, ['transfer_to' => $destUser->id])
            );

            // Crédit du wallet destination
            $creditTx = $this->credit(
                $destUser,
                $destWalletType,
                $montant,
                $description ?? "Transfert depuis {$sourceUser->phone}",
                array_merge($metadata, ['transfer_from' => $sourceUser->id])
            );

            return [
                'debit' => $debitTx,
                'credit' => $creditTx,
            ];
        });
    }

    /**
     * Fragmente le séquestre lors de l'acceptation du devis.
     */
    public function fragmentEscrow(
        Mission $mission,
        User $client,
        User $artisan,
        int $montantTotal,
        float $ratioMat,
        Transaction $paiementTransaction
    ): void {
        $devis = $mission->devis()->where('statut', 'accepte')->first();
        if ($devis) {
            $montantMat = $devis->montant_materiaux;
            $montantMo = $devis->montant_mo;
            if ($montantMat + $montantMo !== $montantTotal) {
                $montantMo = $montantTotal - $montantMat;
            }
        } else {
            $montantMat = (int) round($montantTotal * $ratioMat);
            $montantMo = $montantTotal - $montantMat;
        }

        DB::transaction(function () use (
            $mission,
            $artisan,
            $montantTotal,
            $montantMat,
            $montantMo,
            $ratioMat,
            $paiementTransaction
        ) {
            $isHybrid = ($paiementTransaction->metadata['payment_type'] ?? 'total') === 'hybrid';

            // Mise à jour mission
            $mission->update([
                'montant_total' => $montantTotal,
                'montant_materiaux' => $montantMat,
                'montant_mo' => $montantMo,
                'ratio_materiaux' => $ratioMat,
                'status' => 'funded_locked',
                'payment_type' => $isHybrid ? 'hybrid' : 'total',
            ]);

            // Crédit du wallet_materiaux de l'artisan
            if ($montantMat > 0) {
                $this->credit(
                    $artisan,
                    WalletType::WALLET_MATERIAUX,
                    $montantMat,
                    "Séquestre matériaux - Mission #{$mission->id}",
                    [
                        'mission_id' => $mission->id,
                        'transaction_id' => $paiementTransaction->id,
                        'type' => 'escrow_materiaux',
                    ]
                );
            }

            if (! $isHybrid && $montantMo > 0) {
                // Crédit du wallet_mo de l'artisan
                $this->credit(
                    $artisan,
                    WalletType::WALLET_MO,
                    $montantMo,
                    "Séquestre main d'œuvre - Mission #{$mission->id}",
                    [
                        'mission_id' => $mission->id,
                        'transaction_id' => $paiementTransaction->id,
                        'type' => 'escrow_mo',
                    ]
                );
            }

            // Chantier 9A : Écriture au Grand Livre en Partie Double (BCEAO)
            try {
                app(\App\Services\DoubleEntryLedgerService::class)->recordEscrowFunding(
                    $mission,
                    $montantMo,
                    $montantMat,
                    $paiementTransaction->id
                );
            } catch (\Throwable $e) {
                Log::warning('Échec enregistrement double entry ledger fragmentEscrow: '.$e->getMessage());
            }

            Log::info('Séquestre fragmenté', [
                'mission_id' => $mission->id,
                'montant_total' => $montantTotal,
                'montant_materiaux' => $montantMat,
                'montant_mo' => $montantMo,
                'ratio_materiaux' => $ratioMat,
            ]);
        });
    }

    /**
     * Applique les fonds d'un avenant de devis au séquestre (incrémentation).
     */
    public function applyAvenantEscrow(
        Mission $mission,
        Devis $avenant,
        Transaction $paiementTransaction
    ): void {
        $montantTotal = $avenant->montant_total;
        $montantMat = $avenant->montant_materiaux;
        $montantMo = $avenant->montant_mo;

        DB::transaction(function () use (
            $mission,
            $avenant,
            $montantTotal,
            $montantMat,
            $montantMo,
            $paiementTransaction
        ) {
            // Incrémentation des montants de la mission
            $mission->update([
                'montant_total' => $mission->montant_total + $montantTotal,
                'montant_materiaux' => $mission->montant_materiaux + $montantMat,
                'montant_mo' => $mission->montant_mo + $montantMo,
            ]);

            $artisan = $mission->artisan;

            // Crédit du wallet_materiaux de l'artisan
            if ($montantMat > 0) {
                $this->credit(
                    $artisan,
                    WalletType::WALLET_MATERIAUX,
                    $montantMat,
                    "Séquestre matériaux (Avenant) - Mission #{$mission->id}",
                    [
                        'mission_id' => $mission->id,
                        'transaction_id' => $paiementTransaction->id,
                        'devis_id' => $avenant->id,
                        'type' => 'escrow_materiaux_avenant',
                    ]
                );
            }

            // Crédit du wallet_mo de l'artisan
            if ($montantMo > 0) {
                $this->credit(
                    $artisan,
                    WalletType::WALLET_MO,
                    $montantMo,
                    "Séquestre main d'œuvre (Avenant) - Mission #{$mission->id}",
                    [
                        'mission_id' => $mission->id,
                        'transaction_id' => $paiementTransaction->id,
                        'devis_id' => $avenant->id,
                        'type' => 'escrow_mo_avenant',
                    ]
                );
            }

            // Chantier 9A : Écriture au Grand Livre en Partie Double pour l'avenant (BCEAO)
            try {
                app(\App\Services\DoubleEntryLedgerService::class)->recordEscrowFunding(
                    $mission,
                    $montantMo,
                    $montantMat,
                    $paiementTransaction->id
                );
            } catch (\Throwable $e) {
                Log::warning('Échec enregistrement double entry ledger applyAvenantEscrow: '.$e->getMessage());
            }

            Log::info('Séquestre avenant appliqué', [
                'mission_id' => $mission->id,
                'avenant_id' => $avenant->id,
                'montant_total_ajoute' => $montantTotal,
                'montant_materiaux_ajoute' => $montantMat,
                'montant_mo_ajoute' => $montantMo,
            ]);
        });
    }

    /**
     * Vrai si le jalon a été financé par son propre paiement (mode hybride).
     * Le ledger fait foi : un crédit `escrow_mo_jalon` rattaché au jalon.
     */
    public function isJalonFunded(Jalon $jalon): bool
    {
        return WalletTransaction::query()
            ->where('jalon_id', $jalon->id)
            ->where('wallet_type', WalletType::WALLET_MO->value)
            ->where('operation', WalletOperation::CREDIT->value)
            ->where('metadata->type', 'escrow_mo_jalon')
            ->exists();
    }

    /**
     * Consigne au séquestre MO le paiement confirmé d'un jalon hybride.
     * Idempotent : le webhook opérateur, son rejeu et l'interrogation de statut
     * par l'app peuvent tous confirmer le même paiement — un seul crédit en
     * résulte (verrou sur le jalon puis vérification dans le ledger).
     *
     * `$regularisation` (commande prosartisan:reconcile-hybrid-jalons) accepte
     * aussi un jalon déjà validé ou libéré : son paiement a bien été encaissé
     * mais jamais consigné, et sa libération a puisé dans le séquestre d'autres
     * missions — le financement a posteriori reconstitue ce séquestre.
     *
     * @return bool vrai si ce paiement vient de financer le jalon
     */
    public function fundHybridJalon(Jalon $jalon, Transaction $payment, bool $regularisation = false): bool
    {
        return DB::transaction(function () use ($jalon, $payment, $regularisation) {
            $jalon = Jalon::lockForUpdate()->find($jalon->id);

            $fundableStatus = $regularisation || in_array($jalon?->statut, ['en_attente', 'soumis'], true);

            if (! $jalon || ! $fundableStatus || $this->isJalonFunded($jalon)) {
                return false;
            }

            $this->credit(
                $jalon->mission->artisan,
                WalletType::WALLET_MO,
                $jalon->montant,
                ($regularisation ? 'Régularisation — ' : '')."Financement jalon #{$jalon->ordre} - Mission #{$jalon->mission_id}",
                array_filter([
                    'mission_id' => $jalon->mission_id,
                    'jalon_id' => $jalon->id,
                    'transaction_id' => $payment->id,
                    'type' => 'escrow_mo_jalon',
                    'regularisation' => $regularisation ?: null,
                ], fn ($value) => $value !== null)
            );

            Log::info("[Jalon financé] Jalon #{$jalon->id} financé pour la mission #{$jalon->mission_id}");

            return true;
        });
    }

    /**
     * Mode hybride : la main d'œuvre est consignée jalon par jalon dans le
     * `wallet_mo` de l'artisan, commun à toutes ses missions. Libérer un jalon
     * non couvert débiterait donc le séquestre d'une AUTRE mission. Le jalon
     * n'est libérable que si le séquestre MO de SA mission le couvre, une fois
     * réservé l'argent des autres jalons payés individuellement et non libérés
     * (les jalons d'avenant, financés globalement par applyAvenantEscrow,
     * restent libérables sur ce même principe).
     */
    private function assertHybridJalonCovered(Jalon $jalon, Mission $mission): void
    {
        if ($mission->payment_type !== 'hybrid') {
            return;
        }

        $reservedForOtherJalons = $mission->jalons()
            ->whereKeyNot($jalon->id)
            ->whereIn('statut', ['en_attente', 'soumis', 'valide'])
            ->get()
            ->filter(fn (Jalon $other) => $this->isJalonFunded($other))
            ->sum('montant');

        $available = $this->getMissionEscrowBalance($mission, WalletType::WALLET_MO) - $reservedForOtherJalons;

        if ($available < $jalon->montant) {
            throw new \InvalidArgumentException(
                "Ce jalon n'a pas encore été financé par le client : libération impossible."
            );
        }
    }

    /**
     * Libère le montant d'un jalon vers l'artisan.
     */
    public function releaseJalon(Jalon $jalon): void
    {
        $mission = $jalon->mission;
        $artisan = $mission->artisan;

        if ($mission->isFundsFrozen()) {
            throw new \InvalidArgumentException('Les fonds de cette mission sont gelés suite à un litige.');
        }

        $this->assertHybridJalonCovered($jalon, $mission);

        // Garde-fou anti-contournement (Règle d'or 5) : au-delà du seuil, aucun
        // chemin de libération — y compris le Force-Pass 72h — ne doit pouvoir
        // payer l'artisan tant que le référent n'a pas levé le blocage. Les
        // appelants légitimes (validateOtp, acceptProofs) ne touchent jamais
        // cette méthode sans avoir d'abord vérifié ce même seuil ; ReferentController
        // lève le flag avant d'appeler releaseJalon().
        $seuil = config('prosartisan.mission.referent_threshold', 2000000);
        if ($mission->montant_total > $seuil && $mission->referent_required) {
            throw new \InvalidArgumentException(
                'Cette mission dépasse le seuil référent : validation physique requise avant toute libération de fonds.'
            );
        }

        DB::transaction(function () use ($jalon, $mission, $artisan) {
            $provider = $this->resolveMissionProvider($mission, $artisan);

            $jalon->update([
                'statut' => 'paye',
                'paye_at' => now(),
            ]);

            // Calcul de la commission MO (TTC -> HT)
            $devis = Devis::where('mission_id', $mission->id)->where('statut', 'accepte')->first();
            $commissionService = $devis && $devis->commission_service_ratio !== null
                ? (float) $devis->commission_service_ratio
                : Setting::getValueByKey('commission_service', 0.10);
            $commission = (int) round($jalon->montant * ($commissionService / (1 + $commissionService)));
            $gainNetArtisan = $jalon->montant - $commission;

            // Débit du wallet_mo de l'artisan du montant total TTC
            $this->debit(
                $artisan,
                WalletType::WALLET_MO,
                $jalon->montant,
                "Libération jalon #{$jalon->ordre} - Mission #{$mission->id}",
                [
                    'mission_id' => $mission->id,
                    'jalon_id' => $jalon->id,
                    'type' => 'liberation_jalon',
                ]
            );

            // Créditer le compte financier de prosartisan (l'admin) de la commission MO
            $this->creditPlatformFinancialAccount(
                $commission,
                "Commission plateforme jalon #{$jalon->ordre} - Mission #{$mission->id}",
                [
                    'mission_id' => $mission->id,
                    'jalon_id' => $jalon->id,
                ]
            );

            // Amortissement automatique éventuel du micro-crédit d'urgence actif
            $microCreditService = app(MicroCreditService::class);
            $creditDeduction = $microCreditService->repayFromJalon($jalon, $gainNetArtisan);
            $montantVerseArtisan = max(0, $gainNetArtisan - $creditDeduction);

            // Transaction externe vers Mobile Money de l'artisan (montant net après déduction crédit)
            if ($montantVerseArtisan > 0) {
                $transaction = Transaction::create([
                    'mission_id' => $mission->id,
                    'user_id' => $mission->artisan_id,
                    'type' => 'liberation_jalon',
                    'montant' => $montantVerseArtisan,
                    'wallet_source' => 'escrow_mission_'.$mission->id,
                    'wallet_dest' => 'artisan_mobile_money_'.$mission->artisan_id,
                    'provider' => $provider,
                    'statut' => 'en_attente',
                ]);

                // Virement réel du montant net vers Mobile Money
                $description = "Paiement jalon #{$jalon->ordre} mission #{$mission->id}".($creditDeduction > 0 ? " (amortissement crédit: {$creditDeduction} FCFA déduits)" : '');

                try {
                    $result = $this->transferToMobileMoney($provider, $artisan->payment_phone ?? $artisan->phone, $montantVerseArtisan, $description);
                    $transaction->update([
                        'reference_externe' => $result['id'] ?? $result['txnid'] ?? null,
                        'statut' => 'confirme',
                    ]);
                } catch (\Exception $e) {
                    Log::error('Erreur lors du virement automatique artisan', [
                        'jalon_id' => $jalon->id,
                        'artisan_id' => $artisan->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Chantier 9A : Écriture au Grand Livre en Partie Double pour la libération (BCEAO)
            try {
                app(\App\Services\DoubleEntryLedgerService::class)->recordMilestoneRelease(
                    $jalon,
                    $jalon->montant
                );
            } catch (\Throwable $e) {
                Log::warning('Échec enregistrement double entry ledger releaseJalon: '.$e->getMessage());
            }

            Log::info('Jalon libéré', [
                'jalon_id' => $jalon->id,
                'mission_id' => $mission->id,
                'montant' => $jalon->montant,
            ]);

            $remainingJalons = $mission->jalons()
                ->whereIn('statut', ['en_attente', 'soumis', 'valide'])
                ->count();
            if ($remainingJalons === 0) {
                if ($mission->status instanceof PendingApprovalState) {
                    $mission->status->transitionTo(CompletedState::class);
                } else {
                    $mission->update(['status' => CompletedState::class]);
                }
            } elseif ($mission->status instanceof FundedLockedState) {
                $mission->status->transitionTo(InProgressState::class);
            }
        });
    }

    /**
     * Rembourser le client suite à un litige.
     */
    public function refundClient(Mission $mission): void
    {
        $this->refundClientFromDispute(
            $mission,
            $this->getMissionEscrowBalance($mission, WalletType::WALLET_MATERIAUX),
            $this->getMissionEscrowBalance($mission, WalletType::WALLET_MO),
        );

        $mission->update([
            'status' => 'annulee',
            'funds_frozen' => false,
        ]);
    }

    /**
     * Payer l'artisan suite à un litige (débloquer les jalons restants).
     */
    public function payArtisan(Mission $mission): void
    {
        $this->releaseLaborEscrowToArtisan(
            $mission,
            $this->getMissionEscrowBalance($mission, WalletType::WALLET_MO),
            null,
            true
        );
        $this->releaseMaterialEscrowToArtisan(
            $mission,
            $this->getMissionEscrowBalance($mission, WalletType::WALLET_MATERIAUX),
        );

        $mission->update([
            'status' => 'completed',
            'funds_frozen' => false,
        ]);
    }

    public function getMissionEscrowBalance(Mission $mission, WalletType $walletType): int
    {
        $hasMissionTransactions = WalletTransaction::query()
            ->where('user_id', $mission->artisan_id)
            ->where('mission_id', $mission->id)
            ->where('wallet_type', $walletType->value)
            ->exists();

        // Repli historique (missions financées avant le ledger par mission) :
        // exclu pour la main d'œuvre d'une mission hybride, jamais consignée
        // sans écriture de ledger — il renverrait sinon le solde global de
        // l'artisan, c'est-à-dire le séquestre de ses AUTRES missions.
        $isHybridLabor = $walletType === WalletType::WALLET_MO && $mission->payment_type === 'hybrid';

        if (! $hasMissionTransactions && ! $isHybridLabor) {
            $userBalance = $mission->artisan?->{$walletType->columnName()} ?? 0;
            $missionAmount = $walletType === WalletType::WALLET_MATERIAUX
                ? (int) $mission->montant_materiaux
                : (int) $mission->montant_mo;

            return max(0, min((int) $userBalance, $missionAmount));
        }

        $credits = WalletTransaction::query()
            ->where('user_id', $mission->artisan_id)
            ->where('mission_id', $mission->id)
            ->where('wallet_type', $walletType->value)
            ->whereIn('operation', [WalletOperation::CREDIT->value, WalletOperation::DEBLOCAGE->value])
            ->sum('montant');

        $debits = WalletTransaction::query()
            ->where('user_id', $mission->artisan_id)
            ->where('mission_id', $mission->id)
            ->where('wallet_type', $walletType->value)
            ->whereIn('operation', [WalletOperation::DEBIT->value, WalletOperation::BLOCAGE->value])
            ->sum('montant');

        return max(0, (int) $credits - (int) $debits);
    }

    public function refundClientFromDispute(
        Mission $mission,
        int $refundMateriaux,
        int $refundMo,
        ?Litige $litige = null
    ): ?Transaction {
        $client = $mission->client;
        $artisan = $mission->artisan;
        $total = $refundMateriaux + $refundMo;

        return DB::transaction(function () use ($mission, $client, $artisan, $refundMateriaux, $refundMo, $total, $litige) {
            if ($refundMateriaux > 0) {
                $this->debit(
                    $artisan,
                    WalletType::WALLET_MATERIAUX,
                    $refundMateriaux,
                    "Remboursement litige mission #{$mission->id}",
                    [
                        'mission_id' => $mission->id,
                        'litige_id' => $litige?->id,
                        'type' => 'litige_refund_materiaux',
                    ]
                );
            }

            if ($refundMo > 0) {
                $this->debit(
                    $artisan,
                    WalletType::WALLET_MO,
                    $refundMo,
                    "Remboursement litige mission #{$mission->id}",
                    [
                        'mission_id' => $mission->id,
                        'litige_id' => $litige?->id,
                        'type' => 'litige_refund_mo',
                    ]
                );
            }

            if ($total <= 0) {
                return null;
            }

            $provider = $this->resolveMissionProvider($mission, $client);
            $transaction = Transaction::create([
                'mission_id' => $mission->id,
                'user_id' => $client->id,
                'type' => 'remboursement',
                'montant' => $total,
                'wallet_source' => 'escrow_mission_'.$mission->id,
                'wallet_dest' => 'client_mobile_money_'.$client->id,
                'provider' => $provider,
                'statut' => 'en_attente',
                'metadata' => [
                    'litige_id' => $litige?->id,
                    'refund_materiaux' => $refundMateriaux,
                    'refund_mo' => $refundMo,
                ],
            ]);

            try {
                $result = $this->transferToMobileMoney($provider, $client->payment_phone ?? $client->phone, $total, "Remboursement mission #{$mission->id}");
                $transaction->update([
                    'reference_externe' => $result['id'] ?? $result['txnid'] ?? null,
                    'statut' => 'confirme',
                ]);
            } catch (\Exception $e) {
                Log::error('Erreur lors du remboursement automatique client', [
                    'mission_id' => $mission->id,
                    'client_id' => $client->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $transaction->fresh();
        });
    }

    public function releaseLaborEscrowToArtisan(
        Mission $mission,
        int $amount,
        ?Litige $litige = null,
        bool $force = false
    ): ?Transaction {
        if ($amount <= 0) {
            return null;
        }

        if ($mission->isFundsFrozen() && ! $force) {
            throw new \InvalidArgumentException('Les fonds de cette mission sont gelés suite à un litige.');
        }

        $artisan = $mission->artisan;
        $provider = $this->resolveMissionProvider($mission, $artisan);

        return DB::transaction(function () use ($mission, $artisan, $amount, $litige, $provider) {
            $this->debit(
                $artisan,
                WalletType::WALLET_MO,
                $amount,
                "Paiement force suite au litige mission #{$mission->id}",
                [
                    'mission_id' => $mission->id,
                    'litige_id' => $litige?->id,
                    'type' => 'litige_release_mo',
                ]
            );

            $transaction = Transaction::create([
                'mission_id' => $mission->id,
                'user_id' => $artisan->id,
                'type' => 'liberation_jalon',
                'montant' => $amount,
                'wallet_source' => 'escrow_mission_'.$mission->id,
                'wallet_dest' => 'artisan_mobile_money_'.$artisan->id,
                'provider' => $provider,
                'statut' => 'en_attente',
                'metadata' => [
                    'litige_id' => $litige?->id,
                    'forced_release' => true,
                ],
            ]);

            try {
                $result = $this->transferToMobileMoney($provider, $artisan->payment_phone ?? $artisan->phone, $amount, "Reglement litige mission #{$mission->id}");
                $transaction->update([
                    'reference_externe' => $result['id'] ?? $result['txnid'] ?? null,
                    'statut' => 'confirme',
                ]);
            } catch (\Exception $e) {
                Log::error('Erreur lors du paiement force artisan', [
                    'mission_id' => $mission->id,
                    'artisan_id' => $artisan->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $mission->jalons()
                ->whereIn('statut', ['en_attente', 'soumis', 'valide'])
                ->update([
                    'statut' => 'paye',
                    'paye_at' => now(),
                ]);

            return $transaction->fresh();
        });
    }

    public function releaseMaterialEscrowToArtisan(
        Mission $mission,
        int $amount,
        ?Litige $litige = null
    ): ?Transaction {
        if ($amount <= 0) {
            return null;
        }

        $artisan = $mission->artisan;
        $provider = $this->resolveMissionProvider($mission, $artisan);

        return DB::transaction(function () use ($mission, $artisan, $amount, $litige, $provider) {
            $this->debit(
                $artisan,
                WalletType::WALLET_MATERIAUX,
                $amount,
                "Liberation materiaux suite au litige mission #{$mission->id}",
                [
                    'mission_id' => $mission->id,
                    'litige_id' => $litige?->id,
                    'type' => 'litige_release_materiaux',
                ]
            );

            $transaction = Transaction::create([
                'mission_id' => $mission->id,
                'user_id' => $artisan->id,
                'type' => 'credit',
                'montant' => $amount,
                'wallet_source' => 'escrow_mission_'.$mission->id,
                'wallet_dest' => 'artisan_mobile_money_'.$artisan->id,
                'provider' => $provider,
                'statut' => 'en_attente',
                'metadata' => [
                    'litige_id' => $litige?->id,
                    'wallet_type' => WalletType::WALLET_MATERIAUX->value,
                ],
            ]);

            try {
                $result = $this->transferToMobileMoney($provider, $artisan->payment_phone ?? $artisan->phone, $amount, "Liberation materiaux mission #{$mission->id}");
                $transaction->update([
                    'reference_externe' => $result['id'] ?? $result['txnid'] ?? null,
                    'statut' => 'confirme',
                ]);
            } catch (\Exception $e) {
                Log::error('Erreur lors de la liberation materiaux artisan', [
                    'mission_id' => $mission->id,
                    'artisan_id' => $artisan->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $transaction->fresh();
        });
    }

    /**
     * Obtenir le solde d'un wallet
     */
    public function getBalance(User $user, WalletType $walletType): int
    {
        $columnName = $walletType->columnName();

        return $user->{$columnName} ?? 0;
    }

    /**
     * Obtenir tous les soldes de wallets d'un utilisateur
     */
    public function getAllBalances(User $user): array
    {
        return [
            'wallet_materiaux' => $user->wallet_materiaux ?? 0,
            'wallet_mo' => $user->wallet_mo ?? 0,
            'total' => ($user->wallet_materiaux ?? 0) + ($user->wallet_mo ?? 0),
        ];
    }

    private function resolveMissionProvider(Mission $mission, User $recipient): string
    {
        $provider = $mission->transactions()
            ->orderByDesc('id')
            ->value('provider');

        if (is_string($provider) && $provider !== '') {
            return $provider;
        }

        return $recipient->preferred_payment_provider ?? 'wave';
    }

    private function transferToMobileMoney(string $provider, string $phone, int $montant, string $description): array
    {
        if ($provider === 'orange_money') {
            return $this->orangeMoneyService->transferToMobileMoney($phone, $montant, $description);
        }

        return $this->waveService->transferToMobileMoney($phone, $montant, $description);
    }

    /**
     * Crédite le compte financier de ProsArtisan (l'admin).
     */
    public function creditPlatformFinancialAccount(int $amount, string $description, array $metadata = []): void
    {
        if ($amount <= 0) {
            return;
        }

        $admin = User::where('role', 'admin')->first();
        if (! $admin) {
            Log::warning('Impossible de créditer le compte financier ProsArtisan: aucun administrateur trouvé.');

            return;
        }

        $this->credit(
            $admin,
            WalletType::WALLET_MO,
            $amount,
            $description,
            array_merge($metadata, ['type' => 'platform_commission'])
        );
    }
}
