// Onglet « Transactions » du backoffice.
// Chantier C2 : découpe de console.tsx. Chantier C4 (P1-6) : journal financier paginé + filtres serveur.

import type { FormEvent, ReactNode } from 'react';

import {
    DataTable,
    EmptyState,
    ExportButton,
    MetricCard,
    money,
    numberFormat,
    ProviderBadge,
    providerLabels,
    SectionTitle,
    shortDate,
    Surface,
    TransactionStatusBadge,
    transactionTypeLabels,
} from '../shared';
import type { AdminTransaction, Paginated, TransactionStats } from '../shared';

interface TransactionsPanelProps {
    // Agrégats renvoyés par AdminService::getFinancialKpis() — structure dynamique.
    financialKpis: any;
    transactionStats: TransactionStats;
    transactionsPage: Paginated<AdminTransaction> | undefined;
    search: string;
    onSearchChange: (value: string) => void;
    statusFilter: string;
    onStatusFilterChange: (value: string) => void;
    typeFilter: string;
    onTypeFilterChange: (value: string) => void;
    providerFilter: string;
    onProviderFilterChange: (value: string) => void;
    onSubmit: (event: FormEvent) => void;
    onReset: () => void;
    exportParams: Record<string, string>;
    renderPagination: (links: Paginated<AdminTransaction>['links'] | undefined) => ReactNode;
    onSelectTransaction: (transaction: AdminTransaction) => void;
}

