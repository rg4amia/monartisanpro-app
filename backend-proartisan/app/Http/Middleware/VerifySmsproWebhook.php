<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vérifie la signature HMAC-SHA256 dont SMSpro accompagne chaque webhook.
 *
 * Contrairement à VerifyGatewayRequest — qui doit composer avec une passerelle
 * USSD tierce ne signant pas ses appels — ce contrôle n'accepte **que** la
 * signature : SMSpro la fournit systématiquement, tout repli sur un secret
 * partagé ne ferait qu'affaiblir la garantie sans rien apporter.
 *
 * Référence : `sha256=` . hash_hmac('sha256', corps brut, clé de signature),
 * en-tête `X-Webhook-Signature`.
 */
class VerifySmsproWebhook
{
    private const SIGNATURE_PREFIX = 'sha256=';

    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.gateway.signing_secret', '');

        if ($secret === '') {
            Log::critical('Webhook SMSpro : SMSPRO_WEBHOOK_SECRET absent, requête refusée.', [
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Webhook non configure.',
            ], 503);
        }

        // Corps brut impératif : re-sérialiser le JSON décodé modifierait un
        // espace ou l'ordre des clés et invaliderait la signature.
        $expected = self::SIGNATURE_PREFIX.hash_hmac('sha256', $request->getContent(), $secret);
        $provided = (string) $request->header('X-Webhook-Signature', '');

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            Log::warning('Webhook SMSpro : signature invalide.', [
                'ip' => $request->ip(),
                'has_signature' => $provided !== '',
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Signature invalide.',
            ], 401);
        }

        return $next($request);
    }
}
