// Onglet « Codes Promo » du backoffice — extrait de console.tsx (Chantier C2).

import { cn } from '@/lib/utils';

import { DataTable, money, PlusIcon, Surface } from '../shared';
import type { PromoCodeItem } from '../shared';

interface PromoCodesPanelProps {
    filteredPromoCodes: PromoCodeItem[];
    onCreate: () => void;
    onEdit: (promo: PromoCodeItem) => void;
    onToggle: (promo: PromoCodeItem) => void;
    onDelete: (promo: PromoCodeItem) => void;
}

export function PromoCodesPanel({
    filteredPromoCodes,
    onCreate,
    onEdit,
    onToggle,
    onDelete,
}: PromoCodesPanelProps) {
    return (
        <section className="mt-5 space-y-5">
            <Surface className="rounded-[32px] p-5 lg:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-[var(--admin-border)] pb-5">
                    <div>
                        <h3 className="text-xl font-bold text-[var(--admin-text)] flex items-center gap-2">
                            <span>🏷️ Gestion des Codes Promo</span>
                            <span className="rounded-full bg-[#ebb95e]/20 text-[#8a5d16] text-xs font-bold px-2.5 py-0.5">
                                {filteredPromoCodes.length} code(s)
                            </span>
                        </h3>
                        <p className="text-xs text-[var(--admin-text-soft)] mt-1">
                            Configurez des remises applicables lors du paiement des commandes e-commerce matériaux & articles.
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            onClick={onCreate}
                            className="inline-flex items-center gap-2 rounded-full bg-[#ebb95e] text-[#241b16] px-5 py-2.5 text-xs font-bold hover:opacity-90 transition shadow-sm"
                        >
                            <PlusIcon className="h-4 w-4" />
                            Nouveau Code Promo
                        </button>
                    </div>
                </div>

                <div className="mt-5 overflow-x-auto">
                    <DataTable>
                        <thead>
                            <tr>
                                <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Code & Description</th>
                                <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Remise</th>
                                <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Conditions</th>
                                <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Utilisations</th>
                                <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Période</th>
                                <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Statut</th>
                                <th className="py-3 px-4 text-right text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[var(--admin-border)]">
                            {filteredPromoCodes.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="text-center py-10 text-[var(--admin-muted)]">
                                        Aucun code promo trouvé. Cliquez sur &quot;Nouveau Code Promo&quot; pour en créer un.
                                    </td>
                                </tr>
                            ) : (
                                filteredPromoCodes.map((promo) => (
                                    <tr key={promo.id} className="hover:bg-white/10 dark:hover:bg-white/5 transition">
                                        <td className="py-3.5 px-4">
                                            <div className="font-mono font-bold text-sm text-[var(--admin-text)] flex items-center gap-1.5">
                                                <span className="px-2.5 py-0.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-900 dark:text-amber-300">
                                                    {promo.code}
                                                </span>
                                            </div>
                                            {promo.description && (
                                                <div className="text-xs text-[var(--admin-text-soft)] mt-1 max-w-xs truncate">
                                                    {promo.description}
                                                </div>
                                            )}
                                        </td>
                                        <td className="py-3.5 px-4 whitespace-nowrap">
                                            <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-extrabold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">
                                                {promo.discount_type === 'percent' ? `-${promo.discount_value}%` : `-${money(promo.discount_value)}`}
                                            </span>
                                        </td>
                                        <td className="py-3.5 px-4 text-xs text-[var(--admin-text-soft)]">
                                            <div>Min : <strong>{promo.min_order_amount > 0 ? money(promo.min_order_amount) : 'Aucun'}</strong></div>
                                            {promo.max_discount_amount ? (
                                                <div>Plafond : <strong>{money(promo.max_discount_amount)}</strong></div>
                                            ) : null}
                                        </td>
                                        <td className="py-3.5 px-4 text-xs text-[var(--admin-text)] whitespace-nowrap">
                                            <div className="font-bold">{promo.used_count} {promo.usage_limit ? `/ ${promo.usage_limit}` : 'utilisations'}</div>
                                            {promo.usage_limit ? (
                                                <div className="w-24 h-1.5 bg-black/10 rounded-full mt-1.5 overflow-hidden">
                                                    <div
                                                        className="h-full bg-[#ebb95e] rounded-full"
                                                        style={{ width: `${Math.min(100, (promo.used_count / promo.usage_limit) * 100)}%` }}
                                                    />
                                                </div>
                                            ) : null}
                                        </td>
                                        <td className="py-3.5 px-4 text-xs text-[var(--admin-text-soft)] whitespace-nowrap">
                                            {promo.expires_at ? (
                                                <div>Expire : <strong>{new Date(promo.expires_at).toLocaleDateString('fr-FR')}</strong></div>
                                            ) : (
                                                <span className="text-emerald-600 font-semibold">Illimitée</span>
                                            )}
                                        </td>
                                        <td className="py-3.5 px-4 whitespace-nowrap">
                                            <button
                                                type="button"
                                                onClick={() => onToggle(promo)}
                                                className={cn(
                                                    'rounded-full px-3 py-1 text-xs font-bold border transition cursor-pointer',
                                                    promo.is_active
                                                        ? 'border-green-600/40 bg-green-500/15 text-green-700 dark:text-green-400 hover:bg-green-500/25'
                                                        : 'border-red-600/40 bg-red-500/15 text-red-700 dark:text-red-400 hover:bg-red-500/25',
                                                )}
                                            >
                                                {promo.is_active ? '● Actif' : '○ Inactif'}
                                            </button>
                                        </td>
                                        <td className="py-3.5 px-4 text-right whitespace-nowrap">
                                            <div className="flex justify-end gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => onEdit(promo)}
                                                    className="rounded-lg px-2.5 py-1 text-xs font-semibold bg-black/5 hover:bg-black/10 text-[var(--admin-text)] transition"
                                                >
                                                    Modifier
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => onDelete(promo)}
                                                    className="rounded-lg px-2.5 py-1 text-xs font-semibold bg-red-500/10 hover:bg-red-500/20 text-red-600 transition"
                                                >
                                                    Supprimer
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </DataTable>
                </div>
            </Surface>
        </section>
    );
}
