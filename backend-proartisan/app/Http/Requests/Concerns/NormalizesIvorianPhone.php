<?php

namespace App\Http\Requests\Concerns;

/**
 * Normalisation d'un numéro ivoirien vers le format `+225XXXXXXXXXX`.
 *
 * Logique reprise à l'identique de `SendOtpRequest`/`VerifyOtpRequest`
 * (`prepareForValidation()`), désormais partagée pour éviter la duplication
 * et garantir que toute donnée stockée en attente (parrainage) est au même
 * format exact que `users.phone`, condition nécessaire à la liaison
 * automatique lors de l'inscription.
 */
trait NormalizesIvorianPhone
{
    protected function normalizeIvorianPhone(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $phone = preg_replace('/\s+/', '', $raw);

        if (str_starts_with($phone, '00225')) {
            $phone = '+'.substr($phone, 2);
        } elseif (str_starts_with($phone, '225')) {
            $phone = '+'.$phone;
        } elseif (preg_match('/^[0-9]{10}$/', $phone)) {
            $phone = '+225'.$phone;
        }

        return $phone;
    }
}
