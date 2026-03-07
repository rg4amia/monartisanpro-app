<?php

namespace App\Services;

use App\Enums\WalletOperation;
use App\Enums\WalletType;
use App\Models\Jalon;
use App\Models\JCode;
use App\Models\Mission;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletService
{
    /**
     * Créditer un wallet utilisateur
     *
     * @param User $user
     * @param WalletType $walletType
     * @param int $montant Montant en FCFA
     * @param string|null $description
     * @param array $metadata
     * @return WalletTransaction
     * @throws \Exception
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
            $soldeAvant = $user->{$columnName};
            $soldeApres = $soldeAvant + $montant;

            // Mise à jour du wallet
            $user->update([
                $columnName => $soldeApres
            ]);

            // Création de la transaction wallet
            $walletTransaction = WalletTransaction::create([
                'user_id' => $user->id,
                'wallet_type' => $walletType,
                'operation' => WalletOperation::CREDIT,
                'montant' => $montant,
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
     *
     * @param User $user
     * @param WalletType $walletType
     * @param int $montant Montant en FCFA
     * @param string|null $description
     * @param array $metadata
     * @return WalletTransaction
     * @throws \Exception
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
            $soldeAvant = $user->{$columnName};

            // Vérification du solde
            if ($soldeAvant < $montant) {
                throw new \Exception("Solde insuffisant dans le {$walletType->label()}. Solde: {$soldeAvant} FCFA, Requis: {$montant} FCFA");
            }

            $soldeApres = $soldeAvant - $montant;

            // Mise à jour du wallet
            $user->update([
                $columnName => $soldeApres
            ]);

            // Création de la transaction wallet
            $walletTransaction = WalletTransaction::create([
                'user_id' => $user->id,
                'wallet_type' => $walletType,
                'operation' => WalletOperation::DEBIT,
                'montant' => $montant,
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
     * Transférer des fonds d'un wallet à un autre (même utilisateur ou différent)
     *
     * @param User $sourceUser
     * @param WalletType $sourceWalletType
     * @param User $destUser
     * @param WalletType $destWalletType
     * @param int $montant
     * @param string|null $description
     * @param array $metadata
     * @return array ['debit' => WalletTransaction, 'credit' => WalletTransaction]
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
     * RÈGLE : ratio_materiaux fixé ici, immuable ensuite.
     *
     * @param Mission $mission
     * @param User $client
     * @param User $artisan
     * @param int $montantTotal
     * @param float $ratioMat
     * @param Transaction $paiementTransaction Transaction de paiement Wave/Orange Money confirmée
     * @return void
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

            // Crédit du wallet_mo de l'artisan (bloqué jusqu'à validation jalons)
            $this->credit(
                $artisan,
                WalletType::WALLET_MO,
                $montantMo,
                "Séquestre main d'Suvre - Mission #{$mission->id}",
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
     * Libère le montant d'un jalon vers l'artisan (main d'Suvre).
     *
     * @param Jalon $jalon
     * @return void
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

            // Débit du wallet_mo (libération du séquestre)
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
            Transaction::create([
                'mission_id'    => $mission->id,
                'user_id'       => $mission->artisan_id,
                'type'          => 'liberation_jalon',
                'montant'       => $jalon->montant,
                'wallet_source' => 'escrow_mission_' . $mission->id,
                'wallet_dest'   => 'artisan_mobile_money_' . $mission->artisan_id,
                'provider'      => 'wave', // TODO: choisir provider artisan
                'statut'        => 'en_attente',
            ]);

            Log::info('Jalon libéré', [
                'jalon_id' => $jalon->id,
                'mission_id' => $mission->id,
                'montant' => $jalon->montant,
            ]);
        });
    }

    /**
     * Paye le fournisseur lors de la validation d'un J-Code.
     *
     * @param JCode $jcode
     * @return void
     */
    public function payFournisseur(JCode $jcode): void
    {
        $mission = $jcode->mission;
        $artisan = $mission->artisan;
        $fournisseur = $jcode->fournisseur;

        DB::transaction(function () use ($jcode, $mission, $artisan, $fournisseur) {
            $jcode->update([
                'statut'     => 'utilise',
                'scanned_at' => now(),
            ]);

            // Débit du wallet_materiaux de l'artisan
            $this->debit(
                $artisan,
                WalletType::WALLET_MATERIAUX,
                $jcode->montant,
                "Paiement fournisseur via J-Code {$jcode->code}",
                [
                    'mission_id' => $mission->id,
                    'jcode_id' => $jcode->id,
                    'fournisseur_id' => $fournisseur->id,
                    'type' => 'paiement_fournisseur'
                ]
            );

            // Transaction virement bancaire J+1 vers fournisseur
            Transaction::create([
                'mission_id'    => $mission->id,
                'user_id'       => $jcode->fournisseur_id,
                'type'          => 'paiement_fournisseur',
                'montant'       => $jcode->montant,
                'wallet_source' => 'escrow_mission_' . $mission->id,
                'wallet_dest'   => 'fournisseur_' . $jcode->fournisseur_id,
                'provider'      => 'virement_bancaire',
                'statut'        => 'en_attente', // virement J+1
                'metadata'      => ['jcode' => $jcode->code],
            ]);

            Log::info('Fournisseur payé', [
                'jcode_id' => $jcode->id,
                'mission_id' => $mission->id,
                'fournisseur_id' => $fournisseur->id,
                'montant' => $jcode->montant,
            ]);
        });
    }

    /**
     * Obtenir le solde d'un wallet
     *
     * @param User $user
     * @param WalletType $walletType
     * @return int
     */
    public function getBalance(User $user, WalletType $walletType): int
    {
        $columnName = $walletType->columnName();
        return $user->{$columnName} ?? 0;
    }

    /**
     * Obtenir tous les soldes de wallets d'un utilisateur
     *
     * @param User $user
     * @return array
     */
    public function getAllBalances(User $user): array
    {
        return [
            'wallet_materiaux' => $user->wallet_materiaux ?? 0,
            'wallet_mo' => $user->wallet_mo ?? 0,
            'total' => ($user->wallet_materiaux ?? 0) + ($user->wallet_mo ?? 0),
        ];
    }

    /**
     * Obtenir l'historique des transactions d'un wallet
     *
     * @param User $user
     * @param WalletType|null $walletType
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTransactionHistory(
        User $user,
        ?WalletType $walletType = null,
        int $limit = 50
    ) {
        $query = WalletTransaction::where('user_id', $user->id);

        if ($walletType) {
            $query->where('wallet_type', $walletType);
        }

        return $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
