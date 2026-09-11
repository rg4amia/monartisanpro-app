<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Services\RealtimeEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MissionStreamController extends Controller
{
    public function __construct(private RealtimeEventService $realtimeService) {}

    /**
     * Flux SSE (Server-Sent Events) adapté aux hébergements Hostinger (sans démon persistant).
     * Diffuse les statuts de mission, jalons, J-Codes et nouveaux messages.
     */
    public function stream(Mission $mission, Request $request): StreamedResponse|JsonResponse
    {
        $user = $request->user();

        if ($mission->client_id !== $user->id && $mission->artisan_id !== $user->id && ! in_array($user->role, ['admin', 'referent'])) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé au flux de cette mission.',
            ], 403);
        }

        $lastEventId = (int) ($request->header('Last-Event-ID') ?? $request->query('last_event_id', 0));

        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Désactive le tampon Nginx/Hostinger
        ];

        return response()->stream(function () use ($mission, $lastEventId) {
            $currentLastId = $lastEventId;
            $startTime = time();
            $maxDuration = 25; // 25 secondes max par session HTTP, reconnexion auto Flutter
            $lastHeartbeat = time();

            // Message initial d'établissement de connexion
            echo ": connection_established\n\n";
            if (ob_get_level() > 0) ob_flush();
            flush();

            while (time() - $startTime < $maxDuration) {
                if (connection_aborted()) {
                    break;
                }

                $events = $this->realtimeService->getEventsSince($mission->id, $currentLastId, 20);

                if ($events->isNotEmpty()) {
                    foreach ($events as $event) {
                        $currentLastId = $event->id;
                        $payload = json_encode($event->payload_json, JSON_UNESCAPED_UNICODE);

                        echo "id: {$event->id}\n";
                        echo "event: {$event->event_type}\n";
                        echo "data: {$payload}\n\n";
                    }

                    if (ob_get_level() > 0) ob_flush();
                    flush();
                } else {
                    // Battement de cœur toutes les 10 secondes pour éviter le timeout réseau
                    if (time() - $lastHeartbeat >= 10) {
                        echo ": heartbeat\n\n";
                        if (ob_get_level() > 0) ob_flush();
                        flush();
                        $lastHeartbeat = time();
                    }
                }

                // Pause de 1.5s entre chaque vérification
                usleep(1500000);
            }
        }, 200, $headers);
    }

    /**
     * Endpoint alternatif REST pour récupérer les événements par lot (Fallback polling).
     */
    public function events(Mission $mission, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($mission->client_id !== $user->id && $mission->artisan_id !== $user->id && ! in_array($user->role, ['admin', 'referent'])) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        $lastEventId = (int) $request->query('last_event_id', 0);
        $events = $this->realtimeService->getEventsSince($mission->id, $lastEventId, 50);

        return response()->json([
            'success' => true,
            'data' => $events,
            'last_event_id' => $events->isNotEmpty() ? $events->last()->id : $lastEventId,
        ]);
    }
}
