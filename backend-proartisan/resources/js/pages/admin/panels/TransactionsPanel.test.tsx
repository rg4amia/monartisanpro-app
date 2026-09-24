import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { money } from '../shared';
import type { AdminTransaction, Paginated, TransactionStats } from '../shared';
import { TransactionsPanel } from './TransactionsPanel';

const stats: TransactionStats = {
    confirmed: 10,
    pending: 2,
    failed: 1,
    escrow: 500000,
    released: 250000,
    volume_24h: 75000,
};

function makeTransaction(overrides: Partial<AdminTransaction> = {}): AdminTransaction {
    return {
        id: 1,
        type: 'acompte',
        montant: 150000,
        provider: 'wave',
        statut: 'confirme',
        wallet_source: 'client',
        wallet_dest: 'wallet_mo',
        reference_externe: 'WAVE-123',
        created_at: '2026-01-15T10:00:00Z',
        user: { id: 1, name: 'Awa Traoré' },
        ...overrides,
    } as AdminTransaction;
}

function makePage(data: AdminTransaction[]): Paginated<AdminTransaction> {
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

function renderPanel(overrides: Partial<React.ComponentProps<typeof TransactionsPanel>> = {}) {
    const onSelectTransaction = vi.fn();
    const onSearchChange = vi.fn();
    const onSubmit = vi.fn((e: React.FormEvent) => e.preventDefault());
    const onReset = vi.fn();

    const transactionsPage = makePage([makeTransaction()]);

    render(
        <TransactionsPanel
            financialKpis={{}}
            transactionStats={stats}
            transactionsPage={transactionsPage}
            search=""
            onSearchChange={onSearchChange}
            statusFilter=""
            onStatusFilterChange={vi.fn()}
            typeFilter=""
            onTypeFilterChange={vi.fn()}
            providerFilter=""
            onProviderFilterChange={vi.fn()}
            onSubmit={onSubmit}
            onReset={onReset}
            exportParams={{}}
            renderPagination={() => null}
            onSelectTransaction={onSelectTransaction}
            {...overrides}
        />,
    );

    return { onSelectTransaction, onSearchChange, onSubmit, onReset };
}

describe('TransactionsPanel', () => {
    it("affiche l'onglet Trésorerie par défaut sans planter", () => {
        renderPanel();
        expect(screen.getByRole('button', { name: /Trésorerie & Cash-Out/i })).toBeInTheDocument();
    });

    it('affiche le montant, le statut et le bénéficiaire dans le journal financier', () => {
        renderPanel();
        fireEvent.click(screen.getByRole('button', { name: /Journal Financier/i }));

        // `money()` insère une espace insécable fine (Intl fr-FR) que le
        // normaliseur DOM de Testing Library réduit à une espace classique :
        // on applique la même normalisation des deux côtés de la comparaison.
        expect(screen.getByText(money(150000).replace(/\s/g, ' '))).toBeInTheDocument();
        expect(screen.getByText('Awa Traoré')).toBeInTheDocument();
    });

    it('déclenche onSelectTransaction au clic sur une ligne', () => {
        const { onSelectTransaction } = renderPanel();
        fireEvent.click(screen.getByRole('button', { name: /Journal Financier/i }));

        fireEvent.click(screen.getByText('Awa Traoré'));
        expect(onSelectTransaction).toHaveBeenCalledTimes(1);
        expect(onSelectTransaction).toHaveBeenCalledWith(expect.objectContaining({ id: 1 }));
    });

    it("affiche un état vide quand aucune transaction ne correspond à la recherche", () => {
        renderPanel({ transactionsPage: makePage([]) });
        fireEvent.click(screen.getByRole('button', { name: /Journal Financier/i }));

        expect(screen.getByText('Aucun flux trouvé')).toBeInTheDocument();
    });

    it('transmet la saisie de recherche au parent sans la traiter localement', () => {
        const { onSearchChange } = renderPanel();
        fireEvent.click(screen.getByRole('button', { name: /Journal Financier/i }));

        fireEvent.change(screen.getByPlaceholderText(/ID, référence, wallet/i), { target: { value: 'WAVE-123' } });
        expect(onSearchChange).toHaveBeenCalledWith('WAVE-123');
    });
});
