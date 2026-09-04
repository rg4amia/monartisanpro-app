// Onglet « Communications » du backoffice — extrait de console.tsx (Chantier C2).

import { router } from '@inertiajs/react';

import { cn } from '@/lib/utils';

import { BellIcon, CheckCircleIcon, DataTable, EmptyState, PlusIcon, SearchIcon, SectionTitle, Surface } from '../shared';

type Communication = any;

interface CommunicationsPanelProps {
    communications: Communication[];
    filteredCommunications: Communication[];
    search: string;
    onSearchChange: (value: string) => void;
    commTypeFilter: string;
    onCommTypeFilterChange: (value: string) => void;
    commStatusFilter: string;
    onCommStatusFilterChange: (value: string) => void;
    onCreate: () => void;
    onEdit: (comm: Communication) => void;
}

function handleCommAction(action: string, comm: Communication) {
    if (action === 'edit') return;
    if (action === 'publish') {
        if (window.confirm('Publier cette communication ?')) {
            router.post(`/admin/communications/${comm.id}/publish`, {}, { preserveScroll: true });
        }
    } else if (action === 'cloturer') {
        if (window.confirm('Clôturer (désactiver) cette publication ?')) {
            router.post(`/admin/communications/${comm.id}/cloturer`, {}, { preserveScroll: true });
        }
    } else if (action === 'delete') {
        if (window.confirm('Supprimer définitivement cette communication ?')) {
            router.delete(`/admin/communications/${comm.id}`, { preserveScroll: true });
        }
    }
}

