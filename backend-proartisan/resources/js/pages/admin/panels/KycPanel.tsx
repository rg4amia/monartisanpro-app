// Onglet « KYC & Vérifications » du backoffice.
// Chantier C2 : découpe de console.tsx. Chantier C4 (P1-6) : file paginée + recherche serveur.

import type { FormEvent, ReactNode } from 'react';

import {
    actionButtonClass,
    AvatarBubble,
    BulkActionBar,
    DataTable,
    EmptyState,
    MetricCard,
    numberFormat,
    RoleBadge,
    SectionTitle,
    shortDate,
    Surface,
    VolumeBarChart,
} from '../shared';
import type { FournisseurItem, KycStats, KycUser, Paginated } from '../shared';

export interface CnmciUser {
    id: number;
    name: string;
    phone: string;
    cnmci_number?: string | null;
    cnmci_card_url?: string | null;
    cnmci_status: string;
    created_at: string;
}

interface KycPanelProps {
    kycUsersPage: Paginated<KycUser> | undefined;
    pendingFournisseursList: FournisseurItem[];
    kycStats: KycStats;
    cnmciUsers: CnmciUser[];
    search: string;
    onSearchChange: (value: string) => void;
    onSubmit: (event: FormEvent) => void;
    onReset: () => void;
    renderPagination: (links: Paginated<KycUser>['links'] | undefined) => ReactNode;
    actionLoading: boolean;
    isSelected: (id: number) => boolean;
    selectionCount: number;
    onToggleRow: (id: number) => void;
    onToggleAll: (ids: number[]) => void;
    onClearSelection: () => void;
    onBulkKyc: (decision: 'approuve' | 'rejete') => void;
    onKycDecision: (user: KycUser, decision: 'approuve' | 'rejete') => void;
    onFournisseurDecision: (fournisseur: FournisseurItem, decision: 'agree' | 'suspendu') => void;
    onCnmciDecision: (userId: number, decision: 'valide' | 'rejete') => void;
    /** Capacités fines (Chantier C6 / P2-10). */
    canReview?: boolean;
    canReviewFournisseurs?: boolean;
}

