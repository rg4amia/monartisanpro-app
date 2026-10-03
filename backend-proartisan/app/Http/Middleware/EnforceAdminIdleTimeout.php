<?php

namespace App\Http\Middleware;

use App\Services\Admin\AdminIdleSessionService;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ferme la session d'un administrateur resté inactif (Chantier 18, lot A).
 *
 * Toute requête compte comme une activité, sauf celles marquées
 * `X-Admin-Passive` : un rafraîchissement automatique lancé par l'écran ne
 * doit pas maintenir ouverte une session que personne n'utilise.
 */
class EnforceAdminIdleTimeout
{
    public const PASSIVE_HEADER = 'X-Admin-Passive';

    public function __construct(private AdminIdleSessionService $idle) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if (! $this->idle->concerns($request)) {
            return $next($request);
        }

        if ($this->idle->hasExpired($request)) {
            $this->idle->close($request, 'serveur');

            return $this->expiredResponse($request);
        }

        if (! $request->headers->has(self::PASSIVE_HEADER)) {
            $this->idle->touch($request);
        }

        return $next($request);
    }

    private function expiredResponse(Request $request): mixed
    {
        $login = route('admin.login');

        if ($request->header('X-Inertia')) {
            return Inertia::location($login);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $this->idle->expiredMessage(),
                'redirect' => $login,
            ], Response::HTTP_UNAUTHORIZED);
        }

        return redirect()->to($login);
    }
}
