import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const mapProps = vi.fn();
vi.mock('./IvoryCoastMapSvg', () => ({
    IvoryCoastMapSvg: (props: Record<string, unknown>) => {
        mapProps(props);
        return <div data-testid="ivory-coast-map" />;
    },
}));

vi.mock('@inertiajs/react', () => ({
    Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
    router: { post: vi.fn(), on: () => () => undefined },
}));

import type { TerritoryBreakdowns, TerritoryEntityItem, TerritoryMatrix, TerritorySummary, TerritoryZoneRow } from '../shared/types';
import { CartographyPanel } from './CartographyPanel';
import { emptyZoneRow } from './territoryTypes';

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
        actors: {
            clients: 120,
            clients_with_active_missions: 17,
            artisans: 80,
            artisans_kyc_actif: 60,
            artisans_kyc_percent: 75,
            fournisseurs: 15,
            fournisseurs_agrees: 9,
            livreurs: 20,
            livreurs_en_course: 4,
            total_actors: 235,
        },
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
        reputation: { avg_rating: 4.5, avg_score_prosartisan: 720, total_reviews: 90 },
        ...overrides,
    };
}

function row(slug: string, name: string, type: TerritoryZoneRow['type'], values: Partial<TerritoryZoneRow> = {}): TerritoryZoneRow {
    return { ...emptyZoneRow(slug, name, type), ...values };
}

function makeMatrix(): TerritoryMatrix {
    return {
        national: row('all', "Côte d'Ivoire", 'national', {
            clients: 121, artisans: 82, artisans_kyc_actif: 61, livreurs: 21, livreurs_en_course: 4, fournisseurs: 16,
            fournisseurs_agrees: 9, missions_total: 300, missions_en_cours: 40, missions_terminees: 250, litiges: 5,
            volume_fcfa: 50000000, realization_rate: 83.3,
        }),
        unlocated: row('non_renseigne', 'Commune non renseignée', 'unlocated', { clients: 1, artisans: 2 }),
        districts: {
            abidjan: row('abidjan', 'Abidjan', 'district', {
                clients: 100, artisans: 60, artisans_kyc_actif: 50, livreurs: 18, livreurs_en_course: 4, fournisseurs: 12,
                fournisseurs_agrees: 8, missions_total: 260, missions_en_cours: 35, missions_terminees: 220, litiges: 5,
                volume_fcfa: 45000000, realization_rate: 84.6,
            }),
            gbeke: row('gbeke', 'Bouaké / Gbêkê', 'district', {
                clients: 20, artisans: 20, artisans_kyc_actif: 11, livreurs: 3, fournisseurs: 4, fournisseurs_agrees: 1,
                missions_total: 40, missions_en_cours: 5, missions_terminees: 30, volume_fcfa: 5000000, realization_rate: 75,
            }),
            denguele: row('denguele', 'Odienné', 'district'),
        },
        communes: {
            cocody: row('cocody', 'Cocody', 'commune', { clients: 70, artisans: 25, missions_total: 150, missions_terminees: 130, realization_rate: 86.7 }),
            yopougon: row('yopougon', 'Yopougon', 'commune', { clients: 30, artisans: 35, missions_total: 110, missions_terminees: 90, realization_rate: 81.8 }),
        },
    };
}

function makeEntity(overrides: Partial<TerritoryEntityItem> = {}): TerritoryEntityItem {
    return {
        id: 1,
        type: 'artisan',
        title: "Koffi N'Guessan",
        subtitle: 'Artisan',
        detail: 'Maçonnerie',
        location: 'Cocody',
        district: 'Abidjan',
        status: 'actif',
        status_label: 'KYC actif',
        score_prosartisan: 750,
        created_at: '2026-02-01T00:00:00Z',
        contact: '+2250700000001',
        action_url: '/admin/users?search_users=%2B2250700000001',
        ...overrides,
    };
}

