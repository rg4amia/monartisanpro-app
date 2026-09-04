// Onglet « Utilisateurs » du backoffice.
// Chantier C2 : découpe de console.tsx. Chantier C4 (P1-6) : liste paginée + filtres serveur.

import type { FormEvent, ReactNode } from 'react';

import { cn } from '@/lib/utils';

import {
    AccountStatusBadge,
    actionButtonClass,
    AvatarBubble,
    BulkActionBar,
    DataTable,
    EmptyState,
    ExportButton,
    KycStatusBadge,
    MetricCard,
    numberFormat,
    RoleBadge,
    roleLabels,
    SectionTitle,
    shortDate,
    Surface,
    toneBadgeClasses,
} from '../shared';
import type { AdminUser, FournisseurItem, Paginated, UserStats } from '../shared';

interface UsersPanelProps {
    users: Paginated<AdminUser> | undefined;
    userStats: UserStats;
    pendingFournisseurs: FournisseurItem[];
    topArtisans: AdminUser[];
    search: string;
    onSearchChange: (value: string) => void;
    roleFilter: string;
    onRoleFilterChange: (value: string) => void;
    kycFilter: string;
    onKycFilterChange: (value: string) => void;
    onSubmit: (event: FormEvent) => void;
    onReset: () => void;
    exportParams: Record<string, string>;
    renderPagination: (links: Paginated<AdminUser>['links'] | undefined) => ReactNode;
    actionLoading: boolean;
    isSelected: (id: number) => boolean;
    selectionCount: number;
    onToggleRow: (id: number) => void;
    onToggleAll: (ids: number[]) => void;
    onClearSelection: () => void;
    onBulkStatus: (status: 'actif' | 'suspendu') => void;
    onCreateUser: () => void;
    onEditUser: (user: AdminUser) => void;
    onToggleUserStatus: (user: AdminUser) => void;
    onDeleteUser: (user: AdminUser) => void;
    onFournisseurDecision: (fournisseur: FournisseurItem, decision: 'agree' | 'suspendu') => void;
    /** Capacités fines (Chantier C6 / P2-10). */
    canManage?: boolean;
    canDelete?: boolean;
    canReviewFournisseurs?: boolean;
    /** RGPD (Chantier C6 / P2-11). */
    canViewRgpd?: boolean;
    onOpenRgpd?: (user: AdminUser) => void;
    /** Usurpation de session (Chantier C7). */
    canImpersonate?: boolean;
    onImpersonate?: (user: AdminUser) => void;
}

