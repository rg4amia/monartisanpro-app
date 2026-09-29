// Onglet « Campagnes push & SMS » du backoffice (Chantier 14, lot D) :
// envois ponctuels vers un public ciblé, du brouillon à l'envoi par lots.

import { router } from '@inertiajs/react';
import { useState } from 'react';

import { useServerTable } from '../hooks/useServerTable';
import { DataTable, dateTimeShort, EmptyState, numberFormat, renderPagination, Surface, useConfirm, useDismissOnEscape } from '../shared';
import type { NotificationCampaignItem, NotificationCampaignOptions, NotificationCampaignStatus, Paginated } from '../shared';
import { NotificationCampaignEditor } from './NotificationCampaignEditor';

interface NotificationCampaignsPanelProps {
    campaigns: Paginated<NotificationCampaignItem> | null | undefined;
    options: NotificationCampaignOptions;
}

const STATUS_TONE: Record<NotificationCampaignStatus, string> = {
    brouillon: 'bg-slate-500/15 text-[var(--admin-muted)]',
    programmee: 'bg-sky-500/15 text-sky-600',
    en_cours: 'bg-amber-500/15 text-amber-600',
    envoyee: 'bg-emerald-500/15 text-emerald-600',
    annulee: 'bg-rose-500/15 text-rose-600',
};

function audience(campaign: NotificationCampaignItem, options: NotificationCampaignOptions): string {
    const parts = [
        campaign.target.roles.map((role) => options.roles[role] ?? role).join(', '),
        campaign.target.kyc_statuses.length > 0 ? campaign.target.kyc_statuses.map((status) => options.kyc_statuses[status] ?? status).join(', ') : '',
        campaign.target.commune_ids.length > 0 ? `${campaign.target.commune_ids.length} commune(s)` : '',
        campaign.target.user_ids.length > 0 ? `${campaign.target.user_ids.length} utilisateur(s) désigné(s)` : '',
    ];
    return parts.filter(Boolean).join(' · ') || '—';
}

