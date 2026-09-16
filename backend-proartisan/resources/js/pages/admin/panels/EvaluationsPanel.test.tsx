import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { EvaluationsPanel } from './EvaluationsPanel';
import type { AdminEvaluation, ArtisanScoreItem, EvaluationStats, Paginated } from '../shared';

const stats: EvaluationStats = {
    evaluations_total: 20,
    note_moyenne: 4.3,
    artisans_suivis: 8,
    scores_geles: 1,
} as EvaluationStats;

function makeEvaluation(overrides: Partial<AdminEvaluation> = {}): AdminEvaluation {
    return {
        id: 1,
        mission_id: 12,
        evaluateur_id: 2,
        evalue_id: 3,
        note: 4,
        fiabilite: 4,
        integrite: 5,
        qualite: 4,
        reactivite: 3,
        commentaire: 'Travail soigné.',
        created_at: '2026-02-01T09:00:00Z',
        mission: { id: 12, description: 'Réfection toiture' },
        evaluateur: { id: 2, name: 'Awa Traoré', phone: '+2250700000001' },
        evalue: { id: 3, name: "Koffi N'Guessan", phone: '+2250700000002' },
        ...overrides,
    } as AdminEvaluation;
}

function makeArtisan(overrides: Partial<ArtisanScoreItem> = {}): ArtisanScoreItem {
    return {
        id: 3,
        name: "Koffi N'Guessan",
        phone: '+2250700000002',
        score_prosartisan: 750,
        score_frozen: false,
        evaluations_recues_count: 12,
        evaluations_recues_avg_fiabilite: 4.2,
        evaluations_recues_avg_integrite: 4.5,
        evaluations_recues_avg_qualite: 4.0,
        evaluations_recues_avg_reactivite: 3.8,
        ...overrides,
    };
}

function makePage<T>(data: T[]): Paginated<T> {
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

function renderPanel(overrides: Partial<React.ComponentProps<typeof EvaluationsPanel>> = {}) {
    const props: React.ComponentProps<typeof EvaluationsPanel> = {
        evalSubTab: 'list',
        onEvalSubTabChange: vi.fn(),
        evaluationsPage: makePage([makeEvaluation()]),
        artisansScoresPage: makePage([makeArtisan()]),
        evaluationStats: stats,
        evalSearch: '',
        onEvalSearchChange: vi.fn(),
        onEvalSubmit: vi.fn((e: React.FormEvent) => e.preventDefault()),
        scoreSearch: '',
        onScoreSearchChange: vi.fn(),
        onScoreSubmit: vi.fn((e: React.FormEvent) => e.preventDefault()),
        onResetFilters: vi.fn(),
        exportParams: {},
        renderEvalPagination: () => null,
        renderScorePagination: () => null,
        onSelectArtisanLedger: vi.fn(),
        onToggleScoreFreeze: vi.fn(),
        ...overrides,
    };
    render(<EvaluationsPanel {...props} />);
    return props;
}

describe('EvaluationsPanel', () => {
    it('affiche la liste des évaluations avec les critères ProsArtisan', () => {
        renderPanel();

        expect(screen.getByText('Mission #12')).toBeInTheDocument();
        expect(screen.getByText('Awa Traoré')).toBeInTheDocument();
        expect(screen.getByText("Koffi N'Guessan")).toBeInTheDocument();
        expect(screen.getByText('Travail soigné.')).toBeInTheDocument();
    });

    it('affiche un état vide sans évaluation correspondante', () => {
        renderPanel({ evaluationsPage: makePage([]) });
        expect(screen.getByText('Aucune évaluation trouvée')).toBeInTheDocument();
    });

    it('bascule vers l\'onglet scores ProsArtisan des artisans', () => {
        const props = renderPanel();
        fireEvent.click(screen.getByRole('button', { name: /Scores ProsArtisan Artisans/ }));
        expect(props.onEvalSubTabChange).toHaveBeenCalledWith('artisans');
    });

    it('affiche le score ProsArtisan et le badge micro-crédit dès 700', () => {
        renderPanel({
            evalSubTab: 'artisans',
            artisansScoresPage: makePage([makeArtisan({ score_prosartisan: 750 })]),
        });

        expect(screen.getByText('750 / 1000')).toBeInTheDocument();
        expect(screen.getByText('Micro-crédit éligible')).toBeInTheDocument();
    });

    it('n\'affiche pas le badge micro-crédit sous 700', () => {
        renderPanel({
            evalSubTab: 'artisans',
            artisansScoresPage: makePage([makeArtisan({ score_prosartisan: 500 })]),
        });

        expect(screen.queryByText('Micro-crédit éligible')).not.toBeInTheDocument();
    });

    it('affiche le statut "Gelé" pour un score bloqué et propose de dégeler', () => {
        renderPanel({
            evalSubTab: 'artisans',
            artisansScoresPage: makePage([makeArtisan({ score_frozen: true })]),
        });

        expect(screen.getByText('Gelé (Bloqué)')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Dégeler' })).toBeInTheDocument();
    });

    it('déclenche onToggleScoreFreeze et onSelectArtisanLedger', () => {
        const artisan = makeArtisan();
        const props = renderPanel({
            evalSubTab: 'artisans',
            artisansScoresPage: makePage([artisan]),
        });

        fireEvent.click(screen.getByRole('button', { name: 'Historique' }));
        expect(props.onSelectArtisanLedger).toHaveBeenCalledWith(artisan);

        fireEvent.click(screen.getByRole('button', { name: 'Geler' }));
        expect(props.onToggleScoreFreeze).toHaveBeenCalledWith(artisan);
    });

    it('affiche un état vide sans artisan correspondant', () => {
        renderPanel({ evalSubTab: 'artisans', artisansScoresPage: makePage([]) });
        expect(screen.getByText('Aucun résultat')).toBeInTheDocument();
    });

    it('déclenche les callbacks de recherche', () => {
        const props = renderPanel();
        fireEvent.change(screen.getByPlaceholderText('Mission, client, artisan, commentaire...'), {
            target: { value: 'toiture' },
        });
        expect(props.onEvalSearchChange).toHaveBeenCalledWith('toiture');
    });
});
