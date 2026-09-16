import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { PromoCodesPanel } from './PromoCodesPanel';
import type { PromoCodeItem } from '../shared';

function makePromo(overrides: Partial<PromoCodeItem> = {}): PromoCodeItem {
    return {
        id: 1,
        code: 'BIENVENUE10',
        description: 'Remise de bienvenue',
        discount_type: 'percent',
        discount_value: 10,
        min_order_amount: 5000,
        max_discount_amount: 20000,
        usage_limit: 100,
        used_count: 25,
        expires_at: '2026-12-31T00:00:00Z',
        is_active: true,
        created_at: '2026-01-01T00:00:00Z',
        ...overrides,
    };
}

function renderPanel(overrides: Partial<React.ComponentProps<typeof PromoCodesPanel>> = {}) {
    const props: React.ComponentProps<typeof PromoCodesPanel> = {
        filteredPromoCodes: [makePromo()],
        onCreate: vi.fn(),
        onEdit: vi.fn(),
        onToggle: vi.fn(),
        onDelete: vi.fn(),
        ...overrides,
    };
    render(<PromoCodesPanel {...props} />);
    return props;
}

describe('PromoCodesPanel', () => {
    it('affiche le code, la remise et les conditions', () => {
        renderPanel();

        expect(screen.getByText('BIENVENUE10')).toBeInTheDocument();
        expect(screen.getByText('-10%')).toBeInTheDocument();
        expect(screen.getByText('25 / 100')).toBeInTheDocument();
    });

    it('affiche une remise fixe en FCFA quand discount_type est "fixed"', () => {
        renderPanel({
            filteredPromoCodes: [makePromo({ discount_type: 'fixed', discount_value: 2000 })],
        });

        expect(screen.getByText(/-2\s?000/)).toBeInTheDocument();
    });

    it('affiche "Illimitée" sans date d\'expiration', () => {
        renderPanel({ filteredPromoCodes: [makePromo({ expires_at: null })] });
        expect(screen.getByText('Illimitée')).toBeInTheDocument();
    });

    it('déclenche onCreate au clic sur "Nouveau Code Promo"', () => {
        const props = renderPanel();
        fireEvent.click(screen.getByRole('button', { name: /Nouveau Code Promo/ }));
        expect(props.onCreate).toHaveBeenCalledTimes(1);
    });

    it('déclenche onEdit avec le code ciblé', () => {
        const promo = makePromo();
        const props = renderPanel({ filteredPromoCodes: [promo] });

        fireEvent.click(screen.getByRole('button', { name: 'Modifier' }));

        expect(props.onEdit).toHaveBeenCalledWith(promo);
    });

    it('déclenche onDelete avec le code ciblé', () => {
        const promo = makePromo();
        const props = renderPanel({ filteredPromoCodes: [promo] });

        fireEvent.click(screen.getByRole('button', { name: 'Supprimer' }));

        expect(props.onDelete).toHaveBeenCalledWith(promo);
    });

    it('déclenche onToggle et reflète le statut actif/inactif', () => {
        const promo = makePromo({ is_active: false });
        const props = renderPanel({ filteredPromoCodes: [promo] });

        expect(screen.getByText('○ Inactif')).toBeInTheDocument();
        fireEvent.click(screen.getByText('○ Inactif'));

        expect(props.onToggle).toHaveBeenCalledWith(promo);
    });

    it('affiche un état vide sans aucun code promo', () => {
        renderPanel({ filteredPromoCodes: [] });
        expect(screen.getByText(/Aucun code promo trouvé/)).toBeInTheDocument();
    });
});
