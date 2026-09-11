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
    | 'audit_logs'
    | 'observability'
    | 'cartography'
    | 'vitrine';

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
    type: 'national' | 'district' | 'commune';
    slug: string;
    name: string;
    district_slug?: string | null;
    commune_slug?: string | null;
}

export interface TerritoryActors {
    clients: number;
    artisans: number;
    artisans_kyc_actif: number;
    fournisseurs: number;
    livreurs: number;
    total_actors: number;
}

export interface TerritoryMissions {
    total: number;
    en_cours: number;
    completed: number;
    disputed: number;
    realization_rate: number;
    dispute_rate: number;
    total_volume_fcfa: number;
}

export interface TerritoryReputation {
    avg_rating: number;
    avg_score_prosartisan: number;
}

export interface TerritorySummary {
    zone: TerritoryZone;
    actors: TerritoryActors;
    missions: TerritoryMissions;
    reputation: TerritoryReputation;
}

export interface DistrictHeatmapItem {
    name: string;
    full_name: string;
    chef_lieu: string;
    actors_count: number;
    missions_count: number;
    volume_fcfa: number;
    realization_rate: number;
    dispute_rate: number;
}

export interface CommuneHeatmapItem {
    name: string;
    type: string;
    actors_count: number;
    artisans_count: number;
    fournisseurs_count: number;
    livreurs_count: number;
    clients_count: number;
    missions_count: number;
    volume_fcfa: number;
    realization_rate: number;
}

export interface DistrictListItem {
    id: string;
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
    lat: number;
    lng: number;
}

export interface TerritoryEntityItem {
    id: number;
    type: 'user' | 'mission';
    role?: string;
    name?: string;
    phone?: string;
    kyc_status?: string;
    score_prosartisan?: number;
    commune?: string;
    title?: string;
    subtitle?: string;
    actor_name?: string;
    client_name?: string;
    contact?: string;
    status?: string;
    montant?: number;
    location?: string;
    created_at?: string;
}


