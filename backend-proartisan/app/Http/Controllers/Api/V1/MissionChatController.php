<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\MissionMessage;
use App\Models\User;
use App\Services\AntiCircumventionService;
use App\Services\MissionHistoryService;
use App\Services\NotificationService;
use App\Services\RealtimeEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MissionChatController extends Controller
{
    /**
     * Types de fichier acceptés : type détecté dans le contenu → extension
     * d'enregistrement. L'extension ne vient jamais du nom transmis : le
     * fichier est publié sur le disque public, où un « .php » ou un « .html »
     * envoyé par un participant était servi tel quel.
     */
    private const IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/heic' => 'heic',
        'image/heif' => 'heic',
    ];

    private const AUDIO_TYPES = [
        'audio/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a',
        'audio/m4a' => 'm4a',
        'video/mp4' => 'm4a', // conteneur MP4 d'une note vocale, lu comme une vidéo
        'audio/aac' => 'aac',
        'audio/x-aac' => 'aac',
        'audio/x-hx-aac-adts' => 'aac',
        'audio/mpeg' => 'mp3',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/ogg' => 'ogg',
    ];

    /** Extensions de note vocale admises quand le contenu n'est pas reconnu. */
    private const AUDIO_EXTENSIONS = ['m4a', 'aac', 'mp3', 'wav', 'ogg'];

    public function __construct(
        private AntiCircumventionService $antiCircumvention,
        private NotificationService $notificationService
    ) {}

    /**
     * @return array{0: string, 1: string}|null [type du message, extension], ou null si le fichier est refusé
     */
    private function acceptedMedia(UploadedFile $file): ?array
    {
        $mime = strtolower((string) $file->getMimeType());

        if (isset(self::IMAGE_TYPES[$mime])) {
            return ['image', self::IMAGE_TYPES[$mime]];
        }

        if (isset(self::AUDIO_TYPES[$mime])) {
            return ['audio', self::AUDIO_TYPES[$mime]];
        }

        // Certains téléphones produisent une note vocale dont le contenu n'est
        // pas reconnu : elle est admise sur son extension, prise dans une liste
        // fermée de formats audio que le serveur n'exécute pas.
        $clientExtension = strtolower($file->getClientOriginalExtension());
        if ($mime === 'application/octet-stream' && in_array($clientExtension, self::AUDIO_EXTENSIONS, true)) {
            return ['audio', $clientExtension];
        }

        return null;
    }

    /**
     * Liste les messages d'une mission avec pagination.
     */
    public function index(Mission $mission, Request $request): JsonResponse
    {
        $user = $request->user();

        // Seuls les participants ou administrateurs ont accès au chat de chantier
        if ($mission->client_id !== $user->id && $mission->artisan_id !== $user->id && ! app(MissionHistoryService::class)->staffMayFollow($mission, $user)) {
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

        if ($mission->client_id !== $user->id && $mission->artisan_id !== $user->id && ! app(MissionHistoryService::class)->staffMayFollow($mission, $user)) {
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

            $media = $this->acceptedMedia($file);
            if ($media === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Fichier refusé : seules les photos (JPEG, PNG, WEBP, HEIC) et les notes vocales sont acceptées.',
                ], 422);
            }

            [$type, $extension] = $media;
            $filename = 'chat_'.Str::random(24).'.'.$extension;
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
            app(RealtimeEventService::class)->broadcast(
                $mission->id,
                'chat_message',
                $message->toArray()
            );
        } catch (\Throwable $e) {
            // Silencieux pour ne pas impacter l'envoi HTTP
        }

        // Notification du destinataire. Le service était injecté en optionnel
        // (résolu à null par le conteneur) et appelé via une méthode
        // inexistante, erreur avalée : aucun message de chantier n'était
        // jamais notifié (Chantier 14, lot A).
        $recipientId = ($user->id === $mission->client_id) ? $mission->artisan_id : $mission->client_id;
        $recipient = $recipientId ? User::find($recipientId) : null;
        if ($recipient) {
            try {
                $senderName = $user->name ?? 'Votre interlocuteur';
                $snippet = $type === 'text' ? Str::limit($message->content, 60) : ($type === 'audio' ? '🎙️ Message vocal' : '📷 Photo de chantier');
                $this->notificationService->notify(
                    $recipient,
                    'chat.nouveau_message',
                    ['expediteur' => $senderName, 'extrait' => $snippet],
                    ['mission_id' => $mission->id, 'message_id' => $message->id]
                );
            } catch (\Throwable $e) {
                // L'envoi du message ne doit pas échouer pour une notification,
                // mais la panne doit rester visible.
                Log::error('Notification de message de chantier impossible : '.$e->getMessage(), [
                    'mission_id' => $mission->id,
                    'message_id' => $message->id,
                ]);
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

        $user = $request->user();
        if ($mission->client_id !== $user->id && $mission->artisan_id !== $user->id && ! ($user->role === 'admin' && $user->can('admin.missions.view'))) {
            return response()->json(['success' => false, 'message' => 'Accès non autorisé à ce message.'], 403);
        }

        if ($message->sender_id !== $user->id && ! $message->read_at) {
            $message->update(['read_at' => now()]);
        }

        return response()->json(['success' => true]);
    }
}
