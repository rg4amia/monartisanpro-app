// Modale RGPD — données personnelles d'un utilisateur (Chantier C6 / P2-11).

import { useEffect, useState } from 'react';

import { EmptyState, shortDate, useDismissOnEscape } from '../shared';
import type { AdminUser, PersonalDataReport } from '../shared';

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

function DataRow({ label, value }: { label: string; value: string | number | null | undefined }) {
    return (
        <div className="flex items-start justify-between gap-4 py-1.5">
            <span className="text-xs text-[var(--admin-muted)]">{label}</span>
            <span className="text-xs font-medium text-[var(--admin-text)] text-right break-all">
                {value === null || value === undefined || value === '' ? '—' : value}
            </span>
        </div>
    );
}

export function PersonalDataModal({
    user,
    canAnonymize,
    actionLoading,
    onAnonymize,
    onClose,
}: {
    user: AdminUser;
    canAnonymize: boolean;
    actionLoading: boolean;
    onAnonymize: (user: AdminUser) => void;
    onClose: () => void;
}) {
    const [report, setReport] = useState<PersonalDataReport | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(false);

    useDismissOnEscape(onClose);

    useEffect(() => {
        let active = true;

        fetch(`/admin/users/${user.id}/personal-data`, { headers: { Accept: 'application/json' } })
            .then((response) => {
                if (!response.ok) throw new Error(String(response.status));
                return response.json();
            })
            .then((data: PersonalDataReport) => {
                if (active) setReport(data);
            })
            .catch(() => {
                if (active) setError(true);
            })
            .finally(() => {
                if (active) setLoading(false);
            });

        return () => {
            active = false;
        };
    }, [user.id]);

    const alreadyAnonymized = Boolean(report?.user.anonymized_at);

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" role="presentation" onClick={onClose}>
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="rgpd-modal-title"
                className="admin-panel admin-surface w-full max-w-[720px] rounded-[32px] border p-6 lg:p-8 shadow-2xl relative"
                onClick={(event) => event.stopPropagation()}
            >
                <div className="flex items-center justify-between border-b border-[var(--admin-border)] pb-4">
                    <div>
                        <h2 id="rgpd-modal-title" className="text-xl font-bold text-[var(--admin-text)]">Données personnelles — RGPD</h2>
                        <p className="text-xs text-[var(--admin-muted)] mt-1">Compte #{user.id} • {user.name}</p>
                    </div>
                    <CloseButton onClose={onClose} />
                </div>

                <div className="mt-5 max-h-[420px] overflow-y-auto pr-1">
                    {loading ? (
                        <p className="text-sm text-[var(--admin-muted)] py-8 text-center">Chargement…</p>
                    ) : error || !report ? (
                        <EmptyState description="Impossible de charger les données personnelles." title="Erreur" />
                    ) : (
                        <div className="space-y-5">
                            <section className="rounded-2xl border border-[var(--admin-border)] bg-white/50 p-4">
                                <h3 className="text-xs font-bold uppercase tracking-widest text-[#b77918] mb-2">Identité &amp; contact</h3>
                                <DataRow label="Nom" value={report.user.name} />
                                <DataRow label="E-mail" value={report.user.email} />
                                <DataRow label="Téléphone" value={report.user.phone} />
                                <DataRow label="Téléphone paiement" value={report.user.payment_phone} />
                                <DataRow label="Rôle" value={report.user.role} />
                                <DataRow label="Statut KYC" value={report.user.kyc_status} />
                                <DataRow label="Statut compte" value={report.user.account_status} />
                                <DataRow label="Commune" value={report.user.commune} />
                                <DataRow label="N° CNMCI" value={report.user.cnmci_number} />
                                <DataRow label="Empreinte appareil" value={report.user.device_fingerprint} />
                                <DataRow
                                    label="Position GPS"
                                    value={report.position ? `${report.position.lat}, ${report.position.lng}` : null}
                                />
                            </section>

                            <section className="rounded-2xl border border-[var(--admin-border)] bg-white/50 p-4">
                                <h3 className="text-xs font-bold uppercase tracking-widest text-[#b77918] mb-2">Consentement</h3>
                                <DataRow
                                    label="CGU &amp; confidentialité acceptées le"
                                    value={report.user.cgu_accepted_at ? shortDate(report.user.cgu_accepted_at) : 'Non enregistré'}
                                />
                                <DataRow label="Compte créé le" value={report.user.created_at ? shortDate(report.user.created_at) : null} />
                                <DataRow label="Anonymisé le" value={report.user.anonymized_at ? shortDate(report.user.anonymized_at) : 'Non'} />
                            </section>

                            <section className="rounded-2xl border border-[var(--admin-border)] bg-white/50 p-4">
                                <h3 className="text-xs font-bold uppercase tracking-widest text-[#b77918] mb-2">Empreinte sur la plateforme</h3>
                                <DataRow label="Pièces KYC" value={report.kyc_documents.length} />
                                <DataRow label="Évaluations émises" value={report.evaluations_given} />
                                <DataRow label="Évaluations reçues" value={report.evaluations_received} />
                                <DataRow label="Missions (client)" value={report.missions_as_client} />
                                <DataRow label="Missions (artisan)" value={report.missions_as_artisan} />
                                <DataRow label="Transactions" value={report.transactions_count} />
                                <DataRow label="Notifications" value={report.notifications_count} />
                                <DataRow label="Parrainages" value={report.parrainages_count} />
                            </section>

                            {report.activity_trace.length > 0 && (
                                <section className="rounded-2xl border border-[var(--admin-border)] bg-white/50 p-4">
                                    <h3 className="text-xs font-bold uppercase tracking-widest text-[#b77918] mb-2">Traçabilité récente</h3>
                                    <div className="space-y-1.5">
                                        {report.activity_trace.map((entry, index) => (
                                            <div key={index} className="flex items-center justify-between gap-3 text-xs">
                                                <span className="font-mono text-[var(--admin-text)]">{entry.action}</span>
                                                <span className="text-[var(--admin-muted)] shrink-0">
                                                    {entry.ip_address ?? '—'} • {entry.created_at ? shortDate(entry.created_at) : '—'}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </section>
                            )}
                        </div>
                    )}
                </div>

                <div className="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-[var(--admin-border)] pt-4">
                    <a href={`/admin/users/${user.id}/personal-data/export`} className="admin-button admin-button--ghost">
                        Exporter (JSON)
                    </a>
                    <div className="flex gap-2">
                        {canAnonymize && !alreadyAnonymized ? (
                            <button
                                type="button"
                                disabled={actionLoading}
                                onClick={() => onAnonymize(user)}
                                className="admin-button admin-button--danger"
                            >
                                Anonymiser ce compte
                            </button>
                        ) : null}
                        <button type="button" onClick={onClose} className="admin-button admin-button--primary">
                            Fermer
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
