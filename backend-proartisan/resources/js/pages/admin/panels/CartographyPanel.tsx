import React, { useState, useEffect, useCallback, useTransition } from 'react';
import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import {
    money,
    shortDate,
    MetricCard,
    Surface,
    SectionTitle,
    toneBadgeClasses,
    ErrorBoundary,
} from '../shared';
import type {
    TerritorySummary,
    DistrictHeatmapItem,
    CommuneHeatmapItem,
    DistrictListItem,
    CommuneListItem,
    TerritoryEntityItem,
} from '../shared/types';
import { IvoryCoastMapSvg } from './IvoryCoastMapSvg';
import type { CityGeoData } from './ivoryCoastGeoData';

export interface CartographyPanelProps {
    territorySummary?: TerritorySummary;
    districtsHeatmap?: Record<string, DistrictHeatmapItem>;
    communesHeatmap?: Record<string, CommuneHeatmapItem>;
    districtsList?: DistrictListItem[];
    communesList?: CommuneListItem[];
    entities?: {
        data: TerritoryEntityItem[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
    };
    filters?: {
        district?: string | null;
        commune?: string | null;
        entity_type?: string;
        search?: string;
    };
}

const defaultSummary: TerritorySummary = {
    zone: { type: 'national', slug: 'all', name: "Toute la Côte d'Ivoire (Vue Nationale)" },
    actors: { clients: 0, artisans: 0, artisans_kyc_actif: 0, fournisseurs: 0, livreurs: 0, total_actors: 0 },
    missions: { total: 0, en_cours: 0, completed: 0, terminee: 0, disputed: 0, litige: 0, realization_rate: 0, dispute_rate: 0, total_volume_fcfa: 0, financial_volume_fcfa: 0 },
    reputation: { avg_rating: 0, average_artisan_rating: 0, avg_score_prosartisan: 0, total_reviews: 0 },
};

const defaultEntities = { data: [], current_page: 1, last_page: 1, total: 0, per_page: 15 };

function getActorCount(actor: any): number {
    if (typeof actor === 'number') return actor;
    if (actor && typeof actor === 'object') {
        if (typeof actor.total === 'number') return actor.total;
        if (typeof actor.count === 'number') return actor.count;
    }
    return 0;
}

export function CartographyPanel({
    territorySummary: initialSummary,
    districtsHeatmap = {},
    communesHeatmap = {},
    districtsList = [],
    communesList = [],
    entities: initialEntities,
    filters: initialFilters,
}: CartographyPanelProps) {
    const [selectedDistrict, setSelectedDistrict] = useState<string | null>(initialFilters?.district ?? null);
    const [selectedCommune, setSelectedCommune] = useState<string | null>(initialFilters?.commune ?? null);
    const [viewMode, setViewMode] = useState<'national' | 'abidjan'>(
        initialFilters?.commune || initialFilters?.district === 'abidjan' ? 'abidjan' : 'national'
    );
    const [entityType, setEntityType] = useState<string>(initialFilters?.entity_type ?? 'all');
    const [searchQuery, setSearchQuery] = useState<string>(initialFilters?.search ?? '');
    const [summary, setSummary] = useState<TerritorySummary>(initialSummary ?? defaultSummary);
    const [entities, setEntities] = useState(initialEntities ?? defaultEntities);
    const [currentPage, setCurrentPage] = useState<number>(initialEntities?.current_page ?? 1);
    const [isLoading, setIsLoading] = useState<boolean>(false);
    const [, startTransition] = useTransition();

    useEffect(() => {
        if (initialSummary) setSummary(initialSummary);
    }, [initialSummary]);

    useEffect(() => {
        if (initialEntities) setEntities(initialEntities);
    }, [initialEntities]);

    // Récupération asynchrone des statistiques et entités selon les filtres
    const fetchTerritoryStats = useCallback(
        async (params: {
            district?: string | null;
            commune?: string | null;
            entityType?: string;
            search?: string;
            page?: number;
        }) => {
            setIsLoading(true);
            try {
                const query = new URLSearchParams();
                if (params.district) query.set('district', params.district);
                if (params.commune) query.set('commune', params.commune);
                if (params.entityType && params.entityType !== 'all') query.set('entity_type', params.entityType);
                if (params.search) query.set('search', params.search);
                if (params.page && params.page > 1) query.set('page', params.page.toString());

                const res = await fetch(`/admin/cartographie/stats?${query.toString()}`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (res.ok) {
                    const data = await res.json();
                    startTransition(() => {
                        if (data.summary) setSummary(data.summary);
                        if (data.entities) setEntities(data.entities);
                    });
                }
            } catch (err) {
                console.error('Erreur lors du chargement des statistiques cartographiques', err);
            } finally {
                setIsLoading(false);
            }
        },
        []
    );

    // Déclencheur lors de la sélection d'un District
    const handleSelectDistrict = (slug: string) => {
        if (selectedDistrict === slug && !selectedCommune) {
            setSelectedDistrict(null);
            setSelectedCommune(null);
            setCurrentPage(1);
            fetchTerritoryStats({ district: null, commune: null, entityType, search: searchQuery, page: 1 });
            return;
        }

        setSelectedDistrict(slug);
        setSelectedCommune(null);
        setCurrentPage(1);

        if (slug === 'abidjan') {
            setViewMode('abidjan');
        }

        fetchTerritoryStats({ district: slug, commune: null, entityType, search: searchQuery, page: 1 });
    };

    // Déclencheur lors de la sélection d'une Commune
    const handleSelectCommune = (slug: string) => {
        if (selectedCommune === slug) {
            setSelectedCommune(null);
            setCurrentPage(1);
            fetchTerritoryStats({ district: selectedDistrict, commune: null, entityType, search: searchQuery, page: 1 });
            return;
        }

        setSelectedCommune(slug);
        setCurrentPage(1);
        fetchTerritoryStats({ district: selectedDistrict, commune: slug, entityType, search: searchQuery, page: 1 });
    };

    // Déclencheur lors de la sélection d'une Ville / Pôle urbain
    const handleSelectCity = (city: CityGeoData) => {
        setSelectedDistrict(city.districtSlug);
        setSelectedCommune(null);
        setSearchQuery(city.name);
        setCurrentPage(1);
        fetchTerritoryStats({ district: city.districtSlug, commune: null, entityType, search: city.name, page: 1 });
    };

    // Changement d'onglet d'entité
    const handleEntityTypeChange = (type: string) => {
        setEntityType(type);
        setCurrentPage(1);
        fetchTerritoryStats({ district: selectedDistrict, commune: selectedCommune, entityType: type, search: searchQuery, page: 1 });
    };

    // Recherche textuelle
    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setCurrentPage(1);
        fetchTerritoryStats({ district: selectedDistrict, commune: selectedCommune, entityType, search: searchQuery, page: 1 });
    };

    // Pagination
    const handlePageChange = (newPage: number) => {
        setCurrentPage(newPage);
        fetchTerritoryStats({ district: selectedDistrict, commune: selectedCommune, entityType, search: searchQuery, page: newPage });
    };

    // Réinitialisation globale
    const handleResetFilters = () => {
        setSelectedDistrict(null);
        setSelectedCommune(null);
        setViewMode('national');
        setEntityType('all');
        setSearchQuery('');
        setCurrentPage(1);
        fetchTerritoryStats({ district: null, commune: null, entityType: 'all', search: '', page: 1 });
    };

    // Commutateur de mode
    const handleSwitchViewMode = (mode: 'national' | 'abidjan') => {
        setViewMode(mode);
        if (mode === 'abidjan') {
            setSelectedDistrict('abidjan');
            fetchTerritoryStats({ district: 'abidjan', commune: selectedCommune, entityType, search: searchQuery, page: 1 });
        }
    };

    // Filtrer les communes disponibles dans le dropdown selon le district
    const filteredCommunesList = selectedDistrict
        ? communesList.filter((c) => (c.district_slug || 'abidjan') === selectedDistrict || selectedDistrict === 'abidjan')
        : communesList;

    const safeEntities = entities ?? defaultEntities;
    const safeEntitiesData = safeEntities.data ?? [];
    const disputeCount = summary?.missions?.litige ?? summary?.missions?.disputed ?? 0;
    const zoneName = summary?.zone?.name ?? "Toute la Côte d'Ivoire (Vue Nationale)";

    return (
        <ErrorBoundary fallbackTitle="Module Cartographique">
            <div className="space-y-6">
                {/* 1. Barre de Contrôle & Sélecteurs Territoriaux */}
                <Surface className="p-4 sm:p-5">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div className="flex flex-wrap items-center gap-2.5">
                            <div className="flex items-center gap-2">
                                <span className="text-xl">📍</span>
                                <div>
                                    <h2 className="text-base font-bold text-[var(--admin-text)]">
                                        {zoneName}
                                    </h2>
                                    <p className="text-xs text-[var(--admin-muted)]">
                                        {summary?.zone?.type === 'district' && "District Régional • Vue consolidée départementale"}
                                        {summary?.zone?.type === 'commune' && "Commune Locale • Suivi terrain et réseau de proximité"}
                                        {(summary?.zone?.type === 'national' || !summary?.zone?.type) && "Territoire National • 14 Districts • Échelle Côte d'Ivoire"}
                                    </p>
                                </div>
                            </div>

                            {isLoading && (
                                <span className="flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800 border border-amber-200 animate-pulse">
                                    <span className="h-2 w-2 rounded-full bg-amber-500" />
                                    Synchronisation...
                                </span>
                            )}
                        </div>

                        <div className="flex flex-wrap items-center gap-2.5">
                            {/* Dropdown District */}
                            <div className="relative">
                                <select
                                    value={selectedDistrict ?? ''}
                                    onChange={(e) => handleSelectDistrict(e.target.value || '')}
                                    className="h-9 rounded-xl border border-[var(--admin-border)] bg-[var(--admin-card-bg)] px-3 text-xs font-semibold text-[var(--admin-text)] focus:outline-none focus:ring-2 focus:ring-[#ebb95e]"
                                >
                                    <option value="">Tous les Districts (National)</option>
                                    {districtsList.map((d) => {
                                        const slug = d.slug || d.id;
                                        return (
                                            <option key={slug} value={slug}>
                                                {d.name}
                                            </option>
                                        );
                                    })}
                                </select>
                            </div>

                            {/* Dropdown Commune */}
                            <div className="relative">
                                <select
                                    value={selectedCommune ?? ''}
                                    onChange={(e) => handleSelectCommune(e.target.value || '')}
                                    className="h-9 rounded-xl border border-[var(--admin-border)] bg-[var(--admin-card-bg)] px-3 text-xs font-semibold text-[var(--admin-text)] focus:outline-none focus:ring-2 focus:ring-[#ebb95e]"
                                >
                                    <option value="">Toutes les Communes</option>
                                    {filteredCommunesList.map((c) => {
                                        const slug = c.slug || c.name.toLowerCase();
                                        return (
                                            <option key={slug} value={slug}>
                                                {c.name} {c.district_slug ? `(${c.district_slug})` : ''}
                                            </option>
                                        );
                                    })}
                                </select>
                            </div>

                            {/* Bouton Réinitialiser */}
                            {(selectedDistrict || selectedCommune || entityType !== 'all' || searchQuery) && (
                                <button
                                    type="button"
                                    onClick={handleResetFilters}
                                    className="h-9 rounded-xl border border-[var(--admin-border)] bg-white/60 px-3 text-xs font-semibold text-[var(--admin-text-soft)] hover:bg-white hover:text-[var(--admin-text)] transition"
                                >
                                    Réinitialiser
                                </button>
                            )}
                        </div>
                    </div>
                </Surface>

                {/* 2. KPI Cards Métier : Situation des 4 Acteurs & Performance Terrain */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-4">
                    {/* Clients */}
                    <MetricCard
                        title="Clients Inscrits"
                        value={getActorCount(summary?.actors?.clients).toString()}
                        description={`${(summary?.actors?.clients as any)?.with_active_missions ?? 0} avec mission active`}
                        tone="blue"
                    />

                    {/* Artisans */}
                    <MetricCard
                        title="Artisans du Réseau"
                        value={getActorCount(summary?.actors?.artisans).toString()}
                        description={`${(summary?.actors?.artisans as any)?.kyc_actif ?? summary?.actors?.artisans_kyc_actif ?? 0} KYC validés (${(summary?.actors?.artisans as any)?.kyc_actif_percent ?? 0}%)`}
                        tone="amber"
                    />

                    {/* Fournisseurs (Quincailleries) */}
                    <MetricCard
                        title="Quincailleries Agréées"
                        value={getActorCount(summary?.actors?.fournisseurs).toString()}
                        description={`${(summary?.actors?.fournisseurs as any)?.agreed ?? getActorCount(summary?.actors?.fournisseurs)} boutiques certifiées J-Code`}
                        tone="green"
                    />

                    {/* Livreurs */}
                    <MetricCard
                        title="Livreurs Partenaires"
                        value={getActorCount(summary?.actors?.livreurs).toString()}
                        description={`${(summary?.actors?.livreurs as any)?.active_courses ?? 0} courses en transit`}
                        tone="slate"
                    />
                </div>

                {/* 3. KPI Performance Opérationnelle : Réalisation, Litiges & Volume Financier */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-4">
                    {/* Missions */}
                    <MetricCard
                        title="Missions Totales"
                        value={(summary?.missions?.total ?? 0).toString()}
                        description={`${summary?.missions?.en_cours ?? 0} en cours • ${summary?.missions?.terminee ?? summary?.missions?.completed ?? 0} terminées`}
                        tone="amber"
                    />

                    {/* Taux de Réalisation */}
                    <div className="rounded-2xl border border-[var(--admin-border)] bg-[var(--admin-card-bg)] p-4 shadow-sm flex flex-col justify-between">
                        <div>
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">
                                    Taux de Réalisation
                                </span>
                                <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">
                                    Objectif 85%
                                </span>
                            </div>
                            <p className="mt-1 text-2xl font-bold text-emerald-700">
                                {summary?.missions?.realization_rate ?? 0}%
                            </p>
                        </div>
                        <div className="mt-3">
                            <div className="h-2 w-full rounded-full bg-emerald-100 overflow-hidden">
                                <div
                                    className="h-full bg-emerald-500 rounded-full transition-all duration-500"
                                    style={{ width: `${Math.min(summary?.missions?.realization_rate ?? 0, 100)}%` }}
                                />
                            </div>
                            <p className="mt-1.5 text-[11px] text-[var(--admin-muted)]">
                                {summary?.missions?.terminee ?? summary?.missions?.completed ?? 0} chantiers menés à terme
                            </p>
                        </div>
                    </div>

                    {/* Taux de Litiges */}
                    <div className="rounded-2xl border border-[var(--admin-border)] bg-[var(--admin-card-bg)] p-4 shadow-sm flex flex-col justify-between">
                        <div>
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">
                                    Taux de Litiges
                                </span>
                                <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${
                                    (summary?.missions?.dispute_rate ?? 0) > 5
                                        ? 'bg-rose-100 text-rose-800'
                                        : 'bg-slate-100 text-slate-700'
                                }`}>
                                    {disputeCount} litige{disputeCount > 1 ? 's' : ''}
                                </span>
                            </div>
                            <p className={`mt-1 text-2xl font-bold ${
                                (summary?.missions?.dispute_rate ?? 0) > 5 ? 'text-rose-600' : 'text-slate-800'
                            }`}>
                                {summary?.missions?.dispute_rate ?? 0}%
                            </p>
                        </div>
                        <div className="mt-3">
                            <div className="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                                <div
                                    className={`h-full rounded-full transition-all duration-500 ${
                                        (summary?.missions?.dispute_rate ?? 0) > 5 ? 'bg-rose-500' : 'bg-amber-400'
                                    }`}
                                    style={{ width: `${Math.min((summary?.missions?.dispute_rate ?? 0) * 4, 100)}%` }}
                                />
                            </div>
                            <p className="mt-1.5 text-[11px] text-[var(--admin-muted)]">
                                {disputeCount === 0
                                    ? 'Aucun litige actif sur la zone'
                                    : 'Sous supervision du Référent'}
                            </p>
                        </div>
                    </div>

                    {/* Volume Financier Séquestre */}
                    <MetricCard
                        title="Volume Financier"
                        value={money(summary?.missions?.financial_volume_fcfa ?? summary?.missions?.total_volume_fcfa ?? 0)}
                        description={`Note artisan moyenne : ${summary?.reputation?.average_artisan_rating ?? summary?.reputation?.avg_rating ?? 0}/5 (${summary?.reputation?.total_reviews ?? 0} avis)`}
                        tone="green"
                    />
                </div>

                {/* 4. Carte Interactive SVG de Côte d'Ivoire & Grand Abidjan */}
                <IvoryCoastMapSvg
                    viewMode={viewMode}
                    selectedDistrict={selectedDistrict}
                    selectedCommune={selectedCommune}
                    districtsHeatmap={districtsHeatmap}
                    communesHeatmap={communesHeatmap}
                    onSelectDistrict={handleSelectDistrict}
                    onSelectCommune={handleSelectCommune}
                    onSwitchViewMode={handleSwitchViewMode}
                    onSelectCity={handleSelectCity}
                />

                {/* 5. Tableau Dynamique Récapitulatif Actualisé à Chaque Filtre */}
                <Surface className="p-4 sm:p-6 space-y-4">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-[var(--admin-border)] pb-4">
                        <div>
                            <SectionTitle>Tableau Récapitulatif Territorial</SectionTitle>
                            <p className="text-xs text-[var(--admin-muted)] mt-0.5">
                                {safeEntities.total ?? 0} enregistrement{(safeEntities.total ?? 0) > 1 ? 's' : ''} répertorié{(safeEntities.total ?? 0) > 1 ? 's' : ''} dans la zone sélectionnée ({zoneName})
                            </p>
                        </div>

                        {/* Onglets Filtres d'Entité */}
                        <div className="flex flex-wrap items-center gap-1.5 rounded-2xl bg-[var(--admin-bg)] p-1 border border-[var(--admin-border)]">
                            {[
                                { id: 'all', label: 'Tous' },
                                { id: 'artisan', label: 'Artisans' },
                                { id: 'client', label: 'Clients' },
                                { id: 'fournisseur', label: 'Fournisseurs' },
                                { id: 'livreur', label: 'Livreurs' },
                                { id: 'mission', label: 'Missions' },
                                { id: 'litige', label: 'Litiges' },
                            ].map((tab) => (
                                <button
                                    key={tab.id}
                                    type="button"
                                    onClick={() => handleEntityTypeChange(tab.id)}
                                    className={cn(
                                        'rounded-xl px-3 py-1.5 text-xs font-semibold transition',
                                        entityType === tab.id
                                            ? 'bg-[#241b16] text-white shadow-sm'
                                            : 'text-[var(--admin-text-soft)] hover:text-[var(--admin-text)] hover:bg-white/40'
                                    )}
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* Formulaire de Recherche Textuelle Locale */}
                    <form onSubmit={handleSearchSubmit} className="flex gap-2">
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Rechercher par nom, téléphone, ID ou titre dans cette zone..."
                            className="h-10 flex-1 rounded-xl border border-[var(--admin-border)] bg-[var(--admin-card-bg)] px-3 text-xs text-[var(--admin-text)] placeholder:text-[var(--admin-muted)] focus:outline-none focus:ring-2 focus:ring-[#ebb95e]"
                        />
                        <button
                            type="submit"
                            className="h-10 rounded-xl bg-[#241b16] px-4 text-xs font-semibold text-white transition hover:bg-[#3d2f25]"
                        >
                            Filtrer
                        </button>
                    </form>

                    {/* Table des Données */}
                    <div className="overflow-x-auto rounded-2xl border border-[var(--admin-border)] bg-[var(--admin-card-bg)]">
                        <table className="min-w-full divide-y divide-[var(--admin-border)] text-left text-xs">
                            <thead className="bg-[#faf6f0] text-[var(--admin-muted)] font-semibold uppercase tracking-wider">
                                <tr>
                                    <th scope="col" className="py-3 pl-4 pr-3">Type</th>
                                    <th scope="col" className="px-3 py-3">Intitulé / Nom</th>
                                    <th scope="col" className="px-3 py-3">Localisation</th>
                                    <th scope="col" className="px-3 py-3">Statut / Score</th>
                                    <th scope="col" className="px-3 py-3">Date & Contact</th>
                                    <th scope="col" className="relative py-3 pl-3 pr-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--admin-border)] text-[var(--admin-text)]">
                                {safeEntitiesData.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="py-8 text-center text-sm text-[var(--admin-muted)]">
                                            Aucun enregistrement ne correspond à ces critères dans cette zone territoriale.
                                        </td>
                                    </tr>
                                ) : (
                                    safeEntitiesData.map((item) => (
                                        <tr key={`${item.type}-${item.id}`} className="hover:bg-amber-50/40 transition">
                                            {/* Type */}
                                            <td className="whitespace-nowrap py-3 pl-4 pr-3">
                                                <span
                                                    className={cn(
                                                        'inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold border',
                                                        item.type === 'artisan' && toneBadgeClasses('amber'),
                                                        item.type === 'client' && toneBadgeClasses('blue'),
                                                        item.type === 'fournisseur' && toneBadgeClasses('green'),
                                                        item.type === 'livreur' && toneBadgeClasses('slate'),
                                                        item.type === 'mission' && 'bg-purple-100 text-purple-900 border-purple-200',
                                                        item.type === 'litige' && toneBadgeClasses('rose')
                                                    )}
                                                >
                                                    {item.type === 'artisan' && 'Artisan'}
                                                    {item.type === 'client' && 'Client'}
                                                    {item.type === 'fournisseur' && 'Fournisseur'}
                                                    {item.type === 'livreur' && 'Livreur'}
                                                    {item.type === 'mission' && 'Mission'}
                                                    {item.type === 'litige' && 'Litige'}
                                                </span>
                                            </td>

                                            {/* Nom / Titre */}
                                            <td className="px-3 py-3 font-medium">
                                                <div className="font-semibold text-sm text-[#241b16]">{item.title}</div>
                                                {item.subtitle && (
                                                    <div className="text-[11px] text-[var(--admin-muted)]">{item.subtitle}</div>
                                                )}
                                            </td>

                                            {/* Localisation */}
                                            <td className="px-3 py-3">
                                                <div className="font-medium text-[#241b16]">{item.location || 'Côte d’Ivoire'}</div>
                                                <div className="text-[10px] text-[var(--admin-muted)]">
                                                    {item.commune ? `${item.commune} (${item.district || 'CI'})` : item.district || 'Non spécifié'}
                                                </div>
                                            </td>

                                            {/* Statut / Score */}
                                            <td className="px-3 py-3">
                                                <div className="flex items-center gap-1.5">
                                                    <span className="font-medium">{item.status}</span>
                                                    {item.score_prosartisan !== undefined && item.score_prosartisan !== null && (
                                                        <span className="rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-900">
                                                            ★ {item.score_prosartisan}/1000
                                                        </span>
                                                    )}
                                                </div>
                                                {item.amount_fcfa !== undefined && item.amount_fcfa > 0 && (
                                                    <div className="text-[11px] font-bold text-emerald-700">
                                                        {money(item.amount_fcfa)}
                                                    </div>
                                                )}
                                            </td>

                                            {/* Date & Contact */}
                                            <td className="px-3 py-3 text-[11px] text-[var(--admin-muted)]">
                                                <div>{shortDate(item.created_at)}</div>
                                                {item.contact && <div className="font-mono text-[#241b16]">{item.contact}</div>}
                                            </td>

                                            {/* Action */}
                                            <td className="whitespace-nowrap py-3 pl-3 pr-4 text-right">
                                                {item.action_url ? (
                                                    <Link
                                                        href={item.action_url}
                                                        className="rounded-lg bg-white px-2.5 py-1 text-xs font-semibold text-[#241b16] border border-[#d8c2a3] hover:bg-[#faf6f0] transition shadow-xs"
                                                    >
                                                        Inspecter &rarr;
                                                    </Link>
                                                ) : (
                                                    <span className="text-[10px] text-[var(--admin-muted)]">—</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {safeEntities.last_page > 1 && (
                        <div className="flex items-center justify-between pt-2">
                            <p className="text-xs text-[var(--admin-muted)]">
                                Page {safeEntities.current_page} sur {safeEntities.last_page} ({safeEntities.total} éléments)
                            </p>
                            <div className="flex items-center gap-1">
                                <button
                                    type="button"
                                    disabled={safeEntities.current_page <= 1 || isLoading}
                                    onClick={() => handlePageChange(safeEntities.current_page - 1)}
                                    className="rounded-xl border border-[var(--admin-border)] px-3 py-1.5 text-xs font-semibold disabled:opacity-40 hover:bg-white/60 transition"
                                >
                                    &larr; Précédent
                                </button>
                                <button
                                    type="button"
                                    disabled={safeEntities.current_page >= safeEntities.last_page || isLoading}
                                    onClick={() => handlePageChange(safeEntities.current_page + 1)}
                                    className="rounded-xl border border-[var(--admin-border)] px-3 py-1.5 text-xs font-semibold disabled:opacity-40 hover:bg-white/60 transition"
                                >
                                    Suivant &rarr;
                                </button>
                            </div>
                        </div>
                    )}
                </Surface>
            </div>
        </ErrorBoundary>
    );
}
