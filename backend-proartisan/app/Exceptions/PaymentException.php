<?php

namespace App\Exceptions;

/**
 * Erreur métier d'initiation de paiement, porteuse du code HTTP à renvoyer
 * (400/403/422) pour que PaymentController reproduise exactement le format
 * de réponse JSON historique (success/message).
 */
class PaymentException extends \Exception
{
    public function __construct(string $message, private readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}
