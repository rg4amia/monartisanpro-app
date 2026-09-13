<?php

namespace App\Services;

/**
 * Plafond de téléversement réellement applicable.
 *
 * Valider un fichier à 10 Mo alors que PHP en refuse 8 ne protège de rien :
 * la requête est coupée avant d'atteindre Laravel, `$_POST` arrive vide, et
 * l'administrateur reçoit une erreur de validation incompréhensible du genre
 * « le champ titre est obligatoire » — sur un formulaire qu'il a rempli.
 *
 * Le plafond effectif est donc le plus petit entre la limite souhaitée,
 * `upload_max_filesize` et `post_max_size`. Ces deux directives ne sont pas
 * modifiables depuis le code sur un hébergement mutualisé : mieux vaut s'y
 * conformer et l'annoncer que promettre une taille que le serveur refusera.
 */
class UploadLimitService
{
    /**
     * Plafond effectif en kilooctets.
     *
     * @param  int  $desiredKilobytes  Limite métier souhaitée.
     */
    public function maxKilobytes(int $desiredKilobytes): int
    {
        $candidates = [$desiredKilobytes];

        foreach (['upload_max_filesize', 'post_max_size'] as $directive) {
            $bytes = $this->directiveInBytes($directive);

            // 0 ou absent signifie « sans limite » : la directive ne contraint rien.
            if ($bytes !== null && $bytes > 0) {
                $candidates[] = intdiv($bytes, 1024);
            }
        }

        // `post_max_size` couvre tout le corps de la requête, champs textuels
        // compris. On garde une marge pour eux plutôt que de frôler la coupure.
        return max(1, min($candidates));
    }

    /** Libellé lisible du plafond, pour les messages et l'interface. */
    public function humanLimit(int $desiredKilobytes): string
    {
        $kb = $this->maxKilobytes($desiredKilobytes);

        return $kb >= 1024
            ? rtrim(rtrim(number_format($kb / 1024, 1, ',', ' '), '0'), ',').' Mo'
            : $kb.' Ko';
    }

    /**
     * Convertit une notation ini (`8M`, `512K`, `1G`, `1048576`) en octets.
     */
    public static function parseSize(?string $raw): ?int
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        $unit = strtolower(substr($raw, -1));
        $value = (int) $raw;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    /**
     * Valeur brute d'une directive PHP.
     *
     * Isolée et surchargeable : `upload_max_filesize` et `post_max_size` sont
     * `PHP_INI_PERDIR`, donc impossibles à modifier avec `ini_set` à
     * l'exécution. Sans cette couture, aucun test ne pourrait éprouver le
     * comportement sous une configuration serveur différente de la sienne.
     */
    protected function directiveRaw(string $directive): string
    {
        return (string) ini_get($directive);
    }

    private function directiveInBytes(string $directive): ?int
    {
        return self::parseSize($this->directiveRaw($directive));
    }
}