export function TransactionsPanel({
    financialKpis,
    transactionStats,
    transactionsPage,
    search,
    onSearchChange,
    statusFilter,
    onStatusFilterChange,
    typeFilter,
    onTypeFilterChange,
    providerFilter,
    onProviderFilterChange,
    onSubmit,
    onReset,
    exportParams,
    renderPagination,
    onSelectTransaction,
}: TransactionsPanelProps) {
    const rows = transactionsPage?.data ?? [];
    return (
        <section className="mt-5 space-y-6">
            {/* 1. SOLDE GÉNÉRAL & COMPTE FINANCIER PROSARTISAN */}
            <Surface className="rounded-[32px] p-5 lg:p-6 border border-[#e2d5c3]/60 bg-gradient-to-br from-white via-[#fcfaf7] to-[#f7f2ea]">
                <SectionTitle
                    description="Solde global des commissions perçues, fonds séquestrés en cours et total distribué."
                    title="Solde Général & Compte Financier ProsArtisan"
                />
                <div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <MetricCard description="Commission cumulée (Chantiers + E-commerce)" tone="amber" trend="Revenus ProsArtisan" value={money(financialKpis?.solde_general?.total_commissions_cumulees ?? 0)}>
                        Solde Commissions
                    </MetricCard>
                    <MetricCard description="Main-d'œuvre réservée jalons" tone="blue" trend="Séquestre MO" value={money(financialKpis?.solde_general?.sequestre_mo_encours ?? transactionStats.escrow)}>
                        Encours Séquestre MO
                    </MetricCard>
                    <MetricCard description="Achats matériaux en attente scan" tone="blue" trend="Séquestre Matériaux" value={money(financialKpis?.solde_general?.sequestre_materiaux_encours ?? 0)}>
                        Encours Matériaux
                    </MetricCard>
                    <MetricCard description="Artisans, fournisseurs et livreurs payés" tone="green" trend="Total Distribué" value={money(financialKpis?.solde_general?.total_libere_general ?? transactionStats.released)}>
                        Total Libéré
                    </MetricCard>
                </div>
            </Surface>

            {/* 2. KPIS RECOMMANDÉS PAR PROSARTISAN */}
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <MetricCard description="Ratio missions entrées en arbitrage" tone={(financialKpis?.additional_kpis?.dispute_rate_percent ?? 0) > 5 ? 'rose' : 'green'} trend="Qualité réseau" value={`${financialKpis?.additional_kpis?.dispute_rate_percent ?? 0}%`}>
                    Taux de Litiges
                </MetricCard>
                <MetricCard description="Ratio devis validés par les clients" tone="amber" trend="Conversion Devis" value={`${financialKpis?.additional_kpis?.devis_conversion_rate_percent ?? 0}%`}>
                    Taux de Conversion Devis
                </MetricCard>
                <MetricCard description="Montant moyen par chantier validé" tone="blue" trend="AOV Chantier" value={money(financialKpis?.additional_kpis?.aov_chantier ?? 0)}>
                    Panier Moyen Chantier
                </MetricCard>
                <MetricCard description="Montant moyen par commande quincaillerie" tone="blue" trend="AOV Matériaux" value={money(financialKpis?.additional_kpis?.aov_ecommerce ?? 0)}>
                    Panier Moyen Matériaux
                </MetricCard>
            </div>

            {/* 3. COMMISSIONS PAR CATÉGORIE DE MÉTIER ET PAR ANNÉE */}
            <Surface className="rounded-[32px] p-5 lg:p-6">
                <SectionTitle
                    description="Répartition des commissions de service perçues par secteur d'activité et par année."
                    title="Commissions par Catégorie de Métier & Année"
                />
                <DataTable className="mt-5">
                    <thead>
                        <tr>
                            <th>Catégorie de Métier</th>
                            <th>Année</th>
                            <th>Missions Terminées</th>
                            <th>Volume Brut Travaux</th>
                            <th>Commission Nette ProsArtisan</th>
                        </tr>
                    </thead>
                    <tbody>
                        {(!financialKpis?.commissions_by_category_year || financialKpis.commissions_by_category_year.length === 0) ? (
                            <tr>
                                <td colSpan={5}>
                                    <EmptyState description="Aucune commission enregistrée par catégorie pour le moment." title="Aucune donnée métier" />
                                </td>
                            </tr>
                        ) : (
                            financialKpis.commissions_by_category_year.map((row: any, idx: number) => (
                                <tr key={idx} className="hover:bg-black/[0.02] transition">
                                    <td className="font-semibold text-[var(--admin-text)]">{row.category}</td>
                                    <td>
                                        <span className="inline-flex items-center rounded-md bg-[#8a6b3d]/10 px-2.5 py-1 text-xs font-bold text-[#8a6b3d]">
                                            {row.year}
                                        </span>
                                    </td>
                                    <td className="text-sm text-[var(--admin-text-soft)]">{row.missions_count} mission(s)</td>
                                    <td className="text-sm font-semibold text-[var(--admin-text)]">{money(row.volume_brut)}</td>
                                    <td className="text-sm font-bold text-[#2e7d32]">{money(row.commission_net)}</td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </DataTable>
            </Surface>

            {/* 4. COMMISSIONS ET VOLUME PAR FOURNISSEUR & LIVREURS */}
            <div className="grid gap-6 xl:grid-cols-2">
                {/* TABLEAU FOURNISSEURS */}
                <Surface className="rounded-[32px] p-5 lg:p-6">
                    <SectionTitle
                        description="Volume d'affaires matériaux et commissions générées par quincaillerie."
                        title="Commissions par Fournisseur"
                    />
                    <DataTable className="mt-5">
                        <thead>
                            <tr>
                                <th>Boutique</th>
                                <th>Commandes</th>
                                <th>Volume Matériaux</th>
                                <th>Commission 3%</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(!financialKpis?.commissions_by_supplier || financialKpis.commissions_by_supplier.length === 0) ? (
                                <tr>
                                    <td colSpan={4}>
                                        <EmptyState description="Aucune commande fournisseur enregistrée." title="Aucun fournisseur" />
                                    </td>
                                </tr>
                            ) : (
                                financialKpis.commissions_by_supplier.map((sup: any) => (
                                    <tr key={sup.supplier_id} className="hover:bg-black/[0.02] transition">
                                        <td>
                                            <p className="font-semibold text-[var(--admin-text)]">{sup.shop_name}</p>
                                            <p className="text-xs text-[var(--admin-muted)]">{sup.supplier_phone}</p>
                                        </td>
                                        <td className="text-sm text-[var(--admin-text-soft)]">{sup.orders_count} commande(s)</td>
                                        <td className="text-sm font-semibold text-[var(--admin-text)]">{money(sup.volume_materiaux)}</td>
                                        <td className="text-sm font-bold text-[#8a6b3d]">{money(sup.commission_prosartisan)}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </DataTable>
                </Surface>

                {/* TABLEAU LIVREURS */}
                <Surface className="rounded-[32px] p-5 lg:p-6">
                    <SectionTitle
                        description="Volume de livraison et frais distribués aux livreurs."
                        title="Activité & Frais par Livreur"
                    />
                    <DataTable className="mt-5">
                        <thead>
                            <tr>
                                <th>Livreur</th>
                                <th>Livraisons</th>
                                <th>Frais Générés</th>
                                <th>Gains Libérés</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(!financialKpis?.commissions_by_driver || financialKpis.commissions_by_driver.length === 0) ? (
                                <tr>
                                    <td colSpan={4}>
                                        <EmptyState description="Aucun livreur enregistré ou course effectuée." title="Aucun livreur" />
                                    </td>
                                </tr>
                            ) : (
                                financialKpis.commissions_by_driver.map((drv: any) => (
                                    <tr key={drv.driver_id} className="hover:bg-black/[0.02] transition">
                                        <td>
                                            <p className="font-semibold text-[var(--admin-text)]">{drv.driver_name}</p>
                                            <p className="text-xs text-[var(--admin-muted)]">{drv.driver_phone}</p>
                                        </td>
                                        <td className="text-sm text-[var(--admin-text-soft)]">{drv.deliveries_count} course(s)</td>
                                        <td className="text-sm font-semibold text-[var(--admin-text)]">{money(drv.total_frais_livraison)}</td>
                                        <td className="text-sm font-bold text-[#2e7d32]">{money(drv.gains_livreur_liberes)}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </DataTable>
                </Surface>
            </div>

            {/* 5. JOURNAL FINANCIER DES TRANSACTIONS */}
            <Surface className="rounded-[32px] p-5 lg:p-6">
                <SectionTitle
                    description="Flux Wave, Orange Money et remboursements classés par statut."
                    title="Journal financier des transactions"
                />

                <div className="mt-4 flex flex-wrap gap-2 text-xs">
                    <span className="rounded-full border border-emerald-300 bg-emerald-100 px-3 py-1 font-semibold text-emerald-700">
                        Confirmées : {numberFormat.format(transactionStats.confirmed)}
                    </span>
                    <span className="rounded-full border border-blue-300 bg-blue-100 px-3 py-1 font-semibold text-blue-700">
                        En attente : {numberFormat.format(transactionStats.pending)}
                    </span>
                    <span className="rounded-full border border-rose-300 bg-rose-100 px-3 py-1 font-semibold text-rose-700">
                        Échouées : {numberFormat.format(transactionStats.failed)}
                    </span>
                    <span className="rounded-full border border-amber-300 bg-amber-100 px-3 py-1 font-semibold text-amber-700">
                        Volume 24 h : {money(transactionStats.volume_24h)}
                    </span>
                </div>

                <form onSubmit={onSubmit} className="mt-4 grid items-end gap-3 rounded-2xl border border-[var(--admin-border)] bg-white/40 p-4 md:grid-cols-5">
                    <div className="md:col-span-2">
                        <label className="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)]">Rechercher</label>
                        <input
                            type="text"
                            placeholder="ID, référence, wallet, bénéficiaire..."
                            value={search}
                            onChange={(e) => onSearchChange(e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label className="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)]">Statut</label>
                        <select value={statusFilter} onChange={(e) => onStatusFilterChange(e.target.value)} className="w-full rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none">
                            <option value="">Tous</option>
                            <option value="confirme">Confirmée</option>
                            <option value="en_attente">En attente</option>
                            <option value="echoue">Échouée</option>
                        </select>
                    </div>
                    <div>
                        <label className="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)]">Type</label>
                        <select value={typeFilter} onChange={(e) => onTypeFilterChange(e.target.value)} className="w-full rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none">
                            <option value="">Tous</option>
                            {Object.entries(transactionTypeLabels).map(([value, label]) => (
                                <option key={value} value={value}>{label}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)]">Provider</label>
                        <select value={providerFilter} onChange={(e) => onProviderFilterChange(e.target.value)} className="w-full rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none">
                            <option value="">Tous</option>
                            {Object.entries(providerLabels).map(([value, label]) => (
                                <option key={value} value={value}>{label}</option>
                            ))}
                        </select>
                    </div>
                    <div className="flex gap-2 md:col-span-5">
                        <button type="submit" className="rounded-xl bg-[#ebb95e] px-4 py-2 text-xs font-bold text-[#241b16] transition hover:bg-[#dca850]">Filtrer</button>
                        <button type="button" onClick={onReset} className="rounded-xl border border-[var(--admin-border)] bg-white/60 px-3 py-2 text-xs font-bold text-[var(--admin-text-soft)] transition hover:bg-white/80">Réinitialiser</button>
                        <ExportButton resource="transactions" params={exportParams} />
                        {transactionsPage ? (
                            <span className="ml-auto self-center text-[11px] text-[var(--admin-muted)]">
                                {numberFormat.format(transactionsPage.total)} flux • page {transactionsPage.current_page}/{transactionsPage.last_page}
                            </span>
                        ) : null}
                    </div>
                </form>

                <DataTable className="mt-5">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Montant</th>
                            <th>Provider</th>
                            <th>Statut</th>
                            <th>Bénéficiaire</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 ? (
                            <tr>
                                <td colSpan={7}>
                                    <EmptyState description="Aucune transaction ne correspond à votre recherche." title="Aucun flux trouvé" />
                                </td>
                            </tr>
                        ) : (
                            rows.map((transaction) => (
                                <tr
                                    key={transaction.id}
                                    onClick={() => onSelectTransaction(transaction)}
                                    className="cursor-pointer hover:bg-black/[0.02] transition"
                                >
                                    <td className="font-semibold text-[var(--admin-text)]">#{transaction.id}</td>
                                    <td className="text-sm text-[var(--admin-text-soft)]">{transactionTypeLabels[transaction.type] ?? transaction.type}</td>
                                    <td className="text-sm font-semibold text-[var(--admin-text)]">{money(transaction.montant)}</td>
                                    <td>
                                        <ProviderBadge provider={transaction.provider} />
                                    </td>
                                    <td>
                                        <TransactionStatusBadge status={transaction.statut} />
                                    </td>
                                    <td className="text-sm text-[var(--admin-text-soft)]">{transaction.user?.name ?? 'Non renseigné'}</td>
                                    <td className="text-sm text-[var(--admin-text-soft)]">{shortDate(transaction.created_at)}</td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </DataTable>

                {renderPagination(transactionsPage?.links)}
            </Surface>
        </section>
    );
}
