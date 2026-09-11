<?php

namespace App\Services;

use App\Models\MissionRealtimeEvent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class RealtimeEventService
{
    /**
     * Émet et enregistre un événement temps réel pour une mission ou un canal générique.
     */
    public function broadcast(?int $missionId, string $eventType, array $payload): MissionRealtimeEvent
    {
        try {
            $event = MissionRealtimeEvent::create([
                'mission_id'   => $missionId,
                'event_type'   => $eventType,
                'payload_json' => $payload,
                'created_at'   => now(),
            ]);

            return $event;
        } catch (\Throwable $e) {
            Log::error('Erreur enregistrement événement temps réel: ' . $e->getMessage(), [
                'mission_id' => $missionId,
                'event_type' => $eventType,
            ]);

            // Retourne un objet simulé sans bloquer la transaction principale
            $dummy = new MissionRealtimeEvent([
                'mission_id'   => $missionId,
                'event_type'   => $eventType,
                'payload_json' => $payload,
                'created_at'   => now(),
            ]);
            $dummy->id = 0;
            return $dummy;
        }
    }

    /**
     * Publie un événement temps réel sur un canal spécifique ('order', 'mission', etc.).
     */
    public function publish(string $channelType, int $channelId, string $eventType, array $payload, ?string $summary = null): MissionRealtimeEvent
    {
        $payload['_channel'] = "{$channelType}.{$channelId}";
        if ($summary) {
            $payload['_summary'] = $summary;
        }

        $missionId = ($channelType === 'mission') ? $channelId : null;

        return $this->broadcast($missionId, $eventType, $payload);
    }

    /**
     * Récupère les nouveaux événements survenus après un ID donné.
     *
     * @return Collection<int, MissionRealtimeEvent>
     */
    public function getEventsSince(int $missionId, int $lastEventId = 0, int $limit = 50): Collection
    {
        return MissionRealtimeEvent::where('mission_id', $missionId)
            ->where('id', '>', $lastEventId)
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();
    }
}
