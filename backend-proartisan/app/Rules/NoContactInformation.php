<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NoContactInformation implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            return;
        }

        // 1. Détection des adresses email
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $value)) {
            $fail("L'insertion d'adresses e-mail est interdite dans le champ :attribute pour éviter le contournement de la plateforme.");
            return;
        }

        // 2. Détection des mots-clés suspects ou invitations à contacter hors plateforme
        $suspiciousPatterns = [
            '/\bwhatsapp\b/i',
            '/\btelegram\b/i',
            '/\bfacebook\b/i',
            '/\binstagram\b/i',
            '/\bcontactez[- ]moi\b/i',
            '/\bappelez[- ]moi\b/i',
            '/\bmon numero\b/i',
            '/\bmon numéro\b/i',
            '/\bmon contact\b/i',
            '/\bécrivez[- ]moi\b/i',
            '/\becrivez[- ]moi\b/i',
            '/\bjoignable au\b/i',
            '/\bjoignable sur\b/i',
            '/tel\s*:\s*\+?\d+/i',
            '/tél\s*:\s*\+?\d+/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                $fail("L'insertion de coordonnées de contact ou de réseaux sociaux est interdite dans le champ :attribute.");
                return;
            }
        }

        // 3. Détection des numéros de téléphone (Côte d'Ivoire et générique)
        // On nettoie la chaîne pour garder uniquement les chiffres pour vérifier les séquences numériques longues
        $onlyDigits = preg_replace('/[^0-9]/', '', $value);

        // Si la chaîne contient un indicatif de pays 225
        if (preg_match('/(?:225|\+225|00225)\s*[0-9]/', $value)) {
            // S'il y a un indicatif 225 suivi de chiffres (ex: 22507080910)
            // On vérifie que la longueur totale des chiffres après l'indicatif est d'au moins 8 digits
            if (strlen($onlyDigits) >= 11) { // 225 (3) + 8 = 11
                $fail("Les numéros de téléphone avec indicatif ne sont pas autorisés dans le champ :attribute.");
                return;
            }
        }

        // Numéro CI à 10 chiffres (commence par 01, 05, 07 pour les mobiles, et 21, 25, 27, 20, 22, 23 pour les fixes)
        // On cherche une séquence de 10 chiffres (pouvant être séparés par des espaces/tiret/points)
        // ex: 07 08 09 10 11, 05-06-07-08-09
        if (preg_match('/(?:^|[^0-9])(01|05|07|20|21|22|23|25|27)(?:\s*[-.]?\s*\d){8}(?:$|[^0-9])/', $value)) {
            $fail("Les numéros de téléphone à 10 chiffres ne sont pas autorisés dans le champ :attribute.");
            return;
        }

        // Anciens formats à 8 chiffres commençant par 0, 4, 5, 6, 7, 8, 9 (pour rétrocompatibilité et par sécurité)
        if (preg_match('/(?:^|[^0-9])(0|4|5|6|7|8|9)(?:\s*[-.]?\s*\d){7}(?:$|[^0-9])/', $value)) {
            $fail("Les numéros de téléphone à 8 chiffres ne sont pas autorisés dans le champ :attribute.");
            return;
        }
    }
}
