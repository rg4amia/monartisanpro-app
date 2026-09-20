// Onglet « Parrainage Clients » du backoffice — calque de PromoCodesPanel.tsx.

import { cn } from '@/lib/utils';

import { DataTable, money, PlusIcon, Surface } from '../shared';
import type { CampagneParrainageItem } from '../shared';

interface CampagnesParrainagePanelProps {
    filteredCampagnes: CampagneParrainageItem[];
    onCreate: () => void;
    onEdit: (campagne: CampagneParrainageItem) => void;
    onToggle: (campagne: CampagneParrainageItem) => void;
    onDelete: (campagne: CampagneParrainageItem) => void;
}

export function CampagnesParrainagePanel({
    filteredCampagnes,
    onCreate,
    onEdit,
    onToggle,
    onDelete,
}: CampagnesParrainagePanelProps) {
    return (
        <section className="mt-5 space-y-5">
            <Surface className="rounded-[32px] p-5 lg:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-[var(--admin-border)] pb-5">
                    <div>
                        <h3 className="text-xl font-bold text-[var(--admin-text)] flex items-center gap-2">
                            <span>🤝 Campagnes de Parrainage</span>
                            <span className="rounded-full bg-[#ebb95e]/20 text-[#8a5d16] text-xs font-bold px-2.5 py-0.5">
                                {filteredCampagnes.length} campagne(s)
                            </span>
                        </h3>
                        <p className="text-xs text-[var(--admin-text-soft)] mt-1">
                            Configurez des remises de parrainage client → client applicables lors du paiement des missions et commandes.
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            onClick={onCreate}
                            className="inline-flex items-center gap-2 rounded-full bg-[#ebb95e] text-[#241b16] px-5 py-2.5 text-xs font-bold hover:opacity-90 transition shadow-sm"
                        >
                            <PlusIcon className="h-4 w-4" />
                            Nouvelle Campagne
                        </button>
                    </div>
                </div>

                <div className="mt-5 overflow-x-auto">
                    <DataTable>
                        <thead>
                            <tr>
                                <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Libellé</th>
                                <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Remise</th>
                                <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Conditions</th>
                                <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Période</th>
                                <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Statut</th>
                                <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Créée le</th>
                                <th className="py-3 px-4 text-right text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[var(--admin-border)]">
                            {filteredCampagnes.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="text-center py-10 text-[var(--admin-muted)]">
                                        Aucune campagne de parrainage trouvée. Cliquez sur &quot;Nouvelle Campagne&quot; pour en créer une.
                                    </td>
                                </tr>
                            ) : (
                                filteredCampagnes.map((campagne) => (
                                    <tr key={campagne.id} className="hover:bg-white/10 dark:hover:bg-white/5 transition">
                                        <td className="py-3.5 px-4">
                                            <div className="font-semibold text-sm text-[var(--admin-text)]">
                                                {campagne.libelle}
                                            </div>
                                        </td>
                                        <td className="py-3.5 px-4 whitespace-nowrap">
                                            <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-extrabold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">
                                                {campagne.discount_type === 'percent' ? `-${campagne.discount_value}%` : `-${money(campagne.discount_value)}`}
                                            </span>
                                        </td>
                                        <td className="py-3.5 px-4 text-xs text-[var(--admin-text-soft)]">
                                            <div>Min : <strong>{campagne.min_montant > 0 ? money(campagne.min_montant) : 'Aucun'}</strong></div>
                                            {campagne.max_discount_amount ? (
                                                <div>Plafond : <strong>{money(campagne.max_discount_amount)}</strong></div>
                                            ) : null}
                                        </td>
                                        <td className="py-3.5 px-4 text-xs text-[var(--admin-text-soft)] whitespace-nowrap">
                                            {campagne.starts_at ? (
                                                <div>Du : <strong>{new Date(campagne.starts_at).toLocaleDateString('fr-FR')}</strong></div>
                                            ) : null}
                                            {campagne.expires_at ? (
                                                <div>Au : <strong>{new Date(campagne.expires_at).toLocaleDateString('fr-FR')}</strong></div>
                                            ) : (
                                                <span className="text-emerald-600 font-semibold">Illimitée</span>
                                            )}
                                        </td>
                                        <td className="py-3.5 px-4 whitespace-nowrap">
                                            <button
                                                type="button"
                                                onClick={() => onToggle(campagne)}
                                                className={cn(
                                                    'rounded-full px-3 py-1 text-xs font-bold border transition cursor-pointer',
                                                    campagne.is_active
                                                        ? 'border-green-600/40 bg-green-500/15 text-green-700 dark:text-green-400 hover:bg-green-500/25'
                                                        : 'border-red-600/40 bg-red-500/15 text-red-700 dark:text-red-400 hover:bg-red-500/25',
                                                )}
                                            >
                                                {campagne.is_active ? '● Actif' : '○ Inactif'}
                                            </button>
                                        </td>
                                        <td className="py-3.5 px-4 text-xs text-[var(--admin-text-soft)] whitespace-nowrap">
                                            {new Date(campagne.created_at).toLocaleDateString('fr-FR')}
                                        </td>
                                        <td className="py-3.5 px-4 text-right whitespace-nowrap">
                                            <div className="flex justify-end gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => onEdit(campagne)}
                                                    className="rounded-lg px-2.5 py-1 text-xs font-semibold bg-black/5 hover:bg-black/10 text-[var(--admin-text)] transition"
                                                >
                                                    Modifier
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => onDelete(campagne)}
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
