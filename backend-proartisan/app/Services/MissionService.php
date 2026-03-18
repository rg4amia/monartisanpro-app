<?php

namespace App\Services;

use App\Models\Mission;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class MissionService
{
    public function __construct(private GeminiService $geminiService) {}

    /**
     * Crée une mission et appelle Gemini pour l'estimation.
     */
    public function create(User $client, array $data): Mission
    {
        $mission = Mission::create([
            'client_id'   => $client->id,
            'description' => $data['description'],
            'photos_json' => $data['photos'] ?? null,
            'status'      => 'en_attente',
        ]);

        // Enrichissement Gemini
        $estimate = $this->geminiService->analyzeMission($data['description']);

        $mission->update([
            'gemini_category'       => $estimate['category'],
            'gemini_urgency'        => $estimate['urgency'],
            'gemini_estimation_min' => $estimate['price_min'],
            'gemini_estimation_max' => $estimate['price_max'],
        ]);

        return $mission->fresh();
    }

    /**
     * Analyse le besoin du client via Gemini API.
     */
    public function estimate(array $data): array
    {
        return $this->geminiService->analyzeMission($data['description'] ?? '');
    }
}
