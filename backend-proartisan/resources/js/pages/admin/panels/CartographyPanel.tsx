import { Link } from '@inertiajs/react';
import React, { useCallback, useMemo, useRef, useState, useTransition } from 'react';
import { cn } from '@/lib/utils';
import { redirectIfSessionExpired } from '../hooks/useIdleLogout';
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
    TerritoryBreakdowns,
    TerritoryBreakdownGroup,
    TerritoryFilters,
    TerritoryMatrix,
    TerritoryTypeKey,
    TerritoryZoneRow,
    DistrictListItem,
    CommuneListItem,
    TerritoryEntityItem,
} from '../shared/types';
import type { CityGeoData } from './ivoryCoastGeoData';
import { IvoryCoastMapSvg } from './IvoryCoastMapSvg';
import { EMPTY_MATRIX, TERRITORY_TYPES, UNLOCATED_SLUG, effectiveTypes } from './territoryTypes';

interface EntitiesPage {
    data: TerritoryEntityItem[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
}

export interface CartographyPanelProps {
    territorySummary?: TerritorySummary;
    territoryBreakdowns?: TerritoryBreakdowns;
    territoryMatrix?: TerritoryMatrix;
    districtsList?: DistrictListItem[];
    communesList?: CommuneListItem[];
    entities?: EntitiesPage;
    filters?: TerritoryFilters;
    /** Capacité `admin.exports` : affiche les exports CSV. */
    canExport?: boolean;
}

/** Filtres de l'onglet : un seul état, partagé par la carte et le tableau. */
interface PanelFilters {
    district: string | null;
    commune: string | null;
    types: TerritoryTypeKey[];
    missionStatus: string;
    kyc: string;
    period: string;
    entityType: string;
    sort: string;
    search: string;
    page: number;
}

type SynthesisColumn = { field: keyof TerritoryZoneRow; label: string; detail?: keyof TerritoryZoneRow; detailLabel?: string; type?: TerritoryTypeKey; money?: boolean; percent?: boolean };

const SYNTHESIS_COLUMNS: SynthesisColumn[] = [
    { field: 'clients', label: 'Clients', type: 'client' },
    { field: 'artisans', label: 'Artisans', detail: 'artisans_kyc_actif', detailLabel: 'KYC actif', type: 'artisan' },
    { field: 'livreurs', label: 'Livreurs', detail: 'livreurs_en_course', detailLabel: 'en course', type: 'livreur' },
    { field: 'fournisseurs', label: 'Quincailleries', detail: 'fournisseurs_agrees', detailLabel: 'agréées', type: 'fournisseur' },
    { field: 'missions_en_cours', label: 'Missions en cours', type: 'mission' },
    { field: 'missions_terminees', label: 'Missions terminées', type: 'mission' },
    { field: 'litiges', label: 'Litiges', type: 'mission' },
    { field: 'volume_fcfa', label: 'Volume', type: 'mission', money: true },
    { field: 'realization_rate', label: 'Réalisation', type: 'mission', percent: true },
];

const MISSION_STATUS_OPTIONS = [
    { value: 'all', label: 'Toutes les missions' },
    { value: 'en_cours', label: 'Missions en cours' },
    { value: 'terminees', label: 'Missions terminées' },
    { value: 'litige', label: 'Missions en litige' },
];

const KYC_OPTIONS = [
    { value: 'all', label: 'Tous les statuts KYC' },
    { value: 'actif', label: 'KYC actif' },
    { value: 'en_attente', label: 'KYC en attente' },
    { value: 'rejete', label: 'KYC rejeté' },
];

const PERIOD_OPTIONS = [
    { value: 'all', label: 'Depuis toujours' },
    { value: '30', label: '30 derniers jours' },
    { value: '90', label: '90 derniers jours' },
];

const ACTOR_SORTS = [
    { value: 'recent', label: 'Plus récents' },
    { value: 'name', label: 'Nom (A → Z)' },
    { value: 'score', label: 'Score ProsArtisan' },
];

const MISSION_SORTS = [
    { value: 'recent', label: 'Plus récentes' },
    { value: 'montant', label: 'Montant décroissant' },
];

const ENTITY_LABELS: Record<string, string> = {
    client: 'Client',
    artisan: 'Artisan',
    fournisseur: 'Quincaillerie',
    livreur: 'Livreur',
    mission: 'Mission',
    litige: 'Litige',
};

const defaultSummary: TerritorySummary = {
    zone: { type: 'national', slug: 'all', name: "Toute la Côte d'Ivoire (Vue Nationale)" },
    actors: {},
    missions: {},
    reputation: { avg_rating: null, avg_score_prosartisan: 0, total_reviews: 0 },
};

const defaultEntities: EntitiesPage = { data: [], current_page: 1, last_page: 1, total: 0, per_page: 15 };

const defaultBreakdowns: TerritoryBreakdowns = {
    artisan_categories: { total: 0, items: [] },
    supplier_sectors: { total: 0, items: [] },
    cnmci: { total_artisans: 0, valide: 0, en_attente: 0, rejete: 0, non_renseigne: 0, valide_percent: 0 },
};

const selectClass =
    'h-9 rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 text-xs font-semibold text-[var(--admin-text)] focus:outline-none focus:ring-2 focus:ring-[#ebb95e]';

function isMissionType(type: string): boolean {
    return type === 'mission' || type === 'litige';
}

function toQuery(filters: PanelFilters): URLSearchParams {
    const query = new URLSearchParams();
    if (filters.district) query.set('district', filters.district);
    if (filters.commune) query.set('commune', filters.commune);
    if (filters.entityType !== 'all') query.set('entity_type', filters.entityType);
    if (filters.types.length > 0) query.set('types', filters.types.join(','));
    if (filters.missionStatus !== 'all') query.set('mission_status', filters.missionStatus);
    if (filters.kyc !== 'all') query.set('kyc', filters.kyc);
    if (filters.period !== 'all') query.set('period', filters.period);
    if (filters.sort !== 'recent') query.set('sort', filters.sort);
    if (filters.search) query.set('search', filters.search);
    return query;
}

/** Liste « répartition par catégorie » (secteurs artisans/fournisseurs) avec barre de proportion. */
function BreakdownList({ group, emptyLabel }: { group: TerritoryBreakdownGroup; emptyLabel: string }) {
    if (group.total === 0 || group.items.length === 0) {
        return <p className="text-xs text-[var(--admin-muted)]">{emptyLabel}</p>;
    }

    return (
        <div className="space-y-2.5">
            {group.items.map((item) => (
                <div key={item.sector_id ?? 'non-renseigne'}>
                    <div className="flex items-center justify-between gap-2 text-xs">
                        <span className={cn('font-semibold', item.sector_id === null ? 'text-[var(--admin-muted)] italic' : 'text-[var(--admin-text)]')}>
                            {item.icon ? `${item.icon} ` : ''}{item.label}
                        </span>
                        <span className="shrink-0 text-[var(--admin-muted)]">
                            {item.count} • {item.percent}%
                        </span>
                    </div>
                    <div className="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-[var(--admin-panel)]">
                        <div
                            className={cn('h-full rounded-full transition-all duration-500', item.sector_id === null ? 'bg-[var(--admin-border)]' : 'bg-[#ebb95e]')}
                            style={{ width: `${Math.max(item.percent, item.count > 0 ? 3 : 0)}%` }}
                        />
                    </div>
                </div>
            ))}
        </div>
    );
}

export function CartographyPanel({
    territorySummary: initialSummary,
    territoryBreakdowns: initialBreakdowns,
    territoryMatrix: initialMatrix,
    districtsList = [],
    communesList = [],
    entities: initialEntities,
    filters: initialFilters,
    canExport = false,
}: CartographyPanelProps) {
    const [filters, setFilters] = useState<PanelFilters>(() => ({
        district: initialFilters?.district ?? null,
        commune: initialFilters?.commune ?? null,
        types: (initialFilters?.types ?? []) as TerritoryTypeKey[],
        missionStatus: initialFilters?.mission_status ?? 'all',
        kyc: initialFilters?.kyc ?? 'all',
        period: initialFilters?.period ?? 'all',
        entityType: initialFilters?.entity_type ?? 'all',
        sort: initialFilters?.sort ?? 'recent',
        search: initialFilters?.search ?? '',
        page: 1,
    }));
    // Toujours à jour pour `load`, qui est le seul à modifier les filtres.
    const filtersRef = useRef(filters);

    const [viewMode, setViewMode] = useState<'national' | 'abidjan'>(
        initialFilters?.commune || initialFilters?.district === 'abidjan' ? 'abidjan' : 'national'
    );
    const [tableView, setTableView] = useState<'synthese' | 'detail'>(
        initialFilters?.entity_type && initialFilters.entity_type !== 'all' ? 'detail' : 'synthese'
    );
    const [synthesisSort, setSynthesisSort] = useState<{ field: keyof TerritoryZoneRow; desc: boolean } | null>(null);
    const [searchInput, setSearchInput] = useState<string>(initialFilters?.search ?? '');
    const [summary, setSummary] = useState<TerritorySummary>(initialSummary ?? defaultSummary);
    const [breakdowns, setBreakdowns] = useState<TerritoryBreakdowns>(initialBreakdowns ?? defaultBreakdowns);
    const [matrix, setMatrix] = useState<TerritoryMatrix>(initialMatrix ?? EMPTY_MATRIX);
    const [entities, setEntities] = useState<EntitiesPage>(initialEntities ?? defaultEntities);
    const [isLoading, setIsLoading] = useState<boolean>(false);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [, startTransition] = useTransition();

    // Resynchronisation quand Inertia renvoie de nouvelles props (rechargement
    // partiel) : l'état local est aussi alimenté par le fetch interne, il faut
    // donc que les props fraîches reprennent la main. On ajuste l'état pendant
    // le rendu plutôt que dans un effet — motif recommandé par React, qui évite
    // le rendu en cascade d'un setState déclenché après coup.
    const [syncedSummary, setSyncedSummary] = useState(initialSummary);
    if (initialSummary && initialSummary !== syncedSummary) {
        setSyncedSummary(initialSummary);
        setSummary(initialSummary);
    }

    const [syncedEntities, setSyncedEntities] = useState(initialEntities);
    if (initialEntities && initialEntities !== syncedEntities) {
        setSyncedEntities(initialEntities);
        setEntities(initialEntities);
    }

    const [syncedBreakdowns, setSyncedBreakdowns] = useState(initialBreakdowns);
    if (initialBreakdowns && initialBreakdowns !== syncedBreakdowns) {
        setSyncedBreakdowns(initialBreakdowns);
        setBreakdowns(initialBreakdowns);
    }

    const [syncedMatrix, setSyncedMatrix] = useState(initialMatrix);
    if (initialMatrix && initialMatrix !== syncedMatrix) {
        setSyncedMatrix(initialMatrix);
        setMatrix(initialMatrix);
    }

    // Applique un changement de filtre et recharge la zone : la carte et le
    // tableau partagent ces filtres, un seul appel met les deux à jour.
    const load = useCallback(async (changes: Partial<PanelFilters>) => {
        const next: PanelFilters = { ...filtersRef.current, page: 1, ...changes };
        filtersRef.current = next;
        setFilters(next);
        setIsLoading(true);
        setLoadError(null);

        try {
            const query = toQuery(next);
            if (next.page > 1) query.set('page', next.page.toString());

            const res = await fetch(`/admin/cartographie/stats?${query.toString()}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (redirectIfSessionExpired(res)) return;

            if (!res.ok) {
                setLoadError('Les données de cette zone n’ont pas pu être chargées. Les chiffres affichés sont ceux du dernier chargement.');
                return;
            }

            const data = await res.json();
            startTransition(() => {
                if (data.summary) setSummary(data.summary);
                if (data.entities) setEntities(data.entities);
                if (data.breakdowns) setBreakdowns(data.breakdowns);
                if (data.matrix) setMatrix(data.matrix);
            });
        } catch (err) {
            console.error('Erreur lors du chargement des statistiques cartographiques', err);
            setLoadError('Les données de cette zone n’ont pas pu être chargées. Vérifiez votre connexion.');
        } finally {
            setIsLoading(false);
        }
    }, []);

    const handleSelectDistrict = (slug: string) => {
        if (!slug || (filters.district === slug && !filters.commune)) {
            load({ district: null, commune: null });
            return;
        }

        setViewMode(slug === 'abidjan' ? 'abidjan' : 'national');
        load({ district: slug, commune: null });
    };

    const handleSelectCommune = (slug: string) => {
        if (!slug || filters.commune === slug) {
            load({ commune: null });
            return;
        }

        const found = communesList.find((c) => c.slug === slug);
        setViewMode('abidjan');
        load({ district: found?.district_slug ?? 'abidjan', commune: slug });
    };

    const handleSelectCity = (city: CityGeoData) => {
        setViewMode(city.districtSlug === 'abidjan' ? 'abidjan' : 'national');
        load({ district: city.districtSlug, commune: null });
    };

    const handleSwitchViewMode = (mode: 'national' | 'abidjan') => {
        setViewMode(mode);
        setSynthesisSort(null);
        if (mode === 'national' && filters.commune) {
            load({ commune: null });
        }
    };

    const handleResetFilters = () => {
        setViewMode('national');
        setSearchInput('');
        setSynthesisSort(null);
        load({ district: null, commune: null, types: [], missionStatus: 'all', kyc: 'all', period: 'all', entityType: 'all', sort: 'recent', search: '' });
    };

    // Le type du tableau détaillé suit les types cochés : décocher le type
    // affiché ramène à « Tous les acteurs ».
    const handleToggleType = (key: TerritoryTypeKey | null) => {
        const types = key === null
            ? []
            : filters.types.includes(key)
                ? filters.types.filter((type) => type !== key)
                : [...filters.types, key];

        const visible = types.length === 0 ? TERRITORY_TYPES.map((type) => type.key) : types;
        const entityKey = (isMissionType(filters.entityType) ? 'mission' : filters.entityType) as TerritoryTypeKey;
        const entityType = filters.entityType === 'all' || visible.includes(entityKey) ? filters.entityType : 'all';

        load({ types, entityType, sort: entityType === filters.entityType ? filters.sort : 'recent' });
    };

    const handleEntityTypeChange = (type: string) => {
        const keepsSort = isMissionType(type) === isMissionType(filters.entityType);
        load({ entityType: type, sort: keepsSort ? filters.sort : 'recent' });
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        load({ search: searchInput.trim() });
    };

    const activeTypeDefs = effectiveTypes(filters.types);
    const activeKeys = activeTypeDefs.map((type) => type.key);
    const zoneName = summary?.zone?.name ?? "Toute la Côte d'Ivoire (Vue Nationale)";
    const zoneType = summary?.zone?.type ?? 'national';
    const actors = summary?.actors ?? {};
    const missions = summary?.missions ?? {};
    const disputeCount = missions.litige ?? missions.disputed ?? 0;
    const completedCount = missions.terminee ?? missions.completed ?? 0;
    const avgRating = summary?.reputation?.avg_rating;
    const totalReviews = summary?.reputation?.total_reviews ?? 0;

    const filteredCommunesList = filters.district
        ? communesList.filter((c) => c.district_slug === filters.district || !c.district_slug)
        : communesList;

    const hasActiveFilters =
        !!filters.district || !!filters.commune || filters.types.length > 0 || filters.missionStatus !== 'all' ||
        filters.kyc !== 'all' || filters.period !== 'all' || filters.entityType !== 'all' || !!filters.search;

    // ── Synthèse par zone ───────────────────────────────────────────────────
    const synthesisLevel = viewMode === 'abidjan' ? 'communes' : 'districts';
    const synthesisColumns = SYNTHESIS_COLUMNS.filter((column) => !column.type || activeKeys.includes(column.type));

    const synthesisRows = useMemo(() => {
        const rows: TerritoryZoneRow[] = synthesisLevel === 'communes'
            ? Object.values(matrix.communes)
            : [...Object.values(matrix.districts), matrix.unlocated];

        if (!synthesisSort) return rows;

        const { field, desc } = synthesisSort;
        return [...rows].sort((a, b) => {
            const left = a[field];
            const right = b[field];
            const order = typeof left === 'number' && typeof right === 'number'
                ? left - right
                : String(left).localeCompare(String(right), 'fr');
            return desc ? -order : order;
        });
    }, [matrix, synthesisLevel, synthesisSort]);

    const synthesisTotal: TerritoryZoneRow | undefined = synthesisLevel === 'communes'
        ? matrix.districts.abidjan && { ...matrix.districts.abidjan, name: 'Total Grand Abidjan' }
        : { ...matrix.national, name: 'Total national' };

    const toggleSynthesisSort = (field: keyof TerritoryZoneRow) => {
        setSynthesisSort((current) => (current?.field === field ? { field, desc: !current.desc } : { field, desc: field !== 'name' }));
    };

    const sortIndicator = (field: keyof TerritoryZoneRow) =>
        synthesisSort?.field === field ? (synthesisSort.desc ? ' ▼' : ' ▲') : '';

    const ariaSort = (field: keyof TerritoryZoneRow): 'ascending' | 'descending' | 'none' =>
        synthesisSort?.field === field ? (synthesisSort.desc ? 'descending' : 'ascending') : 'none';

    const isRowSelected = (row: TerritoryZoneRow) =>
        row.type === 'commune' ? filters.commune === row.slug : !filters.commune && filters.district === row.slug;

    const handleSynthesisRowClick = (row: TerritoryZoneRow) => {
        if (row.type === 'commune') handleSelectCommune(row.slug);
        else handleSelectDistrict(row.slug);
    };

    const cellValue = (row: TerritoryZoneRow, column: SynthesisColumn) => {
        const value = row[column.field] as number;
        if (column.money) return money(value);
        if (column.percent) return row.missions_total > 0 ? `${value}%` : '—';
        return value;
    };

    // ── Tableau détaillé ────────────────────────────────────────────────────
    const detailTabs = [
        { id: 'all', label: 'Tous les acteurs' },
        ...activeTypeDefs.filter((type) => type.key !== 'mission').map((type) => ({ id: type.key as string, label: type.label })),
        ...(activeKeys.includes('mission') ? [{ id: 'mission', label: 'Missions' }, { id: 'litige', label: 'Litiges' }] : []),
    ];
    const sortOptions = isMissionType(filters.entityType) ? MISSION_SORTS : ACTOR_SORTS;
    const showsMissions = isMissionType(filters.entityType);

    const exportHref = (vue: 'synthese' | 'detail') => {
        const query = toQuery(filters);
        query.set('vue', vue);
        if (vue === 'synthese' && synthesisLevel === 'communes') query.set('niveau', 'communes');
        return `/admin/cartographie/export?${query.toString()}`;
    };

    const exportLink = (vue: 'synthese' | 'detail') =>
        canExport ? (
            <a
                href={exportHref(vue)}
                className="inline-flex items-center gap-1.5 rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-2 text-xs font-bold text-[var(--admin-text-soft)] transition hover:text-[var(--admin-text)]"
            >
                Exporter CSV
            </a>
        ) : null;

    return (
        <ErrorBoundary fallbackTitle="Module Cartographique">
            <div className="space-y-6">
                {/* 1. Zone sélectionnée */}
                <Surface className="p-4 sm:p-5">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div className="flex flex-wrap items-center gap-2.5">
                            <div className="flex items-center gap-2">
                                <span className="text-xl">📍</span>
                                <div>
                                    <h2 className="text-base font-bold text-[var(--admin-text)]">{zoneName}</h2>
                                    <p className="text-xs text-[var(--admin-muted)]">
                                        {zoneType === 'district' && 'District • Vue consolidée'}
                                        {zoneType === 'commune' && 'Commune • Suivi terrain et réseau de proximité'}
                                        {zoneType === 'unlocated' && 'Acteurs et missions qu’aucune commune ne rattache à un district'}
                                        {zoneType === 'national' && "Territoire national • 14 districts • Échelle Côte d'Ivoire"}
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
                            <select
                                aria-label="District"
                                value={filters.district ?? ''}
                                onChange={(e) => handleSelectDistrict(e.target.value)}
                                className={selectClass}
                            >
                                <option value="">Tous les districts (national)</option>
                                {districtsList.map((d) => {
                                    const slug = d.slug || d.id;
                                    return (
                                        <option key={slug} value={slug}>
                                            {d.name}
                                        </option>
                                    );
                                })}
                                <option value={UNLOCATED_SLUG}>Commune non renseignée</option>
                            </select>

                            <select
                                aria-label="Commune"
                                value={filters.commune ?? ''}
                                onChange={(e) => handleSelectCommune(e.target.value)}
                                className={selectClass}
                            >
                                <option value="">Toutes les communes</option>
                                {filteredCommunesList.map((c) => {
                                    const slug = c.slug || c.name.toLowerCase();
                                    return (
                                        <option key={slug} value={slug}>
                                            {c.name}
                                        </option>
                                    );
                                })}
                            </select>

                            {hasActiveFilters && (
                                <button
                                    type="button"
                                    onClick={handleResetFilters}
                                    className="h-9 rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 text-xs font-semibold text-[var(--admin-text-soft)] hover:text-[var(--admin-text)] transition"
                                >
                                    Réinitialiser
                                </button>
                            )}
                        </div>
                    </div>

                    {loadError && (
                        <p role="alert" className="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700">
                            {loadError}
                        </p>
                    )}
                </Surface>

                {/* 2. Filtres par type : communs à la carte et au tableau */}
                <Surface className="p-4 sm:p-5">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div role="group" aria-label="Filtrer par type" className="flex flex-wrap items-center gap-1.5">
                            <button
                                type="button"
                                aria-pressed={filters.types.length === 0}
                                onClick={() => handleToggleType(null)}
                                className={cn(
                                    'rounded-full border px-3 py-1.5 text-xs font-semibold transition',
                                    filters.types.length === 0
                                        ? 'border-[#241b16] bg-[#241b16] text-white'
                                        : 'border-[var(--admin-border)] text-[var(--admin-text-soft)] hover:text-[var(--admin-text)]'
                                )}
                            >
                                Tout
                            </button>
                            {TERRITORY_TYPES.map((type) => {
                                const pressed = filters.types.includes(type.key);
                                return (
                                    <button
                                        key={type.key}
                                        type="button"
                                        aria-pressed={pressed}
                                        onClick={() => handleToggleType(type.key)}
                                        className={cn(
                                            'flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition',
                                            pressed
                                                ? 'text-white'
                                                : 'border-[var(--admin-border)] text-[var(--admin-text-soft)] hover:text-[var(--admin-text)]'
                                        )}
                                        style={pressed ? { backgroundColor: type.color, borderColor: type.color } : undefined}
                                    >
                                        {!pressed && <span className="h-2 w-2 rounded-full" style={{ backgroundColor: type.color }} />}
                                        {type.label}
                                        <span className={cn('rounded-full px-1.5 text-[10px]', pressed ? 'bg-white/25' : 'bg-[var(--admin-panel)]')}>
                                            {matrix.national[type.field]}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <select
                                aria-label="Statut des missions"
                                value={filters.missionStatus}
                                onChange={(e) => load({ missionStatus: e.target.value })}
                                className={selectClass}
                            >
                                {MISSION_STATUS_OPTIONS.map((option) => (
                                    <option key={option.value} value={option.value}>{option.label}</option>
                                ))}
                            </select>
                            <select
                                aria-label="Période des missions"
                                value={filters.period}
                                onChange={(e) => load({ period: e.target.value })}
                                className={selectClass}
                            >
                                {PERIOD_OPTIONS.map((option) => (
                                    <option key={option.value} value={option.value}>{option.label}</option>
                                ))}
                            </select>
                            <select
                                aria-label="Statut KYC des acteurs"
                                value={filters.kyc}
                                onChange={(e) => load({ kyc: e.target.value })}
                                className={selectClass}
                            >
                                {KYC_OPTIONS.map((option) => (
                                    <option key={option.value} value={option.value}>{option.label}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <p className="mt-2 text-[11px] text-[var(--admin-muted)]">
                        Ces filtres s’appliquent à la carte et au tableau. Les totaux des pastilles portent sur toute la Côte d’Ivoire.
                    </p>
                </Surface>

                {/* 3. Acteurs de la zone */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-4">
                    <MetricCard
                        value={(actors.clients ?? 0).toString()}
                        description={`${actors.clients_with_active_missions ?? 0} avec mission en cours`}
                        tone="blue"
                    >
                        Clients Inscrits
                    </MetricCard>

                    <MetricCard
                        value={(actors.artisans ?? 0).toString()}
                        description={`${actors.artisans_kyc_actif ?? 0} au KYC actif (${actors.artisans_kyc_percent ?? 0}%)`}
                        tone="amber"
                    >
                        Artisans du Réseau
                    </MetricCard>

                    <MetricCard
                        value={(actors.fournisseurs ?? 0).toString()}
                        description={`${actors.fournisseurs_agrees ?? 0} agréée${(actors.fournisseurs_agrees ?? 0) > 1 ? 's' : ''}`}
                        tone="green"
                    >
                        Quincailleries
                    </MetricCard>

                    <MetricCard
                        value={(actors.livreurs ?? 0).toString()}
                        description={`${actors.livreurs_en_course ?? 0} en course`}
                        tone="slate"
                    >
                        Livreurs Partenaires
                    </MetricCard>
                </div>

                {/* 3bis. Répartition par catégorie & conformité de la zone sélectionnée */}
                <div className="grid grid-cols-1 gap-3 lg:grid-cols-3">
                    <Surface className="p-4 sm:p-5">
                        <SectionTitle
                            description={`${breakdowns.artisan_categories.total} artisan(s) inscrit(s) dans cette zone`}
                            title="Artisans par catégorie"
                        />
                        <div className="mt-4">
                            <BreakdownList
                                group={breakdowns.artisan_categories}
                                emptyLabel="Aucun artisan inscrit dans cette zone."
                            />
                        </div>
                    </Surface>

                    <Surface className="p-4 sm:p-5">
                        <SectionTitle
                            description={`${breakdowns.supplier_sectors.total} fournisseur(s) inscrit(s) dans cette zone`}
                            title="Fournisseurs par secteur d’activité"
                        />
                        <div className="mt-4">
                            <BreakdownList
                                group={breakdowns.supplier_sectors}
                                emptyLabel="Aucun fournisseur inscrit dans cette zone."
                            />
                        </div>
                    </Surface>

                    <Surface className="p-4 sm:p-5">
                        <SectionTitle
                            description={`Sur ${breakdowns.cnmci.total_artisans} artisan(s) de la zone`}
                            title="Conformité carte CNMCI"
                        />
                        {breakdowns.cnmci.total_artisans === 0 ? (
                            <p className="mt-4 text-xs text-[var(--admin-muted)]">Aucun artisan inscrit dans cette zone.</p>
                        ) : (
                            <div className="mt-4 space-y-3">
                                <div>
                                    <div className="flex items-end justify-between">
                                        <p className="text-2xl font-bold text-emerald-700">{breakdowns.cnmci.valide}</p>
                                        <p className="text-xs font-semibold text-[var(--admin-muted)]">{breakdowns.cnmci.valide_percent}% avec carte valide</p>
                                    </div>
                                    <div className="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-[var(--admin-panel)]">
                                        <div
                                            className="h-full rounded-full bg-emerald-500 transition-all duration-500"
                                            style={{ width: `${Math.min(breakdowns.cnmci.valide_percent, 100)}%` }}
                                        />
                                    </div>
                                </div>
                                <div className="flex flex-wrap gap-1.5 text-[11px]">
                                    <span className="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 font-semibold text-amber-800">
                                        {breakdowns.cnmci.en_attente} en attente
                                    </span>
                                    <span className="rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 font-semibold text-rose-700">
                                        {breakdowns.cnmci.rejete} rejetée(s)
                                    </span>
                                    <span className="rounded-full border border-[var(--admin-border)] bg-[var(--admin-panel)] px-2 py-0.5 font-semibold text-[var(--admin-muted)]">
                                        {breakdowns.cnmci.non_renseigne} non renseignée(s)
                                    </span>
                                </div>
                            </div>
                        )}
                    </Surface>
                </div>

                {/* 4. Missions de la zone */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-4">
                    <MetricCard
                        value={(missions.total ?? 0).toString()}
                        description={`${missions.en_cours ?? 0} en cours • ${completedCount} terminées`}
                        tone="amber"
                    >
                        Missions Totales
                    </MetricCard>

                    <div className="rounded-2xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] p-4 shadow-sm flex flex-col justify-between">
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
                                {missions.realization_rate ?? 0}%
                            </p>
                        </div>
                        <div className="mt-3">
                            <div className="h-2 w-full rounded-full bg-emerald-100 overflow-hidden">
                                <div
                                    className="h-full bg-emerald-500 rounded-full transition-all duration-500"
                                    style={{ width: `${Math.min(missions.realization_rate ?? 0, 100)}%` }}
                                />
                            </div>
                            <p className="mt-1.5 text-[11px] text-[var(--admin-muted)]">
                                {completedCount} chantiers menés à terme
                            </p>
                        </div>
                    </div>

                    <div className="rounded-2xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] p-4 shadow-sm flex flex-col justify-between">
                        <div>
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">
                                    Taux de Litiges
                                </span>
                                <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${
                                    (missions.dispute_rate ?? 0) > 5
                                        ? 'bg-rose-100 text-rose-800'
                                        : 'bg-slate-100 text-slate-700'
                                }`}>
                                    {disputeCount} litige{disputeCount > 1 ? 's' : ''}
                                </span>
                            </div>
                            <p className={`mt-1 text-2xl font-bold ${
                                (missions.dispute_rate ?? 0) > 5 ? 'text-rose-600' : 'text-slate-800'
                            }`}>
                                {missions.dispute_rate ?? 0}%
                            </p>
                        </div>
                        <div className="mt-3">
                            <div className="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                                <div
                                    className={`h-full rounded-full transition-all duration-500 ${
                                        (missions.dispute_rate ?? 0) > 5 ? 'bg-rose-500' : 'bg-amber-400'
                                    }`}
                                    style={{ width: `${Math.min((missions.dispute_rate ?? 0) * 4, 100)}%` }}
                                />
                            </div>
                            <p className="mt-1.5 text-[11px] text-[var(--admin-muted)]">
                                {disputeCount === 0 ? 'Aucune mission en litige sur la zone' : 'Missions en litige sur la zone'}
                            </p>
                        </div>
                    </div>

                    <MetricCard
                        value={money(missions.financial_volume_fcfa ?? missions.total_volume_fcfa ?? 0)}
                        description={
                            avgRating === null || avgRating === undefined
                                ? 'Note des missions : non évaluée'
                                : `Note moyenne : ${avgRating}/5 (${totalReviews} avis)`
                        }
                        tone="green"
                    >
                        Volume Financier
                    </MetricCard>
                </div>

                {/* 5. Carte interactive */}
                <IvoryCoastMapSvg
                    viewMode={viewMode}
                    selectedDistrict={filters.district === UNLOCATED_SLUG ? null : filters.district}
                    selectedCommune={filters.commune}
                    matrix={matrix}
                    activeTypes={filters.types}
                    onSelectDistrict={handleSelectDistrict}
                    onSelectCommune={handleSelectCommune}
                    onSwitchViewMode={handleSwitchViewMode}
                    onSelectCity={handleSelectCity}
                />

                {/* 6. Tableau Récapitulatif Territorial */}
                <Surface className="p-4 sm:p-6 space-y-4">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-[var(--admin-border)] pb-4">
                        <SectionTitle
                            description={
                                tableView === 'synthese'
                                    ? `Effectifs par type pour chaque ${synthesisLevel === 'communes' ? 'commune du Grand Abidjan' : 'district'}`
                                    : `${entities.total ?? 0} enregistrement${(entities.total ?? 0) > 1 ? 's' : ''} dans la zone sélectionnée (${zoneName})`
                            }
                            title="Tableau Récapitulatif Territorial"
                        />

                        <div className="flex flex-wrap items-center gap-2">
                            <div role="tablist" aria-label="Vue du tableau" className="flex rounded-2xl bg-[var(--admin-bg)] p-1 border border-[var(--admin-border)]">
                                {([['synthese', 'Synthèse par zone'], ['detail', 'Détail']] as const).map(([id, label]) => (
                                    <button
                                        key={id}
                                        type="button"
                                        role="tab"
                                        aria-selected={tableView === id}
                                        onClick={() => setTableView(id)}
                                        className={cn(
                                            'rounded-xl px-3 py-1.5 text-xs font-semibold transition',
                                            tableView === id
                                                ? 'bg-[#241b16] text-white shadow-sm'
                                                : 'text-[var(--admin-text-soft)] hover:text-[var(--admin-text)]'
                                        )}
                                    >
                                        {label}
                                    </button>
                                ))}
                            </div>
                            {exportLink(tableView)}
                        </div>
                    </div>

                    {tableView === 'synthese' ? (
                        <div className="overflow-x-auto rounded-2xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)]">
                            <table className="min-w-full divide-y divide-[var(--admin-border)] text-left text-xs" aria-label="Synthèse par zone">
                                <thead className="text-[var(--admin-muted)] font-semibold uppercase tracking-wider">
                                    <tr>
                                        <th scope="col" aria-sort={ariaSort('name')} className="py-3 pl-4 pr-3">
                                            <button type="button" onClick={() => toggleSynthesisSort('name')} className="font-semibold uppercase tracking-wider">
                                                {synthesisLevel === 'communes' ? 'Commune' : 'District'}{sortIndicator('name')}
                                            </button>
                                        </th>
                                        {synthesisColumns.map((column) => (
                                            <th key={column.field} scope="col" aria-sort={ariaSort(column.field)} className="px-3 py-3 text-right">
                                                <button type="button" onClick={() => toggleSynthesisSort(column.field)} className="font-semibold uppercase tracking-wider">
                                                    {column.label}{sortIndicator(column.field)}
                                                </button>
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[var(--admin-border)] text-[var(--admin-text)]">
                                    {synthesisRows.length === 0 ? (
                                        <tr>
                                            <td colSpan={synthesisColumns.length + 1} className="py-8 text-center text-sm text-[var(--admin-muted)]">
                                                Aucune zone à afficher : la synthèse se remplira dès que des acteurs ou des missions seront enregistrés.
                                            </td>
                                        </tr>
                                    ) : (
                                        synthesisRows.map((row) => (
                                            <tr
                                                key={row.slug}
                                                aria-selected={isRowSelected(row)}
                                                onClick={() => handleSynthesisRowClick(row)}
                                                className={cn(
                                                    'cursor-pointer transition hover:bg-[var(--admin-panel)]',
                                                    isRowSelected(row) && 'bg-amber-50',
                                                    row.type === 'unlocated' && 'italic'
                                                )}
                                            >
                                                <th scope="row" className="whitespace-nowrap py-2.5 pl-4 pr-3 text-left font-semibold">
                                                    {row.name}
                                                </th>
                                                {synthesisColumns.map((column) => (
                                                    <td key={column.field} className="whitespace-nowrap px-3 py-2.5 text-right tabular-nums">
                                                        <span className="font-semibold">{cellValue(row, column)}</span>
                                                        {column.detail && (
                                                            <span className="block text-[10px] text-[var(--admin-muted)]">
                                                                {row[column.detail]} {column.detailLabel}
                                                            </span>
                                                        )}
                                                    </td>
                                                ))}
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                                {synthesisTotal && synthesisRows.length > 0 && (
                                    <tfoot className="border-t-2 border-[var(--admin-border)] bg-[var(--admin-panel)] font-bold text-[var(--admin-text)]">
                                        <tr>
                                            <th scope="row" className="py-3 pl-4 pr-3 text-left">{synthesisTotal.name}</th>
                                            {synthesisColumns.map((column) => (
                                                <td key={column.field} className="whitespace-nowrap px-3 py-3 text-right tabular-nums">
                                                    {cellValue(synthesisTotal, column)}
                                                </td>
                                            ))}
                                        </tr>
                                    </tfoot>
                                )}
                            </table>
                        </div>
                    ) : (
                        <>
                            <div className="flex flex-wrap items-center gap-1.5 rounded-2xl bg-[var(--admin-bg)] p-1 border border-[var(--admin-border)]">
                                {detailTabs.map((tab) => (
                                    <button
                                        key={tab.id}
                                        type="button"
                                        aria-pressed={filters.entityType === tab.id}
                                        onClick={() => handleEntityTypeChange(tab.id)}
                                        className={cn(
                                            'rounded-xl px-3 py-1.5 text-xs font-semibold transition',
                                            filters.entityType === tab.id
                                                ? 'bg-[#241b16] text-white shadow-sm'
                                                : 'text-[var(--admin-text-soft)] hover:text-[var(--admin-text)] hover:bg-[var(--admin-panel)]'
                                        )}
                                    >
                                        {tab.label}
                                    </button>
                                ))}
                            </div>

                            <form onSubmit={handleSearchSubmit} className="flex flex-wrap gap-2">
                                <input
                                    type="text"
                                    value={searchInput}
                                    onChange={(e) => setSearchInput(e.target.value)}
                                    placeholder={showsMissions ? 'Rechercher par numéro, adresse, client ou artisan…' : 'Rechercher par nom ou téléphone…'}
                                    className="h-10 min-w-[200px] flex-1 rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 text-xs text-[var(--admin-text)] placeholder:text-[var(--admin-muted)] focus:outline-none focus:ring-2 focus:ring-[#ebb95e]"
                                />
                                <select
                                    aria-label="Tri"
                                    value={filters.sort}
                                    onChange={(e) => load({ sort: e.target.value })}
                                    className={cn(selectClass, 'h-10')}
                                >
                                    {sortOptions.map((option) => (
                                        <option key={option.value} value={option.value}>{option.label}</option>
                                    ))}
                                </select>
                                <button
                                    type="submit"
                                    className="h-10 rounded-xl bg-[#241b16] px-4 text-xs font-semibold text-white transition hover:bg-[#3d2f25]"
                                >
                                    Filtrer
                                </button>
                            </form>

                            <div className="overflow-x-auto rounded-2xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)]">
                                <table className="min-w-full divide-y divide-[var(--admin-border)] text-left text-xs" aria-label="Détail de la zone">
                                    <thead className="text-[var(--admin-muted)] font-semibold uppercase tracking-wider">
                                        <tr>
                                            <th scope="col" className="py-3 pl-4 pr-3">Type</th>
                                            <th scope="col" className="px-3 py-3">{showsMissions ? 'Mission' : 'Nom'}</th>
                                            <th scope="col" className="px-3 py-3">{showsMissions ? 'Chantier' : 'Localisation'}</th>
                                            <th scope="col" className="px-3 py-3">{showsMissions ? 'Statut & montant' : 'Statut & score'}</th>
                                            <th scope="col" className="px-3 py-3">{showsMissions ? 'Client & artisan' : 'Inscription & contact'}</th>
                                            <th scope="col" className="py-3 pl-3 pr-4 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-[var(--admin-border)] text-[var(--admin-text)]">
                                        {entities.data.length === 0 ? (
                                            <tr>
                                                <td colSpan={6} className="py-8 text-center text-sm text-[var(--admin-muted)]">
                                                    Aucun enregistrement ne correspond à ces critères dans cette zone territoriale.
                                                </td>
                                            </tr>
                                        ) : (
                                            entities.data.map((item) => {
                                                const isMission = isMissionType(item.type);
                                                return (
                                                    <tr key={`${item.type}-${item.id}`} className="hover:bg-[var(--admin-panel)] transition">
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
                                                                {ENTITY_LABELS[item.type] ?? item.type}
                                                            </span>
                                                        </td>

                                                        <td className="px-3 py-3 font-medium">
                                                            <div className="font-semibold text-sm text-[var(--admin-text)]">{item.title || 'Nom non renseigné'}</div>
                                                            {(item.detail || (isMission && item.subtitle)) && (
                                                                <div className="text-[11px] text-[var(--admin-muted)]">{item.detail ?? item.subtitle}</div>
                                                            )}
                                                        </td>

                                                        <td className="px-3 py-3">
                                                            <div className="font-medium text-[var(--admin-text)]">
                                                                {item.location || (isMission ? 'Adresse non renseignée' : 'Commune non renseignée')}
                                                            </div>
                                                            {item.district && (
                                                                <div className="text-[10px] text-[var(--admin-muted)]">{item.district}</div>
                                                            )}
                                                        </td>

                                                        <td className="px-3 py-3">
                                                            <div className="flex items-center gap-1.5">
                                                                <span className="font-medium">{item.status_label ?? 'Statut non renseigné'}</span>
                                                                {item.score_prosartisan !== undefined && item.score_prosartisan !== null && (
                                                                    <span className="rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-900">
                                                                        ★ {item.score_prosartisan}/1000
                                                                    </span>
                                                                )}
                                                            </div>
                                                            {isMission && (
                                                                <div className="text-[11px] font-bold text-emerald-700">{money(item.amount_fcfa ?? 0)}</div>
                                                            )}
                                                        </td>

                                                        <td className="px-3 py-3 text-[11px] text-[var(--admin-muted)]">
                                                            {isMission ? (
                                                                <>
                                                                    <div>Client : <span className="text-[var(--admin-text)]">{item.client_name || 'non renseigné'}</span></div>
                                                                    <div>Artisan : <span className="text-[var(--admin-text)]">{item.actor_name || 'non assigné'}</span></div>
                                                                </>
                                                            ) : (
                                                                <>
                                                                    <div>{item.created_at ? shortDate(item.created_at) : '—'}</div>
                                                                    {item.contact && <div className="font-mono text-[var(--admin-text)]">{item.contact}</div>}
                                                                </>
                                                            )}
                                                        </td>

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
                                                );
                                            })
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            {entities.last_page > 1 && (
                                <div className="flex items-center justify-between pt-2">
                                    <p className="text-xs text-[var(--admin-muted)]">
                                        Page {entities.current_page} sur {entities.last_page} ({entities.total} éléments)
                                    </p>
                                    <div className="flex items-center gap-1">
                                        <button
                                            type="button"
                                            disabled={entities.current_page <= 1 || isLoading}
                                            onClick={() => load({ page: entities.current_page - 1 })}
                                            className="rounded-xl border border-[var(--admin-border)] px-3 py-1.5 text-xs font-semibold disabled:opacity-40 hover:bg-[var(--admin-panel-strong)] transition"
                                        >
                                            &larr; Précédent
                                        </button>
                                        <button
                                            type="button"
                                            disabled={entities.current_page >= entities.last_page || isLoading}
                                            onClick={() => load({ page: entities.current_page + 1 })}
                                            className="rounded-xl border border-[var(--admin-border)] px-3 py-1.5 text-xs font-semibold disabled:opacity-40 hover:bg-[var(--admin-panel-strong)] transition"
                                        >
                                            Suivant &rarr;
                                        </button>
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </Surface>
            </div>
        </ErrorBoundary>
    );
}
