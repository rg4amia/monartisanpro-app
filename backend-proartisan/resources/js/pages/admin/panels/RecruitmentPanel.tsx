// Onglet « Recrutement » du backoffice — modération des offres publiées par
// ProsArtisan, les clients et les fournisseurs, et réglages d'activation de
// la publication par espace mobile (client / fournisseur).

import { router, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useEffect, useState } from 'react';

import { DataTable, EmptyState, MetricCard, numberFormat, Surface, useConfirm } from '../shared';
import type { Paginated, RecruitmentOfferItem, RecruitmentSettings, RecruitmentStats } from '../shared';

interface RecruitmentApplicantRow {
    id: number;
    status: 'submitted' | 'shortlisted' | 'contacted' | 'rejected' | 'confirmed';
    matching_score: number | string | null;
    applied_at: string;
    artisan: { id: number; name: string; phone: string; score_prosartisan: number } | null;
}

const applicationStatusLabels: Record<string, string> = {
    submitted: 'Nouvelle',
    shortlisted: 'Présélectionné',
    contacted: 'Contacté',
    rejected: 'Rejeté',
    confirmed: 'Retenu',
};

const applicationStatusTone: Record<string, string> = {
    submitted: 'bg-slate-100 text-slate-600',
    shortlisted: 'bg-blue-100 text-blue-700',
    contacted: 'bg-amber-100 text-amber-700',
    rejected: 'bg-red-100 text-red-700',
    confirmed: 'bg-green-100 text-green-700',
};

