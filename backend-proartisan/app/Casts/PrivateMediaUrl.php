<?php

namespace App\Casts;

use App\Support\PrivateMedia;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Colonne portant l'adresse d'un fichier privé.
 *
 * Lecture : lien signé à durée limitée. Écriture : adresse canonique, acceptée
 * seulement si le lien reçu porte une signature encore valide (ou s'il est
 * déjà celui enregistré). Une ancienne adresse publique passe inchangée.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class PrivateMediaUrl implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return PrivateMedia::toReadable($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return PrivateMedia::toStored($value, $attributes[$key] ?? null);
    }
}
