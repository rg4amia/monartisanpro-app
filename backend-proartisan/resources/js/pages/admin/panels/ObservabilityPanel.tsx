// Onglet « Santé & Observabilité » du backoffice (Chantier C7 / P2-12).

import {
    DataTable,
    dateTimeShort,
    EmptyState,
    MetricCard,
    money,
    numberFormat,
    SectionTitle,
    Surface,
} from '../shared';
import type { ObservabilitySnapshot } from '../shared';

interface ObservabilityPanelProps {
    snapshot: ObservabilitySnapshot;
    canManage: boolean;
    actionLoading: boolean;
    onRetryJobs: () => void;
    onFlushJobs: () => void;
}

export function ObservabilityPanel({
    snapshot,
    canManage,
    actionLoading,
    onRetryJobs,
    onFlushJobs,
}: ObservabilityPanelProps) {
    const { queue, payments, fraud, referent } = snapshot;

    return (
        <section className="mt-5 space-y-6">
            <div className="grid gap-4 xl:grid-cols-4">
                <MetricCard
                    description="Jobs en file d'attente ayant échoué"
                    tone={queue.failed > 0 ? 'rose' : 'green'}
                    trend={`${numberFormat.format(queue.pending)} en attente`}
                    value={numberFormat.format(queue.failed)}
                >
                    Jobs en échec
                </MetricCard>
                <MetricCard
                    description="Transactions échouées sur 24 h"
                    tone={payments.failed_24h > 0 ? 'rose' : 'green'}
                    trend={`${numberFormat.format(payments.failed_total)} au total`}
                    value={numberFormat.format(payments.failed_24h)}
                >
                    Paiements KO (24 h)
                </MetricCard>
                <MetricCard
                    description="Scans J-Code hors zone GPS (> 100 m) sur 7 j"
                    tone={fraud.gps_attempts_7d > 0 ? 'amber' : 'green'}
                    trend={`${numberFormat.format(fraud.unread_alerts)} alerte(s) non lue(s)`}
                    value={numberFormat.format(fraud.gps_attempts_7d)}
                >
                    Fraude GPS J-Code
                </MetricCard>
                <MetricCard
                    description={`Missions > ${money(referent.threshold)} en attente de validation physique`}
                    tone={referent.blocked > 0 ? 'amber' : 'green'}
                    trend="Visite Référent requise"
                    value={numberFormat.format(referent.blocked)}
                >
                    Bloquées seuil Référent
                </MetricCard>
            </div>

            <Surface className="rounded-[32px] p-5 lg:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <SectionTitle
                        description={`File d'attente : ${numberFormat.format(queue.pending)} job(s) en attente, plus ancien depuis ${numberFormat.format(queue.oldest_pending_minutes)} min.`}
                        title="Jobs en échec"
                    />
                    {canManage ? (
                        <div className="flex gap-2">
                            <button
                                type="button"
                                disabled={actionLoading || queue.failed === 0}
                                onClick={onRetryJobs}
                                className="rounded-xl bg-[#ebb95e] px-4 py-2 text-xs font-bold text-[#241b16] transition hover:bg-[#dca850] disabled:opacity-50"
                            >
                                Relancer tout
                            </button>
                            <button
                                type="button"
                                disabled={actionLoading || queue.failed === 0}
                                onClick={onFlushJobs}
                                className="rounded-xl border border-[var(--admin-border)] bg-white/60 px-3 py-2 text-xs font-bold text-[var(--admin-text-soft)] transition hover:bg-white/80 disabled:opacity-50"
                            >
                                Purger
                            </button>
                        </div>
                    ) : null}
                </div>

                <DataTable className="mt-5">
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Exception</th>
                            <th>Échec</th>
                        </tr>
                    </thead>
                    <tbody>
                        {queue.recent.length === 0 ? (
                            <tr>
                                <td colSpan={3}>
                                    <EmptyState description="Aucun job en échec." title="File saine" />
                                </td>
                            </tr>
                        ) : (
                            queue.recent.map((job) => (
                                <tr key={job.id}>
                                    <td className="font-mono text-xs">{job.queue}</td>
                                    <td className="text-xs text-[var(--admin-text-soft)] break-all">{job.exception}</td>
                                    <td className="text-xs text-[var(--admin-muted)]">{job.failed_at ? dateTimeShort(job.failed_at) : '—'}</td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </DataTable>
            </Surface>

            <Surface className="rounded-[32px] p-5 lg:p-6">
                <SectionTitle description="Transactions dont le webhook fournisseur a échoué ou n'est jamais arrivé." title="Webhooks de paiement KO" />
                <DataTable className="mt-5">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Provider</th>
                            <th>Type</th>
                            <th className="text-right">Montant</th>
                            <th>Erreur</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        {payments.recent.length === 0 ? (
                            <tr>
                                <td colSpan={6}>
                                    <EmptyState description="Aucune transaction en échec." title="Flux sain" />
                                </td>
                            </tr>
                        ) : (
                            payments.recent.map((tx) => (
                                <tr key={tx.id}>
                                    <td className="text-xs">{tx.id}</td>
                                    <td className="text-xs">{tx.provider}</td>
                                    <td className="text-xs">{tx.type}</td>
                                    <td className="text-right text-xs font-semibold">{money(tx.montant)}</td>
                                    <td className="text-xs text-[var(--admin-text-soft)] break-all">{tx.error ?? '—'}</td>
                                    <td className="text-xs text-[var(--admin-muted)]">{tx.created_at ? dateTimeShort(tx.created_at) : '—'}</td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </DataTable>
            </Surface>

            <div className="grid gap-6 xl:grid-cols-2">
                <Surface className="rounded-[32px] p-5 lg:p-6">
                    <SectionTitle description="Tentatives de validation J-Code hors de la zone GPS autorisée." title="Fraude GPS J-Code" />
                    <div className="mt-5 space-y-3">
                        {fraud.recent.length === 0 ? (
                            <EmptyState description="Aucune tentative récente." title="Aucun signal" />
                        ) : (
                            fraud.recent.map((entry) => (
                                <div key={entry.id} className="rounded-2xl border border-[var(--admin-border)] bg-white/60 p-4">
                                    <p className="text-sm font-semibold text-[var(--admin-text)]">{entry.user ?? 'Utilisateur inconnu'}</p>
                                    <p className="text-xs text-[var(--admin-muted)]">
                                        {entry.phone ?? '—'}{entry.mission_id ? ` • Mission #${entry.mission_id}` : ''} • {entry.created_at ? dateTimeShort(entry.created_at) : '—'}
                                    </p>
                                    <p className="mt-1 text-xs text-[var(--admin-text-soft)]">{entry.description}</p>
                                </div>
                            ))
                        )}
                    </div>
                </Surface>

                <Surface className="rounded-[32px] p-5 lg:p-6">
                    <SectionTitle description={`Missions au-delà de ${money(referent.threshold)} nécessitant une visite physique du Référent.`} title="Missions bloquées seuil Référent" />
                    <div className="mt-5 space-y-3">
                        {referent.recent.length === 0 ? (
                            <EmptyState description="Aucune mission en attente de validation Référent." title="Rien à valider" />
                        ) : (
                            referent.recent.map((mission) => (
                                <div key={mission.id} className="rounded-2xl border border-[var(--admin-border)] bg-white/60 p-4">
                                    <div className="flex items-center justify-between gap-3">
                                        <p className="text-sm font-semibold text-[var(--admin-text)]">Mission #{mission.id}</p>
                                        <span className="text-xs font-semibold text-[#b77918]">{money(mission.montant_total)}</span>
                                    </div>
                                    <p className="text-xs text-[var(--admin-muted)]">
                                        {mission.client ?? '—'} → {mission.artisan ?? '—'} • {mission.status} • {mission.created_at ? dateTimeShort(mission.created_at) : '—'}
                                    </p>
                                </div>
                            ))
                        )}
                    </div>
                </Surface>
            </div>

            <p className="text-right text-[11px] text-[var(--admin-muted)]">
                Instantané généré le {dateTimeShort(snapshot.generated_at)}
            </p>
        </section>
    );
}
