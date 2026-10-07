<?php

namespace App\Exceptions;

/**
 * Aucun canal (SMS, WhatsApp) n'a accepté l'envoi d'un code de vérification.
 */
class OtpDeliveryException extends \Exception
{
    public function __construct()
    {
        parent::__construct("Le code n'a pas pu être envoyé. Réessayez dans quelques instants ; si le problème persiste, contactez le support.");
    }
}
