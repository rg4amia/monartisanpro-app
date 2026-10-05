<?php

namespace App\Exceptions;

use Carbon\CarbonInterface;

/**
 * La validation d'un code de retrait ou de réception est suspendue après
 * trop de codes faux (Chantier 32).
 */
class OrderCodeLockedException extends \Exception
{
    public function __construct(public readonly CarbonInterface $lockedUntil)
    {
        $minutes = max(1, (int) ceil(now()->diffInSeconds($lockedUntil, false) / 60));

        parent::__construct('Trop de codes incorrects : la validation est suspendue pendant '.self::durationLabel($minutes).'.');
    }

    public static function durationLabel(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.' minute'.($minutes > 1 ? 's' : '');
        }

        $hours = (int) ceil($minutes / 60);

        return $hours.' heure'.($hours > 1 ? 's' : '');
    }
}
