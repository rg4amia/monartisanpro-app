<?php

namespace App\Listeners;

use App\Models\Mission;
use App\Models\MissionStateTransition;
use App\States\Mission\TransitionContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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

        // L'acteur et le motif viennent de MissionLifecycleService. Le motif
        // n'est plus lu dans la requête : tout champ `reason` posté par
        // l'appelant, quel qu'en soit l'objet, s'y retrouvait.
        $viaService = TransitionContext::isActive();

        MissionStateTransition::create([
            'mission_id' => $mission->id,
            'from_state' => $fromState,
            'to_state' => $toState,
            'user_id' => $viaService ? TransitionContext::actor()?->getKey() : Auth::id(),
            'reason' => $viaService ? Str::limit((string) TransitionContext::reason(), 255, '') ?: null : null,
            'metadata_json' => array_filter([
                'ip' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                ...TransitionContext::data(),
            ], fn ($value) => $value !== null),
            'created_at' => now(),
        ]);
    }
}
