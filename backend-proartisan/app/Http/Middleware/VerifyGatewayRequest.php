<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authentifie les passerelles externes USSD et SMS entrant.
 *
 * Ces deux endpoints déclenchent la validation hors-ligne des retraits et des
 * livraisons, donc la libération de fonds vers le livreur et le fournisseur.
 * L'identité du livreur y est déduite d'un numéro de téléphone transporté
 * dans le corps de la requête (`phoneNumber`, `MSISDN`, `from`…), une donnée
 * que l'appelant choisit librement : sans authentification de la passerelle,
 * n'importe qui sachant le numéro d'un livreur peut confirmer une livraison
 * qui n'a jamais eu lieu.
 *
 * Le contrôle est volontairement « fail closed » : tant qu'aucun secret n'est
 * configuré, l'endpoint reste fermé. Un secret oublié en production doit
 * provoquer une panne visible, jamais une porte ouverte silencieuse.
 */
class VerifyGatewayRequest
{
    /**
     * Préfixe imposé par SMSpro devant la signature HMAC.
     */
    private const SIGNATURE_PREFIX = 'sha256=';

    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.gateway.secret', '');
        $signingSecret = (string) config('services.gateway.signing_secret', '');

        if ($secret === '' && $signingSecret === '') {
            Log::critical('Passerelle USSD/SMS : aucun secret configuré, requête refusée.', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return $this->deny($request, 'Passerelle non configuree.', 503);
        }

        $signature = (string) $request->header('X-Webhook-Signature', '');

        if ($signature !== '') {
            // Signature HMAC de l'opérateur : c'est la preuve la plus forte,
            // car elle couvre le contenu du message et pas seulement
            // l'identité de l'appelant. Une signature présente mais invalide
            // est un signal d'attaque : on refuse sans se rabattre sur le
            // secret partagé, qui serait un contournement trivial.
            if (! $this->signatureIsValid($request, $signingSecret)) {
                Log::warning('Passerelle USSD/SMS : signature HMAC invalide.', [
                    'ip' => $request->ip(),
                    'path' => $request->path(),
                ]);

                return $this->deny($request, 'Acces refuse.', 401);
            }

            return $this->enforceIpAllowlist($request, $next);
        }

        if ($secret === '') {
            Log::critical('Passerelle USSD/SMS : requête sans signature et aucun secret partagé configuré.', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return $this->deny($request, 'Passerelle non configuree.', 503);
        }

        // L'en-tête est la voie préférée. Certaines passerelles ne laissent
        // configurer qu'une URL de rappel, sans en-tête personnalisé : on
        // accepte alors le secret en paramètre d'URL. Les deux portent la même
        // valeur ; le paramètre est simplement plus exposé (journaux d'accès,
        // référents), d'où l'ordre de préférence.
        $provided = (string) $request->header('X-Gateway-Secret', '');

        if ($provided === '') {
            $provided = (string) $request->query('gateway_secret', '');
        }

        // hash_equals : comparaison à temps constant, pour ne pas laisser
        // fuiter le secret octet par octet via le temps de réponse.
        if ($provided === '' || ! hash_equals($secret, $provided)) {
            Log::warning('Passerelle USSD/SMS : secret absent ou invalide.', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return $this->deny($request, 'Acces refuse.', 401);
        }

        return $this->enforceIpAllowlist($request, $next);
    }

    /**
     * Vérifie la signature HMAC-SHA256 de SMSpro sur le corps brut.
     *
     * Le corps *brut* est indispensable : re-sérialiser le JSON décodé
     * changerait un espace ou l'ordre des clés et invaliderait la signature.
     */
    private function signatureIsValid(Request $request, string $signingSecret): bool
    {
        if ($signingSecret === '') {
            Log::critical('Passerelle USSD/SMS : signature reçue mais SMSPRO_WEBHOOK_SECRET absent.');

            return false;
        }

        $expected = self::SIGNATURE_PREFIX.hash_hmac('sha256', $request->getContent(), $signingSecret);

        return hash_equals($expected, (string) $request->header('X-Webhook-Signature', ''));
    }

    private function enforceIpAllowlist(Request $request, Closure $next): Response
    {
        $allowlist = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) config('services.gateway.ips', ''))
        )));

        if ($allowlist !== [] && ! in_array((string) $request->ip(), $allowlist, true)) {
            Log::warning('Passerelle USSD/SMS : IP hors liste blanche.', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return $this->deny($request, 'Acces refuse.', 403);
        }

        return $next($request);
    }

    /**
     * La passerelle USSD attend du texte brut ; le callback SMS, du JSON.
     */
    private function deny(Request $request, string $message, int $status): Response
    {
        if ($request->is('api/v1/ussd')) {
            return response("END {$message}", $status)
                ->header('Content-Type', 'text/plain');
        }

        return response()->json([
            'success' => false,
            'error' => $message,
        ], $status);
    }
}
