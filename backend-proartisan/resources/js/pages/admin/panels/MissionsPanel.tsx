// Onglet « Missions » (chantiers + livraisons) du backoffice.
// Chantier C2 : découpe de console.tsx. Chantier C4 (P1-6) : listes paginées + filtres serveur.

import type { FormEvent, ReactNode } from 'react';

import { cn } from '@/lib/utils';

import {
    DataTable,
    DeliveryModeBadge,
    DeliveryStatusBadge,
    EmptyState,
    ExportButton,
    MetricCard,
    MissionStatusBadge,
    money,
    numberFormat,
    SectionTitle,
    shortDate,
    Surface,
} from '../shared';
import type { AdminMission, AdminOrder, DeliveryStats, MetricItem, MissionStats, Paginated } from '../shared';

type MissionSubTab = 'chantiers' | 'livraisons';

interface MissionsPanelProps {
    missionSubTab: MissionSubTab;
    onMissionSubTabChange: (tab: MissionSubTab) => void;
    deliveryStatusFilter: string;
    onDeliveryStatusFilterChange: (filter: string) => void;
    missionsPage: Paginated<AdminMission> | undefined;
    ordersPage: Paginated<AdminOrder> | undefined;
    missionStats: MissionStats;
    deliveryStats: DeliveryStats;
    missionSearch: string;
    onMissionSearchChange: (value: string) => void;
    onMissionSubmit: (event: FormEvent) => void;
    orderSearch: string;
    onOrderSearchChange: (value: string) => void;
    onOrderSubmit: (event: FormEvent) => void;
    onResetFilters: () => void;
    exportParams: Record<string, string>;
    renderMissionPagination: (links: Paginated<AdminMission>['links'] | undefined) => ReactNode;
    renderOrderPagination: (links: Paginated<AdminOrder>['links'] | undefined) => ReactNode;
    onSelectMission: (mission: AdminMission) => void;
    onSelectOrder: (order: AdminOrder) => void;
}