export function UsersPanel({
    users,
    userStats,
    pendingFournisseurs,
    topArtisans,
    search,
    onSearchChange,
    roleFilter,
    onRoleFilterChange,
    kycFilter,
    onKycFilterChange,
    onSubmit,
    onReset,
    exportParams,
    renderPagination,
    actionLoading,
    isSelected,
    selectionCount,
    onToggleRow,
    onToggleAll,
    onClearSelection,
    onBulkStatus,
    onCreateUser,
    onEditUser,
    onToggleUserStatus,
    onDeleteUser,
    onFournisseurDecision,
    canManage = true,
    canDelete = true,
    canReviewFournisseurs = true,
    canViewRgpd = false,
    onOpenRgpd,
    canImpersonate = false,
    onImpersonate,
}: UsersPanelProps) {
    const rows = users?.data ?? [];
    const pageIds = rows.map((u) => u.id);
    const allOnPageSelected = pageIds.length > 0 && pageIds.every(isSelected);

    return (
        <section className="mt-5 space-y-5">
            {canManage ? (
                <BulkActionBar
                    count={selectionCount}
                    onClear={onClearSelection}
                    actions={[
                        { label: 'Suspendre la sélection', tone: 'danger', onClick: () => onBulkStatus('suspendu') },
                        { label: 'Réactiver la sélection', tone: 'success', onClick: () => onBulkStatus('actif') },
                    ]}
                />
            ) : null}
            <div className="grid gap-4 xl:grid-cols-4">
                <MetricCard description="Base complète des comptes connectés à la plateforme" tone="amber" trend="Communauté totale" value={numberFormat.format(userStats.total)}>
                    Utilisateurs
                </MetricCard>
                <MetricCard description="Comptes artisans KYC actifs" tone="green" trend="Capacité terrain" value={numberFormat.format(userStats.artisans_actifs)}>
                    Artisans actifs
                </MetricCard>
                <MetricCard description="Clients opérationnels avec KYC actif" tone="blue" trend="Demande solvable" value={numberFormat.format(userStats.clients_actifs)}>
                    Clients actifs
                </MetricCard>
                <MetricCard description="Boutiques déjà approuvées" tone="slate" trend="Réseau matériaux" value={numberFormat.format(userStats.fournisseurs_agrees)}>
                    Fournisseurs agréés
                </MetricCard>
            </div>

            <div className="grid gap-5 xl:grid-cols-[1.15fr_0.85fr]">
                <Surface className="rounded-[32px] p-5 lg:p-6">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <SectionTitle
                            description="Vue consolidée des comptes, rôles, KYC et activité mission."
                            title="Répertoire utilisateurs"
                        />
                        {canManage ? (
                            <button
                                type="button"
                                onClick={onCreateUser}
                                className="admin-button admin-button--primary self-start sm:self-auto"
                            >
                                Ajouter un utilisateur
                            </button>
                        ) : null}
                    </div>

                    <form onSubmit={onSubmit} className="mt-5 grid items-end gap-3 rounded-2xl border border-[var(--admin-border)] bg-white/40 p-4 md:grid-cols-4">
                        <div className="md:col-span-2">
                            <label className="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)]">Rechercher</label>
                            <input
                                type="text"
                                placeholder="Nom, téléphone, e-mail ou ID..."
                                value={search}
                                onChange={(e) => onSearchChange(e.target.value)}
                                className="w-full rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                            />
                        </div>
                        <div>
                            <label className="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)]">Rôle</label>
                            <select
                                value={roleFilter}
                                onChange={(e) => onRoleFilterChange(e.target.value)}
                                className="w-full rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                            >
                                <option value="">Tous les rôles</option>
                                {Object.entries(roleLabels).map(([value, label]) => (
                                    <option key={value} value={value}>{label}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)]">KYC</label>
                            <select
                                value={kycFilter}
                                onChange={(e) => onKycFilterChange(e.target.value)}
                                className="w-full rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                            >
                                <option value="">Tous les statuts</option>
                                <option value="actif">Actif</option>
                                <option value="en_attente">En attente</option>
                                <option value="rejete">Rejeté</option>
                            </select>
                        </div>
                        <div className="flex gap-2 md:col-span-4">
                            <button type="submit" className="rounded-xl bg-[#ebb95e] px-4 py-2 text-xs font-bold text-[#241b16] transition hover:bg-[#dca850]">
                                Filtrer
                            </button>
                            <button
                                type="button"
                                onClick={onReset}
                                className="rounded-xl border border-[var(--admin-border)] bg-white/60 px-3 py-2 text-xs font-bold text-[var(--admin-text-soft)] transition hover:bg-white/80"
                            >
                                Réinitialiser
                            </button>
                            <ExportButton resource="users" params={exportParams} />
                            {users ? (
                                <span className="ml-auto self-center text-[11px] text-[var(--admin-muted)]">
                                    {numberFormat.format(users.total)} compte(s) • page {users.current_page}/{users.last_page}
                                </span>
                            ) : null}
                        </div>
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
                                <th>KYC</th>
                                <th>Compte</th>
                                <th>Score / Sécurité</th>
                                <th>Missions</th>
                                <th className="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td colSpan={8}>
                                        <EmptyState description="Aucun compte ne correspond à votre recherche." title="Liste vide" />
                                    </td>
                                </tr>
                            ) : (
                                rows.map((user) => (
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
                                                    <p className="text-xs text-[var(--admin-muted)]">
                                                        #{user.id} • {user.email ?? user.phone}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <RoleBadge role={user.role} />
                                        </td>
                                        <td>
                                            <KycStatusBadge status={user.kyc_status} />
                                        </td>
                                        <td>
                                            <div className="flex flex-col gap-1">
                                                <AccountStatusBadge status={user.account_status} />
                                                <span className="text-[10px] text-[var(--admin-muted)]">
                                                    {user.anonymized_at
                                                        ? 'Anonymisé (RGPD)'
                                                        : user.cgu_accepted_at
                                                            ? `CGU ${shortDate(user.cgu_accepted_at)}`
                                                            : 'CGU non tracées'}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="text-sm">
                                            <div className="flex flex-col gap-1">
                                                <div className="flex items-center gap-1.5">
                                                    <span className="font-semibold text-[var(--admin-text)]">
                                                        {user.score_prosartisan} pts
                                                    </span>
                                                    {user.score_frozen ? (
                                                        <span className="rounded-full bg-[#fbe0da] px-1.5 py-0.5 text-[10px] font-semibold text-[#c55e50] border border-[#f2c1ba]">
                                                            Gelé
                                                        </span>
                                                    ) : (
                                                        <span className="rounded-full bg-[#eef8f0] px-1.5 py-0.5 text-[10px] font-semibold text-[#24734f] border border-[#bfe0c8]">
                                                            Actif
                                                        </span>
                                                    )}
                                                </div>
                                                {user.device_fingerprint ? (
                                                    <span className="text-[10px] text-[var(--admin-muted)] truncate max-w-[120px]" title={user.device_fingerprint}>
                                                        IMEI: {user.device_fingerprint.slice(0, 10)}...
                                                    </span>
                                                ) : (
                                                    <span className="text-[10px] text-[var(--admin-muted)]">
                                                        Aucun device lié
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="text-sm text-[var(--admin-text-soft)]">
                                            Client: {user.missions_client_count} • Artisan: {user.missions_artisan_count}
                                        </td>
                                        <td>
                                            <div className="flex justify-end gap-2">
                                                {canManage ? (
                                                    <>
                                                        <button
                                                            type="button"
                                                            onClick={() => onEditUser(user)}
                                                            className={actionButtonClass('secondary')}
                                                            title="Modifier"
                                                        >
                                                            Modifier
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => onToggleUserStatus(user)}
                                                            className={actionButtonClass((user.account_status ?? 'actif') === 'actif' ? 'danger' : 'success')}
                                                            title={(user.account_status ?? 'actif') === 'actif' ? 'Suspendre' : 'Activer'}
                                                        >
                                                            {(user.account_status ?? 'actif') === 'actif' ? 'Suspendre' : 'Activer'}
                                                        </button>
                                                    </>
                                                ) : null}
                                                {canDelete ? (
                                                    <button
                                                        type="button"
                                                        onClick={() => onDeleteUser(user)}
                                                        className={actionButtonClass('danger')}
                                                        title="Supprimer"
                                                    >
                                                        Supprimer
                                                    </button>
                                                ) : null}
                                                {canViewRgpd && onOpenRgpd ? (
                                                    <button
                                                        type="button"
                                                        onClick={() => onOpenRgpd(user)}
                                                        className={actionButtonClass('secondary')}
                                                        title="Données personnelles (RGPD)"
                                                    >
                                                        RGPD
                                                    </button>
                                                ) : null}
                                                {canImpersonate && onImpersonate && user.role !== 'admin' ? (
                                                    <button
                                                        type="button"
                                                        onClick={() => onImpersonate(user)}
                                                        className={actionButtonClass('secondary')}
                                                        title="Se connecter en tant que cet utilisateur"
                                                    >
                                                        Usurper
                                                    </button>
                                                ) : null}
                                                {!canManage && !canDelete && !canViewRgpd && !canImpersonate ? (
                                                    <span className="text-xs text-[var(--admin-muted)]">Lecture seule</span>
                                                ) : null}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </DataTable>

                    {renderPagination(users?.links)}
                </Surface>

                <div className="space-y-5">
                    <Surface className="rounded-[32px] p-5 lg:p-6">
                        <SectionTitle
                            description="Demandes boutiques toujours en attente d’agrément."
                            title="Fournisseurs en attente"
                        />

                        <div className="mt-5 space-y-3">
                            {pendingFournisseurs.length === 0 ? (
                                <EmptyState description="Aucune boutique en attente." title="Tout est à jour" />
                            ) : (
                                pendingFournisseurs.map((fournisseur) => (
                                    <div key={fournisseur.id} className="rounded-[24px] border border-[var(--admin-border)] bg-white/60 p-4">
                                        <p className="text-sm font-semibold text-[var(--admin-text)]">{fournisseur.nom_boutique}</p>
                                        <p className="mt-1 text-sm text-[var(--admin-text-soft)]">{fournisseur.user?.name ?? 'Contact inconnu'}</p>
                                        <p className="text-xs text-[var(--admin-muted)]">{fournisseur.user?.phone ?? 'Téléphone non renseigné'}</p>
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
                        <SectionTitle description="Comptes artisans les mieux scorés pour les futures affectations." title="Top artisans" />
                        <div className="mt-5 space-y-3">
                            {topArtisans.map((artisan) => (
                                <div key={artisan.id} className="flex items-center justify-between rounded-[22px] border border-[var(--admin-border)] bg-white/60 px-4 py-3">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold text-[var(--admin-text)]">{artisan.name}</p>
                                        <p className="text-xs text-[var(--admin-muted)]">{artisan.phone}</p>
                                    </div>
                                    <span className={cn('rounded-full border px-3 py-1 text-xs font-semibold', toneBadgeClasses('green'))}>
                                        {artisan.score_prosartisan}/1000
                                    </span>
                                </div>
                            ))}
                        </div>
                    </Surface>
                </div>
            </div>
        </section>
    );
}
