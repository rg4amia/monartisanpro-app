// Section Anti-Fraude & Détection de Collusion (Lot 3)
// Gestion proactive des risques chantiers, collusion artisan-client, validation express et alertes géospatiales.

import { useState } from 'react';
import { router } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import {
    DataTable,
    dateTimeShort,
    EmptyState,
    MetricCard,
    numberFormat,
    SectionTitle,
    Surface,
} from '../shared';

export interface FraudAlertItem {
    id: number;
    reference: string;
    mission_id: number | null;
    user_name: string;
    user_phone?: string;
    user_role?: string;
    target_name?: string;
    target_phone?: string;
    target_role?: string;
    type: string;
    severity: 'low' | 'medium' | 'high' | 'critical';
    risk_score: number;
    statut: 'ouverte' | 'en_analyse' | 'confirmee' | 'rejetee' | 'classee';
    action_taken: 'none' | 'payment_hold' | 'flagged_account' | 'blocked';
    reasons: string[];
    metadata: Record<string, any>;
    created_at?: string;
}

interface FraudAlertsSectionProps {
    fraudData?: {
        gps_attempts_7d?: number;
        gps_attempts_total?: number;
        open_alerts_count?: number;
        critical_alerts_count?: number;
        payment_holds_count?: number;
        alerts_list?: FraudAlertItem[];
    };
    canManage?: boolean;
}

const FRAUD_TYPE_LABELS: Record<string, { label: string; icon: string }> = {
    collusion_artisan_client: { label: 'Collusion Artisan-Client', icon: '🤝' },
    collusion_artisan_fournisseur: { label: 'Collusion Artisan-Quincaillerie', icon: '🏪' },
    fast_otp_validation: { label: 'Validation OTP Express (< 2 min)', icon: '⏱️' },
    gps_anomaly_site: { label: 'Anomalie GPS Chantier (> 5 km)', icon: '📍' },
    gps_anomaly_jcode: { label: 'Scan J-Code Hors Zone (> 100 m)', icon: '🚨' },
    duplicate_proof: { label: 'Preuve Photo Dupliquée', icon: '📸' },
    vision_mismatch: { label: 'Incohérence Visuelle IA (Gemini)', icon: '👁️' },
};

