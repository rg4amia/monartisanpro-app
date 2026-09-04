// Onglet « Évaluations & Scores » du backoffice.
// Chantier C2 : découpe de console.tsx. Chantier C4 (P1-6) : listes paginées + recherche serveur.

import type { FormEvent, ReactNode } from 'react';

import { cn } from '@/lib/utils';

import { actionButtonClass, DataTable, EmptyState, ExportButton, numberFormat, SectionTitle, shortDate, Surface } from '../shared';
import type { AdminEvaluation, ArtisanScoreItem, EvaluationStats, Paginated } from '../shared';

interface EvaluationsPanelProps {
    evalSubTab: 'list' | 'artisans';
    onEvalSubTabChange: (tab: 'list' | 'artisans') => void;
    evaluationsPage: Paginated<AdminEvaluation> | undefined;
    artisansScoresPage: Paginated<ArtisanScoreItem> | undefined;
    evaluationStats: EvaluationStats;
    evalSearch: string;
    onEvalSearchChange: (value: string) => void;
    onEvalSubmit: (event: FormEvent) => void;
    scoreSearch: string;
    onScoreSearchChange: (value: string) => void;
    onScoreSubmit: (event: FormEvent) => void;
    onResetFilters: () => void;
    exportParams: Record<string, string>;
    renderEvalPagination: (links: Paginated<AdminEvaluation>['links'] | undefined) => ReactNode;
    renderScorePagination: (links: Paginated<ArtisanScoreItem>['links'] | undefined) => ReactNode;
    onSelectArtisanLedger: (artisan: ArtisanScoreItem) => void;
    onToggleScoreFreeze: (artisan: ArtisanScoreItem) => void;
}

const searchInputClass =
    'w-full rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none';

