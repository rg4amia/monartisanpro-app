import { useState } from 'react';
import { router } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import {
    DataTable,
    EmptyState,
    MetricCard,
    money,
    SectionTitle,
    shortDate,
    Surface,
} from '../shared';
import type { GeneratedDocumentItem, DocumentStats, Paginated } from '../shared';

interface DocumentsReportsSectionProps {
    documentsPage?: Paginated<GeneratedDocumentItem>;
    documentStats?: DocumentStats;
}

export function DocumentsReportsSection({
    documentsPage,
    documentStats,
}: DocumentsReportsSectionProps) {
    const documents = documentsPage?.data ?? [];

    const [search, setSearch] = useState('');
    const [typeFilter, setTypeFilter] = useState('all');
    const [selectedDoc, setSelectedDoc] = useState<GeneratedDocumentItem | null>(null);
    const [isSyncing, setIsSyncing] = useState(false);

    const stats = documentStats ?? {
        total_documents: documents.length,
        total_montant_certifie: documents.reduce((sum, d) => sum + (d.montant || 0), 0),
        recus_jalons_mo: documents.filter((d) => d.document_type === 'recu_liberation_jalon').length,
        recus_quincaillerie: documents.filter((d) => ['recu_paiement_fournisseur', 'recu_cashout'].includes(d.document_type)).length,
        rapports_et_litiges: documents.filter((d) => ['facture_litige', 'rapport_solvabilite'].includes(d.document_type)).length,
    };

    // Client-side filtering as complement to server-side query
    const filteredDocs = documents.filter((doc) => {
        if (typeFilter !== 'all' && doc.document_type !== typeFilter) {
            return false;
        }
        if (search.trim()) {
            const q = search.toLowerCase();
            const matchRef = doc.reference.toLowerCase().includes(q);
            const matchTitle = doc.title.toLowerCase().includes(q);
            const matchUser = doc.user?.name?.toLowerCase().includes(q) || doc.user?.phone?.includes(q);
            const matchMission = doc.mission_id?.toString().includes(q);
            if (!matchRef && !matchTitle && !matchUser && !matchMission) {
                return false;
            }
        }
        return true;
    });

    const handleSync = () => {
        setIsSyncing(true);
        router.post('/admin/documents/sync', {}, {
            preserveScroll: true,
            onFinish: () => setIsSyncing(false),
        });
    };

    const getTypeBadge = (type: string) => {
        switch (type) {
            case 'recu_liberation_jalon':
                return { label: 'Reçu Jalon MO', bg: 'bg-emerald-100 text-emerald-800 border-emerald-300', icon: '👷' };
            case 'recu_paiement_fournisseur':
                return { label: 'Règlement Matériaux', bg: 'bg-amber-100 text-amber-800 border-amber-300', icon: '🏬' };
            case 'recu_cashout':
                return { label: 'Cash-Out Quincaillerie', bg: 'bg-orange-100 text-orange-800 border-orange-300', icon: '💵' };
            case 'facture_litige':
                return { label: 'Facture Arbitrage', bg: 'bg-rose-100 text-rose-800 border-rose-300', icon: '⚖️' };
            case 'rapport_solvabilite':
                return { label: 'Rapport Solvabilité', bg: 'bg-blue-100 text-blue-800 border-blue-300', icon: '📊' };
            case 'recu_remboursement':
                return { label: 'Remboursement', bg: 'bg-purple-100 text-purple-800 border-purple-300', icon: '🔄' };
            case 'recu_acompte':
                return { label: 'Acompte Séquestre', bg: 'bg-teal-100 text-teal-800 border-teal-300', icon: '🛡️' };
            default:
                return { label: 'Pièce Comptable', bg: 'bg-slate-100 text-slate-800 border-slate-300', icon: '📄' };
        }
    };

    return (
        <div className="space-y-6">
            {/* 1. SECTION TITRE & ACTION RAPIDE */}
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 className="text-xl font-black text-[var(--admin-text)] flex items-center gap-2">
                        <span>📑</span>
                        <span>Visibilité des Reçus de Décaissement & Rapports Officiels</span>
                    </h2>
                    <p className="text-xs text-[var(--admin-text-soft)] mt-1">
                        Registre centralisé des pièces comptables, reçus de libération de fonds (artisans & quincailleries),
                        factures d'arbitrage et rapports de solvabilité certifiés.
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={handleSync}
                        disabled={isSyncing}
                        className={cn(
                            'inline-flex items-center gap-2 px-4 py-2 text-xs font-bold rounded-2xl border transition shadow-sm',
                            isSyncing
                                ? 'bg-slate-100 text-slate-400 border-slate-200 cursor-not-allowed'
                                : 'bg-white hover:bg-slate-50 text-[var(--admin-text)] border-[var(--admin-border)]'
                        )}
                    >
                        <span className={cn('transition-transform', isSyncing && 'animate-spin')}>🔄</span>
                        <span>{isSyncing ? 'Synchronisation...' : 'Synchroniser les pièces'}</span>
                    </button>
                </div>
            </div>

            {/* 2. KPI CARDS */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <MetricCard
                    title="Total Pièces Émises"
                    value={stats.total_documents.toString()}
                    description="Reçus de libération et rapports archivés"
                    tone="slate"
                />
                <MetricCard
                    title="Volume Certifié Décaissé"
                    value={money(stats.total_montant_certifie)}
                    description="Fonds libérés des séquestres et versés"
                    tone="green"
                />
                <MetricCard
                    title="Reçus Jalons & Quincailleries"
                    value={(stats.recus_jalons_mo + stats.recus_quincaillerie).toString()}
                    description={`${stats.recus_jalons_mo} jalons MO • ${stats.recus_quincaillerie} matériaux/cashouts`}
                    tone="amber"
                />
                <MetricCard
                    title="Rapports & Arbitrages"
                    value={stats.rapports_et_litiges.toString()}
                    description="Rapports de solvabilité et factures litige"
                    tone="blue"
                />
            </div>

            {/* 3. FILTRES & RECHERCHE */}
            <Surface className="rounded-[28px] p-4 lg:p-5 border border-[var(--admin-border)] bg-white/70 space-y-4">
                <div className="flex flex-col md:flex-row gap-3 items-center justify-between">
                    {/* Recherche */}
                    <div className="w-full md:w-80 relative">
                        <span className="absolute left-3.5 top-2.5 text-xs text-slate-400">🔍</span>
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Rechercher réf, artisan, client, tél, mission..."
                            className="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-[var(--admin-border)] bg-white focus:outline-none focus:ring-2 focus:ring-[#ebb95e]"
                        />
                        {search && (
                            <button
                                type="button"
                                onClick={() => setSearch('')}
                                className="absolute right-3 top-2 text-xs text-slate-400 hover:text-slate-600"
                            >
                                ✕
                            </button>
                        )}
                    </div>

                    {/* Filtre par type */}
                    <div className="flex items-center gap-2 w-full md:w-auto overflow-x-auto pb-1 md:pb-0">
                        <span className="text-[11px] font-bold text-[var(--admin-text-soft)] whitespace-nowrap">Type :</span>
                        <select
                            value={typeFilter}
                            onChange={(e) => setTypeFilter(e.target.value)}
                            className="text-xs rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#ebb95e]"
                        >
                            <option value="all">Tous les documents ({documents.length})</option>
                            <option value="recu_liberation_jalon">Reçus Libération Jalon (Artisans)</option>
                            <option value="recu_paiement_fournisseur">Règlements Matériaux (Quincailleries)</option>
                            <option value="recu_cashout">Bordereaux Cash-Out</option>
                            <option value="facture_litige">Factures d'Arbitrage Litige</option>
                            <option value="rapport_solvabilite">Rapports de Solvabilité</option>
                            <option value="recu_remboursement">Remboursements</option>
                            <option value="recu_acompte">Acomptes Séquestre</option>
                        </select>
                    </div>
                </div>

                {/* 4. TABLE DES DOCUMENTS */}
                {filteredDocs.length === 0 ? (
                    <EmptyState
                        description="Aucune pièce ou reçu ne correspond à vos critères de recherche. Cliquez sur « Synchroniser les pièces » pour scanner les transactions."
                        title="Aucun document répertorié"
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr className="border-b border-[var(--admin-border)] text-[var(--admin-text-soft)] font-bold text-[10px] uppercase tracking-wider bg-slate-50/60">
                                    <th className="py-3 px-3">Réf & Type</th>
                                    <th className="py-3 px-3">Bénéficiaire / Acteur</th>
                                    <th className="py-3 px-3">Mission / Entité</th>
                                    <th className="py-3 px-3 text-right">Montant Certifié</th>
                                    <th className="py-3 px-3">Date d'Émission</th>
                                    <th className="py-3 px-3 text-center">Format</th>
                                    <th className="py-3 px-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--admin-border)]">
                                {filteredDocs.map((doc) => {
                                    const badge = getTypeBadge(doc.document_type);
                                    return (
                                        <tr key={doc.id} className="hover:bg-slate-50/80 transition group">
                                            <td className="py-3 px-3">
                                                <div className="flex items-center gap-2">
                                                    <span className="text-base">{badge.icon}</span>
                                                    <div>
                                                        <div className="font-mono font-bold text-[var(--admin-text)]">
                                                            {doc.reference}
                                                        </div>
                                                        <span className={cn('inline-block text-[9px] font-extrabold px-2 py-0.5 rounded-full border mt-0.5', badge.bg)}>
                                                            {badge.label}
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="py-3 px-3">
                                                <div className="font-bold text-[var(--admin-text)]">
                                                    {doc.user?.name ?? doc.metadata?.beneficiary_name ?? 'N/A'}
                                                </div>
                                                <div className="text-[10px] text-[var(--admin-text-soft)] font-mono">
                                                    {doc.user?.phone ?? doc.metadata?.beneficiary_phone ?? '—'}
                                                </div>
                                            </td>
                                            <td className="py-3 px-3">
                                                {doc.mission_id ? (
                                                    <div>
                                                        <span className="font-bold text-slate-700">Mission #{doc.mission_id}</span>
                                                        {doc.mission?.status && (
                                                            <div className="text-[10px] text-slate-400 capitalize">
                                                                {doc.mission.status}
                                                            </div>
                                                        )}
                                                    </div>
                                                ) : doc.supplier_cashout_id ? (
                                                    <span className="text-slate-600 font-semibold">Cash-Out #{doc.supplier_cashout_id}</span>
                                                ) : doc.litige_id ? (
                                                    <span className="text-rose-600 font-semibold">Litige #{doc.litige_id}</span>
                                                ) : (
                                                    <span className="text-slate-400">—</span>
                                                )}
                                            </td>
                                            <td className="py-3 px-3 text-right font-mono font-bold">
                                                {doc.montant > 0 ? (
                                                    <span className="text-emerald-700">{money(doc.montant)}</span>
                                                ) : (
                                                    <span className="text-slate-400">—</span>
                                                )}
                                            </td>
                                            <td className="py-3 px-3 text-[11px] text-[var(--admin-text-soft)] whitespace-nowrap">
                                                {shortDate(doc.created_at)}
                                            </td>
                                            <td className="py-3 px-3 text-center">
                                                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-rose-50 text-rose-700 text-[10px] font-bold border border-rose-200">
                                                    <span>PDF</span>
                                                </span>
                                            </td>
                                            <td className="py-3 px-3 text-right">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    <button
                                                        type="button"
                                                        onClick={() => setSelectedDoc(doc)}
                                                        className="px-2.5 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-100 text-[11px] font-bold text-slate-700 transition"
                                                        title="Voir les métadonnées"
                                                    >
                                                        👁️ Aperçu
                                                    </button>
                                                    <a
                                                        href={`/admin/documents/${doc.id}/download`}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="px-2.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold transition shadow-sm inline-flex items-center gap-1"
                                                        title="Télécharger le PDF officiel"
                                                    >
                                                        <span>📥</span>
                                                        <span>PDF</span>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </Surface>

            {/* 5. MODAL DE DÉTAIL / APERÇU DU DOCUMENT */}
            {selectedDoc && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm animate-fade-in">
                    <div className="relative w-full max-w-lg bg-white rounded-3xl shadow-2xl border border-[var(--admin-border)] overflow-hidden">
                        {/* Header Modal */}
                        <div className="flex items-center justify-between p-5 border-b border-[var(--admin-border)] bg-gradient-to-r from-slate-50 to-[#fdfaf5]">
                            <div className="flex items-center gap-3">
                                <span className="text-2xl">{getTypeBadge(selectedDoc.document_type).icon}</span>
                                <div>
                                    <h3 className="text-sm font-black text-[var(--admin-text)]">
                                        {selectedDoc.reference}
                                    </h3>
                                    <p className="text-[11px] text-[var(--admin-text-soft)]">
                                        {selectedDoc.title}
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setSelectedDoc(null)}
                                className="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold flex items-center justify-center text-xs transition"
                            >
                                ✕
                            </button>
                        </div>

                        {/* Content Modal */}
                        <div className="p-5 space-y-4 text-xs">
                            <div className="grid grid-cols-2 gap-3 p-3 bg-slate-50 rounded-2xl border border-slate-100">
                                <div>
                                    <span className="text-[10px] text-slate-400 font-bold uppercase block">Bénéficiaire</span>
                                    <span className="font-bold text-slate-800 text-xs">
                                        {selectedDoc.user?.name ?? selectedDoc.metadata?.beneficiary_name ?? 'N/A'}
                                    </span>
                                    <span className="text-[10px] text-slate-500 block font-mono">
                                        {selectedDoc.user?.phone ?? selectedDoc.metadata?.beneficiary_phone ?? '—'}
                                    </span>
                                </div>
                                <div>
                                    <span className="text-[10px] text-slate-400 font-bold uppercase block">Montant Certifié</span>
                                    <span className="font-bold text-emerald-700 text-sm font-mono">
                                        {selectedDoc.montant > 0 ? money(selectedDoc.montant) : '0 FCFA'}
                                    </span>
                                    <span className="text-[10px] text-slate-400 block">Fonds débloqués</span>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <div className="flex justify-between py-1.5 border-b border-slate-100">
                                    <span className="text-slate-500">Type de document</span>
                                    <span className="font-bold text-slate-800">{getTypeBadge(selectedDoc.document_type).label}</span>
                                </div>
                                <div className="flex justify-between py-1.5 border-b border-slate-100">
                                    <span className="text-slate-500">Mission ID</span>
                                    <span className="font-bold text-slate-800">{selectedDoc.mission_id ? `#${selectedDoc.mission_id}` : '—'}</span>
                                </div>
                                <div className="flex justify-between py-1.5 border-b border-slate-100">
                                    <span className="text-slate-500">Transaction ID</span>
                                    <span className="font-mono font-bold text-slate-800">{selectedDoc.transaction_id ? `#${selectedDoc.transaction_id}` : '—'}</span>
                                </div>
                                <div className="flex justify-between py-1.5 border-b border-slate-100">
                                    <span className="text-slate-500">Date d'émission</span>
                                    <span className="font-bold text-slate-800">{shortDate(selectedDoc.created_at)}</span>
                                </div>
                                {selectedDoc.metadata?.reference_externe && (
                                    <div className="flex justify-between py-1.5 border-b border-slate-100">
                                        <span className="text-slate-500">Réf. Externe (Opérateur)</span>
                                        <span className="font-mono text-slate-700 font-semibold">{selectedDoc.metadata.reference_externe}</span>
                                    </div>
                                )}
                            </div>

                            <div className="p-3 bg-emerald-50/60 border border-emerald-200 rounded-2xl text-[11px] text-emerald-900 flex items-start gap-2">
                                <span>🔒</span>
                                <div>
                                    <strong>Document Officiel ProsArtisan CI</strong>
                                    <p className="text-[10px] text-emerald-800/80 mt-0.5">
                                        Ce justificatif a été validé sous le protocole de séquestre décentralisé et comporte un cachet d'intégrité numérique non falsifiable.
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Footer Modal */}
                        <div className="flex items-center justify-end gap-2 p-4 border-t border-[var(--admin-border)] bg-slate-50/80">
                            <button
                                type="button"
                                onClick={() => setSelectedDoc(null)}
                                className="px-4 py-2 text-xs font-bold text-slate-600 hover:text-slate-800"
                            >
                                Fermer
                            </button>
                            <a
                                href={`/admin/documents/${selectedDoc.id}/download`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="px-4 py-2 text-xs font-bold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm inline-flex items-center gap-1.5 transition"
                            >
                                <span>📥</span>
                                <span>Télécharger le Reçu PDF</span>
                            </a>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
