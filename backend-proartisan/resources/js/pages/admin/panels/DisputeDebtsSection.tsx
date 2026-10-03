// Dettes de litige de commande (Chantier 22) : sommes que ProsArtisan a avancées
// au client et que le responsable (fournisseur ou livreur) doit rembourser.
// Affiché dans le sous-onglet « Encaissements » de l'onglet Transactions.

import { router } from '@inertiajs/react';

import { DataTable, EmptyState, MetricCard, money, SectionTitle, shortDate, Surface, useConfirm } from '../shared';

export interface DisputeDebtEntry {
    id: number;
    montant: number;
    source: string;
    source_label: string;
    created_at: string | null;
}

export interface DisputeDebtItem {
    id: number;
    order_id: number | null;
    montant: number;
    montant_recouvre: number;
    restant: number;
    statut: 'en_cours' | 'soldee' | 'annulee';
    statut_label: string;
    created_at: string | null;
    settled_at: string | null;
    cancel_reason?: string | null;
    entries: DisputeDebtEntry[];
    user: { id: number; name: string | null; phone: string | null; role: string } | null;
}

export interface DisputeDebtsOverview {
    stats: { en_cours: number; restant: number; recouvre: number };
    debts: DisputeDebtItem[];
}

const roleLabels: Record<string, string> = { fournisseur: 'Fournisseur', livreur: 'Livreur' };

export function DisputeDebtsSection({ overview }: { overview?: DisputeDebtsOverview | null }) {
    const { confirm, dialog } = useConfirm();

    if (!overview) {
        return (
            <Surface className="rounded-[32px] p-5 lg:p-6">
                <SectionTitle title="Dettes de litige de commande" description="Sommes dues à ProsArtisan après un remboursement avancé au client." />
                <p role="alert" className="mt-4 text-xs text-rose-700">
                    Les dettes de litige n'ont pas pu être chargées. Rechargez la page.
                </p>
            </Surface>
        );
    }

    const cancel = async (debt: DisputeDebtItem) => {
        const reason = await confirm({
            title: 'Annuler cette dette ?',
            message: `${money(debt.restant)} restent dus par ${debt.user?.name ?? 'le responsable'}. L'annulation débloque son compte ; ProsArtisan supporte alors la somme.`,
            confirmLabel: 'Annuler la dette',
            cancelLabel: 'Fermer',
            promptLabel: "Motif de l'annulation",
            promptMinLength: 5,
            tone: 'danger',
        });
        if (typeof reason !== 'string') return;

        router.post(`/admin/dispute-debts/${debt.id}/cancel`, { reason: reason.trim() }, { preserveScroll: true });
    };

    return (
        <Surface className="rounded-[32px] p-5 lg:p-6">
            <SectionTitle
                title="Dettes de litige de commande"
                description="Sommes que ProsArtisan a avancées au client et que le responsable doit rembourser. Le compte du responsable est bloqué tant que la dette est en cours."
            />

            <div className="mt-4 grid gap-4 md:grid-cols-3">
                <MetricCard description="Comptes bloqués" tone="rose" trend="À rembourser" value={String(overview.stats.en_cours)}>
                    Dettes en cours
                </MetricCard>
                <MetricCard description="Somme encore due" tone="amber" trend="Restant" value={money(overview.stats.restant)}>
                    Restant dû
                </MetricCard>
                <MetricCard description="Prélèvements et règlements" tone="green" trend="Cumul" value={money(overview.stats.recouvre)}>
                    Déjà recouvré
                </MetricCard>
            </div>

            <DataTable className="mt-5">
                <thead>
                    <tr>
                        <th>Commande</th>
                        <th>Responsable</th>
                        <th>Dû</th>
                        <th>Recouvré</th>
                        <th>Restant</th>
                        <th>État</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {overview.debts.length === 0 ? (
                        <tr>
                            <td colSpan={7}>
                                <EmptyState
                                    title="Aucune dette de litige"
                                    description="Une dette apparaît quand un remboursement est avancé au client et que le responsable n'a plus les fonds."
                                />
                            </td>
                        </tr>
                    ) : (
                        overview.debts.map((debt) => (
                            <tr key={debt.id}>
                                <td>
                                    <p className="text-sm font-semibold text-[var(--admin-text)]">#{debt.order_id ?? '—'}</p>
                                    <p className="text-xs text-[var(--admin-muted)]">{debt.created_at ? shortDate(debt.created_at) : ''}</p>
                                </td>
                                <td>
                                    <p className="text-sm font-semibold text-[var(--admin-text)]">{debt.user?.name ?? 'Compte supprimé'}</p>
                                    <p className="text-xs text-[var(--admin-muted)]">
                                        {debt.user ? `${roleLabels[debt.user.role] ?? debt.user.role} · ${debt.user.phone ?? ''}` : ''}
                                    </p>
                                </td>
                                <td className="text-sm">{money(debt.montant)}</td>
                                <td className="text-sm">
                                    {money(debt.montant_recouvre)}
                                    {debt.entries.length > 0 && (
                                        <p className="text-[11px] text-[var(--admin-muted)]">
                                            {debt.entries.length} opération(s), dernière : {debt.entries[0].source_label}
                                        </p>
                                    )}
                                </td>
                                <td className="text-sm font-bold text-[var(--admin-text)]">{money(debt.restant)}</td>
                                <td>
                                    <span className="text-xs font-semibold">{debt.statut_label}</span>
                                    {debt.cancel_reason ? <p className="text-[11px] text-[var(--admin-muted)]">{debt.cancel_reason}</p> : null}
                                </td>
                                <td>
                                    {debt.statut === 'en_cours' ? (
                                        <button
                                            type="button"
                                            onClick={() => cancel(debt)}
                                            className="rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-1.5 text-xs font-bold text-[var(--admin-text)] transition hover:bg-black/5"
                                        >
                                            Annuler la dette
                                        </button>
                                    ) : null}
                                </td>
                            </tr>
                        ))
                    )}
                </tbody>
            </DataTable>

            {dialog}
        </Surface>
    );
}
