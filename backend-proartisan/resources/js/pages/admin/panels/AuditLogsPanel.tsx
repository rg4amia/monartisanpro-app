// Onglet « Journal d'audit » du backoffice (Chantier C3 / P0-4).
// Traçabilité horodatée et attribuée des actions administrateur sensibles.

import type { FormEvent, ReactNode } from 'react';

import { cn } from '@/lib/utils';

import {
    auditActionLabels,
    auditActionTone,
    DataTable,
    dateTimeShort,
    EmptyState,
    Surface,
    toneBadgeClasses,
} from '../shared';
import type { AdminActivityLogItem, AuditAdminOption, PaginatedAuditLogs } from '../shared';

interface AuditLogsPanelProps {
    auditLogs: PaginatedAuditLogs | undefined;
    auditActions: string[];
    auditAdmins: AuditAdminOption[];
    search: string;
    onSearchChange: (value: string) => void;
    actionFilter: string;
    onActionFilterChange: (value: string) => void;
    adminFilter: string;
    onAdminFilterChange: (value: string) => void;
    dateFrom: string;
    onDateFromChange: (value: string) => void;
    dateTo: string;
    onDateToChange: (value: string) => void;
    onSubmit: (event: FormEvent) => void;
    onReset: () => void;
    renderPagination: (links: PaginatedAuditLogs['links'] | undefined) => ReactNode;
}

function actionLabel(action: string): string {
    return auditActionLabels[action] ?? action;
}

function ContextPreview({ context }: { context: Record<string, unknown> | null }) {
    if (!context || Object.keys(context).length === 0) {
        return <span className="text-[9px] text-[var(--admin-muted)]">-</span>;
    }

    return (
        <details className="cursor-pointer text-[10px] font-mono text-amber-800">
            <summary className="text-[9px] font-bold text-amber-700 hover:underline">
                Voir le détail ({Object.keys(context).length})
            </summary>
            <pre className="mt-1 overflow-x-auto rounded-lg border border-black/10 bg-black/[0.03] p-2 text-[9px] leading-tight">
                {JSON.stringify(context, null, 2)}
            </pre>
        </details>
    );
}

