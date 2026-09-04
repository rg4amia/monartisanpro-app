// Modales de détail (lecture seule) du backoffice — extraites de console.tsx (Chantier C2).

import { cn } from '@/lib/utils';

import {
    DeliveryModeBadge,
    DeliveryStatusBadge,
    EmptyState,
    MissionStatusBadge,
    money,
    ProviderBadge,
    shortDate,
    TransactionStatusBadge,
    transactionTypeLabels,
} from '../shared';
import type { AdminMission, AdminOrder, AdminTransaction, ArtisanScoreItem, ScoreLedgerEntryItem } from '../shared';

function CloseButton({ onClose }: { onClose: () => void }) {
    return (
        <button
            type="button"
            onClick={onClose}
            className="rounded-full p-2 text-[var(--admin-muted)] hover:bg-white/10 hover:text-[var(--admin-text)] transition"
            title="Fermer"
            aria-label="Fermer"
        >
            <svg className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                <path d="M6 18L18 6M6 6l12 12" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
        </button>
    );
}

export function ArtisanLedgerModal({
    artisan,
    scoreLedger,
    onClose,
}: {
    artisan: ArtisanScoreItem;
    scoreLedger: ScoreLedgerEntryItem[];
    onClose: () => void;
}) {
    const entries = scoreLedger.filter((entry) => entry.user_id === artisan.id);

    return (
        <div role="dialog" aria-modal="true" aria-label="Fenêtre de détail" className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div className="admin-panel admin-surface w-full max-w-[700px] rounded-[32px] border p-6 lg:p-8 shadow-2xl relative">
                <div className="flex items-center justify-between border-b border-[var(--admin-border)] pb-4">
                    <div>
                        <h2 className="text-xl font-bold text-[var(--admin-text)]">
                            Historique ProsArtisan : {artisan.name}
                        </h2>
                        <p className="text-xs text-[var(--admin-muted)] mt-1">
                            Score actuel : {artisan.score_prosartisan}/1000 • {artisan.score_frozen ? 'Score Gelé' : 'Score Dynamique'}
                        </p>
                    </div>
                    <CloseButton onClose={onClose} />
                </div>

                <div className="mt-6 max-h-[400px] overflow-y-auto space-y-3 pr-1">
                    {entries.length === 0 ? (
                        <EmptyState description="Aucun événement enregistré dans le Ledger pour cet artisan." title="Historique vide" />
                    ) : (
                        entries.map((entry) => (
                            <div key={entry.id} className="rounded-2xl border border-[var(--admin-border)] bg-white/60 p-4 flex items-start justify-between gap-4">
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2">
                                        <span className={cn(
                                            'rounded-md px-2 py-0.5 text-[10px] font-bold uppercase',
                                            entry.points > 0 ? 'bg-green-100 text-green-800' : 'bg-rose-100 text-rose-800',
                                        )}>
                                            {entry.points > 0 ? `+${entry.points}` : entry.points} points
                                        </span>
                                        <span className="text-xs text-[var(--admin-muted)]">
                                            Poids de crédibilité: {entry.credibility_factor}
                                        </span>
                                    </div>
                                    <p className="mt-2 text-sm font-semibold text-[var(--admin-text)]">{entry.description}</p>
                                    <span className="mt-1 block text-xs text-[var(--admin-muted)]">Type: {entry.event_type}</span>
                                </div>
                                <span className="text-xs text-[var(--admin-muted)] shrink-0">
                                    {shortDate(entry.created_at)}
                                </span>
                            </div>
                        ))
                    )}
                </div>

                <div className="mt-6 flex justify-end">
                    <button type="button" onClick={onClose} className="admin-button admin-button--ghost">
                        Fermer
                    </button>
                </div>
            </div>
        </div>
    );
}

