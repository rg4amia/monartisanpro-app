import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import type { CampagneParrainageItem } from '../shared';
import { CampagnesParrainagePanel } from './CampagnesParrainagePanel';

function makeCampagne(overrides: Partial<CampagneParrainageItem> = {}): CampagneParrainageItem {
    return {
        id: 1,
        libelle: 'Parrainage Bienvenue',
        discount_type: 'percent',
        discount_value: 10,
        max_discount_amount: 20000,
        min_montant: 5000,
        starts_at: '2026-01-01T00:00:00Z',
        expires_at: '2026-12-31T00:00:00Z',
        is_active: true,
        created_at: '2026-01-01T00:00:00Z',
        ...overrides,
    };
}

function renderPanel(overrides: Partial<React.ComponentProps<typeof CampagnesParrainagePanel>> = {}) {
    const props: React.ComponentProps<typeof CampagnesParrainagePanel> = {
        filteredCampagnes: [makeCampagne()],
        onCreate: vi.fn(),
        onEdit: vi.fn(),
        onToggle: vi.fn(),
        onDelete: vi.fn(),
        ...overrides,
    };
    render(<CampagnesParrainagePanel {...props} />);
    return props;
}

describe('CampagnesParrainagePanel', () => {
    it('affiche le libellé, la remise et les conditions', () => {
        renderPanel();

        expect(screen.getByText('Parrainage Bienvenue')).toBeInTheDocument();
        expect(screen.getByText('-10%')).toBeInTheDocument();
    });

    it('affiche une remise fixe en FCFA quand discount_type est "fixed"', () => {
        renderPanel({
            filteredCampagnes: [makeCampagne({ discount_type: 'fixed', discount_value: 2000 })],
        });

        expect(screen.getByText(/-2\s?000/)).toBeInTheDocument();
    });

    it('affiche "Illimitée" sans date d\'expiration', () => {
        renderPanel({ filteredCampagnes: [makeCampagne({ expires_at: null })] });
        expect(screen.getByText('Illimitée')).toBeInTheDocument();
    });

    it('déclenche onCreate au clic sur "Nouvelle Campagne"', () => {
        const props = renderPanel();
        fireEvent.click(screen.getByRole('button', { name: /Nouvelle Campagne/ }));
        expect(props.onCreate).toHaveBeenCalledTimes(1);
    });

    it('déclenche onEdit avec la campagne ciblée', () => {
        const campagne = makeCampagne();
        const props = renderPanel({ filteredCampagnes: [campagne] });

        fireEvent.click(screen.getByRole('button', { name: 'Modifier' }));

        expect(props.onEdit).toHaveBeenCalledWith(campagne);
    });

    it('déclenche onDelete avec la campagne ciblée', () => {
        const campagne = makeCampagne();
        const props = renderPanel({ filteredCampagnes: [campagne] });

        fireEvent.click(screen.getByRole('button', { name: 'Supprimer' }));

        expect(props.onDelete).toHaveBeenCalledWith(campagne);
    });

    it('déclenche onToggle et reflète le statut actif/inactif', () => {
        const campagne = makeCampagne({ is_active: false });
        const props = renderPanel({ filteredCampagnes: [campagne] });

        expect(screen.getByText('○ Inactif')).toBeInTheDocument();
        fireEvent.click(screen.getByText('○ Inactif'));

        expect(props.onToggle).toHaveBeenCalledWith(campagne);
    });

    it('affiche un état vide sans aucune campagne', () => {
        renderPanel({ filteredCampagnes: [] });
        expect(screen.getByText(/Aucune campagne de parrainage trouvée/)).toBeInTheDocument();
    });
});
