// Sous-onglet « Encaissements » de l'onglet Transactions (Chantier 11).
// 1. Courses livrées impayées : relances automatiques (push + SMS), relance manuelle.
// 2. Comptes restreints pour course impayée : levée motivée.
// 3. Virements bancaires de commandes de matériaux à confirmer.

import { router } from '@inertiajs/react';

import { DataTable, EmptyState, MetricCard, money, SectionTitle, Surface, useConfirm } from '../shared';

export interface UnpaidFareItem {
    order_id: number;
    client: { id: number; name: string | null; phone: string | null; restricted: boolean } | null;
    driver: { id: number; name: string | null } | null;
    montant: number;
    delivered_at: string | null;
    reminders_count: number;
    last_reminder_at: string | null;
    next_reminder_at: string | null;
}

export interface RestrictedClientItem {
    id: number;
    name: string | null;
    phone: string | null;
    restricted_at: string | null;
    reason: string | null;
}

export interface PendingBankTransferItem {
    transaction_id: number;
    reference: string | null;
    montant: number;
    client: { id: number; name: string | null; phone: string | null } | null;
    order_ids: number[];
    created_at: string | null;
}

export interface CollectionsOverview {
    unpaid: UnpaidFareItem[];
    restricted: RestrictedClientItem[];
    pending_bank_transfers: PendingBankTransferItem[];
    settings: { interval_hours: number; max_reminders: number };
    stats: { unpaid_count: number; unpaid_amount: number; restricted_count: number };
}

const dateTime = (value: string | null) =>
    value
        ? new Date(value).toLocaleString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
        : '—';

