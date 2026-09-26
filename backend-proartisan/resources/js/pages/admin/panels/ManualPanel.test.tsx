import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { MANUAL_DOCUMENT_URL, MANUAL_DOWNLOAD_URL, ManualPanel } from './ManualPanel';

describe('ManualPanel', () => {
    it('affiche le manuel et propose de le télécharger', () => {
        render(
            <ManualPanel
                manual={{
                    available: true,
                    html: '<h1>Manuel</h1>',
                    updatedAt: '2026-09-26T18:00:00+00:00',
                    sizeKb: 46,
                }}
            />,
        );

        const frame = screen.getByTitle("Manuel d'utilisation ProsArtisan");
        expect(frame.getAttribute('srcdoc')).toBe('<h1>Manuel</h1>');
        // Aucun script n'est autorisé dans le document encadré.
        expect(frame.getAttribute('sandbox')).toBe('');

        const download = screen.getByRole('link', { name: 'Télécharger (HTML)' });
        expect(download.getAttribute('href')).toBe(MANUAL_DOWNLOAD_URL);
        expect(download.hasAttribute('download')).toBe(true);
        expect(screen.getByRole('link', { name: 'Ouvrir en plein écran' }).getAttribute('href')).toBe(MANUAL_DOCUMENT_URL);
        expect(screen.getByText(/Mis à jour le 26 septembre 2026/)).toBeTruthy();
    });

    it('signale un manuel absent au lieu d’un cadre vide', () => {
        render(<ManualPanel manual={{ available: false, html: null, updatedAt: null, sizeKb: null }} />);

        expect(screen.getByText('Manuel introuvable')).toBeTruthy();
        expect(screen.queryByTitle("Manuel d'utilisation ProsArtisan")).toBeNull();
    });
});
