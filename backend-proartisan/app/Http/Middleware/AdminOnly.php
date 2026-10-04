<?php

namespace App\Http\Middleware;

use App\Services\Admin\AdminActivityLogger;
use App\Services\Admin\AdminPermissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class AdminOnly
{
    public const SUSPENDED_MESSAGE = 'Ce compte administrateur est suspendu. Adressez-vous à un super administrateur.';

    public function __construct(
        private AdminPermissionService $permissions,
        private AdminActivityLogger $audit,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if (! $user) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié.',
                ], Response::HTTP_UNAUTHORIZED);
            }

            return redirect()->route('admin.login');
        }

        if ($user->role !== 'admin') {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé.',
                ], Response::HTTP_FORBIDDEN);
            }

            abort(Response::HTTP_FORBIDDEN, 'Accès non autorisé.');
        }

        // Un administrateur suspendu ou anonymisé perd le backoffice à sa
        // requête suivante : sans ce contrôle, la suspension restait sans effet.
        if (! $this->permissions->hasBackofficeAccess($user)) {
            return $this->closeSuspendedSession($request);
        }

        return $next($request);
    }

    private function closeSuspendedSession(Request $request): mixed
    {
        $user = $request->user();
        $isApi = $request->is('api/*');

        if (! $isApi && $request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->flash('error', self::SUSPENDED_MESSAGE);
        }

        $this->audit->log('admin.session.closed_suspended', $user, [
            'account_status' => $user->account_status,
            'anonymise' => $user->anonymized_at !== null,
        ], actor: $user);

        if ($isApi || $request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => self::SUSPENDED_MESSAGE,
            ], $isApi ? Response::HTTP_FORBIDDEN : Response::HTTP_UNAUTHORIZED);
        }

        $login = route('admin.login');

        return $request->header('X-Inertia') ? Inertia::location($login) : redirect()->to($login);
    }
}
