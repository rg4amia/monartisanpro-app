<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KycVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->kyc_status !== 'actif') {
            return response()->json([
                'success' => false,
                'message' => 'Votre KYC doit être validé pour effectuer cette action.',
                'kyc_status' => $user?->kyc_status ?? 'en_attente',
            ], 403);
        }

        return $next($request);
    }
}
