<?php

namespace App\Services;

use App\Models\Jalon;
use App\Models\Mission;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
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
        if (! str_contains($base, 'models')) {
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
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                ])->withOptions([
                    'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
                ])->post($this->getEndpointUrl(), [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'response_mime_type' => 'application/json',
                    ],
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
            : "\nContexte complémentaire:\n- ".implode("\n- ", $contextLines)."\n";

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
        if (str_contains($desc, 'plomb') || str_contains($desc, 'eau')) {
            $category = 'Plomberie';
        } elseif (str_contains($desc, 'electr') || str_contains($desc, 'courant')) {
            $category = 'Électricité';
        } elseif (str_contains($desc, 'peint') || str_contains($desc, 'mur')) {
            $category = 'Peinture';
        }

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
    public function suggestDevis(Mission $mission): array
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
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ])->withOptions([
                'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
            ])->post($this->getEndpointUrl(), [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                ],
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
            if ($days < 1) {
                $days = 3;
            }

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
    private function getFallbackDevisSuggestion(Mission $mission): array
    {
        $desc = strtolower($mission->description);

        if (str_contains($desc, 'plomb') || str_contains($desc, 'eau') || str_contains($desc, 'tuyau')) {
            $lignes = [
                ['type' => 'mat', 'description' => 'Achat de fournitures plomberie (tuyaux PVC, colle, raccords, joint)', 'montant' => 25000, 'source' => 'custom'],
                ['type' => 'mo', 'description' => 'Main d\'œuvre plomberie : installation et raccordements de base', 'montant' => 15000, 'source' => 'custom'],
            ];
        } elseif (str_contains($desc, 'electr') || str_contains($desc, 'courant') || str_contains($desc, 'ampoule')) {
            $lignes = [
                ['type' => 'mat', 'description' => 'Câbles électriques, disjoncteurs et prises de rechange', 'montant' => 30000, 'source' => 'custom'],
                ['type' => 'mo', 'description' => 'Recherche de panne et réfection du câblage défaillant', 'montant' => 20000, 'source' => 'custom'],
            ];
        } elseif (str_contains($desc, 'peint') || str_contains($desc, 'mur') || str_contains($desc, 'enduit')) {
            $lignes = [
                ['type' => 'mat', 'description' => 'Pots de peinture blanc mat (30L) et pinceaux/rouleaux', 'montant' => 45000, 'source' => 'custom'],
                ['type' => 'mo', 'description' => 'Préparation des supports et application de deux couches de peinture', 'montant' => 25000, 'source' => 'custom'],
            ];
        } elseif (str_contains($desc, 'macon') || str_contains($desc, 'ciment') || str_contains($desc, 'fer') || str_contains($desc, 'beton') || str_contains($desc, 'coffrage')) {
            $lignes = [
                ['type' => 'mat', 'description' => 'Fournitures maçonnerie (fers de 12, sacs de ciment CPJ 42.5, fil recuit, pointes)', 'montant' => 40000, 'source' => 'custom'],
                ['type' => 'mo', 'description' => 'Façonnage ferraillage, coffrage et coulage béton', 'montant' => 30000, 'source' => 'custom'],
            ];
        } else {
            $lignes = [
                ['type' => 'mat', 'description' => 'Matériaux et outillages consommables nécessaires aux travaux', 'montant' => 35000, 'source' => 'custom'],
                ['type' => 'mo', 'description' => 'Prestation de main d\'œuvre pour l\'exécution des travaux demandés', 'montant' => 25000, 'source' => 'custom'],
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
            ],
        ];

        return [
            'lignes' => $lignes,
            'jalons' => $jalons,
        ];
    }

    /**
     * Analyse un fichier média (photo/vidéo) pour repérer les contacts, adresses, localisations.
     */
    public function analyzeMediaForSensitiveData(UploadedFile $file): array
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

            if (! $isImage && ! $isVideo) {
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
                'User-Agent' => 'Mozilla/5.0',
            ])->withOptions([
                'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
            ])->post($this->getEndpointUrl(), [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'inlineData' => [
                                    'mimeType' => $mimeType,
                                    'data' => $base64Data,
                                ],
                            ],
                            [
                                'text' => $prompt,
                            ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                ],
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
     * Transcrit la note vocale (≤ 20 s) jointe à une candidature de recrutement
     * et signale toute coordonnée : le numéro de l'artisan n'est jamais
     * transmis au recruteur, la note ne doit pas servir à le contourner.
     *
     * Renvoie null si le service est indisponible (clé absente, erreur) :
     * aucune transcription n'est inventée (Règle d'or 29).
     *
     * @return array{transcription: string, contains_contact_details: bool}|null
     */
    public function transcribeRecruitmentVoiceNote(string $audioBase64, string $mimeType, ?int $userId = null): ?array
    {
        if (config('app.env') === 'testing') {
            // Simulation déterministe : un audio dont le contenu mentionne
            // « contact » est traité comme contenant des coordonnées.
            $flagged = str_contains(base64_decode($audioBase64), 'contact');

            return [
                'transcription' => 'Transcription simulée (tests) : maçon, dix ans d\'expérience, disponible dès lundi.',
                'contains_contact_details' => $flagged,
            ];
        }

        if (empty($this->apiKey)) {
            return null;
        }

        $startTime = microtime(true);
        $prompt = "Tu reçois la note vocale (20 secondes au plus) d'un artisan du bâtiment ivoirien qui postule à une offre d'emploi.
Comprends le français, le langage familier ivoirien, le nouchi et le vocabulaire de chantier.

1. Transcris fidèlement ce qu'il dit dans \"transcription\", en français correct.
2. Mets \"contains_contact_details\" à true s'il communique un moyen de le joindre ou de le trouver en dehors de la plateforme :
   numéro de téléphone (même épelé ou découpé), adresse e-mail, compte WhatsApp / Facebook / réseau social, adresse précise.

Retourne obligatoirement ce format JSON uniquement:
{
  \"transcription\": \"...\",
  \"contains_contact_details\": true|false
}";

        try {
            $response = Http::timeout(25)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]])
                ->post($this->getEndpointUrl(), [
                    'contents' => [[
                        'parts' => [
                            ['inlineData' => ['mimeType' => $mimeType, 'data' => $audioBase64]],
                            ['text' => $prompt],
                        ],
                    ]],
                    'generationConfig' => ['response_mime_type' => 'application/json'],
                ]);

            $responseTimeMs = (microtime(true) - $startTime) * 1000;

            if ($response->successful()) {
                $rawJson = $response->json();
                $result = json_decode($rawJson['candidates'][0]['content']['parts'][0]['text'] ?? '', true);
                $usage = $rawJson['usageMetadata'] ?? [];

                AiMonitoringService::log(
                    $this->model,
                    'recruitment_voice_note',
                    (int) ($usage['promptTokenCount'] ?? 0),
                    (int) ($usage['candidatesTokenCount'] ?? 0),
                    $responseTimeMs,
                    200,
                    null,
                    $userId,
                );

                if (is_array($result) && isset($result['transcription'])) {
                    return [
                        'transcription' => trim((string) $result['transcription']),
                        'contains_contact_details' => (bool) ($result['contains_contact_details'] ?? false),
                    ];
                }

                return null;
            }

            AiMonitoringService::log($this->model, 'recruitment_voice_note', 0, 0, $responseTimeMs, $response->status(), $response->body(), $userId);
            Log::error('Gemini Recruitment Voice Note API Error', ['status' => $response->status()]);
        } catch (\Exception $e) {
            AiMonitoringService::log($this->model, 'recruitment_voice_note', 0, 0, (microtime(true) - $startTime) * 1000, 500, $e->getMessage(), $userId);
            Log::error('Gemini Recruitment Voice Note Exception', ['message' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Construit le prompt système pour la transcription et structuration d'un devis dicté en audio ou texte.
     * Intègre le glossaire BTP ivoirien, le parler nouchi, les unités courantes et les règles financières FCFA.
     */
    public function buildVoiceQuotePrompt(Mission $mission, ?string $context = null): string
    {
        return "Tu es un métreur et expert BTP expérimenté en Côte d'Ivoire (Abidjan et villes de l'intérieur).
Un artisan du bâtiment ivoirien t'envoie un enregistrement vocal ou une dictée décrivant son devis (fournitures, outillage, main d'œuvre, délais).

Comprends parfaitement le français ivoirien, le nouchi (argot populaire ivoirien) et le jargon technique des chantiers en Côte d'Ivoire.

Dictionnaires et repères contextuels ivoiriens :
1. Pannes & Expressions de chantier :
   - « ya dra » / « y a drap » : il y a un problème / une panne à régler
   - « ça fait masse » : court-circuit électrique ou défaut d'isolement
   - « l'eau coule sous la dalle / sous l'évier » : fuite d'eau sanitaire
   - « tuyau percé / tuyau bouché / siphon gâté » : plomberie défaillante
   - « frotter » : enduire, appliquer un crépi ou peindre un mur
   - « sciencer » : réfléchir, calculer le coût, trouver la solution technique
   - « pia » / « djinzin » / « gbô » : argent, coût en FCFA
   - « djassa » / « gnan » : quincaillerie, marché de matériaux

2. Matériaux, outillages et unités courantes en Côte d'Ivoire :
   - Maçonnerie & Gros œuvre :
     * Fers à béton : « fer de 6 », « fer de 8 », « fer de 10 », « fer de 12 » (en barres de 12m)
     * Ciment : « ciment CPJ 32.5 » ou « CPJ 42.5 » (ex: Bélier, Dangote, Ivoire Ciment, vendu au sac de 50kg)
     * Granulats : sable lagunaire, sable de carrière, gravier 5/15 ou 15/25 (en voyages ou mètres cubes)
     * Bois & fixations : chevrons, bastaings, contreplaqué marine/ordinaire, « paquet de pointes 60, 70 ou 80 », fil de fer recuit
   - Plomberie :
     * Tuyauterie : tuyau pression PN10/PN16, tube PVC évacuation diamètres 32, 40, 50, 100
     * Raccords : coudes 90°/45°, tés, manchons, réductions, vannes d'arrêt 1/2 ou 3/4
     * Consommables : colle PVC Tangit, ruban téflon, filasse, pâte à joint
     * Sanitaires : siphon lavabo/évier, mécanisme WC complet, flexible sanitaire
   - Électricité :
     * Câblage : câble TH 1.5mm² (éclairage), 2.5mm² (prises), 4mm² ou 6mm² (climatisation)
     * Protections : disjoncteur différentiel 30mA, disjoncteurs divisionnaires 10A/16A/20A/32A
     * Accessoires : gaine orange ICTA, boîte de dérivation, dominos, interrupteurs va-et-vient, réglettes LED
   - Peinture & Finitions :
     * Peinture : seau 15L ou 30L peinture à l'eau (mat ou satin), pot de peinture à l'huile (glycéro)
     * Préparation : pot d'enduit de rebouchage/lissage, rouleau anti-goutte, pinceau à rechampir, diluant

Contexte de la mission :
- Titre / besoin client : \"{$mission->description}\"
".($context ? "- Contexte additionnel : \"{$context}\"\n" : '')."

Ta tâche :
1. Transcrire fidèlement ce que dit l'artisan dans le champ \"transcription\" (si audio fourni).
2. Extraire et catégoriser les lignes de devis (lignes) :
   - \"type\": \"mo\" (main d'œuvre) ou \"mat\" (matériaux / fournitures)
   - \"description\": formulation claire et professionnelle en français (ex: \"Achat de 4 barres de fer de 12 et 2 sacs de ciment Bélier\", \"Pose et raccordement plomberie sous évier\")
   - \"montant\": entier en FCFA (sans décimale, format BIGINT). Calcule la somme si quantités et prix unitaires sont mentionnés.
3. Proposer un découpage logique et équilibré en jalons de paiement (jalons) :
   - \"ordre\": 1, 2, ...
   - \"description\": livrable ou étape correspondante (ex: \"Acompte et approvisionnement des fournitures\", \"Exécution des travaux et finitions\")
   - \"montant\": entier en FCFA
   - \"date_cible_days\": délai estimé en jours (généralement 1 à 5 jours)

RÈGLES CRITIQUES :
- Le montant total des lignes (somme de lignes.*.montant) DOIT être STRICTEMENT ÉGAL au montant total des jalons (somme de jalons.*.montant).
- Aucun numéro de téléphone ni coordonnée personnelle dans les descriptions.
- Tous les montants financiers sont en FCFA entiers.

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
    }

    /**
     * Analyse une dictée textuelle d'un artisan pour générer les lignes et jalons de devis.
     */
    public function parseVoiceQuoteTranscript(
        Mission $mission,
        string $transcript,
        ?int $userId = null
    ): array {
        if (empty($this->apiKey) || config('app.env') === 'testing') {
            $fallback = $this->getFallbackDevisSuggestion($mission);
            $fallback['transcription'] = $transcript;

            return $fallback;
        }

        $startTime = microtime(true);

        try {
            $prompt = $this->buildVoiceQuotePrompt($mission, "Dictée artisan : {$transcript}");

            $response = Http::timeout(25)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]])
                ->post($this->getEndpointUrl(), [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'response_mime_type' => 'application/json',
                    ],
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
                    'voice_quote_text',
                    $promptTokens,
                    $completionTokens,
                    $responseTimeMs,
                    200,
                    null,
                    $userId
                );

                if (isset($result['lignes']) && isset($result['jalons'])) {
                    $balanced = $this->formatAndBalanceSuggestion($result);
                    $balanced['transcription'] = $transcript;

                    return $balanced;
                }
            }

            AiMonitoringService::log(
                $this->model,
                'voice_quote_text',
                0,
                0,
                $responseTimeMs,
                $response->status(),
                $response->body(),
                $userId
            );
        } catch (\Exception $e) {
            $responseTimeMs = (microtime(true) - $startTime) * 1000;
            AiMonitoringService::log(
                $this->model,
                'voice_quote_text',
                0,
                0,
                $responseTimeMs,
                500,
                $e->getMessage(),
                $userId
            );
        }

        $fallback = $this->getFallbackDevisSuggestion($mission);
        $fallback['transcription'] = $transcript;

        return $fallback;
    }

    /**
     * Analyse un enregistrement audio dicté par un artisan pour générer les lignes et jalons de devis.
     * Supporte les accents ivoiriens, le nouchi et le vocabulaire BTP local.
     */
    public function parseVoiceQuote(
        Mission $mission,
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
            $prompt = $this->buildVoiceQuotePrompt($mission);

            $response = Http::timeout(25)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0',
                ])->withOptions([
                    'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
                ])->post($this->getEndpointUrl(), [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'inlineData' => [
                                        'mimeType' => $mimeType,
                                        'data' => $audioBase64,
                                    ],
                                ],
                                [
                                    'text' => $prompt,
                                ],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'response_mime_type' => 'application/json',
                    ],
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
        $fallback['transcription'] = 'Transcription approximative : besoin évalué à partir de la description initiale du chantier.';

        return $fallback;
    }

    /**
     * Analyse la conformité visuelle des photos de preuves d'un jalon (Gemini Multimodal).
     */
    public function analyzeMilestoneVision(Jalon $jalon, array $photos = []): array
    {
        $photosList = ! empty($photos) ? $photos : ($jalon->photos_json ?? []);

        if (empty($photosList)) {
            return [
                'approved' => true,
                'conformity_score' => 100,
                'detected_elements' => ['non_applicable'],
                'anomalies' => [],
                'summary' => 'Aucune photo soumise pour analyse visuelle.',
                'confidence' => 'medium',
                'analyzed_at' => now()->toIso8601String(),
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
                    'approved' => false,
                    'conformity_score' => 25,
                    'detected_elements' => ['surface_vide', 'element_inconnu'],
                    'anomalies' => ['incohérence_visuelle_majeure', 'absence_d_ouvrage_conforme'],
                    'summary' => "Incohérence visuelle détectée : la photo fournie ne correspond pas aux travaux spécifiés pour '{$jalon->description}'.",
                    'confidence' => 'high',
                    'analyzed_at' => now()->toIso8601String(),
                ];
            }

            return [
                'approved' => true,
                'conformity_score' => 92,
                'detected_elements' => ['materiaux_conformes', 'travaux_executes', 'outillage_chantier'],
                'anomalies' => [],
                'summary' => "Preuve visuelle conforme : réalisation cohérente avec l'intitulé '{$jalon->description}'.",
                'confidence' => 'high',
                'analyzed_at' => now()->toIso8601String(),
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
        $prompt .= '}';

        $parts[] = ['text' => $prompt];

        // Charger jusqu'à 3 images
        $imageCount = 0;
        foreach (array_slice($photosList, 0, 3) as $photo) {
            $path = is_array($photo) ? ($photo['path'] ?? null) : null;
            if (! $path && is_array($photo) && isset($photo['url'])) {
                $path = str_replace('/storage/', '', parse_url($photo['url'], PHP_URL_PATH) ?? '');
            }

            if ($path && Storage::disk('public')->exists($path)) {
                $raw = Storage::disk('public')->get($path);
                /** @var FilesystemAdapter $disk */
                $disk = Storage::disk('public');
                $mime = $disk->mimeType($path) ?? 'image/jpeg';
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $mime,
                        'data' => base64_encode($raw),
                    ],
                ];
                $imageCount++;
            }
        }

        if ($imageCount === 0) {
            return [
                'approved' => true,
                'conformity_score' => 85,
                'detected_elements' => ['non_verifiable_localement'],
                'anomalies' => [],
                'summary' => 'Photos stockées sur un support distant non directement accessible en local pour vision API.',
                'confidence' => 'low',
                'analyzed_at' => now()->toIso8601String(),
            ];
        }

        $startTime = microtime(true);
        $userId = $jalon->mission?->artisan_id;

        try {
            $response = Http::timeout(12)
                ->connectTimeout(5)
                ->post($this->getEndpointUrl(), [
                    'contents' => [
                        ['parts' => $parts],
                    ],
                    'generationConfig' => [
                        'response_mime_type' => 'application/json',
                    ],
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
                        'approved' => (bool) ($result['approved'] ?? ($score >= 50)),
                        'conformity_score' => $score,
                        'detected_elements' => (array) ($result['detected_elements'] ?? []),
                        'anomalies' => (array) ($result['anomalies'] ?? []),
                        'summary' => (string) ($result['summary'] ?? 'Analyse visuelle complétée.'),
                        'confidence' => (string) ($result['confidence'] ?? 'high'),
                        'analyzed_at' => now()->toIso8601String(),
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
            'approved' => true,
            'conformity_score' => 75,
            'detected_elements' => ['inspection_secours'],
            'anomalies' => [],
            'summary' => 'Approbation par défaut suite à une indisponibilité temporaire du service de vision.',
            'confidence' => 'low',
            'analyzed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Analyse multimodale (photos/vidéos + texte) pour le pré-diagnostic de panne ou travaux BTP.
     *
     * @param  array<int, UploadedFile|string>  $mediaFiles
     * @return array{
     *     diagnostic_summary: string,
     *     severity: string,
     *     recommended_trade: string,
     *     recommended_intervention_type: string,
     *     key_visual_clues: array<int, string>,
     *     urgency_precautions: array<int, string>,
     *     estimated_materials: array<int, array{name: string, estimated_price: int}>,
     *     pricing: array{
     *         materials_min: int,
     *         materials_max: int,
     *         labor_min: int,
     *         labor_max: int,
     *         total_min: int,
     *         total_max: int
     *     }
     * }
     */
    public function analyzePreDiagnostic(
        array $mediaFiles = [],
        string $description = '',
        ?string $categoryHint = null,
        ?int $userId = null
    ): array {
        if (empty($this->apiKey) || config('app.env') === 'testing' || (empty($mediaFiles) && empty($description))) {
            return $this->getFallbackPreDiagnostic($description, $categoryHint);
        }

        $parts = [];
        $prompt = "Tu es un ingénieur expert du bâtiment et des travaux publics en Côte d'Ivoire.\n";
        $prompt .= "Un client demande un diagnostic technique pour un sinistre, une panne ou des travaux de rénovation.\n";
        if (! empty($description)) {
            $prompt .= "Description fournie par le client : \"{$description}\".\n";
        }
        if (! empty($categoryHint)) {
            $prompt .= "Indication de domaine : \"{$categoryHint}\".\n";
        }
        $prompt .= "Examine attentivement les éléments visuels joints (photos et/ou vidéo) ainsi que le texte.\n";
        $prompt .= "Tu dois identifier :\n";
        $prompt .= "1. La nature exacte du problème ou de l'ouvrage (diagnostic synthétique clair en français).\n";
        $prompt .= "2. La sévérité ('faible', 'moyen', 'eleve', 'urgent').\n";
        $prompt .= "3. Le métier d'artisan requis (ex: Plomberie, Électricité, Maçonnerie, Peinture, Climatisation, Menuiserie, Serrurerie, Étanchéité).\n";
        $prompt .= "4. Le type d'intervention conseillé ('Dépannage', 'Maintenance', 'Assistance', 'Déplacement / Diagnostic').\n";
        $prompt .= "5. Les indices visuels clés observés sur l'image/vidéo.\n";
        $prompt .= "6. Les précautions d'urgence immédiates pour la sécurité du client.\n";
        $prompt .= "7. La liste des matériaux et pièces de rechange probables avec prix estimatifs du marché abidjanais en FCFA entiers.\n";
        $prompt .= "8. La fourchette budgétaire réaliste en FCFA entiers (matériaux, main d'œuvre et total).\n\n";
        $prompt .= "Réponds STRICTEMENT par un objet JSON au format suivant (sans texte additionnel) :\n";
        $prompt .= "{\n";
        $prompt .= "  \"diagnostic_summary\": \"Synthèse claire du problème détecté\",\n";
        $prompt .= "  \"severity\": \"moyen\",\n";
        $prompt .= "  \"recommended_trade\": \"Plomberie\",\n";
        $prompt .= "  \"recommended_intervention_type\": \"Dépannage\",\n";
        $prompt .= "  \"key_visual_clues\": [\"Trace d'infiltration\", \"Raccord dévissé\"],\n";
        $prompt .= "  \"urgency_precautions\": [\"Couper l'arrivée d'eau principale\"],\n";
        $prompt .= "  \"estimated_materials\": [\n";
        $prompt .= "    {\"name\": \"Joint d'étanchéité\", \"estimated_price\": 2500}\n";
        $prompt .= "  ],\n";
        $prompt .= "  \"pricing\": {\n";
        $prompt .= "    \"materials_min\": 5000,\n";
        $prompt .= "    \"materials_max\": 15000,\n";
        $prompt .= "    \"labor_min\": 10000,\n";
        $prompt .= "    \"labor_max\": 25000,\n";
        $prompt .= "    \"total_min\": 15000,\n";
        $prompt .= "    \"total_max\": 40000\n";
        $prompt .= "  }\n";
        $prompt .= '}';

        $parts[] = ['text' => $prompt];

        // Charger jusqu'à 3 images ou 1 vidéo
        $loadedMedia = 0;
        foreach (array_slice($mediaFiles, 0, 3) as $media) {
            $mimeType = null;
            $dataBase64 = null;

            if ($media instanceof UploadedFile) {
                $mimeType = $media->getMimeType() ?: 'image/jpeg';
                $dataBase64 = base64_encode(file_get_contents($media->getPathname()));
            } elseif (is_string($media)) {
                $path = $media;
                if (Storage::disk('public')->exists($path)) {
                    /** @var FilesystemAdapter $disk */
                    $disk = Storage::disk('public');
                    $mimeType = $disk->mimeType($path) ?: 'image/jpeg';
                    $dataBase64 = base64_encode($disk->get($path));
                } elseif (file_exists($path)) {
                    $mimeType = mime_content_type($path) ?: 'image/jpeg';
                    $dataBase64 = base64_encode(file_get_contents($path));
                }
            }

            if ($mimeType && $dataBase64) {
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $mimeType,
                        'data' => $dataBase64,
                    ],
                ];
                $loadedMedia++;
            }
        }

        $startTime = microtime(true);

        try {
            $response = Http::timeout(15)
                ->connectTimeout(5)
                ->post($this->getEndpointUrl(), [
                    'contents' => [
                        ['parts' => $parts],
                    ],
                    'generationConfig' => [
                        'response_mime_type' => 'application/json',
                    ],
                ]);

            $responseTimeMs = (microtime(true) - $startTime) * 1000;

            if ($response->successful()) {
                $jsonText = $response->json('candidates.0.content.parts.0.text') ?? '{}';
                $result = json_decode($jsonText, true);

                $promptTokens = $response->json('usageMetadata.promptTokenCount') ?? 0;
                $completionTokens = $response->json('usageMetadata.candidatesTokenCount') ?? 0;

                AiMonitoringService::log(
                    $this->model,
                    'pre_diagnostic',
                    $promptTokens,
                    $completionTokens,
                    $responseTimeMs,
                    200,
                    null,
                    $userId
                );

                if (is_array($result) && ! empty($result['diagnostic_summary'])) {
                    return $this->normalizePreDiagnosticResult($result);
                }
            }

            AiMonitoringService::log(
                $this->model,
                'pre_diagnostic',
                0,
                0,
                $responseTimeMs,
                $response->status(),
                $response->body(),
                $userId
            );

            Log::error('Gemini Pre-Diagnostic Error', ['status' => $response->status(), 'body' => $response->body()]);
        } catch (\Throwable $e) {
            $responseTimeMs = (microtime(true) - $startTime) * 1000;
            AiMonitoringService::log(
                $this->model,
                'pre_diagnostic',
                0,
                0,
                $responseTimeMs,
                500,
                $e->getMessage(),
                $userId
            );
            Log::error('Gemini Pre-Diagnostic Exception', ['message' => $e->getMessage()]);
        }

        return $this->getFallbackPreDiagnostic($description, $categoryHint);
    }

    /**
     * Normalise et assainit le résultat JSON retourné par Gemini.
     */
    private function normalizePreDiagnosticResult(array $result): array
    {
        $severity = match (strtolower((string) ($result['severity'] ?? 'moyen'))) {
            'faible', 'low' => 'faible',
            'urgent', 'high', 'eleve', 'élevé', 'critique' => 'urgent',
            'moyen', 'medium' => 'moyen',
            default => 'moyen',
        };

        $materials = [];
        if (! empty($result['estimated_materials']) && is_array($result['estimated_materials'])) {
            foreach ($result['estimated_materials'] as $item) {
                if (is_array($item) && ! empty($item['name'])) {
                    $materials[] = [
                        'name' => (string) $item['name'],
                        'estimated_price' => max(0, (int) ($item['estimated_price'] ?? 0)),
                    ];
                }
            }
        }

        $p = $result['pricing'] ?? [];
        $matMin = max(0, (int) ($p['materials_min'] ?? 5000));
        $matMax = max($matMin, (int) ($p['materials_max'] ?? ($matMin * 2)));
        $labMin = max(0, (int) ($p['labor_min'] ?? 10000));
        $labMax = max($labMin, (int) ($p['labor_max'] ?? ($labMin * 2)));
        $totMin = max($matMin + $labMin, (int) ($p['total_min'] ?? ($matMin + $labMin)));
        $totMax = max($totMin, (int) ($p['total_max'] ?? ($matMax + $labMax)));

        return [
            'diagnostic_summary' => (string) ($result['diagnostic_summary'] ?? 'Diagnostic technique visuel complété.'),
            'severity' => $severity,
            'recommended_trade' => (string) ($result['recommended_trade'] ?? 'Plomberie'),
            'recommended_intervention_type' => (string) ($result['recommended_intervention_type'] ?? 'Dépannage'),
            'key_visual_clues' => array_values(array_map('strval', (array) ($result['key_visual_clues'] ?? []))),
            'urgency_precautions' => array_values(array_map('strval', (array) ($result['urgency_precautions'] ?? []))),
            'estimated_materials' => $materials,
            'pricing' => [
                'materials_min' => $matMin,
                'materials_max' => $matMax,
                'labor_min' => $labMin,
                'labor_max' => $labMax,
                'total_min' => $totMin,
                'total_max' => $totMax,
            ],
            'analyzed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Fallback déterministe hors-ligne ou environnement de test.
     */
    public function getFallbackPreDiagnostic(string $description = '', ?string $categoryHint = null): array
    {
        $text = strtolower($description.' '.$categoryHint);

        $trade = 'Plomberie';
        $interventionType = 'Dépannage';
        $summary = 'Examen technique requis sur place pour établir un chiffrage précis.';
        $severity = 'moyen';
        $materials = [];
        $precautions = [];
        $clues = ['Examen visuel préliminaire'];
        $totMin = 15000;
        $totMax = 45000;

        if (str_contains($text, 'fuite') || str_contains($text, 'eau') || str_contains($text, 'tuyau') || str_contains($text, 'robinet') || str_contains($text, 'wc')) {
            $trade = 'Plomberie';
            $summary = 'Fuite ou dysfonctionnement sur le réseau hydraulique / sanitaire.';
            $severity = str_contains($text, 'inond') || str_contains($text, 'urgent') ? 'urgent' : 'moyen';
            $precautions = ['Fermer la vanne d\'arrêt générale du domicile pour limiter les dégâts des eaux.'];
            $clues = ['Écoulement visible', 'Présence d\'eau stagnante'];
            $materials = [
                ['name' => 'Joint d\'étanchéité & téflon', 'estimated_price' => 2000],
                ['name' => 'Raccord ou flexible sanitaire', 'estimated_price' => 6000],
            ];
            $totMin = 15000;
            $totMax = 35000;
        } elseif (str_contains($text, 'disjoncteur') || str_contains($text, 'courant') || str_contains($text, 'prise') || str_contains($text, 'electr') || str_contains($text, 'court-circuit')) {
            $trade = 'Électricité';
            $summary = 'Anomalie électrique, défaut d\'isolement ou disjonction répétée.';
            $severity = 'urgent';
            $precautions = ['Couper le disjoncteur général et ne pas toucher aux fils dénudés.'];
            $clues = ['Rupture de continuité électrique', 'Traces d\'échauffement ou étincelles'];
            $materials = [
                ['name' => 'Disjoncteur divisionnaire 16A/20A', 'estimated_price' => 8500],
                ['name' => 'Câbles cuivre VGV & dominos', 'estimated_price' => 4500],
            ];
            $totMin = 20000;
            $totMax = 50000;
        } elseif (str_contains($text, 'clim') || str_contains($text, 'froid') || str_contains($text, 'gaz')) {
            $trade = 'Climatisation';
            $summary = 'Baisse de rendement thermique, encrassement ou fuite de fluide frigorigène.';
            $severity = 'moyen';
            $precautions = ['Éteindre le split pour préserver le compresseur extérieur.'];
            $clues = ['Manque d\'air froid', 'Givre sur circuit cuivre'];
            $materials = [
                ['name' => 'Recharge fluide frigorigène R410A', 'estimated_price' => 25000],
                ['name' => 'Nettoyage filtres et désinfection', 'estimated_price' => 5000],
            ];
            $totMin = 30000;
            $totMax = 65000;
        } elseif (str_contains($text, 'mur') || str_contains($text, 'fissure') || str_contains($text, 'ciment') || str_contains($text, 'dalle')) {
            $trade = 'Maçonnerie';
            $summary = 'Fissuration de maçonnerie ou dégradation de support cimentaire.';
            $severity = 'moyen';
            $precautions = ['Éviter de surcharger la zone concernée.'];
            $clues = ['Fissure apparente', 'Écaillage de crépi'];
            $materials = [
                ['name' => 'Sac de ciment CPJ 42.5', 'estimated_price' => 5500],
                ['name' => 'Sable fin et adjuvant d\'adhérence', 'estimated_price' => 6000],
            ];
            $totMin = 25000;
            $totMax = 60000;
        }

        $matMin = (int) round($totMin * 0.4);
        $matMax = (int) round($totMax * 0.45);
        $labMin = $totMin - $matMin;
        $labMax = $totMax - $matMax;

        return [
            'diagnostic_summary' => $summary,
            'severity' => $severity,
            'recommended_trade' => $trade,
            'recommended_intervention_type' => $interventionType,
            'key_visual_clues' => $clues,
            'urgency_precautions' => $precautions,
            'estimated_materials' => $materials,
            'pricing' => [
                'materials_min' => $matMin,
                'materials_max' => $matMax,
                'labor_min' => $labMin,
                'labor_max' => $labMax,
                'total_min' => $totMin,
                'total_max' => $totMax,
            ],
            'analyzed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Analyse une pièce d'identité (CNI, passeport, attestation ONECI) par Gemini Vision :
     * extraction OCR et contrôle d'intégrité.
     *
     * Échec fermé : sans clé configurée, sur erreur HTTP ou réponse illisible,
     * le résultat porte `analysis_available = false` et des scores nuls. Aucune
     * valeur n'est inventée (Règle d'or 29) — un repli « optimiste » activerait
     * des comptes sans qu'aucune pièce n'ait été examinée.
     *
     * @return array{
     *     analysis_available: bool,
     *     document_type: ?string,
     *     document_number: ?string,
     *     last_name: ?string,
     *     first_name: ?string,
     *     birth_date: ?string,
     *     expiry_date: ?string,
     *     is_expired: bool,
     *     is_legible: bool,
     *     has_photo: bool,
     *     is_tampered: bool,
     *     quality_score: ?int,
     *     anomalies: array<int, string>,
     *     summary: string
     * }
     */
    public function extractCniData(string $imageBytes, string $mimeType = 'image/jpeg', ?int $userId = null): array
    {
        $prompt = "Tu es un inspecteur spécialisé dans la vérification de pièces d'identité officielles en Côte d'Ivoire (CNI, passeport, attestation d'identité de l'ONECI).\n"
            ."Examine attentivement l'image de la pièce d'identité fournie et effectue les contrôles suivants :\n"
            ."1. Type de document : 'cni', 'passeport', 'attestation' ou 'autre'.\n"
            ."2. Champs lisibles : numéro de document, nom de famille, prénom(s), date de naissance (AAAA-MM-JJ), date d'expiration (AAAA-MM-JJ). Laisse null tout champ illisible, n'invente rien.\n"
            .'3. Document expiré par rapport au '.now()->toDateString()." (is_expired).\n"
            ."4. Netteté et lisibilité (is_legible).\n"
            ."5. Présence d'une photo d'identité nette (has_photo).\n"
            ."6. Altération, montage, photo d'écran ou photocopie (is_tampered).\n"
            ."7. Score global de qualité de 0 à 100 (quality_score).\n\n"
            ."Réponds uniquement par un objet JSON strict :\n"
            .'{"document_type": "cni", "document_number": "CI0123456789", "last_name": "NOM", "first_name": "Prénoms", '
            .'"birth_date": "1990-05-15", "expiry_date": "2030-01-01", "is_expired": false, "is_legible": true, '
            .'"has_photo": true, "is_tampered": false, "quality_score": 90, "anomalies": [], "summary": "Synthèse en français"}';

        $result = $this->callKycVision([[$imageBytes, $mimeType]], $prompt, 'kyc_ocr_cni', 15, $userId);

        if ($result === null) {
            return $this->unavailableCniAnalysis();
        }

        $expiryDate = $this->kycDateOrNull($result['expiry_date'] ?? null);

        return [
            'analysis_available' => true,
            'document_type' => $this->kycStringOrNull($result['document_type'] ?? null),
            'document_number' => $this->normalizeDocumentNumber($result['document_number'] ?? null),
            'last_name' => $this->kycStringOrNull($result['last_name'] ?? null),
            'first_name' => $this->kycStringOrNull($result['first_name'] ?? null),
            'birth_date' => $this->kycDateOrNull($result['birth_date'] ?? null),
            'expiry_date' => $expiryDate,
            // La date d'expiration lue fait foi : on ne s'en remet pas au seul
            // jugement du modèle sur la date du jour.
            'is_expired' => ($result['is_expired'] ?? false) === true
                || ($expiryDate !== null && $expiryDate < now()->toDateString()),
            'is_legible' => ($result['is_legible'] ?? false) === true,
            'has_photo' => ($result['has_photo'] ?? false) === true,
            'is_tampered' => ($result['is_tampered'] ?? true) !== false,
            'quality_score' => $this->kycScore($result['quality_score'] ?? null) ?? 0,
            'anomalies' => $this->kycStringList($result['anomalies'] ?? []),
            'summary' => $this->kycStringOrNull($result['summary'] ?? null) ?? 'Analyse de la pièce d\'identité effectuée.',
        ];
    }

    /**
     * Compare le portrait de la pièce d'identité au selfie (biométrie faciale)
     * et contrôle la vivacité du selfie (photo d'écran, tirage papier, masque).
     *
     * Échec fermé, comme `extractCniData` : aucune concordance n'est présumée.
     *
     * @return array{
     *     analysis_available: bool,
     *     face_matched: bool,
     *     similarity_score: ?int,
     *     liveness_detected: bool,
     *     liveness_score: ?int,
     *     overall_confidence_score: ?int,
     *     anomalies: array<int, string>,
     *     summary: string
     * }
     */
    public function verifyFaceAndLiveness(
        string $cniBytes,
        string $cniMime,
        string $selfieBytes,
        string $selfieMime,
        ?int $userId = null
    ): array {
        $prompt = "Tu es un expert biométrique spécialisé dans la reconnaissance faciale, la lutte contre l'usurpation d'identité et la détection de vivacité.\n"
            ."Image 1 : pièce d'identité officielle portant le portrait de son titulaire.\n"
            ."Image 2 : selfie pris par l'utilisateur lors de son inscription sur ProsArtisan.\n\n"
            ."1. Détermine si les deux visages appartiennent à la même personne (face_matched).\n"
            ."2. Score de similarité faciale de 0 à 100 (similarity_score).\n"
            ."3. Vérifie que le selfie montre un visage réel et vivant, et non la photo d'un écran, un tirage papier, un masque ou un visage généré (liveness_detected, liveness_score de 0 à 100).\n"
            ."4. Score de confiance global de 0 à 100 (overall_confidence_score).\n"
            ."5. Liste les anomalies (ex. 'visage_partiellement_masque', 'eclairage_insuffisant', 'photo_d_un_ecran').\n\n"
            ."Réponds uniquement par un objet JSON strict :\n"
            .'{"face_matched": true, "similarity_score": 95, "liveness_detected": true, "liveness_score": 92, '
            .'"overall_confidence_score": 94, "anomalies": [], "summary": "Explication synthétique en français"}';

        $result = $this->callKycVision(
            [[$cniBytes, $cniMime], [$selfieBytes, $selfieMime]],
            $prompt,
            'kyc_facial_match',
            20,
            $userId
        );

        if ($result === null) {
            return [
                'analysis_available' => false,
                'face_matched' => false,
                'similarity_score' => null,
                'liveness_detected' => false,
                'liveness_score' => null,
                'overall_confidence_score' => null,
                'anomalies' => ['analyse_ia_indisponible'],
                'summary' => 'Vérification biométrique automatique indisponible : dossier transmis à la revue humaine.',
            ];
        }

        $similarity = $this->kycScore($result['similarity_score'] ?? null) ?? 0;
        $liveness = $this->kycScore($result['liveness_score'] ?? null) ?? 0;
        // Le score global ne dépasse jamais le plus faible des deux contrôles :
        // un visage concordant mais photographié sur un écran reste suspect.
        $overall = min(
            $this->kycScore($result['overall_confidence_score'] ?? null) ?? min($similarity, $liveness),
            $similarity,
            $liveness,
        );

        return [
            'analysis_available' => true,
            'face_matched' => ($result['face_matched'] ?? false) === true,
            'similarity_score' => $similarity,
            'liveness_detected' => ($result['liveness_detected'] ?? false) === true,
            'liveness_score' => $liveness,
            'overall_confidence_score' => $overall,
            'anomalies' => $this->kycStringList($result['anomalies'] ?? []),
            'summary' => $this->kycStringOrNull($result['summary'] ?? null) ?? 'Comparaison faciale effectuée.',
        ];
    }

    /**
     * Normalise un numéro de pièce pour la détection des doublons entre comptes :
     * majuscules, sans espaces, tirets ni points.
     */
    public function normalizeDocumentNumber(mixed $value): ?string
    {
        $raw = $this->kycStringOrNull($value);
        if ($raw === null) {
            return null;
        }

        $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $raw));

        return $normalized === '' ? null : substr($normalized, 0, 64);
    }

    /**
     * Appel Gemini Vision commun aux contrôles KYC. Renvoie le JSON décodé,
     * ou null si l'analyse n'a pas pu être menée (clé absente, erreur, réponse illisible).
     *
     * @param  array<int, array{0: string, 1: string}>  $images  Couples [octets, type MIME].
     * @return array<string, mixed>|null
     */
    private function callKycVision(array $images, string $prompt, string $action, int $timeout, ?int $userId): ?array
    {
        if ($this->apiKey === '' || $this->apiKey === 'PLACEHOLDER_KEY') {
            return null;
        }

        foreach ($images as [$bytes]) {
            if ($bytes === '') {
                return null;
            }
        }

        $parts = array_map(fn (array $image) => [
            'inline_data' => [
                'mime_type' => $image[1],
                'data' => base64_encode($image[0]),
            ],
        ], $images);
        $parts[] = ['text' => $prompt];

        $startTime = microtime(true);

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout(5)
                ->post($this->getEndpointUrl(), [
                    'contents' => [['parts' => $parts]],
                    'generationConfig' => ['response_mime_type' => 'application/json'],
                ]);

            $responseTimeMs = (microtime(true) - $startTime) * 1000;

            if (! $response->successful()) {
                AiMonitoringService::log($this->model, $action, 0, 0, $responseTimeMs, $response->status(), $response->body(), $userId);
                Log::error('Gemini KYC : réponse en erreur', ['action' => $action, 'status' => $response->status()]);

                return null;
            }

            AiMonitoringService::log(
                $this->model,
                $action,
                (int) ($response->json('usageMetadata.promptTokenCount') ?? 0),
                (int) ($response->json('usageMetadata.candidatesTokenCount') ?? 0),
                $responseTimeMs,
                200,
                null,
                $userId
            );

            $text = trim((string) ($response->json('candidates.0.content.parts.0.text') ?? ''));
            $text = trim((string) preg_replace('/^```(?:json)?|```$/m', '', $text));
            $decoded = json_decode($text, true);

            if (! is_array($decoded)) {
                Log::warning('Gemini KYC : réponse non JSON', ['action' => $action]);

                return null;
            }

            return $decoded;
        } catch (\Throwable $e) {
            $responseTimeMs = (microtime(true) - $startTime) * 1000;
            AiMonitoringService::log($this->model, $action, 0, 0, $responseTimeMs, 500, $e->getMessage(), $userId);
            Log::error('Gemini KYC : exception', ['action' => $action, 'message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailableCniAnalysis(): array
    {
        return [
            'analysis_available' => false,
            'document_type' => null,
            'document_number' => null,
            'last_name' => null,
            'first_name' => null,
            'birth_date' => null,
            'expiry_date' => null,
            'is_expired' => false,
            'is_legible' => false,
            'has_photo' => false,
            'is_tampered' => false,
            'quality_score' => null,
            'anomalies' => ['analyse_ia_indisponible'],
            'summary' => 'Analyse automatique de la pièce indisponible : dossier transmis à la revue humaine.',
        ];
    }

    private function kycStringOrNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' || strtolower($string) === 'null' ? null : mb_substr($string, 0, 255);
    }

    private function kycDateOrNull(mixed $value): ?string
    {
        $string = $this->kycStringOrNull($value);

        return $string !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $string) === 1 ? $string : null;
    }

    private function kycScore(mixed $value): ?int
    {
        return is_numeric($value) ? max(0, min(100, (int) round((float) $value))) : null;
    }

    /**
     * @return array<int, string>
     */
    private function kycStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn ($item) => $this->kycStringOrNull($item), $value),
            fn ($item) => $item !== null,
        ));
    }
}