export function CommunicationsPanel({
    communications,
    filteredCommunications,
    search,
    onSearchChange,
    commTypeFilter,
    onCommTypeFilterChange,
    commStatusFilter,
    onCommStatusFilterChange,
    onCreate,
    onEdit,
}: CommunicationsPanelProps) {
    const list = communications ?? [];

    return (
        <section className="mt-5 space-y-6">
            {/* Metric Cards Summary Header */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <Surface className="rounded-[28px] p-5 border border-[var(--admin-border)] bg-gradient-to-br from-amber-500/10 via-amber-500/5 to-transparent">
                    <div className="flex items-center justify-between">
                        <span className="text-[11px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">Total Publications</span>
                        <div className="p-2 rounded-xl bg-amber-500/15 text-amber-600">
                            <BellIcon className="h-5 w-5" />
                        </div>
                    </div>
                    <p className="mt-3 text-3xl font-extrabold text-[var(--admin-text)]">{list.length}</p>
                    <p className="mt-1 text-xs text-[var(--admin-text-soft)]">Toutes catégories confondues</p>
                </Surface>

                <Surface className="rounded-[28px] p-5 border border-[var(--admin-border)] bg-gradient-to-br from-emerald-500/10 via-emerald-500/5 to-transparent">
                    <div className="flex items-center justify-between">
                        <span className="text-[11px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Astuces &quot;Le saviez-vous ?&quot;</span>
                        <div className="p-2 rounded-xl bg-emerald-500/15 text-emerald-600">
                            <span className="text-base">💡</span>
                        </div>
                    </div>
                    <p className="mt-3 text-3xl font-extrabold text-[var(--admin-text)]">
                        {list.filter((c: Communication) => c.type === 'le_saviez_vous').length}
                    </p>
                    <p className="mt-1 text-xs text-[var(--admin-text-soft)]">Conseils &amp; astuces pratiques</p>
                </Surface>

                <Surface className="rounded-[28px] p-5 border border-[var(--admin-border)] bg-gradient-to-br from-blue-500/10 via-blue-500/5 to-transparent">
                    <div className="flex items-center justify-between">
                        <span className="text-[11px] font-bold uppercase tracking-wider text-blue-700 dark:text-blue-400">Annonces Intérieures</span>
                        <div className="p-2 rounded-xl bg-blue-500/15 text-blue-600">
                            <span className="text-base">📢</span>
                        </div>
                    </div>
                    <p className="mt-3 text-3xl font-extrabold text-[var(--admin-text)]">
                        {list.filter((c: Communication) => c.type === 'annonce').length}
                    </p>
                    <p className="mt-1 text-xs text-[var(--admin-text-soft)]">Communiqués &amp; alertes plateforme</p>
                </Surface>

                <Surface className="rounded-[28px] p-5 border border-[var(--admin-border)] bg-gradient-to-br from-green-500/10 via-green-500/5 to-transparent">
                    <div className="flex items-center justify-between">
                        <span className="text-[11px] font-bold uppercase tracking-wider text-green-700 dark:text-green-400">Actives / Publiées</span>
                        <div className="p-2 rounded-xl bg-green-500/15 text-green-600">
                            <CheckCircleIcon className="h-5 w-5" />
                        </div>
                    </div>
                    <p className="mt-3 text-3xl font-extrabold text-[var(--admin-text)]">
                        {list.filter((c: Communication) => c.statut === 'publie').length}
                    </p>
                    <p className="mt-1 text-xs text-[var(--admin-text-soft)]">Visibles par les utilisateurs</p>
                </Surface>
            </div>

            {/* Main Content Surface */}
            <Surface className="rounded-[32px] p-5 lg:p-6 shadow-xl">
                {/* Header & Controls Toolbar */}
                <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-[var(--admin-border)] pb-5 mb-5">
                    <SectionTitle
                        title="Gestion des Communications & Astuces"
                        description="Publiez des annonces ou des conseils &quot;Le saviez-vous ?&quot; ciblés par rôle."
                    />
                    <button
                        type="button"
                        onClick={onCreate}
                        className="self-start lg:self-center rounded-full bg-[#ebb95e] text-[#241b16] px-5 py-2.5 text-sm font-bold hover:opacity-90 transition flex items-center gap-2 shadow-md hover:shadow-lg active:scale-95 transform"
                    >
                        <PlusIcon className="h-4 w-4" /> Nouvelle publication
                    </button>
                </div>

                {/* Filter Bar */}
                <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 mb-6">
                    <div className="relative flex-1">
                        <SearchIcon className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-[var(--admin-muted)]" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => onSearchChange(e.target.value)}
                            placeholder="Rechercher par titre ou contenu..."
                            className="admin-input w-full rounded-full pl-10 pr-4 py-2 text-xs outline-none"
                        />
                    </div>

                    <select
                        value={commTypeFilter}
                        onChange={(e) => onCommTypeFilterChange(e.target.value)}
                        className="admin-input rounded-full px-4 py-2 text-xs outline-none cursor-pointer bg-transparent"
                    >
                        <option value="all">Tous les types</option>
                        <option value="le_saviez_vous">💡 Le saviez-vous ?</option>
                        <option value="annonce">📢 Annonce</option>
                    </select>

                    <select
                        value={commStatusFilter}
                        onChange={(e) => onCommStatusFilterChange(e.target.value)}
                        className="admin-input rounded-full px-4 py-2 text-xs outline-none cursor-pointer bg-transparent"
                    >
                        <option value="all">Tous les statuts</option>
                        <option value="publie">Publié</option>
                        <option value="brouillon">Brouillon</option>
                        <option value="cloture">Clôturé</option>
                    </select>
                </div>

                {/* Mobile Cards View (< md) */}
                <div className="block md:hidden space-y-4">
                    {filteredCommunications.length === 0 ? (
                        <EmptyState
                            title="Aucune communication trouvée"
                            description="Ajustez vos filtres ou créez une nouvelle publication."
                        />
                    ) : (
                        filteredCommunications.map((comm: Communication) => (
                            <div
                                key={comm.id}
                                className="rounded-2xl border border-[var(--admin-border)] bg-white/50 dark:bg-white/5 p-4 space-y-3 transition hover:shadow-md"
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <span className={cn(
                                        'rounded-full px-2.5 py-1 text-[11px] font-bold border whitespace-nowrap inline-flex items-center gap-1.5',
                                        comm.type === 'le_saviez_vous'
                                            ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                            : 'border-blue-500/40 bg-blue-500/10 text-blue-600 dark:text-blue-400',
                                    )}>
                                        <span>{comm.type === 'le_saviez_vous' ? '💡' : '📢'}</span>
                                        <span>{comm.type === 'le_saviez_vous' ? 'Le saviez-vous ?' : 'Annonce'}</span>
                                    </span>

                                    <span className={cn(
                                        'rounded-full px-2.5 py-0.5 text-[10px] font-extrabold border uppercase tracking-wider',
                                        comm.statut === 'publie' ? 'border-green-600/40 bg-green-500/15 text-green-700 dark:text-green-400'
                                            : comm.statut === 'cloture' ? 'border-gray-600/40 bg-gray-500/15 text-gray-600 dark:text-gray-400'
                                                : 'border-amber-600/40 bg-amber-500/15 text-amber-700 dark:text-amber-400',
                                    )}>
                                        {comm.statut}
                                    </span>
                                </div>

                                <div>
                                    <h4 className="font-bold text-sm text-[var(--admin-text)] leading-snug">{comm.titre}</h4>
                                    <p className="text-xs text-[var(--admin-text-soft)] mt-1 line-clamp-2 leading-relaxed">{comm.contenu}</p>
                                </div>

                                <div className="flex flex-wrap gap-1.5 pt-1">
                                    <span className="text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)] self-center mr-1">Cibles:</span>
                                    {comm.cibles_json.map((role: string) => (
                                        <span key={role} className="rounded-md bg-amber-500/10 border border-amber-500/20 text-amber-800 dark:text-amber-300 text-[10px] font-semibold px-2 py-0.5 capitalize">
                                            {role}
                                        </span>
                                    ))}
                                </div>

                                <div className="pt-2 border-t border-[var(--admin-border)] flex items-center justify-between text-[11px] text-[var(--admin-text-soft)]">
                                    <div>
                                        <span className="font-medium text-[var(--admin-text)]">{comm.auteur?.name || 'Système'}</span>
                                        <span className="mx-1">•</span>
                                        <span>{new Date(comm.created_at).toLocaleDateString('fr-FR')}</span>
                                    </div>

                                    <select
                                        className="admin-input rounded-xl text-xs py-1 px-2.5 outline-none cursor-pointer"
                                        defaultValue=""
                                        onChange={(e) => {
                                            const action = e.target.value;
                                            if (action === 'edit') onEdit(comm);
                                            else handleCommAction(action, comm);
                                            e.target.value = '';
                                        }}
                                    >
                                        <option value="" disabled>Actions...</option>
                                        {comm.statut === 'brouillon' && <option value="edit">Modifier</option>}
                                        {comm.statut === 'brouillon' && <option value="publish">Publier</option>}
                                        {comm.statut === 'publie' && <option value="cloturer">Désactiver</option>}
                                        {comm.statut === 'brouillon' && <option value="delete">Supprimer</option>}
                                    </select>
                                </div>
                            </div>
                        ))
                    )}
                </div>

                {/* Desktop Table View (>= md) */}
                <div className="hidden md:block overflow-x-auto">
                    <DataTable>
                        <thead>
                            <tr>
                                <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Titre & Contenu</th>
                                <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Type</th>
                                <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Cibles</th>
                                <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Statut</th>
                                <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Auteur</th>
                                <th className="py-3 px-4 text-left text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Dates</th>
                                <th className="py-3 px-4 text-right text-xs font-bold uppercase tracking-wider text-[var(--admin-muted)]">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[var(--admin-border)]">
                            {filteredCommunications.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="text-center py-10 text-[var(--admin-muted)]">
                                        Aucune communication enregistrée ou correspondant à vos critères de recherche.
                                    </td>
                                </tr>
                            ) : (
                                filteredCommunications.map((comm: Communication) => (
                                    <tr key={comm.id} className="hover:bg-white/10 dark:hover:bg-white/5 transition">
                                        <td className="py-3.5 px-4 font-semibold text-[var(--admin-text)] max-w-xs">
                                            <div className="font-bold text-sm leading-tight text-[var(--admin-text)]">{comm.titre}</div>
                                            <div className="text-xs text-[var(--admin-text-soft)] font-normal line-clamp-1 mt-1">{comm.contenu}</div>
                                        </td>
                                        <td className="py-3.5 px-4 whitespace-nowrap">
                                            <span className={cn(
                                                'rounded-full px-3 py-1 text-xs font-bold border whitespace-nowrap inline-flex items-center gap-1.5',
                                                comm.type === 'le_saviez_vous'
                                                    ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                                    : 'border-blue-500/40 bg-blue-500/10 text-blue-700 dark:text-blue-400',
                                            )}>
                                                <span>{comm.type === 'le_saviez_vous' ? '💡' : '📢'}</span>
                                                <span>{comm.type === 'le_saviez_vous' ? 'Le saviez-vous ?' : 'Annonce'}</span>
                                            </span>
                                        </td>
                                        <td className="py-3.5 px-4 text-xs">
                                            <div className="flex flex-wrap gap-1">
                                                {comm.cibles_json.map((role: string) => (
                                                    <span key={role} className="rounded-md bg-amber-500/10 border border-amber-500/20 text-amber-900 dark:text-amber-300 text-[10px] font-bold px-2 py-0.5 capitalize">
                                                        {role}
                                                    </span>
                                                ))}
                                            </div>
                                        </td>
                                        <td className="py-3.5 px-4 whitespace-nowrap">
                                            <span className={cn(
                                                'rounded-full px-2.5 py-0.5 text-xs font-extrabold border uppercase tracking-wider',
                                                comm.statut === 'publie' ? 'border-green-600/40 bg-green-500/15 text-green-700 dark:text-green-400'
                                                    : comm.statut === 'cloture' ? 'border-gray-600/40 bg-gray-500/15 text-gray-600 dark:text-gray-400'
                                                        : 'border-amber-600/40 bg-amber-500/15 text-amber-700 dark:text-amber-400',
                                            )}>
                                                {comm.statut}
                                            </span>
                                        </td>
                                        <td className="py-3.5 px-4 text-xs font-medium text-[var(--admin-text)]">
                                            {comm.auteur?.name || 'Système'}
                                        </td>
                                        <td className="py-3.5 px-4 text-xs text-[var(--admin-text-soft)] whitespace-nowrap">
                                            <div>Créé : <span className="font-semibold">{new Date(comm.created_at).toLocaleDateString('fr-FR')}</span></div>
                                            {comm.publie_at && <div>Publié : <span className="font-semibold">{new Date(comm.publie_at).toLocaleDateString('fr-FR')}</span></div>}
                                            {comm.cloture_at && <div>Clôturé : <span className="font-semibold">{new Date(comm.cloture_at).toLocaleDateString('fr-FR')}</span></div>}
                                        </td>
                                        <td className="py-3.5 px-4 text-right whitespace-nowrap">
                                            <div className="flex justify-end">
                                                <select
                                                    className="admin-input rounded-xl text-xs py-1.5 px-3 outline-none cursor-pointer font-semibold shadow-sm"
                                                    defaultValue=""
                                                    onChange={(e) => {
                                                        const action = e.target.value;
                                                        if (action === 'edit') onEdit(comm);
                                                        else handleCommAction(action, comm);
                                                        e.target.value = '';
                                                    }}
                                                >
                                                    <option value="" disabled>Actions...</option>
                                                    {comm.statut === 'brouillon' && <option value="edit">Modifier</option>}
                                                    {comm.statut === 'brouillon' && <option value="publish">Publier</option>}
                                                    {comm.statut === 'publie' && <option value="cloturer">Désactiver</option>}
                                                    {comm.statut === 'brouillon' && <option value="delete">Supprimer</option>}
                                                </select>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </DataTable>
                </div>
            </Surface>
        </section>
    );
}
