import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({
    router: {
        post: vi.fn(),
        get: vi.fn(),
    },
}));

import type { LitigeItem, LitigeStats, Paginated } from '../shared';
import { LitigesPanel } from './LitigesPanel';

const stats: LitigeStats = { open: 3, resolved: 12, high_risk: 1, missions_disputed: 3 };

function makeLitige(overrides: Partial<LitigeItem> = {}): LitigeItem {
    return {
        id: 42,
        mission_id: 7,
        description: 'Matériaux non livrés selon le devis initial.',
        statut: 'ouvert',
        decision: null,
        created_at: '2026-02-01T09:00:00Z',
        mission: {
            montant_total: 350000,
            client: { name: 'Awa Traoré' },
            artisan: { name: 'Koffi N\'Guessan' },
        },
        ...overrides,
    };
}

function makePage(data: LitigeItem[]): Paginated<LitigeItem> {
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

function renderPanel(overrides: Partial<React.ComponentProps<typeof LitigesPanel>> = {}) {
    const onDecision = vi.fn();
    const onSubmit = vi.fn((e: React.FormEvent) => e.preventDefault());

    render(
        <LitigesPanel
            litigesPage={makePage([makeLitige()])}
            litigeStats={stats}
            fraudAlerts={0}
            search=""
            onSearchChange={vi.fn()}
            statusFilter=""
            onStatusFilterChange={vi.fn()}
            onSubmit={onSubmit}
            onReset={vi.fn()}
            exportParams={{}}
            renderPagination={() => null}
            actionLoading={false}
            onDecision={onDecision}
            canArbitrate
            {...overrides}
        />,
    );

    return { onDecision };
}

describe('LitigesPanel', () => {
    it('affiche les informations clés du dossier de litige', () => {
        renderPanel();
        expect(screen.getByText('Litige #42')).toBeInTheDocument();
        expect(screen.getByText('Awa Traoré')).toBeInTheDocument();
        expect(screen.getByText("Koffi N'Guessan")).toBeInTheDocument();
    });

    it('déclenche onDecision avec le bon litige et la bonne décision', () => {
        const { onDecision } = renderPanel();

        fireEvent.click(screen.getByRole('button', { name: 'Payer artisan' }));

        expect(onDecision).toHaveBeenCalledTimes(1);
        expect(onDecision).toHaveBeenCalledWith(expect.objectContaining({ id: 42 }), 'artisan');
    });

    it("masque les boutons d'arbitrage et affiche un message lecture seule sans la capacité admin.litiges.arbitrate", () => {
        renderPanel({ canArbitrate: false });

        expect(screen.queryByRole('button', { name: 'Rembourser client' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Payer artisan' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Geler et envoyer Référent' })).not.toBeInTheDocument();
        expect(screen.getByText('Lecture seule — arbitrage non autorisé.')).toBeInTheDocument();
    });

    it('un litige résolu affiche la décision finale sans boutons d’arbitrage', () => {
        renderPanel({
            litigesPage: makePage([makeLitige({ statut: 'resolu', decision: 'artisan' })]),
        });

        expect(screen.getByText(/Décision finale: Payer artisan/)).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Payer artisan' })).not.toBeInTheDocument();
    });

    it('affiche un état vide sans dossier de litige', () => {
        renderPanel({ litigesPage: makePage([]) });
        expect(screen.getByText('Aucun litige affiché')).toBeInTheDocument();
    });
});
