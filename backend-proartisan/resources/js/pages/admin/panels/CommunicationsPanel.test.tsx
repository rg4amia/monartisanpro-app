import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const routerPost = vi.fn();
const routerDelete = vi.fn();
vi.mock('@inertiajs/react', () => ({
    router: {
        post: (...args: unknown[]) => routerPost(...args),
        delete: (...args: unknown[]) => routerDelete(...args),
    },
}));

import { CommunicationsPanel } from './CommunicationsPanel';

function makeComm(overrides: Record<string, unknown> = {}) {
    return {
        id: 9,
        type: 'annonce',
        statut: 'brouillon',
        titre: 'Maintenance planifiée',
        contenu: 'Une coupure de service est prévue dimanche.',
        cibles_json: ['client', 'artisan'],
        auteur: { name: 'Admin ProsArtisan' },
        created_at: '2026-02-01T00:00:00Z',
        publie_at: null,
        cloture_at: null,
        ...overrides,
    };
}

function renderPanel(overrides: Partial<React.ComponentProps<typeof CommunicationsPanel>> = {}) {
    const list = [makeComm()];
    const props: React.ComponentProps<typeof CommunicationsPanel> = {
        communications: list,
        filteredCommunications: list,
        search: '',
        onSearchChange: vi.fn(),
        commTypeFilter: 'all',
        onCommTypeFilterChange: vi.fn(),
        commStatusFilter: 'all',
        onCommStatusFilterChange: vi.fn(),
        onCreate: vi.fn(),
        onEdit: vi.fn(),
        ...overrides,
    };
    render(<CommunicationsPanel {...props} />);
    return props;
}

describe('CommunicationsPanel', () => {
    beforeEach(() => {
        routerPost.mockClear();
        routerDelete.mockClear();
    });

    it('affiche le titre, le contenu et les métriques de synthèse', () => {
        renderPanel();

        expect(screen.getAllByText('Maintenance planifiée').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Annonce').length).toBeGreaterThan(0);
    });

    it('affiche un état vide sans communication filtrée', () => {
        renderPanel({ filteredCommunications: [] });
        expect(screen.getByText('Aucune communication trouvée')).toBeInTheDocument();
        expect(screen.getByText(/Aucune communication enregistrée/)).toBeInTheDocument();
    });

    it('déclenche onCreate au clic sur "Nouvelle publication"', () => {
        const props = renderPanel();
        fireEvent.click(screen.getByRole('button', { name: /Nouvelle publication/ }));
        expect(props.onCreate).toHaveBeenCalledTimes(1);
    });

    it('déclenche onSearchChange à la saisie', () => {
        const props = renderPanel();
        fireEvent.change(screen.getByPlaceholderText('Rechercher par titre ou contenu...'), {
            target: { value: 'maintenance' },
        });
        expect(props.onSearchChange).toHaveBeenCalledWith('maintenance');
    });

    it('déclenche onCommTypeFilterChange et onCommStatusFilterChange', () => {
        const props = renderPanel();
        const [typeFilter, statusFilter] = screen.getAllByRole('combobox');

        fireEvent.change(typeFilter, { target: { value: 'le_saviez_vous' } });
        fireEvent.change(statusFilter, { target: { value: 'publie' } });

        expect(props.onCommTypeFilterChange).toHaveBeenCalledWith('le_saviez_vous');
        expect(props.onCommStatusFilterChange).toHaveBeenCalledWith('publie');
    });

    it('déclenche onEdit pour une communication modifiable', () => {
        const comm = makeComm({ statut: 'brouillon' });
        const props = renderPanel({ communications: [comm], filteredCommunications: [comm] });
        const actionSelects = screen.getAllByRole('combobox').slice(2);

        fireEvent.change(actionSelects[actionSelects.length - 1], { target: { value: 'edit' } });

        expect(props.onEdit).toHaveBeenCalledWith(comm);
    });

    it('publie un brouillon après confirmation', async () => {
        const comm = makeComm({ statut: 'brouillon' });
        renderPanel({ communications: [comm], filteredCommunications: [comm] });
        const actionSelects = screen.getAllByRole('combobox').slice(2);

        fireEvent.change(actionSelects[actionSelects.length - 1], { target: { value: 'publish' } });
        const dialog = await screen.findByRole('dialog');
        fireEvent.click(within(dialog).getByRole('button', { name: 'Publier' }));

        await waitFor(() =>
            expect(routerPost).toHaveBeenCalledWith(
                '/admin/communications/9/publish',
                {},
                expect.objectContaining({ preserveScroll: true }),
            ),
        );
    });

    it('désactive une publication active après confirmation', async () => {
        const comm = makeComm({ statut: 'publie' });
        renderPanel({ communications: [comm], filteredCommunications: [comm] });
        const actionSelects = screen.getAllByRole('combobox').slice(2);

        fireEvent.change(actionSelects[actionSelects.length - 1], { target: { value: 'cloturer' } });
        const dialog = await screen.findByRole('dialog');
        fireEvent.click(within(dialog).getByRole('button', { name: 'Désactiver' }));

        await waitFor(() =>
            expect(routerPost).toHaveBeenCalledWith(
                '/admin/communications/9/cloturer',
                {},
                expect.objectContaining({ preserveScroll: true }),
            ),
        );
    });

    it('supprime une communication après confirmation', async () => {
        const comm = makeComm({ statut: 'brouillon' });
        renderPanel({ communications: [comm], filteredCommunications: [comm] });
        const actionSelects = screen.getAllByRole('combobox').slice(2);

        fireEvent.change(actionSelects[actionSelects.length - 1], { target: { value: 'delete' } });
        const dialog = await screen.findByRole('dialog');
        fireEvent.click(within(dialog).getByRole('button', { name: 'Supprimer' }));

        await waitFor(() =>
            expect(routerDelete).toHaveBeenCalledWith(
                '/admin/communications/9',
                expect.objectContaining({ preserveScroll: true }),
            ),
        );
    });

    it('n\'offre ni modification ni suppression pour une publication active', () => {
        const comm = makeComm({ statut: 'publie' });
        renderPanel({ communications: [comm], filteredCommunications: [comm] });
        const actionSelects = screen.getAllByRole('combobox').slice(2);
        const desktopSelect = actionSelects[actionSelects.length - 1];

        expect(within(desktopSelect).queryByText('Modifier')).not.toBeInTheDocument();
        expect(within(desktopSelect).queryByText('Supprimer')).not.toBeInTheDocument();
        expect(within(desktopSelect).getByText('Désactiver')).toBeInTheDocument();
    });
});
