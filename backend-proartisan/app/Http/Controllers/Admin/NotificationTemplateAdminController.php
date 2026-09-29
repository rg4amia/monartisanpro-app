<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\NotificationTemplateAdminService;
use App\Services\Notifications\NotificationCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Messages push et SMS du backoffice (Chantier 14, lot C). Capacité
 * `admin.notifications.manage` portée par les routes.
 */
class NotificationTemplateAdminController extends Controller
{
    public function __construct(private NotificationTemplateAdminService $service) {}

    public function update(Request $request, string $event): RedirectResponse
    {
        abort_unless(NotificationCatalog::has($event), 404);

        $validated = $request->validate([
            'push_title' => 'nullable|string',
            'push_body' => 'nullable|string',
            'sms_body' => 'nullable|string',
            'channel_in_app' => 'nullable|boolean',
            'channel_push' => 'nullable|boolean',
            'channel_sms' => 'nullable|boolean',
        ]);

        $this->service->update($event, $validated, $request->user());

        return back()->with('success', 'Message enregistré.');
    }

    public function reset(Request $request, string $event): RedirectResponse
    {
        abort_unless(NotificationCatalog::has($event), 404);

        $this->service->reset($event, $request->user());

        return back()->with('success', 'Texte d\'origine rétabli.');
    }

    public function test(Request $request, string $event): RedirectResponse
    {
        abort_unless(NotificationCatalog::has($event), 404);

        $validated = $request->validate([
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:push,sms',
        ]);

        $results = $this->service->sendTest($event, $validated['channels'], $request->user());

        $labels = ['push' => 'Push', 'sms' => 'SMS'];
        $statuses = NotificationTemplateAdminService::DELIVERY_STATUS_LABELS;
        $summary = collect($results)
            ->map(fn (array $result, string $channel) => $labels[$channel].' : '.mb_strtolower($statuses[$result['status']] ?? $result['status'])
                .($result['reason'] ? " ({$result['reason']})" : ''))
            ->implode(' · ');

        $failed = collect($results)->contains(fn (array $result) => $result['status'] !== 'envoye');

        return back()->with($failed ? 'error' : 'success', 'Envoi de test — '.($summary !== '' ? $summary : 'aucun canal disponible').'.');
    }
}
