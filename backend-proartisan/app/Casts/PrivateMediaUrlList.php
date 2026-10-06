<?php

namespace App\Casts;

use App\Support\PrivateMedia;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Colonne JSON portant une liste de fichiers privés : soit des adresses
 * (photos d'une mission), soit des objets dont la clé `url` est une adresse
 * (photos d'une étape, avec position et date).
 *
 * Même règle que `PrivateMediaUrl`, appliquée à chaque adresse. Une adresse
 * privée refusée à l'écriture est retirée de la liste.
 *
 * @implements CastsAttributes<array<int, mixed>|null, array<int, mixed>|null>
 */
class PrivateMediaUrlList implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        $items = $this->decode($value);
        if ($items === null) {
            return null;
        }

        return array_map(function (mixed $item): mixed {
            if (is_array($item) && array_key_exists('url', $item)) {
                $item['url'] = PrivateMedia::toReadable($item['url']);

                return $item;
            }

            return PrivateMedia::toReadable($item);
        }, $items);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $items = is_string($value) ? ($this->decode($value) ?? []) : (array) $value;
        $known = $this->knownPaths($this->decode($attributes[$key] ?? null) ?? []);
        $stored = [];

        foreach ($items as $index => $item) {
            if (is_array($item) && array_key_exists('url', $item)) {
                $url = $this->storedUrl($item['url'], $known);
                if ($url === null && is_string($item['url']) && $item['url'] !== '') {
                    continue;
                }
                $item['url'] = $url;
                $stored[$index] = $item;

                continue;
            }

            if (is_string($item)) {
                $url = $this->storedUrl($item, $known);
                if ($url === null) {
                    continue;
                }
                $stored[$index] = $url;

                continue;
            }

            $stored[$index] = $item;
        }

        return json_encode(array_is_list($items) ? array_values($stored) : $stored);
    }

    /**
     * @param  array<string, true>  $known
     */
    private function storedUrl(mixed $url, array $known): ?string
    {
        $path = PrivateMedia::pathFromUrl($url);

        // Déjà enregistrée dans cette colonne : conservée sans nouvelle signature.
        if ($path !== null && isset($known[$path])) {
            return PrivateMedia::canonicalUrl($path);
        }

        return PrivateMedia::toStored($url);
    }

    /**
     * @param  array<int|string, mixed>  $items
     * @return array<string, true>
     */
    private function knownPaths(array $items): array
    {
        $paths = [];
        foreach ($items as $item) {
            $path = PrivateMedia::pathFromUrl(is_array($item) ? ($item['url'] ?? null) : $item);
            if ($path !== null) {
                $paths[$path] = true;
            }
        }

        return $paths;
    }

    /**
     * @return array<int|string, mixed>|null
     */
    private function decode(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }
}
