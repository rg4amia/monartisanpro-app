import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const routerPost = vi.fn();
const routerPut = vi.fn();
vi.mock('@inertiajs/react', () => ({
    router: {
        post: (...args: unknown[]) => routerPost(...args),
        put: (...args: unknown[]) => routerPut(...args),
    },
}));

// La carte des coordonnées bancaires (useForm) a son propre test.
vi.mock('./BankTransferSettingsCard', () => ({
    BankTransferSettingsCard: () => <div>carte-virement-bancaire</div>,
}));

import type { SectorItem, SettingItem } from '../shared';
import { SettingsPanel } from './SettingsPanel';

function makeSetting(overrides: Partial<SettingItem> = {}): SettingItem {
    return {
        id: 1,
        key: 'commission_rate',
        value: '5',
        type: 'percent',
        group: 'commissions',
        label: 'Taux de commission',
        description: 'Commission plateforme sur chaque mission.',
        ...overrides,
    };
}

function makeSector(overrides: Partial<SectorItem> = {}): SectorItem {
    return {
        id: 1,
        name: 'Bâtiment',
        trades: [{ id: 1, sector_id: 1, name: 'Maçon' }],
        ...overrides,
    };
}

function renderPanel(overrides: Partial<React.ComponentProps<typeof SettingsPanel>> = {}) {
    const props: React.ComponentProps<typeof SettingsPanel> = {
        adminName: 'Admin ProsArtisan',
        adminContact: 'admin@prosartisan.ci',
        offlineActive: false,
        isOfflineSimulated: false,
        onToggleOfflineSimulated: vi.fn(),
        onRefresh: vi.fn(),
        settingsList: [makeSetting()],
        sectors: [makeSector()],
        expandedSectors: {},
        onToggleSector: vi.fn(),
        ...overrides,
    };
    render(<SettingsPanel {...props} />);
    return props;
}

describe('SettingsPanel', () => {
    beforeEach(() => {
        routerPost.mockClear();
        routerPut.mockClear();
    });

    it('affiche les règles métier critiques et la session admin', () => {
        renderPanel();

        expect(screen.getByText(/Le scan J-Code doit toujours vérifier/)).toBeInTheDocument();
        expect(screen.getByText('Admin ProsArtisan')).toBeInTheDocument();
        expect(screen.getByText('admin@prosartisan.ci')).toBeInTheDocument();
    });

    it('déclenche onRefresh et la déconnexion', () => {
        const props = renderPanel();

        fireEvent.click(screen.getByRole('button', { name: 'Rafraîchir les données' }));
        expect(props.onRefresh).toHaveBeenCalledTimes(1);

        fireEvent.click(screen.getByRole('button', { name: 'Se déconnecter' }));
        expect(routerPost).toHaveBeenCalledWith('/admin/logout');
    });

    it('déclenche onToggleOfflineSimulated', () => {
        const props = renderPanel();
        fireEvent.click(screen.getByRole('button', { name: 'Simuler Offline' }));
        expect(props.onToggleOfflineSimulated).toHaveBeenCalledTimes(1);
    });

    it('met à jour un paramètre texte à la perte de focus', () => {
        renderPanel({ settingsList: [makeSetting({ key: 'commission_rate', value: '5' })] });

        const input = screen.getByDisplayValue('5');
        fireEvent.change(input, { target: { value: '7' } });
        fireEvent.blur(input);

        expect(routerPut).toHaveBeenCalledWith(
            '/admin/settings/1',
            { value: '7' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('ne fait aucun appel si la valeur du paramètre n\'a pas changé', () => {
        renderPanel({ settingsList: [makeSetting({ value: '5' })] });

        const input = screen.getByDisplayValue('5');
        fireEvent.blur(input);

        expect(routerPut).not.toHaveBeenCalled();
    });

    it('met à jour un paramètre de blocage via le select dédié', () => {
        renderPanel({
            settingsList: [
                makeSetting({ id: 2, key: 'block_new_accounts', value: 'none' }),
            ],
        });

        fireEvent.change(screen.getByDisplayValue('Accès normal'), { target: { value: 'new' } });

        expect(routerPut).toHaveBeenCalledWith(
            '/admin/settings/2',
            { value: 'new' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('affiche un message si aucun paramètre n\'est configuré', () => {
        renderPanel({ settingsList: [] });
        expect(screen.getByText('Aucun paramètre trouvé.')).toBeInTheDocument();
    });

    it('crée une nouvelle catégorie (secteur)', () => {
        renderPanel();

        fireEvent.change(screen.getByPlaceholderText('Nom de la catégorie (ex: Électricité)'), {
            target: { value: 'Électricité' },
        });
        fireEvent.click(screen.getAllByRole('button', { name: 'Ajouter' })[0]);

        expect(routerPost).toHaveBeenCalledWith(
            '/admin/sectors',
            { name: 'Électricité' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('déplie une catégorie pour afficher ses métiers', () => {
        const props = renderPanel();

        fireEvent.click(screen.getByRole('button', { name: /sous-catégories/ }));

        expect(props.onToggleSector).toHaveBeenCalledWith(1);
    });

    it('affiche les métiers d\'une catégorie dépliée', () => {
        renderPanel({ expandedSectors: { 1: true } });
        expect(screen.getByDisplayValue('Maçon')).toBeInTheDocument();
    });

    it('met à jour le nom d\'un métier à la perte de focus', () => {
        renderPanel({ expandedSectors: { 1: true } });

        const input = screen.getByDisplayValue('Maçon');
        fireEvent.change(input, { target: { value: 'Maçon confirmé' } });
        fireEvent.blur(input);

        expect(routerPut).toHaveBeenCalledWith(
            '/admin/trades/1',
            { name: 'Maçon confirmé' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('affiche un message si aucune catégorie n\'est configurée', () => {
        renderPanel({ sectors: [] });
        expect(screen.getByText('Aucune catégorie trouvée.')).toBeInTheDocument();
    });
});
