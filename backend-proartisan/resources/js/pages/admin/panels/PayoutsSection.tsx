// Sous-onglet « Versements & retraits livreurs » de l'onglet Transactions (Chantier 10).
// 1. Virements Mobile Money non aboutis (artisans, livreurs) : historique complet,
//    relance, versement manuel hors plateforme sur référence.
// 2. Demandes de retrait des gains livreur : approbation, versement, rejet motivé.

import { router } from '@inertiajs/react';
import { Fragment, useState } from 'react';

import { DataTable, EmptyState, MetricCard, money, SectionTitle, Surface, useConfirm } from '../shared';

export interface PayoutEvent {
    id: number;
    action: string;
    action_label: string;
    statut_avant: string | null;
    statut_apres: string | null;
    message: string | null;
    actor: string | null;
    created_at: string | null;
}

export interface PayoutItem {
    id: number;
    reference: string;
    context: string;
    context_label: string;
    montant: number;
    montant_transfere: number;
    provider: string;
    phone: string | null;
    statut: 'en_cours' | 'echoue' | 'verse' | 'annule';
    statut_label: string;
    attempts: number;
    last_error: string | null;
    next_retry_at: string | null;
    paid_at: string | null;
    external_reference: string | null;
    mission_id: number | null;
    description: string | null;
    beneficiary: { id: number; name: string | null; phone: string | null; role: string } | null;
    /** Remboursement client : artisan dont le séquestre est prélevé au virement réussi. */
    debited_from?: { id: number; name: string | null } | null;
    can_retry: boolean;
    created_at: string | null;
    events?: PayoutEvent[];
}

export interface PayoutsOverview {
    pending: PayoutItem[];
    recent: PayoutItem[];
    stats: { failed_count: number; failed_amount: number; exhausted_count: number; paid_today: number };
}

export interface DriverCashoutItem {
    id: number;
    reference: string;
    montant_brut: number;
    montant_commission: number;
    montant_net: number;
    statut: 'en_attente' | 'approuve' | 'complete' | 'rejete';
    statut_label: string;
    mode_retrait: string;
    beneficiary_name: string;
    beneficiary_phone: string;
    notes: string | null;
    payout: PayoutItem | null;
    created_at: string | null;
    driver: { id: number; name: string | null; phone: string | null } | null;
    available_balance: number;
}

export interface DriverCashoutsOverview {
    pending: DriverCashoutItem[];
    recent: DriverCashoutItem[];
    commission_rate: number;
}

interface PayoutsSectionProps {
    payoutsOverview?: PayoutsOverview | null;
    driverCashoutsOverview?: DriverCashoutsOverview | null;
}

const modeLabels: Record<string, string> = {
    wave: 'Wave',
    orange_money: 'Orange Money',
    virement_bancaire: 'Virement bancaire',
};

const dateTime = (value: string | null) =>
    value
        ? new Date(value).toLocaleString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
        : '—';

function PayoutStatusBadge({ statut, label }: { statut: string; label: string }) {
    const tone: Record<string, string> = {
        echoue: 'bg-rose-50 text-rose-700 border-rose-200',
        en_cours: 'bg-amber-50 text-amber-700 border-amber-200',
        verse: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        annule: 'bg-slate-100 text-slate-600 border-slate-200',
        en_attente: 'bg-amber-50 text-amber-700 border-amber-200',
        approuve: 'bg-blue-50 text-blue-700 border-blue-200',
        complete: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        rejete: 'bg-rose-50 text-rose-700 border-rose-200',
    };

    return (
        <span className={`inline-flex rounded-full border px-2.5 py-0.5 text-[11px] font-bold ${tone[statut] ?? tone.annule}`}>
            {label}
        </span>
    );
}

