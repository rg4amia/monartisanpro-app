<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key') ?? '';
        $this->model = config('services.gemini.model', 'gemini-1.5-flash');
    }

    /**
     * Analyse un besoin de travaux et retourne une estimation.
     */
    public function analyzeMission(string $description, array $context = []): array
    {
        if (empty($this->apiKey) || config('app.env') === 'testing') {
            return $this->getFallbackAnalysis($description);
        }

        try {
            $prompt = $this->buildPrompt($description, $context);

            $response = Http::withOptions([
                'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                ]
            ]);

            if ($response->successful()) {
                $result = json_decode($response->json()['candidates'][0]['content']['parts'][0]['text'], true);
                return [
                    'category' => $result['category'] ?? 'Travaux généraux',
                    'urgency' => $result['urgency'] ?? 'moyen',
                    'price_min' => (int) ($result['price_min'] ?? 50000),
                    'price_max' => (int) ($result['price_max'] ?? 250000),
                    'explanation' => $result['explanation'] ?? '',
                ];
            }

            Log::error('Gemini API Error', ['body' => $response->body()]);
        } catch (\Exception $e) {
            Log::error('Gemini Exception', ['message' => $e->getMessage()]);
        }

        return $this->getFallbackAnalysis($description);
    }

    private function buildPrompt(string $description, array $context = []): string
    {
        $categoryHint = trim((string) ($context['category'] ?? ''));
        $locationHint = trim((string) ($context['location_address'] ?? ''));

        $contextLines = array_filter([
            $categoryHint !== '' ? "Catégorie suggérée par l'application : {$categoryHint}" : null,
            $locationHint !== '' ? "Zone d'intervention : {$locationHint}" : null,
        ]);

        $contextBlock = empty($contextLines)
            ? ''
            : "\nContexte complémentaire:\n- " . implode("\n- ", $contextLines) . "\n";

        return "Tu es un expert en bâtiment en Côte d'Ivoire. Analyse ce besoin client et retourne un JSON.
        Description: {$description}{$contextBlock}
        
        Retourne obligatoirement ce format JSON:
        {
          \"category\": \"(Plomberie|Électricité|Peinture|Maçonnerie|Menuiserie|Froid|Général)\",
          \"urgency\": \"(faible|moyen|urgent)\",
          \"price_min\": 50000,
          \"price_max\": 150000,
          \"explanation\": \"Courte explication en français\"
        }";
    }

    private function getFallbackAnalysis(string $description): array
    {
        $desc = strtolower($description);
        $category = 'Général';
        if (str_contains($desc, 'plomb') || str_contains($desc, 'eau')) $category = 'Plomberie';
        elseif (str_contains($desc, 'electr') || str_contains($desc, 'courant')) $category = 'Électricité';
        elseif (str_contains($desc, 'peint') || str_contains($desc, 'mur')) $category = 'Peinture';

        return [
            'category' => $category,
            'urgency' => 'moyen',
            'price_min' => 25000,
            'price_max' => 100000,
            'explanation' => 'Estimation basée sur des mots-clés (API Gemini non disponible).',
        ];
    }

    /**
     * Suggère une structure de devis (lignes et jalons) pour aider l'artisan.
     */
    public function suggestDevis(\App\Models\Mission $mission): array
    {
        if (empty($this->apiKey) || config('app.env') === 'testing') {
            return $this->getFallbackDevisSuggestion($mission);
        }

        try {
            $prompt = "Tu es un expert en bâtiment en Côte d'Ivoire. Aide un artisan à rédiger un devis pour la mission suivante.
            Description du besoin client: \"{$mission->description}\"
            
            Tu dois proposer :
            1. Des lignes de devis (lignes) avec :
               - \"type\": \"mo\" (main d'œuvre) ou \"mat\" (matériaux)
               - \"description\": description claire en français
               - \"montant\": montant entier en FCFA (sans décimale)
            2. Des jalons de paiement (jalons) avec :
               - \"ordre\": numéro d'ordre (1, 2, ...)
               - \"description\": ce qui sera fait à cette étape
               - \"montant\": montant libéré pour ce jalon en FCFA
               - \"date_cible_days\": nombre de jours requis à partir d'aujourd'hui pour atteindre ce jalon (ex: 3 pour 3 jours)
               
            RÈGLES CRITIQUES :
            - Le montant total des lignes (somme de lignes.*.montant) DOIT être exactement égal au montant total des jalons (somme de jalons.*.montant).
            - Interdiction absolue d'inclure des coordonnées, des numéros de téléphone ou des contacts dans les descriptions.
            - Les descriptions doivent être simples, claires et rédigées en français.

            Retourne obligatoirement ce format JSON uniquement:
            {
              \"lignes\": [
                {
                  \"type\": \"mo|mat\",
                  \"description\": \"...\",
                  \"montant\": 15000
                }
              ],
              \"jalons\": [
                {
                  \"ordre\": 1,
                  \"description\": \"...\",
                  \"montant\": 15000,
                  \"date_cible_days\": 3
                }
              ]
            }";

            $response = Http::withOptions([
                'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                ]
            ]);

            if ($response->successful()) {
                $result = json_decode($response->json()['candidates'][0]['content']['parts'][0]['text'], true);
                if (isset($result['lignes']) && isset($result['jalons'])) {
                    return $this->formatAndBalanceSuggestion($result);
                }
            }

            Log::error('Gemini Devis Suggestion Error', ['body' => $response->body()]);
        } catch (\Exception $e) {
            Log::error('Gemini Devis Suggestion Exception', ['message' => $e->getMessage()]);
        }

        return $this->getFallbackDevisSuggestion($mission);
    }

    /**
     * Formate les dates cibles des jalons et assure la balance stricte des montants.
     */
    private function formatAndBalanceSuggestion(array $suggestion): array
    {
        $lignes = [];
        $totalLignes = 0;

        foreach ($suggestion['lignes'] as $ligne) {
            $montant = (int) ($ligne['montant'] ?? 0);
            $totalLignes += $montant;
            $lignes[] = [
                'type' => $ligne['type'] ?? 'mo',
                'description' => strip_tags($ligne['description'] ?? 'Ligne de devis'),
                'montant' => $montant,
                'source' => 'custom',
            ];
        }

        $jalons = [];
        $totalJalons = 0;
        foreach ($suggestion['jalons'] as $jalon) {
            $montant = (int) ($jalon['montant'] ?? 0);
            $totalJalons += $montant;
            $days = (int) ($jalon['date_cible_days'] ?? 3);
            if ($days < 1) $days = 3;

            $jalons[] = [
                'ordre' => (int) ($jalon['ordre'] ?? 1),
                'description' => strip_tags($jalon['description'] ?? 'Jalon'),
                'montant' => $montant,
                'date_cible' => now()->addDays($days)->toDateString(),
            ];
        }

        // Assurer l'équilibrage parfait des montants
        if ($totalLignes !== $totalJalons && count($jalons) > 0) {
            $diff = $totalLignes - $totalJalons;
            $lastIndex = count($jalons) - 1;
            $jalons[$lastIndex]['montant'] += $diff;
            if ($jalons[$lastIndex]['montant'] < 0) {
                $jalons[$lastIndex]['montant'] = 0;
            }
        }

        return [
            'lignes' => $lignes,
            'jalons' => $jalons,
        ];
    }

    /**
     * Fallbacks thématiques pour les devis.
     */
    private function getFallbackDevisSuggestion(\App\Models\Mission $mission): array
    {
        $desc = strtolower($mission->description);
        
        if (str_contains($desc, 'plomb') || str_contains($desc, 'eau') || str_contains($desc, 'tuyau')) {
            $lignes = [
                ['type' => 'mat', 'description' => 'Achat de fournitures plomberie (tuyaux PVC, colle, raccords, joint)', 'montant' => 25000, 'source' => 'custom'],
                ['type' => 'mo', 'description' => 'Main d\'œuvre plomberie : installation et raccordements de base', 'montant' => 15000, 'source' => 'custom']
            ];
        } elseif (str_contains($desc, 'electr') || str_contains($desc, 'courant') || str_contains($desc, 'ampoule')) {
            $lignes = [
                ['type' => 'mat', 'description' => 'Câbles électriques, disjoncteurs et prises de rechange', 'montant' => 30000, 'source' => 'custom'],
                ['type' => 'mo', 'description' => 'Recherche de panne et réfection du câblage défaillant', 'montant' => 20000, 'source' => 'custom']
            ];
        } elseif (str_contains($desc, 'peint') || str_contains($desc, 'mur') || str_contains($desc, 'enduit')) {
            $lignes = [
                ['type' => 'mat', 'description' => 'Pots de peinture blanc mat (30L) et pinceaux/rouleaux', 'montant' => 45000, 'source' => 'custom'],
                ['type' => 'mo', 'description' => 'Préparation des supports et application de deux couches de peinture', 'montant' => 25000, 'source' => 'custom']
            ];
        } else {
            $lignes = [
                ['type' => 'mat', 'description' => 'Matériaux et outillages consommables nécessaires aux travaux', 'montant' => 35000, 'source' => 'custom'],
                ['type' => 'mo', 'description' => 'Prestation de main d\'œuvre pour l\'exécution des travaux demandés', 'montant' => 25000, 'source' => 'custom']
            ];
        }

        $total = collect($lignes)->sum('montant');

        $jalons = [
            [
                'ordre' => 1,
                'description' => 'Démarrage du chantier et installation des matériaux requis',
                'montant' => (int) round($total * 0.5),
                'date_cible' => now()->addDays(2)->toDateString(),
            ],
            [
                'ordre' => 2,
                'description' => 'Livraison finale et nettoyage après travaux',
                'montant' => (int) ($total - round($total * 0.5)),
                'date_cible' => now()->addDays(5)->toDateString(),
            ]
        ];

        return [
            'lignes' => $lignes,
            'jalons' => $jalons,
        ];
    }
}
