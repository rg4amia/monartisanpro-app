<?php

namespace App\Services;

use App\Enums\WalletOperation;
use App\Enums\WalletType;
use App\Models\Jalon;
use App\Models\Mission;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTransaction;
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
                'reference' => 'WTX-' . strtoupper(Str::random(12)),
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

            // Création de la transaction de wallet
            $walletTransaction = WalletTransaction::create([
                'user_id' => $user->id,
                'wallet_type' => $walletType->value,
                'operation' => WalletOperation::DEBIT,
                'montant' => $montant,
                'reference' => 'WTX-' . strtoupper(Str::random(12)),
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
        $montantMat = (int) round($montantTotal * $ratioMat);
        $montantMo  = $montantTotal - $montantMat;

        DB::transaction(function () use (
            $mission,
            $artisan,
            $montantTotal,
            $montantMat,
            $montantMo,
            $ratioMat,
            $paiementTransaction
        ) {
            // Mise à jour mission
            $mission->update([
                'montant_total'     => $montantTotal,
                'montant_materiaux' => $montantMat,
                'montant_mo'        => $montantMo,
                'ratio_materiaux'   => $ratioMat,
                'status'            => 'financee',
            ]);

            // Crédit du wallet_materiaux de l'artisan
            $this->credit(
                $artisan,
                WalletType::WALLET_MATERIAUX,
                $montantMat,
                "Séquestre matériaux - Mission #{$mission->id}",
                [
                    'mission_id' => $mission->id,
                    'transaction_id' => $paiementTransaction->id,
                    'type' => 'escrow_materiaux'
                ]
            );

            // Crédit du wallet_mo de l'artisan
            $this->credit(
                $artisan,
                WalletType::WALLET_MO,
                $montantMo,
                "Séquestre main d'œuvre - Mission #{$mission->id}",
                [
                    'mission_id' => $mission->id,
                    'transaction_id' => $paiementTransaction->id,
                    'type' => 'escrow_mo'
                ]
            );

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
     * Libère le montant d'un jalon vers l'artisan.
     */
    public function releaseJalon(Jalon $jalon): void
    {
        $mission = $jalon->mission;
        $artisan = $mission->artisan;

        DB::transaction(function () use ($jalon, $mission, $artisan) {
            $jalon->update([
                'statut'  => 'paye',
                'paye_at' => now(),
            ]);

            // Débit du wallet_mo
            $this->debit(
                $artisan,
                WalletType::WALLET_MO,
                $jalon->montant,
                "Libération jalon #{$jalon->ordre} - Mission #{$mission->id}",
                [
                    'mission_id' => $mission->id,
                    'jalon_id' => $jalon->id,
                    'type' => 'liberation_jalon'
                ]
            );

            // Transaction externe vers Mobile Money de l'artisan
            $transaction = Transaction::create([
                'mission_id'    => $mission->id,
                'user_id'       => $mission->artisan_id,
                'type'          => 'liberation_jalon',
                'montant'       => $jalon->montant,
                'wallet_source' => 'escrow_mission_' . $mission->id,
                'wallet_dest'   => 'artisan_mobile_money_' . $mission->artisan_id,
                'provider'      => 'wave', // Default provider
                'statut'        => 'en_attente',
            ]);

            // Virement réel vers Mobile Money
            $provider = $artisan->preferred_payment_provider ?? 'wave';
            $description = "Paiement jalon #{$jalon->ordre} mission #{$mission->id}";

            try {
                if ($provider === 'wave') {
                    $result = $this->waveService->transferToMobileMoney($artisan->phone, $jalon->montant, $description);
                    $transaction->update([
                        'reference_externe' => $result['id'] ?? null,
                        'statut' => 'confirme',
                    ]);
                } elseif ($provider === 'orange_money') {
                    $result = $this->orangeMoneyService->transferToMobileMoney($artisan->phone, $jalon->montant, $description);
                    $transaction->update([
                        'reference_externe' => $result['txnid'] ?? null,
                        'statut' => 'confirme',
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Erreur lors du virement automatique artisan', [
                    'jalon_id' => $jalon->id,
                    'artisan_id' => $artisan->id,
                    'error' => $e->getMessage(),
                ]);
            }

            Log::info('Jalon libéré', [
                'jalon_id' => $jalon->id,
                'mission_id' => $mission->id,
                'montant' => $jalon->montant,
            ]);
        });
    }

    /**
     * Rembourser le client suite à un litige.
     */
    public function refundClient(Mission $mission): void
    {
        $client = $mission->client;
        $artisan = $mission->artisan;

        DB::transaction(function () use ($mission, $client, $artisan) {
            // Récupérer ce qui reste dans les wallets de l'artisan pour cette mission
            if ($artisan->wallet_materiaux > 0) {
                $this->debit(
                    $artisan,
                    WalletType::WALLET_MATERIAUX,
                    min($artisan->wallet_materiaux, $mission->montant_materiaux),
                    "Remboursement client - Litige mission #{$mission->id}"
                );
            }

            if ($artisan->wallet_mo > 0) {
                $this->debit(
                    $artisan,
                    WalletType::WALLET_MO,
                    min($artisan->wallet_mo, $mission->montant_mo),
                    "Remboursement client - Litige mission #{$mission->id}"
                );
            }

            // Virement Mobile Money vers client
            $transaction = Transaction::create([
                'mission_id' => $mission->id,
                'user_id' => $client->id,
                'type' => 'remboursement',
                'montant' => $mission->montant_total,
                'wallet_source' => 'escrow_mission_' . $mission->id,
                'wallet_dest' => 'client_mobile_money_' . $client->id,
                'provider' => 'wave',
                'statut' => 'en_attente',
            ]);

            try {
                $result = $this->waveService->transferToMobileMoney($client->phone, $mission->montant_total, "Remboursement mission #{$mission->id}");
                $transaction->update([
                    'reference_externe' => $result['id'] ?? null,
                    'statut' => 'confirme',
                ]);
            } catch (\Exception $e) {
                Log::error('Erreur lors du remboursement automatique client', [
                    'mission_id' => $mission->id,
                    'client_id' => $client->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $mission->update(['status' => 'annulee']);
        });
    }

    /**
     * Payer l'artisan suite à un litige (débloquer les jalons restants).
     */
    public function payArtisan(Mission $mission): void
    {
        // Libérer tous les jalons en attente ou soumis
        $jalonsRestants = $mission->jalons()
            ->whereIn('statut', ['en_attente', 'soumis', 'valide'])
            ->get();

        foreach ($jalonsRestants as $jalon) {
            $this->releaseJalon($jalon);
        }

        $mission->update(['status' => 'terminee']);
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
}
