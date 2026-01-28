<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class LocaleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get locale from request header, query parameter, or user preference
        $locale = $this->getLocaleFromRequest($request);

        // Validate and set locale
        if ($this->isValidLocale($locale)) {
            App::setLocale($locale);
        }

        return $next($request);
    }

    /**
     * Get locale from various sources
     */
    private function getLocaleFromRequest(Request $request): string
    {
        // 1. Check Accept-Language header
        $acceptLanguage = $request->header('Accept-Language');
        if ($acceptLanguage) {
            $locale = $this->parseAcceptLanguageHeader($acceptLanguage);
            if ($this->isValidLocale($locale)) {
                return $locale;
            }
        }

        // 2. Check query parameter
        $queryLocale = $request->query('locale');
        if ($queryLocale && $this->isValidLocale($queryLocale)) {
            return $queryLocale;
        }

        // 3. Check user preference (if authenticated)
        if ($request->user() && method_exists($request->user(), 'getPreferredLocale')) {
            $userLocale = $request->user()->getPreferredLocale();
            if ($this->isValidLocale($userLocale)) {
                return $userLocale;
            }
        }

        // 4. Default to French
        return 'fr';
    }

    /**
     * Parse Accept-Language header
     */
    private function parseAcceptLanguageHeader(string $acceptLanguage): string
    {
        // Simple parsing - get first language code
        $languages = explode(',', $acceptLanguage);
        $firstLanguage = trim(explode(';', $languages[0])[0]);

        // Extract language code (e.g., 'fr-FR' -> 'fr')
        return substr($firstLanguage, 0, 2);
    }

    /**
     * Check if locale is valid/supported
     */
    private function isValidLocale(string $locale): bool
    {
        return in_array($locale, ['fr', 'en']);
    }
}