function renderPanel(overrides: Partial<React.ComponentProps<typeof CartographyPanel>> = {}) {
    const props: React.ComponentProps<typeof CartographyPanel> = {
        territorySummary: makeSummary(),
        territoryBreakdowns: makeBreakdowns(),
        territoryMatrix: makeMatrix(),
        districtsList: [{ id: 'abidjan', slug: 'abidjan', name: 'Abidjan', short_name: 'ABJ', chef_lieu: 'Abidjan', lat: 5.3, lng: -4.0, villes: [] }],
        communesList: [{ name: 'Cocody', type: 'commune', slug: 'cocody', district_slug: 'abidjan', lat: 5.35, lng: -3.98 }],
        entities: { data: [makeEntity()], current_page: 1, last_page: 1, total: 1, per_page: 15 },
        filters: {},
        ...overrides,
    };
    render(<CartographyPanel {...props} />);
    return props;
}

function lastFetchUrl(): string {
    return (global.fetch as ReturnType<typeof vi.fn>).mock.calls.at(-1)?.[0] as string;
}

function lastMapProps(): Record<string, unknown> {
    return mapProps.mock.calls.at(-1)?.[0] as Record<string, unknown>;
}

function openDetail() {
    fireEvent.click(screen.getByRole('tab', { name: 'Détail' }));
}