export function KycPanel({
    kycUsersPage,
    pendingFournisseursList,
    kycStats,
    cnmciUsers,
    search,
    onSearchChange,
    onSubmit,
    onReset,
    renderPagination,
    actionLoading,
    isSelected,
    selectionCount,
    onToggleRow,
    onToggleAll,
    onClearSelection,
    onBulkKyc,
    onKycDecision,
    onFournisseurDecision,
    onCnmciDecision,
    canReview = true,
    canReviewFournisseurs = true,
}: KycPanelProps) {
    const kycRows = kycUsersPage?.data ?? [];
    const pageIds = kycRows.map((u) => u.id);
    const allOnPageSelected = pageIds.length > 0 && pageIds.every(isSelected);

    return (
        <section className="mt-5 space-y-5">
            {canReview ? (
                <BulkActionBar
                    count={selectionCount}
                    onClear={onClearSelection}
                    actions={[
                        { label: 'Approuver la sélection', tone: 'success', onClick: () => onBulkKyc('approuve') },
                        { label: 'Rejeter la sélection', tone: 'danger', onClick: () => onBulkKyc('rejete') },
                    ]}
                />
            ) : null}
            <div className="grid gap-4 xl:grid-cols-4">
                <MetricCard description="Clients, artisans et fournisseurs en attente" tone="amber" trend="À traiter sans friction" value={numberFormat.format(kycStats.pending)}>
                    Dossiers ouverts
                </MetricCard>
                <MetricCard
                    description="Demandes à fort impact sur la disponibilité terrain"
                    tone="green"
                    trend="Priorité aux artisans"
                    value={numberFormat.format(kycStats.artisans_pending)}
                >
                    Artisans en attente
                </MetricCard>
                <MetricCard
                    description="Boutiques à activer pour le scan des J-Codes"
                    tone="blue"
                    trend="Agrément fournisseur"
                    value={numberFormat.format(kycStats.fournisseurs_pending)}
                >
                    Fournisseurs à revoir
                </MetricCard>
                <MetricCard
                    description="Comptes déjà bloqués après revue"
                    tone="rose"
                    trend="Historique des rejets"
                    value={numberFormat.format(kycStats.rejected)}
                >
                    Rejets connus
                </MetricCard>
            </div>

            <div className="grid gap-5 xl:grid-cols-[1.15fr_0.85fr]">
                <Surface className="rounded-[32px] p-5 lg:p-6">
                    <SectionTitle
                        description="Validation documentaire avec liens directs vers les pièces CNI et selfie."
                        title="Dossiers à traiter"
                    />

                    <form onSubmit={onSubmit} className="mt-4 flex flex-wrap gap-2">
                        <input
                            type="text"
                            placeholder="Nom, téléphone, e-mail ou ID..."
                            value={search}
                            onChange={(e) => onSearchChange(e.target.value)}
                            className="w-full max-w-xs rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                        />
                        <button type="submit" className="rounded-xl bg-[#ebb95e] px-4 py-2 text-xs font-bold text-[#241b16] transition hover:bg-[#dca850]">Filtrer</button>
                        <button type="button" onClick={onReset} className="rounded-xl border border-[var(--admin-border)] bg-white/60 px-3 py-2 text-xs font-bold text-[var(--admin-text-soft)] transition hover:bg-white/80">Réinitialiser</button>
                        {kycUsersPage ? (
                            <span className="ml-auto self-center text-[11px] text-[var(--admin-muted)]">
                                {numberFormat.format(kycUsersPage.total)} dossier(s) • page {kycUsersPage.current_page}/{kycUsersPage.last_page}
                            </span>
                        ) : null}
                    </form>

                    <DataTable className="mt-5">
                        <thead>
                            <tr>
                                <th className="w-8">
                                    <input
                                        type="checkbox"
                                        aria-label="Tout sélectionner"
                                        checked={allOnPageSelected}
                                        onChange={() => onToggleAll(pageIds)}
                                    />
                                </th>
                                <th>Utilisateur</th>
                                <th>Rôle</th>
                                <th>Documents</th>
                                <th>Déposé le</th>
                                <th className="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {kycRows.length === 0 ? (
                                <tr>
                                    <td colSpan={6}>
                                        <EmptyState description="Aucun dossier ne correspond à votre recherche." title="Rien à afficher" />
                                    </td>
                                </tr>
                            ) : (
                                kycRows.map((user) => (
                                    <tr key={user.id} className={isSelected(user.id) ? 'bg-amber-500/10' : undefined}>
                                        <td>
                                            <input
                                                type="checkbox"
                                                aria-label={`Sélectionner ${user.name}`}
                                                checked={isSelected(user.id)}
                                                onChange={() => onToggleRow(user.id)}
                                            />
                                        </td>
                                        <td>
                                            <div className="flex items-center gap-3">
                                                <AvatarBubble label={user.name} />
                                                <div className="min-w-0">
                                                    <p className="truncate font-semibold text-[var(--admin-text)]">{user.name}</p>
                                                    <p className="text-xs text-[var(--admin-muted)]">{user.phone}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <RoleBadge role={user.role} />
                                        </td>
                                        <td>
                                            <div className="flex flex-wrap gap-2">
                                                {user.kyc_documents.map((document) => (
                                                    <a
                                                        key={document.id}
                                                        href={document.file_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="rounded-full border border-[#e6d3b2] px-3 py-1 text-xs font-medium text-[#8b6732] transition hover:bg-[#fbf1db]"
                                                    >
                                                        Voir {document.type.toUpperCase()}
                                                    </a>
                                                ))}
                                            </div>
                                        </td>
                                        <td className="text-sm text-[var(--admin-text-soft)]">{shortDate(user.created_at)}</td>
                                        <td>
                                            {canReview ? (
                                                <div className="flex justify-end gap-2">
                                                    <button
                                                        type="button"
                                                        disabled={actionLoading}
                                                        onClick={() => onKycDecision(user, 'approuve')}
                                                        className={actionButtonClass('success')}
                                                    >
                                                        Approuver
                                                    </button>
                                                    <button
                                                        type="button"
                                                        disabled={actionLoading}
                                                        onClick={() => onKycDecision(user, 'rejete')}
                                                        className={actionButtonClass('danger')}
                                                    >
                                                        Rejeter
                                                    </button>
                                                </div>
                                            ) : (
                                                <span className="block text-right text-xs text-[var(--admin-muted)]">Lecture seule</span>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </DataTable>

                    {renderPagination(kycUsersPage?.links)}
                </Surface>

                <div className="space-y-5">
                    <Surface className="rounded-[32px] p-5 lg:p-6">
                        <SectionTitle
                            description="Demandes fournisseurs à activer avant les prochains scans J-Code."
                            title="Boutiques à agréer"
                        />

                        <div className="mt-5 space-y-3">
                            {pendingFournisseursList.length === 0 ? (
                                <EmptyState description="Aucune boutique en attente." title="File fournisseur vide" />
                            ) : (
                                pendingFournisseursList.map((fournisseur) => (
                                    <div key={fournisseur.id} className="rounded-[24px] border border-[var(--admin-border)] bg-white/60 p-4">
                                        <p className="text-sm font-semibold text-[var(--admin-text)]">{fournisseur.nom_boutique}</p>
                                        <p className="mt-1 text-sm text-[var(--admin-text-soft)]">{fournisseur.user?.name ?? 'Contact inconnu'}</p>
                                        <p className="text-xs text-[var(--admin-muted)]">
                                            {fournisseur.user?.phone ?? 'Téléphone non renseigné'} • {shortDate(fournisseur.created_at)}
                                        </p>
                                        {canReviewFournisseurs ? (
                                            <div className="mt-4 flex gap-2">
                                                <button
                                                    type="button"
                                                    disabled={actionLoading}
                                                    onClick={() => onFournisseurDecision(fournisseur, 'agree')}
                                                    className={actionButtonClass('success')}
                                                >
                                                    Agréer
                                                </button>
                                                <button
                                                    type="button"
                                                    disabled={actionLoading}
                                                    onClick={() => onFournisseurDecision(fournisseur, 'suspendu')}
                                                    className={actionButtonClass('danger')}
                                                >
                                                    Suspendre
                                                </button>
                                            </div>
                                        ) : null}
                                    </div>
                                ))
                            )}
                        </div>
                    </Surface>

                    <Surface className="rounded-[32px] p-5 lg:p-6">
                        <SectionTitle
                            description="Vérifiez le numéro d'artisan et la carte CNMCI soumis par les artisans."
                            title="Certifications CNMCI"
                        />

                        <div className="mt-5 space-y-3">
                            {cnmciUsers.length === 0 ? (
                                <EmptyState description="Aucune affiliation CNMCI en attente." title="File CNMCI vide" />
                            ) : (
                                cnmciUsers.map((artisan) => (
                                    <div key={artisan.id} className="rounded-[24px] border border-[var(--admin-border)] bg-white/60 p-4">
                                        <p className="text-sm font-semibold text-[var(--admin-text)]">{artisan.name}</p>
                                        <p className="mt-0.5 text-xs text-[var(--admin-muted)]">{artisan.phone}</p>

                                        <div className="mt-2 bg-[#fdfaf3] border border-[#f5ebcf] rounded-xl p-3">
                                            <p className="text-xs text-[var(--admin-text-soft)]">
                                                <strong className="text-[var(--admin-text)]">Numéro CNMCI :</strong> {artisan.cnmci_number || 'Non renseigné'}
                                            </p>
                                            {artisan.cnmci_card_url && (
                                                <a
                                                    href={artisan.cnmci_card_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="inline-flex items-center gap-1.5 text-xs font-semibold text-[#8b6732] hover:underline mt-2"
                                                >
                                                    <span>Voir l'image de la carte</span>
                                                    <span className="text-[10px]">↗</span>
                                                </a>
                                            )}
                                        </div>

                                        {canReview ? (
                                            <div className="mt-4 flex gap-2">
                                                <button
                                                    type="button"
                                                    disabled={actionLoading}
                                                    onClick={() => onCnmciDecision(artisan.id, 'valide')}
                                                    className={actionButtonClass('success')}
                                                >
                                                    Valider
                                                </button>
                                                <button
                                                    type="button"
                                                    disabled={actionLoading}
                                                    onClick={() => onCnmciDecision(artisan.id, 'rejete')}
                                                    className={actionButtonClass('danger')}
                                                >
                                                    Rejeter
                                                </button>
                                            </div>
                                        ) : null}
                                    </div>
                                ))
                            )}
                        </div>
                    </Surface>

                    <Surface className="rounded-[32px] p-5 lg:p-6">
                        <SectionTitle description="Répartition des créations récentes de comptes." title="Nouveaux comptes / 15 jours" />
                        <VolumeBarChart bars={kycStats.registration_trend} color="#d59a37" />
                    </Surface>
                </div>
            </div>
        </section>
    );
}
