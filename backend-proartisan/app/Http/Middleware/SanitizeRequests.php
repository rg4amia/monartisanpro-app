<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeRequests
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
        $input = $request->all();

        // Assainir récursivement toutes les chaînes de caractères
        array_walk_recursive($input, function (&$val) {
            if (is_string($val)) {
                // Supprimer les balises HTML et script pour bloquer les injections XSS
                $val = strip_tags($val);
                // Retirer les espaces inutiles au début/fin
                $val = trim($val);
            }
        });

        $request->merge($input);

        return $next($request);
    }
}
