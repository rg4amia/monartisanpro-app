<?php

namespace App\Services;

use App\Enums\WalletOperation;
use App\Enums\WalletType;
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