function ScheduleDialog({ campaign, onClose }: { campaign: NotificationCampaignItem; onClose: () => void }) {
    useDismissOnEscape(onClose);
    const [mode, setMode] = useState<'now' | 'later'>('now');
    const [at, setAt] = useState('');
    const [error, setError] = useState<string | null>(null);
    const estimate = campaign.estimate;

    const submit = () => {
        setError(null);
        router.post(
            `/admin/campagnes-notifications/${campaign.id}/programmer`,
            { scheduled_at: mode === 'later' && at ? at : null },
            {
                preserveScroll: true,
                onSuccess: () => onClose(),
                onError: (errors: Record<string, string>) => setError(Object.values(errors)[0] ?? 'Programmation impossible.'),
            },
        );
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" role="presentation" onClick={onClose}>
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="schedule-title"
                className="admin-panel admin-surface w-full max-w-md rounded-[28px] border p-6 shadow-2xl"
                onClick={(e) => e.stopPropagation()}
            >
                <h2 id="schedule-title" className="text-lg font-bold text-[var(--admin-text)]">
                    Envoyer « {campaign.name} » ?
                </h2>
                {estimate ? (
                    <ul className="mt-3 space-y-1 text-sm text-[var(--admin-text)]" data-testid="schedule-estimate">
                        <li>
                            <strong>{numberFormat.format(estimate.recipients)}</strong> destinataire(s)
                        </li>
                        {campaign.channels.sms ? (
                            <li>
                                <strong>{numberFormat.format(estimate.sms_messages)}</strong> SMS ({numberFormat.format(estimate.sms_recipients)} numéro(s) ×{' '}
                                {estimate.sms_segments} SMS par message)
                            </li>
                        ) : null}
                        {estimate.over_limit ? (
                            <li className="font-semibold text-rose-500">Au-delà du plafond de {numberFormat.format(estimate.max_recipients)} destinataires : l'envoi sera refusé.</li>
                        ) : null}
                    </ul>
                ) : null}
                <fieldset className="mt-4 space-y-2 text-sm text-[var(--admin-text)]">
                    <label className="flex items-center gap-2">
                        <input type="radio" name="when" checked={mode === 'now'} onChange={() => setMode('now')} />
                        Maintenant (départ dans la minute)
                    </label>
                    <label className="flex items-center gap-2">
                        <input type="radio" name="when" checked={mode === 'later'} onChange={() => setMode('later')} />
                        À une date précise
                    </label>
                    {mode === 'later' ? (
                        <input
                            type="datetime-local"
                            aria-label="Date d'envoi"
                            value={at}
                            onChange={(e) => setAt(e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-2 text-sm"
                        />
                    ) : null}
                </fieldset>
                {error ? <p className="mt-3 text-xs font-semibold text-rose-500">{error}</p> : null}
                <div className="mt-5 flex justify-end gap-2">
                    <button type="button" onClick={onClose} className="rounded-xl px-4 py-2 text-sm font-semibold text-[var(--admin-muted)]">
                        Annuler
                    </button>
                    <button
                        type="button"
                        onClick={submit}
                        disabled={mode === 'later' && at === ''}
                        className="rounded-xl bg-[#ebb95e] px-4 py-2 text-sm font-bold text-[#1d1a14] disabled:opacity-50"
                    >
                        {mode === 'now' ? 'Envoyer' : 'Programmer'}
                    </button>
                </div>
            </div>
        </div>
    );
}

export function NotificationCampaignsPanel({ campaigns, options }: NotificationCampaignsPanelProps) {
    const { confirm, dialog } = useConfirm();
    const [editing, setEditing] = useState<NotificationCampaignItem | 'new' | null>(null);
    const [scheduling, setScheduling] = useState<NotificationCampaignItem | null>(null);
    const table = useServerTable({
        path: '/admin/campagnes-notifications',
        only: ['notificationCampaigns'],
        initial: { campaign_status: '', campaign_search: '' },
        storageKey: 'notification_campaigns',
    });
    const rows = campaigns?.data ?? [];
    const selectClass =
        'rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none';
    const actionClass = 'rounded-lg border border-[var(--admin-border)] px-2 py-1 text-[11px] font-semibold text-[var(--admin-text)] hover:border-amber-500';

    const cancel = async (campaign: NotificationCampaignItem) => {
        const ok = await confirm({
            title: 'Annuler la campagne ?',
            message:
                campaign.status === 'en_cours'
                    ? `L'envoi de « ${campaign.name} » s'arrête : les ${numberFormat.format(campaign.counts.served)} destinataire(s) déjà servi(s) le restent.`
                    : `« ${campaign.name} » ne partira pas.`,
            confirmLabel: 'Annuler la campagne',
            tone: 'danger',
        });
        if (ok) router.post(`/admin/campagnes-notifications/${campaign.id}/annuler`, {}, { preserveScroll: true });
    };

    const remove = async (campaign: NotificationCampaignItem) => {
        const ok = await confirm({
            title: 'Supprimer la campagne ?',
            message: `« ${campaign.name} » sera définitivement supprimée.`,
            confirmLabel: 'Supprimer',
            tone: 'danger',
        });
        if (ok) router.delete(`/admin/campagnes-notifications/${campaign.id}`, { preserveScroll: true });
    };

    const sendTest = (campaign: NotificationCampaignItem) => {
        const channels = (['push', 'sms'] as const).filter((channel) => campaign.channels[channel]);
        router.post(`/admin/campagnes-notifications/${campaign.id}/test`, { channels: channels.length > 0 ? channels : ['push'] }, { preserveScroll: true });
    };

    return (
        <section className="mt-5 space-y-5">
            <Surface className="rounded-[32px] p-5 lg:p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <form onSubmit={table.apply} className="flex flex-1 flex-wrap gap-3">
                        <input
                            type="search"
                            value={table.filters.campaign_search}
                            onChange={(e) => table.set('campaign_search', e.target.value)}
                            placeholder="Nom ou titre..."
                            aria-label="Rechercher une campagne"
                            className={selectClass}
                        />
                        <select value={table.filters.campaign_status} onChange={(e) => table.applyWith('campaign_status', e.target.value)} aria-label="Statut" className={selectClass}>
                            <option value="">Tous les statuts</option>
                            {Object.entries(options.statuses).map(([key, label]) => (
                                <option key={key} value={key}>
                                    {label}
                                </option>
                            ))}
                        </select>
                        <button type="submit" className="rounded-xl px-3 py-2 text-xs font-semibold text-[var(--admin-muted)]">
                            Filtrer
                        </button>
                    </form>
                    <button type="button" onClick={() => setEditing('new')} className="rounded-xl bg-[#ebb95e] px-4 py-2 text-sm font-bold text-[#1d1a14]">
                        Nouvelle campagne
                    </button>
                </div>
                <p className="mt-3 text-xs text-[var(--admin-muted)]">
                    Envoi par lots chaque minute, au plus {numberFormat.format(options.max_recipients)} destinataires par campagne. Les administrateurs et les comptes
                    suspendus ou anonymisés ne sont jamais visés.
                </p>

                <DataTable className="mt-5">
                    <thead>
                        <tr>
                            <th>Campagne</th>
                            <th>Public</th>
                            <th>Statut</th>
                            <th>Destinataires</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 ? (
                            <tr>
                                <td colSpan={5}>
                                    <EmptyState description="Créez une campagne pour informer un public ciblé par push ou SMS." title="Aucune campagne" />
                                </td>
                            </tr>
                        ) : (
                            rows.map((campaign) => (
                                <tr key={campaign.id}>
                                    <td className="text-xs">
                                        <p className="font-semibold text-[var(--admin-text)]">{campaign.name}</p>
                                        <p className="text-[var(--admin-muted)]">
                                            {campaign.nature_label} ·{' '}
                                            {[campaign.channels.in_app && 'App', campaign.channels.push && 'Push', campaign.channels.sms && 'SMS'].filter(Boolean).join(', ')}
                                        </p>
                                    </td>
                                    <td className="text-xs">{audience(campaign, options)}</td>
                                    <td className="text-xs">
                                        <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${STATUS_TONE[campaign.status]}`}>{campaign.status_label}</span>
                                        {campaign.scheduled_at && campaign.status === 'programmee' ? (
                                            <p className="mt-1 text-[var(--admin-muted)]">pour le {dateTimeShort(campaign.scheduled_at)}</p>
                                        ) : null}
                                        {campaign.finished_at ? <p className="mt-1 text-[var(--admin-muted)]">le {dateTimeShort(campaign.finished_at)}</p> : null}
                                    </td>
                                    <td className="text-xs">
                                        {campaign.estimate ? (
                                            <span>
                                                {numberFormat.format(campaign.estimate.recipients)} prévu(s)
                                                {campaign.channels.sms ? ` · ${numberFormat.format(campaign.estimate.sms_messages)} SMS` : ''}
                                            </span>
                                        ) : (
                                            <span>
                                                {numberFormat.format(campaign.counts.served)} servi(s) · {numberFormat.format(campaign.counts.sent)} envoi(s) réussi(s)
                                                {campaign.counts.failed > 0 ? <span className="text-rose-500"> · {numberFormat.format(campaign.counts.failed)} échec(s)</span> : null}
                                            </span>
                                        )}
                                    </td>
                                    <td>
                                        <div className="flex flex-wrap gap-1">
                                            {campaign.editable ? (
                                                <>
                                                    <button type="button" className={actionClass} onClick={() => setEditing(campaign)} aria-label={`Modifier ${campaign.name}`}>
                                                        Modifier
                                                    </button>
                                                    <button type="button" className={actionClass} onClick={() => sendTest(campaign)} aria-label={`Tester ${campaign.name}`}>
                                                        Me l'envoyer
                                                    </button>
                                                    <button type="button" className={actionClass} onClick={() => setScheduling(campaign)} aria-label={`Envoyer ${campaign.name}`}>
                                                        {campaign.status === 'programmee' ? 'Reprogrammer' : 'Envoyer…'}
                                                    </button>
                                                </>
                                            ) : null}
                                            <button
                                                type="button"
                                                className={actionClass}
                                                onClick={() => router.post(`/admin/campagnes-notifications/${campaign.id}/dupliquer`, {}, { preserveScroll: true })}
                                                aria-label={`Dupliquer ${campaign.name}`}
                                            >
                                                Dupliquer
                                            </button>
                                            {campaign.cancellable ? (
                                                <button type="button" className={`${actionClass} text-rose-500`} onClick={() => cancel(campaign)} aria-label={`Annuler ${campaign.name}`}>
                                                    Annuler
                                                </button>
                                            ) : null}
                                            {campaign.deletable ? (
                                                <button type="button" className={`${actionClass} text-rose-500`} onClick={() => remove(campaign)} aria-label={`Supprimer ${campaign.name}`}>
                                                    Supprimer
                                                </button>
                                            ) : null}
                                        </div>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </DataTable>
                {renderPagination(campaigns?.links, ['notificationCampaigns'])}
            </Surface>

            {editing ? (
                <NotificationCampaignEditor
                    key={editing === 'new' ? 'new' : editing.id}
                    campaign={editing === 'new' ? null : editing}
                    options={options}
                    onClose={() => setEditing(null)}
                />
            ) : null}
            {scheduling ? <ScheduleDialog campaign={scheduling} onClose={() => setScheduling(null)} /> : null}
            {dialog}
        </section>
    );
}
