<?php

namespace App\Services;

use App\Models\Mission;
use Illuminate\Support\Facades\Log;

class AntiCircumventionService
{
    /**
     * Regex de détection des numéros de téléphone (Côte d'Ivoire & international).
     * Couvre les formats standards, avec séparateurs, espacés (ex: 07 01 02 03 04, 07.01.02.03.04).
     */
    private const PHONE_PATTERNS = [
        // Format CI avec ou sans indicatif : (+225) 07/05/01 suivi de 8 chiffres
        '/(?:\+?\s*225[\s.\-_]*)?(?:0\s*[157](?:[\s.\-_]*\d){8})/i',
        // Séquence continue ou espacée de 10 chiffres commençant par 0
        '/\b0(?:\s*\d){9}\b/',
        // Numéros internationaux génériques avec indicatif
        '/\+\s*\d{1,3}(?:[\s.\-_]*\d){8,12}/',
    ];

    /**
     * Regex de détection de numéros écrits en toutes lettres (ex: "zéro sept...", "zero cinq...").
     */
    private const TEXTUAL_NUMBER_PATTERNS = [
        '/(?:z[eé]ro\s+(?:sept|cinq|un))(?:\s+(?:z[eé]ro|un|deux|trois|quatre|cinq|six|sept|huit|neuf)){4,}/i',
    ];

    /**
     * Mots-clés incitant à la désintermédiation / contournement.
     */
    private const CIRCUMVENTION_KEYWORDS = [
        'en direct',
        'hors appli',
        'hors application',
        'hors plate-forme',
        'hors plateforme',
        'annuler l\'appli',
        'annule l\'appli',
        'wave direct',
        'orange money direct',
        'mon whatsapp',
        'ecris moi sur whatsapp',
        'écris-moi sur whatsapp',
        'appelle moi sur',
        'appelez moi sur',
        'passe ton numero',
        'donne ton numero',
        'donne ton contact',
        'envoie ton numero',
    ];

    /**
     * Analyse et traite un message de chantier avant enregistrement.
     *
     * @return array{content: string, is_redacted: bool, flagged: bool, flags_detail: array}
     */
    public function inspectAndFilter(string $content, Mission $mission): array
    {
        $filteredContent = $content;
        $isRedacted = false;
        $flagged = false;
        $flagsDetail = [];

        // 1. Détection des numéros de téléphone
        foreach (self::PHONE_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                $flagged = true;
                $flagsDetail[] = 'phone_number_detected';

                // Avant que la mission soit financée, masquage strict
                if (! $this->isChatAllowedWithoutFilter($mission)) {
                    $filteredContent = preg_replace($pattern, '[COORDONNÉES MASQUÉES AVANT VALIDATION DU DEVIS]', $filteredContent);
                    $isRedacted = true;
                }
            }
        }

        // 2. Détection des numéros textuels
        foreach (self::TEXTUAL_NUMBER_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                $flagged = true;
                $flagsDetail[] = 'textual_phone_detected';

                if (! $this->isChatAllowedWithoutFilter($mission)) {
                    $filteredContent = preg_replace($pattern, '[COORDONNÉES MASQUÉES AVANT VALIDATION DU DEVIS]', $filteredContent);
                    $isRedacted = true;
                }
            }
        }

        // 3. Détection des mots-clés de contournement
        $contentLower = mb_strtolower($content);
        foreach (self::CIRCUMVENTION_KEYWORDS as $keyword) {
            if (str_contains($contentLower, $keyword)) {
                $flagged = true;
                $flagsDetail[] = "circumvention_keyword: {$keyword}";

                if (! $this->isChatAllowedWithoutFilter($mission)) {
                    // Masque le mot-clé suspect
                    $escaped = preg_quote($keyword, '/');
                    $filteredContent = preg_replace("/\b{$escaped}\b/i", '[MESSAGE SIGNALÉ : ÉCHANGE HORS PLATEFORME INTERDIT]', $filteredContent);
                    $isRedacted = true;
                }
            }
        }

        if ($flagged) {
            Log::warning('AntiCircumvention: tentative de désintermédiation détectée', [
                'mission_id' => $mission->id,
                'client_id' => $mission->client_id,
                'artisan_id' => $mission->artisan_id,
                'flags' => $flagsDetail,
                'original_length' => strlen($content),
            ]);
        }

        return [
            'content' => $filteredContent,
            'is_redacted' => $isRedacted,
            'flagged' => $flagged,
            'flags_detail' => $flagsDetail,
        ];
    }

    /**
     * Le chat est-il totalement libre (sans filtrage) ?
     * Vrai dès que la mission est financée / devis accepté.
     */
    public function isChatAllowedWithoutFilter(Mission $mission): bool
    {
        return $mission->status instanceof \App\States\Mission\FundedLockedState
            || $mission->status instanceof \App\States\Mission\InProgressState
            || $mission->status instanceof \App\States\Mission\CompletedState;
    }
}
