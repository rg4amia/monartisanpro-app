import { fireEvent, render, screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const routerPut = vi.fn();
const routerPost = vi.fn();
const routerGet = vi.fn();

vi.mock('@inertiajs/react', () => ({
    router: {
        put: (...args: unknown[]) => routerPut(...args),
        post: (...args: unknown[]) => routerPost(...args),
        get: (...args: unknown[]) => routerGet(...args),
    },
    Link: ({ children }: { children: React.ReactNode }) => <a>{children}</a>,
}));

import type { ArtisanAvailabilityItem, DirectoryArtisanRow, DirectoryOptions, Paginated } from '../shared';
import { ArtisanDirectoryPanel } from './ArtisanDirectoryPanel';

const options: DirectoryOptions = {
    statuses: { disponible: 'Disponible', occupe: 'Occupé', conge: 'En congé' },
    days: { '1': 'Lundi', '2': 'Mardi', '3': 'Mercredi', '4': 'Jeudi', '5': 'Vendredi', '6': 'Samedi', '7': 'Dimanche' },
    max_slots: 14,
};

function availability(overrides: Partial<ArtisanAvailabilityItem> = {}): ArtisanAvailabilityItem {
    return {
        id: 11,
        status: 'disponible',
        status_label: 'Disponible',
        effective_status: 'disponible',
        effective_label: 'Disponible',
        until_date: null,
        schedule: [{ day: 1, start: '08:00', end: '17:00' }],
        schedule_summary: 'Lundi 08:00–17:00',
        night_work: false,
        review_status: 'validee',
        review_label: 'Validée',
        rejection_reason: null,
        submitted_at: null,
        reviewed_at: null,
        ...overrides,
    };
}

function row(overrides: Partial<DirectoryArtisanRow> = {}): DirectoryArtisanRow {
    return {
        id: 5,
        name: 'Koffi Yao',
        phone: '+2250700000001',
        trade: 'Plombier sanitaire',
        city: 'Cocody',
        score_prosartisan: 720,
        kyc_status: 'actif',
        account_status: 'actif',
        hidden: false,
        hidden_at: null,
        hidden_reason: null,
        listed: true,
        blockers: [],
        published: availability(),
        pending: availability({
            id: 12,
            status: 'conge',
            effective_status: 'conge',
            effective_label: "En congé jusqu'au 15/10/2026",
            until_date: '2026-10-15',
            review_status: 'en_attente',
            review_label: 'En attente de validation',
            schedule_summary: null,
            schedule: [],
        }),
        ...overrides,
    };
}

const page = (data: DirectoryArtisanRow[]): Paginated<DirectoryArtisanRow> =>
    ({ data, links: [], current_page: 1, last_page: 1, total: data.length, per_page: 25 }) as unknown as Paginated<DirectoryArtisanRow>;

const stats = { artisans: 3, published: 2, hidden: 1, pending: 1 };

describe('ArtisanDirectoryPanel', () => {
    beforeEach(() => {
        routerPost.mockReset();
        routerPut.mockReset();
        localStorage.clear();
    });

    it('montre la version publiée à côté de la déclaration à valider', () => {
        render(<ArtisanDirectoryPanel artisans={page([row()])} stats={stats} options={options} />);

        expect(screen.getByText('Lundi 08:00–17:00')).toBeInTheDocument();
        expect(within(screen.getByTestId('pending-5')).getByText("En congé jusqu'au 15/10/2026")).toBeInTheDocument();
    });

    it('valide une disponibilité en attente', () => {
        render(<ArtisanDirectoryPanel artisans={page([row()])} stats={stats} options={options} />);

        fireEvent.click(screen.getByRole('button', { name: 'Valider la disponibilité de Koffi Yao' }));
        expect(routerPost).toHaveBeenCalledWith('/admin/annuaire-artisans/disponibilites/12/valider', {}, expect.anything());
    });

    it('refuse une disponibilité avec un motif obligatoire', async () => {
        render(<ArtisanDirectoryPanel artisans={page([row()])} stats={stats} options={options} />);

        fireEvent.click(screen.getByRole('button', { name: 'Refuser la disponibilité de Koffi Yao' }));
        const dialog = await screen.findByRole('dialog');
        const confirmButton = within(dialog).getByRole('button', { name: 'Refuser' });
        expect(confirmButton).toBeDisabled();

        fireEvent.change(within(dialog).getByRole('textbox'), { target: { value: 'Horaires incohérents' } });
        fireEvent.click(confirmButton);
        await vi.waitFor(() =>
            expect(routerPost).toHaveBeenCalledWith('/admin/annuaire-artisans/disponibilites/12/refuser', { reason: 'Horaires incohérents' }, expect.anything()),
        );
    });

    it("désactive puis propose de réactiver une fiche dans l'annuaire", async () => {
        const { rerender } = render(<ArtisanDirectoryPanel artisans={page([row({ pending: null })])} stats={stats} options={options} />);

        fireEvent.click(screen.getByRole('button', { name: "Retirer Koffi Yao de l'annuaire" }));
        const dialog = await screen.findByRole('dialog');
        fireEvent.change(within(dialog).getByRole('textbox'), { target: { value: 'Photo inappropriée' } });
        fireEvent.click(within(dialog).getByRole('button', { name: 'Retirer' }));
        await vi.waitFor(() => expect(routerPost).toHaveBeenCalledWith('/admin/annuaire-artisans/5/retirer', { reason: 'Photo inappropriée' }, expect.anything()));

        rerender(
            <ArtisanDirectoryPanel
                artisans={page([row({ pending: null, hidden: true, listed: false, blockers: ["Retiré de l'annuaire"], hidden_reason: 'Photo inappropriée' })])}
                stats={stats}
                options={options}
            />,
        );
        expect(screen.getByText('Non visible')).toBeInTheDocument();
        expect(screen.getByText('Motif : Photo inappropriée')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: "Réactiver Koffi Yao dans l'annuaire" })).toBeInTheDocument();
    });

    it("publie directement la disponibilité saisie par l'administrateur", () => {
        render(<ArtisanDirectoryPanel artisans={page([row({ pending: null })])} stats={stats} options={options} />);

        fireEvent.click(screen.getByRole('button', { name: 'Saisir la disponibilité de Koffi Yao' }));
        const dialog = screen.getByRole('dialog');
        fireEvent.click(within(dialog).getByLabelText('Occupé'));
        expect(within(dialog).getByRole('button', { name: 'Publier' })).toBeDisabled();

        fireEvent.change(within(dialog).getByLabelText('Date de retour'), { target: { value: '2026-10-20' } });
        fireEvent.click(within(dialog).getByLabelText('Intervient la nuit'));
        fireEvent.click(within(dialog).getByRole('button', { name: 'Publier' }));

        expect(routerPut).toHaveBeenCalledWith(
            '/admin/annuaire-artisans/5/disponibilite',
            { status: 'occupe', until_date: '2026-10-20', schedule: [{ day: 1, start: '08:00', end: '17:00' }], night_work: true },
            expect.anything(),
        );
    });
});
