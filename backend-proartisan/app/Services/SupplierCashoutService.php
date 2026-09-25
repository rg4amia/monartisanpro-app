<?php

namespace App\Services;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\WalletType;
use App\Models\GeneratedDocument;
use App\Models\Setting;
use App\Models\SupplierCashout;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Admin\AdminActivityLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SupplierCashoutService
{
    public function __construct(
        private WalletService $walletService,
        private GeneratedDocumentService $documentService,
        private AdminActivityLogger $audit
    ) {}

    /**
     * Calcule le solde disponible pour un cash-out quincaillerie.
     * Règle 23 : Event Sourcing Pur adossé au grand livre wallet_transactions.
     */
    public function getAvailableBalance(User $supplier): int
    {
        // 1. Solde actuel du wallet_materiaux recalculé dynamiquement
        $walletMateriaux = (int) $supplier->getWalletBalance(WalletType::WALLET_MATERIAUX);

        // 2. Montant total des demandes de cashout en cours (en attente ou approuvées mais non encore complétées ni rejetées)
        $pendingCashouts = (int) SupplierCashout::where('supplier_id', $supplier->id)
            ->whereIn('statut', [SupplierCashout::STATUT_EN_ATTENTE, SupplierCashout::STATUT_APPROUVE])
            ->sum('montant_brut');

        return max(0, $walletMateriaux - $pendingCashouts);
    }

    /**
     * Fournit les statistiques complètes de trésorerie pour la quincaillerie.
     */
    public function getSupplierCashoutStats(User $supplier): array
    {
        $walletMateriaux = (int) $supplier->getWalletBalance(WalletType::WALLET_MATERIAUX);
        $available = $this->getAvailableBalance($supplier);

        $pending = (int) SupplierCashout::where('supplier_id', $supplier->id)
            ->whereIn('statut', [SupplierCashout::STATUT_EN_ATTENTE, SupplierCashout::STATUT_APPROUVE])
            ->sum('montant_net');

        $totalCompleted = (int) SupplierCashout::where('supplier_id', $supplier->id)
            ->where('statut', SupplierCashout::STATUT_COMPLETE)
            ->sum('montant_net');

        $countTotal = SupplierCashout::where('supplier_id', $supplier->id)->count();

        return [
            'wallet_materiaux' => $walletMateriaux,
            'available_balance' => $available,
            'pending_amount' => $pending,
            'total_withdrawn' => $totalCompleted,
            'total_requests' => $countTotal,
        ];
    }

    /**
     * Enregistre une demande de cashout initiée par une quincaillerie.
     */
    public function requestCashout(User $supplier, array $data): SupplierCashout
    {
        if ($supplier->role !== 'fournisseur') {
            throw new \InvalidArgumentException('Seules les quincailleries agréées peuvent demander un cash-out.');
        }

        if ($supplier->kyc_status !== 'actif') {
            throw new \InvalidArgumentException('Votre compte doit être KYC validé pour effectuer un retrait.');
        }

        $montantBrut = (int) ($data['montant_brut'] ?? 0);
        if ($montantBrut < 1000) {
            throw new \InvalidArgumentException('Le montant minimum de retrait est de 1 000 FCFA.');
        }

        $available = $this->getAvailableBalance($supplier);
        if ($montantBrut > $available) {
            throw new \InvalidArgumentException("Solde disponible insuffisant ({$available} FCFA disponible, {$montantBrut} FCFA demandé).");
        }

        $modeRetrait = $data['mode_retrait'] ?? SupplierCashout::MODE_WAVE;
        $allowedModes = [
            SupplierCashout::MODE_ESPECES_GUICHET,
            SupplierCashout::MODE_WAVE,
            SupplierCashout::MODE_ORANGE_MONEY,
            SupplierCashout::MODE_VIREMENT_BANCAIRE,
        ];

        if (! in_array($modeRetrait, $allowedModes, true)) {
            throw new \InvalidArgumentException('Mode de retrait non supporté.');
        }

        $rate = (float) Setting::getValueByKey('commission_cashout_quincaillerie', 0.025);
        $commission = (int) round($montantBrut * $rate);
        $net = max(0, $montantBrut - $commission);

        return DB::transaction(function () use ($supplier, $data, $montantBrut, $rate, $commission, $net, $modeRetrait) {
            $cashout = SupplierCashout::create([
                'reference' => SupplierCashout::generateReference(),
                'supplier_id' => $supplier->id,
                'beneficiary_name' => $data['beneficiary_name'] ?? $supplier->name,
                'beneficiary_phone' => $data['beneficiary_phone'] ?? $supplier->phone,
                'bank_name' => $data['bank_name'] ?? null,
                'bank_account_number' => $data['bank_account_number'] ?? null,
                'montant_brut' => $montantBrut,
                'commission_rate' => $rate,
                'montant_commission' => $commission,
                'montant_net' => $net,
                'statut' => SupplierCashout::STATUT_EN_ATTENTE,
                'mode_retrait' => $modeRetrait,
                'notes' => $data['notes'] ?? null,
                'processed_by' => null,
            ]);

            if ($this->audit) {
                $this->audit->log('cashout.requested', $cashout, [
                    'supplier_id' => $supplier->id,
                    'montant_brut' => $montantBrut,
                    'mode_retrait' => $modeRetrait,
                ], $cashout->reference, $supplier);
            }

            Log::info("Nouvelle demande de cash-out quincaillerie {$cashout->reference}", [
                'supplier_id' => $supplier->id,
                'montant_brut' => $montantBrut,
                'montant_net' => $net,
            ]);

            return $cashout;
        });
    }

    /**
     * Approuve une demande de retrait.
     */
    public function approveCashout(SupplierCashout $cashout, ?User $admin = null): SupplierCashout
    {
        if ($cashout->statut !== SupplierCashout::STATUT_EN_ATTENTE) {
            throw new \InvalidArgumentException('Seule une demande en attente peut être approuvée.');
        }

        $cashout->update([
            'statut' => SupplierCashout::STATUT_APPROUVE,
            'processed_by' => $admin?->id,
            'processed_at' => now(),
        ]);

        if ($this->audit) {
            $this->audit->log('cashout.approved', $cashout, [], $cashout->reference, $admin);
        }

        return $cashout;
    }

    /**
     * Complète un cash-out (débit réel du wallet_materiaux et tracement financier).
     */
    public function completeCashout(
        SupplierCashout $cashout,
        ?User $admin = null,
        ?string $externalReference = null
    ): SupplierCashout {
        if (! in_array($cashout->statut, [SupplierCashout::STATUT_EN_ATTENTE, SupplierCashout::STATUT_APPROUVE], true)) {
            throw new \InvalidArgumentException('Cette demande de cash-out ne peut pas être complétée.');
        }

        return DB::transaction(function () use ($cashout, $admin, $externalReference) {
            $supplier = $cashout->supplier;

            // 1. Débiter le wallet_materiaux du fournisseur du montant brut si solde provisionné
            $currentBalance = (int) $supplier->getWalletBalance(WalletType::WALLET_MATERIAUX);
            if ($currentBalance >= $cashout->montant_brut) {
                $this->walletService->debit(
                    $supplier,
                    WalletType::WALLET_MATERIAUX,
                    $cashout->montant_brut,
                    "Décaissement cash-out quincaillerie {$cashout->reference}",
                    [
                        'cashout_id' => $cashout->id,
                        'reference' => $cashout->reference,
                        'type' => 'supplier_cashout',
                        'mode_retrait' => $cashout->mode_retrait,
                    ]
                );
            } else {
                Log::warning("Cashout {$cashout->reference} complété avec solde wallet non provisionné ({$currentBalance} FCFA)", [
                    'supplier_id' => $supplier->id,
                    'montant_brut' => $cashout->montant_brut,
                ]);
            }

            // 2. Créditer les commissions plateforme si applicables
            if ($cashout->montant_commission > 0) {
                $this->walletService->creditPlatformFinancialAccount(
                    $cashout->montant_commission,
                    "Commission plateforme cash-out {$cashout->reference}",
                    [
                        'cashout_id' => $cashout->id,
                        'reference' => $cashout->reference,
                    ]
                );
            }

            // 3. Déterminer le provider financier officiel
            $provider = match ($cashout->mode_retrait) {
                SupplierCashout::MODE_WAVE => 'wave',
                SupplierCashout::MODE_ORANGE_MONEY => 'orange_money',
                SupplierCashout::MODE_VIREMENT_BANCAIRE => 'virement_bancaire',
                default => 'virement_bancaire',
            };

            $refExterne = $externalReference ?? $cashout->reference;

            // 4. Enregistrer la Transaction financière officielle
            Transaction::create([
                'user_id' => $supplier->id,
                'type' => 'paiement_fournisseur',
                'montant' => $cashout->montant_net,
                'wallet_source' => 'platform_treasury',
                'wallet_dest' => 'supplier_'.$provider.'_'.$supplier->id,
                'provider' => $provider,
                'statut' => 'confirme',
                'reference_externe' => $refExterne,
                'metadata' => [
                    'cashout_id' => $cashout->id,
                    'reference' => $cashout->reference,
                    'montant_brut' => $cashout->montant_brut,
                    'montant_commission' => $cashout->montant_commission,
                    'montant_net' => $cashout->montant_net,
                    'mode_retrait' => $cashout->mode_retrait,
                    'beneficiary_name' => $cashout->beneficiary_name,
                    'beneficiary_phone' => $cashout->beneficiary_phone,
                    'bank_name' => $cashout->bank_name,
                    'bank_account_number' => $cashout->bank_account_number,
                ],
            ]);

            // 5. Mettre à jour le statut du cashout
            $cashout->update([
                'statut' => SupplierCashout::STATUT_COMPLETE,
                'processed_by' => $admin?->id ?? $cashout->processed_by,
                'processed_at' => now(),
            ]);

            // 6. Générer le document PDF certifié
            $this->documentService->createOrGetForCashout($cashout);

            if ($this->audit) {
                $this->audit->log('cashout.completed', $cashout, [
                    'montant_net' => $cashout->montant_net,
                    'external_reference' => $refExterne,
                ], $cashout->reference, $admin);
            }

            Log::info("Cash-out quincaillerie {$cashout->reference} complété avec succès.", [
                'cashout_id' => $cashout->id,
                'montant_net' => $cashout->montant_net,
            ]);

            return $cashout;
        });
    }

    /**
     * Rejette une demande de retrait.
     */
    public function rejectCashout(
        SupplierCashout $cashout,
        ?User $admin = null,
        string $reason = 'Demande non conforme'
    ): SupplierCashout {
        if ($cashout->statut === SupplierCashout::STATUT_COMPLETE) {
            throw new \InvalidArgumentException('Une opération déjà complétée ne peut pas être rejetée.');
        }

        $notes = $cashout->notes ? "{$cashout->notes}\nRejet : {$reason}" : "Rejet : {$reason}";

        $cashout->update([
            'statut' => SupplierCashout::STATUT_REJETE,
            'notes' => $notes,
            'processed_by' => $admin?->id,
            'processed_at' => now(),
        ]);

        if ($this->audit) {
            $this->audit->log('cashout.rejected', $cashout, [
                'reason' => $reason,
            ], $cashout->reference, $admin);
        }

        return $cashout;
    }

    /**
     * Génère un lot de virement (batch) pour regrouper plusieurs cash-outs approuvés.
     */
    public function createBatch(array $cashoutIds, ?User $admin = null): array
    {
        $cashouts = SupplierCashout::whereIn('id', $cashoutIds)
            ->whereIn('statut', [SupplierCashout::STATUT_EN_ATTENTE, SupplierCashout::STATUT_APPROUVE])
            ->get();

        if ($cashouts->isEmpty()) {
            throw new \InvalidArgumentException('Aucune demande éligible trouvée pour la constitution du lot.');
        }

        $batchReference = 'BATCH-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));

        SupplierCashout::whereIn('id', $cashouts->pluck('id'))->update([
            'batch_reference' => $batchReference,
            'statut' => SupplierCashout::STATUT_APPROUVE,
            'processed_by' => $admin?->id,
            'processed_at' => now(),
        ]);

        $totalBrut = (int) $cashouts->sum('montant_brut');
        $totalCommission = (int) $cashouts->sum('montant_commission');
        $totalNet = (int) $cashouts->sum('montant_net');

        if ($this->audit) {
            $this->audit->log('cashout.batch_created', null, [
                'batch_reference' => $batchReference,
                'count' => $cashouts->count(),
                'total_net' => $totalNet,
            ], $batchReference, $admin);
        }

        return [
            'batch_reference' => $batchReference,
            'count' => $cashouts->count(),
            'total_brut' => $totalBrut,
            'total_commission' => $totalCommission,
            'total_net' => $totalNet,
            'cashouts' => $cashouts,
        ];
    }

    /**
     * Rapprochement bancaire groupé : marque tous les cashouts d'un lot comme complétés.
     */
    public function reconcileBatch(
        string $batchReference,
        ?User $admin = null,
        ?string $bankTxReference = null
    ): array {
        $cashouts = SupplierCashout::where('batch_reference', $batchReference)
            ->whereIn('statut', [SupplierCashout::STATUT_EN_ATTENTE, SupplierCashout::STATUT_APPROUVE])
            ->get();

        if ($cashouts->isEmpty()) {
            throw new \InvalidArgumentException("Aucun cash-out en attente dans le lot {$batchReference}.");
        }

        $completed = [];
        foreach ($cashouts as $cashout) {
            $extRef = $bankTxReference ? "{$bankTxReference}-{$cashout->id}" : "REC-{$batchReference}-{$cashout->id}";
            $completedCashout = $this->completeCashout($cashout, $admin, $extRef);
            $completedCashout->update([
                'reconciled_at' => now(),
                'reconciled_by' => $admin?->id,
            ]);
            $completed[] = $completedCashout;
        }

        if ($this->audit) {
            $this->audit->log('cashout.batch_reconciled', null, [
                'batch_reference' => $batchReference,
                'count' => count($completed),
                'bank_reference' => $bankTxReference,
            ], $batchReference, $admin);
        }

        return [
            'batch_reference' => $batchReference,
            'reconciled_count' => count($completed),
            'total_reconciled_net' => (int) collect($completed)->sum('montant_net'),
            'reconciled_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Traite les cashouts éligibles à J+1 garantis pour les quincailleries.
     */
    public function processAutomatedPayoutsJ1(): array
    {
        // Récupère les demandes de cash-out en attente depuis au moins 24h, pour quincailleries actives
        $cutoff = now()->subHours(24);
        $eligibleCashouts = SupplierCashout::where('statut', SupplierCashout::STATUT_EN_ATTENTE)
            ->where('created_at', '<=', $cutoff)
            ->whereHas('supplier', fn ($q) => $q->where('kyc_status', 'actif'))
            ->get();

        $processed = [];
        $failed = [];

        foreach ($eligibleCashouts as $cashout) {
            try {
                $this->approveCashout($cashout);
                $this->completeCashout($cashout, null, "AUTO-J1-{$cashout->reference}");
                $processed[] = $cashout->reference;
            } catch (\Exception $e) {
                Log::error("Erreur payout automatique J+1 sur {$cashout->reference}: {$e->getMessage()}");
                $failed[] = [
                    'reference' => $cashout->reference,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'total_eligible' => $eligibleCashouts->count(),
            'processed_count' => count($processed),
            'failed_count' => count($failed),
            'processed_references' => $processed,
            'failed' => $failed,
        ];
    }
}
