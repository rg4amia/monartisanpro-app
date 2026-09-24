import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('./IvoryCoastMapSvg', () => ({
    IvoryCoastMapSvg: () => <div data-testid="ivory-coast-map" />,
}));

import type { TerritoryBreakdowns, TerritoryEntityItem, TerritorySummary } from '../shared/types';
import { CartographyPanel } from './CartographyPanel';

function makeBreakdowns(overrides: Partial<TerritoryBreakdowns> = {}): TerritoryBreakdowns {
    return {
        artisan_categories: {
            total: 10,
            items: [
                { sector_id: 1, label: 'Électricité', icon: '⚡', count: 6, percent: 60 },
                { sector_id: null, label: 'Non renseigné', icon: null, count: 4, percent: 40 },
            ],
        },
        supplier_sectors: {
            total: 4,
            items: [
                { sector_id: 2, label: 'Plomberie', icon: '🔧', count: 3, percent: 75 },
                { sector_id: null, label: 'Non renseigné', icon: null, count: 1, percent: 25 },
            ],
        },
        cnmci: { total_artisans: 10, valide: 6, en_attente: 2, rejete: 1, non_renseigne: 1, valide_percent: 60 },
        ...overrides,
    };
}

function makeSummary(overrides: Partial<TerritorySummary> = {}): TerritorySummary {
    return {
        zone: { type: 'national', slug: 'all', name: "Toute la Côte d'Ivoire (Vue Nationale)" },
        actors: { clients: 120, artisans: 80, artisans_kyc_actif: 60, fournisseurs: 15, livreurs: 20, total_actors: 235 },
        missions: {
            total: 300,
            en_cours: 40,
            completed: 250,
            terminee: 250,
            disputed: 5,
            litige: 5,
            realization_rate: 83,
            dispute_rate: 1.6,
            total_volume_fcfa: 50000000,
            financial_volume_fcfa: 50000000,
        },
        reputation: { avg_rating: 4.5, average_artisan_rating: 4.5, avg_score_prosartisan: 720, total_reviews: 90 },
        ...overrides,
    } as TerritorySummary;
}

function makeEntity(overrides: Partial<TerritoryEntityItem> = {}): TerritoryEntityItem {
    return {
        id: 1,
        type: 'artisan',
        title: "Koffi N'Guessan",
        subtitle: 'Maçon',
        location: 'Cocody',
        commune: 'Cocody',
        district: 'Abidjan',
        status: 'Actif',
        score_prosartisan: 750,
        created_at: '2026-02-01T00:00:00Z',
        contact: '+2250700000001',
        action_url: '/admin/users/1',
        ...overrides,
    } as TerritoryEntityItem;
}

function renderPanel(overrides: Partial<React.ComponentProps<typeof CartographyPanel>> = {}) {
    const props: React.ComponentProps<typeof CartographyPanel> = {
        territorySummary: makeSummary(),
        territoryBreakdowns: makeBreakdowns(),
        districtsHeatmap: {},
        communesHeatmap: {},
        districtsList: [{ id: 'abidjan', slug: 'abidjan', name: 'Abidjan', short_name: 'ABJ', chef_lieu: 'Abidjan', lat: 5.3, lng: -4.0, villes: [] }],
        communesList: [{ name: 'Cocody', type: 'commune', slug: 'cocody', district_slug: 'abidjan', lat: 5.35, lng: -3.98 }],
        entities: { data: [makeEntity()], current_page: 1, last_page: 1, total: 1, per_page: 15 },
        filters: {},
        ...overrides,
    };
    render(<CartographyPanel {...props} />);
    return props;
}

