<?php

namespace App\Support;

use App\Exceptions\MissionActionException;
use App\Exceptions\MissionTransitionException;
use App\Exceptions\OrderCodeLockedException;
use App\Exceptions\ParrainageClientException;
use App\Exceptions\ParrainageException;
use App\Exceptions\PaymentException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Message d'une erreur tel qu'il peut être montré à l'utilisateur.
 *
 * Les services signalent un refus métier par une `\Exception` nue ou par une
 * exception de l'application, au message rédigé en français (« Code de retrait
 * incorrect. »). Toute autre exception — requête SQL, erreur de type, service
 * extérieur — porte un message technique, souvent en anglais, qui ne doit pas
 * atteindre l'écran (Règle d'or 35) : elle est journalisée et remplacée.
 */
final class UserFacingError
{
    private const BUSINESS_EXCEPTIONS = [
        MissionActionException::class,
        MissionTransitionException::class,
        OrderCodeLockedException::class,
        ParrainageClientException::class,
        ParrainageException::class,
        PaymentException::class,
        ValidationException::class,
    ];

    public static function message(Throwable $e, string $fallback): string
    {
        if (self::isBusinessRefusal($e) && trim($e->getMessage()) !== '') {
            return $e->getMessage();
        }

        Log::error('Erreur technique masquée à l\'utilisateur', [
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'file' => $e->getFile().':'.$e->getLine(),
        ]);

        return $fallback;
    }

    public static function isBusinessRefusal(Throwable $e): bool
    {
        if ($e::class === \Exception::class || $e::class === \DomainException::class) {
            return true;
        }

        foreach (self::BUSINESS_EXCEPTIONS as $class) {
            if ($e instanceof $class) {
                return true;
            }
        }

        return false;
    }
}
