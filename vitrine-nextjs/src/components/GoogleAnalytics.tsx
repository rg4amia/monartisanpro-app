'use client';

import { useEffect, useState } from 'react';
import Script from 'next/script';
import { COOKIE_CONSENT_STORAGE_KEY, type CookiePreferences } from './CookieConsent';

const GA_MEASUREMENT_ID = 'G-JZ32VTRQSP';

/**
 * Charge Google Analytics uniquement si l'utilisateur a explicitement
 * accepté les cookies de mesure d'audience (RGPD — Règle d'or 11).
 * Tant qu'aucun choix n'est enregistré, ou si les cookies analytiques
 * sont refusés, aucun script gtag n'est injecté dans la page.
 */
export default function GoogleAnalytics() {
    const [analyticsAllowed, setAnalyticsAllowed] = useState(false);

    useEffect(() => {
        const readConsent = (): boolean => {
            try {
                const stored = localStorage.getItem(COOKIE_CONSENT_STORAGE_KEY);
                if (!stored) return false;
                const parsed: CookiePreferences = JSON.parse(stored);
                return parsed.analytics === true;
            } catch {
                return false;
            }
        };

        setAnalyticsAllowed(readConsent());

        const handleConsentUpdate = (event: Event) => {
            const detail = (event as CustomEvent<CookiePreferences>).detail;
            const allowed = detail?.analytics === true;
            setAnalyticsAllowed(allowed);
            // Si l'utilisateur retire son consentement après coup, on demande à
            // gtag.js (déjà chargé) d'arrêter la collecte, conformément au
            // mécanisme d'opt-out documenté par Google.
            if (!allowed) {
                (window as unknown as Record<string, boolean>)[`ga-disable-${GA_MEASUREMENT_ID}`] = true;
            }
        };

        window.addEventListener('prosartisan-cookie-consent', handleConsentUpdate);
        return () => window.removeEventListener('prosartisan-cookie-consent', handleConsentUpdate);
    }, []);

    if (!analyticsAllowed) return null;

    return (
        <>
            <Script strategy="afterInteractive" src={`https://www.googletagmanager.com/gtag/js?id=${GA_MEASUREMENT_ID}`} />
            <Script id="google-analytics" strategy="afterInteractive">
                {`
                    window.dataLayer = window.dataLayer || [];
                    function gtag(){dataLayer.push(arguments);}
                    gtag('js', new Date());
                    gtag('config', '${GA_MEASUREMENT_ID}');
                `}
            </Script>
        </>
    );
}
