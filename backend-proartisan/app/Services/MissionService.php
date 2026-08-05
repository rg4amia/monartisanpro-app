<?php

namespace App\Services;

use App\Models\Mission;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class MissionService
{
    public function __construct(
        private GeminiService $geminiService,
        private NotificationService $notificationService
    ) {}

    /**
     * Crée une mission et appelle Gemini pour l'estimation.
     */
    public function create(User $client, array $data): Mission
    {
        $hasArtisan = !empty($data['artisan_id']);
        $initialState = $hasArtisan 
            ? \App\States\Mission\PendingArtisanAcceptanceState::class 
            : \App\States\Mission\DraftState::class;

        $mission = Mission::create([
            'client_id'           => $client->id,
            'artisan_id'          => $data['artisan_id'] ?? null,
            'requested_sector_id' => $data['sector_id'] ?? null,
            'requested_trade_id'  => $data['trade_id'] ?? null,
            'description'         => $data['description'],
            'photos_json'         => $data['photos'] ?? null,
            'status'              => $initialState,
            'client_latitude'     => $data['lat'] ?? null,
            'client_longitude'    => $data['lng'] ?? null,
            'client_address'      => $data['location_address'] ?? null,
        ]);

        // Enrichissement Gemini
        try {
            $estimate = $this->geminiService->analyzeMission($data['description'], [
                'category' => $data['category'] ?? null,
                'location_address' => $data['location_address'] ?? null,
            ]);

            $urgency = match (strtolower((string) ($estimate['urgency'] ?? 'moyen'))) {
                'faible', 'low', 'normale', 'normal' => 'faible',
                'urgent', 'haute', 'high', 'elevee', 'élevée' => 'urgent',
                default => 'moyen',
            };

            $mission->update([
                'gemini_category'       => $estimate['category'] ?? 'Travaux généraux',
                'gemini_urgency'        => $urgency,
                'gemini_estimation_min' => $estimate['price_min'] ?? 25000,
                'gemini_estimation_max' => $estimate['price_max'] ?? 100000,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Échec enrichissement Gemini: ' . $e->getMessage());
        }

        if ($hasArtisan) {
            try {
                $artisan = User::find($data['artisan_id']);
                if ($artisan) {
                    $clientName = $client->name ?? 'Client';
                    $this->notificationService->send(
                        $artisan,
                        'mission',
                        'Nouvelle demande de devis',
                        "Le client {$clientName} vous a envoyé une demande de devis.",
                        ['mission_id' => $mission->id]
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('Échec envoi notification artisan: ' . $e->getMessage());
            }
        }

        return $mission->fresh();
    }

    /**
     * Analyse le besoin du client via Gemini API.
     */
    public function estimate(array $data): array
    {
        return $this->geminiService->analyzeMission($data['description'] ?? '', [
            'category' => $data['category'] ?? null,
            'location_address' => $data['location_address'] ?? null,
        ]);
    }
}
