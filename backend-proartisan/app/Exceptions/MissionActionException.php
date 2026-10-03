<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Refus métier d'une action sur une mission (acceptation, assignation,
 * validation finale, annulation…). Le message est destiné à l'utilisateur ;
 * `status` est le code HTTP à renvoyer.
 */
class MissionActionException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
