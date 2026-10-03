<?php

namespace App\Services\Admin;

use App\Http\Controllers\Admin\ImpersonationController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Fermeture de la session du backoffice après inactivité (Chantier 18, lot A).
 *
 * Le serveur est seul juge du délai : l'écran prévient et redirige, mais une
 * session restée ouverte dans un navigateur sans JavaScript, ou reprise par
 * un tiers sur un poste laissé sans surveillance, expire de la même façon.
 */
class AdminIdleSessionService
{
    /** Horodatage (secondes Unix) de la dernière activité de la session. */
    public const SESSION_KEY = 'admin_last_activity_at';

    public function __construct(private AdminActivityLogger $audit) {}

    public function timeoutMinutes(): int
    {
        return max(1, (int) config('prosartisan.admin.idle_timeout_minutes', 15));
    }

    public function timeoutSeconds(): int
    {
        return $this->timeoutMinutes() * 60;
    }

    /**
     * Sessions soumises au délai : celle d'un administrateur, et celle qu'il
     * a basculée sur un autre compte (usurpation, Règle d'or 24).
     */
    public function concerns(Request $request): bool
    {
        $user = $request->user();

        if (! $user || ! $request->hasSession()) {
            return false;
        }

        return $user->role === 'admin' || $request->session()->has(ImpersonationController::SESSION_KEY);
    }

    public function hasExpired(Request $request): bool
    {
        $last = $request->session()->get(self::SESSION_KEY);

        if ($last === null) {
            // Session recréée par le cookie « Se souvenir de moi » : la
            // précédente a expiré sans activité, la reconnexion est due.
            return Auth::guard('web')->viaRemember();
        }

        return now()->timestamp - (int) $last >= $this->timeoutSeconds();
    }

    public function touch(Request $request): void
    {
        $request->session()->put(self::SESSION_KEY, now()->timestamp);
    }

    /**
     * Ferme la session et prépare le message affiché sur la page de connexion.
     *
     * @param  string  $origin  `serveur` (requête arrivée trop tard) ou `ecran` (délai constaté par le navigateur).
     */
    public function close(Request $request, string $origin): void
    {
        $user = $request->user();
        $impersonatorId = $request->session()->get(ImpersonationController::SESSION_KEY);
        $actor = $impersonatorId ? User::find($impersonatorId) : $user;

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->flash('error', $this->expiredMessage());

        $this->audit->log('admin.session.expired_idle', $user, [
            'delai_minutes' => $this->timeoutMinutes(),
            'origine' => $origin,
            'usurpation' => $impersonatorId !== null,
        ], actor: $actor);
    }

    public function expiredMessage(): string
    {
        return sprintf(
            'Votre session a été fermée après %d minutes d\'inactivité. Reconnectez-vous.',
            $this->timeoutMinutes(),
        );
    }
}