describe('CartographyPanel', () => {
    beforeEach(() => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => ({}) }));
    });

    afterEach(() => vi.unstubAllGlobals());

    it('affiche la zone courante et les KPI des 4 acteurs', () => {
        renderPanel();

        expect(screen.getByText("Toute la Côte d'Ivoire (Vue Nationale)")).toBeInTheDocument();
        expect(screen.getByText('120')).toBeInTheDocument();
        expect(screen.getByText('80')).toBeInTheDocument();
    });

    it('affiche le taux de réalisation et le taux de litiges', () => {
        renderPanel();

        expect(screen.getByText('83%')).toBeInTheDocument();
        expect(screen.getByText('1.6%')).toBeInTheDocument();
    });

    it('liste les entités territoriales avec leur type et leur score', () => {
        renderPanel();

        expect(screen.getByText("Koffi N'Guessan")).toBeInTheDocument();
        expect(screen.getByText('★ 750/1000')).toBeInTheDocument();
    });

    it('affiche un état vide sans entité correspondante', () => {
        renderPanel({ entities: { data: [], current_page: 1, last_page: 1, total: 0, per_page: 15 } });
        expect(screen.getByText(/Aucun enregistrement ne correspond/)).toBeInTheDocument();
    });

    it('interroge le backend au changement de district', async () => {
        renderPanel();

        fireEvent.change(screen.getByDisplayValue('Tous les Districts (National)'), {
            target: { value: 'abidjan' },
        });

        await waitFor(() =>
            expect(global.fetch).toHaveBeenCalledWith(
                expect.stringContaining('district=abidjan'),
                expect.objectContaining({ headers: expect.objectContaining({ Accept: 'application/json' }) }),
            ),
        );
    });

    it('interroge le backend au changement d\'onglet d\'entité', async () => {
        renderPanel();

        fireEvent.click(screen.getByRole('button', { name: 'Artisans' }));

        await waitFor(() =>
            expect(global.fetch).toHaveBeenCalledWith(
                expect.stringContaining('entity_type=artisan'),
                expect.anything(),
            ),
        );
    });

    it('interroge le backend à la soumission de la recherche', async () => {
        renderPanel();

        fireEvent.change(
            screen.getByPlaceholderText('Rechercher par nom, téléphone, ID ou titre dans cette zone...'),
            { target: { value: 'Koffi' } },
        );
        fireEvent.submit(screen.getByRole('button', { name: 'Filtrer' }).closest('form')!);

        await waitFor(() =>
            expect(global.fetch).toHaveBeenCalledWith(expect.stringContaining('search=Koffi'), expect.anything()),
        );
    });

    it('affiche la pagination et navigue vers la page suivante', async () => {
        renderPanel({
            entities: { data: [makeEntity()], current_page: 1, last_page: 3, total: 30, per_page: 15 },
        });

        expect(screen.getByText('Page 1 sur 3 (30 éléments)')).toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: /Suivant/ }));

        await waitFor(() =>
            expect(global.fetch).toHaveBeenCalledWith(expect.stringContaining('page=2'), expect.anything()),
        );
    });

    it('affiche la répartition des artisans par catégorie et la part « Non renseigné »', () => {
        renderPanel();

        expect(screen.getByText('Artisans par catégorie')).toBeInTheDocument();
        expect(screen.getByText(/⚡ Électricité/)).toBeInTheDocument();
        expect(screen.getByText('6 • 60%')).toBeInTheDocument();
        expect(screen.getByText('4 • 40%')).toBeInTheDocument();
    });

    it('affiche la répartition des fournisseurs par secteur d’activité', () => {
        renderPanel();

        expect(screen.getByText('Fournisseurs par secteur d’activité')).toBeInTheDocument();
        expect(screen.getByText(/🔧 Plomberie/)).toBeInTheDocument();
    });

    it('affiche la conformité CNMCI des artisans de la zone', () => {
        renderPanel();

        expect(screen.getByText('Conformité carte CNMCI')).toBeInTheDocument();
        expect(screen.getByText('6')).toBeInTheDocument();
        expect(screen.getByText('60% avec carte valide')).toBeInTheDocument();
        expect(screen.getByText('2 en attente')).toBeInTheDocument();
        expect(screen.getByText('1 rejetée(s)')).toBeInTheDocument();
        expect(screen.getByText('1 non renseignée(s)')).toBeInTheDocument();
    });

    it('affiche un message explicite quand aucune donnée de répartition n’existe', () => {
        renderPanel({
            territoryBreakdowns: {
                artisan_categories: { total: 0, items: [] },
                supplier_sectors: { total: 0, items: [] },
                cnmci: { total_artisans: 0, valide: 0, en_attente: 0, rejete: 0, non_renseigne: 0, valide_percent: 0 },
            },
        });

        expect(screen.getAllByText('Aucun artisan inscrit dans cette zone.').length).toBeGreaterThan(0);
        expect(screen.getByText('Aucun fournisseur inscrit dans cette zone.')).toBeInTheDocument();
    });

    it('réinitialise tous les filtres', async () => {
        renderPanel({ filters: { district: 'abidjan' } });

        fireEvent.click(screen.getByRole('button', { name: 'Réinitialiser' }));

        await waitFor(() => expect(global.fetch).toHaveBeenCalled());
        const lastCallUrl = (global.fetch as ReturnType<typeof vi.fn>).mock.calls.at(-1)?.[0] as string;
        expect(lastCallUrl).not.toContain('district=');
    });
});
