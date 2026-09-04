// Onglet « Litiges » du backoffice.
// Chantier C2 : découpe de console.tsx. Chantier C4 (P1-6) : liste paginée + filtres serveur.

import type { FormEvent, ReactNode } from 'react';

import {
    actionButtonClass,
    DecisionBadge,
    EmptyState,
    ExportButton,
    InfoPill,
    LitigeStatusBadge,
    litigeDecisionLabels,
    MetricCard,
    money,
    numberFormat,
    shortDate,
    Surface,
} from '../shared';
import type { LitigeItem, LitigeStats, Paginated } from '../shared';

interface LitigesPanelProps {
    litigesPage: Paginated<LitigeItem> | undefined;
    litigeStats: LitigeStats;
    fraudAlerts: number;
    search: string;
    onSearchChange: (value: string) => void;
    statusFilter: string;
    onStatusFilterChange: (value: string) => void;
    onSubmit: (event: FormEvent) => void;
    onReset: () => void;
    exportParams: Record<string, string>;
    renderPagination: (links: Paginated<LitigeItem>['links'] | undefined) => ReactNode;
    actionLoading: boolean;
    onDecision: (litige: LitigeItem, decision: 'client' | 'artisan' | 'gel') => void;
    /** Capacité `admin.litiges.arbitrate` (Chantier C6 / P2-10). */
    canArbitrate?: boolean;
}

export function LitigesPanel({
    litigesPage,
    litigeStats,
    fraudAlerts,
    search,
    onSearchChange,
    statusFilter,
    onStatusFilterChange,
    onSubmit,
    onReset,
    exportParams,
    renderPagination,
    actionLoading,
    onDecision,
    canArbitrate = true,
}: LitigesPanelProps) {
    const rows = litigesPage?.data ?? [];

    return (
        <section className="mt-5 space-y-5">
            <div className="grid gap-4 xl:grid-cols-4">
                <MetricCard description="Dossiers non résolus" tone="rose" trend="À arbitrer" value={numberFormat.format(litigeStats.open)}>
                    Litiges actifs
                </MetricCard>
                <MetricCard
                    description="Dossiers au-dessus de 2 000 000 FCFA"
                    tone="amber"
                    trend="Visite Référent à prévoir"
                    value={numberFormat.format(litigeStats.high_risk)}
                >
                    Haute priorité
                </MetricCard>
                <MetricCard description="Missions au statut litige" tone="blue" trend="Impact opérationnel" value={numberFormat.format(litigeStats.missions_disputed)}>
                    Missions bloquées
                </MetricCard>
                <MetricCard description="Signaux potentiels de fraude" tone="slate" trend="Surveillance J-Code" value={numberFormat.format(fraudAlerts)}>
                    Alertes
                </MetricCard>
            </div>

            <Surface className="rounded-[28px] p-4 lg:p-5">
                <form onSubmit={onSubmit} className="grid items-end gap-3 md:grid-cols-4">
                    <div className="md:col-span-2">
                        <label className="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)]">Rechercher</label>
                        <input
                            type="text"
                            placeholder="ID litige/mission, description, client, artisan..."
                            value={search}
                            onChange={(e) => onSearchChange(e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label className="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)]">Statut</label>
                        <select value={statusFilter} onChange={(e) => onStatusFilterChange(e.target.value)} className="w-full rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none">
                            <option value="">Tous</option>
                            <option value="ouvert">Ouvert</option>
                            <option value="en_cours">En cours</option>
                            <option value="resolu">Résolu</option>
                        </select>
                    </div>
                    <div className="flex gap-2">
                        <button type="submit" className="rounded-xl bg-[#ebb95e] px-4 py-2 text-xs font-bold text-[#241b16] transition hover:bg-[#dca850]">Filtrer</button>
                        <button type="button" onClick={onReset} className="rounded-xl border border-[var(--admin-border)] bg-white/60 px-3 py-2 text-xs font-bold text-[var(--admin-text-soft)] transition hover:bg-white/80">Réinitialiser</button>
                        <ExportButton resource="litiges" params={exportParams} />
                    </div>
                    {litigesPage ? (
                        <span className="text-[11px] text-[var(--admin-muted)] md:col-span-4">
                            {numberFormat.format(litigesPage.total)} litige(s) • page {litigesPage.current_page}/{litigesPage.last_page}
                        </span>
                    ) : null}
                </form>
            </Surface>

            <div className="grid gap-5 xl:grid-cols-2">
                {rows.length === 0 ? (
                    <Surface className="rounded-[32px] p-6 xl:col-span-2">
                        <EmptyState description="Aucun litige ne correspond à votre filtre." title="Aucun litige affiché" />
                    </Surface>
                ) : (
                    rows.map((litige) => (
                        <Surface key={litige.id} className="rounded-[32px] p-5 lg:p-6">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h3 className="text-lg font-semibold text-[var(--admin-text)]">Litige #{litige.id}</h3>
                                        <LitigeStatusBadge status={litige.statut} />
                                    </div>
                                    <p className="mt-1 text-sm text-[var(--admin-muted)]">
                                        Mission #{litige.mission_id} • {shortDate(litige.created_at)}
                                    </p>
                                </div>

                                {litige.decision ? <DecisionBadge decision={litige.decision} /> : null}
                            </div>

                            <p className="mt-4 text-sm leading-6 text-[var(--admin-text-soft)]">{litige.description}</p>

                            <div className="mt-5 grid gap-3 sm:grid-cols-3">
                                <InfoPill label="Client" value={litige.mission.client?.name ?? 'N/A'} />
                                <InfoPill label="Artisan" value={litige.mission.artisan?.name ?? 'N/A'} />
                                <InfoPill label="Montant" value={money(litige.mission.montant_total ?? 0)} />
                            </div>

                            {litige.statut !== 'resolu' && !canArbitrate ? (
                                <p className="mt-5 text-xs text-[var(--admin-muted)]">Lecture seule — arbitrage non autorisé.</p>
                            ) : litige.statut !== 'resolu' ? (
                                <div className="mt-5 flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        disabled={actionLoading}
                                        onClick={() => onDecision(litige, 'client')}
                                        className={actionButtonClass('danger')}
                                    >
                                        Rembourser client
                                    </button>
                                    <button
                                        type="button"
                                        disabled={actionLoading}
                                        onClick={() => onDecision(litige, 'artisan')}
                                        className={actionButtonClass('success')}
                                    >
                                        Payer artisan
                                    </button>
                                    <button
                                        type="button"
                                        disabled={actionLoading}
                                        onClick={() => onDecision(litige, 'gel')}
                                        className={actionButtonClass('secondary')}
                                    >
                                        Geler et envoyer Référent
                                    </button>
                                </div>
                            ) : (
                                <div className="mt-5 space-y-2">
                                    <p className="text-sm text-[var(--admin-text-soft)]">
                                        Décision finale: {litige.decision ? litigeDecisionLabels[litige.decision] : 'Aucune'}
                                    </p>
                                    {litige.decision === 'artisan' && litige.resolution_payload?.invoice_path ? (
                                        <div className="mt-2">
                                            <a
                                                href={`/admin/litiges/${litige.id}/invoice`}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="inline-flex items-center gap-2 text-sm text-[#10B981] hover:underline font-semibold"
                                            >
                                                Télécharger la Facture de Décaissement
                                            </a>
                                        </div>
                                    ) : null}
                                </div>
                            )}
                        </Surface>
                    ))
                )}
            </div>

            {renderPagination(litigesPage?.links)}
        </section>
    );
}
