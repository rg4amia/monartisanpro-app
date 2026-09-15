// Onglet « WhatsApp » du backoffice — réglages du bouton "click-to-chat" du
// front office (numéro, message pré-rempli) et journal des clics enregistrés.

import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

import { DataTable, EmptyState, MetricCard, numberFormat, Surface } from '../shared';
import type { Paginated, WhatsappClickLogItem, WhatsappClickStats, WhatsappSettings } from '../shared';

interface WhatsAppPanelProps {
    whatsappClicksPage: Paginated<WhatsappClickLogItem> | null | undefined;
    whatsappClickStats: WhatsappClickStats;
    whatsappSettings: WhatsappSettings;
    search: string;
    onSearchChange: (value: string) => void;
    onSubmit: (event: FormEvent) => void;
    onReset: () => void;
    renderPagination: (links: Paginated<WhatsappClickLogItem>['links'] | undefined) => ReactNode;
    canManage: boolean;
}

const sourceLabels: Record<string, string> = {
    floating_button: 'Bouton flottant',
    footer: 'Footer',
};

function formatDateTime(value: string): string {
    return new Date(value).toLocaleString('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function WhatsAppPanel({
    whatsappClicksPage,
    whatsappClickStats,
    whatsappSettings,
    search,
    onSearchChange,
    onSubmit,
    onReset,
    renderPagination,
    canManage,
}: WhatsAppPanelProps) {
    const rows = whatsappClicksPage?.data ?? [];

    const { data, setData, post, processing, errors, recentlySuccessful } = useForm({
        whatsapp_widget_enabled: whatsappSettings.whatsapp_widget_enabled,
        whatsapp_widget_phone: whatsappSettings.whatsapp_widget_phone,
        whatsapp_widget_message: whatsappSettings.whatsapp_widget_message,
    });

    const handleSubmitSettings = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/vitrine/settings', { preserveScroll: true });
    };

    return (
        <section className="mt-5 space-y-5">
            <div className="grid gap-4 xl:grid-cols-3">
                <MetricCard description="Clics enregistrés depuis le lancement" tone="amber" value={numberFormat.format(whatsappClickStats.total)}>
                    Total des clics
                </MetricCard>
                <MetricCard description="Depuis minuit" tone="green" value={numberFormat.format(whatsappClickStats.today)}>
                    Clics aujourd'hui
                </MetricCard>
                <MetricCard description="Glissant, 7 derniers jours" tone="blue" value={numberFormat.format(whatsappClickStats.last_7_days)}>
                    Clics (7 jours)
                </MetricCard>
            </div>

            <Surface className="rounded-[28px] p-4 lg:p-5">
                <form onSubmit={handleSubmitSettings} className="space-y-4">
                    <div className="flex flex-col gap-1 border-b border-[var(--admin-border)] pb-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 className="text-base font-bold text-[var(--admin-text)]">Bouton WhatsApp du front office</h3>
                            <p className="text-xs text-[var(--admin-text-soft)] mt-0.5">
                                Numéro et message pré-rempli utilisés par le bouton flottant "click-to-chat" affiché sur le site vitrine.
                            </p>
                        </div>
                        <button
                            type="submit"
                            disabled={!canManage || processing}
                            className="rounded-xl px-5 py-2.5 text-sm font-semibold bg-[#ebb95e] text-[#241b16] hover:bg-[#e0ab4b] disabled:opacity-50 shadow-sm whitespace-nowrap"
                        >
                            {processing ? 'Enregistrement...' : recentlySuccessful ? 'Enregistré ✓' : 'Enregistrer'}
                        </button>
                    </div>

                    {Object.keys(errors).length > 0 && (
                        <div className="p-3 bg-red-100 border border-red-300 text-red-700 rounded-xl text-xs space-y-1">
                            {Object.values(errors).map((err, i) => (
                                <p key={i}>{err}</p>
                            ))}
                        </div>
                    )}

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)]">
                                Numéro WhatsApp (format international)
                            </label>
                            <input
                                type="text"
                                placeholder="+2250160606183"
                                value={data.whatsapp_widget_phone}
                                onChange={(e) => setData('whatsapp_widget_phone', e.target.value)}
                                disabled={!canManage}
                                className="w-full rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none disabled:opacity-60"
                            />
                        </div>
                        <div>
                            <label className="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)]">
                                Statut du bouton
                            </label>
                            <select
                                value={data.whatsapp_widget_enabled}
                                onChange={(e) => setData('whatsapp_widget_enabled', e.target.value)}
                                disabled={!canManage}
                                className="w-full rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none disabled:opacity-60"
                            >
                                <option value="1">Actif — affiché sur le site</option>
                                <option value="0">Désactivé — masqué du site</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label className="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)]">
                            Message pré-rempli
                        </label>
                        <textarea
                            rows={2}
                            value={data.whatsapp_widget_message}
                            onChange={(e) => setData('whatsapp_widget_message', e.target.value)}
                            disabled={!canManage}
                            className="w-full rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none disabled:opacity-60"
                        />
                    </div>
                </form>
            </Surface>

            <Surface className="rounded-[28px] p-4 lg:p-5">
                <form onSubmit={onSubmit} className="grid items-end gap-3 md:grid-cols-4">
                    <div className="md:col-span-3">
                        <label className="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)]">
                            Rechercher par page d'origine
                        </label>
                        <input
                            type="text"
                            placeholder="/, /contact, /artisans..."
                            value={search}
                            onChange={(e) => onSearchChange(e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                        />
                    </div>
                    <div className="flex gap-2">
                        <button
                            type="submit"
                            className="flex-1 rounded-xl px-4 py-2 text-xs font-semibold bg-[#ebb95e] text-[#241b16] hover:bg-[#e0ab4b]"
                        >
                            Filtrer
                        </button>
                        <button
                            type="button"
                            onClick={onReset}
                            className="flex-1 rounded-xl px-4 py-2 text-xs font-semibold border border-[var(--admin-border)] text-[var(--admin-text-soft)] hover:bg-white/40"
                        >
                            Réinitialiser
                        </button>
                    </div>
                </form>

                <div className="mt-5 overflow-x-auto">
                    {rows.length === 0 ? (
                        <EmptyState
                            title="Aucun clic enregistré"
                            description="Les clics sur le bouton WhatsApp du front office apparaîtront ici dès qu'un visiteur l'utilisera."
                        />
                    ) : (
                        <DataTable>
                            <thead>
                                <tr>
                                    <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Page d'origine</th>
                                    <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Source</th>
                                    <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Referrer</th>
                                    <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Date</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--admin-border)]">
                                {rows.map((log) => (
                                    <tr key={log.id} className="hover:bg-white/10 dark:hover:bg-white/5 transition">
                                        <td className="py-3 px-4 text-xs font-mono text-[var(--admin-text)]">{log.page || '—'}</td>
                                        <td className="py-3 px-4 text-xs text-[var(--admin-text-soft)]">{sourceLabels[log.source] ?? log.source}</td>
                                        <td className="py-3 px-4 text-xs text-[var(--admin-text-soft)] max-w-xs truncate">{log.referrer || '—'}</td>
                                        <td className="py-3 px-4 text-xs text-[var(--admin-text-soft)] whitespace-nowrap">{formatDateTime(log.created_at)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    )}
                </div>

                {renderPagination(whatsappClicksPage?.links)}
            </Surface>
        </section>
    );
}
