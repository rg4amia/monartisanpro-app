<?php

namespace App\Services;

use App\Models\Mission;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class MissionService
{
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

        // Enrichissement Gemini (asynchrone dans un vrai système)
        $estimate = $this->estimate($data);
        $mission->update([
            'gemini_category'       => $estimate['category'],
            'gemini_urgency'        => $estimate['urgency'],
            'gemini_estimation_min' => $estimate['price_min'],
            'gemini_estimation_max' => $estimate['price_max'],
        ]);

        return $mission->fresh();
    }

    /**
     * Analyse le besoin du client via Gemini API (stub pour dev).
     */
    public function estimate(array $data): array
    {
        Log::info('[Gemini STUB] Estimation demandée pour : ' . ($data['description'] ?? ''));

        // En production : appel Gemini API avec clé GEMINI_API_KEY
        // Pour dev : retourne une estimation par défaut selon mots-clés
        $description = strtolower($data['description'] ?? '');

        $category = 'Travaux généraux';
        if (str_contains($description, 'électri') || str_contains($description, 'electri')) {
            $category = 'Électricité';
        } elseif (str_contains($description, 'plomb') || str_contains($description, 'eau') || str_contains($description, 'tuyau')) {
            $category = 'Plomberie';
        } elseif (str_contains($description, 'peinture') || str_contains($description, 'mur')) {
            $category = 'Peinture';
        } elseif (str_contains($description, 'cliamtis') || str_contains($description, 'clim')) {
            $category = 'Climatisation';
        } elseif (str_contains($description, 'maçon') || str_contains($description, 'béton') || str_contains($description, 'beton')) {
            $category = 'Maçonnerie';
        }

        return [
            'category'  => $category,
            'urgency'   => 'moyen',
            'price_min' => 50000,
            'price_max' => 250000,
            'breakdown' => [
                'materials' => ['min' => 30000, 'max' => 150000],
                'labor'     => ['min' => 20000, 'max' => 100000],
            ],
        ];
    }
}
