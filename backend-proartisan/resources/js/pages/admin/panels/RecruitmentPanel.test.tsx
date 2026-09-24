import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { useState } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const routerPost = vi.fn();
const formPost = vi.fn();

vi.mock('@inertiajs/react', () => ({
    router: { post: (...args: unknown[]) => routerPost(...args) },
    useForm: (initial: Record<string, unknown>) => {
        const [data, setDataState] = useState(initial);
        return {
            data,
            setData: (key: string, value: unknown) =>
                setDataState((current) => ({ ...current, [key]: value })),
            post: (url: string, opts?: { onSuccess?: () => void; onFinish?: () => void }) => {
                formPost(url, data);
                opts?.onSuccess?.();
                opts?.onFinish?.();
            },
            processing: false,
            recentlySuccessful: false,
        };
    },
}));

import type { Paginated, RecruitmentOfferItem, RecruitmentSettings, RecruitmentStats } from '../shared';
import { RecruitmentPanel } from './RecruitmentPanel';

const stats: RecruitmentStats = { total: 10, pending_review: 2, active: 6, filled: 2 };
const settings: RecruitmentSettings = { client_posting_enabled: '1', fournisseur_posting_enabled: '1' };

function makeOffer(overrides: Partial<RecruitmentOfferItem> = {}): RecruitmentOfferItem {
    return {
        id: 5,
        creator_id: 1,
        creator_type: 'client',
        trade_id: 1,
        title: 'Pose carrelage villa Cocody',
        description: 'Chantier 3 jours',
        mission_type: 'journalier',
        commune: 'Cocody',
        sous_quartier: null,
        date_debut: '2026-03-01T00:00:00Z',
        daily_rate_min: 10000,
        daily_rate_max: 15000,
        openings_count: 2,
        deadline_at: '2026-03-10T00:00:00Z',
        status: 'pending_review',
        created_at: '2026-02-01T00:00:00Z',
        trade: { id: 1, name: 'Carreleur' },
        creator: { id: 1, name: 'Awa Traoré' },
        applications_count: 0,
        ...overrides,
    } as RecruitmentOfferItem;
}

function makePage(data: RecruitmentOfferItem[]): Paginated<RecruitmentOfferItem> {
    return {
        data,
        links: [],
        current_page: 1,
        last_page: 1,
        total: data.length,
        per_page: 15,
        from: data.length > 0 ? 1 : null,
        to: data.length > 0 ? data.length : null,
    };
}

function renderPanel(overrides: Partial<React.ComponentProps<typeof RecruitmentPanel>> = {}) {
    const props: React.ComponentProps<typeof RecruitmentPanel> = {
        recruitmentOffersPage: makePage([makeOffer()]),
        recruitmentStats: stats,
        recruitmentSettings: settings,
        search: '',
        onSearchChange: vi.fn(),
        onSubmit: vi.fn((e: React.FormEvent) => e.preventDefault()),
        onReset: vi.fn(),
        renderPagination: () => null,
        canManage: true,
        ...overrides,
    };
    render(<RecruitmentPanel {...props} />);
    return props;
}

describe('RecruitmentPanel', () => {
    beforeEach(() => {
        routerPost.mockClear();
        formPost.mockClear();
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => ({ data: [] }) }));
    });

    afterEach(() => vi.unstubAllGlobals());

    it('affiche les KPI et les offres de la liste', () => {
        renderPanel();

        expect(screen.getByText('Pose carrelage villa Cocody')).toBeInTheDocument();
        expect(screen.getByText('Carreleur')).toBeInTheDocument();
        expect(screen.getByText('Cocody')).toBeInTheDocument();
        expect(screen.getAllByText('En attente de modération').length).toBeGreaterThan(0);
    });

    it('affiche un état vide sans aucune offre', () => {
        renderPanel({ recruitmentOffersPage: makePage([]) });
        expect(screen.getByText('Aucune offre de recrutement')).toBeInTheDocument();
    });

    it('désactive le lien candidatures quand applications_count vaut 0', () => {
        renderPanel({ recruitmentOffersPage: makePage([makeOffer({ applications_count: 0 })]) });
        expect(screen.getByRole('button', { name: '0' })).toBeDisabled();
    });

    it('masque la colonne actions sans la capacité admin.recruitment.manage', () => {
        renderPanel({ canManage: false });
        expect(screen.queryByRole('button', { name: 'Approuver' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Voir candidats' })).not.toBeInTheDocument();
    });

    it('approuve une offre après confirmation', async () => {
        renderPanel();

        fireEvent.click(screen.getByRole('button', { name: 'Approuver' }));
        const dialog = await screen.findByRole('dialog');
        fireEvent.click(within(dialog).getByRole('button', { name: 'Approuver' }));

        await waitFor(() =>
            expect(routerPost).toHaveBeenCalledWith(
                '/admin/recruitment/5/approve',
                {},
                expect.objectContaining({ preserveScroll: true }),
            ),
        );
    });

    it('n\'appelle pas le backend si l\'approbation est annulée', async () => {
        renderPanel();

        fireEvent.click(screen.getByRole('button', { name: 'Approuver' }));
        const dialog = await screen.findByRole('dialog');
        fireEvent.click(within(dialog).getByRole('button', { name: 'Annuler' }));

        expect(routerPost).not.toHaveBeenCalled();
    });

    it('rejette une offre après confirmation', async () => {
        renderPanel();

        fireEvent.click(screen.getByRole('button', { name: 'Rejeter' }));
        const dialog = await screen.findByRole('dialog');
        fireEvent.click(within(dialog).getByRole('button', { name: 'Rejeter' }));

        await waitFor(() =>
            expect(routerPost).toHaveBeenCalledWith(
                '/admin/recruitment/5/reject',
                {},
                expect.objectContaining({ preserveScroll: true }),
            ),
        );
    });

    it('ne propose Approuver/Rejeter que pour une offre en attente de modération', () => {
        renderPanel({ recruitmentOffersPage: makePage([makeOffer({ status: 'active' })]) });

        expect(screen.queryByRole('button', { name: 'Approuver' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Rejeter' })).not.toBeInTheDocument();
    });

    it('ouvre la modale des candidatures et charge la liste', async () => {
        renderPanel({ recruitmentOffersPage: makePage([makeOffer({ applications_count: 3 })]) });

        fireEvent.click(screen.getByRole('button', { name: '3' }));

        expect(await screen.findByText('Candidatures reçues')).toBeInTheDocument();
        expect(global.fetch).toHaveBeenCalledWith(
            '/admin/recruitment/5/applications',
            expect.objectContaining({ headers: { Accept: 'application/json' } }),
        );
    });

    it('modifie les réglages de publication par espace et les enregistre', () => {
        renderPanel();

        const [clientSelect] = screen.getAllByRole('combobox');
        fireEvent.change(clientSelect, { target: { value: '0' } });
        fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

        expect(formPost).toHaveBeenCalledWith(
            '/admin/recruitment/settings',
            expect.objectContaining({
                client_posting_enabled: '0',
                fournisseur_posting_enabled: '1',
            }),
        );
    });

    it('désactive les contrôles de réglages sans la capacité de gestion', () => {
        renderPanel({ canManage: false });

        expect(screen.getByRole('button', { name: 'Enregistrer' })).toBeDisabled();
    });
});
