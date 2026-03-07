<?php

namespace App\Enums;

enum WalletType: string
{
    case WALLET_MATERIAUX = 'wallet_materiaux';
    case WALLET_MO = 'wallet_mo';

    /**
     * Obtenir le label français du type de wallet
     */
    public function label(): string
    {
        return match($this) {
            self::WALLET_MATERIAUX => 'Wallet Matériaux',
            self::WALLET_MO => 'Wallet Main d\'Suvre',
        };
    }

    /**
     * Obtenir le nom de la colonne dans la table users
     */
    public function columnName(): string
    {
        return $this->value;
    }
}
