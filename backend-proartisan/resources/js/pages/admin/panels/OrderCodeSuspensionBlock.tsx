// Saisie d'un code de retrait ou de réception suspendue après trop de codes
// faux (Chantiers 32 et 34) : l'administrateur voit jusqu'à quand, et peut
// lever la suspension avec un motif. Le serveur reste seul juge.

import { router } from '@inertiajs/react';

import type { AdminOrder } from '../shared';
import { useConfirm } from '../shared/ConfirmDialog';

type CodeKind = 'pickup' | 'reception';

const LABELS: Record<CodeKind, string> = { pickup: 'retrait', reception: 'réception' };

export function OrderCodeSuspensionBlock({
    order,
    canLift,
    onLifted,
}: {
    order: AdminOrder;
    canLift: boolean;
    onLifted?: () => void;
}) {
    const { confirm, dialog } = useConfirm();

    const suspended = (['pickup', 'reception'] as CodeKind[])
        .map((kind) => ({ kind, until: order.code_suspensions?.[kind] ?? null }))
        .filter((entry): entry is { kind: CodeKind; until: string } => Boolean(entry.until));

    if (suspended.length === 0) return null;

    const lift = async (kind: CodeKind) => {
        const reason = await confirm({
            title: `Lever la suspension du code de ${LABELS[kind]} ?`,
            message:
                'La saisie de ce code redeviendra possible tout de suite et le compte des codes faux repartira de zéro. Ne levez la suspension qu’après avoir vérifié la situation avec le fournisseur, le livreur ou le client.',
            confirmLabel: 'Lever la suspension',
            promptLabel: 'Motif de la levée',
            promptMinLength: 5,
            tone: 'primary',
        });
        if (typeof reason !== 'string') return;

        router.post(
            `/admin/orders/${order.id}/codes/unlock`,
            { code: kind, reason: reason.trim() },
            { preserveScroll: true, onSuccess: () => onLifted?.() },
        );
    };

    return (
        <div className="mt-6 rounded-2xl border border-amber-300 bg-amber-50 p-4">
            <h3 className="text-sm font-bold text-amber-900">Saisie de code suspendue</h3>
            <p className="mt-1 text-xs text-amber-800">
                Trop de codes faux ont été saisis sur cette commande. La suspension se lève d’elle-même à l’heure indiquée.
            </p>
            <ul className="mt-3 space-y-2">
                {suspended.map(({ kind, until }) => (
                    <li key={kind} className="flex flex-wrap items-center justify-between gap-3 text-sm text-amber-900">
                        <span>
                            Code de {LABELS[kind]} : suspendu jusqu’à{' '}
                            <strong>{new Date(until).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}</strong>
                        </span>
                        {canLift && (
                            <button
                                type="button"
                                onClick={() => lift(kind)}
                                className="rounded-xl border border-amber-400 bg-white px-3 py-1.5 text-xs font-semibold text-amber-900 hover:bg-amber-100 transition"
                            >
                                Lever la suspension du code de {LABELS[kind]}
                            </button>
                        )}
                    </li>
                ))}
            </ul>
            {dialog}
        </div>
    );
}
