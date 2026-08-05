<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use App\Models\StagingItem;
use App\Models\ProductionItem;
use App\Models\ImportHistory;
use App\Models\LlmAttachment;
use App\Models\Profession;
use App\Models\LlmCategory;
use App\Models\LlmContext;

class LlmAdminController extends Controller
{
    public function getStaging()
    {
        return response()->json(StagingItem::orderBy('created_at', 'desc')->get());
    }

    public function getProduction()
    {
        $items = ProductionItem::all();
        $result = $items->map(function ($item) {
            return $item->generated_json;
        });
        return response()->json($result);
    }

    public function getImports()
    {
        return response()->json(ImportHistory::orderBy('imported_at', 'desc')->get());
    }

    public function clearImports()
    {
        ImportHistory::truncate();
        LlmAttachment::truncate();

        // Clean up files in storage
        Storage::disk('public')->deleteDirectory('fileshare');
        Storage::disk('public')->makeDirectory('fileshare');

        return response()->json(['status' => 'success']);
    }

    public function getProfessions()
    {
        return response()->json(Profession::all());
    }

    public function getCategories()
    {
        return response()->json(LlmCategory::all());
    }

    public function getContexts()
    {
        return response()->json(LlmContext::all());
    }

    public function storeImport(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|string',
            'filename' => 'required|string',
            'file_size' => 'required|integer',
            'imported_at' => 'required|string',
            'status' => 'nullable|string',
        ]);

        $import = ImportHistory::create([
            'id' => $validated['id'],
            'filename' => $validated['filename'],
            'file_size' => $validated['file_size'],
            'imported_at' => $validated['imported_at'],
            'status' => $validated['status'] ?? 'PENDING_VLM',
            'vlm_extracted' => false,
            'llm_downscaled' => false,
        ]);

        return response()->json(['status' => 'success', 'id' => $import->id], 201);
    }

    public function updateImport(Request $request, string $id)
    {
        $import = ImportHistory::findOrFail($id);
        $data = $request->only(['status', 'vlm_extracted', 'llm_downscaled']);
        $import->update($data);
        return response()->json(['status' => 'success', 'updated' => $import->id]);
    }

    public function upload(Request $request)
    {
        $validated = $request->validate([
            'filename' => 'required|string',
            'content' => 'required|string', // base64
        ]);

        $fileData = base64_decode($validated['content']);
        $ext = pathinfo($validated['filename'], PATHINFO_EXTENSION);
        $fileId = (string) Str::uuid();
        $safeFilename = $ext ? "{$fileId}.{$ext}" : $fileId;

        // Ensure directories exist
        Storage::disk('public')->makeDirectory('fileshare');

        // Save to public storage/fileshare
        Storage::disk('public')->put("fileshare/{$safeFilename}", $fileData);
        $fileLink = "/storage/fileshare/{$safeFilename}";

        LlmAttachment::create([
            'id' => $fileId,
            'original_filename' => $validated['filename'],
            'extension' => $ext,
            'file_link' => $fileLink,
            'uploaded_by' => Auth::user()?->name ?? 'Admin',
            'created_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'id' => $fileId,
            'file_link' => $fileLink
        ], 201);
    }

    public function storeStaging(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|string',
            'raw_pdf_source' => 'required|string',
            'original_extracted_text' => 'required|string',
            'generated_json' => 'required|array',
            'status' => 'nullable|string',
        ]);

        $staging = StagingItem::create([
            'id' => $validated['id'],
            'raw_pdf_source' => $validated['raw_pdf_source'],
            'original_extracted_text' => $validated['original_extracted_text'],
            'generated_json' => $validated['generated_json'],
            'status' => $validated['status'] ?? 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['status' => 'success', 'id' => $staging->id], 201);
    }

    public function updateStaging(Request $request, string $id)
    {
        $staging = StagingItem::findOrFail($id);
        $staging->update([
            'generated_json' => $request->all(),
            'updated_at' => now(),
        ]);
        return response()->json(['status' => 'success', 'updated' => $staging->id]);
    }

    public function approveStaging(Request $request, string $id)
    {
        $staging = StagingItem::findOrFail($id);
        $staging->update([
            'status' => 'APPROVED',
            'validated_at' => now(),
        ]);

        $generatedJson = $staging->generated_json;
        $tagsList = $generatedJson['metadata']['tags_pathologies'] ?? [];
        $tagsStr = implode(',', $tagsList);

        ProductionItem::updateOrCreate(
            ['id' => $id],
            [
                'generated_json' => $generatedJson,
                'tags' => $tagsStr
            ]
        );

        return response()->json([
            'status' => 'success',
            'promoted' => $id,
            'generated_json' => $generatedJson
        ]);
    }

    public function rejectStaging(Request $request, string $id)
    {
        $staging = StagingItem::findOrFail($id);
        $validated = $request->validate([
            'reviewer_notes' => 'nullable|string',
        ]);

        $staging->update([
            'status' => 'REJECTED',
            'reviewer_notes' => $validated['reviewer_notes'] ?? '',
            'validated_at' => now(),
        ]);

        return response()->json(['status' => 'success', 'rejected' => $id]);
    }

    public function destroyStaging(string $id)
    {
        $staging = StagingItem::findOrFail($id);
        $staging->delete();

        return response()->json(['status' => 'success', 'deleted' => $id]);
    }

    public function search(Request $request)
    {
        $validated = $request->validate([
            'tags' => 'nullable|array',
            'filters' => 'nullable|array',
            'image_b64' => 'nullable|string',
            'image_url' => 'nullable|string',
            'image_name' => 'nullable|string',
        ]);

        $queryTags = $validated['tags'] ?? [];
        $filters = $validated['filters'] ?? [];

        // VLM analysis using real Gemini 1.5 Flash if image is provided
        if (!empty($validated['image_b64']) || !empty($validated['image_url'])) {
            $geminiKey = config('services.gemini.api_key');
            if ($geminiKey) {
                $base64 = '';
                $mimeType = 'image/jpeg';

                if (!empty($validated['image_b64'])) {
                    $base64 = $validated['image_b64'];
                    if (preg_match('/^data:([^;]+);base64,(.*)$/', $base64, $m)) {
                        $mimeType = $m[1];
                        $base64 = $m[2];
                    }
                } else if (!empty($validated['image_url'])) {
                    try {
                        $imgResponse = Http::timeout(10)->get($validated['image_url']);
                        if ($imgResponse->successful()) {
                            $base64 = base64_encode($imgResponse->body());
                            $mimeType = $imgResponse->header('Content-Type') ?? 'image/jpeg';
                        }
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Failed to download image from URL: " . $e->getMessage());
                    }
                }

                if (!empty($base64)) {
                    $vlmData = $this->analyzeMultimodalImage($base64, $mimeType, $geminiKey);
                    if ($vlmData) {
                        $matched = [
                            'id' => 'vlm-' . rand(100, 999),
                            'norme_origine' => [
                                'source' => 'VLM Ingestion',
                                'reference_article' => 'ANALYSIS',
                                'titre_original' => 'Analyse visuelle VLM en direct',
                                'texte_brut' => 'Analyse automatique de l\'image soumise par l\'IA.'
                            ],
                            'alternative_prosartisan' => [
                                'titre_vulgarise' => $vlmData['titre_vulgarise'] ?? 'Analyse de l\'image',
                                'methode_execution' => $vlmData['methode_execution'] ?? '',
                                'bouclier_autorite' => $vlmData['bouclier_autorite'] ?? '',
                                'dosages_recommandes' => [],
                                'materiaux_recommandes' => []
                            ],
                            'cout_estime_local' => [
                                'gamme_prix' => 'Moyen',
                                'estimation_m2_fcfa' => 'N/A'
                            ],
                            'metadata' => [
                                'tags_pathologies' => [$vlmData['pathologie_principale'] ?? 'divers']
                            ]
                        ];
                        return response()->json([$matched]);
                    }
                }
            }

            $matched = $this->simulateVlmResult($queryTags);
            return response()->json([$matched]);
        }

        $ragMatches = null;
        $geminiKey = config('services.gemini.api_key');
        $qdrantUrl = config('services.qdrant.url');

        if (!empty($qdrantUrl) && !empty($geminiKey) && !empty($queryTags)) {
            $vector = $this->getGeminiEmbedding(implode(' ', $queryTags), $geminiKey);
            if ($vector) {
                $ragMatches = $this->queryQdrant($vector);
            }
        }

        if ($ragMatches === null) {
            $rows = ProductionItem::all();
            $ragMatches = [];

            foreach ($rows as $r) {
                $itemJson = $r->generated_json;
                $itemTags = array_filter(array_map('trim', explode(',', $r->tags)));

                // 1. Tag Match
                $hasTagMatch = false;
                foreach ($queryTags as $tag) {
                    if (in_array(trim($tag), $itemTags)) {
                        $hasTagMatch = true;
                        break;
                    }
                }
                if (!empty($queryTags) && !$hasTagMatch) {
                    continue;
                }
                $ragMatches[] = $itemJson;
            }
        }

        $matchedResults = [];
        foreach ($ragMatches as $itemJson) {
            // 2. Budget filter
            if (!empty($filters['maxBudget'])) {
                $maxBudget = $filters['maxBudget'];
                $itemBudget = $itemJson['cout_estime_local']['gamme_prix'] ?? 'Faible';
                $budgetWeights = ['Faible' => 1, 'Moyen' => 2, 'Eleve' => 3];
                $maxWeight = $budgetWeights[$maxBudget] ?? 2;
                $itemWeight = $budgetWeights[$itemBudget] ?? 1;
                if ($itemWeight > $maxWeight) {
                    continue;
                }
            }

            // 2b. Type of work filter
            if (!empty($filters['type_ouvrage']) && $filters['type_ouvrage'] !== 'Tout') {
                $itemType = $itemJson['metadata']['type_ouvrage'] ?? '';
                if (strtolower($itemType) !== strtolower($filters['type_ouvrage'])) {
                    continue;
                }
            }

            // 3. Hardware store filter
            if (!empty($filters['onlyHardwareStore']) && $filters['onlyHardwareStore'] === true) {
                $mats = $itemJson['alternative_prosartisan']['materiaux_recommandes'] ?? [];
                $hasOnlyLocal = true;
                foreach ($mats as $m) {
                    if (($m['disponibilite'] ?? '') !== 'Quincaillerie') {
                        $hasOnlyLocal = false;
                        break;
                    }
                }
                if (!$hasOnlyLocal) {
                    continue;
                }
            }

            $matchedResults[] = $itemJson;
        }

        if (empty($matchedResults)) {
            $fallback = $this->generateFallback($queryTags);
            $matchedResults[] = $fallback;
        }

        return response()->json($matchedResults);
    }

    private function isQueryInScope(string $query): bool
    {
        $keywords = [
            'dosage',
            'ciment',
            'beton',
            'béton',
            'dalle',
            'mur',
            'brique',
            'agglo',
            'sable',
            'gravier',
            'enduit',
            'plomb',
            'tuyau',
            'fuite',
            'robinet',
            'electr',
            'électr',
            'cable',
            'câble',
            'fil',
            'courant',
            'prise',
            'disjoncteur',
            'peint',
            'humid',
            'fissur',
            'infiltr',
            'chantier',
            'macon',
            'maçon',
            'travaux',
            'devis',
            'renov',
            'rénov',
            'constru',
            'batiment',
            'bâtiment',
            'carrel',
            'toit',
            'charp',
            'bois',
            'fer',
            'soud',
            'nzassa',
            'referent',
            'référent',
            'jcode',
            'sequestre',
            'séquestre',
            'wallet',
            'portland',
            'cpj',
            'mortier',
            'gachage',
            'gâchage',
            'lbtp',
            'bnetd'
        ];

        $queryLower = mb_strtolower(trim($query));
        $queryClean = str_replace(
            ['é', 'è', 'ê', 'ë', 'à', 'â', 'î', 'ï', 'ô', 'ö', 'û', 'ü', 'ç'],
            ['e', 'e', 'e', 'e', 'a', 'a', 'i', 'i', 'o', 'o', 'u', 'u', 'c'],
            $queryLower
        );

        foreach ($keywords as $kw) {
            if (str_contains($queryClean, $kw)) {
                return true;
            }
        }

        return false;
    }

    public function chat(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string',
            'trade' => 'nullable|string',
        ]);

        // Rate limiting check
        if (!\App\Services\AiMonitoringService::checkUserLimit()) {
            return response()->json([
                'success' => false,
                'error' => 'Quota d\'interactions journalier atteint. Réessayez demain.'
            ], 429);
        }

        $userMsg = trim($validated['message']);

        $trade = $validated['trade'] ?? 'Maçon';
        $userMsgLower = strtolower($userMsg);

        $ragMatches = null;
        $geminiKey = config('services.gemini.api_key');
        $qdrantUrl = config('services.qdrant.url');

        if (!empty($qdrantUrl) && !empty($geminiKey)) {
            $vector = $this->getGeminiEmbedding($userMsg, $geminiKey);
            if ($vector) {
                $ragMatches = $this->queryQdrant($vector);
            }
        }

        if ($ragMatches === null) {
            $rows = ProductionItem::all();
            $ragMatches = [];

            foreach ($rows as $r) {
                $itemJson = $r->generated_json;
                $itemTags = array_filter(array_map('trim', explode(',', strtolower($r->tags))));
                $title = strtolower($itemJson['alternative_prosartisan']['titre_vulgarise'] ?? '');
                $rawText = strtolower($itemJson['norme_origine']['texte_brut'] ?? '');
                $execution = strtolower($itemJson['alternative_prosartisan']['methode_execution'] ?? '');

                $matched = false;

                // 1. Exact Tag Match
                foreach ($itemTags as $tag) {
                    if (str_contains($userMsgLower, $tag) || str_contains($tag, $userMsgLower)) {
                        $matched = true;
                        break;
                    }
                }

                // 2. Title Match
                if (!$matched && $title && (str_contains($userMsgLower, $title) || $this->hasMatchingWord($title, $userMsgLower))) {
                    $matched = true;
                }

                // 3. Raw Text or Execution Method Match
                if (!$matched && (str_contains($rawText, $userMsgLower) || str_contains($execution, $userMsgLower))) {
                    $matched = true;
                }

                // 4. Token Word Match (for keywords > 4 characters)
                if (!$matched) {
                    $userWords = array_filter(explode(' ', preg_replace('/[^\p{L}\p{N}\s]/u', '', $userMsgLower)));
                    foreach ($userWords as $uWord) {
                        if (strlen($uWord) > 4) {
                            if (str_contains($title, $uWord) || str_contains($rawText, $uWord) || str_contains($execution, $uWord)) {
                                $matched = true;
                                break;
                            }
                        }
                    }
                }

                if ($matched) {
                    $ragMatches[] = $itemJson;
                }
            }
        }

        $contextTexts = [];
        $sources = [];

        foreach ($ragMatches as $match) {
            $alt = $match['alternative_prosartisan'] ?? [];
            $norme = $match['norme_origine'] ?? [];
            $cout = $match['cout_estime_local'] ?? [];

            $contextStr = "Titre: " . ($alt['titre_vulgarise'] ?? '') . "\nMéthode recommandée: " . ($alt['methode_execution'] ?? '') . "\nDosages: ";
            foreach ($alt['dosages_recommandes'] ?? [] as $d) {
                $contextStr .= ($d['element'] ?? '') . " - " . ($d['ratio'] ?? '') . " (" . ($d['unite_mesure_locale'] ?? '') . "), ";
            }
            if (!empty($cout['estimation_m2_fcfa'])) {
                $contextStr .= "\nCoût: " . $cout['estimation_m2_fcfa'];
            }

            $contextTexts[] = $contextStr;
            $sources[] = [
                'id' => $match['id'],
                'title' => $alt['titre_vulgarise'] ?? '',
                'source_doc' => $norme['titre_original'] ?? ''
            ];
        }

        $geminiKey = config('services.gemini.api_key');
        $reply = "";

        if ($geminiKey) {
            $reply = $this->callGeminiApi($geminiKey, $userMsg, $contextTexts, $trade);
        } else {
            $reply = $this->localChatFallback($userMsg, $ragMatches);
        }

        return response()->json([
            'response' => $reply,
            'sources' => $sources
        ]);
    }

    private function hasMatchingWord(string $title, string $userMsgLower): bool
    {
        $words = explode(' ', $title);
        foreach ($words as $word) {
            if (strlen($word) > 4 && str_contains($userMsgLower, $word)) {
                return true;
            }
        }
        return false;
    }

    private function simulateVlmResult(array $queryTags): array
    {
        // Simple mock VLM result
        return [
            'id' => 'vlm-' . rand(100, 999),
            'norme_origine' => [
                'source' => 'VLM Ingestion',
                'reference_article' => 'ANALYSIS',
                'titre_original' => 'Analyse visuelle VLM',
                'texte_brut' => 'Analyse automatique de l\'image soumise.'
            ],
            'alternative_prosartisan' => [
                'titre_vulgarise' => 'Recommandation visuelle automatique',
                'methode_execution' => 'Veuillez inspecter l\'ouvrage pour valider le dosage.',
                'dosages_recommandes' => [],
                'materiaux_recommandes' => []
            ],
            'cout_estime_local' => [
                'gamme_prix' => 'Moyen',
                'estimation_m2_fcfa' => 'N/A'
            ],
            'metadata' => [
                'tags_pathologies' => $queryTags
            ]
        ];
    }

    private function analyzeMultimodalImage(string $base64, string $mime, string $key): ?array
    {
        $prompt = "Tu es un ingénieur BTP expert en Côte d'Ivoire. Analyse cette image de pathologie de chantier. ";
        $prompt .= "Identifie les pathologies visibles (ex: fissures, humidité, infiltration, éclatement du béton, rouille de fer). ";
        $prompt .= "Retourne obligatoirement un objet JSON valide contenant UNIQUEMENT ces clés :\n";
        $prompt .= "- 'titre_vulgarise': Le titre simple de la pathologie (ex: Fissure de linteau, Infiltration de dalle, Humidité de bas de mur).\n";
        $prompt .= "- 'pathologie_principale': Le tag système principal (choisis parmi: fissure_structure, remontee_capillaire, infiltration_dalle, ferraillage, toit_terrasse).\n";
        $prompt .= "- 'methode_execution': Instructions techniques claires, étapes de réparation adaptées aux chantiers ivoiriens (avec dosages et ciment).\n";
        $prompt .= "- 'bouclier_autorite': L'argumentaire de vulgarisation en français ivoirien, rassurant et professionnel, pour expliquer le problème au propriétaire de la maison et le convaincre de faire les bons travaux de réparation durables (par exemple: 'Propriétaire, ...' ou 'Tonton, ...').\n\n";
        $prompt .= "Ne retourne aucun texte en dehors du JSON.";

        try {
            $model = config('services.gemini.model', 'gemini-3.6-flash');
            $baseUrl = config('services.gemini.base_url', 'https://generativelanguage.googleapis.com');
            $url = "{$baseUrl}/v1beta/models/{$model}:generateContent?key={$key}";
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            ])->withOptions([
                'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]
            ])->timeout(60)->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            [
                                'inlineData' => [
                                    'mimeType' => $mime,
                                    'data' => $base64
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                $text = $response->json('candidates.0.content.parts.0.text') ?? '';
                $text = preg_replace('/^```json\s*/i', '', $text);
                $text = preg_replace('/```\s*$/', '', $text);
                $text = trim($text);

                $data = json_decode($text, true);
                if (is_array($data)) {
                    return $data;
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Multimodal analysis failed: " . $e->getMessage());
        }
        return null;
    }

    private function generateFallback(array $queryTags): array
    {
        return [
            'id' => 'fallback-' . rand(100, 999),
            'norme_origine' => [
                'source' => 'ProsArtisan RAG Fallback',
                'reference_article' => 'FALLBACK',
                'titre_original' => 'Fallback générique',
                'texte_brut' => 'Aucune fiche validée n\'a été trouvée.'
            ],
            'alternative_prosartisan' => [
                'titre_vulgarise' => 'Alternative de secours (Fallback)',
                'methode_execution' => 'Effectuer un gâchage classique en respectant les bonnes pratiques locales.',
                'dosages_recommandes' => [
                    ['element' => 'Ciment CPJ 42.5', 'ratio' => '1 sac', 'unite_mesure_locale' => 'Sac'],
                    ['element' => 'Sable propre', 'ratio' => '2.5 brouettes', 'unite_mesure_locale' => 'Brouette']
                ],
                'materiaux_recommandes' => []
            ],
            'cout_estime_local' => [
                'gamme_prix' => 'Moyen',
                'estimation_m2_fcfa' => 'Variable'
            ],
            'metadata' => [
                'tags_pathologies' => $queryTags
            ]
        ];
    }

    private function callGeminiApi(string $key, string $userMsg, array $contextTexts, string $trade = 'Maçon'): string
    {
        $prompt = "Tu es un assistant BTP expert pour la plateforme ProsArtisan en Côte d'Ivoire. L'utilisateur connecté est un artisan de catégorie : **{$trade}**.\n";
        $prompt .= "Adapte le ton, le vocabulaire technique et les conseils spécifiquement pour le métier de **{$trade}** (ex: pour un maçon parle de dosages/ciments CPJ 42.5/32.5, pour un plombier de diamètres/pente/colle/SODECl, pour un électricien de sections de câbles/terre/disjoncteurs, pour un peintre de préparation/peinture Pliolite/humidité).\n";
        $prompt .= "L'artisan te pose la question suivante : '{$userMsg}'.\n";
        if (!empty($contextTexts)) {
            $prompt .= "\nUtilise en priorité les informations locales suivantes de notre base de données BTP :\n";
            $prompt .= implode("\n\n---\n", $contextTexts);
            $prompt .= "\n\nFormate ta réponse avec des puces claires et valide d'abord l'approche technique de l'artisan.";
        } else {
            $prompt .= "\nRéponds de manière professionnelle et concrète, adaptée à la réalité des chantiers en Côte d'Ivoire. Si sa question est totalement hors-sujet ou n'a aucun rapport avec le BTP, la construction ou son métier, rappelle-lui gentiment et poliment ton rôle de guide de chantier.";
        }

        $startTime = microtime(true);
        $model = config('services.gemini.model', 'gemini-3.6-flash');
        try {
            $baseUrl = config('services.gemini.base_url', 'https://generativelanguage.googleapis.com');
            $url = "{$baseUrl}/v1beta/models/{$model}:generateContent?key={$key}";
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            ])->withOptions([
                'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]
            ])->timeout(60)->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);

            $responseTimeMs = (microtime(true) - $startTime) * 1000;
            if ($response->successful()) {
                $promptTokens = $response->json('usageMetadata.promptTokenCount') ?? 0;
                $completionTokens = $response->json('usageMetadata.candidatesTokenCount') ?? 0;
                \App\Services\AiMonitoringService::log($model, 'chat', $promptTokens, $completionTokens, $responseTimeMs, 200);

                return $response->json('candidates.0.content.parts.0.text') ?? "Désolé, je n'ai pas pu formuler de réponse.";
            } else {
                \App\Services\AiMonitoringService::log($model, 'chat', 0, 0, $responseTimeMs, $response->status(), $response->body());
                return "Erreur API Gemini (Status " . $response->status() . "): " . $response->body();
            }
        } catch (\Exception $e) {
            $responseTimeMs = (microtime(true) - $startTime) * 1000;
            \App\Services\AiMonitoringService::log($model, 'chat', 0, 0, $responseTimeMs, 500, $e->getMessage());
            return "Exception Gemini: " . $e->getMessage();
        }
    }

    private function localChatFallback(string $userMsg, array $ragMatches): string
    {
        if (!$this->isQueryInScope($userMsg)) {
            return "Désolé, cette question ne fait pas partie du périmètre fonctionnel de ProsArtisan.";
        }

        if (empty($ragMatches)) {
            return "Bonjour Boss ! Je suis connecté hors-ligne et je n'ai pas trouvé de fiche technique correspondante dans mon cache. Pour les dosages classiques, assure-toi d'utiliser du ciment CPJ 42.5 pour les dalles et structures porteuses, et du CPJ 32.5 pour les enduits et maçonneries simples.";
        }

        $first = $ragMatches[0];
        $alt = $first['alternative_prosartisan'] ?? [];
        $title = $alt['titre_vulgarise'] ?? '';
        $method = $alt['methode_execution'] ?? '';

        $reply = "Bonjour Boss ! En me basant sur la fiche **{$title}** de notre base locale :\n\n";
        $reply .= "**Méthode :** {$method}\n\n";
        $reply .= "**Dosages recommandés :**\n";
        foreach ($alt['dosages_recommandes'] ?? [] as $d) {
            $reply .= "- " . ($d['element'] ?? '') . " : " . ($d['ratio'] ?? '') . " (" . ($d['unite_mesure_locale'] ?? '') . ")\n";
        }
        return $reply;
    }

    private function getGeminiEmbedding(string $text, string $key): ?array
    {
        $startTime = microtime(true);
        $model = 'models/text-embedding-004';
        try {
            $baseUrl = config('services.gemini.base_url', 'https://generativelanguage.googleapis.com');
            $url = "{$baseUrl}/v1beta/models/text-embedding-004:embedContent?key={$key}";
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            ])->withOptions([
                'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]
            ])->timeout(15)->post($url, [
                'model' => 'models/text-embedding-004',
                'content' => [
                    'parts' => [
                        ['text' => $text]
                    ]
                ]
            ]);

            $responseTimeMs = (microtime(true) - $startTime) * 1000;
            if ($response->successful()) {
                $promptTokens = ceil(strlen($text) / 4);
                \App\Services\AiMonitoringService::log($model, 'embedding', $promptTokens, 0, $responseTimeMs, 200);
                return $response->json('embedding.values');
            } else {
                \App\Services\AiMonitoringService::log($model, 'embedding', 0, 0, $responseTimeMs, $response->status(), $response->body());
            }
        } catch (\Exception $e) {
            $responseTimeMs = (microtime(true) - $startTime) * 1000;
            \App\Services\AiMonitoringService::log($model, 'embedding', 0, 0, $responseTimeMs, 500, $e->getMessage());
            \Illuminate\Support\Facades\Log::error('Gemini Embedding Exception: ' . $e->getMessage());
        }
        return null;
    }

    private function queryQdrant(array $vector, int $limit = 3): ?array
    {
        $url = config('services.qdrant.url');
        $apiKey = config('services.qdrant.api_key');
        $collection = config('services.qdrant.collection', 'btp_rules');

        if (empty($url)) {
            return null;
        }

        $startTime = microtime(true);
        try {
            $request = Http::timeout(5);
            if (!empty($apiKey)) {
                $request = $request->withHeaders(['api-key' => $apiKey]);
            }

            $response = $request->post("{$url}/collections/{$collection}/points/search", [
                'vector' => $vector,
                'limit' => $limit,
                'with_payload' => true
            ]);

            $responseTimeMs = (microtime(true) - $startTime) * 1000;
            if ($response->successful()) {
                \App\Services\AiMonitoringService::log('qdrant', 'search', 0, 0, $responseTimeMs, 200);
                $points = $response->json('result') ?? [];
                $matches = [];
                foreach ($points as $point) {
                    if (!empty($point['payload'])) {
                        $matches[] = $point['payload'];
                    }
                }
                return $matches;
            } else {
                \App\Services\AiMonitoringService::log('qdrant', 'search', 0, 0, $responseTimeMs, $response->status(), $response->body());
            }
        } catch (\Exception $e) {
            $responseTimeMs = (microtime(true) - $startTime) * 1000;
            \App\Services\AiMonitoringService::log('qdrant', 'search', 0, 0, $responseTimeMs, 500, $e->getMessage());
            \Illuminate\Support\Facades\Log::error("Failed to query Qdrant: " . $e->getMessage());
        }
        return null;
    }

    public function llmMediation(Request $request, \App\Models\Litige $litige): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $userMsg = trim($request->input('message'));
        $geminiKey = env('GEMINI_API_KEY');

        $litige->loadMissing(['mission.client', 'mission.artisan']);

        $client = $litige->mission->client;
        $artisan = $litige->mission->artisan;
        $mission = $litige->mission;

        $prompt = "Tu es un médiateur neutre et professionnel de niveau 1 pour la plateforme ProsArtisan en Côte d'Ivoire. Ta mission est d'apaiser les tensions entre le client et l'artisan, de filtrer les émotions pour se concentrer uniquement sur les faits techniques et contractuels, et de proposer une ébauche de résolution juste pour les deux parties.\n\n";
        $prompt .= "--- CONTEXTE DU LITIGE ---\n";
        $prompt .= "- Litige ID: #{$litige->id}\n";
        $prompt .= "- Motif officiel: {$litige->motif}\n";
        $prompt .= "- Description initiale: {$litige->description}\n";
        $prompt .= "- Mission: {$mission->description}\n";
        $prompt .= "- Montant Total: {$mission->montant_total} FCFA\n";
        $prompt .= "- Montant Matériaux: {$mission->montant_materiaux} FCFA\n";
        $prompt .= "- Montant Main d'œuvre: {$mission->montant_mo} FCFA\n";
        $prompt .= "- Client: {$client->name} (Score ProsArtisan: {$client->score_prosartisan})\n";
        $prompt .= "- Artisan: {$artisan->name} (Score ProsArtisan: {$artisan->score_prosartisan})\n\n";
        $prompt .= "--- RESSENTI / RÈCIT SUPPLÉMENTAIRE SOUMIS ---\n";
        $prompt .= "\"{$userMsg}\"\n\n";
        $prompt .= "Rédige une réponse structurée contenant :\n";
        $prompt .= "1. **Faits extraits** : Résumé neutre et chronologique des faits techniques (sans colère, exagérations ou insultes).\n";
        $prompt .= "2. **Analyse de la situation** : Explication objective des manquements ou des points d'accord potentiels.\n";
        $prompt .= "3. **Proposition de résolution recommandée** : Action concrète (ex: finalisation d'un jalon, remboursement partiel, médiation physique du référent) avec un ton calme et orienté solution.";

        $reply = "";

        if ($geminiKey) {
            try {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$geminiKey}";
                $response = Http::post($url, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ]
                ]);

                if ($response->successful()) {
                    $reply = $response->json('candidates.0.content.parts.0.text') ?? "Impossible de générer la médiation.";
                } else {
                    $reply = "Le service de médiation IA est temporairement indisponible.";
                }
            } catch (\Exception $e) {
                $reply = "Une erreur est survenue lors de l'appel au service de médiation.";
            }
        } else {
            $reply = "### Faits extraits\n- Litige initié sur la mission #{$mission->id} de type BTP.\n- Motif : {$litige->motif}.\n- Différend concernant l'exécution des travaux.\n\n### Analyse\n- Les émotions des parties altèrent la communication.\n- Absence d'éléments contradictoires immédiats.\n\n### Proposition de résolution recommandée\n- Nous recommandons de soumettre le cas au Jury N'Zassa ou de planifier une visite du Référent de zone pour valider la conformité technique de l'ouvrage.";
        }

        return response()->json([
            'success' => true,
            'mediation' => $reply,
        ]);
    }
}
