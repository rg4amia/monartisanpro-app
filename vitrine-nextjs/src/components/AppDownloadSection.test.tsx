import { render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const getAppLinksMock = vi.fn();
vi.mock('@/lib/api', () => ({
    api: {
        getAppLinks: (...args: unknown[]) => getAppLinksMock(...args),
    },
}));

import AppDownloadSection from './AppDownloadSection';

const PLAY = 'https://play.google.com/store/apps/details?id=com.prosartisan.app';
const APPLE = 'https://apps.apple.com/ci/app/prosartisan/id6450000001';

describe('AppDownloadSection', () => {
    beforeEach(() => {
        getAppLinksMock.mockReset();
    });

    it("reste masquée tant qu'aucun lien n'est validé", async () => {
        getAppLinksMock.mockResolvedValue([]);
        const { container } = render(<AppDownloadSection />);

        await waitFor(() => expect(getAppLinksMock).toHaveBeenCalled());
        expect(container).toBeEmptyDOMElement();
    });

    it("n'affiche que le badge du magasin publié", async () => {
        getAppLinksMock.mockResolvedValue([{ platform: 'android', label: 'Google Play', url: PLAY }]);
        render(<AppDownloadSection />);

        const badge = await screen.findByRole('link', { name: "Télécharger l'application sur Google Play" });
        expect(badge).toHaveAttribute('href', PLAY);
        expect(badge).toHaveAttribute('target', '_blank');
        expect(badge).toHaveAttribute('rel', 'noopener noreferrer');
        expect(screen.queryByRole('link', { name: "Télécharger l'application dans l'App Store" })).not.toBeInTheDocument();
        expect(screen.getByRole('heading', { name: /Téléchargez l'application ProsArtisan/ })).toBeInTheDocument();
    });

    it('affiche les deux badges quand les deux liens sont publiés', async () => {
        getAppLinksMock.mockResolvedValue([
            { platform: 'android', label: 'Google Play', url: PLAY },
            { platform: 'ios', label: 'App Store', url: APPLE },
        ]);
        render(<AppDownloadSection />);

        expect(await screen.findByRole('link', { name: "Télécharger l'application dans l'App Store" })).toHaveAttribute('href', APPLE);
        expect(screen.getByRole('link', { name: "Télécharger l'application sur Google Play" })).toBeInTheDocument();
    });
});
