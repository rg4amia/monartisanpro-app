<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception levée lorsque le Circuit Breaker d'un opérateur Mobile Money est ouvert.
 * Retourne un HTTP 503 (Service Unavailable) par défaut.
 */
class CircuitOpenException extends HttpException
{
    public function __construct(string $message = 'Service de paiement temporairement indisponible.')
    {
        parent::__construct(503, $message);
    }
}
