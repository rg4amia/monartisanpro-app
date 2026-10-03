// Types transverses du backoffice admin, extraits de console.tsx (Chantier C2).

export type AdminTab =
    | 'dashboard'
    | 'kyc'
    | 'missions'
    | 'litiges'
    | 'notifications'
    | 'users'
    | 'transactions'
    | 'settings'
    | 'llm_admin'
    | 'roles_permissions'
    | 'evaluations'
    | 'ai_dashboard'
    | 'communications'
    | 'promo_codes'
    | 'campagnes_parrainage'
    | 'audit_logs'
    | 'observability'
    | 'cartography'
    | 'vitrine'
    | 'whatsapp'
    | 'faq'
    | 'notification_messages'
    | 'notification_campaigns'
    | 'artisan_directory'
    | 'app_links'
    | 'recruitment'
    | 'manual';

export type ThemeMode = 'light' | 'dark';

export type Tone = 'amber' | 'green' | 'rose' | 'blue' | 'slate' | 'purple';

export interface ChartPoint {
    label: string;
    value: number;
}

export interface TimelinePoint {
    date: string;
    label: string;
}

export interface DualSeries {
    color: string;
    label: string;
    points: ChartPoint[];
}

export interface MetricItem {
    description: string;
    title: string;
    tone: Tone;
    value: string;
}

export interface NavigationItem {
    count?: number;
    id: AdminTab;
    label: string;
}

export interface NavigationGroup {
    items: NavigationItem[];
    label: string;
}

export interface FlashMessages {
    error?: string | null;
    success?: string | null;
}

export interface AdminNotificationItem {
    id: number | string;
    user_id?: number | null;
    type: string;
    title: string;
    body: string;
    data_json?: Record<string, unknown> | null;
    read_at?: string | null;
    created_at: string;
    updated_at?: string;
    action_url?: string;
    action_label?: string;
}

export interface ExchangeRates {
    usdToXof: number;
    eurToXof: number;
    eurToUsd: number;
}

export type NotifFilter = 'all' | 'unread' | 'alerts';

export interface TerritoryZone {
    type: 'national' | 'district' | 'commune' | 'unlocated';
    slug: string;
    name: string;
    district_slug?: string | null;
    commune_slug?: string | null;
}

export interface TerritoryActors {
    clients?: number;
    clients_with_active_missions?: number;
    artisans?: number;
    artisans_kyc_actif?: number;
    artisans_kyc_percent?: number;
    fournisseurs?: number;
    fournisseurs_agrees?: number;
    livreurs?: number;
    livreurs_en_course?: number;
    total_actors?: number;
}

export interface TerritoryMissions {
    total?: number;
    en_cours?: number;
    completed?: number;
    terminee?: number;
    disputed?: number;
    litige?: number;
    realization_rate?: number;
    dispute_rate?: number;
    total_volume_fcfa?: number;
    financial_volume_fcfa?: number;
}

export interface TerritoryReputation {
    /** `null` tant qu'aucune évaluation n'existe : affiché « Non évalué ». */
    avg_rating?: number | null;
    avg_score_prosartisan?: number;
    total_reviews?: number;
}

export interface TerritorySummary {
    zone?: TerritoryZone;
    actors?: TerritoryActors;
    missions?: TerritoryMissions;
    reputation?: TerritoryReputation;
}

export interface TerritoryBreakdownItem {
    sector_id: number | null;
    label: string;
    icon?: string | null;
    count: number;
    percent: number;
}

export interface TerritoryBreakdownGroup {
    total: number;
    items: TerritoryBreakdownItem[];
}

export interface TerritoryCnmciBreakdown {
    total_artisans: number;
    valide: number;
    en_attente: number;
    rejete: number;
    non_renseigne: number;
    valide_percent: number;
}

export interface TerritoryBreakdowns {
    artisan_categories: TerritoryBreakdownGroup;
    supplier_sectors: TerritoryBreakdownGroup;
    cnmci: TerritoryCnmciBreakdown;
}

/** Effectifs d'une zone, par type (Chantier 18). */
export interface TerritoryZoneRow {
    slug: string;
    name: string;
    type: 'national' | 'district' | 'commune' | 'unlocated';
    clients: number;
    clients_mission_active: number;
    artisans: number;
    artisans_kyc_actif: number;
    livreurs: number;
    livreurs_en_course: number;
    fournisseurs: number;
    fournisseurs_agrees: number;
    missions_total: number;
    missions_en_cours: number;
    missions_terminees: number;
    litiges: number;
    volume_fcfa: number;
    total_actors: number;
    realization_rate: number;
    dispute_rate: number;
}

/** Une ligne par district, par commune du Grand Abidjan, « non renseignée » et total national. */
export interface TerritoryMatrix {
    national: TerritoryZoneRow;
    unlocated: TerritoryZoneRow;
    districts: Record<string, TerritoryZoneRow>;
    communes: Record<string, TerritoryZoneRow>;
}

export type TerritoryTypeKey = 'client' | 'artisan' | 'livreur' | 'fournisseur' | 'mission';

export interface TerritoryFilters {
    district?: string | null;
    commune?: string | null;
    entity_type?: string;
    types?: string[];
    mission_status?: string;
    kyc?: string;
    period?: string;
    search?: string;
    sort?: string;
}

export interface DistrictListItem {
    id: string;
    slug?: string;
    name: string;
    short_name: string;
    chef_lieu: string;
    lat: number;
    lng: number;
    villes: string[];
}

export interface CommuneListItem {
    name: string;
    type: string;
    slug?: string;
    district_slug?: string;
    lat: number;
    lng: number;
}

export interface TerritoryEntityItem {
    id: number;
    type: string;
    title?: string | null;
    subtitle?: string | null;
    /** Précision propre au type : métier, boutique et agrément, course en cours. */
    detail?: string | null;
    actor_name?: string | null;
    client_name?: string | null;
    contact?: string | null;
    /** Clé technique ; l'affichage passe par `status_label`. */
    status?: string | null;
    status_label?: string | null;
    score_prosartisan?: number | null;
    amount_fcfa?: number;
    location?: string | null;
    district?: string | null;
    action_url?: string;
    created_at?: string | null;
}

export type HeatmapMetricMode = 'actors' | 'volume' | 'rate';

export interface MapViewport {
    x: number;
    y: number;
    scale: number;
}



