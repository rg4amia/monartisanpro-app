// Onglet « Messages push & SMS » du backoffice (Chantier 14, lot C) :
// textes et canaux de chaque notification, envoi de test, journal des envois.

import { useMemo, useState } from 'react';

import { useServerTable } from '../hooks/useServerTable';
import {
    DataTable,
    dateTimeShort,
    EmptyState,
    MetricCard,
    numberFormat,
    renderPagination,
    RoleBadge,
    SectionTitle,
    Surface,
} from '../shared';
import type { NotificationDeliveryRow, NotificationDeliveryStats, NotificationEventItem, Paginated } from '../shared';
import { NotificationMessageEditor } from './NotificationMessageEditor';

type SubTab = 'modeles' | 'journal';

interface NotificationMessagesPanelProps {
    events: NotificationEventItem[];
    domains: Record<string, string>;
    audiences: Record<string, string>;
    smsMaxSegments: number;
    deliveries: Paginated<NotificationDeliveryRow> | null | undefined;
    deliveryStats: NotificationDeliveryStats | undefined;
}

const DELIVERY_STATUS_TONE: Record<NotificationDeliveryRow['status'], string> = {
    envoye: 'bg-emerald-500/15 text-emerald-600',
    echoue: 'bg-rose-500/15 text-rose-600',
    ignore: 'bg-slate-500/15 text-[var(--admin-muted)]',
};

