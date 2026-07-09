<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Headers de sécurité recommandés par l'OWASP
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Content Security Policy dynamique (compatible avec Vite en local et Inertia en production)
        $cspDirectives = [
            "default-src 'self'",
            "img-src 'self' data: https:",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "font-src 'self' https://fonts.bunny.net data:",
            "frame-ancestors 'none'",
        ];

        if (!app()->isProduction()) {
            $vitePort = '5173';
            $hotFile = public_path('hot');
            if (file_exists($hotFile)) {
                $hotUrl = trim(file_get_contents($hotFile));
                $parsedUrl = parse_url($hotUrl);
                if (isset($parsedUrl['port'])) {
                    $vitePort = $parsedUrl['port'];
                }
            }
            // En développement local, on autorise Vite HMR, le websocket et le Hot Reload avec le port actif et des wildcards
            $cspDirectives[] = "script-src 'self' 'unsafe-inline' 'unsafe-eval' http://localhost:{$vitePort} http://127.0.0.1:{$vitePort} http://[::1]:{$vitePort} http://localhost:* http://127.0.0.1:*";
            $cspDirectives[] = "connect-src 'self' ws://localhost:{$vitePort} ws://127.0.0.1:{$vitePort} ws://[::1]:{$vitePort} http://localhost:{$vitePort} http://127.0.0.1:{$vitePort} http://[::1]:{$vitePort} ws://localhost:* ws://127.0.0.1:* http://localhost:* http://127.0.0.1:* http://127.0.0.1:8000 http://localhost:8000";
        } else {
            // En production, on reste plus restrictif mais compatible avec le démarrage d'Inertia
            $cspDirectives[] = "script-src 'self' 'unsafe-inline'";
            $cspDirectives[] = "connect-src 'self'";
        }

        $response->headers->set('Content-Security-Policy', implode('; ', $cspDirectives));

        // Limitation des APIs matérielles du navigateur (sécurité client)
        $response->headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=()');

        // Force le protocole HTTPS en production (HSTS)
        if ($request->isSecure() || app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
