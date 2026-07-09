<?php

namespace App\Enums;

enum WalletOperation: string
{
    case CREDIT = 'credit';
    case DEBIT = 'debit';
    case BLOCAGE = 'blocage';
    case DEBLOCAGE = 'deblocage';
    case REVERSE_ENTRY = 'reverse_entry';   // Annulation comptable (jamais DELETE)
    case FEE_CUT = 'fee_cut';              // Commission prélevée par la plateforme

    /**
     * Obtenir le label français de l'opération
     */
    public function label(): string
    {
        return match($this) {
            self::CREDIT        => 'Crédit',
            self::DEBIT         => 'Débit',
            self::BLOCAGE       => 'Blocage',
            self::DEBLOCAGE     => 'Déblocage',
            self::REVERSE_ENTRY => 'Écriture d\'annulation',
            self::FEE_CUT       => 'Commission plateforme',
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
        return in_array($this, [self::DEBIT, self::BLOCAGE, self::FEE_CUT]);
    }
}
