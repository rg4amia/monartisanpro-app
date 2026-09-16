import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { IvoryCoastMapSvg } from './IvoryCoastMapSvg';
import { ABIDJAN_COMMUNES_GEODATA, IVORY_COAST_DISTRICTS } from './ivoryCoastGeoData';

function renderMap(overrides: Partial<React.ComponentProps<typeof IvoryCoastMapSvg>> = {}) {
    const props: React.ComponentProps<typeof IvoryCoastMapSvg> = {
        viewMode: 'national',
        selectedDistrict: null,
        selectedCommune: null,
        districtsHeatmap: {},
        communesHeatmap: {},
        onSelectDistrict: vi.fn(),
        onSelectCommune: vi.fn(),
        onSwitchViewMode: vi.fn(),
        onSelectCity: vi.fn(),
        ...overrides,
    };
    render(<IvoryCoastMapSvg {...props} />);
    return props;
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
        const firstDistrict = IVORY_COAST_DISTRICTS[0];
        renderMap({ onSelectDistrict });

        fireEvent.click(screen.getByText(firstDistrict.name));

        expect(onSelectDistrict).toHaveBeenCalledWith(firstDistrict.slug);
    });

    it('déclenche onSelectCommune au clic sur une commune en mode Abidjan', () => {
        const onSelectCommune = vi.fn();
        const firstCommune = ABIDJAN_COMMUNES_GEODATA[0];
        renderMap({ viewMode: 'abidjan', onSelectCommune });

        fireEvent.click(screen.getByText(firstCommune.name));

        expect(onSelectCommune).toHaveBeenCalledWith(firstCommune.id);
    });
});