export function MissionsPanel({
    missionSubTab,
    onMissionSubTabChange,
    deliveryStatusFilter,
    onDeliveryStatusFilterChange,
    missionsPage,
    ordersPage,
    missionStats,
    deliveryStats,
    missionSearch,
    onMissionSearchChange,
    onMissionSubmit,
    orderSearch,
    onOrderSearchChange,
    onOrderSubmit,
    onResetFilters,
    exportParams,
    renderMissionPagination,
    renderOrderPagination,
    onSelectMission,
    onSelectOrder,
}: MissionsPanelProps) {
    const filteredMissions = missionsPage?.data ?? [];
    const filteredOrders = ordersPage?.data ?? [];

    const missionStatusMetrics: MetricItem[] = [
        { title: 'Missions en cours', description: 'Missions financées et terrain', tone: 'green', value: numberFormat.format(missionStats.en_cours) },
        { title: 'Seuil > 2M FCFA', description: 'Escalade Référent nécessaire', tone: 'amber', value: numberFormat.format(missionStats.referent_required) },
        { title: 'Missions en litige', description: 'Missions avec arbitrage', tone: 'rose', value: numberFormat.format(missionStats.en_litige) },
        { title: 'Missions enrichies', description: 'Analyses Gemini disponibles', tone: 'blue', value: numberFormat.format(missionStats.enrichies) },
    ];

    const deliveryMetrics: MetricItem[] = [
        { title: 'Total livraisons', description: 'Courses & livraisons enregistrées', tone: 'blue', value: numberFormat.format(deliveryStats.total) },
        { title: 'En transit', description: 'Livreur en route / Colis récupéré', tone: 'purple', value: numberFormat.format(deliveryStats.in_transit) },
        { title: 'En attente livreur', description: 'Recherche ou assignation coursier', tone: 'amber', value: numberFormat.format(deliveryStats.awaiting_driver) },
        { title: 'Livrées & Clôturées', description: 'Remises validées avec succès', tone: 'green', value: numberFormat.format(deliveryStats.delivered) },
    ];

    const deliveryFilters = [
        { id: 'all', label: 'Toutes les livraisons', count: deliveryStats.total },
        { id: 'searching_driver', label: 'Recherche coursier', count: deliveryStats.by_status.searching_driver ?? 0 },
        { id: 'driver_assigned', label: 'Livreur assigné', count: deliveryStats.by_status.driver_assigned ?? 0 },
        { id: 'shipping', label: 'En cours de livraison', count: deliveryStats.in_transit },
        { id: 'delivered', label: 'Livrées & Clôturées', count: deliveryStats.delivered },
        { id: 'disputed', label: 'En litige', count: deliveryStats.by_status.disputed ?? 0 },
    ];

    return (
        <section className="mt-5 space-y-5">
            {/* Sélecteur de sous-vue : Chantiers Artisans vs Livraisons & Courses Livreurs */}
            <div className="flex flex-wrap items-center justify-between gap-4 rounded-[28px] border border-[var(--admin-border)] bg-white/60 p-2 backdrop-blur-md">
                <div className="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        onClick={() => onMissionSubTabChange('chantiers')}
                        className={cn(
                            'flex items-center gap-2.5 rounded-[22px] px-5 py-2.5 text-sm font-semibold transition-all duration-200 shadow-sm',
                            missionSubTab === 'chantiers'
                                ? 'bg-[#1e293b] text-white shadow-md ring-2 ring-[#1e293b]/20'
                                : 'text-[var(--admin-text-soft)] hover:bg-black/5 hover:text-[var(--admin-text)]',
                        )}
                    >
                        <span>🔨 Chantiers & Missions Artisans</span>
                        <span className={cn(
                            'rounded-full px-2 py-0.5 text-xs font-bold',
                            missionSubTab === 'chantiers' ? 'bg-white/20 text-white' : 'bg-black/5 text-[var(--admin-text-soft)]',
                        )}>
                            {filteredMissions.length}
                        </span>
                    </button>

                    <button
                        type="button"
                        onClick={() => onMissionSubTabChange('livraisons')}
                        className={cn(
                            'flex items-center gap-2.5 rounded-[22px] px-5 py-2.5 text-sm font-semibold transition-all duration-200 shadow-sm',
                            missionSubTab === 'livraisons'
                                ? 'bg-[#8a6b3d] text-white shadow-md ring-2 ring-[#8a6b3d]/20'
                                : 'text-[var(--admin-text-soft)] hover:bg-black/5 hover:text-[var(--admin-text)]',
                        )}
                    >
                        <span>🛵 Livraisons Matériaux & Courses Livreurs</span>
                        <span className={cn(
                            'rounded-full px-2 py-0.5 text-xs font-bold',
                            missionSubTab === 'livraisons' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-900',
                        )}>
                            {filteredOrders.length}
                        </span>
                    </button>
                </div>

                <div className="px-3 text-xs text-[var(--admin-muted)] font-medium">
                    {missionSubTab === 'chantiers' ? 'Suivi des devis, jalons et séquestres artisans' : 'Suivi temps réel des coursiers, quincailleries et artisans'}
                </div>
            </div>

            {missionSubTab === 'chantiers' ? (
                <>
                    <div className="grid gap-4 xl:grid-cols-4">
                        {missionStatusMetrics.map((metric) => (
                            <MetricCard
                                key={metric.title}
                                description={metric.description}
                                tone={metric.tone}
                                trend="Lecture en temps réel"
                                value={metric.value}
                            >
                                {metric.title}
                            </MetricCard>
                        ))}
                    </div>

                    <Surface className="rounded-[32px] p-5 lg:p-6">
                        <SectionTitle
                            description="Vision claire des missions, enrichissement Gemini et montant piloté."
                            title="Pipeline missions"
                        />

                        <form onSubmit={onMissionSubmit} className="mt-4 flex flex-wrap gap-2">
                            <input
                                type="text"
                                placeholder="ID, description, catégorie, client, artisan..."
                                value={missionSearch}
                                onChange={(e) => onMissionSearchChange(e.target.value)}
                                className="w-full max-w-sm rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                            />
                            <button type="submit" className="rounded-xl bg-[#ebb95e] px-4 py-2 text-xs font-bold text-[#241b16] transition hover:bg-[#dca850]">Filtrer</button>
                            <button type="button" onClick={onResetFilters} className="rounded-xl border border-[var(--admin-border)] bg-white/60 px-3 py-2 text-xs font-bold text-[var(--admin-text-soft)] transition hover:bg-white/80">Réinitialiser</button>
                            <ExportButton resource="missions" params={exportParams} />
                            {missionsPage ? (
                                <span className="ml-auto self-center text-[11px] text-[var(--admin-muted)]">
                                    {numberFormat.format(missionsPage.total)} mission(s) • page {missionsPage.current_page}/{missionsPage.last_page}
                                </span>
                            ) : null}
                        </form>

                        <DataTable className="mt-5">
                            <thead>
                                <tr>
                                    <th>Mission</th>
                                    <th>Client / Artisan</th>
                                    <th>Analyse Gemini</th>
                                    <th>Montant</th>
                                    <th>Zone</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filteredMissions.length === 0 ? (
                                    <tr>
                                        <td colSpan={6}>
                                            <EmptyState description="Aucune mission ne correspond à votre recherche." title="Mission introuvable" />
                                        </td>
                                    </tr>
                                ) : (
                                    filteredMissions.map((mission) => (
                                        <tr
                                            key={mission.id}
                                            onClick={() => onSelectMission(mission)}
                                            className="cursor-pointer hover:bg-black/[0.02] transition"
                                        >
                                            <td>
                                                <div className="space-y-2">
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-sm font-semibold text-[var(--admin-text)]">#{mission.id}</span>
                                                        <MissionStatusBadge status={mission.status} />
                                                    </div>
                                                    <p className="max-w-[280px] text-sm text-[var(--admin-text-soft)]">{mission.description}</p>
                                                </div>
                                            </td>
                                            <td>
                                                <div className="space-y-1 text-sm text-[var(--admin-text-soft)]">
                                                    <p>
                                                        <span className="font-medium text-[var(--admin-text)]">Client:</span> {mission.client?.name ?? 'Non renseigné'}
                                                    </p>
                                                    <p>
                                                        <span className="font-medium text-[var(--admin-text)]">Artisan:</span> {mission.artisan?.name ?? 'Non affecté'}
                                                    </p>
                                                </div>
                                            </td>
                                            <td>
                                                <div className="space-y-1 text-sm text-[var(--admin-text-soft)]">
                                                    <p className="font-semibold text-[var(--admin-text)]">{mission.gemini_category ?? 'Non classée'}</p>
                                                    <p>Urgence: {mission.gemini_urgency ?? 'N/A'}</p>
                                                    <p>
                                                        Estimation:{' '}
                                                        {mission.gemini_estimation_min && mission.gemini_estimation_max
                                                            ? `${money(mission.gemini_estimation_min)} - ${money(mission.gemini_estimation_max)}`
                                                            : 'Non disponible'}
                                                    </p>
                                                </div>
                                            </td>
                                            <td className="text-sm font-semibold text-[var(--admin-text)]">
                                                {mission.montant_total ? money(mission.montant_total) : 'Non défini'}
                                            </td>
                                            <td className="text-sm text-[var(--admin-text-soft)]">{mission.client_address ?? 'Adresse non renseignée'}</td>
                                            <td className="text-sm text-[var(--admin-text-soft)]">{shortDate(mission.created_at)}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </DataTable>

                        {renderMissionPagination(missionsPage?.links)}
                    </Surface>
                </>
            ) : (
                <>
                    {/* Métriques KPI Livraisons */}
                    <div className="grid gap-4 xl:grid-cols-4">
                        {deliveryMetrics.map((metric) => (
                            <MetricCard
                                key={metric.title}
                                description={metric.description}
                                tone={metric.tone}
                                trend="Suivi en direct"
                                value={metric.value}
                            >
                                {metric.title}
                            </MetricCard>
                        ))}
                    </div>

                    {/* Filtres d'état des livraisons */}
                    <div className="flex flex-wrap items-center gap-2 pt-1">
                        {deliveryFilters.map((filterItem) => (
                            <button
                                key={filterItem.id}
                                type="button"
                                onClick={() => onDeliveryStatusFilterChange(filterItem.id)}
                                className={cn(
                                    'rounded-full px-4 py-1.5 text-xs font-semibold transition border',
                                    deliveryStatusFilter === filterItem.id
                                        ? 'bg-[#8a6b3d] text-white border-[#8a6b3d] shadow-sm'
                                        : 'bg-white/80 text-[var(--admin-text-soft)] border-[var(--admin-border)] hover:bg-black/5',
                                )}
                            >
                                {filterItem.label} ({filterItem.count})
                            </button>
                        ))}
                    </div>

                    <Surface className="rounded-[32px] p-5 lg:p-6">
                        <SectionTitle
                            description="Traçabilité 360° des courses, expéditions quincailleries et remises sur chantier aux artisans."
                            title="Suivi des Livraisons & Courses Livreurs"
                        />

                        <form onSubmit={onOrderSubmit} className="mt-4 flex flex-wrap gap-2">
                            <input
                                type="text"
                                placeholder="ID, code retrait/réception, client, livreur, fournisseur..."
                                value={orderSearch}
                                onChange={(e) => onOrderSearchChange(e.target.value)}
                                className="w-full max-w-sm rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                            />
                            <button type="submit" className="rounded-xl bg-[#8a6b3d] px-4 py-2 text-xs font-bold text-white transition hover:bg-[#75592f]">Filtrer</button>
                            <button type="button" onClick={onResetFilters} className="rounded-xl border border-[var(--admin-border)] bg-white/60 px-3 py-2 text-xs font-bold text-[var(--admin-text-soft)] transition hover:bg-white/80">Réinitialiser</button>
                            {ordersPage ? (
                                <span className="ml-auto self-center text-[11px] text-[var(--admin-muted)]">
                                    {numberFormat.format(ordersPage.total)} livraison(s) • page {ordersPage.current_page}/{ordersPage.last_page}
                                </span>
                            ) : null}
                        </form>

                        <DataTable className="mt-5">
                            <thead>
                                <tr>
                                    <th>Course / Commande</th>
                                    <th>Livreur (Coursier)</th>
                                    <th>Artisan / Destinataire</th>
                                    <th>Quincaillerie Fournisseur</th>
                                    <th>Matériaux</th>
                                    <th>Frais & Total</th>
                                    <th>Statut & Traçabilité</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filteredOrders.length === 0 ? (
                                    <tr>
                                        <td colSpan={8}>
                                            <EmptyState description="Aucune livraison ne correspond à votre filtre ou recherche." title="Aucune livraison trouvée" />
                                        </td>
                                    </tr>
                                ) : (
                                    filteredOrders.map((order) => (
                                        <tr
                                            key={order.id}
                                            onClick={() => onSelectOrder(order)}
                                            className="cursor-pointer hover:bg-black/[0.02] transition"
                                        >
                                            <td>
                                                <div className="space-y-1.5">
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-sm font-bold text-[var(--admin-text)]">#{order.id}</span>
                                                        <DeliveryModeBadge mode={order.delivery_mode} />
                                                    </div>
                                                    <p className="text-xs text-[var(--admin-muted)]">{shortDate(order.created_at)}</p>
                                                </div>
                                            </td>
                                            <td>
                                                {order.driver ? (
                                                    <div className="space-y-1">
                                                        <p className="text-sm font-bold text-[var(--admin-text)] flex items-center gap-1.5">
                                                            <span>🛵</span>
                                                            <span>{order.driver.name}</span>
                                                        </p>
                                                        <a
                                                            href={`tel:${order.driver.phone}`}
                                                            onClick={(e) => e.stopPropagation()}
                                                            className="text-xs text-[#8a6b3d] hover:underline font-mono"
                                                        >
                                                            📞 {order.driver.phone}
                                                        </a>
                                                    </div>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1.5 rounded-full border border-amber-300 bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-800">
                                                        <span className="inline-block h-2 w-2 animate-ping rounded-full bg-amber-500" />
                                                        En attente d'assignation
                                                    </span>
                                                )}
                                            </td>
                                            <td>
                                                <div className="space-y-1 text-sm text-[var(--admin-text-soft)]">
                                                    <p className="font-bold text-[var(--admin-text)]">
                                                        {order.client?.name ?? 'Non renseigné'}
                                                        {order.client?.role ? (
                                                            <span className="ml-1.5 text-[10px] uppercase font-semibold text-[var(--admin-muted)]">
                                                                ({order.client.role})
                                                            </span>
                                                        ) : null}
                                                    </p>
                                                    <p className="text-xs text-[var(--admin-muted)]">{order.client?.phone}</p>
                                                </div>
                                            </td>
                                            <td>
                                                <div className="space-y-1 text-sm text-[var(--admin-text-soft)]">
                                                    <p className="font-semibold text-[var(--admin-text)]">
                                                        🏪 {order.supplier?.fournisseur_agree?.nom_boutique ?? order.supplier?.name ?? 'Quincaillerie'}
                                                    </p>
                                                    <p className="text-xs text-[var(--admin-muted)]">{order.supplier?.phone}</p>
                                                </div>
                                            </td>
                                            <td>
                                                <div className="space-y-1">
                                                    <span className="rounded-full bg-black/5 px-2 py-0.5 text-xs font-semibold text-[var(--admin-text)]">
                                                        {order.items?.length ?? 0} article(s)
                                                    </span>
                                                    {order.items && order.items.length > 0 && (
                                                        <p className="max-w-[180px] truncate text-[11px] text-[var(--admin-muted)]">
                                                            {order.items.map((i) => i.product?.name ?? 'Article').join(', ')}
                                                        </p>
                                                    )}
                                                </div>
                                            </td>
                                            <td>
                                                <div className="space-y-1 text-xs">
                                                    <p className="font-bold text-[var(--admin-text)]">{money(order.total_amount || order.subtotal)}</p>
                                                    {order.delivery_cost > 0 && (
                                                        <p className="text-[#8a6b3d] font-medium">Livreur: {money(order.delivery_cost)}</p>
                                                    )}
                                                </div>
                                            </td>
                                            <td>
                                                <div className="space-y-1.5">
                                                    <DeliveryStatusBadge status={order.status} />
                                                    <div className="flex flex-wrap gap-1 text-[10px] font-mono text-[var(--admin-muted)]">
                                                        {order.pickup_code ? <span className="bg-white/80 border px-1.5 py-0.5 rounded">R: {order.pickup_code}</span> : null}
                                                        {order.reception_code ? <span className="bg-white/80 border px-1.5 py-0.5 rounded">C: {order.reception_code}</span> : null}
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <button
                                                    type="button"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        onSelectOrder(order);
                                                    }}
                                                    className="rounded-xl border border-[var(--admin-border)] bg-white/80 px-3 py-1.5 text-xs font-bold text-[var(--admin-text)] hover:bg-[#8a6b3d] hover:text-white transition shadow-sm"
                                                >
                                                    Suivi 360°
                                                </button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </DataTable>

                        {renderOrderPagination(ordersPage?.links)}
                    </Surface>
                </>
            )}
        </section>
    );
}
