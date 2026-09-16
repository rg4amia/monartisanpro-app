import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const getPopupMock = vi.fn();
vi.mock('@/lib/api', () => ({
    api: {
        getPopup: (...args: unknown[]) => getPopupMock(...args),
    },
}));

import PopupModal from './PopupModal';

const popupFixture = {
    id: 7,
    titre: 'Bénéficiez de 5% de remise',
    contenu: 'Utilisez le code PROMO5 avant la fin du mois.',
    image_url: null,
    lien_cta: '/artisans',
    texte_cta: 'Activer la remise',
};

describe('PopupModal', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        getPopupMock.mockReset();
        window.sessionStorage.clear();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('n\'affiche rien sans popup actif', async () => {
        getPopupMock.mockResolvedValue(null);
        render(<PopupModal />);

        await act(async () => {
            await Promise.resolve();
        });

        expect(screen.queryByText(/Bénéficiez de 5% de remise/)).not.toBeInTheDocument();
    });

    it('affiche le popup après le délai configuré', async () => {
        getPopupMock.mockResolvedValue(popupFixture);
        render(<PopupModal />);

        await act(async () => {
            await Promise.resolve();
        });
        act(() => {
            vi.advanceTimersByTime(1500);
        });

        expect(screen.getByText('Bénéficiez de 5% de remise')).toBeInTheDocument();
        expect(screen.getByText('Activer la remise')).toBeInTheDocument();
    });

    it('n\'affiche pas un popup déjà rejeté au cours de la session', async () => {
        window.sessionStorage.setItem('popup_dismissed_7', 'true');
        getPopupMock.mockResolvedValue(popupFixture);
        render(<PopupModal />);

        await act(async () => {
            await Promise.resolve();
        });
        act(() => {
            vi.advanceTimersByTime(2000);
        });

        expect(screen.queryByText('Bénéficiez de 5% de remise')).not.toBeInTheDocument();
    });

    it('mémorise le rejet et ferme le popup au clic sur "Fermer"', async () => {
        getPopupMock.mockResolvedValue(popupFixture);
        render(<PopupModal />);

        await act(async () => {
            await Promise.resolve();
        });
        act(() => {
            vi.advanceTimersByTime(1500);
        });

        // Le bouton croix (icône) et le bouton texte partagent l'intitulé
        // accessible "Fermer" : on cible le second, le bouton texte du pied.
        const closeButtons = screen.getAllByRole('button', { name: 'Fermer' });
        fireEvent.click(closeButtons[closeButtons.length - 1]);

        expect(window.sessionStorage.getItem('popup_dismissed_7')).toBe('true');
    });

    it('le lien de l\'offre pointe vers lien_cta et referme le popup', async () => {
        getPopupMock.mockResolvedValue(popupFixture);
        render(<PopupModal />);

        await act(async () => {
            await Promise.resolve();
        });
        act(() => {
            vi.advanceTimersByTime(1500);
        });

        const cta = screen.getByRole('link', { name: /Activer la remise/ });
        expect(cta).toHaveAttribute('href', '/artisans');
    });
});
