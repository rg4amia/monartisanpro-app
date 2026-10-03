import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import type { TerritoryMatrix, TerritoryZoneRow } from '../shared/types';
import { ABIDJAN_COMMUNES_GEODATA, IVORY_COAST_DISTRICTS } from './ivoryCoastGeoData';
import { IvoryCoastMapSvg } from './IvoryCoastMapSvg';
import { EMPTY_MATRIX, emptyZoneRow } from './territoryTypes';

const firstDistrict = IVORY_COAST_DISTRICTS[0];
const firstCommune = ABIDJAN_COMMUNES_GEODATA[0];

function row(slug: string, type: TerritoryZoneRow['type'], values: Partial<TerritoryZoneRow>): TerritoryZoneRow {
    return { ...emptyZoneRow(slug, slug, type), ...values };
}

function makeMatrix(): TerritoryMatrix {
    return {
        ...EMPTY_MATRIX,
        districts: {
            [firstDistrict.slug]: row(firstDistrict.slug, 'district', {
                clients: 40, clients_mission_active: 6, artisans: 25, artisans_kyc_actif: 20, livreurs: 7, livreurs_en_course: 2,
                fournisseurs: 5, fournisseurs_agrees: 3, missions_total: 90, missions_en_cours: 12, missions_terminees: 70,
                litiges: 4, volume_fcfa: 12500000, realization_rate: 77.8,
            }),
        },
        communes: {
            [firstCommune.id]: row(firstCommune.id, 'commune', { clients: 11, artisans: 9, missions_total: 30 }),
        },
    };
}

function renderMap(overrides: Partial<React.ComponentProps<typeof IvoryCoastMapSvg>> = {}) {
    const props: React.ComponentProps<typeof IvoryCoastMapSvg> = {
        viewMode: 'national',
        selectedDistrict: null,
        selectedCommune: null,
        matrix: makeMatrix(),
        activeTypes: [],
        onSelectDistrict: vi.fn(),
        onSelectCommune: vi.fn(),
        onSwitchViewMode: vi.fn(),
        onSelectCity: vi.fn(),
        ...overrides,
    };
    const view = render(<IvoryCoastMapSvg {...props} />);
    return { props, ...view };
}

/** Groupe SVG d'un district : porte le clic et le survol. */
function districtGroup(name: string): Element {
    return screen.getByText(name).closest('g.cursor-pointer')!;
}

describe('IvoryCoastMapSvg', () => {
    it('affiche la carte nationale des districts par défaut', () => {
        renderMap();
        expect(
            screen.getByLabelText("Carte vectorielle des 14 districts et pôles urbains de Côte d'Ivoire"),
        ).toBeInTheDocument();
    });

    it('bascule vers la vue Abidjan au clic', () => {
        const onSwitchViewMode = vi.fn();
        renderMap({ onSwitchViewMode });

        fireEvent.click(screen.getByRole('button', { name: /Abidjan \(13\)/ }));

        expect(onSwitchViewMode).toHaveBeenCalledWith('abidjan');
    });

    it('affiche la carte des communes en mode Abidjan', () => {
        renderMap({ viewMode: 'abidjan' });
        expect(screen.getByLabelText('Carte des 13 communes du Grand Abidjan')).toBeInTheDocument();
    });

    it('déclenche onSelectDistrict au clic sur un district', () => {
        const onSelectDistrict = vi.fn();
        renderMap({ onSelectDistrict });

        fireEvent.click(screen.getByText(firstDistrict.name));

        expect(onSelectDistrict).toHaveBeenCalledWith(firstDistrict.slug);
    });

    it('déclenche onSelectCommune au clic sur une commune en mode Abidjan', () => {
        const onSelectCommune = vi.fn();
        renderMap({ viewMode: 'abidjan', onSelectCommune });

        fireEvent.click(screen.getByText(firstCommune.name));

        expect(onSelectCommune).toHaveBeenCalledWith(firstCommune.id);
    });

    it('sans type coché, la pastille d’une zone porte l’effectif de tous les types', () => {
        renderMap();

        // 40 clients + 25 artisans + 7 livreurs + 5 quincailleries + 90 missions.
        expect(within(districtGroup(firstDistrict.name) as HTMLElement).getByText('167')).toBeInTheDocument();
    });

    it('la pastille ne compte que les types cochés', () => {
        renderMap({ activeTypes: ['artisan', 'livreur'] });

        expect(within(districtGroup(firstDistrict.name) as HTMLElement).getByText('32')).toBeInTheDocument();
    });

    it('détaille chaque type coché par une pastille de couleur dans la zone', () => {
        renderMap({ activeTypes: ['artisan', 'livreur'] });

        expect(screen.getByTestId(`badge-${firstDistrict.slug}-artisan`)).toHaveTextContent('25');
        expect(screen.getByTestId(`badge-${firstDistrict.slug}-livreur`)).toHaveTextContent('7');
        expect(screen.queryByTestId(`badge-${firstDistrict.slug}-client`)).toBeNull();
    });

    it('n’ajoute pas de pastilles par type quand tous les types sont affichés', () => {
        renderMap();

        expect(screen.queryByTestId(`badge-${firstDistrict.slug}-artisan`)).toBeNull();
    });

    it('au survol, donne le décompte complet par type de la zone', () => {
        renderMap({ activeTypes: ['artisan'] });

        fireEvent.mouseEnter(districtGroup(firstDistrict.name));

        const breakdown = screen.getByTestId('zone-breakdown');
        expect(within(breakdown).getByText('Clients').closest('li')).toHaveTextContent('40');
        expect(within(breakdown).getByText('6 avec mission en cours')).toBeInTheDocument();
        expect(within(breakdown).getByText('20 KYC actif')).toBeInTheDocument();
        expect(within(breakdown).getByText('2 en course')).toBeInTheDocument();
        expect(within(breakdown).getByText('3 agréées')).toBeInTheDocument();
        expect(within(breakdown).getByText('12 en cours • 70 terminées')).toBeInTheDocument();
        expect(screen.getByText('77.8%')).toBeInTheDocument();
        expect(screen.getByText(/12\s500\s000 FCFA/)).toBeInTheDocument();
    });

    it('annonce une zone sans donnée au survol', () => {
        renderMap({ matrix: EMPTY_MATRIX });

        fireEvent.mouseEnter(districtGroup(firstDistrict.name));

        expect(screen.getByText('Aucune donnée pour cette zone.')).toBeInTheDocument();
    });

    it('la légende signale les zones sans donnée', () => {
        renderMap();
        expect(screen.getByText('Zone sans donnée')).toBeInTheDocument();
    });
});