export function MissionDetailModal({
    mission,
    orders,
    onClose,
    onSelectOrder,
}: {
    mission: AdminMission;
    orders: AdminOrder[];
    onClose: () => void;
    onSelectOrder: (order: AdminOrder) => void;
}) {
    const clientId = (mission.client as any)?.id;
    const artisanId = (mission.artisan as any)?.id;
    const relatedOrders = (orders ?? []).filter((o) => o.client_id === clientId || o.client_id === artisanId);

    return (
        <div role="dialog" aria-modal="true" aria-label="Fenêtre de détail" className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div className="admin-panel admin-surface w-full max-w-[850px] rounded-[32px] border p-6 lg:p-8 shadow-2xl relative max-h-[85vh] overflow-y-auto">
                <div className="flex items-center justify-between border-b border-[var(--admin-border)] pb-4">
                    <div className="space-y-1">
                        <h2 className="text-xl font-bold text-[var(--admin-text)] flex items-center gap-3">
                            <span>Détails de la mission #{mission.id}</span>
                            <MissionStatusBadge status={mission.status} />
                        </h2>
                        <p className="text-xs text-[var(--admin-muted)]">Créée le {shortDate(mission.created_at)}</p>
                    </div>
                    <CloseButton onClose={onClose} />
                </div>

                <div className="mt-6 space-y-6">
                    <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-3">
                        <div className="p-4 rounded-2xl border border-[var(--admin-border)] bg-white/40">
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Description</p>
                            <p className="mt-1 text-sm text-[var(--admin-text)] font-medium">{mission.description}</p>
                        </div>
                        <div className="p-4 rounded-2xl border border-[var(--admin-border)] bg-white/40">
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Client</p>
                            <p className="mt-1 text-sm text-[var(--admin-text)] font-bold">{mission.client?.name ?? 'Non renseigné'}</p>
                            <p className="text-xs text-[var(--admin-muted)]">{mission.client?.phone}</p>
                        </div>
                        <div className="p-4 rounded-2xl border border-[var(--admin-border)] bg-white/40">
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Artisan</p>
                            <p className="mt-1 text-sm text-[var(--admin-text)] font-bold">{mission.artisan?.name ?? 'Non affecté'}</p>
                            <p className="text-xs text-[var(--admin-muted)]">{mission.artisan?.phone}</p>
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-3">
                        <div className="p-4 rounded-2xl border border-[var(--admin-border)] bg-white/40">
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Analyse Gemini IA</p>
                            <p className="mt-1 text-sm text-[var(--admin-text)] font-semibold">{mission.gemini_category ?? 'Non classée'}</p>
                            <p className="text-xs text-[var(--admin-text-soft)]">Urgence : {mission.gemini_urgency ?? 'N/A'}</p>
                        </div>
                        <div className="p-4 rounded-2xl border border-[var(--admin-border)] bg-white/40">
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Financement total</p>
                            <p className="mt-1 text-base font-bold text-[#8a6b3d]">{mission.montant_total ? money(mission.montant_total) : 'Non défini'}</p>
                            {mission.montant_materiaux && (
                                <p className="text-xs text-[var(--admin-text-soft)]">Matériaux : {money(mission.montant_materiaux)}</p>
                            )}
                        </div>
                        <div className="p-4 rounded-2xl border border-[var(--admin-border)] bg-white/40">
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Main d'œuvre & Ratio</p>
                            <p className="mt-1 text-sm font-semibold text-[var(--admin-text)]">
                                {mission.montant_mo ? money(mission.montant_mo) : 'Non défini'}
                            </p>
                            {mission.ratio_materiaux && (
                                <p className="text-xs text-[var(--admin-text-soft)]">Ratio Mat : {(Number(mission.ratio_materiaux) * 100).toFixed(0)}%</p>
                            )}
                        </div>
                    </div>

                    <div className="space-y-2.5">
                        <h3 className="text-sm font-bold text-[var(--admin-text)] uppercase tracking-wider">Historique des Jalons</h3>
                        {mission.jalons && mission.jalons.length > 0 ? (
                            <div className="overflow-x-auto rounded-2xl border border-[var(--admin-border)] bg-white/30">
                                <table className="min-w-full divide-y divide-[var(--admin-border)] text-xs text-left">
                                    <thead className="bg-[#fcf8f2] text-[var(--admin-muted)] font-semibold uppercase">
                                        <tr>
                                            <th className="px-4 py-2">Ordre</th>
                                            <th className="px-4 py-2">Description</th>
                                            <th className="px-4 py-2">Montant</th>
                                            <th className="px-4 py-2">Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-[var(--admin-border)]">
                                        {mission.jalons.map((jalon: any) => (
                                            <tr key={jalon.id}>
                                                <td className="px-4 py-2 font-bold">#{jalon.ordre}</td>
                                                <td className="px-4 py-2">{jalon.description}</td>
                                                <td className="px-4 py-2 font-medium">{money(jalon.montant)}</td>
                                                <td className="px-4 py-2">
                                                    <span className={cn('px-2 py-0.5 rounded-full border text-[10px] font-bold',
                                                        jalon.statut === 'paye' || jalon.statut === 'valide' ? 'border-green-300 bg-green-50 text-green-700' : 'border-amber-300 bg-amber-50 text-amber-700',
                                                    )}>
                                                        {jalon.statut}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-xs text-[var(--admin-muted)] italic">Aucun jalon défini.</p>
                        )}
                    </div>

                    <div className="space-y-2.5">
                        <h3 className="text-sm font-bold text-[var(--admin-text)] uppercase tracking-wider">Historique des J-Codes (Matériaux)</h3>
                        {mission.jcodes && mission.jcodes.length > 0 ? (
                            <div className="overflow-x-auto rounded-2xl border border-[var(--admin-border)] bg-white/30">
                                <table className="min-w-full divide-y divide-[var(--admin-border)] text-xs text-left">
                                    <thead className="bg-[#fcf8f2] text-[var(--admin-muted)] font-semibold uppercase">
                                        <tr>
                                            <th className="px-4 py-2">Code</th>
                                            <th className="px-4 py-2">Montant</th>
                                            <th className="px-4 py-2">Statut</th>
                                            <th className="px-4 py-2">Fournisseur</th>
                                            <th className="px-4 py-2">Date d'utilisation</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-[var(--admin-border)]">
                                        {mission.jcodes.map((jcode: any) => (
                                            <tr key={jcode.id}>
                                                <td className="px-4 py-2 font-mono font-bold text-[#8a6b3d]">{jcode.code}</td>
                                                <td className="px-4 py-2 font-medium">{money(jcode.montant)}</td>
                                                <td className="px-4 py-2">
                                                    <span className={cn('px-2 py-0.5 rounded-full border text-[10px] font-bold',
                                                        jcode.statut === 'utilise' ? 'border-green-300 bg-green-50 text-green-700' : 'border-amber-300 bg-amber-50 text-amber-700',
                                                    )}>
                                                        {jcode.statut}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-2">{jcode.fournisseur?.nom_boutique ?? jcode.fournisseur?.name ?? 'Non scanné'}</td>
                                                <td className="px-4 py-2">{jcode.scanned_at ? shortDate(jcode.scanned_at) : 'En attente'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-xs text-[var(--admin-muted)] italic">Aucun J-Code généré.</p>
                        )}
                    </div>

                    <div className="space-y-2.5">
                        <h3 className="text-sm font-bold text-[var(--admin-text)] uppercase tracking-wider">Transactions liées</h3>
                        {mission.transactions && mission.transactions.length > 0 ? (
                            <div className="overflow-x-auto rounded-2xl border border-[var(--admin-border)] bg-white/30">
                                <table className="min-w-full divide-y divide-[var(--admin-border)] text-xs text-left">
                                    <thead className="bg-[#fcf8f2] text-[var(--admin-muted)] font-semibold uppercase">
                                        <tr>
                                            <th className="px-4 py-2">Type</th>
                                            <th className="px-4 py-2">Montant</th>
                                            <th className="px-4 py-2">Moyen</th>
                                            <th className="px-4 py-2">Statut</th>
                                            <th className="px-4 py-2">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-[var(--admin-border)]">
                                        {mission.transactions.map((tx: any) => (
                                            <tr key={tx.id}>
                                                <td className="px-4 py-2 font-semibold">{transactionTypeLabels[tx.type] ?? tx.type}</td>
                                                <td className="px-4 py-2 font-bold">{money(tx.montant)}</td>
                                                <td className="px-4 py-2">
                                                    <ProviderBadge provider={tx.provider} />
                                                </td>
                                                <td className="px-4 py-2">
                                                    <TransactionStatusBadge status={tx.statut} />
                                                </td>
                                                <td className="px-4 py-2 text-[var(--admin-muted)]">{shortDate(tx.created_at)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-xs text-[var(--admin-muted)] italic">Aucune transaction enregistrée.</p>
                        )}
                    </div>

                    {relatedOrders.length > 0 && (
                        <div className="space-y-2.5">
                            <h3 className="text-sm font-bold text-[var(--admin-text)] uppercase tracking-wider flex items-center gap-2">
                                <span>🛵 Courses & Livraisons Liées</span>
                            </h3>
                            <div className="grid gap-3 sm:grid-cols-2">
                                {relatedOrders.map((ord) => (
                                    <div
                                        key={ord.id}
                                        onClick={() => onSelectOrder(ord)}
                                        className="rounded-2xl border border-[var(--admin-border)] bg-white/60 p-3 hover:border-[#8a6b3d] cursor-pointer transition flex items-center justify-between"
                                    >
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <span className="font-bold text-xs">Course #{ord.id}</span>
                                                <DeliveryStatusBadge status={ord.status} />
                                            </div>
                                            <p className="text-xs text-[var(--admin-text-soft)] mt-1">
                                                {ord.driver ? `🛵 ${ord.driver.name}` : 'En attente de coursier'}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <span className="font-bold text-xs text-[#8a6b3d]">{money(ord.total_amount || ord.subtotal)}</span>
                                            <p className="text-[10px] text-[var(--admin-muted)]">{shortDate(ord.created_at)}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

export function OrderDetailModal({ order, onClose }: { order: AdminOrder; onClose: () => void }) {
    return (
        <div role="dialog" aria-modal="true" aria-label="Fenêtre de détail" className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div className="admin-panel admin-surface w-full max-w-[850px] rounded-[32px] border p-6 lg:p-8 shadow-2xl relative max-h-[88vh] overflow-y-auto">
                <div className="flex items-center justify-between border-b border-[var(--admin-border)] pb-4">
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-3">
                            <h2 className="text-xl font-bold text-[var(--admin-text)]">
                                Suivi 360° Livraison #{order.id}
                            </h2>
                            <DeliveryStatusBadge status={order.status} />
                            <DeliveryModeBadge mode={order.delivery_mode} />
                        </div>
                        <p className="text-xs text-[var(--admin-muted)]">
                            Créée le {new Date(order.created_at).toLocaleString('fr-FR')}
                            {order.delivered_at && ` • Livrée le ${new Date(order.delivered_at).toLocaleString('fr-FR')}`}
                        </p>
                    </div>
                    <CloseButton onClose={onClose} />
                </div>

                <div className="mt-6 rounded-2xl border border-[var(--admin-border)] bg-[#fcf8f2]/60 p-4">
                    <p className="text-[11px] font-bold uppercase tracking-wider text-[var(--admin-muted)] mb-3">Progression de la Course</p>
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center text-xs">
                        <div className={cn('p-2 rounded-xl border', order.status !== 'unpaid' ? 'bg-green-50 border-green-300 text-green-800 font-bold' : 'bg-gray-50 text-gray-400')}>
                            <span>1. Payée / Séquestrée</span>
                        </div>
                        <div className={cn('p-2 rounded-xl border', ['prepared', 'searching_driver', 'driver_assigned', 'driver_picked_up', 'shipping', 'delivered'].includes(order.status) ? 'bg-green-50 border-green-300 text-green-800 font-bold' : 'bg-gray-50 text-gray-400')}>
                            <span>2. Préparée en boutique</span>
                        </div>
                        <div className={cn('p-2 rounded-xl border', ['driver_assigned', 'driver_picked_up', 'shipping', 'delivered'].includes(order.status) ? 'bg-green-50 border-green-300 text-green-800 font-bold' : order.status === 'searching_driver' ? 'bg-amber-50 border-amber-300 text-amber-800 font-bold animate-pulse' : 'bg-gray-50 text-gray-400')}>
                            <span>3. Coursier assigné</span>
                        </div>
                        <div className={cn('p-2 rounded-xl border', order.status === 'delivered' ? 'bg-green-100 border-green-400 text-green-900 font-bold' : ['shipping', 'driver_picked_up'].includes(order.status) ? 'bg-purple-50 border-purple-300 text-purple-800 font-bold animate-pulse' : 'bg-gray-50 text-gray-400')}>
                            <span>4. Remise validée</span>
                        </div>
                    </div>
                </div>

                <div className="mt-6 space-y-6">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="p-4 rounded-2xl border border-[var(--admin-border)] bg-white/50">
                            <p className="text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)] flex items-center gap-1">
                                <span>🛵 Livreur (Coursier)</span>
                            </p>
                            {order.driver ? (
                                <div className="mt-2 space-y-1">
                                    <p className="text-sm font-bold text-[var(--admin-text)]">{order.driver.name}</p>
                                    <p className="text-xs text-[var(--admin-muted)]">{order.driver.role ?? 'Livreur'}</p>
                                    <div className="pt-1.5">
                                        <a
                                            href={`tel:${order.driver.phone}`}
                                            className="inline-flex items-center gap-1.5 rounded-lg bg-amber-50 border border-amber-200 px-2.5 py-1 text-xs font-mono font-bold text-[#8a6b3d] hover:bg-amber-100 transition"
                                        >
                                            📞 {order.driver.phone}
                                        </a>
                                    </div>
                                </div>
                            ) : (
                                <p className="mt-2 text-xs text-amber-700 italic">Aucun livreur encore affecté.</p>
                            )}
                        </div>

                        <div className="p-4 rounded-2xl border border-[var(--admin-border)] bg-white/50">
                            <p className="text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)] flex items-center gap-1">
                                <span>👷 Destinataire sur chantier</span>
                            </p>
                            <div className="mt-2 space-y-1">
                                <p className="text-sm font-bold text-[var(--admin-text)]">
                                    {order.client?.name ?? 'Non renseigné'}
                                    {order.client?.role && (
                                        <span className="ml-1.5 text-[10px] uppercase font-semibold text-[var(--admin-muted)]">
                                            ({order.client.role})
                                        </span>
                                    )}
                                </p>
                                <div className="pt-1.5">
                                    <a
                                        href={`tel:${order.client?.phone}`}
                                        className="inline-flex items-center gap-1.5 rounded-lg bg-slate-50 border border-slate-200 px-2.5 py-1 text-xs font-mono font-bold text-[var(--admin-text)] hover:bg-slate-100 transition"
                                    >
                                        📞 {order.client?.phone ?? 'N/A'}
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div className="p-4 rounded-2xl border border-[var(--admin-border)] bg-white/50">
                            <p className="text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)] flex items-center gap-1">
                                <span>🏪 Quincaillerie de collecte</span>
                            </p>
                            <div className="mt-2 space-y-1">
                                <p className="text-sm font-bold text-[var(--admin-text)]">
                                    {order.supplier?.fournisseur_agree?.nom_boutique ?? order.supplier?.name ?? 'Quincaillerie'}
                                </p>
                                <div className="pt-1.5">
                                    <a
                                        href={`tel:${order.supplier?.phone}`}
                                        className="inline-flex items-center gap-1.5 rounded-lg bg-slate-50 border border-slate-200 px-2.5 py-1 text-xs font-mono font-bold text-[var(--admin-text)] hover:bg-slate-100 transition"
                                    >
                                        📞 {order.supplier?.phone ?? 'N/A'}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="p-4 rounded-2xl border border-[var(--admin-border)] bg-white/40 flex items-center justify-between">
                            <div>
                                <p className="text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)]">Code Retrait Quincaillerie</p>
                                <p className="mt-1 font-mono text-base font-bold text-[#8a6b3d]">{order.pickup_code || 'N/A'}</p>
                            </div>
                            <span className="text-xs text-[var(--admin-muted)]">Scanné/saisi lors de la collecte</span>
                        </div>

                        <div className="p-4 rounded-2xl border border-[var(--admin-border)] bg-white/40 flex items-center justify-between">
                            <div>
                                <p className="text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)]">Code Réception Chantier</p>
                                <p className="mt-1 font-mono text-base font-bold text-green-700">{order.reception_code || 'N/A'}</p>
                            </div>
                            <span className="text-xs text-[var(--admin-muted)]">Validé à la remise au chantier</span>
                        </div>
                    </div>

                    <div className="space-y-2.5">
                        <h3 className="text-sm font-bold text-[var(--admin-text)] uppercase tracking-wider">Articles & Matériaux Commandés</h3>
                        {order.items && order.items.length > 0 ? (
                            <div className="overflow-x-auto rounded-2xl border border-[var(--admin-border)] bg-white/30">
                                <table className="min-w-full divide-y divide-[var(--admin-border)] text-xs text-left">
                                    <thead className="bg-[#fcf8f2] text-[var(--admin-muted)] font-semibold uppercase">
                                        <tr>
                                            <th className="px-4 py-2.5">Article</th>
                                            <th className="px-4 py-2.5">Quantité</th>
                                            <th className="px-4 py-2.5">Prix unitaire</th>
                                            <th className="px-4 py-2.5">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-[var(--admin-border)]">
                                        {order.items.map((item) => (
                                            <tr key={item.id}>
                                                <td className="px-4 py-2.5 font-bold text-[var(--admin-text)]">
                                                    {item.product?.name ?? `Produit #${item.supplier_product_id}`}
                                                </td>
                                                <td className="px-4 py-2.5 font-semibold">
                                                    {item.quantity} {item.product?.unit ?? 'unité(s)'}
                                                </td>
                                                <td className="px-4 py-2.5">{money(item.unit_price)}</td>
                                                <td className="px-4 py-2.5 font-bold text-[var(--admin-text)]">
                                                    {money(item.quantity * item.unit_price)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-xs text-[var(--admin-muted)] italic">Aucun détail d'article disponible.</p>
                        )}
                    </div>

                    <div className="p-4 rounded-2xl border border-[var(--admin-border)] bg-[#fcf8f2] space-y-2 text-sm">
                        <div className="flex justify-between">
                            <span className="text-[var(--admin-text-soft)]">Sous-total Matériaux</span>
                            <span className="font-semibold text-[var(--admin-text)]">{money(order.subtotal)}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-[var(--admin-text-soft)]">Frais de livraison Livreur</span>
                            <span className="font-semibold text-[#8a6b3d]">{money(order.delivery_cost)}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-[var(--admin-text-soft)]">Commission ProsArtisan</span>
                            <span className="font-semibold text-[var(--admin-text)]">{money(order.platform_fee)}</span>
                        </div>
                        <div className="flex justify-between pt-2 border-t border-[var(--admin-border)] text-base font-bold">
                            <span className="text-[var(--admin-text)]">Total Général Séquestré</span>
                            <span className="text-[#8a6b3d]">{money(order.total_amount || order.subtotal)}</span>
                        </div>
                    </div>

                    {(order.pickup_photo_url || order.delivery_photo_url) && (
                        <div className="space-y-2.5">
                            <h3 className="text-sm font-bold text-[var(--admin-text)] uppercase tracking-wider">Preuves Photographiques</h3>
                            <div className="grid gap-4 sm:grid-cols-2">
                                {order.pickup_photo_url && (
                                    <div className="p-3 rounded-2xl border border-[var(--admin-border)] bg-white/40">
                                        <p className="text-xs font-semibold text-[var(--admin-text)] mb-2">📸 Ramassage Quincaillerie</p>
                                        <img src={order.pickup_photo_url} alt="Photo ramassage" className="w-full h-40 object-cover rounded-xl border" />
                                    </div>
                                )}
                                {order.delivery_photo_url && (
                                    <div className="p-3 rounded-2xl border border-[var(--admin-border)] bg-white/40">
                                        <p className="text-xs font-semibold text-[var(--admin-text)] mb-2">📸 Livraison sur Chantier</p>
                                        <img src={order.delivery_photo_url} alt="Photo livraison" className="w-full h-40 object-cover rounded-xl border" />
                                    </div>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

export function TransactionDetailModal({ transaction, onClose }: { transaction: AdminTransaction; onClose: () => void }) {
    return (
        <div role="dialog" aria-modal="true" aria-label="Fenêtre de détail" className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div className="admin-panel admin-surface w-full max-w-[550px] rounded-[32px] border p-6 lg:p-8 shadow-2xl relative animate-in fade-in zoom-in duration-200">
                <div className="flex items-center justify-between border-b border-[var(--admin-border)] pb-4">
                    <div className="space-y-1">
                        <h2 className="text-xl font-bold text-[var(--admin-text)] flex items-center gap-3">
                            <span>Transaction #{transaction.id}</span>
                            <TransactionStatusBadge status={transaction.statut} />
                        </h2>
                        <p className="text-xs text-[var(--admin-muted)]">Enregistrée le {shortDate(transaction.created_at)}</p>
                    </div>
                    <CloseButton onClose={onClose} />
                </div>

                <div className="mt-6 space-y-4 text-sm">
                    <div className="flex justify-between py-2.5 border-b border-[var(--admin-border)]">
                        <span className="text-[var(--admin-muted)]">Type de transaction</span>
                        <span className="font-semibold text-[var(--admin-text)]">{transactionTypeLabels[transaction.type] ?? transaction.type}</span>
                    </div>
                    <div className="flex justify-between py-2.5 border-b border-[var(--admin-border)]">
                        <span className="text-[var(--admin-muted)]">Montant</span>
                        <span className="font-bold text-[#8a6b3d] text-base">{money(transaction.montant)}</span>
                    </div>
                    <div className="flex justify-between py-2.5 border-b border-[var(--admin-border)]">
                        <span className="text-[var(--admin-muted)]">Moyen de paiement</span>
                        <span className="font-semibold text-[var(--admin-text)]">
                            <ProviderBadge provider={transaction.provider} />
                        </span>
                    </div>
                    <div className="flex justify-between py-2.5 border-b border-[var(--admin-border)]">
                        <span className="text-[var(--admin-muted)]">Référence externe</span>
                        <span className="font-mono text-xs bg-slate-100/80 border border-slate-200 rounded px-1.5 py-0.5 text-[var(--admin-text)]">
                            {transaction.reference_externe ?? 'N/A'}
                        </span>
                    </div>
                    <div className="flex justify-between py-2.5 border-b border-[var(--admin-border)]">
                        <span className="text-[var(--admin-muted)]">Provenance (Source)</span>
                        <span className="font-medium text-[var(--admin-text)]">{transaction.wallet_source}</span>
                    </div>
                    <div className="flex justify-between py-2.5 border-b border-[var(--admin-border)]">
                        <span className="text-[var(--admin-muted)]">Destination</span>
                        <span className="font-medium text-[var(--admin-text)]">{transaction.wallet_dest}</span>
                    </div>
                    <div className="flex justify-between py-2.5 border-b border-[var(--admin-border)]">
                        <span className="text-[var(--admin-muted)]">Bénéficiaire</span>
                        <span className="font-semibold text-[var(--admin-text)]">{transaction.user?.name ?? 'Non renseigné'}</span>
                    </div>
                    {transaction.mission && (
                        <div className="flex justify-between py-2.5 border-b border-[var(--admin-border)]">
                            <span className="text-[var(--admin-muted)]">Mission associée</span>
                            <span className="font-semibold text-blue-700">#{transaction.mission.id} - {transaction.mission.description}</span>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
