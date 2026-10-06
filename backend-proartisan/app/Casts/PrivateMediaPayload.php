<?php

namespace App\Casts;

use App\Support\PrivateMedia;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Colonne JSON écrite par le serveur seul (charge d'un événement temps réel),
 * qui peut contenir l'adresse d'un fichier privé à n'importe quelle
 * profondeur.
 *
 * Écriture : chaque adresse privée est ramenée à sa forme canonique.
 * Lecture : chacune reçoit un lien signé neuf — celui posé à l'envoi aurait
 * expiré pour un événement relu plus tard. À ne pas poser sur une colonne
 * alimentée par l'utilisateur : rien n'y vérifie la signature reçue.
 *
 * @implements CastsAttributes<array<int|string, mixed>|null, array<int|string, mixed>|null>
 */
class PrivateMediaPayload implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) ? $this->walk($decoded, fn (string $path) => PrivateMedia::signedUrl($path)) : null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($this->walk((array) $value, fn (string $path) => PrivateMedia::canonicalUrl($path)));
    }

    /**
     * @param  array<int|string, mixed>  $data
     * @param  callable(string): string  $rewrite
     * @return array<int|string, mixed>
     */
    private function walk(array $data, callable $rewrite): array
    {
        foreach ($data as $index => $item) {
            if (is_array($item)) {
                $data[$index] = $this->walk($item, $rewrite);

                continue;
            }

            $path = PrivateMedia::pathFromUrl($item);
            if ($path !== null) {
                $data[$index] = $rewrite($path);
            }
        }

        return $data;
    }
}
