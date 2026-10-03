<?php

namespace App\Services\Llm;

use Illuminate\Validation\Rule;

/**
 * Forme d'une fiche technique de la base de connaissances : celle que lisent
 * l'Assistant IA et sa page mobile. Toute fiche — générée par l'IA ou saisie
 * par un administrateur — passe par `normalize()` avant d'être enregistrée.
 */
class KnowledgeSheetSchema
{
    public const PRICE_RANGES = ['Faible', 'Moyen', 'Eleve'];

    public const MAX_ROWS = 20;

    public const MAX_TAGS = 12;

    /**
     * Règles de validation d'une fiche saisie ou corrigée dans le backoffice.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'norme_origine' => ['nullable', 'array'],
            'norme_origine.source' => ['nullable', 'string', 'max:255'],
            'norme_origine.reference_article' => ['nullable', 'string', 'max:255'],
            'norme_origine.titre_original' => ['nullable', 'string', 'max:255'],
            'norme_origine.texte_brut' => ['nullable', 'string', 'max:20000'],
            'alternative_prosartisan' => ['required', 'array'],
            'alternative_prosartisan.titre_vulgarise' => ['required', 'string', 'min:3', 'max:255'],
            'alternative_prosartisan.methode_execution' => ['required', 'string', 'min:10', 'max:10000'],
            'alternative_prosartisan.bouclier_autorite' => ['nullable', 'string', 'max:5000'],
            'alternative_prosartisan.dosages_recommandes' => ['nullable', 'array', 'max:'.self::MAX_ROWS],
            'alternative_prosartisan.dosages_recommandes.*.element' => ['required', 'string', 'max:255'],
            'alternative_prosartisan.dosages_recommandes.*.ratio' => ['required', 'string', 'max:255'],
            'alternative_prosartisan.dosages_recommandes.*.unite_mesure_locale' => ['nullable', 'string', 'max:100'],
            'alternative_prosartisan.materiaux_recommandes' => ['nullable', 'array', 'max:'.self::MAX_ROWS],
            'alternative_prosartisan.materiaux_recommandes.*.nom' => ['required', 'string', 'max:255'],
            'alternative_prosartisan.materiaux_recommandes.*.substitut_acceptable' => ['nullable', 'string', 'max:255'],
            'alternative_prosartisan.materiaux_recommandes.*.disponibilite' => ['nullable', 'string', 'max:100'],
            'cout_estime_local' => ['nullable', 'array'],
            'cout_estime_local.gamme_prix' => ['nullable', Rule::in(self::PRICE_RANGES)],
            'cout_estime_local.estimation_m2_fcfa' => ['nullable', 'string', 'max:255'],
            'cout_estime_local.justification_economique' => ['nullable', 'string', 'max:2000'],
            'metadata' => ['required', 'array'],
            'metadata.tags_pathologies' => ['required', 'array', 'min:1', 'max:'.self::MAX_TAGS],
            'metadata.tags_pathologies.*' => ['required', 'string', 'max:60'],
            'metadata.type_ouvrage' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'alternative_prosartisan.titre_vulgarise.required' => 'Donnez un titre à la fiche.',
            'alternative_prosartisan.titre_vulgarise.min' => 'Le titre de la fiche doit compter au moins 3 caractères.',
            'alternative_prosartisan.methode_execution.required' => "Décrivez la méthode d'exécution.",
            'alternative_prosartisan.methode_execution.min' => "La méthode d'exécution doit compter au moins 10 caractères.",
            'alternative_prosartisan.dosages_recommandes.*.element.required' => 'Chaque dosage doit nommer son élément.',
            'alternative_prosartisan.dosages_recommandes.*.ratio.required' => 'Chaque dosage doit indiquer sa quantité.',
            'alternative_prosartisan.materiaux_recommandes.*.nom.required' => 'Chaque matériau doit être nommé.',
            'cout_estime_local.gamme_prix.in' => 'La gamme de prix est Faible, Moyen ou Eleve.',
            'metadata.tags_pathologies.required' => 'Indiquez au moins un mot-clé : sans lui, la fiche ne sera jamais trouvée.',
            'metadata.tags_pathologies.min' => 'Indiquez au moins un mot-clé : sans lui, la fiche ne sera jamais trouvée.',
        ];
    }

    /**
     * Ramène un contenu quelconque à la forme d'une fiche. Une valeur absente
     * reste vide : rien n'est complété par une valeur par défaut.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public static function normalize(array $raw, string $id): array
    {
        $norme = self::arr($raw['norme_origine'] ?? null);
        $alt = self::arr($raw['alternative_prosartisan'] ?? null);
        $cout = self::arr($raw['cout_estime_local'] ?? null);
        $meta = self::arr($raw['metadata'] ?? null);

        $dosages = [];
        foreach (array_slice(self::arr($alt['dosages_recommandes'] ?? null), 0, self::MAX_ROWS) as $row) {
            $row = self::arr($row);
            $element = self::str($row['element'] ?? null, 255);
            $ratio = self::str($row['ratio'] ?? null, 255);
            if ($element !== '' && $ratio !== '') {
                $dosages[] = [
                    'element' => $element,
                    'ratio' => $ratio,
                    'unite_mesure_locale' => self::str($row['unite_mesure_locale'] ?? null, 100),
                ];
            }
        }

        $materiaux = [];
        foreach (array_slice(self::arr($alt['materiaux_recommandes'] ?? null), 0, self::MAX_ROWS) as $row) {
            $row = self::arr($row);
            $nom = self::str($row['nom'] ?? null, 255);
            if ($nom !== '') {
                $materiaux[] = [
                    'nom' => $nom,
                    'substitut_acceptable' => self::str($row['substitut_acceptable'] ?? null, 255),
                    'disponibilite' => self::str($row['disponibilite'] ?? null, 100),
                ];
            }
        }

        $tags = [];
        foreach (self::arr($meta['tags_pathologies'] ?? null) as $tag) {
            $tag = self::tag($tag);
            if ($tag !== '' && ! in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
        }

        $gamme = self::str($cout['gamme_prix'] ?? null, 20);
        // L'IA écrit volontiers « Élevé » : la valeur attendue est sans accent.
        $gamme = ['Élevé' => 'Eleve', 'Elevé' => 'Eleve', 'Élevée' => 'Eleve'][$gamme] ?? $gamme;

        return [
            'id' => $id,
            'norme_origine' => [
                'source' => self::str($norme['source'] ?? null, 255),
                'reference_article' => self::str($norme['reference_article'] ?? null, 255),
                'titre_original' => self::str($norme['titre_original'] ?? null, 255),
                'texte_brut' => self::str($norme['texte_brut'] ?? null, 20000),
            ],
            'alternative_prosartisan' => [
                'titre_vulgarise' => self::str($alt['titre_vulgarise'] ?? null, 255),
                'methode_execution' => self::str($alt['methode_execution'] ?? null, 10000),
                'bouclier_autorite' => self::str($alt['bouclier_autorite'] ?? null, 5000),
                'dosages_recommandes' => $dosages,
                'materiaux_recommandes' => $materiaux,
            ],
            'cout_estime_local' => [
                'gamme_prix' => in_array($gamme, self::PRICE_RANGES, true) ? $gamme : '',
                'estimation_m2_fcfa' => self::str($cout['estimation_m2_fcfa'] ?? null, 255),
                'justification_economique' => self::str($cout['justification_economique'] ?? null, 2000),
            ],
            'metadata' => [
                'tags_pathologies' => array_slice($tags, 0, self::MAX_TAGS),
                'type_ouvrage' => self::str($meta['type_ouvrage'] ?? null, 100),
            ],
        ];
    }

    /**
     * Une fiche n'est exploitable que si elle a un titre, une méthode et un mot-clé.
     *
     * @param  array<string, mixed>  $sheet
     */
    public static function isUsable(array $sheet): bool
    {
        return $sheet['alternative_prosartisan']['titre_vulgarise'] !== ''
            && $sheet['alternative_prosartisan']['methode_execution'] !== ''
            && $sheet['metadata']['tags_pathologies'] !== [];
    }

    /**
     * Mot-clé de recherche : minuscules, sans accent, mots reliés par « _ ».
     */
    public static function tag(mixed $value): string
    {
        $value = mb_strtolower(self::str($value, 60));
        $value = strtr($value, [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o', 'û' => 'u', 'ù' => 'u', 'ü' => 'u', 'ç' => 'c',
        ]);

        return trim((string) preg_replace('/[^a-z0-9]+/', '_', $value), '_');
    }

    /**
     * @return array<int|string, mixed>
     */
    private static function arr(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private static function str(mixed $value, int $max): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        return mb_substr(trim((string) $value), 0, $max);
    }
}