export function FraudAlertsSection({ fraudData, canManage = true }: FraudAlertsSectionProps) {
    const alerts = fraudData?.alerts_list ?? [];
    const openCount = fraudData?.open_alerts_count ?? alerts.filter((a) => a.statut === 'ouverte' || a.statut === 'en_analyse').length;
    const criticalCount = fraudData?.critical_alerts_count ?? alerts.filter((a) => a.severity === 'critical' || a.risk_score >= 75).length;
    const holdCount = fraudData?.payment_holds_count ?? alerts.filter((a) => a.action_taken === 'payment_hold').length;

    const [selectedAlert, setSelectedAlert] = useState<FraudAlertItem | null>(null);
    const [actionNote, setActionNote] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [filterType, setFilterType] = useState<string>('');
    const [filterSeverity, setFilterSeverity] = useState<string>('');

    const filteredAlerts = alerts.filter((item) => {
        if (filterType && item.type !== filterType) return false;
        if (filterSeverity && item.severity !== filterSeverity) return false;
        return true;
    });

    const handleAction = (endpoint: string) => {
        if (!selectedAlert) return;
        setIsSubmitting(true);
        router.post(
            `/admin/fraud-alerts/${selectedAlert.id}/${endpoint}`,
            { notes: actionNote },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedAlert(null);
                    setActionNote('');
                },
                onFinish: () => setIsSubmitting(false),
            }
        );
    };

    return (
        <div className="space-y-6">
            {/* CARTES DE KPIS SÉCURITÉ & FRAUDE */}
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <MetricCard
                    description="Alertes en attente d'instruction"
                    tone={openCount > 0 ? 'rose' : 'green'}
                    trend="Vigilance active"
                    value={numberFormat.format(openCount)}
                >
                    Alertes Ouvertes
                </MetricCard>

                <MetricCard
                    description="Risque critique ou score ≥ 75/100"
                    tone={criticalCount > 0 ? 'rose' : 'green'}
                    trend="Haute priorité"
                    value={numberFormat.format(criticalCount)}
                >
                    Alertes Critiques
                </MetricCard>

                <MetricCard
                    description="Missions avec décaissement bloqué préventivement"
                    tone={holdCount > 0 ? 'amber' : 'green'}
                    trend="Séquestre protégé"
                    value={numberFormat.format(holdCount)}
                >
                    Gels Conservatoires
                </MetricCard>

                <MetricCard
                    description="Tentatives de scan hors zone boutique (7 j)"
                    tone={(fraudData?.gps_attempts_7d ?? 0) > 0 ? 'amber' : 'green'}
                    trend="Geofencing J-Code"
                    value={numberFormat.format(fraudData?.gps_attempts_7d ?? 0)}
                >
                    Fraude GPS Boutique
                </MetricCard>
            </div>

            {/* TABLE DES ALERTES DE FRAUDE & COLLUSION */}
            <Surface className="rounded-[32px] p-5 lg:p-6">
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <SectionTitle
                        description="Détection automatique des collusions, anomalies de géolocalisation et manipulations de jalons."
                        title="Centre de Détection des Fraudes & Collusions"
                    />

                    {/* FILTRES RAPIDES */}
                    <div className="flex flex-wrap items-center gap-2 text-xs">
                        <select
                            value={filterType}
                            onChange={(e) => setFilterType(e.target.value)}
                            className="rounded-xl border border-[var(--admin-border)] bg-white px-3 py-1.5 text-xs text-[var(--admin-text)]"
                        >
                            <option value="">Tous les types d'alerte</option>
                            {Object.entries(FRAUD_TYPE_LABELS).map(([k, v]) => (
                                <option key={k} value={k}>
                                    {v.icon} {v.label}
                                </option>
                            ))}
                        </select>

                        <select
                            value={filterSeverity}
                            onChange={(e) => setFilterSeverity(e.target.value)}
                            className="rounded-xl border border-[var(--admin-border)] bg-white px-3 py-1.5 text-xs text-[var(--admin-text)]"
                        >
                            <option value="">Toutes gravités</option>
                            <option value="critical">Critique</option>
                            <option value="high">Élevée</option>
                            <option value="medium">Moyenne</option>
                            <option value="low">Basse</option>
                        </select>
                    </div>
                </div>

                <DataTable className="mt-5">
                    <thead>
                        <tr>
                            <th>Référence</th>
                            <th>Type de Détection</th>
                            <th>Auteur / Acteurs</th>
                            <th>Niveau de Risque</th>
                            <th>Action & Statut</th>
                            <th>Détecté le</th>
                            <th className="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filteredAlerts.length === 0 ? (
                            <tr>
                                <td colSpan={7}>
                                    <EmptyState
                                        description="Aucun signal suspect ou tentative de fraude n'a été détecté pour le moment."
                                        title="Réseau sécurisé et conforme"
                                    />
                                </td>
                            </tr>
                        ) : (
                            filteredAlerts.map((item) => {
                                const typeMeta = FRAUD_TYPE_LABELS[item.type] ?? { label: item.type, icon: '⚠️' };
                                return (
                                    <tr key={item.id} className="hover:bg-black/[0.02] transition">
                                        <td className="font-semibold text-xs text-[var(--admin-text)]">
                                            <span>{item.reference}</span>
                                            {item.mission_id ? (
                                                <span className="block text-[10px] text-[var(--admin-muted)]">
                                                    Mission #{item.mission_id}
                                                </span>
                                            ) : null}
                                        </td>
                                        <td>
                                            <span className="inline-flex items-center gap-1 text-xs font-semibold text-[var(--admin-text)]">
                                                <span>{typeMeta.icon}</span>
                                                <span>{typeMeta.label}</span>
                                            </span>
                                        </td>
                                        <td>
                                            <p className="font-semibold text-xs text-[var(--admin-text)]">
                                                {item.user_name} ({item.user_role ?? 'Acteur'})
                                            </p>
                                            {item.target_name ? (
                                                <p className="text-[11px] text-[var(--admin-muted)]">
                                                    Complice présumé : {item.target_name} ({item.target_role ?? 'Cible'})
                                                </p>
                                            ) : null}
                                        </td>
                                        <td>
                                            <div className="flex items-center gap-2">
                                                <span
                                                    className={cn(
                                                        'rounded-lg px-2 py-0.5 text-[11px] font-extrabold',
                                                        item.risk_score >= 75
                                                            ? 'bg-rose-100 text-rose-800'
                                                            : item.risk_score >= 50
                                                            ? 'bg-amber-100 text-amber-800'
                                                            : 'bg-emerald-100 text-emerald-800'
                                                    )}
                                                >
                                                    {item.risk_score}/100
                                                </span>
                                                <span className="text-[10px] uppercase font-bold text-[var(--admin-muted)]">
                                                    {item.severity}
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div className="flex flex-col gap-1">
                                                {item.action_taken === 'payment_hold' ? (
                                                    <span className="inline-flex items-center rounded-md bg-rose-50 border border-rose-200 px-2 py-0.5 text-[10px] font-bold text-rose-700">
                                                        🛑 Gel Paiement
                                                    </span>
                                                ) : (
                                                    <span className="text-[11px] text-[var(--admin-muted)]">
                                                        Normal
                                                    </span>
                                                )}
                                                <span className="text-[10px] text-[var(--admin-text-soft)] capitalize">
                                                    {item.statut.replace('_', ' ')}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="text-xs text-[var(--admin-muted)]">
                                            {item.created_at ? dateTimeShort(item.created_at) : '—'}
                                        </td>
                                        <td className="text-right">
                                            <button
                                                type="button"
                                                onClick={() => setSelectedAlert(item)}
                                                className="rounded-xl border border-[var(--admin-border)] bg-white px-3 py-1.5 text-xs font-semibold text-[var(--admin-text)] hover:bg-[#f5ebd7] transition"
                                            >
                                                Instruire
                                            </button>
                                        </td>
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </DataTable>
            </Surface>

            {/* MODALE D'INSTRUCTION ET D'ARBITRAGE DE FRAUDE */}
            {selectedAlert && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
                    <div className="w-full max-w-2xl rounded-3xl border border-[var(--admin-border)] bg-white p-6 shadow-2xl">
                        <div className="flex items-center justify-between border-b border-[var(--admin-border)] pb-4">
                            <div>
                                <h3 className="text-lg font-bold text-[var(--admin-text)]">
                                    Instruction Alerte #{selectedAlert.reference}
                                </h3>
                                <p className="text-xs text-[var(--admin-muted)]">
                                    {FRAUD_TYPE_LABELS[selectedAlert.type]?.label ?? selectedAlert.type} • Score de Risque : {selectedAlert.risk_score}/100
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setSelectedAlert(null)}
                                className="rounded-full p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                            >
                                ✕
                            </button>
                        </div>

                        {/* RAISONS DÉTECTÉES */}
                        <div className="mt-4 space-y-3">
                            <div className="rounded-2xl border border-rose-200 bg-rose-50/50 p-4">
                                <h4 className="text-xs font-bold uppercase tracking-wider text-rose-800">
                                    Signaux d'anomalie détectés par l'algorithme :
                                </h4>
                                <ul className="mt-2 space-y-1.5">
                                    {selectedAlert.reasons.map((r, i) => (
                                        <li key={i} className="text-xs text-rose-900 flex items-start gap-2">
                                            <span className="text-rose-500 font-bold">•</span>
                                            <span>{r}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            {/* MÉCADONNÉES TECHNIQUES */}
                            {selectedAlert.metadata && Object.keys(selectedAlert.metadata).length > 0 && (
                                <div className="rounded-2xl border border-[var(--admin-border)] bg-gray-50/80 p-3 text-xs">
                                    <h5 className="font-bold text-[var(--admin-text)] mb-1">Détails techniques :</h5>
                                    <pre className="text-[11px] text-[var(--admin-text-soft)] overflow-x-auto">
                                        {JSON.stringify(selectedAlert.metadata, null, 2)}
                                    </pre>
                                </div>
                            )}

                            {/* CHAMP DE MOTIF D'ARBITRAGE */}
                            <div>
                                <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">
                                    Note d'arbitrage / Décision administrateur :
                                </label>
                                <textarea
                                    rows={3}
                                    placeholder="Précisez la décision, les constatations ou le motif de classement..."
                                    value={actionNote}
                                    onChange={(e) => setActionNote(e.target.value)}
                                    className="w-full rounded-xl border border-[var(--admin-border)] p-3 text-xs outline-none focus:border-[#ebb95e]"
                                />
                            </div>
                        </div>

                        {/* BOUTONS D'ARBITRAGE */}
                        <div className="mt-6 flex flex-wrap items-center justify-end gap-2 border-t border-[var(--admin-border)] pt-4">
                            <button
                                type="button"
                                onClick={() => setSelectedAlert(null)}
                                className="rounded-xl border border-[var(--admin-border)] px-4 py-2 text-xs font-semibold text-[var(--admin-text-soft)] hover:bg-gray-50"
                            >
                                Annuler
                            </button>

                            {canManage && (
                                <>
                                    <button
                                        type="button"
                                        disabled={isSubmitting}
                                        onClick={() => handleAction('dismiss')}
                                        className="rounded-xl border border-gray-300 bg-gray-100 px-4 py-2 text-xs font-bold text-gray-700 hover:bg-gray-200"
                                    >
                                        Classer sans suite
                                    </button>

                                    {selectedAlert.action_taken === 'payment_hold' ? (
                                        <button
                                            type="button"
                                            disabled={isSubmitting}
                                            onClick={() => handleAction('release')}
                                            className="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700"
                                        >
                                            Lever le Gel & Libérer
                                        </button>
                                    ) : (
                                        <button
                                            type="button"
                                            disabled={isSubmitting}
                                            onClick={() => handleAction('hold')}
                                            className="rounded-xl bg-amber-500 px-4 py-2 text-xs font-bold text-white hover:bg-amber-600"
                                        >
                                            Activer Gel Préventif
                                        </button>
                                    )}

                                    <button
                                        type="button"
                                        disabled={isSubmitting || !actionNote.trim()}
                                        onClick={() => handleAction('confirm')}
                                        className="rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white hover:bg-rose-700 disabled:opacity-50"
                                    >
                                        Confirmer Fraude & Sanctionner
                                    </button>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
