<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case EN_ATTENTE = 'en_attente';
    case CONFIRME = 'confirme';
    case ECHOUE = 'echoue';

    /**
     * Obtenir le label français du statut
     */
    public function label(): string
    {
        return match($this) {
            self::EN_ATTENTE => 'En attente',
            self::CONFIRME => 'Confirmé',
            self::ECHOUE => 'Échoué',
        };
    }

    /**
     * Vérifie si le paiement est finalisé (succès ou échec)
     */
    public function isFinalized(): bool
    {
        return in_array($this, [self::CONFIRME, self::ECHOUE]);
    }

    /**
     * Vérifie si le paiement est réussi
     */
    public function isSuccessful(): bool
    {
        return $this === self::CONFIRME;
    }
}