export function CollectionsSection({ collectionsOverview }: { collectionsOverview?: CollectionsOverview | null }) {
    const { confirm: askConfirm, dialog: confirmDialog } = useConfirm();

    const unpaid = collectionsOverview?.unpaid ?? [];
    const restricted = collectionsOverview?.restricted ?? [];
    const transfers = collectionsOverview?.pending_bank_transfers ?? [];
    const stats = collectionsOverview?.stats;
    const maxReminders = collectionsOverview?.settings.max_reminders ?? 5;
    const intervalHours = collectionsOverview?.settings.interval_hours ?? 24;

    const remind = async (fare: UnpaidFareItem) => {
        const ok = await askConfirm({
            title: `Relancer le client — commande #${fare.order_id}`,
            message: `${fare.client?.name ?? 'Le client'} recevra une notification et un SMS pour régler ${money(fare.montant)}.`,
            confirmLabel: 'Relancer',
        });
        if (ok) {
            router.post(`/admin/collections/orders/${fare.order_id}/remind`, {}, { preserveScroll: true });
        }
    };

    const lift = async (client: RestrictedClientItem) => {
        const reason = await askConfirm({
            title: `Lever la restriction de ${client.name ?? `l'utilisateur #${client.id}`}`,
            message: 'Le client pourra de nouveau commander et publier des missions, même si une course reste due.',
            promptLabel: 'Motif de la levée',
            promptMinLength: 5,
            confirmLabel: 'Lever la restriction',
            tone: 'danger',
        });
        if (reason && typeof reason === 'string') {
            router.post(`/admin/collections/users/${client.id}/lift-restriction`, { reason: reason.trim() }, { preserveScroll: true });
        }
    };

    const confirmTransfer = async (transfer: PendingBankTransferItem) => {
        const reference = await askConfirm({
            title: `Confirmer le virement de ${money(transfer.montant)}`,
            message: `À n'utiliser qu'après réception effective sur le compte ProsArtisan. La commande #${transfer.order_ids.join(', #')} sera transmise à la quincaillerie.`,
            promptLabel: 'Référence du virement reçu',
            promptMinLength: 3,
            confirmLabel: 'Confirmer la réception',
        });
        if (reference && typeof reference === 'string') {
            router.post(
                `/admin/collections/transactions/${transfer.transaction_id}/confirm-bank-transfer`,
                { bank_reference: reference.trim() },
                { preserveScroll: true },
            );
        }
    };

    return (
        <div className="space-y-6">
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <MetricCard description="Courses livrées que le client n'a pas réglées" tone={(stats?.unpaid_count ?? 0) > 0 ? 'rose' : 'green'} trend="Courses impayées" value={String(stats?.unpaid_count ?? 0)}>
                    Courses à encaisser
                </MetricCard>
                <MetricCard description="Dû aux livreurs, en attente du client" tone="amber" trend="Montant" value={money(stats?.unpaid_amount ?? 0)}>
                    Montant impayé
                </MetricCard>
                <MetricCard description="Commandes et missions bloquées jusqu'au paiement" tone={(stats?.restricted_count ?? 0) > 0 ? 'rose' : 'green'} trend="Comptes restreints" value={String(stats?.restricted_count ?? 0)}>
                    Clients restreints
                </MetricCard>
                <MetricCard description="Virements de commandes annoncés, à confirmer" tone={transfers.length > 0 ? 'amber' : 'green'} trend="Virements" value={String(transfers.length)}>
                    Virements à confirmer
                </MetricCard>
            </div>

            <Surface className="rounded-[32px] p-5 lg:p-6">
                <SectionTitle
                    title="Courses livrées impayées"
                    description={`Le client est relancé toutes les ${intervalHours} h (notification et SMS). Au-delà de ${maxReminders} relances, il est présumé avoir payé hors plateforme et son compte est restreint.`}
                />
                <div className="mt-5">
                    {unpaid.length === 0 ? (
                        <EmptyState title="Aucune course impayée" description="Toutes les courses livrées ont été réglées par les clients." />
                    ) : (
                        <DataTable>
                            <thead>
                                <tr>
                                    <th>Commande</th>
                                    <th>Client</th>
                                    <th>Livreur</th>
                                    <th>Montant</th>
                                    <th>Livrée le</th>
                                    <th>Relances</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {unpaid.map((fare) => (
                                    <tr key={fare.order_id}>
                                        <td className="text-xs font-bold text-[var(--admin-text)]">#{fare.order_id}</td>
                                        <td>
                                            <div className="text-sm font-semibold text-[var(--admin-text)]">{fare.client?.name ?? '—'}</div>
                                            <div className="text-xs text-[var(--admin-muted)]">{fare.client?.phone ?? ''}</div>
                                            {fare.client?.restricted ? (
                                                <span className="mt-1 inline-flex rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-[10px] font-bold text-rose-700">Restreint</span>
                                            ) : null}
                                        </td>
                                        <td className="text-xs text-[var(--admin-text-soft)]">{fare.driver?.name ?? '—'}</td>
                                        <td className="text-sm font-bold text-[var(--admin-text)]">{money(fare.montant)}</td>
                                        <td className="text-xs text-[var(--admin-muted)]">{dateTime(fare.delivered_at)}</td>
                                        <td className="text-xs text-[var(--admin-muted)]">
                                            {fare.reminders_count}/{maxReminders}
                                            <div>Prochaine : {dateTime(fare.next_reminder_at)}</div>
                                        </td>
                                        <td>
                                            <button type="button" onClick={() => remind(fare)} className="rounded-lg bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700 transition hover:bg-blue-100">
                                                Relancer maintenant
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    )}
                </div>
            </Surface>

            <Surface className="rounded-[32px] p-5 lg:p-6">
                <SectionTitle
                    title="Comptes restreints"
                    description="Restriction levée automatiquement au paiement de la dernière course due. Une levée manuelle est motivée et journalisée."
                />
                <div className="mt-5">
                    {restricted.length === 0 ? (
                        <EmptyState title="Aucun compte restreint" description="Les clients restreints pour course impayée apparaîtront ici." />
                    ) : (
                        <DataTable>
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Depuis</th>
                                    <th>Motif</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {restricted.map((client) => (
                                    <tr key={client.id}>
                                        <td>
                                            <div className="text-sm font-semibold text-[var(--admin-text)]">{client.name ?? `#${client.id}`}</div>
                                            <div className="text-xs text-[var(--admin-muted)]">{client.phone ?? ''}</div>
                                        </td>
                                        <td className="text-xs text-[var(--admin-muted)]">{dateTime(client.restricted_at)}</td>
                                        <td className="max-w-md text-xs text-[var(--admin-text-soft)]">{client.reason ?? '—'}</td>
                                        <td>
                                            <button type="button" onClick={() => lift(client)} className="rounded-lg bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-800 transition hover:bg-amber-100">
                                                Lever la restriction
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    )}
                </div>
            </Surface>

            <Surface className="rounded-[32px] p-5 lg:p-6">
                <SectionTitle
                    title="Virements de commandes à confirmer"
                    description="Au-delà du plafond Mobile Money, une commande se règle par virement : le stock reste réservé 72 h, jusqu'à la confirmation de réception."
                />
                <div className="mt-5">
                    {transfers.length === 0 ? (
                        <EmptyState title="Aucun virement en attente" description="Les virements annoncés par les clients apparaîtront ici." />
                    ) : (
                        <DataTable>
                            <thead>
                                <tr>
                                    <th>Référence</th>
                                    <th>Client</th>
                                    <th>Commandes</th>
                                    <th>Montant</th>
                                    <th>Annoncé le</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {transfers.map((transfer) => (
                                    <tr key={transfer.transaction_id}>
                                        <td className="text-xs font-bold text-[var(--admin-text)]">{transfer.reference ?? `#${transfer.transaction_id}`}</td>
                                        <td className="text-sm font-semibold text-[var(--admin-text)]">{transfer.client?.name ?? '—'}</td>
                                        <td className="text-xs text-[var(--admin-text-soft)]">#{transfer.order_ids.join(', #')}</td>
                                        <td className="text-sm font-bold text-[var(--admin-text)]">{money(transfer.montant)}</td>
                                        <td className="text-xs text-[var(--admin-muted)]">{dateTime(transfer.created_at)}</td>
                                        <td>
                                            <button type="button" onClick={() => confirmTransfer(transfer)} className="rounded-lg bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100">
                                                Virement reçu
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    )}
                </div>
            </Surface>

            {confirmDialog}
        </div>
    );
}
