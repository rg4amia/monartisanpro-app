<?php

namespace App\Listeners;

use App\Models\Mission;
use App\Models\MissionStateTransition;
use Illuminate\Support\Facades\Auth;
use Spatie\ModelStates\Events\StateChanged;

class RecordMissionStateTransition
{
    /**
     * Handle the event.
     */
    public function handle(StateChanged $event): void
    {
        if (! ($event->model instanceof Mission)) {
            return;
        }

        $mission = $event->model;
        $fromState = $event->initialState ? $event->initialState->getValue() : 'unknown';
        $toState = $event->finalState ? $event->finalState->getValue() : 'unknown';

        // Évite de loguer les transitions idempotentes vers soi-même
        if ($fromState === $toState) {
            return;
        }

        $userId = Auth::id();
        $ip = request()?->ip();
        $userAgent = request()?->userAgent();

        MissionStateTransition::create([
            'mission_id' => $mission->id,
            'from_state' => $fromState,
            'to_state' => $toState,
            'user_id' => $userId,
            'reason' => request()?->input('reason') ?? null,
            'metadata_json' => [
                'ip' => $ip,
                'user_agent' => $userAgent,
            ],
            'created_at' => now(),
        ]);
    }
}
