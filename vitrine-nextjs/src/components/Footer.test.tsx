import { act, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

const getSettingsMock = vi.fn();
vi.mock('@/lib/api', () => ({
    api: {
        getSettings: (...args: unknown[]) => getSettingsMock(...args),
    },
}));

import Footer from './Footer';

describe('Footer', () => {
    it('affiche les coordonnées par défaut tant que les réglages ne sont pas chargés', () => {
        getSettingsMock.mockReturnValue(new Promise(() => {}));
        render(<Footer />);

        expect(screen.getByText('+225 01 60 60 61 83')).toBeInTheDocument();
        expect(screen.getByText('info@prosartisan.net')).toBeInTheDocument();
        expect(screen.getByText(`© ${new Date().getFullYear()} ProsArtisan. Tous droits réservés.`)).toBeInTheDocument();
    });

    it('remplace les valeurs par défaut par les réglages du backoffice', async () => {
        getSettingsMock.mockResolvedValue({
            contact_phone: '+225 07 00 00 00 09',
            contact_email: 'contact@prosartisan.ci',
            footer_address: 'Plateau, Abidjan',
        });
        render(<Footer />);

        await act(async () => {
            await Promise.resolve();
        });

        expect(screen.getByText('+225 07 00 00 00 09')).toBeInTheDocument();
        expect(screen.getByText('contact@prosartisan.ci')).toBeInTheDocument();
        expect(screen.getByText('Plateau, Abidjan')).toBeInTheDocument();
    });

    it('construit les liens tel: et mailto: à partir des coordonnées', async () => {
        getSettingsMock.mockResolvedValue({ contact_phone: '+225 07 00 00 00 09' });
        render(<Footer />);
        await act(async () => {
            await Promise.resolve();
        });

        expect(screen.getByText('+225 07 00 00 00 09').closest('a')).toHaveAttribute(
            'href',
            'tel:+2250700000009',
        );
        expect(screen.getByText('info@prosartisan.net').closest('a')).toHaveAttribute(
            'href',
            'mailto:info@prosartisan.net',
        );
    });

    it('n\'affiche aucun réseau social sans lien configuré', () => {
        getSettingsMock.mockReturnValue(new Promise(() => {}));
        render(<Footer />);

        expect(screen.queryByLabelText('Facebook')).not.toBeInTheDocument();
        expect(screen.queryByLabelText('Instagram')).not.toBeInTheDocument();
    });

    it('affiche uniquement les réseaux sociaux configurés', async () => {
        getSettingsMock.mockResolvedValue({
            lien_facebook: 'https://facebook.com/prosartisan',
            lien_instagram: 'https://instagram.com/prosartisan',
        });
        render(<Footer />);
        await act(async () => {
            await Promise.resolve();
        });

        expect(screen.getByLabelText('Facebook')).toHaveAttribute('href', 'https://facebook.com/prosartisan');
        expect(screen.getByLabelText('Instagram')).toBeInTheDocument();
        expect(screen.queryByLabelText('LinkedIn')).not.toBeInTheDocument();
    });

    it('ouvre le centre de gestion des cookies depuis le plan du site et la barre inférieure', () => {
        getSettingsMock.mockReturnValue(new Promise(() => {}));
        const listener = vi.fn();
        window.addEventListener('open-cookie-settings', listener);
        render(<Footer />);

        screen.getByText('🍪 Gestion des cookies').click();
        screen.getByText('Gestion des cookies', { selector: 'button' }).click();

        expect(listener).toHaveBeenCalledTimes(2);
        window.removeEventListener('open-cookie-settings', listener);
    });

    it('affiche un lien permanent vers les CGU', () => {
        getSettingsMock.mockReturnValue(new Promise(() => {}));
        render(<Footer />);

        const cguLinks = screen.getAllByText('CGU & Mentions Légales');
        expect(cguLinks[0].closest('a')).toHaveAttribute('href', '/cgu');
    });
});
