<?php

namespace App\Exceptions;

/**
 * Erreur métier du parrainage client → client, porteuse du code HTTP à
 * renvoyer (403/404/422) pour que le contrôleur reproduise exactement le
 * format de réponse JSON de ParrainageController (success/message).
 */
class ParrainageClientException extends \Exception
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
