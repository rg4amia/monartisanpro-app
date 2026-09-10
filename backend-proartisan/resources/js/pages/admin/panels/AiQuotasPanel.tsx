// Section « Quotas & consommation IA par utilisateur » de l'onglet « Suivi & Coûts IA ».
// Liste paginée serveur (useServerTable) + surcharge/blocage par utilisateur.

import type { FormEvent, ReactNode } from 'react';

import { DataTable, EmptyState, numberFormat, RoleBadge, SectionTitle, Surface } from '../shared';
import type { AiUserQuotaRow, Paginated } from '../shared';

const usd = (v: number | string): string => `$${Number(v || 0).toFixed(4)}`;

/** Limite journalière effective + libellé de statut d'une ligne. */
function effectiveLimit(row: AiUserQuotaRow, globalDaily: number): { label: string; tone: string } {
    if (row.blocked) return { label: 'Bloqué', tone: 'bg-rose-100 text-rose-700 border-rose-300' };
    if (row.override_daily !== null) {
        return {
            label: row.override_daily === 0 ? 'Illimité (surcharge)' : `${row.override_daily}/j (surcharge)`,
            tone: 'bg-amber-100 text-amber-800 border-amber-300',
        };
    }
    return {
        label: globalDaily > 0 ? `${globalDaily}/j (défaut)` : 'Illimité (défaut)',
        tone: 'bg-slate-100 text-slate-600 border-slate-300',
    };
}

interface AiQuotasPanelProps {
    aiUserQuotasPage: Paginated<AiUserQuotaRow> | undefined;
    globalDailyLimit: number;
    search: string;
    onSearchChange: (value: string) => void;
    roleFilter: string;
    onRoleFilterChange: (value: string) => void;
    onSubmit: (event: FormEvent) => void;
    onReset: () => void;
    renderPagination: (links: Paginated<AiUserQuotaRow>['links'] | undefined) => ReactNode;
    canManage: boolean;
    onEditQuota: (row: AiUserQuotaRow) => void;
}

export function AiQuotasPanel({
    aiUserQuotasPage,
    globalDailyLimit,
    search,
    onSearchChange,
    roleFilter,
    onRoleFilterChange,
    onSubmit,
    onReset,
    renderPagination,
    canManage,
    onEditQuota,
}: AiQuotasPanelProps) {
    const rows = aiUserQuotasPage?.data ?? [];

    return (
        <Surface className="rounded-[32px] p-5 lg:p-6">
            <SectionTitle
                description="Consommation de l'Assistant IA (chat BTP + diagnostic) par utilisateur mobile sur 24 h / 30 j, et surcharge du quota individuel."
                title="Quotas & consommation IA par utilisateur"
            />

            <form onSubmit={onSubmit} className="mt-4 grid items-end gap-3 rounded-2xl border border-[var(--admin-border)] bg-white/40 p-4 md:grid-cols-4">
                <div className="md:col-span-2">
                    <label className="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)]">Rechercher</label>
                    <input
                        type="text"
                        placeholder="Nom ou téléphone..."
                        value={search}
                        onChange={(e) => onSearchChange(e.target.value)}
                        className="w-full rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                    />
                </div>
                <div>
                    <label className="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)]">Rôle</label>
                    <select value={roleFilter} onChange={(e) => onRoleFilterChange(e.target.value)} className="w-full rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none">
                        <option value="">Tous</option>
                        <option value="client">Client</option>
                        <option value="artisan">Artisan</option>
                        <option value="fournisseur">Fournisseur</option>
                    </select>
                </div>
                <div className="flex gap-2 md:col-span-4">
                    <button type="submit" className="rounded-xl bg-[#ebb95e] px-4 py-2 text-xs font-bold text-[#241b16] transition hover:bg-[#dca850]">Filtrer</button>
                    <button type="button" onClick={onReset} className="rounded-xl border border-[var(--admin-border)] bg-white/60 px-3 py-2 text-xs font-bold text-[var(--admin-text-soft)] transition hover:bg-white/80">Réinitialiser</button>
                    {aiUserQuotasPage ? (
                        <span className="ml-auto self-center text-[11px] text-[var(--admin-muted)]">
                            {numberFormat.format(aiUserQuotasPage.total)} utilisateur(s) • page {aiUserQuotasPage.current_page}/{aiUserQuotasPage.last_page}
                        </span>
                    ) : null}
                </div>
            </form>

            <DataTable className="mt-5">
                <thead>
                    <tr>
                        <th>Utilisateur</th>
                        <th>Rôle</th>
                        <th>Requêtes 24 h</th>
                        <th>Requêtes 30 j</th>
                        <th>Coût 30 j</th>
                        <th>Quota effectif</th>
                        {canManage ? <th></th> : null}
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 ? (
                        <tr>
                            <td colSpan={canManage ? 7 : 6}>
                                <EmptyState description="Aucun utilisateur ne correspond à votre recherche." title="Aucun utilisateur" />
                            </td>
                        </tr>
                    ) : (
                        rows.map((row) => {
                            const eff = effectiveLimit(row, globalDailyLimit);
                            return (
                                <tr key={row.id} className="hover:bg-black/[0.02] transition">
                                    <td>
                                        <p className="font-semibold text-[var(--admin-text)]">{row.name}</p>
                                        <p className="text-xs text-[var(--admin-muted)]">{row.phone ?? '—'}</p>
                                    </td>
                                    <td><RoleBadge role={row.role} /></td>
                                    <td className="text-sm text-[var(--admin-text-soft)]">{numberFormat.format(row.requests_24h)}</td>
                                    <td className="text-sm text-[var(--admin-text-soft)]">{numberFormat.format(row.requests_30d)}</td>
                                    <td className="text-sm font-semibold text-[var(--admin-text)]">{usd(row.cost_30d)}</td>
                                    <td>
                                        <span className={`inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold ${eff.tone}`}>
                                            {eff.label}
                                        </span>
                                        {row.note ? <p className="mt-1 max-w-[220px] truncate text-[11px] text-[var(--admin-muted)]" title={row.note}>{row.note}</p> : null}
                                    </td>
                                    {canManage ? (
                                        <td className="text-right">
                                            <button
                                                type="button"
                                                onClick={() => onEditQuota(row)}
                                                className="rounded-lg border border-[var(--admin-border)] bg-white/60 px-2.5 py-1 text-xs font-bold text-[var(--admin-text-soft)] transition hover:bg-white/90"
                                            >
                                                Gérer
                                            </button>
                                        </td>
                                    ) : null}
                                </tr>
                            );
                        })
                    )}
                </tbody>
            </DataTable>

            {renderPagination(aiUserQuotasPage?.links)}
        </Surface>
    );
}
