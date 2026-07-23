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
        $estimate = $this->geminiService->analyzeMission($data['description'], [
            'category' => $data['category'] ?? null,
            'location_address' => $data['location_address'] ?? null,
        ]);

        $mission->update([
            'gemini_category'       => $estimate['category'],
            'gemini_urgency'        => $estimate['urgency'],
            'gemini_estimation_min' => $estimate['price_min'],
            'gemini_estimation_max' => $estimate['price_max'],
        ]);

        if ($hasArtisan) {
            $artisan = User::find($data['artisan_id']);
            if ($artisan) {
                $this->notificationService->send(
                    $artisan,
                    'mission',
                    'Nouvelle demande de devis',
                    "Le client {$client->phone} vous a envoyé une demande de devis.",
                    ['mission_id' => $mission->id]
                );
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