export function PayoutHistory({ events }: { events: PayoutEvent[] }) {
    if (events.length === 0) {
        return <p className="text-xs text-[var(--admin-muted)]">Aucune action enregistrée.</p>;
    }

    return (
        <ol className="space-y-2" aria-label="Historique du versement">
            {events.map((event) => (
                <li key={event.id} className="flex gap-3 text-xs">
                    <span className="w-28 shrink-0 text-[var(--admin-muted)]">{dateTime(event.created_at)}</span>
                    <span className="font-semibold text-[var(--admin-text)]">{event.action_label}</span>
                    {event.actor ? <span className="text-[var(--admin-muted)]">par {event.actor}</span> : null}
                    {event.message ? <span className="text-rose-700">— {event.message}</span> : null}
                </li>
            ))}
        </ol>
    );
}

export function PayoutsSection({ payoutsOverview, driverCashoutsOverview }: PayoutsSectionProps) {
    const { confirm: askConfirm, dialog: confirmDialog } = useConfirm();
    const [expanded, setExpanded] = useState<number | null>(null);

    const pending = payoutsOverview?.pending ?? [];
    const stats = payoutsOverview?.stats;
    const cashouts = driverCashoutsOverview?.pending ?? [];
    const recentCashouts = driverCashoutsOverview?.recent ?? [];

    const retry = async (payout: PayoutItem) => {
        const ok = await askConfirm({
            title: `Relancer le virement ${payout.reference}`,
            message: `${money(payout.montant_transfere)} vers ${payout.phone ?? 'le numéro du bénéficiaire'} (${modeLabels[payout.provider] ?? payout.provider}).`,
            confirmLabel: 'Relancer',
        });
        if (ok) {
            router.post(`/admin/payouts/${payout.id}/retry`, {}, { preserveScroll: true });
        }
    };

    const markPaid = async (payout: PayoutItem) => {
        const reference = await askConfirm({
            title: `Solder manuellement ${payout.reference}`,
            message: `À n'utiliser que si ${money(payout.montant_transfere)} ont été versés hors plateforme (virement, espèces). Le portefeuille du bénéficiaire sera débité.`,
            promptLabel: 'Référence du versement (obligatoire)',
            promptMinLength: 3,
            confirmLabel: 'Solder le versement',
            tone: 'danger',
        });
        if (reference && typeof reference === 'string') {
            router.post(`/admin/payouts/${payout.id}/mark-paid`, { external_reference: reference.trim() }, { preserveScroll: true });
        }
    };

    const payCashout = async (cashout: DriverCashoutItem) => {
        if (cashout.mode_retrait === 'virement_bancaire') {
            const reference = await askConfirm({
                title: `Verser le retrait ${cashout.reference}`,
                message: `Virement bancaire de ${money(cashout.montant_net)} à ${cashout.beneficiary_name}.`,
                promptLabel: 'Référence du virement bancaire',
                promptMinLength: 3,
                confirmLabel: 'Confirmer le virement',
            });
            if (reference && typeof reference === 'string') {
                router.post(`/admin/driver-cashouts/${cashout.id}/pay`, { external_reference: reference.trim() }, { preserveScroll: true });
            }
            return;
        }

        const ok = await askConfirm({
            title: `Verser le retrait ${cashout.reference}`,
            message: `${money(cashout.montant_net)} seront envoyés sur ${modeLabels[cashout.mode_retrait] ?? cashout.mode_retrait} au ${cashout.beneficiary_phone}.`,
            confirmLabel: 'Verser maintenant',
        });
        if (ok) {
            router.post(`/admin/driver-cashouts/${cashout.id}/pay`, {}, { preserveScroll: true });
        }
    };

    const rejectCashout = async (cashout: DriverCashoutItem) => {
        const reason = await askConfirm({
            title: `Rejeter le retrait ${cashout.reference}`,
            message: 'Le montant redeviendra disponible sur le portefeuille du livreur.',
            promptLabel: 'Motif du rejet',
            promptMinLength: 5,
            confirmLabel: 'Rejeter',
            tone: 'danger',
        });
        if (reason && typeof reason === 'string') {
            router.post(`/admin/driver-cashouts/${cashout.id}/reject`, { reason: reason.trim() }, { preserveScroll: true });
        }
    };

    return (
        <div className="space-y-6">
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <MetricCard description="Virements Mobile Money à relancer" tone={(stats?.failed_count ?? 0) > 0 ? 'rose' : 'green'} trend="Versements échoués" value={String(stats?.failed_count ?? 0)}>
                    Virements échoués
                </MetricCard>
                <MetricCard description="Montant dû, resté sur les portefeuilles" tone="amber" trend="À verser" value={money(stats?.failed_amount ?? 0)}>
                    Montant en attente
                </MetricCard>
                <MetricCard description="Relances automatiques épuisées : action humaine requise" tone={(stats?.exhausted_count ?? 0) > 0 ? 'rose' : 'green'} trend="Relances épuisées" value={String(stats?.exhausted_count ?? 0)}>
                    À traiter manuellement
                </MetricCard>
                <MetricCard description="Versements aboutis depuis minuit" tone="green" trend="Aujourd'hui" value={money(stats?.paid_today ?? 0)}>
                    Versé aujourd'hui
                </MetricCard>
            </div>

            <Surface className="rounded-[32px] p-5 lg:p-6">
                <SectionTitle
                    title="Virements Mobile Money non aboutis"
                    description="Un virement échoué ne débite pas le portefeuille du bénéficiaire : il est relancé automatiquement (15 min, 30 min, 1 h, 2 h), puis attend une action humaine."
                />
                <div className="mt-5">
                    {pending.length === 0 ? (
                        <EmptyState title="Aucun virement en attente" description="Tous les versements Mobile Money ont abouti." />
                    ) : (
                        <DataTable>
                            <thead>
                                <tr>
                                    <th>Référence</th>
                                    <th>Bénéficiaire</th>
                                    <th>Objet</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                    <th>Tentatives</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {pending.map((payout) => (
                                    <Fragment key={payout.id}>
                                        <tr>
                                            <td className="text-xs font-bold text-[var(--admin-text)]">{payout.reference}</td>
                                            <td>
                                                <div className="text-sm font-semibold text-[var(--admin-text)]">{payout.beneficiary?.name ?? '—'}</div>
                                                <div className="text-xs text-[var(--admin-muted)]">{payout.phone ?? 'Aucun numéro'}</div>
                                            </td>
                                            <td className="text-xs text-[var(--admin-text-soft)]">
                                                {payout.context_label}
                                                {payout.mission_id ? ` · mission #${payout.mission_id}` : ''}
                                                {payout.debited_from && (
                                                    <div className="text-[var(--admin-muted)]">Prélevé chez {payout.debited_from.name ?? `l'utilisateur #${payout.debited_from.id}`}</div>
                                                )}
                                            </td>
                                            <td className="text-sm font-bold text-[var(--admin-text)]">{money(payout.montant_transfere)}</td>
                                            <td>
                                                <PayoutStatusBadge statut={payout.statut} label={payout.statut_label} />
                                                {payout.last_error ? <div className="mt-1 max-w-xs text-[11px] text-rose-700">{payout.last_error}</div> : null}
                                            </td>
                                            <td className="text-xs text-[var(--admin-muted)]">
                                                {payout.attempts}
                                                <div>{payout.next_retry_at ? `Relance ${dateTime(payout.next_retry_at)}` : 'Relances épuisées'}</div>
                                            </td>
                                            <td>
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    {payout.can_retry ? (
                                                        <button type="button" onClick={() => retry(payout)} className="rounded-lg bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700 transition hover:bg-blue-100">
                                                            Relancer
                                                        </button>
                                                    ) : null}
                                                    <button type="button" onClick={() => markPaid(payout)} className="rounded-lg bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-800 transition hover:bg-amber-100">
                                                        Versé hors plateforme
                                                    </button>
                                                    <button
                                                        type="button"
                                                        aria-expanded={expanded === payout.id}
                                                        onClick={() => setExpanded(expanded === payout.id ? null : payout.id)}
                                                        className="rounded-lg bg-[var(--admin-panel-strong)] px-2 py-1 text-xs font-semibold text-[var(--admin-text-soft)] transition hover:text-[var(--admin-text)]"
                                                    >
                                                        {expanded === payout.id ? 'Masquer l\'historique' : 'Historique'}
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        {expanded === payout.id ? (
                                            <tr>
                                                <td colSpan={7} className="bg-[var(--admin-panel-strong)]/40">
                                                    <PayoutHistory events={payout.events ?? []} />
                                                </td>
                                            </tr>
                                        ) : null}
                                    </Fragment>
                                ))}
                            </tbody>
                        </DataTable>
                    )}
                </div>
            </Surface>

            <Surface className="rounded-[32px] p-5 lg:p-6">
                <SectionTitle
                    title="Retraits des gains livreur"
                    description={`Demandes des livreurs, sur le modèle du cash-out quincaillerie. Frais de retrait actuels : ${((driverCashoutsOverview?.commission_rate ?? 0) * 100).toLocaleString('fr-FR')} %.`}
                />
                <div className="mt-5">
                    {cashouts.length === 0 ? (
                        <EmptyState title="Aucune demande de retrait en attente" description="Les nouvelles demandes des livreurs apparaîtront ici." />
                    ) : (
                        <DataTable>
                            <thead>
                                <tr>
                                    <th>Référence</th>
                                    <th>Livreur</th>
                                    <th>Mode</th>
                                    <th>Montant net</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {cashouts.map((cashout) => (
                                    <tr key={cashout.id}>
                                        <td className="text-xs font-bold text-[var(--admin-text)]">{cashout.reference}</td>
                                        <td>
                                            <div className="text-sm font-semibold text-[var(--admin-text)]">{cashout.driver?.name ?? cashout.beneficiary_name}</div>
                                            <div className="text-xs text-[var(--admin-muted)]">{cashout.beneficiary_phone}</div>
                                        </td>
                                        <td className="text-xs text-[var(--admin-text-soft)]">{modeLabels[cashout.mode_retrait] ?? cashout.mode_retrait}</td>
                                        <td className="text-sm font-bold text-emerald-700">
                                            {money(cashout.montant_net)}
                                            {cashout.montant_commission > 0 ? <div className="text-[11px] font-normal text-[var(--admin-muted)]">frais {money(cashout.montant_commission)}</div> : null}
                                        </td>
                                        <td>
                                            <PayoutStatusBadge statut={cashout.statut} label={cashout.statut_label} />
                                            {cashout.payout && cashout.payout.statut === 'echoue' ? (
                                                <div className="mt-1 text-[11px] text-rose-700">Virement échoué — voir la liste des virements</div>
                                            ) : null}
                                        </td>
                                        <td>
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                {cashout.statut === 'en_attente' ? (
                                                    <button type="button" onClick={() => router.post(`/admin/driver-cashouts/${cashout.id}/approve`, {}, { preserveScroll: true })} className="rounded-lg bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700 transition hover:bg-blue-100">
                                                        Approuver
                                                    </button>
                                                ) : null}
                                                {!cashout.payout ? (
                                                    <button type="button" onClick={() => payCashout(cashout)} className="rounded-lg bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100">
                                                        Verser
                                                    </button>
                                                ) : null}
                                                {!cashout.payout || cashout.payout.statut !== 'verse' ? (
                                                    <button type="button" onClick={() => rejectCashout(cashout)} className="rounded-lg bg-red-50 px-2 py-1 text-xs font-semibold text-red-700 transition hover:bg-red-100">
                                                        Rejeter
                                                    </button>
                                                ) : null}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    )}
                </div>

                {recentCashouts.length > 0 ? (
                    <div className="mt-6">
                        <h4 className="text-xs font-bold uppercase tracking-wide text-[var(--admin-muted)]">Derniers retraits traités</h4>
                        <ul className="mt-2 divide-y divide-[var(--admin-border)]">
                            {recentCashouts.map((cashout) => (
                                <li key={cashout.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-xs">
                                    <span className="font-semibold text-[var(--admin-text)]">{cashout.reference} · {cashout.driver?.name ?? cashout.beneficiary_name}</span>
                                    <span className="flex items-center gap-2">
                                        {money(cashout.montant_net)}
                                        <PayoutStatusBadge statut={cashout.statut} label={cashout.statut_label} />
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                ) : null}
            </Surface>

            {confirmDialog}
        </div>
    );
}
