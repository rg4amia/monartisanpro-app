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

        return response()->json([
            'success' => false,
            'message' => $user->account_status === 'banni'
                ? 'Votre compte a été banni suite à des litiges répétés.'
                : 'Votre compte est temporairement bloqué en raison de litiges abusifs.',
            'account_status' => $user->account_status,
            'reason' => $user->account_status_reason,
        ], 403);
    }
}
