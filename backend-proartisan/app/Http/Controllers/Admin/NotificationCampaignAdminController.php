<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationCampaign;
use App\Services\Admin\NotificationCampaignAdminService;
use App\Services\Admin\NotificationTemplateAdminService;
use App\Services\Notifications\NotificationCampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Campagnes push et SMS du backoffice (Chantier 14, lot D). Capacité
 * `admin.notifications.broadcast` portée par les routes.
 */
class NotificationCampaignAdminController extends Controller
{
    public function __construct(private NotificationCampaignAdminService $service) {}

    public function searchUsers(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->searchUsers((string) $request->query('q', ''))]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->service->create($this->validateCampaign($request), $request->user());

        return back()->with('success', 'Campagne enregistrée en brouillon.');
    }

    public function update(Request $request, NotificationCampaign $campaign): RedirectResponse
    {
        $this->service->update($campaign, $this->validateCampaign($request), $request->user());

        return back()->with('success', 'Campagne enregistrée.');
    }

    public function destroy(Request $request, NotificationCampaign $campaign): RedirectResponse
    {
        $this->service->delete($campaign, $request->user());

        return back()->with('success', 'Campagne supprimée.');
    }

    public function duplicate(Request $request, NotificationCampaign $campaign): RedirectResponse
    {
        $this->service->duplicate($campaign, $request->user());

        return back()->with('success', 'Campagne dupliquée en brouillon.');
    }

    public function schedule(Request $request, NotificationCampaign $campaign): RedirectResponse
    {
        $validated = $request->validate([
            'scheduled_at' => 'nullable|date|after_or_equal:'.now()->subMinutes(5)->toDateTimeString(),
        ], [
            'scheduled_at.after_or_equal' => 'La date d\'envoi est déjà passée.',
        ]);

        $at = filled($validated['scheduled_at'] ?? null) ? Carbon::parse($validated['scheduled_at']) : null;
        $this->service->schedule($campaign, $at, $request->user());

        return back()->with('success', $at && $at->isFuture()
            ? 'Campagne programmée pour le '.$at->timezone(config('app.timezone'))->format('d/m/Y à H:i').'.'
            : 'Envoi lancé : la campagne part dans la minute.');
    }

    public function cancel(Request $request, NotificationCampaign $campaign): RedirectResponse
    {
        $this->service->cancel($campaign, $request->user());

        return back()->with('success', 'Campagne annulée.');
    }

    public function test(Request $request, NotificationCampaign $campaign): RedirectResponse
    {
        $validated = $request->validate([
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:push,sms',
        ]);

        $results = $this->service->sendTest($campaign, $validated['channels'], $request->user());

        $labels = ['push' => 'Push', 'sms' => 'SMS'];
        $statuses = NotificationTemplateAdminService::DELIVERY_STATUS_LABELS;
        $summary = collect($results)
            ->map(fn (array $result, string $channel) => $labels[$channel].' : '.mb_strtolower($statuses[$result['status']] ?? $result['status'])
                .($result['reason'] ? " ({$result['reason']})" : ''))
            ->implode(' · ');

        $failed = $results === [] || collect($results)->contains(fn (array $result) => $result['status'] !== 'envoye');

        return back()->with($failed ? 'error' : 'success', 'Envoi de test — '.($summary !== '' ? $summary : 'aucun texte à envoyer sur ce canal').'.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCampaign(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'nature' => ['required', Rule::in(array_keys(NotificationCampaign::NATURE_LABELS))],
            'push_title' => 'nullable|string|max:255',
            'push_body' => 'nullable|string|max:1000',
            'sms_body' => 'nullable|string|max:1000',
            'channel_in_app' => 'required|boolean',
            'channel_push' => 'required|boolean',
            'channel_sms' => 'required|boolean',
            'target' => 'array',
            'target.roles' => 'array',
            'target.roles.*' => Rule::in(array_keys(NotificationCampaignService::ROLES)),
            'target.commune_ids' => 'array',
            'target.commune_ids.*' => 'integer|exists:communes,id',
            'target.kyc_statuses' => 'array',
            'target.kyc_statuses.*' => Rule::in(array_keys(NotificationCampaignService::KYC_STATUSES)),
            'target.user_ids' => 'array|max:'.NotificationCampaignService::MAX_SELECTED_USERS,
            'target.user_ids.*' => 'integer|exists:users,id',
            'open_screen' => ['required', Rule::in(array_keys(NotificationCampaign::SCREEN_LABELS))],
            'communication_id' => 'nullable|integer',
        ], [
            'name.required' => 'Donnez un nom à la campagne.',
            'target.user_ids.max' => 'Au plus '.NotificationCampaignService::MAX_SELECTED_USERS.' utilisateurs désignés un à un.',
        ]);
    }
}
