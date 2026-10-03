// Litige ouvert par le client sur une commande : motif, et clôture par
// l'administrateur avec son issue (Chantiers 21 et 22).
//
// Réclamation acceptée : l'administrateur fixe le montant remboursé (plafonné
// au prix des articles) et désigne le responsable. Le serveur reste seul juge
// du plafond et du responsable.

import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import { money, shortDate } from '../shared';
import type { AdminOrder } from '../shared';
import { useConfirm } from '../shared/ConfirmDialog';

type Responsible = 'fournisseur' | 'livreur';

export function OrderDisputeBlock({
    order,
    canResolve,
    onResolved,
}: {
    order: AdminOrder;
    canResolve: boolean;
    onResolved?: () => void;
}) {
    const { confirm, dialog } = useConfirm();
    const [accepting, setAccepting] = useState(false);
    const [amount, setAmount] = useState('');
    const [responsible, setResponsible] = useState<Responsible>('fournisseur');
    const [note, setNote] = useState('');

    if (order.status !== 'disputed') return null;

    const ceiling = Number(order.subtotal) || 0;
    const hasDriver = Boolean(order.driver_id);
    const refund = Number(amount);
    const amountValid = Number.isInteger(refund) && refund >= 1 && refund <= ceiling;
    const canSubmit = amountValid && note.trim().length >= 5;

    const post = (payload: Record<string, string | number>) => {
        router.post(`/admin/orders/${order.id}/dispute/resolve`, payload, {
            preserveScroll: true,
            onSuccess: () => onResolved?.(),
        });
    };

    const reject = async () => {
        const reason = await confirm({
            title: 'Rejeter la réclamation du client ?',
            message:
                "Le litige sera clos sans remboursement et la commande reviendra à l'état « Livrée ». La somme gelée chez le fournisseur sera libérée. Le client, le fournisseur et le livreur seront prévenus.",
            confirmLabel: 'Clore le litige',
            promptLabel: 'Motif de la décision',
            promptMinLength: 5,
            tone: 'primary',
        });
        if (typeof reason !== 'string') return;

        post({ outcome: 'reclamation_rejetee', note: reason.trim() });
    };

    const accept = (event: FormEvent) => {
        event.preventDefault();
        if (!canSubmit) return;

        post({ outcome: 'reclamation_acceptee', note: note.trim(), refund_amount: refund, responsible });
    };

    return (
        <div className="mt-6 rounded-2xl border border-rose-300 bg-rose-50 p-4 text-xs text-rose-900">
            <p className="text-sm font-bold text-rose-950">Litige ouvert par le client</p>
            <p className="mt-1">
                {order.dispute_opened_at ? `Ouvert le ${shortDate(order.dispute_opened_at)}. ` : ''}
                Motif : {order.dispute_reason || 'non renseigné'}
            </p>

            {!canResolve ? (
                <p className="mt-2 italic">La clôture du litige demande le droit d'arbitrer les litiges.</p>
            ) : accepting ? (
                <form onSubmit={accept} aria-label="Accepter la réclamation" className="mt-3 space-y-3 rounded-xl border border-rose-200 bg-white p-3">
                    <p className="text-[var(--admin-text-soft)]">
                        Le client est remboursé sur son Mobile Money ; ProsArtisan avance la somme. Le responsable la supporte : elle est prélevée sur
                        son portefeuille, et ce qui manque devient une dette qui bloque son compte. Les frais de service ne sont pas remboursés.
                    </p>

                    <label className="block">
                        <span className="mb-1 block font-semibold">Montant à rembourser (FCFA) — plafond {money(ceiling)}</span>
                        <input
                            type="number"
                            min={1}
                            max={ceiling}
                            step={1}
                            value={amount}
                            onChange={(event) => setAmount(event.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-2 text-sm text-[var(--admin-text)]"
                        />
                    </label>
                    {amount !== '' && !amountValid && (
                        <p role="alert" className="font-semibold text-rose-700">
                            Le montant doit être compris entre 1 FCFA et {money(ceiling)}.
                        </p>
                    )}

                    <label className="block">
                        <span className="mb-1 block font-semibold">Responsable</span>
                        <select
                            value={responsible}
                            onChange={(event) => setResponsible(event.target.value as Responsible)}
                            className="w-full rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-2 text-sm text-[var(--admin-text)]"
                        >
                            <option value="fournisseur">Fournisseur (marchandise manquante, erronée, défectueuse)</option>
                            {hasDriver && <option value="livreur">Livreur (marchandise abîmée ou perdue en route — la course est annulée ou remboursée)</option>}
                        </select>
                    </label>

                    <label className="block">
                        <span className="mb-1 block font-semibold">Motif de la décision</span>
                        <textarea
                            value={note}
                            onChange={(event) => setNote(event.target.value)}
                            rows={2}
                            className="w-full rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-2 text-sm text-[var(--admin-text)]"
                        />
                    </label>

                    <div className="flex flex-wrap justify-end gap-2">
                        <button type="button" onClick={() => setAccepting(false)} className="admin-button admin-button--ghost">
                            Retour
                        </button>
                        <button type="submit" disabled={!canSubmit} className="admin-button admin-button--primary disabled:opacity-50">
                            Rembourser et clore le litige
                        </button>
                    </div>
                </form>
            ) : (
                <div className="mt-3 flex flex-wrap gap-2">
                    <button
                        type="button"
                        onClick={() => setAccepting(true)}
                        className="rounded-xl border border-rose-300 bg-white px-3 py-1.5 text-xs font-bold text-rose-900 transition hover:bg-rose-100"
                    >
                        Accepter la réclamation
                    </button>
                    <button
                        type="button"
                        onClick={reject}
                        className="rounded-xl border border-rose-300 bg-white px-3 py-1.5 text-xs font-bold text-rose-900 transition hover:bg-rose-100"
                    >
                        Rejeter la réclamation
                    </button>
                </div>
            )}

            {dialog}
        </div>
    );
}
