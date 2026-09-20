<?php

namespace App\Exceptions;

/**
 * Erreur métier du parrainage artisan → apprenti, porteuse du code HTTP à
 * renvoyer (403/422) pour que le contrôleur reproduise exactement le format
 * de réponse JSON historique de ParrainageController (success/message).
 */
class ParrainageException extends \Exception
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
