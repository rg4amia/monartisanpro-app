<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateNotificationPreferencesRequest;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\NotificationPreferenceService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notifications,
        private NotificationPreferenceService $preferences,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'domain' => ['sometimes', 'nullable', 'string', Rule::in(array_keys(NotificationCatalog::DOMAINS))],
        ], [
            'domain.in' => NotificationPreferenceService::UNKNOWN_DOMAIN_MESSAGE,
        ]);

        $list = $this->notifications->listFor(
            $request->user(),
            (int) ($filters['per_page'] ?? 30),
            $filters['domain'] ?? null,
        );

        return response()->json([
            'success' => true,
            'data' => NotificationResource::collection($list['page']->items()),
            'meta' => [
                'total' => $list['page']->total(),
                'current_page' => $list['page']->currentPage(),
                'last_page' => $list['page']->lastPage(),
                'unread' => $list['unread'],
                'domains' => $list['domains'],
            ],
        ]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Accès refusé.'], 403);
        }

        $this->notifications->markRead($notification);

        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->notifications->markAllRead($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Toutes les notifications marquées comme lues.',
        ]);
    }

    public function preferences(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->preferences->for($request->user()),
        ]);
    }

    public function updatePreferences(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Préférences enregistrées.',
            'data' => $this->preferences->update($request->user(), $request->validated()),
        ]);
    }
}
