import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const usePathnameMock = vi.fn(() => '/artisans');
vi.mock('next/navigation', () => ({
    usePathname: () => usePathnameMock(),
}));

const getSettingsMock = vi.fn();
const logWhatsappClickMock = vi.fn();
vi.mock('@/lib/api', () => ({
    api: {
        getSettings: (...args: unknown[]) => getSettingsMock(...args),
        logWhatsappClick: (...args: unknown[]) => logWhatsappClickMock(...args),
    },
}));

import WhatsAppButton from './WhatsAppButton';

describe('WhatsAppButton', () => {
    beforeEach(() => {
        usePathnameMock.mockReturnValue('/artisans');
        getSettingsMock.mockReset();
        logWhatsappClickMock.mockReset();
    });

    it('n\'affiche rien tant que les réglages n\'ont pas chargé', () => {
        getSettingsMock.mockReturnValue(new Promise(() => {}));
        render(<WhatsAppButton />);

        expect(screen.queryByLabelText('Discuter avec ProsArtisan sur WhatsApp')).not.toBeInTheDocument();
    });

    it('n\'affiche rien quand le bouton est désactivé côté backoffice', async () => {
        getSettingsMock.mockResolvedValue({
            whatsapp_widget_enabled: '0',
            whatsapp_widget_phone: '+2250160606183',
        });
        render(<WhatsAppButton />);

        await waitFor(() => expect(getSettingsMock).toHaveBeenCalled());
        expect(screen.queryByLabelText('Discuter avec ProsArtisan sur WhatsApp')).not.toBeInTheDocument();
    });

    it('n\'affiche rien sans numéro configuré', async () => {
        getSettingsMock.mockResolvedValue({ whatsapp_widget_enabled: '1', whatsapp_widget_phone: '' });
        render(<WhatsAppButton />);

        await waitFor(() => expect(getSettingsMock).toHaveBeenCalled());
        expect(screen.queryByLabelText('Discuter avec ProsArtisan sur WhatsApp')).not.toBeInTheDocument();
    });

    it('affiche le bouton avec le lien click-to-chat correctement formé', async () => {
        getSettingsMock.mockResolvedValue({
            whatsapp_widget_enabled: '1',
            whatsapp_widget_phone: '+225 01 60 60 61 83',
            whatsapp_widget_message: 'Bonjour, je veux un devis.',
        });
        render(<WhatsAppButton />);

        const link = await screen.findByLabelText('Discuter avec ProsArtisan sur WhatsApp');

        expect(link).toHaveAttribute(
            'href',
            'https://api.whatsapp.com/send/?phone=2250160606183&text=Bonjour%2C%20je%20veux%20un%20devis.&type=phone_number&app_absent=0',
        );
    });

    it('utilise un message par défaut si aucun n\'est configuré', async () => {
        getSettingsMock.mockResolvedValue({
            whatsapp_widget_enabled: '1',
            whatsapp_widget_phone: '+2250160606183',
        });
        render(<WhatsAppButton />);

        const link = await screen.findByLabelText('Discuter avec ProsArtisan sur WhatsApp');

        expect(link.getAttribute('href')).toContain(
            encodeURIComponent('Bonjour ProsArtisan, je souhaite être mis en relation avec un artisan.'),
        );
    });

    it('journalise le clic avec la page courante', async () => {
        usePathnameMock.mockReturnValue('/contact');
        getSettingsMock.mockResolvedValue({
            whatsapp_widget_enabled: '1',
            whatsapp_widget_phone: '+2250160606183',
        });
        render(<WhatsAppButton />);

        const link = await screen.findByLabelText('Discuter avec ProsArtisan sur WhatsApp');
        fireEvent.click(link);

        expect(logWhatsappClickMock).toHaveBeenCalledWith('/contact');
    });
});
