<?php

namespace App\Enums;

enum PaymentProvider: string
{
    case WAVE = 'wave';
    case ORANGE_MONEY = 'orange_money';
    case VIREMENT_BANCAIRE = 'virement_bancaire';

    /**
     * Obtenir le label français du provider
     */
    public function label(): string
    {
        return match($this) {
            self::WAVE => 'Wave CI',
            self::ORANGE_MONEY => 'Orange Money CI',
            self::VIREMENT_BANCAIRE => 'Virement Bancaire',
        };
    }

    /**
     * Vérifie si c'est un paiement mobile money
     */
    public function isMobileMoney(): bool
    {
        return in_array($this, [self::WAVE, self::ORANGE_MONEY]);
    }

    /**
     * Retourne l'URL de base de l'API
     */
    public function apiBaseUrl(): ?string
    {
        return match($this) {
            self::WAVE => config('services.wave.api_url'),
            self::ORANGE_MONEY => config('services.orange_money.api_url'),
            self::VIREMENT_BANCAIRE => null,
        };
    }
}