export function EvaluationsPanel({
    evalSubTab,
    onEvalSubTabChange,
    evaluationsPage,
    artisansScoresPage,
    evaluationStats,
    evalSearch,
    onEvalSearchChange,
    onEvalSubmit,
    scoreSearch,
    onScoreSearchChange,
    onScoreSubmit,
    onResetFilters,
    exportParams,
    renderEvalPagination,
    renderScorePagination,
    onSelectArtisanLedger,
    onToggleScoreFreeze,
}: EvaluationsPanelProps) {
    const evaluations = evaluationsPage?.data ?? [];
    const artisanScores = artisansScoresPage?.data ?? [];

    return (
        <section className="mt-5 space-y-5">
            <div className="grid gap-3 sm:grid-cols-4">
                <div className="rounded-2xl border border-[var(--admin-border)] bg-white/50 p-3 text-xs">
                    <p className="text-[var(--admin-muted)]">Évaluations</p>
                    <p className="text-lg font-bold text-[var(--admin-text)]">{numberFormat.format(evaluationStats.evaluations_total)}</p>
                </div>
                <div className="rounded-2xl border border-[var(--admin-border)] bg-white/50 p-3 text-xs">
                    <p className="text-[var(--admin-muted)]">Note moyenne</p>
                    <p className="text-lg font-bold text-[var(--admin-text)]">{evaluationStats.note_moyenne} / 5</p>
                </div>
                <div className="rounded-2xl border border-[var(--admin-border)] bg-white/50 p-3 text-xs">
                    <p className="text-[var(--admin-muted)]">Artisans suivis</p>
                    <p className="text-lg font-bold text-[var(--admin-text)]">{numberFormat.format(evaluationStats.artisans_suivis)}</p>
                </div>
                <div className="rounded-2xl border border-[var(--admin-border)] bg-white/50 p-3 text-xs">
                    <p className="text-[var(--admin-muted)]">Scores gelés</p>
                    <p className="text-lg font-bold text-[var(--admin-text)]">{numberFormat.format(evaluationStats.scores_geles)}</p>
                </div>
            </div>

            <div className="flex gap-2 border-b border-[var(--admin-border)] pb-4">
                <button
                    type="button"
                    onClick={() => onEvalSubTabChange('list')}
                    className={cn(
                        'rounded-xl px-4 py-2 text-sm font-semibold transition',
                        evalSubTab === 'list'
                            ? 'bg-[#ebb95e] text-[#241b16]'
                            : 'text-[var(--admin-text-soft)] hover:bg-white/40',
                    )}
                >
                    Évaluations clients ({evaluationsPage?.total ?? 0})
                </button>
                <button
                    type="button"
                    onClick={() => onEvalSubTabChange('artisans')}
                    className={cn(
                        'rounded-xl px-4 py-2 text-sm font-semibold transition',
                        evalSubTab === 'artisans'
                            ? 'bg-[#ebb95e] text-[#241b16]'
                            : 'text-[var(--admin-text-soft)] hover:bg-white/40',
                    )}
                >
                    Scores ProsArtisan Artisans ({artisansScoresPage?.total ?? 0})
                </button>
            </div>

            {evalSubTab === 'list' ? (
                <Surface className="rounded-[32px] p-5 lg:p-6">
                    <SectionTitle
                        description="Historique des évaluations des chantiers avec le détail des notes de fiabilité, intégrité, qualité et réactivité."
                        title="Liste des Évaluations"
                    />
                    <form onSubmit={onEvalSubmit} className="mt-4 flex flex-wrap gap-2">
                        <input
                            type="text"
                            placeholder="Mission, client, artisan, commentaire..."
                            value={evalSearch}
                            onChange={(e) => onEvalSearchChange(e.target.value)}
                            className={cn(searchInputClass, 'max-w-sm')}
                        />
                        <button type="submit" className="rounded-xl bg-[#ebb95e] px-4 py-2 text-xs font-bold text-[#241b16] transition hover:bg-[#dca850]">Filtrer</button>
                        <button type="button" onClick={onResetFilters} className="rounded-xl border border-[var(--admin-border)] bg-white/60 px-3 py-2 text-xs font-bold text-[var(--admin-text-soft)] transition hover:bg-white/80">Réinitialiser</button>
                        <ExportButton resource="evaluations" params={exportParams} />
                    </form>
                    <DataTable className="mt-5">
                        <thead>
                            <tr>
                                <th>Mission</th>
                                <th>Évaluateur (Client)</th>
                                <th>Évalué (Artisan)</th>
                                <th>Note Générale</th>
                                <th>Critères ProsArtisan (F / I / Q / R)</th>
                                <th>Commentaire</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            {evaluations.length === 0 ? (
                                <tr>
                                    <td colSpan={7}>
                                        <EmptyState description="Aucune évaluation ne correspond à vos filtres." title="Aucune évaluation trouvée" />
                                    </td>
                                </tr>
                            ) : (
                                evaluations.map((evaluation) => (
                                    <tr key={evaluation.id}>
                                        <td className="font-medium text-[var(--admin-text)]">
                                            Mission #{evaluation.mission_id}
                                            {evaluation.mission && (
                                                <span className="block text-xs text-[var(--admin-muted)] truncate max-w-[150px]">
                                                    {evaluation.mission.description}
                                                </span>
                                            )}
                                        </td>
                                        <td>
                                            <div className="font-semibold text-[var(--admin-text)]">
                                                {evaluation.evaluateur?.name ?? 'Client inconnu'}
                                            </div>
                                            <div className="text-xs text-[var(--admin-muted)]">
                                                {evaluation.evaluateur?.phone}
                                            </div>
                                        </td>
                                        <td>
                                            <div className="font-semibold text-[var(--admin-text)]">
                                                {evaluation.evalue?.name ?? 'Artisan inconnu'}
                                            </div>
                                            <div className="text-xs text-[var(--admin-muted)]">
                                                {evaluation.evalue?.phone}
                                            </div>
                                        </td>
                                        <td>
                                            <div className="flex items-center gap-1">
                                                <span className="font-bold text-[#b77918]">{evaluation.note}</span>
                                                <span className="text-xs text-[var(--admin-muted)]">/ 5</span>
                                                <div className="flex text-amber-500">
                                                    {Array.from({ length: 5 }).map((_, idx) => (
                                                        <svg
                                                            key={idx}
                                                            className={cn('h-3.5 w-3.5', idx < evaluation.note ? 'fill-current' : 'stroke-current fill-none')}
                                                            viewBox="0 0 24 24"
                                                        >
                                                            <path d="m12 2 2.68 5.44L21 8.6l-4.5 4.38 1.06 6.18L12 16.26l-5.56 2.9 1.06-6.18L3 8.6l6.32-.92L12 2Z" />
                                                        </svg>
                                                    ))}
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div className="grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-[var(--admin-text-soft)]">
                                                <span>Fiabilité (40%) : <strong className="text-[var(--admin-text)]">{evaluation.fiabilite}/5</strong></span>
                                                <span>Intégrité (30%) : <strong className="text-[var(--admin-text)]">{evaluation.integrite}/5</strong></span>
                                                <span>Qualité (20%) : <strong className="text-[var(--admin-text)]">{evaluation.qualite}/5</strong></span>
                                                <span>Réactivité (10%) : <strong className="text-[var(--admin-text)]">{evaluation.reactivite}/5</strong></span>
                                            </div>
                                        </td>
                                        <td className="max-w-[200px] truncate text-sm italic text-[var(--admin-text-soft)]" title={evaluation.commentaire ?? ''}>
                                            {evaluation.commentaire ?? 'Aucun commentaire'}
                                        </td>
                                        <td className="text-xs text-[var(--admin-muted)]">
                                            {shortDate(evaluation.created_at)}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </DataTable>
                    {renderEvalPagination(evaluationsPage?.links)}
                </Surface>
            ) : (
                <Surface className="rounded-[32px] p-5 lg:p-6">
                    <SectionTitle
                        description="Administration des réputations d'artisans. Gelez les scores pour geler les droits au micro-crédit en cas de litige."
                        title="Scores ProsArtisan des Artisans"
                    />
                    <form onSubmit={onScoreSubmit} className="mt-4 flex flex-wrap gap-2">
                        <input
                            type="text"
                            placeholder="Nom, téléphone ou ID artisan..."
                            value={scoreSearch}
                            onChange={(e) => onScoreSearchChange(e.target.value)}
                            className={cn(searchInputClass, 'max-w-sm')}
                        />
                        <button type="submit" className="rounded-xl bg-[#ebb95e] px-4 py-2 text-xs font-bold text-[#241b16] transition hover:bg-[#dca850]">Filtrer</button>
                        <button type="button" onClick={onResetFilters} className="rounded-xl border border-[var(--admin-border)] bg-white/60 px-3 py-2 text-xs font-bold text-[var(--admin-text-soft)] transition hover:bg-white/80">Réinitialiser</button>
                    </form>
                    <DataTable className="mt-5">
                        <thead>
                            <tr>
                                <th>Artisan</th>
                                <th>Score ProsArtisan</th>
                                <th>Évaluations reçues</th>
                                <th>Moyennes critères (F / I / Q / R)</th>
                                <th>Statut du Score</th>
                                <th className="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {artisanScores.length === 0 ? (
                                <tr>
                                    <td colSpan={6}>
                                        <EmptyState description="Aucun artisan ne correspond à votre recherche." title="Aucun résultat" />
                                    </td>
                                </tr>
                            ) : (
                                artisanScores.map((artisan) => (
                                    <tr key={artisan.id}>
                                        <td>
                                            <div className="font-semibold text-[var(--admin-text)]">{artisan.name}</div>
                                            <div className="text-xs text-[var(--admin-muted)]">{artisan.phone}</div>
                                        </td>
                                        <td>
                                            <span className={cn(
                                                'rounded-full border px-3 py-1 text-xs font-bold',
                                                artisan.score_prosartisan >= 700
                                                    ? 'border-green-300 bg-green-50 text-green-700'
                                                    : artisan.score_prosartisan >= 400
                                                        ? 'border-amber-300 bg-amber-50 text-amber-700'
                                                        : 'border-rose-300 bg-rose-50 text-rose-700',
                                            )}>
                                                {artisan.score_prosartisan} / 1000
                                            </span>
                                            {artisan.score_prosartisan >= 700 && (
                                                <span className="ml-2 inline-flex items-center rounded bg-yellow-100 px-2 py-0.5 text-[10px] font-semibold text-yellow-800">
                                                    Micro-crédit éligible
                                                </span>
                                            )}
                                        </td>
                                        <td>
                                            <span className="text-sm font-semibold">{artisan.evaluations_recues_count}</span>
                                        </td>
                                        <td>
                                            <div className="grid grid-cols-2 gap-x-2 text-xs text-[var(--admin-text-soft)]">
                                                <span>F: <strong>{Number(artisan.evaluations_recues_avg_fiabilite ?? 0).toFixed(1)}/5</strong></span>
                                                <span>I: <strong>{Number(artisan.evaluations_recues_avg_integrite ?? 0).toFixed(1)}/5</strong></span>
                                                <span>Q: <strong>{Number(artisan.evaluations_recues_avg_qualite ?? 0).toFixed(1)}/5</strong></span>
                                                <span>R: <strong>{Number(artisan.evaluations_recues_avg_reactivite ?? 0).toFixed(1)}/5</strong></span>
                                            </div>
                                        </td>
                                        <td>
                                            {artisan.score_frozen ? (
                                                <span className="rounded-full border border-rose-300 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-600">
                                                    Gelé (Bloqué)
                                                </span>
                                            ) : (
                                                <span className="rounded-full border border-green-300 bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-600">
                                                    Actif (Calculé)
                                                </span>
                                            )}
                                        </td>
                                        <td className="text-right">
                                            <div className="flex justify-end gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => onSelectArtisanLedger(artisan)}
                                                    className="rounded-full border border-[var(--admin-border)] hover:bg-[#f7efe2] text-[#8a6b3d] px-3.5 py-1.5 text-xs font-semibold transition"
                                                >
                                                    Historique
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => onToggleScoreFreeze(artisan)}
                                                    className={actionButtonClass(artisan.score_frozen ? 'success' : 'danger')}
                                                >
                                                    {artisan.score_frozen ? 'Dégeler' : 'Geler'}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </DataTable>
                    {renderScorePagination(artisansScoresPage?.links)}
                </Surface>
            )}
        </section>
    );
}
