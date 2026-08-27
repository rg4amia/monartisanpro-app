<?php

namespace App\Enums;

enum WalletType: string
{
    case WALLET_MATERIAUX = 'wallet_materiaux';
    case WALLET_MO = 'wallet_mo';

    /**
     * Obtenir le label francais du type de wallet
     */
    public function label(): string
    {
        return match($this) {
            self::WALLET_MATERIAUX => 'Wallet Materiaux',
            self::WALLET_MO => 'Wallet Main d\'Oeuvre',
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
