<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/**
 * Valeur de colonne `position` portable entre les moteurs de test.
 *
 * Sous MariaDB / MySQL (production, job CI `tests-mariadb`), `position` est un
 * `POINT` — `NOT NULL` sur `fournisseurs_agrees` pour l'index spatial — qui
 * s'écrit `POINT(lng, lat)` sans SRID (MariaDB refuse le mutateur SRID à deux arguments).
 * Sous SQLite, les migrations créent une simple chaîne « lat,lng », comme
 * l'écrit FournisseurAgree::setPosition().
 *
 * Défaut : Abidjan Plateau.
 */
final class Geo
{
    public static function point(float $lat = 5.3599, float $lng = -4.0083): mixed
    {
        if (config('database.default') === 'sqlite') {
            return "$lat,$lng";
        }

        return DB::raw(sprintf('POINT(%F, %F)', $lng, $lat));
    }
}
