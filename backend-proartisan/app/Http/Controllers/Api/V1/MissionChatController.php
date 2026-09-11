<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\MissionMessage;
use App\Services\AntiCircumventionService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MissionChatController extends Controller
{
    public function __construct(
        private AntiCircumventionService $antiCircumvention,
        private ?NotificationService $notificationService = null
    ) {}

    /**
     * Liste les messages d'une mission avec pagination.
     */
    public function index(Mission $mission, Request $request): JsonResponse
    {
        $user = $request->user();

        // Seuls les participants ou administrateurs ont accès au chat de chantier
        if ($mission->client_id !== $user->id && $mission->artisan_id !== $user->id && ! in_array($user->role, ['admin', 'referent'])) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé à la discussion de ce chantier.',
            ], 403);
        }

        $isFunded = $mission->isFunded();

        $messages = $mission->messages()
            ->with(['sender:id,name,role'])
            ->latest('created_at')
            ->paginate($request->integer('per_page', 50));

        // Marquer comme lus les messages destinés à l'utilisateur courant
        $mission->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'is_funded' => $isFunded,
            'chat_mode' => $isFunded ? 'unlimited' : 'anti_circumvention_active',
            'data' => array_reverse($messages->items()),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    /**
     * Envoie un message dans la discussion de mission.
     */
    public function store(Mission $mission, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($mission->client_id !== $user->id && $mission->artisan_id !== $user->id && ! in_array($user->role, ['admin', 'referent'])) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les participants peuvent poster un message.',
            ], 403);
        }

        $request->validate([
            'type' => ['nullable', 'in:text,image,audio'],
            'content' => ['nullable', 'string', 'max:2000'],
            'file' => ['nullable', 'file', 'max:10240'], // 10 Mo
        ]);

        $type = $request->input('type', 'text');
        $rawContent = trim((string) $request->input('content', ''));
        $mediaUrl = null;
        $mediaMetadata = [];

        // Upload média si présent
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $mime = $file->getMimeType() ?: '';

            if (str_starts_with($mime, 'image/')) {
                $type = 'image';
            } elseif (str_starts_with($mime, 'audio/') || in_array($file->getClientOriginalExtension(), ['m4a', 'mp3', 'wav', 'aac', 'ogg'])) {
                $type = 'audio';
            }

            $extension = $file->getClientOriginalExtension() ?: 'bin';
            $filename = 'chat_' . Str::random(24) . '.' . $extension;
            $path = $file->storeAs("chat/{$mission->id}", $filename, 'public');
            $mediaUrl = Storage::disk('public')->url($path);

            $mediaMetadata = [
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $mime,
                'size_bytes' => $file->getSize(),
            ];
        }

        if (empty($rawContent) && empty($mediaUrl)) {
            return response()->json([
                'success' => false,
                'message' => 'Le message doit contenir un texte ou un fichier média.',
            ], 422);
        }

        // Inspection anti-contournement
        $filterResult = $this->antiCircumvention->inspectAndFilter($rawContent, $mission);

        $message = MissionMessage::create([
            'mission_id' => $mission->id,
            'sender_id' => $user->id,
            'type' => $type,
            'content' => $filterResult['content'],
            'media_url' => $mediaUrl,
            'media_metadata' => ! empty($mediaMetadata) ? $mediaMetadata : null,
            'is_redacted' => $filterResult['is_redacted'],
            'flagged_for_review' => $filterResult['flagged'],
        ]);

        $message->load('sender:id,name,role');

        // Diffusion temps réel SSE / WebSockets
        try {
            app(\App\Services\RealtimeEventService::class)->broadcast(
                $mission->id,
                'chat_message',
                $message->toArray()
            );
        } catch (\Throwable $e) {
            // Silencieux pour ne pas impacter l'envoi HTTP
        }

        // Notification du destinataire
        $recipientId = ($user->id === $mission->client_id) ? $mission->artisan_id : $mission->client_id;
        if ($recipientId && $this->notificationService) {
            try {
                $senderName = $user->name ?? 'Votre interlocuteur';
                $snippet = $type === 'text' ? Str::limit($message->content, 60) : ($type === 'audio' ? '🎙️ Message vocal' : '📷 Photo de chantier');
                $this->notificationService->sendToUser(
                    $recipientId,
                    "Nouveau message de {$senderName}",
                    $snippet,
                    ['type' => 'chat_message', 'mission_id' => $mission->id, 'message_id' => $message->id]
                );
            } catch (\Throwable $e) {
                // Silencieux pour ne pas impacter l'envoi du message
            }
        }

        return response()->json([
            'success' => true,
            'message' => $filterResult['is_redacted']
                ? 'Message envoyé. Des coordonnées ont été automatiquement masquées par mesure de sécurité avant validation du devis.'
                : 'Message envoyé avec succès.',
            'data' => $message,
        ], 201);
    }

    /**
     * Marque un message spécifique comme lu.
     */
    public function markAsRead(Mission $mission, MissionMessage $message, Request $request): JsonResponse
    {
        if ($message->mission_id !== $mission->id) {
            return response()->json(['success' => false, 'message' => 'Message introuvable.'], 404);
        }

        if ($message->sender_id !== $request->user()->id && ! $message->read_at) {
            $message->update(['read_at' => now()]);
        }

        return response()->json(['success' => true]);
    }
}
