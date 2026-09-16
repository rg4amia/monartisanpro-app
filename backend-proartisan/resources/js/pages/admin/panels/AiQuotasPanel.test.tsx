import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { AiQuotasPanel } from './AiQuotasPanel';
import type { AiUserQuotaRow, Paginated } from '../shared';

function makeRow(overrides: Partial<AiUserQuotaRow> = {}): AiUserQuotaRow {
    return {
        id: 1,
        name: 'Awa Traoré',
        phone: '+2250700000001',
        role: 'client',
        override_daily: null,
        override_monthly: null,
        blocked: false,
        note: null,
        requests_24h: 5,
        requests_30d: 40,
        cost_30d: 1.2345,
        ...overrides,
    };
}

function makePage(data: AiUserQuotaRow[]): Paginated<AiUserQuotaRow> {
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

function renderPanel(overrides: Partial<React.ComponentProps<typeof AiQuotasPanel>> = {}) {
    const props: React.ComponentProps<typeof AiQuotasPanel> = {
        aiUserQuotasPage: makePage([makeRow()]),
        globalDailyLimit: 20,
        search: '',
        onSearchChange: vi.fn(),
        roleFilter: '',
        onRoleFilterChange: vi.fn(),
        onSubmit: vi.fn((e: React.FormEvent) => e.preventDefault()),
        onReset: vi.fn(),
        renderPagination: () => null,
        canManage: true,
        onEditQuota: vi.fn(),
        ...overrides,
    };
    render(<AiQuotasPanel {...props} />);
    return props;
}

describe('AiQuotasPanel', () => {
    it('affiche la consommation IA par utilisateur', () => {
        renderPanel();

        expect(screen.getByText('Awa Traoré')).toBeInTheDocument();
        expect(screen.getByText('$1.2345')).toBeInTheDocument();
    });

    it('affiche la limite par défaut quand aucune surcharge n\'est définie', () => {
        renderPanel({ globalDailyLimit: 20, aiUserQuotasPage: makePage([makeRow({ override_daily: null })]) });
        expect(screen.getByText('20/j (défaut)')).toBeInTheDocument();
    });

    it('affiche la surcharge individuelle quand elle est définie', () => {
        renderPanel({ aiUserQuotasPage: makePage([makeRow({ override_daily: 5 })]) });
        expect(screen.getByText('5/j (surcharge)')).toBeInTheDocument();
    });

    it('affiche "Illimité (surcharge)" quand la surcharge vaut 0', () => {
        renderPanel({ aiUserQuotasPage: makePage([makeRow({ override_daily: 0 })]) });
        expect(screen.getByText('Illimité (surcharge)')).toBeInTheDocument();
    });

    it('affiche "Bloqué" pour un utilisateur bloqué, sans tenir compte de la surcharge', () => {
        renderPanel({
            aiUserQuotasPage: makePage([makeRow({ blocked: true, override_daily: 10 })]),
        });
        expect(screen.getByText('Bloqué')).toBeInTheDocument();
        expect(screen.queryByText('10/j (surcharge)')).not.toBeInTheDocument();
    });

    it('affiche un état vide sans utilisateur correspondant', () => {
        renderPanel({ aiUserQuotasPage: makePage([]) });
        expect(screen.getByText('Aucun utilisateur')).toBeInTheDocument();
    });

    it('déclenche onEditQuota au clic sur "Gérer"', () => {
        const row = makeRow();
        const props = renderPanel({ aiUserQuotasPage: makePage([row]) });

        fireEvent.click(screen.getByRole('button', { name: 'Gérer' }));

        expect(props.onEditQuota).toHaveBeenCalledWith(row);
    });

    it('masque la colonne de gestion sans la capacité admin.ai.manage', () => {
        renderPanel({ canManage: false });
        expect(screen.queryByRole('button', { name: 'Gérer' })).not.toBeInTheDocument();
    });

    it('déclenche les callbacks de recherche et de filtre par rôle', () => {
        const props = renderPanel();

        fireEvent.change(screen.getByPlaceholderText('Nom ou téléphone...'), {
            target: { value: 'Awa' },
        });
        expect(props.onSearchChange).toHaveBeenCalledWith('Awa');

        fireEvent.change(screen.getByRole('combobox'), { target: { value: 'artisan' } });
        expect(props.onRoleFilterChange).toHaveBeenCalledWith('artisan');
    });
});
