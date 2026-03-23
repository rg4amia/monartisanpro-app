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

            $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
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
}
