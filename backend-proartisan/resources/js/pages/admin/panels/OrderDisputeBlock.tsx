// Litige ouvert par le client sur une commande : motif, et clôture par
// l'administrateur avec son issue (Chantier 21).

import { router } from '@inertiajs/react';

import { shortDate } from '../shared';
import type { AdminOrder } from '../shared';
import { useConfirm } from '../shared/ConfirmDialog';

type Outcome = 'reclamation_acceptee' | 'reclamation_rejetee';

const outcomes: Array<{ value: Outcome; button: string; title: string }> = [
    { value: 'reclamation_acceptee', button: 'Accepter la réclamation', title: 'Accepter la réclamation du client ?' },
    { value: 'reclamation_rejetee', button: 'Rejeter la réclamation', title: 'Rejeter la réclamation du client ?' },
];

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

    if (order.status !== 'disputed') return null;

    const resolve = async (outcome: (typeof outcomes)[number]) => {
        const note = await confirm({
            title: outcome.title,
            message:
                "Le litige sera clos et la commande reviendra à l'état « Livrée ». Le client, le fournisseur et le livreur seront prévenus de la décision.\n\nAucun fonds n'est déplacé : un remboursement ou un dédommagement se traite à part.",
            confirmLabel: 'Clore le litige',
            promptLabel: 'Motif de la décision',
            promptMinLength: 5,
            tone: 'primary',
        });
        if (typeof note !== 'string') return;

        router.post(
            `/admin/orders/${order.id}/dispute/resolve`,
            { outcome: outcome.value, note: note.trim() },
            { preserveScroll: true, onSuccess: () => onResolved?.() },
        );
    };

    return (
        <div className="mt-6 rounded-2xl border border-rose-300 bg-rose-50 p-4 text-xs text-rose-900">
            <p className="text-sm font-bold text-rose-950">Litige ouvert par le client</p>
            <p className="mt-1">
                {order.dispute_opened_at ? `Ouvert le ${shortDate(order.dispute_opened_at)}. ` : ''}
                Motif : {order.dispute_reason || 'non renseigné'}
            </p>

            {canResolve ? (
                <div className="mt-3 flex flex-wrap gap-2">
                    {outcomes.map((outcome) => (
                        <button
                            key={outcome.value}
                            type="button"
                            onClick={() => resolve(outcome)}
                            className="rounded-xl border border-rose-300 bg-white px-3 py-1.5 text-xs font-bold text-rose-900 transition hover:bg-rose-100"
                        >
                            {outcome.button}
                        </button>
                    ))}
                </div>
            ) : (
                <p className="mt-2 italic">La clôture du litige demande le droit d'arbitrer les litiges.</p>
            )}

            {dialog}
        </div>
    );
}