export function AuditLogsPanel({
    auditLogs,
    auditActions,
    auditAdmins,
    search,
    onSearchChange,
    actionFilter,
    onActionFilterChange,
    adminFilter,
    onAdminFilterChange,
    dateFrom,
    onDateFromChange,
    dateTo,
    onDateToChange,
    onSubmit,
    onReset,
    renderPagination,
}: AuditLogsPanelProps) {
    const rows: AdminActivityLogItem[] = auditLogs?.data ?? [];

    return (
        <section className="mt-5 space-y-5">
            <Surface className="rounded-[32px] border border-[#e2d5c3]/60 bg-gradient-to-br from-white via-[#fcfaf7] to-[#f7f2ea] p-5 lg:p-6">
                <div className="flex flex-col justify-between gap-4 border-b border-[var(--admin-border)] pb-4 md:flex-row md:items-center">
                    <div>
                        <h3 className="text-lg font-bold text-[var(--admin-text)]">Journal d'audit des actions administrateur</h3>
                        <p className="text-xs text-[var(--admin-text-soft)]">
                            Revue KYC, arbitrage de litige, gel de score, suspension de compte, modification de paramètres et
                            connexions admin. Table append-only : {auditLogs?.total ?? 0} entrée(s).
                        </p>
                    </div>
                </div>

                <form
                    onSubmit={onSubmit}
                    className="mt-5 grid items-end gap-4 rounded-2xl border border-[var(--admin-border)] bg-white/40 p-4 md:grid-cols-5"
                >
                    <div>
                        <label className="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)]">Rechercher</label>
                        <input
                            type="text"
                            placeholder="Admin, action, entité, IP..."
                            value={search}
                            onChange={(e) => onSearchChange(e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label className="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)]">Action</label>
                        <select
                            value={actionFilter}
                            onChange={(e) => onActionFilterChange(e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                        >
                            <option value="">Toutes les actions</option>
                            {auditActions.map((action) => (
                                <option key={action} value={action}>{actionLabel(action)}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)]">Administrateur</label>
                        <select
                            value={adminFilter}
                            onChange={(e) => onAdminFilterChange(e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                        >
                            <option value="">Tous les admins</option>
                            {auditAdmins.map((admin) => (
                                <option key={admin.admin_id} value={admin.admin_id}>
                                    {admin.admin_name ?? `Admin #${admin.admin_id}`}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)]">Du</label>
                        <input
                            type="date"
                            value={dateFrom}
                            onChange={(e) => onDateFromChange(e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label className="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)]">Au</label>
                        <input
                            type="date"
                            value={dateTo}
                            onChange={(e) => onDateToChange(e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                        />
                    </div>
                    <div className="flex gap-2 md:col-span-5">
                        <button
                            type="submit"
                            className="rounded-xl bg-[#ebb95e] px-4 py-2 text-xs font-bold text-[#241b16] transition hover:bg-[#dca850]"
                        >
                            Filtrer
                        </button>
                        <button
                            type="button"
                            onClick={onReset}
                            className="rounded-xl border border-[var(--admin-border)] bg-white/60 px-3 py-2 text-xs font-bold text-[var(--admin-text-soft)] transition hover:bg-white/80"
                        >
                            Réinitialiser
                        </button>
                    </div>
                </form>

                <DataTable className="mt-5">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Administrateur</th>
                            <th>Action</th>
                            <th>Entité concernée</th>
                            <th>Détail</th>
                            <th>Origine</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 ? (
                            <tr>
                                <td colSpan={6}>
                                    <EmptyState description="Aucune action ne correspond aux filtres sélectionnés." title="Journal vide" />
                                </td>
                            </tr>
                        ) : (
                            rows.map((log) => (
                                <tr key={log.id} className="transition hover:bg-black/[0.02]">
                                    <td className="whitespace-nowrap font-mono text-xs text-[var(--admin-text-soft)]">{dateTimeShort(log.created_at)}</td>
                                    <td>
                                        {log.admin_name || log.admin?.name ? (
                                            <div>
                                                <p className="text-xs font-semibold text-[var(--admin-text)]">{log.admin_name ?? log.admin?.name}</p>
                                                {log.admin?.phone && <p className="text-[10px] text-[var(--admin-muted)]">{log.admin.phone}</p>}
                                            </div>
                                        ) : (
                                            <span className="text-xs text-[var(--admin-muted)]">Système / anonyme</span>
                                        )}
                                    </td>
                                    <td>
                                        <span className={cn('rounded-full border px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider', toneBadgeClasses(auditActionTone[log.action] ?? 'slate'))}>
                                            {actionLabel(log.action)}
                                        </span>
                                    </td>
                                    <td className="max-w-xs">
                                        {log.subject_label ? (
                                            <div>
                                                <p className="text-xs font-medium text-[var(--admin-text)]">{log.subject_label}</p>
                                                {log.subject_type && (
                                                    <p className="text-[10px] text-[var(--admin-muted)]">
                                                        {log.subject_type.split('\\').pop()}
                                                        {log.subject_id ? ` #${log.subject_id}` : ''}
                                                    </p>
                                                )}
                                            </div>
                                        ) : (
                                            <span className="text-[10px] text-[var(--admin-muted)]">-</span>
                                        )}
                                    </td>
                                    <td className="max-w-sm"><ContextPreview context={log.context} /></td>
                                    <td className="whitespace-nowrap font-mono text-[10px] text-[var(--admin-muted)]">{log.ip_address ?? '-'}</td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </DataTable>

                {renderPagination(auditLogs?.links)}
            </Surface>
        </section>
    );
}