function ApplicantsModal({ offer, onClose }: { offer: RecruitmentOfferItem; onClose: () => void }) {
    const [applications, setApplications] = useState<RecruitmentApplicantRow[] | null>(null);
    const [loading, setLoading] = useState(true);
    const [updatingId, setUpdatingId] = useState<number | null>(null);

    const load = () => {
        setLoading(true);
        fetch(`/admin/recruitment/${offer.id}/applications`, { headers: { Accept: 'application/json' } })
            .then((res) => res.json())
            .then((json) => setApplications(json.data ?? []))
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        load();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [offer.id]);

    const updateStatus = (application: RecruitmentApplicantRow, status: string) => {
        setUpdatingId(application.id);
        router.post(
            `/admin/recruitment/${offer.id}/applications/${application.id}/status`,
            { status },
            { preserveScroll: true, onFinish: () => { setUpdatingId(null); load(); } },
        );
    };

    return (
        <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" role="presentation" onClick={onClose}>
            <div
                role="dialog"
                aria-modal="true"
                className="admin-panel admin-surface w-full max-w-[600px] max-h-[85vh] overflow-y-auto rounded-[28px] border p-6 shadow-2xl"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-start justify-between gap-3 border-b border-[var(--admin-border)] pb-4">
                    <div>
                        <h2 className="text-lg font-bold text-[var(--admin-text)]">Candidatures reçues</h2>
                        <p className="mt-1 text-xs text-[var(--admin-text-soft)]">{offer.title}</p>
                    </div>
                    <button onClick={onClose} className="admin-button admin-button--ghost">
                        Fermer
                    </button>
                </div>

                <div className="mt-4 space-y-3">
                    {loading ? (
                        <p className="text-sm text-[var(--admin-text-soft)]">Chargement...</p>
                    ) : !applications || applications.length === 0 ? (
                        <EmptyState title="Aucune candidature" description="Aucun artisan n'a encore postulé à cette offre." />
                    ) : (
                        applications.map((application) => (
                            <div key={application.id} className="rounded-[20px] border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] p-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold text-[var(--admin-text)]">
                                            {application.artisan?.name ?? 'Artisan supprimé'}
                                        </p>
                                        <p className="text-xs text-[var(--admin-muted)]">{application.artisan?.phone ?? '—'}</p>
                                    </div>
                                    <span className={`rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap ${applicationStatusTone[application.status] ?? 'bg-slate-100 text-slate-600'}`}>
                                        {applicationStatusLabels[application.status] ?? application.status}
                                    </span>
                                </div>
                                <div className="mt-2 flex flex-wrap gap-3 text-xs text-[var(--admin-text-soft)]">
                                    <span>Score ProsArtisan : {application.artisan?.score_prosartisan ?? '—'}/1000</span>
                                    <span>Matching : {application.matching_score !== null ? `${application.matching_score}/100` : '—'}</span>
                                </div>
                                <div className="mt-3 flex flex-wrap gap-2">
                                    {(['shortlisted', 'contacted', 'confirmed', 'rejected'] as const).map((status) => (
                                        <button
                                            key={status}
                                            disabled={updatingId === application.id || application.status === status}
                                            onClick={() => updateStatus(application, status)}
                                            className="rounded-lg px-3 py-1.5 text-xs font-semibold border border-[var(--admin-border)] text-[var(--admin-text-soft)] hover:bg-[var(--admin-panel-strong)] disabled:opacity-40"
                                        >
                                            {applicationStatusLabels[status]}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        ))
                    )}
                </div>
            </div>
        </div>
    );
}

interface RecruitmentPanelProps {
    recruitmentOffersPage: Paginated<RecruitmentOfferItem> | null | undefined;
    recruitmentStats: RecruitmentStats;
    recruitmentSettings: RecruitmentSettings;
    search: string;
    onSearchChange: (value: string) => void;
    onSubmit: (event: FormEvent) => void;
    onReset: () => void;
    renderPagination: (links: Paginated<RecruitmentOfferItem>['links'] | undefined) => ReactNode;
    canManage: boolean;
}

const creatorTypeLabels: Record<string, string> = {
    admin: 'ProsArtisan',
    client: 'Client',
    fournisseur: 'Fournisseur',
};

const statusLabels: Record<string, string> = {
    draft: 'Brouillon',
    pending_review: 'En attente de modération',
    active: 'Active',
    filled: 'Pourvue',
    expired: 'Expirée',
    cancelled: 'Rejetée / annulée',
};

const statusTone: Record<string, string> = {
    draft: 'bg-slate-100 text-slate-600',
    pending_review: 'bg-amber-100 text-amber-700',
    active: 'bg-green-100 text-green-700',
    filled: 'bg-blue-100 text-blue-700',
    expired: 'bg-slate-100 text-slate-500',
    cancelled: 'bg-red-100 text-red-700',
};

function formatDate(value: string | null): string {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
}

export function RecruitmentPanel({
    recruitmentOffersPage,
    recruitmentStats,
    recruitmentSettings,
    search,
    onSearchChange,
    onSubmit,
    onReset,
    renderPagination,
    canManage,
}: RecruitmentPanelProps) {
    const rows = recruitmentOffersPage?.data ?? [];
    const { confirm, dialog } = useConfirm();
    const [rejectingId, setRejectingId] = useState<number | null>(null);
    const [applicantsOffer, setApplicantsOffer] = useState<RecruitmentOfferItem | null>(null);

    const { data, setData, post, processing, recentlySuccessful } = useForm({
        client_posting_enabled: recruitmentSettings.client_posting_enabled ?? '1',
        fournisseur_posting_enabled: recruitmentSettings.fournisseur_posting_enabled ?? '1',
    });

    const handleSubmitSettings = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/recruitment/settings', { preserveScroll: true });
    };

    const handleApprove = async (offer: RecruitmentOfferItem) => {
        const confirmed = await confirm({
            title: "Approuver l'offre",
            message: `Publier « ${offer.title} » sur l'espace artisan ?`,
            confirmLabel: 'Approuver',
        });
        if (!confirmed) return;
        router.post(`/admin/recruitment/${offer.id}/approve`, {}, { preserveScroll: true });
    };

    const handleReject = async (offer: RecruitmentOfferItem) => {
        const confirmed = await confirm({
            title: "Rejeter l'offre",
            message: `« ${offer.title} » ne sera pas publiée et restera visible côté recruteur comme rejetée.`,
            confirmLabel: 'Rejeter',
            tone: 'danger',
        });
        if (!confirmed) return;
        setRejectingId(offer.id);
        router.post(`/admin/recruitment/${offer.id}/reject`, {}, { preserveScroll: true, onFinish: () => setRejectingId(null) });
    };

    return (
        <section className="mt-5 space-y-5">
            {dialog}

            <div className="grid gap-4 xl:grid-cols-4">
                <MetricCard description="Toutes offres confondues" tone="slate" value={numberFormat.format(recruitmentStats.total)}>
                    Total des offres
                </MetricCard>
                <MetricCard description="Comptes non vérifiés, à modérer" tone="amber" value={numberFormat.format(recruitmentStats.pending_review)}>
                    En attente de modération
                </MetricCard>
                <MetricCard description="Visibles côté artisan" tone="green" value={numberFormat.format(recruitmentStats.active)}>
                    Offres actives
                </MetricCard>
                <MetricCard description="Recrutement finalisé" tone="blue" value={numberFormat.format(recruitmentStats.filled)}>
                    Offres pourvues
                </MetricCard>
            </div>

            <Surface className="rounded-[28px] p-4 lg:p-5">
                <form onSubmit={handleSubmitSettings} className="space-y-4">
                    <div className="flex flex-col gap-1 border-b border-[var(--admin-border)] pb-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 className="text-base font-bold text-[var(--admin-text)]">Publication par espace</h3>
                            <p className="text-xs text-[var(--admin-text-soft)] mt-0.5">
                                Autoriser les clients et/ou les fournisseurs à publier des offres de recrutement depuis leur espace mobile.
                            </p>
                        </div>
                        <button
                            type="submit"
                            disabled={!canManage || processing}
                            className="rounded-xl px-5 py-2.5 text-sm font-semibold bg-[#ebb95e] text-[#241b16] hover:bg-[#e0ab4b] disabled:opacity-50 shadow-sm whitespace-nowrap"
                        >
                            {processing ? 'Enregistrement...' : recentlySuccessful ? 'Enregistré ✓' : 'Enregistrer'}
                        </button>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)]">
                                Espace Client
                            </label>
                            <select
                                value={data.client_posting_enabled}
                                onChange={(e) => setData('client_posting_enabled', e.target.value)}
                                disabled={!canManage}
                                className="w-full rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none disabled:opacity-60"
                            >
                                <option value="1">Publication autorisée</option>
                                <option value="0">Publication désactivée</option>
                            </select>
                        </div>
                        <div>
                            <label className="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)]">
                                Espace Fournisseur
                            </label>
                            <select
                                value={data.fournisseur_posting_enabled}
                                onChange={(e) => setData('fournisseur_posting_enabled', e.target.value)}
                                disabled={!canManage}
                                className="w-full rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none disabled:opacity-60"
                            >
                                <option value="1">Publication autorisée</option>
                                <option value="0">Publication désactivée</option>
                            </select>
                        </div>
                    </div>
                </form>
            </Surface>

            <Surface className="rounded-[28px] p-4 lg:p-5">
                <form onSubmit={onSubmit} className="grid items-end gap-3 md:grid-cols-4">
                    <div className="md:col-span-3">
                        <label className="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)]">
                            Rechercher par titre
                        </label>
                        <input
                            type="text"
                            placeholder="Maçon, staff, électricien..."
                            value={search}
                            onChange={(e) => onSearchChange(e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                        />
                    </div>
                    <div className="flex gap-2">
                        <button
                            type="submit"
                            className="flex-1 rounded-xl px-4 py-2 text-xs font-semibold bg-[#ebb95e] text-[#241b16] hover:bg-[#e0ab4b]"
                        >
                            Filtrer
                        </button>
                        <button
                            type="button"
                            onClick={onReset}
                            className="flex-1 rounded-xl px-4 py-2 text-xs font-semibold border border-[var(--admin-border)] text-[var(--admin-text-soft)] hover:bg-[var(--admin-panel)]"
                        >
                            Réinitialiser
                        </button>
                    </div>
                </form>

                <div className="mt-5 overflow-x-auto">
                    {rows.length === 0 ? (
                        <EmptyState
                            title="Aucune offre de recrutement"
                            description="Les offres publiées par ProsArtisan, les clients et les fournisseurs apparaîtront ici."
                        />
                    ) : (
                        <DataTable>
                            <thead>
                                <tr>
                                    <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Offre</th>
                                    <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Recruteur</th>
                                    <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Commune</th>
                                    <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Candidatures</th>
                                    <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Période</th>
                                    <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Statut</th>
                                    {canManage && (
                                        <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Actions</th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--admin-border)]">
                                {rows.map((offer) => (
                                    <tr key={offer.id} className="hover:bg-white/10 dark:hover:bg-white/5 transition">
                                        <td className="py-3 px-4 text-xs text-[var(--admin-text)]">
                                            <p className="font-semibold">{offer.title}</p>
                                            <p className="text-[var(--admin-muted)]">{offer.trade?.name ?? '—'}</p>
                                        </td>
                                        <td className="py-3 px-4 text-xs text-[var(--admin-text-soft)]">
                                            <p>{offer.creator?.name ?? '—'}</p>
                                            <p className="text-[var(--admin-muted)]">{creatorTypeLabels[offer.creator_type]}</p>
                                        </td>
                                        <td className="py-3 px-4 text-xs text-[var(--admin-text-soft)]">
                                            {offer.commune}
                                            {offer.sous_quartier ? ` — ${offer.sous_quartier}` : ''}
                                        </td>
                                        <td className="py-3 px-4 text-xs">
                                            <button
                                                onClick={() => setApplicantsOffer(offer)}
                                                disabled={(offer.applications_count ?? 0) === 0}
                                                className="font-semibold text-[#8a6b3d] underline decoration-dotted underline-offset-2 hover:text-[#6f531f] disabled:text-[var(--admin-text-soft)] disabled:no-underline disabled:cursor-default"
                                            >
                                                {numberFormat.format(offer.applications_count ?? 0)}
                                            </button>
                                        </td>
                                        <td className="py-3 px-4 text-xs text-[var(--admin-text-soft)] whitespace-nowrap">
                                            {offer.date_debut || offer.deadline_at
                                                ? `${formatDate(offer.date_debut)} → ${formatDate(offer.deadline_at)}`
                                                : '—'}
                                        </td>
                                        <td className="py-3 px-4 text-xs">
                                            <span className={`rounded-full px-2.5 py-1 font-semibold ${statusTone[offer.status] ?? 'bg-slate-100 text-slate-600'}`}>
                                                {statusLabels[offer.status] ?? offer.status}
                                            </span>
                                        </td>
                                        {canManage && (
                                            <td className="py-3 px-4 text-xs whitespace-nowrap">
                                                <div className="flex flex-wrap gap-2">
                                                    <button
                                                        onClick={() => setApplicantsOffer(offer)}
                                                        disabled={(offer.applications_count ?? 0) === 0}
                                                        className="rounded-lg px-3 py-1.5 font-semibold bg-[#f3e6cf] text-[#8a6b3d] hover:bg-[#ecd9b3] disabled:opacity-40 disabled:cursor-default"
                                                    >
                                                        Voir candidats
                                                    </button>
                                                    {offer.status === 'pending_review' && (
                                                        <>
                                                            <button
                                                                onClick={() => handleApprove(offer)}
                                                                className="rounded-lg px-3 py-1.5 font-semibold bg-green-100 text-green-700 hover:bg-green-200"
                                                            >
                                                                Approuver
                                                            </button>
                                                            <button
                                                                onClick={() => handleReject(offer)}
                                                                disabled={rejectingId === offer.id}
                                                                className="rounded-lg px-3 py-1.5 font-semibold bg-red-100 text-red-700 hover:bg-red-200 disabled:opacity-50"
                                                            >
                                                                Rejeter
                                                            </button>
                                                        </>
                                                    )}
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </DataTable>
                    )}
                </div>

                {renderPagination(recruitmentOffersPage?.links)}
            </Surface>

            {applicantsOffer && <ApplicantsModal offer={applicantsOffer} onClose={() => setApplicantsOffer(null)} />}
        </section>
    );
}