function ChannelChips({ event }: { event: NotificationEventItem }) {
    const chips: Array<[string, boolean]> = [
        ['App', event.current.channels.in_app],
        ['Push', event.current.channels.push],
        ['SMS', event.current.channels.sms],
    ];
    return (
        <div className="flex gap-1">
            {chips.map(([label, active]) => (
                <span
                    key={label}
                    className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${active ? 'bg-[#ebb95e]/20 text-[var(--admin-text)]' : 'text-[var(--admin-muted)] line-through'}`}
                    title={active ? `${label} actif` : `${label} désactivé`}
                >
                    {label}
                </span>
            ))}
        </div>
    );
}

function TemplatesView({ events, domains, audiences, smsMaxSegments }: Omit<NotificationMessagesPanelProps, 'deliveries' | 'deliveryStats'>) {
    const [search, setSearch] = useState('');
    const [domain, setDomain] = useState('');
    const [audience, setAudience] = useState('');
    const [only, setOnly] = useState<'' | 'sms' | 'modified'>('');
    const [editing, setEditing] = useState<NotificationEventItem | null>(null);

    const filtered = useMemo(() => {
        const needle = search.trim().toLowerCase();
        return events.filter((event) => {
            if (domain && event.domain !== domain) return false;
            if (audience && event.audience !== audience) return false;
            if (only === 'sms' && !event.current.channels.sms) return false;
            if (only === 'modified' && !event.overridden) return false;
            if (!needle) return true;
            return [event.label, event.key, event.current.title, event.current.body, event.current.sms_body ?? '']
                .join(' ')
                .toLowerCase()
                .includes(needle);
        });
    }, [events, search, domain, audience, only]);

    const groups = Object.entries(domains)
        .map(([key, label]) => ({ key, label, items: filtered.filter((event) => event.domain === key) }))
        .filter((group) => group.items.length > 0);

    const selectClass =
        'rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none';

    return (
        <div className="space-y-5">
            <Surface className="rounded-[32px] p-5">
                <div className="grid gap-3 md:grid-cols-4">
                    <input
                        type="search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Rechercher un message ou un texte..."
                        aria-label="Rechercher un message"
                        className={`${selectClass} md:col-span-1`}
                    />
                    <select value={domain} onChange={(e) => setDomain(e.target.value)} aria-label="Domaine" className={selectClass}>
                        <option value="">Tous les domaines</option>
                        {Object.entries(domains).map(([key, label]) => (
                            <option key={key} value={key}>
                                {label}
                            </option>
                        ))}
                    </select>
                    <select value={audience} onChange={(e) => setAudience(e.target.value)} aria-label="Destinataire" className={selectClass}>
                        <option value="">Tous les destinataires</option>
                        {Object.entries(audiences).map(([key, label]) => (
                            <option key={key} value={key}>
                                {label}
                            </option>
                        ))}
                    </select>
                    <select value={only} onChange={(e) => setOnly(e.target.value as typeof only)} aria-label="Afficher" className={selectClass}>
                        <option value="">Tous les messages</option>
                        <option value="sms">Envoyés par SMS</option>
                        <option value="modified">Modifiés</option>
                    </select>
                </div>
                <p className="mt-3 text-xs text-[var(--admin-muted)]">
                    {numberFormat.format(filtered.length)} message(s) · {numberFormat.format(events.filter((e) => e.overridden).length)} modifié(s) ·{' '}
                    {numberFormat.format(events.filter((e) => e.current.channels.sms).length)} envoyé(s) aussi par SMS
                </p>
            </Surface>

            {groups.length === 0 ? (
                <Surface className="rounded-[32px] p-5">
                    <EmptyState description="Aucun message ne correspond à ces filtres." title="Aucun message" />
                </Surface>
            ) : (
                groups.map((group) => (
                    <Surface key={group.key} className="rounded-[32px] p-5 lg:p-6">
                        <SectionTitle description={`${group.items.length} message(s)`} title={group.label} />
                        <ul className="mt-4 divide-y divide-[var(--admin-border)]">
                            {group.items.map((event) => (
                                <li key={event.key} className="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="text-sm font-semibold text-[var(--admin-text)]">{event.label}</p>
                                            {event.overridden ? (
                                                <span className="rounded-full bg-amber-500/15 px-2 py-0.5 text-[10px] font-bold text-amber-600">Modifié</span>
                                            ) : (
                                                <span className="rounded-full px-2 py-0.5 text-[10px] font-semibold text-[var(--admin-muted)]">Texte d'origine</span>
                                            )}
                                        </div>
                                        <p className="mt-0.5 truncate text-xs text-[var(--admin-text-soft)]">
                                            {audiences[event.audience] ?? event.audience} · {event.current.title}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <ChannelChips event={event} />
                                        <button
                                            type="button"
                                            onClick={() => setEditing(event)}
                                            aria-label={`Modifier : ${event.label}`}
                                            className="rounded-xl border border-[var(--admin-border)] px-3 py-1.5 text-xs font-semibold text-[var(--admin-text)] hover:border-amber-500"
                                        >
                                            Modifier
                                        </button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </Surface>
                ))
            )}

            {editing ? (
                <NotificationMessageEditor
                    key={editing.key}
                    event={editing}
                    audienceLabel={audiences[editing.audience] ?? editing.audience}
                    smsMaxSegments={smsMaxSegments}
                    onClose={() => setEditing(null)}
                />
            ) : null}
        </div>
    );
}

function JournalView({ deliveries, deliveryStats }: Pick<NotificationMessagesPanelProps, 'deliveries' | 'deliveryStats'>) {
    const table = useServerTable({
        path: '/admin/messages',
        only: ['notificationDeliveries'],
        initial: { delivery_status: '', delivery_channel: '', delivery_search: '' },
        storageKey: 'notification_deliveries',
    });
    const rows = deliveries?.data ?? [];
    const selectClass =
        'rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none';

    return (
        <div className="space-y-5">
            {deliveryStats ? (
                <div className="grid gap-4 md:grid-cols-4">
                    <MetricCard description="Push et SMS aboutis sur 24 h" tone="green" trend="24 h" value={numberFormat.format(deliveryStats.sent)}>
                        Envoyés
                    </MetricCard>
                    <MetricCard description="Refus du fournisseur ou panne sur 24 h" tone={deliveryStats.failed > 0 ? 'rose' : 'green'} trend="24 h" value={numberFormat.format(deliveryStats.failed)}>
                        Échoués
                    </MetricCard>
                    <MetricCard description="Push non configuré ou numéro absent" tone="slate" trend="24 h" value={numberFormat.format(deliveryStats.skipped)}>
                        Ignorés
                    </MetricCard>
                    <MetricCard description="SMS partis sur 24 h" tone="amber" trend="24 h" value={numberFormat.format(deliveryStats.sms_sent)}>
                        SMS envoyés
                    </MetricCard>
                </div>
            ) : null}

            <Surface className="rounded-[32px] p-5 lg:p-6">
                <form onSubmit={table.apply} className="grid gap-3 md:grid-cols-4">
                    <input
                        type="search"
                        value={table.filters.delivery_search}
                        onChange={(e) => table.set('delivery_search', e.target.value)}
                        placeholder="Message, nom ou téléphone..."
                        aria-label="Rechercher dans le journal"
                        className={selectClass}
                    />
                    <select value={table.filters.delivery_status} onChange={(e) => table.applyWith('delivery_status', e.target.value)} aria-label="Statut" className={selectClass}>
                        <option value="">Tous les statuts</option>
                        <option value="envoye">Envoyé</option>
                        <option value="echoue">Échoué</option>
                        <option value="ignore">Ignoré</option>
                    </select>
                    <select value={table.filters.delivery_channel} onChange={(e) => table.applyWith('delivery_channel', e.target.value)} aria-label="Canal" className={selectClass}>
                        <option value="">Tous les canaux</option>
                        <option value="push">Push</option>
                        <option value="sms">SMS</option>
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
                            <th>Date</th>
                            <th>Message</th>
                            <th>Destinataire</th>
                            <th>Canal</th>
                            <th>Statut</th>
                            <th>Motif</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 ? (
                            <tr>
                                <td colSpan={6}>
                                    <EmptyState description="Aucun envoi push ou SMS ne correspond à ces critères." title="Journal vide" />
                                </td>
                            </tr>
                        ) : (
                            rows.map((row) => (
                                <tr key={row.id}>
                                    <td className="text-xs text-[var(--admin-muted)]">{row.created_at ? dateTimeShort(row.created_at) : '—'}</td>
                                    <td className="text-xs">{row.event_label ?? '—'}</td>
                                    <td className="text-xs">
                                        {row.user ? (
                                            <span className="flex items-center gap-2">
                                                {row.user.name} <RoleBadge role={row.user.role} />
                                            </span>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td className="text-xs">
                                        {row.channel === 'sms' ? 'SMS' : 'Push'}
                                        {row.provider ? <span className="text-[var(--admin-muted)]"> · {row.provider}</span> : null}
                                    </td>
                                    <td>
                                        <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${DELIVERY_STATUS_TONE[row.status]}`}>{row.status_label}</span>
                                    </td>
                                    <td className="break-all text-xs text-[var(--admin-text-soft)]">{row.reason ?? '—'}</td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </DataTable>
                {renderPagination(deliveries?.links, ['notificationDeliveries'])}
            </Surface>
        </div>
    );
}

export function NotificationMessagesPanel(props: NotificationMessagesPanelProps) {
    const [tab, setTab] = useState<SubTab>('modeles');
    const tabs: Array<[SubTab, string]> = [
        ['modeles', 'Modèles de messages'],
        ['journal', 'Journal des envois'],
    ];

    return (
        <section className="mt-5 space-y-5">
            <div className="flex gap-2 border-b border-[var(--admin-border)] pb-4" role="tablist">
                {tabs.map(([id, label]) => (
                    <button
                        key={id}
                        type="button"
                        role="tab"
                        aria-selected={tab === id}
                        onClick={() => setTab(id)}
                        className={`rounded-xl px-4 py-2 text-sm font-semibold transition ${tab === id ? 'bg-[#ebb95e] text-[#1d1a14]' : 'text-[var(--admin-muted)] hover:text-[var(--admin-text)]'}`}
                    >
                        {label}
                    </button>
                ))}
            </div>

            {tab === 'modeles' ? (
                <TemplatesView events={props.events} domains={props.domains} audiences={props.audiences} smsMaxSegments={props.smsMaxSegments} />
            ) : (
                <JournalView deliveries={props.deliveries} deliveryStats={props.deliveryStats} />
            )}
        </section>
    );
}
