import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import CookieConsent, { COOKIE_CONSENT_STORAGE_KEY, type CookiePreferences } from './CookieConsent';

function readStoredConsent(): CookiePreferences | null {
    const raw = window.localStorage.getItem(COOKIE_CONSENT_STORAGE_KEY);
    return raw ? (JSON.parse(raw) as CookiePreferences) : null;
}

describe('CookieConsent', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('n\'affiche pas immédiatement le bandeau au premier rendu', () => {
        render(<CookieConsent />);
        expect(screen.queryByText('Gestion de vos cookies & vie privée')).not.toBeInTheDocument();
    });

    it('affiche le bandeau après un court délai en l\'absence de consentement enregistré', () => {
        render(<CookieConsent />);

        act(() => {
            vi.advanceTimersByTime(800);
        });

        expect(screen.getByText('Gestion de vos cookies & vie privée')).toBeInTheDocument();
    });

    it('n\'affiche pas le bandeau si un consentement est déjà enregistré', () => {
        window.localStorage.setItem(
            COOKIE_CONSENT_STORAGE_KEY,
            JSON.stringify({ essential: true, analytics: true, preferences: true, timestamp: Date.now() }),
        );

        render(<CookieConsent />);
        act(() => {
            vi.advanceTimersByTime(1000);
        });

        expect(screen.queryByText('Gestion de vos cookies & vie privée')).not.toBeInTheDocument();
    });

    it('enregistre l\'acceptation totale et notifie les scripts tiers', () => {
        const listener = vi.fn();
        window.addEventListener('prosartisan-cookie-consent', listener);
        render(<CookieConsent />);
        act(() => {
            vi.advanceTimersByTime(800);
        });

        fireEvent.click(screen.getByRole('button', { name: 'Tout accepter' }));

        const stored = readStoredConsent();
        expect(stored?.analytics).toBe(true);
        expect(stored?.preferences).toBe(true);
        expect(listener).toHaveBeenCalledTimes(1);
        expect(screen.queryByText('Gestion de vos cookies & vie privée')).not.toBeInTheDocument();

        window.removeEventListener('prosartisan-cookie-consent', listener);
    });

    it('enregistre le refus des cookies non-essentiels', () => {
        render(<CookieConsent />);
        act(() => {
            vi.advanceTimersByTime(800);
        });

        fireEvent.click(screen.getByRole('button', { name: 'Refuser non-essentiels' }));

        const stored = readStoredConsent();
        expect(stored?.essential).toBe(true);
        expect(stored?.analytics).toBe(false);
        expect(stored?.preferences).toBe(false);
    });

    it('permet de personnaliser puis d\'enregistrer un choix mixte', () => {
        render(<CookieConsent />);
        act(() => {
            vi.advanceTimersByTime(800);
        });

        fireEvent.click(screen.getByRole('button', { name: /Personnaliser/ }));
        expect(screen.getByText('Centre de préférences des cookies')).toBeInTheDocument();

        const [analyticsToggle] = screen.getAllByRole('checkbox');
        fireEvent.click(analyticsToggle);
        fireEvent.click(screen.getByRole('button', { name: 'Enregistrer mes choix' }));

        const stored = readStoredConsent();
        expect(stored?.analytics).toBe(false);
        expect(stored?.preferences).toBe(true);
    });

    it('rouvre le bandeau de personnalisation sur l\'événement "open-cookie-settings"', () => {
        window.localStorage.setItem(
            COOKIE_CONSENT_STORAGE_KEY,
            JSON.stringify({ essential: true, analytics: true, preferences: true, timestamp: Date.now() }),
        );
        render(<CookieConsent />);

        act(() => {
            window.dispatchEvent(new Event('open-cookie-settings'));
        });

        expect(screen.getByText('Centre de préférences des cookies')).toBeInTheDocument();
    });
});
