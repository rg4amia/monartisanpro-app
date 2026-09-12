// Section « Cash-Out Quincaillerie & Trésorerie 360° » du backoffice admin.
// Permet de piloter la commission quincaillerie sur les retraits cash,
// de visualiser les flux Entrées/Sorties réels et les fonds bloqués sur litiges.

import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    DataTable,
    EmptyState,
    MetricCard,
    money,
    SectionTitle,
    Surface,
} from '../shared';

interface CashoutItem {
    id: number;
    reference: string;
    supplier_id: number;
    supplier_name: string;
    supplier_phone: string;
    beneficiary_name: string;
    beneficiary_phone: string;
    montant_brut: number;
    commission_rate: number;
    montant_commission: number;
    montant_net: number;
    statut: 'en_attente' | 'approuve' | 'complete' | 'rejete';
    mode_retrait: string;
    created_at?: string;
}

interface CashoutQuincaillerieSectionProps {
    financialKpis: any;
    settingsList?: any[];
    suppliers?: Array<{ id: number; name: string; phone: string }>;
}

export function CashoutQuincaillerieSection({
    financialKpis,
    settingsList = [],
    suppliers = [],
}: CashoutQuincaillerieSectionProps) {
    const solde = financialKpis?.solde_general ?? {};
    const cashoutKpis = financialKpis?.cashout_kpis ?? {};
    const cashoutsBySupplier = financialKpis?.cashouts_by_supplier ?? [];
    const recentCashouts: CashoutItem[] = financialKpis?.recent_cashouts ?? [];

    // Paramètre de commission dans les settings
    const cashoutSetting = settingsList.find((s) => s.key === 'commission_cashout_quincaillerie');
    const [rateInput, setRateInput] = useState<string>(
        cashoutSetting ? (parseFloat(cashoutSetting.value) * 100).toString() : (cashoutKpis.commission_rate_percent ?? 2.5).toString()
    );
    const [isUpdatingRate, setIsUpdatingRate] = useState(false);

    // Simulateur de calcul
    const [simulatedAmount, setSimulatedAmount] = useState<number>(50000);
    const currentRate = parseFloat(rateInput) || 2.5;
    const simCommission = Math.round(simulatedAmount * (currentRate / 100));
    const simNet = Math.max(0, simulatedAmount - simCommission);

    // Modal nouveau cash-out
    const [showNewModal, setShowNewModal] = useState(false);
    const [newSupplierId, setNewSupplierId] = useState('');
    const [newBeneficiary, setNewBeneficiary] = useState('');
    const [newPhone, setNewPhone] = useState('');
    const [newAmount, setNewAmount] = useState('');
    const [newMode, setNewMode] = useState('especes_guichet');
    const [newNotes, setNewNotes] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleUpdateRate = (e: React.FormEvent) => {
        e.preventDefault();
        if (!cashoutSetting) return;

        const numericRate = parseFloat(rateInput);
        if (isNaN(numericRate) || numericRate < 0 || numericRate > 100) {
            alert('Veuillez renseigner un pourcentage valide (ex: 2.5)');
            return;
        }

        setIsUpdatingRate(true);
        router.put(
            `/admin/settings/${cashoutSetting.id}`,
            { value: (numericRate / 100).toFixed(4) },
            {
                preserveScroll: true,
                onFinish: () => setIsUpdatingRate(false),
            }
        );
    };

    const handleCreateCashout = (e: React.FormEvent) => {
        e.preventDefault();
        if (!newSupplierId || !newBeneficiary || !newPhone || !newAmount) {
            alert('Veuillez remplir tous les champs obligatoires.');
            return;
        }

        setIsSubmitting(true);
        router.post(
            '/admin/cashouts',
            {
                supplier_id: newSupplierId,
                beneficiary_name: newBeneficiary,
                beneficiary_phone: newPhone,
                montant_brut: parseInt(newAmount, 10),
                mode_retrait: newMode,
                notes: newNotes,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setShowNewModal(false);
                    setNewSupplierId('');
                    setNewBeneficiary('');
                    setNewPhone('');
                    setNewAmount('');
                    setNewNotes('');
                },
                onFinish: () => setIsSubmitting(false),
            }
        );
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'complete':
                return <span className="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">Complété</span>;
            case 'approuve':
                return <span className="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-800">Approuvé</span>;
            case 'rejete':
                return <span className="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-800">Rejeté</span>;
            default:
                return <span className="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">En attente</span>;
        }
    };

    return (
        <div className="space-y-6">
            {/* 1. CARTES TRÉSORERIE & FONDS BLOQUÉS SUR LITIGES */}
            <Surface className="rounded-[32px] p-5 lg:p-6 border border-[#e2d5c3]/60 bg-gradient-to-br from-white via-[#fcfaf7] to-[#f4ebe1]">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <SectionTitle
                        description="Bilan financier consolidé : entrées, sorties réelles, et fonds séquestrés sous litige."
                        title="Trésorerie Globale & Séquestres Bloqués"
                    />
                    <button
                        type="button"
                        onClick={() => setShowNewModal(true)}
                        className="admin-button admin-button--primary text-xs py-2 px-4 shrink-0 rounded-xl"
                    >
                        + Enregistrer un Retrait Cash
                    </button>
                </div>

                <div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <MetricCard
                        description="Acomptes chantiers + commandes"
                        tone="green"
                        trend="Flux Entrants (Inflows)"
                        value={money(solde?.total_entrees ?? 0)}
                    >
                        Total Entrées
                    </MetricCard>

                    <MetricCard
                        description="Jalons payés, factures & cash-outs"
                        tone="rose"
                        trend="Flux Sortants (Outflows)"
                        value={money(solde?.total_sorties ?? 0)}
                    >
                        Total Sorties
                    </MetricCard>

                    <MetricCard
                        description={`${solde?.missions_bloquees_litiges_count ?? 0} chantier(s) en arbitrage`}
                        tone="rose"
                        trend="Séquestre Gelé"
                        value={money(solde?.sequestre_bloque_litiges ?? 0)}
                    >
                        Bloqué sur Litiges 🔒
                    </MetricCard>

                    <MetricCard
                        description="Commissions allouées aux quincailleries"
                        tone="amber"
                        trend="Partenaires Relais"
                        value={money(cashoutKpis?.total_commissions_quincailleries ?? 0)}
                    >
                        Commissions Cash-Out
                    </MetricCard>
                </div>
            </Surface>

            {/* 2. PILOTAGE DU POURCENTAGE DE COMMISSION & SIMULATEUR */}
            <div className="grid gap-6 lg:grid-cols-2">
                {/* Configuration du % de commission */}
                <Surface className="rounded-[32px] p-5 lg:p-6">
                    <SectionTitle
                        description="Pourcentage accordé aux quincailleries sur chaque retrait cash effectué à leur guichet."
                        title="Commission Quincaillerie (Retraits Cash)"
                    />
                    <form onSubmit={handleUpdateRate} className="mt-5 space-y-4">
                        <div className="flex items-center gap-3">
                            <div className="relative flex-1">
                                <input
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    max="50"
                                    value={rateInput}
                                    onChange={(e) => setRateInput(e.target.value)}
                                    className="admin-input w-full rounded-2xl px-4 py-3 text-lg font-bold text-[var(--admin-text)] outline-none border border-[var(--admin-border)]"
                                    placeholder="Ex: 2.5"
                                />
                                <span className="absolute right-4 top-1/2 -translate-y-1/2 text-sm font-semibold text-[var(--admin-muted)]">
                                    %
                                </span>
                            </div>
                            <button
                                type="submit"
                                disabled={isUpdatingRate}
                                className="admin-button admin-button--primary py-3 px-5 rounded-2xl text-xs font-semibold shrink-0"
                            >
                                {isUpdatingRate ? 'Mise à jour...' : 'Appliquer'}
                            </button>
                        </div>
                        <p className="text-xs text-[var(--admin-muted)] leading-relaxed">
                            Ce pourcentage est automatiquement prélevé sur le montant brut du retrait pour rétribuer la quincaillerie partenaire tenant lieu de guichet relais.
                        </p>
                    </form>
                </Surface>

                {/* Simulateur instantané */}
                <Surface className="rounded-[32px] p-5 lg:p-6 bg-gradient-to-br from-[#faf7f2] to-white">
                    <SectionTitle
                        description="Simulez instantanément la commission quincaillerie et le montant net pour un retrait type."
                        title="Simulateur de Retrait d'Espèces"
                    />
                    <div className="mt-5 space-y-4">
                        <div className="flex items-center justify-between text-xs text-[var(--admin-muted)] font-semibold">
                            <span>Montant brut : {money(simulatedAmount)}</span>
                            <span className="text-[#8a6b3d]">Taux actif : {currentRate}%</span>
                        </div>
                        <input
                            type="range"
                            min="5000"
                            max="500000"
                            step="5000"
                            value={simulatedAmount}
                            onChange={(e) => setSimulatedAmount(parseInt(e.target.value, 10))}
                            className="w-full h-2 bg-[#e2d5c3] rounded-lg appearance-none cursor-pointer accent-[#8a6b3d]"
                        />
                        <div className="grid grid-cols-2 gap-3 pt-2">
                            <div className="p-3 rounded-2xl bg-amber-50 border border-amber-200">
                                <p className="text-[10px] font-semibold uppercase text-amber-800">Commission Quincaillerie</p>
                                <p className="text-base font-bold text-amber-900 mt-1">{money(simCommission)}</p>
                            </div>
                            <div className="p-3 rounded-2xl bg-green-50 border border-green-200">
                                <p className="text-[10px] font-semibold uppercase text-green-800">Net Décaissé</p>
                                <p className="text-base font-bold text-green-900 mt-1">{money(simNet)}</p>
                            </div>
                        </div>
                    </div>
                </Surface>
            </div>

            {/* 3. RÉPARTITION PAR QUINCAILLERIE */}
            <Surface className="rounded-[32px] p-5 lg:p-6">
                <SectionTitle
                    description="Performance et volume d'espèces distribué par chaque quincaillerie agréée."
                    title="Activité Cash-Out par Quincaillerie"
                />
                <DataTable className="mt-5">
                    <thead>
                        <tr>
                            <th>Boutique Relais</th>
                            <th>Contact</th>
                            <th>Opérations</th>
                            <th>Volume Brut Distribué</th>
                            <th>Commissions Gagnées</th>
                        </tr>
                    </thead>
                    <tbody>
                        {cashoutsBySupplier.length === 0 ? (
                            <tr>
                                <td colSpan={5}>
                                    <EmptyState
                                        description="Aucune opération de cash-out enregistrée par les quincailleries pour l'instant."
                                        title="Aucun historique quincaillerie"
                                    />
                                </td>
                            </tr>
                        ) : (
                            cashoutsBySupplier.map((row: any, idx: number) => (
                                <tr key={idx} className="hover:bg-black/[0.02] transition">
                                    <td className="font-semibold text-[var(--admin-text)]">
                                        {row.shop_name || row.supplier_name}
                                    </td>
                                    <td className="text-sm text-[var(--admin-text-soft)]">{row.supplier_phone}</td>
                                    <td className="text-sm font-semibold">{row.total_operations} retrait(s)</td>
                                    <td className="text-sm font-bold text-[var(--admin-text)]">{money(row.volume_brut_retraits)}</td>
                                    <td className="text-sm font-bold text-[#2e7d32]">{money(row.total_commissions_gagnees)}</td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </DataTable>
            </Surface>

            {/* 4. DEMANDES & JOURNAL DES RETRAITS RECENTS */}
            <Surface className="rounded-[32px] p-5 lg:p-6">
                <SectionTitle
                    description="Historique chronologique des demandes de retrait cash avec options de validation et suivi."
                    title="Journal des Opérations de Retrait Cash"
                />
                <DataTable className="mt-5">
                    <thead>
                        <tr>
                            <th>Réf / Date</th>
                            <th>Quincaillerie</th>
                            <th>Bénéficiaire</th>
                            <th>Montant Brut</th>
                            <th>Commission</th>
                            <th>Net Décaissé</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {recentCashouts.length === 0 ? (
                            <tr>
                                <td colSpan={8}>
                                    <EmptyState
                                        description="Aucune transaction de retrait cash enregistrée."
                                        title="Historique vide"
                                    />
                                </td>
                            </tr>
                        ) : (
                            recentCashouts.map((item) => (
                                <tr key={item.id} className="hover:bg-black/[0.02] transition">
                                    <td>
                                        <div className="font-mono text-xs font-bold text-[#8a6b3d]">{item.reference}</div>
                                        <div className="text-[11px] text-[var(--admin-muted)] mt-0.5">
                                            {item.created_at ? new Date(item.created_at).toLocaleDateString('fr-FR') : '-'}
                                        </div>
                                    </td>
                                    <td className="font-medium text-sm text-[var(--admin-text)]">{item.supplier_name}</td>
                                    <td>
                                        <div className="text-sm font-semibold text-[var(--admin-text)]">{item.beneficiary_name}</div>
                                        <div className="text-xs text-[var(--admin-muted)]">{item.beneficiary_phone}</div>
                                    </td>
                                    <td className="text-sm font-bold text-[var(--admin-text)]">{money(item.montant_brut)}</td>
                                    <td className="text-sm font-semibold text-amber-700">{money(item.montant_commission)}</td>
                                    <td className="text-sm font-bold text-green-700">{money(item.montant_net)}</td>
                                    <td>{getStatusBadge(item.statut)}</td>
                                    <td>
                                        <div className="flex items-center gap-1.5">
                                            {item.statut === 'en_attente' && (
                                                <>
                                                    <button
                                                        type="button"
                                                        onClick={() => router.post(`/admin/cashouts/${item.id}/approve`, {}, { preserveScroll: true })}
                                                        className="px-2 py-1 text-xs font-semibold rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-100 transition"
                                                        title="Approuver la demande"
                                                    >
                                                        Approuver
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            const reason = prompt('Motif du rejet :');
                                                            if (reason) {
                                                                router.post(`/admin/cashouts/${item.id}/reject`, { reason }, { preserveScroll: true });
                                                            }
                                                        }}
                                                        className="px-2 py-1 text-xs font-semibold rounded-lg bg-red-50 text-red-700 hover:bg-red-100 transition"
                                                        title="Rejeter la demande"
                                                    >
                                                        Rejeter
                                                    </button>
                                                </>
                                            )}
                                            {item.statut === 'approuve' && (
                                                <button
                                                    type="button"
                                                    onClick={() => router.post(`/admin/cashouts/${item.id}/complete`, {}, { preserveScroll: true })}
                                                    className="px-2.5 py-1 text-xs font-semibold rounded-lg bg-green-50 text-green-700 hover:bg-green-100 transition"
                                                    title="Confirmer la remise d'espèces"
                                                >
                                                    Décaisser
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </DataTable>
            </Surface>

            {/* MODAL NOUVELLE OPÉRATION DE CASHOUT */}
            {showNewModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm">
                    <div className="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl border border-[var(--admin-border)]">
                        <h3 className="text-lg font-bold text-[var(--admin-text)]">Enregistrer un Retrait Cash</h3>
                        <p className="text-xs text-[var(--admin-muted)] mt-1">
                            Opération de remise d'espèces au guichet d'une quincaillerie partenaire.
                        </p>

                        <form onSubmit={handleCreateCashout} className="mt-5 space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-[var(--admin-text-soft)] mb-1">
                                    Quincaillerie Relais *
                                </label>
                                <select
                                    value={newSupplierId}
                                    onChange={(e) => setNewSupplierId(e.target.value)}
                                    required
                                    className="admin-input w-full rounded-xl px-3 py-2 text-sm bg-white border border-[var(--admin-border)]"
                                >
                                    <option value="">Sélectionner une quincaillerie...</option>
                                    {cashoutsBySupplier.map((s: any) => (
                                        <option key={s.supplier_id} value={s.supplier_id}>
                                            {s.shop_name || s.supplier_name} ({s.supplier_phone})
                                        </option>
                                    ))}
                                    {suppliers.map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.name} ({s.phone})
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold text-[var(--admin-text-soft)] mb-1">
                                        Nom Bénéficiaire *
                                    </label>
                                    <input
                                        type="text"
                                        value={newBeneficiary}
                                        onChange={(e) => setNewBeneficiary(e.target.value)}
                                        required
                                        placeholder="Ex: Kouassi Michel"
                                        className="admin-input w-full rounded-xl px-3 py-2 text-sm border border-[var(--admin-border)]"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-[var(--admin-text-soft)] mb-1">
                                        Téléphone Bénéficiaire *
                                    </label>
                                    <input
                                        type="text"
                                        value={newPhone}
                                        onChange={(e) => setNewPhone(e.target.value)}
                                        required
                                        placeholder="0701020304"
                                        className="admin-input w-full rounded-xl px-3 py-2 text-sm border border-[var(--admin-border)]"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold text-[var(--admin-text-soft)] mb-1">
                                        Montant Brut (FCFA) *
                                    </label>
                                    <input
                                        type="number"
                                        min="1000"
                                        step="500"
                                        value={newAmount}
                                        onChange={(e) => setNewAmount(e.target.value)}
                                        required
                                        placeholder="Ex: 50000"
                                        className="admin-input w-full rounded-xl px-3 py-2 text-sm font-semibold border border-[var(--admin-border)]"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-[var(--admin-text-soft)] mb-1">
                                        Mode de remise
                                    </label>
                                    <select
                                        value={newMode}
                                        onChange={(e) => setNewMode(e.target.value)}
                                        className="admin-input w-full rounded-xl px-3 py-2 text-sm bg-white border border-[var(--admin-border)]"
                                    >
                                        <option value="especes_guichet">Espèces au guichet</option>
                                        <option value="wave">Wave</option>
                                        <option value="orange_money">Orange Money</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-[var(--admin-text-soft)] mb-1">
                                    Notes / Justificatif
                                </label>
                                <textarea
                                    value={newNotes}
                                    onChange={(e) => setNewNotes(e.target.value)}
                                    rows={2}
                                    placeholder="Préciser le motif ou la référence chantier..."
                                    className="admin-input w-full rounded-xl px-3 py-2 text-sm border border-[var(--admin-border)]"
                                />
                            </div>

                            <div className="flex justify-end gap-3 pt-3 border-t border-[var(--admin-border)]">
                                <button
                                    type="button"
                                    onClick={() => setShowNewModal(false)}
                                    className="admin-button admin-button--ghost text-xs py-2 px-4 rounded-xl"
                                >
                                    Annuler
                                </button>
                                <button
                                    type="submit"
                                    disabled={isSubmitting}
                                    className="admin-button admin-button--primary text-xs py-2 px-5 rounded-xl font-semibold"
                                >
                                    {isSubmitting ? 'Enregistrement...' : 'Enregistrer'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
