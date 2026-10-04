<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isAccountActive()) {
            return $next($request);
        }

        // Le message reprend le motif enregistré : il annonçait « litiges
        // abusifs » quel que soit le motif réel de la suspension.
        $reason = trim((string) $user->account_status_reason);
        $status = $user->account_status === 'banni' ? 'Votre compte est banni.' : 'Votre compte est suspendu.';

        return response()->json([
            'success' => false,
            'message' => $reason !== ''
                ? "{$status} Motif : {$reason}"
                : "{$status} Contactez le support ProsArtisan.",
            'account_status' => $user->account_status,
            'reason' => $user->account_status_reason,
        ], 403);
    }
}
