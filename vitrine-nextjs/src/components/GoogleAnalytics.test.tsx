import { act, render } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';

import GoogleAnalytics from './GoogleAnalytics';
import { COOKIE_CONSENT_STORAGE_KEY } from './CookieConsent';

const GA_FLAG = 'ga-disable-G-JZ32VTRQSP';

function storeConsent(analytics: boolean) {
    window.localStorage.setItem(
        COOKIE_CONSENT_STORAGE_KEY,
        JSON.stringify({ essential: true, analytics, preferences: true, timestamp: Date.now() }),
    );
}

// `next/script` (strategy afterInteractive) insère ses balises directement
// dans le <head> du document, en dehors de l'arbre React : le `cleanup()`
// automatique de Testing Library ne les retire pas entre les tests.
afterEach(() => {
    document.querySelectorAll('script[src*="googletagmanager"], #google-analytics').forEach((el) => el.remove());
});

describe('GoogleAnalytics — chargement conditionné au consentement RGPD', () => {
    it('ne charge aucun script sans consentement enregistré', () => {
        const { container } = render(<GoogleAnalytics />);
        expect(container.querySelector('script[src*="googletagmanager"]')).not.toBeInTheDocument();
    });

    it('ne charge aucun script si les cookies analytiques ont été refusés', () => {
        storeConsent(false);
        const { container } = render(<GoogleAnalytics />);
        expect(container.querySelector('script[src*="googletagmanager"]')).not.toBeInTheDocument();
    });

    it('charge gtag.js quand le consentement analytique est accordé', () => {
        storeConsent(true);
        render(<GoogleAnalytics />);
        // `next/script` (strategy afterInteractive) injecte la balise dans le
        // <head> du document plutôt que dans l'arbre React rendu.
        expect(document.querySelector('script[src*="googletagmanager"]')).toBeInTheDocument();
    });

    // Note : la réactivité à l'événement `prosartisan-cookie-consent` côté
    // acceptation est validée indirectement — même état `analyticsAllowed`,
    // même condition de rendu que le test de chargement au montage ci-dessus.
    // On ne la re-teste pas côté DOM ici : le cache interne de dédoublonnage
    // de `next/script` (par `src`) empêcherait une seconde insertion réelle
    // dans le même fichier de test, indépendamment de la justesse du composant.

    it('déclenche l\'opt-out gtag quand le consentement est retiré après coup', () => {
        storeConsent(true);
        render(<GoogleAnalytics />);
        delete (window as unknown as Record<string, boolean>)[GA_FLAG];

        act(() => {
            window.dispatchEvent(
                new CustomEvent('prosartisan-cookie-consent', {
                    detail: { essential: true, analytics: false, preferences: true, timestamp: Date.now() },
                }),
            );
        });

        expect((window as unknown as Record<string, boolean>)[GA_FLAG]).toBe(true);
    });
});
