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

