<?php

namespace App\Services;

use App\Models\DoubleEntryLedgerEntry;
use App\Models\Jalon;
use App\Models\Mission;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DoubleEntryLedgerService
{
    // Comptes comptables standardisés conformes BCEAO
    public const ACCOUNT_CLIENT_ESCROW = 'client_escrow';
    public const ACCOUNT_PLATFORM_ESCROW_MO = 'platform_escrow_mo';
    public const ACCOUNT_PLATFORM_ESCROW_MATERIALS = 'platform_escrow_materials';
    public const ACCOUNT_ARTISAN_CASHABLE = 'artisan_cashable_mo';
    public const ACCOUNT_SUPPLIER_PAYABLE = 'supplier_payable';
    public const ACCOUNT_PLATFORM_COMMISSION = 'platform_commission';
    public const ACCOUNT_CLIENT_REFUND = 'client_refund';

    /**
     * Enregistre l'indemnisation d'un juré (5 000 FCFA) prélevée sur les commissions ou charges de plateforme.
     */
    public function recordJurorCompensation(
        int $amount,
        \App\Models\User $juror,
        \App\Models\Litige $litige,
        \App\Models\JuryReview $review
    ): DoubleEntryLedgerEntry {
        return $this->recordDoubleEntry(
            self::ACCOUNT_PLATFORM_COMMISSION,
            self::ACCOUNT_ARTISAN_CASHABLE,
            $amount,
            'juror_compensation',
            [
                'mission_id' => $litige->mission_id,
                'user_id' => $juror->id,
                'description' => "Indemnité de juré pour le litige #{$litige->id} (JuryReview #{$review->id})",
                'metadata' => [
                    'litige_id' => $litige->id,
                    'jury_review_id' => $review->id,
                ],
            ]
        );
    }

    /**
     * Enregistre une écriture élémentaire en partie double garantie équilibrée.
     */
    public function recordDoubleEntry(
        string $accountSource,
        string $accountDestination,
        int $amount,
        string $entryType,
        array $context = [],
        ?string $transactionGroupId = null
    ): DoubleEntryLedgerEntry {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Le montant d'une écriture comptable doit être strictement positif ({$amount} FCFA reçu).");
        }

        if ($accountSource === $accountDestination) {
            throw new InvalidArgumentException("Le compte source et le compte destination doivent être distincts ({$accountSource}).");
        }

        $groupId = $transactionGroupId ?? (string) Str::uuid();

        return DB::transaction(function () use ($accountSource, $accountDestination, $amount, $entryType, $context, $groupId) {
            return DoubleEntryLedgerEntry::create([
                'transaction_group_id' => $groupId,
                'account_source' => $accountSource,
                'account_destination' => $accountDestination,
                'amount' => $amount,
                'currency' => 'XOF',
                'entry_type' => $entryType,
                'mission_id' => $context['mission_id'] ?? null,
                'jalon_id' => $context['jalon_id'] ?? null,
                'order_id' => $context['order_id'] ?? null,
                'user_id' => $context['user_id'] ?? null,
                'description' => $context['description'] ?? null,
                'metadata_json' => $context['metadata'] ?? null,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Enregistre le séquestre initial d'une mission (Main d'œuvre + Matériaux).
     */
    public function recordEscrowFunding(
        Mission $mission,
        int $montantMo,
        int $montantMateriaux,
        ?int $transactionId = null
    ): array {
        $groupId = (string) Str::uuid();
        $entries = [];

        DB::transaction(function () use ($mission, $montantMo, $montantMateriaux, $transactionId, $groupId, &$entries) {
            if ($montantMo > 0) {
                $entries[] = $this->recordDoubleEntry(
                    self::ACCOUNT_CLIENT_ESCROW,
                    self::ACCOUNT_PLATFORM_ESCROW_MO,
                    $montantMo,
                    'escrow_funding_mo',
                    [
                        'mission_id' => $mission->id,
                        'user_id' => $mission->client_id,
                        'description' => "Financement séquestre main d'œuvre mission #{$mission->id}",
                        'metadata' => ['transaction_id' => $transactionId],
                    ],
                    $groupId
                );
            }

            if ($montantMateriaux > 0) {
                $entries[] = $this->recordDoubleEntry(
                    self::ACCOUNT_CLIENT_ESCROW,
                    self::ACCOUNT_PLATFORM_ESCROW_MATERIALS,
                    $montantMateriaux,
                    'escrow_funding_materials',
                    [
                        'mission_id' => $mission->id,
                        'user_id' => $mission->client_id,
                        'description' => "Financement séquestre matériaux mission #{$mission->id}",
                        'metadata' => ['transaction_id' => $transactionId],
                    ],
                    $groupId
                );
            }
        });

        return $entries;
    }

    /**
     * Enregistre la libération d'un jalon vers l'artisan.
     */
    public function recordMilestoneRelease(Jalon $jalon, int $amount): DoubleEntryLedgerEntry
    {
        return $this->recordDoubleEntry(
            self::ACCOUNT_PLATFORM_ESCROW_MO,
            self::ACCOUNT_ARTISAN_CASHABLE,
            $amount,
            'milestone_release',
            [
                'mission_id' => $jalon->mission_id,
                'jalon_id' => $jalon->id,
                'user_id' => $jalon->mission->artisan_id,
                'description' => "Libération jalon #{$jalon->ordre} mission #{$jalon->mission_id}",
            ]
        );
    }

    /**
     * Enregistre un déboursement fournisseur pour les matériaux.
     */
    public function recordSupplierPayout(
        int $amount,
        int $supplierId,
        ?int $missionId = null,
        ?int $orderId = null,
        ?string $description = null
    ): DoubleEntryLedgerEntry {
        return $this->recordDoubleEntry(
            self::ACCOUNT_PLATFORM_ESCROW_MATERIALS,
            self::ACCOUNT_SUPPLIER_PAYABLE,
            $amount,
            'supplier_materials_payout',
            [
                'mission_id' => $missionId,
                'order_id' => $orderId,
                'user_id' => $supplierId,
                'description' => $description ?? "Déboursement fournisseur matériaux #{$supplierId}",
            ]
        );
    }

    /**
     * Enregistre un remboursement client consécutif à un litige ou annulation.
     */
    public function recordRefund(
        Mission $mission,
        int $amount,
        string $sourceAccount = self::ACCOUNT_PLATFORM_ESCROW_MO,
        ?string $reason = null
    ): DoubleEntryLedgerEntry {
        return $this->recordDoubleEntry(
            $sourceAccount,
            self::ACCOUNT_CLIENT_REFUND,
            $amount,
            'client_refund',
            [
                'mission_id' => $mission->id,
                'user_id' => $mission->client_id,
                'description' => $reason ?? "Remboursement client mission #{$mission->id}",
            ]
        );
    }

    /**
     * Calcule le solde net d'un compte comptable (Crédits reçus - Débits émis).
     */
    public function getAccountBalance(string $account, ?int $missionId = null): int
    {
        $creditsQuery = DoubleEntryLedgerEntry::where('account_destination', $account);
        $debitsQuery = DoubleEntryLedgerEntry::where('account_source', $account);

        if ($missionId !== null) {
            $creditsQuery->where('mission_id', $missionId);
            $debitsQuery->where('mission_id', $missionId);
        }

        $totalCredits = (int) $creditsQuery->sum('amount');
        $totalDebits = (int) $debitsQuery->sum('amount');

        return $totalCredits - $totalDebits;
    }

    /**
     * Audit complet de l'intégrité en partie double (Somme Débits == Somme Crédits).
     */
    public function verifyIntegrity(?int $missionId = null): array
    {
        if (! Schema::hasTable('double_entry_ledger_entries')) {
            return [
                'is_balanced' => true,
                'balanced' => true,
                'total_entries_count' => 0,
                'total_volume_fcfa' => 0,
                'total_volume_xof' => 0,
                'discrepancy_amount' => 0,
                'anomalies_count' => 0,
                'anomalies' => [],
                'timestamp' => now()->toIso8601String(),
            ];
        }

        $query = DoubleEntryLedgerEntry::query();
        if ($missionId !== null) {
            $query->where('mission_id', $missionId);
        }

        $totalEntries = $query->count();
        $totalVolume = (int) ($query->sum('amount') ?? 0);

        $anomalies = [];
        $discrepancyAmount = 0;

        // 1. Détection d'écritures invalides (montant <= 0 ou comptes source/destination identiques ou nuls)
        $invalidEntries = (clone $query)
            ->where(function ($q) {
                $q->where('amount', '<=', 0)
                    ->orWhereNull('amount')
                    ->orWhereColumn('account_source', 'account_destination')
                    ->orWhereNull('account_source')
                    ->orWhereNull('account_destination');
            })
            ->get();

        foreach ($invalidEntries as $invalid) {
            $amt = abs((int) ($invalid->amount ?? 0));
            $anomalies[] = [
                'type' => 'invalid_entry',
                'entry_id' => $invalid->id,
                'reason' => 'Écriture invalide ou comptes source/destination identiques',
                'amount' => $amt,
            ];
            $discrepancyAmount += $amt;
        }

        $isBalanced = count($anomalies) === 0 && $discrepancyAmount === 0;

        return [
            'is_balanced' => $isBalanced,
            'balanced' => $isBalanced,
            'total_entries_count' => $totalEntries,
            'total_volume_fcfa' => $totalVolume,
            'total_volume_xof' => $totalVolume,
            'discrepancy_amount' => $discrepancyAmount,
            'anomalies_count' => count($anomalies),
            'anomalies' => $anomalies,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
