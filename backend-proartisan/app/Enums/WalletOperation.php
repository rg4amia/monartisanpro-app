<?php

namespace App\Enums;

enum WalletOperation: string
{
    case CREDIT = 'credit';
    case DEBIT = 'debit';
    case BLOCAGE = 'blocage';
    case DEBLOCAGE = 'deblocage';

    /**
     * Obtenir le label français de l'opération
     */
    public function label(): string
    {
        return match($this) {
            self::CREDIT => 'Crédit',
            self::DEBIT => 'Débit',
            self::BLOCAGE => 'Blocage',
            self::DEBLOCAGE => 'Déblocage',
        };
    }

    /**
     * Vérifie si l'opération augmente le solde
     */
    public function increasesBalance(): bool
    {
        return in_array($this, [self::CREDIT, self::DEBLOCAGE]);
    }

    /**
     * Vérifie si l'opération diminue le solde
     */
    public function decreasesBalance(): bool
    {
        return in_array($this, [self::DEBIT, self::BLOCAGE]);
    }
}
