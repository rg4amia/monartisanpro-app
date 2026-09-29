// Onglet « Annuaire artisans » du backoffice (Chantier 15) : disponibilités
// déclarées à valider avant publication sur le site vitrine, saisie directe,
// présence de chaque artisan dans l'annuaire.

import { router } from '@inertiajs/react';
import { useState } from 'react';

import { useServerTable } from '../hooks/useServerTable';
import { DataTable, EmptyState, MetricCard, numberFormat, renderPagination, Surface, useConfirm } from '../shared';
import type { ArtisanAvailabilityItem, DirectoryArtisanRow, DirectoryOptions, DirectoryStats, Paginated } from '../shared';
import { ArtisanAvailabilityEditor } from './ArtisanAvailabilityEditor';

interface ArtisanDirectoryPanelProps {
    artisans: Paginated<DirectoryArtisanRow> | null | undefined;
    stats: DirectoryStats | undefined;
    options: DirectoryOptions;
}

const STATUS_TONE: Record<ArtisanAvailabilityItem['effective_status'], string> = {
    disponible: 'bg-emerald-500/15 text-emerald-600',
    occupe: 'bg-amber-500/15 text-amber-600',
    conge: 'bg-slate-500/15 text-[var(--admin-muted)]',
};

function AvailabilitySummary({ availability }: { availability: ArtisanAvailabilityItem }) {
    return (
        <div className="space-y-1 text-xs">
            <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${STATUS_TONE[availability.effective_status]}`}>{availability.effective_label}</span>
            <p className="text-[var(--admin-text-soft)]">{availability.schedule_summary ?? 'Horaires non précisés'}</p>
            {availability.night_work ? <p className="text-[var(--admin-muted)]">Intervient la nuit</p> : null}
        </div>
    );
}

export function ArtisanDirectoryPanel({ artisans, stats, options }: ArtisanDirectoryPanelProps) {
    const { confirm, dialog } = useConfirm();
    const [editing, setEditing] = useState<DirectoryArtisanRow | null>(null);
    const table = useServerTable({
        path: '/admin/annuaire-artisans',
        only: ['directoryArtisans', 'directoryStats'],
        initial: { directory_search: '', directory_visibility: '', directory_availability: '' },
        storageKey: 'artisan_directory',
    });
    const rows = artisans?.data ?? [];
    const selectClass =
        'rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none';
    const actionClass = 'rounded-lg border border-[var(--admin-border)] px-2 py-1 text-[11px] font-semibold text-[var(--admin-text)] hover:border-amber-500';

    const approve = (row: DirectoryArtisanRow) => {
        if (!row.pending) return;
        router.post(`/admin/annuaire-artisans/disponibilites/${row.pending.id}/valider`, {}, { preserveScroll: true });
    };

    const reject = async (row: DirectoryArtisanRow) => {
        if (!row.pending) return;
        const reason = await confirm({
            title: 'Refuser cette disponibilité ?',
            message: `${row.name} en sera informé avec votre motif ; sa disponibilité actuelle reste affichée.`,
            confirmLabel: 'Refuser',
            tone: 'danger',
            promptLabel: 'Motif du refus',
            promptMinLength: 5,
        });
        if (typeof reason === 'string') {
            router.post(`/admin/annuaire-artisans/disponibilites/${row.pending.id}/refuser`, { reason }, { preserveScroll: true });
        }
    };

    const hide = async (row: DirectoryArtisanRow) => {
        const reason = await confirm({
            title: "Retirer de l'annuaire ?",
            message: `La fiche de ${row.name} disparaît du site vitrine. Son compte et ses missions ne changent pas ; il en est informé.`,
            confirmLabel: 'Retirer',
            tone: 'danger',
            promptLabel: 'Motif du retrait',
            promptMinLength: 5,
        });
        if (typeof reason === 'string') {
            router.post(`/admin/annuaire-artisans/${row.id}/retirer`, { reason }, { preserveScroll: true });
        }
    };

    const show = async (row: DirectoryArtisanRow) => {
        const ok = await confirm({
            title: "Réactiver dans l'annuaire ?",
            message: `La fiche de ${row.name} sera de nouveau visible sur le site vitrine${row.blockers.length > 1 ? ' dès que les autres conditions seront remplies' : ''}.`,
            confirmLabel: 'Réactiver',
        });
        if (ok) router.post(`/admin/annuaire-artisans/${row.id}/publier`, {}, { preserveScroll: true });
    };

    return (
        <section className="mt-5 space-y-5">
            {stats ? (
                <div className="grid gap-4 md:grid-cols-4">
                    <MetricCard description="Artisans inscrits (hors comptes anonymisés)" tone="slate" trend="total" value={numberFormat.format(stats.artisans)}>
                        Artisans
                    </MetricCard>
                    <MetricCard description="KYC actif, compte actif, non retirés" tone="green" trend="vitrine" value={numberFormat.format(stats.published)}>
                        Visibles dans l'annuaire
                    </MetricCard>
                    <MetricCard description="Retirés par un administrateur" tone={stats.hidden > 0 ? 'rose' : 'slate'} trend="retirés" value={numberFormat.format(stats.hidden)}>
                        Désactivés
                    </MetricCard>
                    <MetricCard description="Disponibilités déclarées à valider" tone={stats.pending > 0 ? 'amber' : 'green'} trend="à traiter" value={numberFormat.format(stats.pending)}>
                        À valider
                    </MetricCard>
                </div>
            ) : null}

            <Surface className="rounded-[32px] p-5 lg:p-6">
                <form onSubmit={table.apply} className="grid gap-3 md:grid-cols-4">
                    <input
                        type="search"
                        value={table.filters.directory_search}
                        onChange={(e) => table.set('directory_search', e.target.value)}
                        placeholder="Nom ou téléphone..."
                        aria-label="Rechercher un artisan"
                        className={selectClass}
                    />
                    <select value={table.filters.directory_visibility} onChange={(e) => table.applyWith('directory_visibility', e.target.value)} aria-label="Présence" className={selectClass}>
                        <option value="">Toutes les fiches</option>
                        <option value="visible">Actives dans l'annuaire</option>
                        <option value="hidden">Désactivées</option>
                    </select>
                    <select
                        value={table.filters.directory_availability}
                        onChange={(e) => table.applyWith('directory_availability', e.target.value)}
                        aria-label="Disponibilité"
                        className={selectClass}
                    >
                        <option value="">Toutes les disponibilités</option>
                        <option value="pending">À valider</option>
                        <option value="published">Publiée</option>
                        <option value="none">Jamais publiée</option>
                    </select>
                    <div className="flex gap-2">
                        <button type="submit" className="rounded-xl bg-[#ebb95e] px-3 py-2 text-xs font-bold text-[#1d1a14]">
                            Filtrer
                        </button>
                        {table.hasActiveFilters ? (
                            <button type="button" onClick={table.reset} className="rounded-xl px-3 py-2 text-xs font-semibold text-[var(--admin-muted)]">
                                Réinitialiser
                            </button>
                        ) : null}
                    </div>
                </form>

                <DataTable className="mt-5">
                    <thead>
                        <tr>
                            <th>Artisan</th>
                            <th>Annuaire</th>
                            <th>Disponibilité publiée</th>
                            <th>À valider</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 ? (
                            <tr>
                                <td colSpan={5}>
                                    <EmptyState description="Aucun artisan ne correspond à ces critères." title="Aucun artisan" />
                                </td>
                            </tr>
                        ) : (
                            rows.map((row) => (
                                <tr key={row.id}>
                                    <td className="text-xs">
                                        <p className="font-semibold text-[var(--admin-text)]">{row.name}</p>
                                        <p className="text-[var(--admin-muted)]">{[row.trade ?? 'Métier non renseigné', row.city].filter(Boolean).join(' · ')}</p>
                                        <p className="text-[var(--admin-muted)]">{row.phone ?? '—'}</p>
                                    </td>
                                    <td className="text-xs">
                                        {row.listed ? (
                                            <span className="rounded-full bg-emerald-500/15 px-2 py-0.5 text-[10px] font-bold text-emerald-600">Visible</span>
                                        ) : (
                                            <span className="rounded-full bg-rose-500/15 px-2 py-0.5 text-[10px] font-bold text-rose-600">Non visible</span>
                                        )}
                                        {row.blockers.length > 0 ? <p className="mt-1 text-[var(--admin-muted)]">{row.blockers.join(' · ')}</p> : null}
                                        {row.hidden_reason ? <p className="mt-1 text-[var(--admin-text-soft)]">Motif : {row.hidden_reason}</p> : null}
                                    </td>
                                    <td>{row.published ? <AvailabilitySummary availability={row.published} /> : <span className="text-xs text-[var(--admin-muted)]">Aucune</span>}</td>
                                    <td>
                                        {row.pending ? (
                                            <div className="space-y-2" data-testid={`pending-${row.id}`}>
                                                <AvailabilitySummary availability={row.pending} />
                                                <div className="flex gap-1">
                                                    <button type="button" className={`${actionClass} text-emerald-600`} onClick={() => approve(row)} aria-label={`Valider la disponibilité de ${row.name}`}>
                                                        Valider
                                                    </button>
                                                    <button type="button" className={`${actionClass} text-rose-500`} onClick={() => reject(row)} aria-label={`Refuser la disponibilité de ${row.name}`}>
                                                        Refuser
                                                    </button>
                                                </div>
                                            </div>
                                        ) : (
                                            <span className="text-xs text-[var(--admin-muted)]">—</span>
                                        )}
                                    </td>
                                    <td>
                                        <div className="flex flex-wrap gap-1">
                                            <button type="button" className={actionClass} onClick={() => setEditing(row)} aria-label={`Saisir la disponibilité de ${row.name}`}>
                                                Saisir la disponibilité
                                            </button>
                                            {row.hidden ? (
                                                <button type="button" className={`${actionClass} text-emerald-600`} onClick={() => show(row)} aria-label={`Réactiver ${row.name} dans l'annuaire`}>
                                                    Réactiver
                                                </button>
                                            ) : (
                                                <button type="button" className={`${actionClass} text-rose-500`} onClick={() => hide(row)} aria-label={`Retirer ${row.name} de l'annuaire`}>
                                                    Désactiver
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </DataTable>
                {renderPagination(artisans?.links, ['directoryArtisans', 'directoryStats'])}
            </Surface>

            {editing ? <ArtisanAvailabilityEditor key={editing.id} artisan={editing} options={options} onClose={() => setEditing(null)} /> : null}
            {dialog}
        </section>
    );
}
