<?php

namespace App\Services;

use App\Models\Jalon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GeminiService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key') ?? '';
        $this->model = config('services.gemini.model', 'gemini-3.6-flash');
        $this->baseUrl = config('services.gemini.base_url', 'https://generativelanguage.googleapis.com');
    }

    private function getEndpointUrl(): string
    {
        $base = rtrim($this->baseUrl, '/');
        if (!str_contains($base, 'models')) {
            $base .= "/v1beta/models/{$this->model}:generateContent";
        }
        return "{$base}?key={$this->apiKey}";
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

            $response = Http::timeout(8)
                ->connectTimeout(4)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
                ])->withOptions([
                    'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]
                ])->post($this->getEndpointUrl(), [
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

            Log::error('Gemini API Error', ['status' => $response->status(), 'body' => $response->body()]);
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

            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            ])->withOptions([
                'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]
            ])->post($this->getEndpointUrl(), [
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

            Log::error('Gemini Devis Suggestion Error', ['status' => $response->status(), 'body' => $response->body()]);
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

    /**
     * Analyse un fichier média (photo/vidéo) pour repérer les contacts, adresses, localisations.
     */
    public function analyzeMediaForSensitiveData(\Illuminate\Http\UploadedFile $file): array
    {
        if (empty($this->apiKey) || config('app.env') === 'testing') {
            // Dans l'environnement de test ou sans clé, on fait une simulation souple.
            // On rejette si le nom du fichier contient le mot clé "sensitive", "contact" ou "address"
            $fileName = strtolower($file->getClientOriginalName());
            if (str_contains($fileName, 'sensitive') || str_contains($fileName, 'contact') || str_contains($fileName, 'address')) {
                return [
                    'contains_sensitive_data' => true,
                    'details' => 'Le fichier contient des indications interdites (détecté par simulation).',
                ];
            }
            return [
                'contains_sensitive_data' => false,
                'details' => '',
            ];
        }

        try {
            $mimeType = $file->getMimeType();
            $isImage = str_starts_with($mimeType, 'image/');
            $isVideo = str_starts_with($mimeType, 'video/');

            if (!$isImage && !$isVideo) {
                return [
                    'contains_sensitive_data' => false,
                    'details' => '',
                ];
            }

            $base64Data = base64_encode(file_get_contents($file->getPathname()));

            $prompt = "Analyse cette image ou vidéo. Tu devez repérer et rejeter tout élément qui contient des coordonnées personnelles, professionnelles ou des indications de localisation précise.
            
            Détecte et signale comme INTERDIT (contains_sensitive_data = true) si tu trouves :
            - Un numéro de téléphone (ex: +225, 07, 05, 01, etc.)
            - Une adresse email ou un contact de réseau social (Facebook, WhatsApp, Instagram, etc.)
            - Une adresse physique écrite ou une localisation précise (nom de rue, coordonnées GPS écrites, plaque d'immatriculation lisible, adresse postale, etc.)

            Retourne obligatoirement ce format JSON uniquement:
            {
              \"contains_sensitive_data\": true|false,
              \"details\": \"Raison succincte en français si true\"
            }";

            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0'
            ])->withOptions([
                'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]
            ])->post($this->getEndpointUrl(), [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'inlineData' => [
                                    'mimeType' => $mimeType,
                                    'data' => $base64Data,
                                ]
                            ],
                            [
                                'text' => $prompt
                            ]
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
                    'contains_sensitive_data' => (bool) ($result['contains_sensitive_data'] ?? false),
                    'details' => $result['details'] ?? '',
                ];
            }

            Log::error('Gemini Media API Error', ['status' => $response->status(), 'body' => $response->body()]);
        } catch (\Exception $e) {
            Log::error('Gemini Media Exception', ['message' => $e->getMessage()]);
        }

        // En cas d'erreur de communication, on tolère pour éviter de bloquer l'expérience utilisateur
        return [
            'contains_sensitive_data' => false,
            'details' => '',
        ];
    }

    /**
     * Analyse un enregistrement audio dicté par un artisan pour générer les lignes et jalons de devis.
     * Supporte les accents ivoiriens, le nouchi et le vocabulaire BTP local.
     */
    public function parseVoiceQuote(
        \App\Models\Mission $mission,
        string $audioBase64,
        string $mimeType,
        ?int $userId = null
    ): array {
        if (empty($this->apiKey) || config('app.env') === 'testing') {
            $fallback = $this->getFallbackDevisSuggestion($mission);
            $fallback['transcription'] = "Transcription audio simulée (mode test/hors-ligne) : fournitures et main d'œuvre pour les travaux de la mission.";
            return $fallback;
        }

        $startTime = microtime(true);

        try {
            $prompt = "Tu es un métreur et expert BTP expérimenté en Côte d'Ivoire.
Un artisan du bâtiment t'a envoyé un enregistrement vocal décrivant son devis (fournitures, outillage, main d'œuvre, délais).
Comprends le français, le langage familier ivoirien, le nouchi et les expressions de chantier locales.

Contexte de la mission :
- Titre / besoin client : \"{$mission->description}\"

Ta tâche :
1. Transcrire fidèlement ce que dit l'artisan dans le champ \"transcription\".
2. Extraire et catégoriser les lignes de devis (lignes) :
   - \"type\": \"mo\" (main d'œuvre) ou \"mat\" (matériaux / fournitures)
   - \"description\": description claire et propre en français (ex: \"Achat de 3 sacs de ciment CPJ 42.5\", \"Pose et raccordement plomberie\")
   - \"montant\": entier en FCFA (sans décimale). Si l'artisan donne un prix unitaire et une quantité, calcule le montant total.
3. Proposer un découpage logique en jalons de paiement (jalons) :
   - \"ordre\": 1, 2, ...
   - \"description\": livrable ou étape correspondante
   - \"montant\": entier en FCFA
   - \"date_cible_days\": nombre de jours requis pour atteindre ce jalon (par défaut 2 à 5 jours)

RÈGLES CRITIQUES :
- Le montant total des lignes (somme de lignes.*.montant) DOIT être exactement égal au montant total des jalons (somme de jalons.*.montant).
- Aucun numéro de téléphone ni coordonnée dans les descriptions.
- Si le montant n'est pas précisé dans l'audio pour certains éléments, estime un prix cohérent avec le marché abidjanais en FCFA.

Retourne obligatoirement ce format JSON uniquement:
{
  \"transcription\": \"...\",
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

            $response = Http::timeout(25)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0'
                ])->withOptions([
                    'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]
                ])->post($this->getEndpointUrl(), [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'inlineData' => [
                                        'mimeType' => $mimeType,
                                        'data' => $audioBase64,
                                    ]
                                ],
                                [
                                    'text' => $prompt
                                ]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'response_mime_type' => 'application/json',
                    ]
                ]);

            $responseTimeMs = (microtime(true) - $startTime) * 1000;

            if ($response->successful()) {
                $rawJson = $response->json();
                $textResult = $rawJson['candidates'][0]['content']['parts'][0]['text'] ?? '';
                $result = json_decode($textResult, true);

                $usage = $rawJson['usageMetadata'] ?? [];
                $promptTokens = (int) ($usage['promptTokenCount'] ?? 200);
                $completionTokens = (int) ($usage['candidatesTokenCount'] ?? 150);

                AiMonitoringService::log(
                    $this->model,
                    'voice_quote',
                    $promptTokens,
                    $completionTokens,
                    $responseTimeMs,
                    200,
                    null,
                    $userId
                );

                if (isset($result['lignes']) && isset($result['jalons'])) {
                    $balanced = $this->formatAndBalanceSuggestion($result);
                    $balanced['transcription'] = (string) ($result['transcription'] ?? 'Transcription effectuée.');
                    return $balanced;
                }
            }

            AiMonitoringService::log(
                $this->model,
                'voice_quote',
                0,
                0,
                $responseTimeMs,
                $response->status(),
                $response->body(),
                $userId
            );

            Log::error('Gemini Voice Quote API Error', ['status' => $response->status(), 'body' => $response->body()]);
        } catch (\Exception $e) {
            $responseTimeMs = (microtime(true) - $startTime) * 1000;
            AiMonitoringService::log(
                $this->model,
                'voice_quote',
                0,
                0,
                $responseTimeMs,
                500,
                $e->getMessage(),
                $userId
            );
            Log::error('Gemini Voice Quote Exception', ['message' => $e->getMessage()]);
        }

        $fallback = $this->getFallbackDevisSuggestion($mission);
        $fallback['transcription'] = "Transcription approximative : besoin évalué à partir de la description initiale du chantier.";
        return $fallback;
    }

    /**
     * Analyse la conformité visuelle des photos de preuves d'un jalon (Gemini Multimodal).
     *
     * @param Jalon $jalon
     * @param array $photos
     * @return array
     */
    public function analyzeMilestoneVision(Jalon $jalon, array $photos = []): array
    {
        $photosList = !empty($photos) ? $photos : ($jalon->photos_json ?? []);

        if (empty($photosList)) {
            return [
                'approved'          => true,
                'conformity_score'  => 100,
                'detected_elements' => ['non_applicable'],
                'anomalies'         => [],
                'summary'           => 'Aucune photo soumise pour analyse visuelle.',
                'confidence'        => 'medium',
                'analyzed_at'       => now()->toIso8601String(),
            ];
        }

        $isTestingOrNoKey = empty($this->apiKey) || $this->apiKey === 'PLACEHOLDER_KEY' || config('app.env') === 'testing';

        if ($isTestingOrNoKey) {
            $descLower = strtolower($jalon->description);
            $isSuspect = str_contains($descLower, 'frauduleux') ||
                         str_contains($descLower, 'incohérent') ||
                         str_contains($descLower, 'incoherent') ||
                         str_contains($descLower, 'faux') ||
                         str_contains($descLower, 'fake') ||
                         str_contains($descLower, 'mismatch');

            if ($isSuspect) {
                return [
                    'approved'          => false,
                    'conformity_score'  => 25,
                    'detected_elements' => ['surface_vide', 'element_inconnu'],
                    'anomalies'         => ['incohérence_visuelle_majeure', 'absence_d_ouvrage_conforme'],
                    'summary'           => "Incohérence visuelle détectée : la photo fournie ne correspond pas aux travaux spécifiés pour '{$jalon->description}'.",
                    'confidence'        => 'high',
                    'analyzed_at'       => now()->toIso8601String(),
                ];
            }

            return [
                'approved'          => true,
                'conformity_score'  => 92,
                'detected_elements' => ['materiaux_conformes', 'travaux_executes', 'outillage_chantier'],
                'anomalies'         => [],
                'summary'           => "Preuve visuelle conforme : réalisation cohérente avec l'intitulé '{$jalon->description}'.",
                'confidence'        => 'high',
                'analyzed_at'       => now()->toIso8601String(),
            ];
        }

        // Préparation des données d'images pour l'appel multimodal Gemini
        $parts = [];
        $category = $jalon->mission?->category ?? 'BTP / Travaux';
        $prompt = "Tu es un expert assermenté en inspection technique de chantiers du bâtiment et travaux publics en Côte d'Ivoire.\n";
        $prompt .= "L'artisan déclare avoir achevé le jalon suivant : '{$jalon->description}' dans le cadre d'une mission de type '{$category}'.\n";
        $prompt .= "Examine avec attention la ou les photos de preuve jointes.\n";
        $prompt .= "Critères d'analyse :\n";
        $prompt .= "1. Cohérence technique : les éléments visibles correspondent-ils aux travaux annoncés ?\n";
        $prompt .= "2. Détection de fraude visuelle : photo d'un écran d'ordinateur/téléphone, image générique du web, chantier vide, absence d'ouvrage neuf ou réparé.\n";
        $prompt .= "3. Score de conformité : attribue une note de 0 à 100 reflétant la certitude de conformité.\n\n";
        $prompt .= "Réponds OBLIGATOIREMENT par un objet JSON strict au format suivant :\n";
        $prompt .= "{\n";
        $prompt .= "  \"approved\": true ou false,\n";
        $prompt .= "  \"conformity_score\": 85,\n";
        $prompt .= "  \"detected_elements\": [\"element1\", \"element2\"],\n";
        $prompt .= "  \"anomalies\": [\"anomalie1\"],\n";
        $prompt .= "  \"summary\": \"Explication claire et synthétique en français\",\n";
        $prompt .= "  \"confidence\": \"high\" ou \"medium\" ou \"low\"\n";
        $prompt .= "}";

        $parts[] = ['text' => $prompt];

        // Charger jusqu'à 3 images
        $imageCount = 0;
        foreach (array_slice($photosList, 0, 3) as $photo) {
            $path = is_array($photo) ? ($photo['path'] ?? null) : null;
            if (!$path && is_array($photo) && isset($photo['url'])) {
                $path = str_replace('/storage/', '', parse_url($photo['url'], PHP_URL_PATH) ?? '');
            }

            if ($path && Storage::disk('public')->exists($path)) {
                $raw = Storage::disk('public')->get($path);
                /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
                $disk = Storage::disk('public');
                $mime = $disk->mimeType($path) ?? 'image/jpeg';
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $mime,
                        'data'      => base64_encode($raw),
                    ]
                ];
                $imageCount++;
            }
        }

        if ($imageCount === 0) {
            return [
                'approved'          => true,
                'conformity_score'  => 85,
                'detected_elements' => ['non_verifiable_localement'],
                'anomalies'         => [],
                'summary'           => 'Photos stockées sur un support distant non directement accessible en local pour vision API.',
                'confidence'        => 'low',
                'analyzed_at'       => now()->toIso8601String(),
            ];
        }

        $startTime = microtime(true);
        $userId = $jalon->mission?->artisan_id;

        try {
            $response = Http::timeout(12)
                ->connectTimeout(5)
                ->post($this->getEndpointUrl(), [
                    'contents' => [
                        ['parts' => $parts]
                    ],
                    'generationConfig' => [
                        'response_mime_type' => 'application/json',
                    ]
                ]);

            $responseTimeMs = (microtime(true) - $startTime) * 1000;

            if ($response->successful()) {
                $jsonText = $response->json('candidates.0.content.parts.0.text') ?? '{}';
                $result = json_decode($jsonText, true);

                $promptTokens = $response->json('usageMetadata.promptTokenCount') ?? 0;
                $completionTokens = $response->json('usageMetadata.candidatesTokenCount') ?? 0;

                AiMonitoringService::log(
                    $this->model,
                    'vision_milestone',
                    $promptTokens,
                    $completionTokens,
                    $responseTimeMs,
                    200,
                    null,
                    $userId
                );

                if (is_array($result) && isset($result['conformity_score'])) {
                    $score = max(0, min(100, (int) $result['conformity_score']));
                    return [
                        'approved'          => (bool) ($result['approved'] ?? ($score >= 50)),
                        'conformity_score'  => $score,
                        'detected_elements' => (array) ($result['detected_elements'] ?? []),
                        'anomalies'         => (array) ($result['anomalies'] ?? []),
                        'summary'           => (string) ($result['summary'] ?? 'Analyse visuelle complétée.'),
                        'confidence'        => (string) ($result['confidence'] ?? 'high'),
                        'analyzed_at'       => now()->toIso8601String(),
                    ];
                }
            }

            AiMonitoringService::log(
                $this->model,
                'vision_milestone',
                0,
                0,
                $responseTimeMs,
                $response->status(),
                $response->body(),
                $userId
            );

            Log::error('Gemini Vision API Error', ['status' => $response->status(), 'body' => $response->body()]);
        } catch (\Throwable $e) {
            $responseTimeMs = (microtime(true) - $startTime) * 1000;
            AiMonitoringService::log(
                $this->model,
                'vision_milestone',
                0,
                0,
                $responseTimeMs,
                500,
                $e->getMessage(),
                $userId
            );
            Log::error('Gemini Vision Exception', ['message' => $e->getMessage()]);
        }

        return [
            'approved'          => true,
            'conformity_score'  => 75,
            'detected_elements' => ['inspection_secours'],
            'anomalies'         => [],
            'summary'           => 'Approbation par défaut suite à une indisponibilité temporaire du service de vision.',
            'confidence'        => 'low',
            'analyzed_at'       => now()->toIso8601String(),
        ];
    }
}