describe('CartographyPanel', () => {
    beforeEach(() => {
        mapProps.mockClear();
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, status: 200, json: async () => ({}) }));
    });

    afterEach(() => vi.unstubAllGlobals());

    it('affiche la zone courante et les indicateurs réellement calculés des 4 acteurs', () => {
        renderPanel();

        expect(screen.getByText("Toute la Côte d'Ivoire (Vue Nationale)")).toBeInTheDocument();
        expect(screen.getByText('120')).toBeInTheDocument();
        expect(screen.getByText('17 avec mission en cours')).toBeInTheDocument();
        expect(screen.getByText('60 au KYC actif (75%)')).toBeInTheDocument();
        expect(screen.getByText('9 agréées')).toBeInTheDocument();
        // Présent sur la carte des livreurs et dans la synthèse par zone.
        expect(screen.getAllByText('4 en course').length).toBeGreaterThan(0);
    });

    it('affiche le taux de réalisation et le taux de litiges', () => {
        renderPanel();

        expect(screen.getByText('83%')).toBeInTheDocument();
        expect(screen.getByText('1.6%')).toBeInTheDocument();
    });

    it('annonce une note non évaluée plutôt que 0/5', () => {
        renderPanel({ territorySummary: makeSummary({ reputation: { avg_rating: null, total_reviews: 0 } }) });

        expect(screen.getByText('Note des missions : non évaluée')).toBeInTheDocument();
        expect(screen.queryByText(/0\/5/)).toBeNull();
    });

    it('interroge le backend au changement de district', async () => {
        renderPanel();

        fireEvent.change(screen.getByLabelText('District'), { target: { value: 'abidjan' } });

        await waitFor(() =>
            expect(global.fetch).toHaveBeenCalledWith(
                expect.stringContaining('district=abidjan'),
                expect.objectContaining({ headers: expect.objectContaining({ Accept: 'application/json' }) }),
            ),
        );
    });

    it('propose la zone « Commune non renseignée »', async () => {
        renderPanel();

        fireEvent.change(screen.getByLabelText('District'), { target: { value: 'non_renseigne' } });

        await waitFor(() => expect(lastFetchUrl()).toContain('district=non_renseigne'));
    });

    describe('filtres par type', () => {
        it('affiche une pastille par type avec son total national', () => {
            renderPanel();

            const group = screen.getByRole('group', { name: 'Filtrer par type' });
            expect(within(group).getByRole('button', { name: /Clients\s*121/ })).toBeInTheDocument();
            expect(within(group).getByRole('button', { name: /Artisans\s*82/ })).toBeInTheDocument();
            expect(within(group).getByRole('button', { name: /Livreurs\s*21/ })).toBeInTheDocument();
            expect(within(group).getByRole('button', { name: /Quincailleries\s*16/ })).toBeInTheDocument();
            expect(within(group).getByRole('button', { name: /Missions\s*300/ })).toBeInTheDocument();
            expect(within(group).getByRole('button', { name: 'Tout' })).toHaveAttribute('aria-pressed', 'true');
        });

        it('cocher des types filtre la carte et le tableau par le même état', async () => {
            renderPanel();
            const group = screen.getByRole('group', { name: 'Filtrer par type' });

            fireEvent.click(within(group).getByRole('button', { name: /Artisans/ }));
            fireEvent.click(within(group).getByRole('button', { name: /Livreurs/ }));

            await waitFor(() => expect(lastFetchUrl()).toContain('types=artisan%2Clivreur'));
            expect(lastMapProps().activeTypes).toEqual(['artisan', 'livreur']);

            // La synthèse ne garde que les colonnes des types cochés.
            const table = screen.getByRole('table', { name: 'Synthèse par zone' });
            expect(within(table).getByRole('button', { name: /^Artisans/ })).toBeInTheDocument();
            expect(within(table).getByRole('button', { name: /^Livreurs/ })).toBeInTheDocument();
            expect(within(table).queryByRole('button', { name: /^Clients/ })).toBeNull();
            expect(within(table).queryByRole('button', { name: /^Volume/ })).toBeNull();
        });

        it('« Tout » décoche tous les types', async () => {
            renderPanel({ filters: { types: ['artisan'] } });

            fireEvent.click(screen.getByRole('button', { name: 'Tout' }));

            await waitFor(() => expect(global.fetch).toHaveBeenCalled());
            expect(lastFetchUrl()).not.toContain('types=');
            expect(lastMapProps().activeTypes).toEqual([]);
        });

        it('transmet les filtres de statut de mission, de période et de KYC', async () => {
            renderPanel();

            fireEvent.change(screen.getByLabelText('Statut des missions'), { target: { value: 'en_cours' } });
            await waitFor(() => expect(lastFetchUrl()).toContain('mission_status=en_cours'));

            fireEvent.change(screen.getByLabelText('Période des missions'), { target: { value: '30' } });
            await waitFor(() => expect(lastFetchUrl()).toContain('period=30'));

            fireEvent.change(screen.getByLabelText('Statut KYC des acteurs'), { target: { value: 'actif' } });
            await waitFor(() => expect(lastFetchUrl()).toContain('kyc=actif'));
            expect(lastFetchUrl()).toContain('mission_status=en_cours');
        });

        it('la carte reçoit la matrice renvoyée par le backend', async () => {
            const matrix = makeMatrix();
            matrix.districts.abidjan.artisans = 999;
            (global.fetch as ReturnType<typeof vi.fn>).mockResolvedValue({ ok: true, status: 200, json: async () => ({ matrix }) });
            renderPanel();

            fireEvent.change(screen.getByLabelText('Statut KYC des acteurs'), { target: { value: 'actif' } });

            await waitFor(() => expect((lastMapProps().matrix as TerritoryMatrix).districts.abidjan.artisans).toBe(999));
        });
    });

    describe('synthèse par zone', () => {
        it('liste une ligne par district, les non renseignés et le total national', () => {
            renderPanel();
            const table = screen.getByRole('table', { name: 'Synthèse par zone' });

            const abidjan = within(table).getByRole('rowheader', { name: 'Abidjan' }).closest('tr')!;
            expect(within(abidjan).getByText('100')).toBeInTheDocument();
            expect(within(abidjan).getByText('50 KYC actif')).toBeInTheDocument();
            expect(within(abidjan).getByText('8 agréées')).toBeInTheDocument();
            expect(within(abidjan).getByText('84.6%')).toBeInTheDocument();

            expect(within(table).getByRole('rowheader', { name: 'Commune non renseignée' })).toBeInTheDocument();
            const total = within(table).getByRole('rowheader', { name: 'Total national' }).closest('tr')!;
            expect(within(total).getByText('121')).toBeInTheDocument();
        });

        it('n’affiche pas de taux de réalisation pour une zone sans mission', () => {
            renderPanel();
            const odienne = screen.getByRole('rowheader', { name: 'Odienné' }).closest('tr')!;

            expect(within(odienne).getByText('—')).toBeInTheDocument();
            expect(within(odienne).queryByText('0%')).toBeNull();
        });

        it('trie par colonne au clic sur l’en-tête', () => {
            renderPanel();
            const table = screen.getByRole('table', { name: 'Synthèse par zone' });
            const names = () => within(table).getAllByRole('rowheader').map((cell) => cell.textContent);

            fireEvent.click(within(table).getByRole('button', { name: /^Artisans/ }));
            expect(names().slice(0, 3)).toEqual(['Abidjan', 'Bouaké / Gbêkê', 'Commune non renseignée']);

            fireEvent.click(within(table).getByRole('button', { name: /^Artisans/ }));
            expect(names()[0]).toBe('Odienné');
        });

        it('un clic sur une ligne sélectionne la zone', async () => {
            renderPanel();

            fireEvent.click(screen.getByRole('rowheader', { name: 'Bouaké / Gbêkê' }));

            await waitFor(() => expect(lastFetchUrl()).toContain('district=gbeke'));
            expect(lastMapProps().selectedDistrict).toBe('gbeke');
        });

        it('affiche les communes dans la vue Grand Abidjan', () => {
            renderPanel({ filters: { district: 'abidjan' } });
            const table = screen.getByRole('table', { name: 'Synthèse par zone' });

            expect(within(table).getByRole('rowheader', { name: 'Cocody' })).toBeInTheDocument();
            expect(within(table).getByRole('rowheader', { name: 'Total Grand Abidjan' })).toBeInTheDocument();
            expect(within(table).queryByRole('rowheader', { name: 'Commune non renseignée' })).toBeNull();
        });

        it('annonce une synthèse vide', () => {
            renderPanel({ territoryMatrix: undefined, filters: { district: 'abidjan' } });

            expect(screen.getByText(/Aucune zone à afficher/)).toBeInTheDocument();
            expect(screen.queryByRole('rowheader', { name: 'Total Grand Abidjan' })).toBeNull();
        });
    });

    describe('détail', () => {
        it('liste les acteurs avec leur statut en français, leur précision et leur score', () => {
            renderPanel();
            openDetail();

            const table = screen.getByRole('table', { name: 'Détail de la zone' });
            expect(within(table).getByText("Koffi N'Guessan")).toBeInTheDocument();
            expect(within(table).getByText('Maçonnerie')).toBeInTheDocument();
            expect(within(table).getByText('KYC actif')).toBeInTheDocument();
            expect(within(table).queryByText('actif')).toBeNull();
            expect(within(table).getByText('★ 750/1000')).toBeInTheDocument();
        });

        it('n’invente pas de localisation pour un acteur sans commune', () => {
            renderPanel({ entities: { data: [makeEntity({ location: null, district: null })], current_page: 1, last_page: 1, total: 1, per_page: 15 } });
            openDetail();

            const table = screen.getByRole('table', { name: 'Détail de la zone' });
            expect(within(table).getByText('Commune non renseignée')).toBeInTheDocument();
            expect(within(table).queryByText('Abidjan')).toBeNull();
        });

        it('affiche une mission avec son statut libellé, son montant, son client et son artisan', () => {
            renderPanel({
                filters: { entity_type: 'mission' },
                entities: {
                    data: [makeEntity({
                        id: 7, type: 'mission', title: 'Mission #7 — Plomberie', subtitle: 'Fuite sous évier', detail: undefined,
                        location: 'Cocody Riviera 3', district: null, status: 'in_progress', status_label: 'En cours',
                        score_prosartisan: null, amount_fcfa: 150000, client_name: 'Awa', actor_name: 'Koffi',
                    })],
                    current_page: 1, last_page: 1, total: 1, per_page: 15,
                },
            });

            const table = screen.getByRole('table', { name: 'Détail de la zone' });
            expect(within(table).getByText('En cours')).toBeInTheDocument();
            expect(within(table).queryByText('in_progress')).toBeNull();
            expect(within(table).getByText('Cocody Riviera 3')).toBeInTheDocument();
            expect(within(table).getByText('Awa')).toBeInTheDocument();
            expect(within(table).getByText('Koffi')).toBeInTheDocument();
        });

        it('affiche un état vide sans entité correspondante', () => {
            renderPanel({ entities: { data: [], current_page: 1, last_page: 1, total: 0, per_page: 15 } });
            openDetail();

            expect(screen.getByText(/Aucun enregistrement ne correspond/)).toBeInTheDocument();
        });

        it('envoie au backend le type attendu pour chaque onglet', async () => {
            renderPanel();
            openDetail();

            for (const [label, type] of [['Artisans', 'artisan'], ['Quincailleries', 'fournisseur'], ['Missions', 'mission'], ['Litiges', 'litige']]) {
                const tabs = screen.getAllByRole('button', { name: label });
                fireEvent.click(tabs[tabs.length - 1]);
                await waitFor(() => expect(lastFetchUrl()).toContain(`entity_type=${type}`));
            }
        });

        it('ne propose que les onglets des types cochés', () => {
            renderPanel({ filters: { types: ['artisan'] } });
            openDetail();

            expect(screen.getByRole('button', { name: 'Tous les acteurs' })).toBeInTheDocument();
            expect(screen.queryByRole('button', { name: 'Litiges' })).toBeNull();
            expect(screen.queryByRole('button', { name: 'Clients' })).toBeNull();
        });

        it('interroge le backend à la soumission de la recherche', async () => {
            renderPanel();
            openDetail();

            fireEvent.change(screen.getByPlaceholderText('Rechercher par nom ou téléphone…'), { target: { value: 'Koffi' } });
            fireEvent.submit(screen.getByRole('button', { name: 'Filtrer' }).closest('form')!);

            await waitFor(() => expect(lastFetchUrl()).toContain('search=Koffi'));
        });

        it('transmet le tri choisi', async () => {
            renderPanel();
            openDetail();

            fireEvent.change(screen.getByLabelText('Tri'), { target: { value: 'score' } });

            await waitFor(() => expect(lastFetchUrl()).toContain('sort=score'));
        });

        it('affiche la pagination et navigue vers la page suivante', async () => {
            renderPanel({
                entities: { data: [makeEntity()], current_page: 1, last_page: 3, total: 30, per_page: 15 },
            });
            openDetail();

            expect(screen.getByText('Page 1 sur 3 (30 éléments)')).toBeInTheDocument();
            fireEvent.click(screen.getByRole('button', { name: /Suivant/ }));

            await waitFor(() => expect(lastFetchUrl()).toContain('page=2'));
        });
    });

    describe('export CSV', () => {
        it('n’apparaît pas sans la capacité d’export', () => {
            renderPanel();
            expect(screen.queryByRole('link', { name: 'Exporter CSV' })).toBeNull();
        });

        it('exporte la vue affichée avec les filtres de l’écran', () => {
            renderPanel({ canExport: true, filters: { district: 'abidjan', types: ['artisan'], kyc: 'actif' } });

            const synthese = screen.getByRole('link', { name: 'Exporter CSV' }).getAttribute('href')!;
            expect(synthese).toContain('/admin/cartographie/export?');
            expect(synthese).toContain('vue=synthese');
            expect(synthese).toContain('niveau=communes');
            expect(synthese).toContain('district=abidjan');
            expect(synthese).toContain('kyc=actif');

            openDetail();
            expect(screen.getByRole('link', { name: 'Exporter CSV' }).getAttribute('href')).toContain('vue=detail');
        });
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
        renderPanel({ filters: { district: 'abidjan', types: ['artisan'], kyc: 'actif' } });

        fireEvent.click(screen.getByRole('button', { name: 'Réinitialiser' }));

        await waitFor(() => expect(global.fetch).toHaveBeenCalled());
        expect(lastFetchUrl()).not.toContain('district=');
        expect(lastFetchUrl()).not.toContain('types=');
        expect(lastFetchUrl()).not.toContain('kyc=');
    });

    it('annonce un chargement en échec au lieu de garder le silence', async () => {
        (global.fetch as ReturnType<typeof vi.fn>).mockResolvedValue({ ok: false, status: 500, json: async () => ({}) });
        renderPanel();

        fireEvent.change(screen.getByLabelText('District'), { target: { value: 'abidjan' } });

        expect(await screen.findByRole('alert')).toHaveTextContent('n’ont pas pu être chargées');
    });

    it('renvoie à la connexion quand la session a expiré', async () => {
        const assign = vi.fn();
        vi.stubGlobal('location', { ...window.location, assign });
        (global.fetch as ReturnType<typeof vi.fn>).mockResolvedValue({ ok: false, status: 401, json: async () => ({}) });
        renderPanel();

        fireEvent.change(screen.getByLabelText('District'), { target: { value: 'abidjan' } });

        await waitFor(() => expect(assign).toHaveBeenCalledWith('/admin/login'));
        expect(screen.queryByRole('alert')).toBeNull();
    });
});
