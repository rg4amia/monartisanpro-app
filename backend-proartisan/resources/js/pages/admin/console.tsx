import { Head, Link } from '@inertiajs/react';
import { startTransition, useDeferredValue, useEffect, useMemo, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';

import { cn } from '@/lib/utils';

type AdminTab = 'dashboard' | 'kyc' | 'missions' | 'litiges' | 'users' | 'transactions' | 'settings';
type ThemeMode = 'light' | 'dark';
type Tone = 'amber' | 'green' | 'rose' | 'blue' | 'slate';

interface ApiError {
    message?: string;
    errors?: Record<string, string[]>;
}

interface AccessConfig {
    apiUrl: string;
    token: string;
}

interface DashboardData {
    users_total: number;
    artisans_actifs: number;
    clients_actifs: number;
    fournisseurs_agrees: number;
    missions_en_cours: number;
    missions_en_litige: number;
    litiges_ouverts: number;
    kyc_en_attente: number;
    referent_required_open: number;
    recent_fraud_alerts: number;
    volume_transactions_24h: number;
}

interface KycDocument {
    id: number;
    type: 'cni' | 'selfie';
    file_url: string;
}

interface KycUser {
    id: number;
    name: string;
    phone: string;
    role: 'client' | 'artisan' | 'fournisseur' | 'admin' | 'referent';
    created_at: string;
    kyc_documents: KycDocument[];
}

interface LitigeActor {
    name: string;
}

interface LitigeMission {
    montant_total: number;
    client?: LitigeActor;
    artisan?: LitigeActor;
}

interface LitigeItem {
    id: number;
    mission_id: number;
    description: string;
    statut: 'ouvert' | 'en_cours' | 'resolu';
    decision: 'client' | 'artisan' | 'gel' | null;
    created_at: string;
    mission: LitigeMission;
}

interface AdminMissionParty {
    name: string;
    phone?: string;
}

interface AdminMission {
    id: number;
    description: string;
    status: string;
    gemini_category?: string | null;
    gemini_urgency?: string | null;
    gemini_estimation_min?: number | null;
    gemini_estimation_max?: number | null;
    montant_total?: number | null;
    client_address?: string | null;
    created_at: string;
    client?: AdminMissionParty | null;
    artisan?: AdminMissionParty | null;
}

interface FournisseurUser {
    name: string;
    phone: string;
}

interface FournisseurItem {
    id: number;
    nom_boutique: string;
    created_at: string;
    user?: FournisseurUser;
}

interface AdminUser {
    id: number;
    name: string;
    phone: string;
    role: string;
    kyc_status: string;
    score_nzassa: number;
    created_at: string;
    missions_client_count: number;
    missions_artisan_count: number;
}

interface AdminTransaction {
    id: number;
    type: string;
    montant: number;
    provider: string;
    statut: string;
    wallet_source: string;
    wallet_dest: string;
    created_at: string;
    user?: {
        name: string;
    };
}

interface ChartPoint {
    label: string;
    value: number;
}

interface TimelinePoint {
    date: string;
    label: string;
}

interface DualSeries {
    color: string;
    label: string;
    points: ChartPoint[];
}

interface MetricItem {
    description: string;
    title: string;
    tone: Tone;
    value: string;
}

interface NavigationItem {
    count?: number;
    id: AdminTab;
    label: string;
}

interface NavigationGroup {
    items: NavigationItem[];
    label: string;
}

const tabRoutes: Record<AdminTab, string> = {
    dashboard: '/admin/dashboard',
    kyc: '/admin/kyc',
    missions: '/admin/missions',
    litiges: '/admin/litiges',
    users: '/admin/users',
    transactions: '/admin/transactions',
    settings: '/admin/settings',
};

const tabMeta: Record<AdminTab, { description: string; label: string; section: string }> = {
    dashboard: {
        label: 'Vue d’ensemble',
        section: 'PILOTAGE',
        description: 'Lecture rapide de la santé opérationnelle, financière et terrain de ProsArtisan.',
    },
    kyc: {
        label: 'KYC & Vérifications',
        section: 'VALIDATIONS',
        description: 'Traitez les dossiers sensibles avant qu’ils ne bloquent les missions et paiements.',
    },
    missions: {
        label: 'Missions',
        section: 'OPÉRATIONS',
        description: 'Suivi du pipe client, matching terrain et supervision des interventions en cours.',
    },
    litiges: {
        label: 'Litiges',
        section: 'ARBITRAGE',
        description: 'Décidez vite sur les dossiers chauds et les missions au-delà du seuil Référent.',
    },
    users: {
        label: 'Utilisateurs',
        section: 'COMMUNAUTÉ',
        description: 'Vue consolidée des clients, artisans, fournisseurs et comptes à surveiller.',
    },
    transactions: {
        label: 'Transactions',
        section: 'FINANCE',
        description: 'Lecture claire des flux Wave, Orange Money et des libérations de fonds.',
    },
    settings: {
        label: 'Paramètres',
        section: 'PLATEFORME',
        description: 'Règles métier, configuration d’accès et garde-fous du backoffice.',
    },
};

const searchPlaceholders: Record<AdminTab, string> = {
    dashboard: 'Recherche rapide sur les signaux du jour...',
    kyc: 'Rechercher un dossier KYC, un numéro ou un rôle...',
    missions: 'Rechercher une mission, une catégorie ou un intervenant...',
    litiges: 'Rechercher un litige ou une mission...',
    users: 'Nom, téléphone, ID ou rôle...',
    transactions: 'Type, provider, statut ou bénéficiaire...',
    settings: 'Rechercher une règle ou un paramètre...',
};

const quickDockTabs: AdminTab[] = ['dashboard', 'missions', 'users', 'settings'];

const numberFormat = new Intl.NumberFormat('fr-FR');

const money = (amount: number): string => `${numberFormat.format(amount)} FCFA`;

const shortDate = (value: string): string =>
    new Date(value).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });

const fullDate = (value: Date): string =>
    value.toLocaleDateString('fr-FR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

const compactDate = (value: Date): string =>
    value.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
    });

const roleLabels: Record<string, string> = {
    admin: 'Admin',
    artisan: 'Artisan',
    client: 'Client',
    fournisseur: 'Fournisseur',
    referent: 'Référent',
};

const kycStatusLabels: Record<string, string> = {
    actif: 'Actif',
    en_attente: 'En attente',
    rejete: 'Rejeté',
};

const missionStatusLabels: Record<string, string> = {
    annulee: 'Annulée',
    en_attente: 'En attente',
    en_cours: 'En cours',
    financee: 'Financée',
    litige: 'Litige',
    terminee: 'Terminée',
};

const transactionTypeLabels: Record<string, string> = {
    acompte: 'Acompte',
    credit: 'Crédit',
    liberation_jalon: 'Libération jalon',
    paiement_fournisseur: 'Paiement fournisseur',
    remboursement: 'Remboursement',
};

const providerLabels: Record<string, string> = {
    orange_money: 'Orange Money',
    virement_bancaire: 'Virement',
    wave: 'Wave',
};

const litigeDecisionLabels: Record<string, string> = {
    artisan: 'Payer artisan',
    client: 'Rembourser client',
    gel: 'Geler et envoyer Référent',
};

function toneBadgeClasses(tone: Tone): string {
    const classes: Record<Tone, string> = {
        amber: 'border-[#efcf95] bg-[#f8eed4] text-[#b77918]',
        blue: 'border-[#bcd4f6] bg-[#edf5ff] text-[#2d6aa6]',
        green: 'border-[#bfe0c8] bg-[#eef8f0] text-[#24734f]',
        rose: 'border-[#f2c1ba] bg-[#fff0ed] text-[#c55e50]',
        slate: 'border-[#dfd4c4] bg-[#f4eee6] text-[#746251]',
    };

    return classes[tone];
}

function toneIconClasses(tone: Tone): string {
    const classes: Record<Tone, string> = {
        amber: 'bg-[#f7e3bc] text-[#b77918]',
        blue: 'bg-[#dcebfb] text-[#2d6aa6]',
        green: 'bg-[#dff1e4] text-[#24734f]',
        rose: 'bg-[#fbe0da] text-[#c55e50]',
        slate: 'bg-[#efe6da] text-[#746251]',
    };

    return classes[tone];
}

function normalizeSearch(parts: Array<number | string | null | undefined>): string {
    return parts
        .filter((part): part is number | string => part !== null && part !== undefined)
        .join(' ')
        .toLowerCase();
}

function getInitials(value: string): string {
    const initials = value
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');

    return initials || 'PA';
}

function buildTimeline(days: number): TimelinePoint[] {
    const today = new Date();

    return Array.from({ length: days }, (_, index) => {
        const date = new Date(today);
        date.setDate(today.getDate() - (days - 1 - index));

        return {
            date: date.toISOString().slice(0, 10),
            label: compactDate(date),
        };
    });
}

function sumAmount(items: AdminTransaction[]): number {
    return items.reduce((sum, item) => sum + item.montant, 0);
}

export default function AdminConsole({ initialTab }: { initialTab: AdminTab }) {
    const activeTab = initialTab;

    const [token, setToken] = useState<string>('');
    const [apiUrl, setApiUrl] = useState<string>('/api/v1');
    const [draftToken, setDraftToken] = useState<string>('');
    const [draftApiUrl, setDraftApiUrl] = useState<string>('/api/v1');
    const [themeMode, setThemeMode] = useState<ThemeMode>('light');

    const [loading, setLoading] = useState<boolean>(false);
    const [actionLoading, setActionLoading] = useState<boolean>(false);
    const [error, setError] = useState<string>('');
    const [search, setSearch] = useState<string>('');

    const [dashboard, setDashboard] = useState<DashboardData | null>(null);
    const [kycUsers, setKycUsers] = useState<KycUser[]>([]);
    const [missions, setMissions] = useState<AdminMission[]>([]);
    const [litiges, setLitiges] = useState<LitigeItem[]>([]);
    const [fournisseurs, setFournisseurs] = useState<FournisseurItem[]>([]);
    const [users, setUsers] = useState<AdminUser[]>([]);
    const [transactions, setTransactions] = useState<AdminTransaction[]>([]);

    const deferredSearch = useDeferredValue(search.trim().toLowerCase());

    const apiFetch = async <T,>(path: string, access: AccessConfig, options?: RequestInit): Promise<T> => {
        if (!access.apiUrl.trim()) {
            throw new Error('Renseignez l’URL de l’API.');
        }

        if (!access.token.trim()) {
            throw new Error('Renseignez un token Sanctum administrateur.');
        }

        const response = await fetch(`${access.apiUrl}${path}`, {
            ...options,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                Authorization: `Bearer ${access.token}`,
                ...(options?.headers ?? {}),
            },
        });

        const json = (await response.json()) as {
            success: boolean;
            message?: string;
            data?: T;
            errors?: Record<string, string[]>;
        };

        if (!response.ok || !json.success) {
            const problem = json as ApiError;
            const firstFieldError = problem.errors ? Object.values(problem.errors)[0]?.[0] : undefined;
            throw new Error(problem.message ?? firstFieldError ?? 'Erreur API.');
        }

        return json.data as T;
    };

    const loadAll = async (override?: Partial<AccessConfig>): Promise<void> => {
        const access: AccessConfig = {
            apiUrl: override?.apiUrl ?? apiUrl,
            token: override?.token ?? token,
        };

        if (!access.token.trim()) {
            return;
        }

        setLoading(true);
        setError('');

        try {
            const [dash, kyc, missionsRes, lit, four, usersRes, txs] = await Promise.all([
                apiFetch<DashboardData>('/admin/dashboard', access),
                apiFetch<KycUser[]>('/admin/kyc/pending?per_page=60', access),
                apiFetch<AdminMission[]>('/admin/missions?per_page=100', access),
                apiFetch<LitigeItem[]>('/admin/litiges?per_page=60', access),
                apiFetch<FournisseurItem[]>('/admin/fournisseurs/pending?per_page=60', access),
                apiFetch<AdminUser[]>('/admin/users?per_page=100', access),
                apiFetch<AdminTransaction[]>('/admin/transactions?per_page=100', access),
            ]);

            startTransition(() => {
                setDashboard(dash);
                setKycUsers(kyc ?? []);
                setMissions(missionsRes ?? []);
                setLitiges(lit ?? []);
                setFournisseurs(four ?? []);
                setUsers(usersRes ?? []);
                setTransactions(txs ?? []);
            });
        } catch (exception) {
            setError(exception instanceof Error ? exception.message : 'Impossible de charger les données.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const savedToken = localStorage.getItem('prosartisan_admin_token') ?? '';
        const savedApiUrl = localStorage.getItem('prosartisan_api_url') ?? '/api/v1';
        const savedTheme = localStorage.getItem('prosartisan_admin_theme');

        setToken(savedToken);
        setApiUrl(savedApiUrl);
        setDraftToken(savedToken);
        setDraftApiUrl(savedApiUrl);
        setThemeMode(savedTheme === 'dark' ? 'dark' : 'light');

        if (savedToken.trim()) {
            void loadAll({ apiUrl: savedApiUrl, token: savedToken });
        }
    }, []);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        localStorage.setItem('prosartisan_admin_theme', themeMode);
    }, [themeMode]);

    const persistAccess = (nextAccess: AccessConfig): void => {
        setToken(nextAccess.token);
        setApiUrl(nextAccess.apiUrl);
        setDraftToken(nextAccess.token);
        setDraftApiUrl(nextAccess.apiUrl);

        if (typeof window !== 'undefined') {
            localStorage.setItem('prosartisan_admin_token', nextAccess.token);
            localStorage.setItem('prosartisan_api_url', nextAccess.apiUrl);
        }
    };

    const clearAccess = (): void => {
        setToken('');
        setDraftToken('');
        setDashboard(null);
        setKycUsers([]);
        setMissions([]);
        setLitiges([]);
        setFournisseurs([]);
        setUsers([]);
        setTransactions([]);
        setSearch('');
        setError('');

        if (typeof window !== 'undefined') {
            localStorage.removeItem('prosartisan_admin_token');
        }
    };

    const submitAccess = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        const nextAccess: AccessConfig = {
            apiUrl: draftApiUrl.trim() || '/api/v1',
            token: draftToken.trim(),
        };

        if (!nextAccess.token) {
            setError('Ajoutez un token Sanctum administrateur pour ouvrir le backoffice.');
            return;
        }

        persistAccess(nextAccess);
        void loadAll(nextAccess);
    };

    const refreshData = (): void => {
        void loadAll();
    };

    const handleKycDecision = async (user: KycUser, decision: 'approuve' | 'rejete'): Promise<void> => {
        let rejectionReason: string | undefined;

        if (decision === 'rejete') {
            rejectionReason = window.prompt('Motif de rejet KYC (minimum 10 caractères) :') ?? '';

            if (rejectionReason.trim().length < 10) {
                window.alert('Motif trop court.');
                return;
            }
        }

        setActionLoading(true);

        try {
            await apiFetch(`/admin/kyc/${user.id}/review`, { apiUrl, token }, {
                method: 'POST',
                body: JSON.stringify({ decision, rejection_reason: rejectionReason }),
            });

            await loadAll();
        } catch (exception) {
            setError(exception instanceof Error ? exception.message : 'Action impossible.');
        } finally {
            setActionLoading(false);
        }
    };

    const handleLitigeDecision = async (litige: LitigeItem, decision: 'client' | 'artisan' | 'gel'): Promise<void> => {
        setActionLoading(true);

        try {
            await apiFetch(`/admin/litiges/${litige.id}/resolve`, { apiUrl, token }, {
                method: 'POST',
                body: JSON.stringify({ decision }),
            });

            await loadAll();
        } catch (exception) {
            setError(exception instanceof Error ? exception.message : 'Action impossible.');
        } finally {
            setActionLoading(false);
        }
    };

    const handleFournisseurDecision = async (
        fournisseur: FournisseurItem,
        decision: 'agree' | 'suspendu',
    ): Promise<void> => {
        setActionLoading(true);

        try {
            await apiFetch(`/admin/fournisseurs/${fournisseur.id}/review`, { apiUrl, token }, {
                method: 'POST',
                body: JSON.stringify({ decision }),
            });

            await loadAll();
        } catch (exception) {
            setError(exception instanceof Error ? exception.message : 'Action impossible.');
        } finally {
            setActionLoading(false);
        }
    };

    const analytics = useMemo(() => {
        const filteredKyc = kycUsers.filter((user) =>
            deferredSearch === ''
                ? true
                : normalizeSearch([user.id, user.name, user.phone, user.role, user.created_at]).includes(deferredSearch),
        );

        const filteredMissions = missions.filter((mission) =>
            deferredSearch === ''
                ? true
                : normalizeSearch([
                      mission.id,
                      mission.description,
                      mission.status,
                      mission.gemini_category,
                      mission.gemini_urgency,
                      mission.client?.name,
                      mission.artisan?.name,
                      mission.client_address,
                  ]).includes(deferredSearch),
        );

        const filteredLitiges = litiges.filter((litige) =>
            deferredSearch === ''
                ? true
                : normalizeSearch([
                      litige.id,
                      litige.mission_id,
                      litige.description,
                      litige.statut,
                      litige.decision,
                      litige.mission.client?.name,
                      litige.mission.artisan?.name,
                  ]).includes(deferredSearch),
        );

        const filteredUsers = users.filter((user) =>
            deferredSearch === ''
                ? true
                : normalizeSearch([
                      user.id,
                      user.name,
                      user.phone,
                      user.role,
                      user.kyc_status,
                      user.score_nzassa,
                  ]).includes(deferredSearch),
        );

        const filteredTransactions = transactions.filter((transaction) =>
            deferredSearch === ''
                ? true
                : normalizeSearch([
                      transaction.id,
                      transaction.type,
                      transaction.provider,
                      transaction.statut,
                      transaction.wallet_source,
                      transaction.wallet_dest,
                      transaction.user?.name,
                  ]).includes(deferredSearch),
        );

        const filteredFournisseurs = fournisseurs.filter((fournisseur) =>
            deferredSearch === ''
                ? true
                : normalizeSearch([
                      fournisseur.id,
                      fournisseur.nom_boutique,
                      fournisseur.user?.name,
                      fournisseur.user?.phone,
                  ]).includes(deferredSearch),
        );

        const confirmedTransactions = transactions.filter((transaction) => transaction.statut === 'confirme');
        const pendingTransactions = transactions.filter((transaction) => transaction.statut === 'en_attente');
        const failedTransactions = transactions.filter((transaction) => transaction.statut === 'echoue');
        const escrowTransactions = confirmedTransactions.filter((transaction) => transaction.type === 'acompte');
        const releasedTransactions = confirmedTransactions.filter((transaction) =>
            ['liberation_jalon', 'paiement_fournisseur'].includes(transaction.type),
        );
        const todayTimeline = buildTimeline(7);
        const activityTimeline = buildTimeline(15);
        const monthlyUserCount = users.filter((user) => {
            const createdAt = new Date(user.created_at);
            const today = new Date();

            return createdAt.getMonth() === today.getMonth() && createdAt.getFullYear() === today.getFullYear();
        }).length;
        const weeklyUserCount = users.filter((user) => new Date(user.created_at) >= new Date(Date.now() - 7 * 24 * 60 * 60 * 1000)).length;
        const highRiskDisputes = filteredLitiges.filter((litige) => (litige.mission.montant_total ?? 0) >= 2_000_000);
        const topArtisans = [...users]
            .filter((user) => user.role === 'artisan')
            .sort((left, right) => right.score_nzassa - left.score_nzassa)
            .slice(0, 5);
        const urgentKyc = [...filteredKyc]
            .sort((left, right) => new Date(left.created_at).getTime() - new Date(right.created_at).getTime())
            .slice(0, 6);
        const recentTransactions = [...filteredTransactions]
            .sort((left, right) => new Date(right.created_at).getTime() - new Date(left.created_at).getTime())
            .slice(0, 6);
        const recentActivity = [
            ...kycUsers.map((user) => ({
                date: user.created_at,
                detail: `${user.name} • ${roleLabels[user.role] ?? user.role}`,
                id: `kyc-${user.id}`,
                title: 'Nouveau dossier KYC',
                tone: 'amber' as const,
            })),
            ...litiges.map((litige) => ({
                date: litige.created_at,
                detail: `Mission #${litige.mission_id} • ${litige.statut}`,
                id: `litige-${litige.id}`,
                title: `Litige #${litige.id}`,
                tone: litige.statut === 'resolu' ? ('green' as const) : ('rose' as const),
            })),
            ...transactions.map((transaction) => ({
                date: transaction.created_at,
                detail: `${transactionTypeLabels[transaction.type] ?? transaction.type} • ${money(transaction.montant)}`,
                id: `tx-${transaction.id}`,
                title: `Transaction #${transaction.id}`,
                tone:
                    transaction.statut === 'confirme'
                        ? ('green' as const)
                        : transaction.statut === 'echoue'
                          ? ('rose' as const)
                          : ('blue' as const),
            })),
        ]
            .sort((left, right) => new Date(right.date).getTime() - new Date(left.date).getTime())
            .slice(0, 8);

        const acompteTrend = todayTimeline.map((point) => ({
            label: point.label,
            value: confirmedTransactions
                .filter((transaction) => transaction.created_at.slice(0, 10) === point.date && transaction.type === 'acompte')
                .reduce((sum, transaction) => sum + transaction.montant, 0),
        }));

        const releaseTrend = todayTimeline.map((point) => ({
            label: point.label,
            value: confirmedTransactions
                .filter(
                    (transaction) =>
                        transaction.created_at.slice(0, 10) === point.date &&
                        ['liberation_jalon', 'paiement_fournisseur'].includes(transaction.type),
                )
                .reduce((sum, transaction) => sum + transaction.montant, 0),
        }));

        const registrationTrend = activityTimeline.map((point) => ({
            label: point.label,
            value: users.filter((user) => user.created_at.slice(0, 10) === point.date).length,
        }));

        const operationsTrend = activityTimeline.map((point) => ({
            label: point.label,
            value:
                kycUsers.filter((user) => user.created_at.slice(0, 10) === point.date).length +
                litiges.filter((litige) => litige.created_at.slice(0, 10) === point.date).length +
                transactions.filter((transaction) => transaction.created_at.slice(0, 10) === point.date).length,
        }));

        const missionStatusMetrics: MetricItem[] = [
            {
                description: 'Missions financées et terrain',
                title: 'Missions en cours',
                tone: 'green',
                value: numberFormat.format(dashboard?.missions_en_cours ?? filteredMissions.filter((mission) => mission.status === 'en_cours').length),
            },
            {
                description: 'Escalade Référent nécessaire',
                title: 'Seuil > 2M FCFA',
                tone: 'amber',
                value: numberFormat.format(dashboard?.referent_required_open ?? 0),
            },
            {
                description: 'Missions avec arbitrage',
                title: 'Missions en litige',
                tone: 'rose',
                value: numberFormat.format(dashboard?.missions_en_litige ?? filteredMissions.filter((mission) => mission.status === 'litige').length),
            },
            {
                description: 'Analyses Gemini disponibles',
                title: 'Missions enrichies',
                tone: 'blue',
                value: numberFormat.format(filteredMissions.filter((mission) => Boolean(mission.gemini_category)).length),
            },
        ];

        return {
            acompteTrend,
            activityTrend: operationsTrend,
            confirmedTransactions,
            escrowAmount: sumAmount(escrowTransactions),
            failedTransactions,
            filteredFournisseurs,
            filteredKyc,
            filteredLitiges,
            filteredMissions,
            filteredTransactions,
            filteredUsers,
            highRiskDisputes,
            missionStatusMetrics,
            monthlyUserCount,
            pendingTransactions,
            recentActivity,
            recentTransactions,
            registrationTrend,
            releaseTrend,
            releasedAmount: sumAmount(releasedTransactions),
            topArtisans,
            urgentKyc,
            weeklyUserCount,
        };
    }, [dashboard, deferredSearch, fournisseurs, kycUsers, litiges, missions, transactions, users]);

    const totalUsers = dashboard?.users_total ?? users.length;
    const artisansActifs = dashboard?.artisans_actifs ?? users.filter((user) => user.role === 'artisan' && user.kyc_status === 'actif').length;
    const clientsActifs = dashboard?.clients_actifs ?? users.filter((user) => user.role === 'client' && user.kyc_status === 'actif').length;
    const fournisseursAgrees = dashboard?.fournisseurs_agrees ?? 0;
    const kycPending = dashboard?.kyc_en_attente ?? kycUsers.length;
    const openDisputes = dashboard?.litiges_ouverts ?? litiges.filter((litige) => litige.statut !== 'resolu').length;
    const missionsInProgress = dashboard?.missions_en_cours ?? missions.filter((mission) => mission.status === 'en_cours').length;
    const referentRequired = dashboard?.referent_required_open ?? 0;
    const fraudAlerts = dashboard?.recent_fraud_alerts ?? 0;
    const volume24h = dashboard?.volume_transactions_24h ?? 0;

    const navigation: NavigationGroup[] = [
        {
            label: 'Pilotage',
            items: [
                { id: 'dashboard', label: tabMeta.dashboard.label },
                { count: kycPending, id: 'kyc', label: tabMeta.kyc.label },
                { count: missionsInProgress, id: 'missions', label: tabMeta.missions.label },
                { count: openDisputes, id: 'litiges', label: tabMeta.litiges.label },
            ],
        },
        {
            label: 'Réseau',
            items: [
                { count: totalUsers, id: 'users', label: tabMeta.users.label },
                { count: analytics.pendingTransactions.length, id: 'transactions', label: tabMeta.transactions.label },
            ],
        },
        {
            label: 'Plateforme',
            items: [{ id: 'settings', label: tabMeta.settings.label }],
        },
    ];

    const heroStats = useMemo(() => {
        switch (activeTab) {
            case 'dashboard':
                return [
                    { label: 'Utilisateurs', tone: 'amber' as const, value: numberFormat.format(totalUsers) },
                    { label: 'Missions en cours', tone: 'green' as const, value: numberFormat.format(missionsInProgress) },
                    { label: 'KYC à valider', tone: 'blue' as const, value: numberFormat.format(kycPending) },
                    { label: 'Volume 24h', tone: 'slate' as const, value: money(volume24h) },
                ];
            case 'kyc':
                return [
                    { label: 'Dossiers ouverts', tone: 'amber' as const, value: numberFormat.format(kycPending) },
                    {
                        label: 'Artisans prioritaires',
                        tone: 'green' as const,
                        value: numberFormat.format(kycUsers.filter((user) => user.role === 'artisan').length),
                    },
                    {
                        label: 'Fournisseurs à valider',
                        tone: 'blue' as const,
                        value: numberFormat.format(fournisseurs.length),
                    },
                    {
                        label: 'Rejets récents',
                        tone: 'rose' as const,
                        value: numberFormat.format(users.filter((user) => user.kyc_status === 'rejete').length),
                    },
                ];
            case 'missions':
                return [
                    { label: 'Missions en cours', tone: 'green' as const, value: numberFormat.format(missionsInProgress) },
                    {
                        label: 'En litige',
                        tone: 'rose' as const,
                        value: numberFormat.format(dashboard?.missions_en_litige ?? 0),
                    },
                    { label: 'Référent requis', tone: 'amber' as const, value: numberFormat.format(referentRequired) },
                    {
                        label: 'Montant piloté',
                        tone: 'slate' as const,
                        value: money(
                            analytics.filteredMissions.reduce((sum, mission) => sum + (mission.montant_total ?? 0), 0),
                        ),
                    },
                ];
            case 'litiges':
                return [
                    { label: 'Litiges ouverts', tone: 'rose' as const, value: numberFormat.format(openDisputes) },
                    {
                        label: 'Haute priorité',
                        tone: 'amber' as const,
                        value: numberFormat.format(analytics.highRiskDisputes.length),
                    },
                    { label: 'Référent requis', tone: 'blue' as const, value: numberFormat.format(referentRequired) },
                    { label: 'Alertes fraude', tone: 'slate' as const, value: numberFormat.format(fraudAlerts) },
                ];
            case 'users':
                return [
                    { label: 'Comptes', tone: 'amber' as const, value: numberFormat.format(totalUsers) },
                    { label: 'Artisans actifs', tone: 'green' as const, value: numberFormat.format(artisansActifs) },
                    { label: 'Clients actifs', tone: 'blue' as const, value: numberFormat.format(clientsActifs) },
                    { label: 'Fournisseurs agréés', tone: 'slate' as const, value: numberFormat.format(fournisseursAgrees) },
                ];
            case 'transactions':
                return [
                    { label: 'Volume 24h', tone: 'amber' as const, value: money(volume24h) },
                    {
                        label: 'En attente',
                        tone: 'blue' as const,
                        value: numberFormat.format(analytics.pendingTransactions.length),
                    },
                    {
                        label: 'Échouées',
                        tone: 'rose' as const,
                        value: numberFormat.format(analytics.failedTransactions.length),
                    },
                    { label: 'Fonds libérés', tone: 'green' as const, value: money(analytics.releasedAmount) },
                ];
            case 'settings':
                return [
                    { label: 'Pays', tone: 'amber' as const, value: 'Côte d’Ivoire' },
                    { label: 'Devise', tone: 'green' as const, value: 'FCFA' },
                    { label: 'Paiements', tone: 'blue' as const, value: 'Wave / Orange' },
                    { label: 'Mode mobile', tone: 'slate' as const, value: 'Hors-ligne' },
                ];
            default:
                return [];
        }
    }, [
        activeTab,
        analytics.failedTransactions.length,
        analytics.highRiskDisputes.length,
        analytics.pendingTransactions.length,
        analytics.releasedAmount,
        analytics.filteredMissions,
        artisansActifs,
        clientsActifs,
        fournisseurs.length,
        fournisseursAgrees,
        fraudAlerts,
        kycPending,
        kycUsers,
        missionsInProgress,
        openDisputes,
        referentRequired,
        totalUsers,
        volume24h,
        users,
        dashboard?.missions_en_litige,
    ]);

    const summaryCards = [
        {
            description: `${analytics.monthlyUserCount} nouveaux ce mois`,
            title: 'Utilisateurs inscrits',
            tone: 'amber' as const,
            trend: analytics.weeklyUserCount > 0 ? `+${analytics.weeklyUserCount} sur 7 jours` : 'Aucun nouveau compte cette semaine',
            value: numberFormat.format(totalUsers),
        },
        {
            description: 'Paiements confirmés sur 24h',
            title: 'Volume du jour',
            tone: 'green' as const,
            trend: analytics.confirmedTransactions.length > 0 ? `${analytics.confirmedTransactions.length} transactions confirmées` : 'Aucune transaction confirmée',
            value: money(volume24h),
        },
        {
            description: 'Artisans, clients et fournisseurs en attente',
            title: 'Validation KYC',
            tone: 'blue' as const,
            trend: analytics.urgentKyc.length > 0 ? `${analytics.urgentKyc.length} dossiers à traiter vite` : 'File vide',
            value: numberFormat.format(kycPending),
        },
        {
            description: 'Dossiers à arbitrer maintenant',
            title: 'Litiges actifs',
            tone: 'rose' as const,
            trend: analytics.highRiskDisputes.length > 0 ? `${analytics.highRiskDisputes.length} au-dessus du seuil Référent` : 'Aucun dossier critique',
            value: numberFormat.format(openDisputes),
        },
    ];

    const isConfigured = token.trim().length > 0;
    const isInitialLoading =
        loading &&
        dashboard === null &&
        kycUsers.length === 0 &&
        missions.length === 0 &&
        litiges.length === 0 &&
        users.length === 0 &&
        transactions.length === 0;

    if (!isConfigured) {
        return (
            <>
                <Head title="ProsArtisan Backoffice" />

                <AdminAccessScreen
                    apiUrl={draftApiUrl}
                    error={error}
                    onApiUrlChange={setDraftApiUrl}
                    onSubmit={submitAccess}
                    onThemeToggle={() => setThemeMode((current) => (current === 'light' ? 'dark' : 'light'))}
                    onTokenChange={setDraftToken}
                    themeMode={themeMode}
                    token={draftToken}
                />
            </>
        );
    }

    return (
        <>
            <Head title="ProsArtisan Backoffice" />

            <div className={cn('admin-shell min-h-screen', themeMode === 'dark' && 'admin-shell--dark')}>
                <div className="relative z-10 flex min-h-screen">
                    <aside className="admin-panel hidden w-[310px] shrink-0 border-r px-5 py-6 lg:flex lg:flex-col">
                        <div className="flex items-center gap-3">
                            <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#ebb95e] text-[#241b16] shadow-[0_16px_35px_rgba(210,152,52,0.24)]">
                                <img src="/img/prosartisan-logo.png" alt="ProsArtisan" className="h-8 w-8 object-contain" />
                            </div>
                            <div className="min-w-0">
                                <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-[var(--admin-muted)]">
                                    ProsArtisan
                                </p>
                                <div className="flex items-center gap-2">
                                    <h1 className="truncate text-xl font-semibold text-[var(--admin-text)]">Backoffice</h1>
                                    <span className={cn('rounded-full border px-2 py-0.5 text-[11px] font-semibold', toneBadgeClasses('amber'))}>
                                        ADMIN
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div className="mt-8 space-y-7">
                            {navigation.map((group) => (
                                <div key={group.label}>
                                    <p className="mb-3 px-3 text-[11px] font-semibold uppercase tracking-[0.24em] text-[var(--admin-muted)]">
                                        {group.label}
                                    </p>
                                    <div className="space-y-1.5">
                                        {group.items.map((item) => {
                                            const isActive = item.id === activeTab;

                                            return (
                                                <Link
                                                    key={item.id}
                                                    href={tabRoutes[item.id]}
                                                    className={cn(
                                                        'admin-nav-link flex items-center justify-between gap-3 rounded-2xl px-3 py-3 text-sm font-medium transition',
                                                        isActive && 'admin-nav-link--active',
                                                    )}
                                                >
                                                    <span className="flex min-w-0 items-center gap-3">
                                                        <span
                                                            className={cn(
                                                                'flex h-9 w-9 items-center justify-center rounded-xl',
                                                                isActive ? 'bg-[#f8e4bc] text-[#b77918]' : 'bg-[#f5ecdf] text-[var(--admin-muted)]',
                                                            )}
                                                        >
                                                            <TabIcon tab={item.id} />
                                                        </span>
                                                        <span className="truncate">{item.label}</span>
                                                    </span>

                                                    {typeof item.count === 'number' && item.count > 0 ? (
                                                        <span className="rounded-full bg-white/80 px-2.5 py-1 text-xs font-semibold text-[#8a6b3d]">
                                                            {item.count}
                                                        </span>
                                                    ) : null}
                                                </Link>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div className="mt-auto space-y-4">
                            <Surface className="rounded-[28px] p-4">
                                <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-[var(--admin-muted)]">
                                    Contexte marché
                                </p>
                                <div className="mt-3 space-y-3 text-sm text-[var(--admin-text-soft)]">
                                    <InfoRow label="Pays" value="Côte d’Ivoire" />
                                    <InfoRow label="Devise" value="FCFA" />
                                    <InfoRow label="Paiements" value="Wave CI, Orange Money CI" />
                                    <InfoRow label="Connectivité" value="Mode hors-ligne + USSD" />
                                </div>
                            </Surface>

                            <Link
                                href={tabRoutes.settings}
                                className="flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-medium text-[var(--admin-text-soft)] transition hover:bg-white/50"
                            >
                                <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#f5ecdf] text-[var(--admin-muted)]">
                                    <TabIcon tab="settings" />
                                </span>
                                Paramètres
                            </Link>
                        </div>
                    </aside>

                    <div className="min-w-0 flex-1">
                        <header className="admin-panel sticky top-0 z-20 border-b px-4 py-4 lg:px-7">
                            <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                                <div className="flex min-w-0 flex-1 items-center gap-3">
                                    <div className="admin-input flex w-full items-center gap-3 rounded-2xl px-4 py-3 xl:max-w-[420px]">
                                        <SearchIcon className="h-5 w-5 text-[var(--admin-muted)]" />
                                        <input
                                            value={search}
                                            onChange={(event) => setSearch(event.target.value)}
                                            placeholder={searchPlaceholders[activeTab]}
                                            className="w-full bg-transparent text-sm text-[var(--admin-text)] outline-none placeholder:text-[var(--admin-muted)]"
                                        />
                                    </div>

                                    <div className="hidden items-center gap-2 xl:flex">
                                        <button type="button" className="admin-button admin-button--ghost" onClick={refreshData}>
                                            <RefreshIcon className="h-4 w-4" />
                                            Rafraîchir
                                        </button>
                                        <span className={cn('rounded-full border px-3 py-2 text-xs font-semibold', toneBadgeClasses('slate'))}>
                                            API {apiUrl.replace(/^https?:\/\//, '')}
                                        </span>
                                    </div>
                                </div>

                                <div className="flex items-center justify-between gap-3">
                                    <button
                                        type="button"
                                        className="admin-button admin-button--ghost"
                                        onClick={() => setThemeMode((current) => (current === 'light' ? 'dark' : 'light'))}
                                    >
                                        {themeMode === 'light' ? <MoonIcon className="h-4 w-4" /> : <SunIcon className="h-4 w-4" />}
                                        {themeMode === 'light' ? 'Sombre' : 'Clair'}
                                    </button>

                                    <button type="button" className="relative rounded-2xl border border-transparent p-3 text-[var(--admin-muted)] transition hover:bg-white/55">
                                        <BellIcon className="h-5 w-5" />
                                        <span className="absolute right-2 top-2 h-2.5 w-2.5 rounded-full bg-[#f15f57]" />
                                    </button>

                                    <div className="hidden items-center gap-3 rounded-3xl bg-white/50 px-3 py-2 sm:flex">
                                        <div className="flex h-11 w-11 items-center justify-center rounded-full bg-[#ebb95e] text-sm font-bold text-[#241b16]">
                                            A
                                        </div>
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-semibold text-[var(--admin-text)]">Admin ProsArtisan</p>
                                            <p className="text-xs text-[var(--admin-muted)]">Sanctum admin</p>
                                        </div>
                                    </div>

                                    <button type="button" className="rounded-2xl p-3 text-[var(--admin-muted)] transition hover:bg-white/55" onClick={clearAccess}>
                                        <LogoutIcon className="h-5 w-5" />
                                    </button>
                                </div>
                            </div>
                        </header>

                        <main className="px-4 pb-32 pt-5 lg:px-7 lg:pb-24">
                            <div className="mb-4 flex gap-2 overflow-x-auto pb-1 lg:hidden">
                                {navigation.flatMap((group) => group.items).map((item) => (
                                    <Link
                                        key={item.id}
                                        href={tabRoutes[item.id]}
                                        className={cn(
                                            'admin-chip whitespace-nowrap rounded-full border px-4 py-2 text-sm',
                                            item.id === activeTab && 'border-[#ebb95e] bg-[#f8e8c8] text-[#8a5d16]',
                                        )}
                                    >
                                        {item.label}
                                    </Link>
                                ))}
                            </div>

                            <Surface className="admin-hero rounded-[34px] p-6 lg:p-8">
                                <div className="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                                    <div className="max-w-3xl">
                                        <p className="text-[11px] font-semibold uppercase tracking-[0.26em] text-[var(--admin-muted)]">
                                            {tabMeta[activeTab].section}
                                        </p>
                                        <h2 className="mt-3 text-4xl font-semibold tracking-tight text-[var(--admin-text)]">
                                            {tabMeta[activeTab].label}
                                        </h2>
                                        <p className="mt-2 max-w-2xl text-sm leading-6 text-[var(--admin-text-soft)]">
                                            {tabMeta[activeTab].description}
                                        </p>
                                        <p className="mt-3 text-sm text-[var(--admin-muted)]">{fullDate(new Date())}</p>
                                    </div>

                                    <div className="grid gap-3 sm:grid-cols-2 xl:w-[480px]">
                                        {heroStats.map((stat) => (
                                            <div key={stat.label} className="rounded-[24px] bg-white/60 p-4 shadow-[0_18px_36px_rgba(147,119,74,0.08)]">
                                                <p className="text-xs uppercase tracking-[0.22em] text-[var(--admin-muted)]">{stat.label}</p>
                                                <div className="mt-2 flex items-center gap-2">
                                                    <span className={cn('inline-flex h-8 w-8 items-center justify-center rounded-xl', toneIconClasses(stat.tone))}>
                                                        <PulseDot />
                                                    </span>
                                                    <p className="text-lg font-semibold text-[var(--admin-text)]">{stat.value}</p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </Surface>

                            {error ? (
                                <div className="mt-4 rounded-[24px] border border-[#efc1b9] bg-[#fff3ef] px-4 py-3 text-sm text-[#b24f43]">
                                    {error}
                                </div>
                            ) : null}

                            {loading && !isInitialLoading ? (
                                <div className="mt-4 rounded-[24px] border border-[#e2d5c2] bg-white/70 px-4 py-3 text-sm text-[var(--admin-text-soft)]">
                                    Rafraîchissement des données en cours...
                                </div>
                            ) : null}

                            {isInitialLoading ? (
                                <Surface className="mt-5 rounded-[32px] p-12 text-center">
                                    <p className="text-base font-medium text-[var(--admin-text)]">Chargement du poste d’administration...</p>
                                    <p className="mt-2 text-sm text-[var(--admin-muted)]">
                                        Synchronisation des missions, validations, litiges et flux financiers.
                                    </p>
                                </Surface>
                            ) : null}

                            {!isInitialLoading && activeTab === 'dashboard' ? (
                                <section className="mt-5 space-y-5">
                                    <div className="grid gap-4 xl:grid-cols-4">
                                        {summaryCards.map((card) => (
                                            <MetricCard
                                                key={card.title}
                                                description={card.description}
                                                tone={card.tone}
                                                trend={card.trend}
                                                value={card.value}
                                            >
                                                {card.title}
                                            </MetricCard>
                                        ))}
                                    </div>

                                    <div className="grid gap-5 xl:grid-cols-[1.1fr_0.9fr]">
                                        <Surface className="rounded-[32px] p-5 lg:p-6">
                                            <SectionTitle
                                                description="Acomptes encaissés versus fonds déjà libérés aux artisans et fournisseurs."
                                                title="Flux financiers / 7 jours"
                                            />
                                            <DualLineChart
                                                series={[
                                                    { color: '#dfab4e', label: 'Acomptes confirmés', points: analytics.acompteTrend },
                                                    { color: '#e16c5f', label: 'Fonds libérés', points: analytics.releaseTrend },
                                                ]}
                                            />
                                        </Surface>

                                        <Surface className="rounded-[32px] p-5 lg:p-6">
                                            <SectionTitle
                                                description="Charge combinée KYC, litiges et transactions à traiter."
                                                title="Volume opérationnel / 15 jours"
                                            />
                                            <VolumeBarChart bars={analytics.activityTrend} color="#dfab4e" />
                                        </Surface>
                                    </div>

                                    <div className="grid gap-5 xl:grid-cols-[0.95fr_1.05fr]">
                                        <Surface className="rounded-[32px] p-5 lg:p-6">
                                            <div className="flex items-center justify-between gap-3">
                                                <SectionTitle
                                                    description="Dossiers les plus anciens ou sensibles à prendre en main."
                                                    title="File prioritaire"
                                                />
                                                <Link href={tabRoutes.kyc} className="text-sm font-medium text-[#8a6b3d] transition hover:text-[#6f531f]">
                                                    Voir tout
                                                </Link>
                                            </div>

                                            <div className="mt-5 space-y-3">
                                                {analytics.urgentKyc.length === 0 ? (
                                                    <EmptyState
                                                        description="Aucun dossier urgent en attente de revue."
                                                        title="File KYC vide"
                                                    />
                                                ) : (
                                                    analytics.urgentKyc.map((user) => (
                                                        <div key={user.id} className="rounded-[24px] border border-[var(--admin-border)] bg-white/60 p-4">
                                                            <div className="flex items-start justify-between gap-3">
                                                                <div className="flex min-w-0 items-start gap-3">
                                                                    <AvatarBubble label={user.name} />
                                                                    <div className="min-w-0">
                                                                        <p className="truncate text-sm font-semibold text-[var(--admin-text)]">{user.name}</p>
                                                                        <p className="text-xs text-[var(--admin-muted)]">{user.phone}</p>
                                                                    </div>
                                                                </div>
                                                                <RoleBadge role={user.role} />
                                                            </div>
                                                            <div className="mt-3 flex flex-wrap gap-2">
                                                                {user.kyc_documents.map((document) => (
                                                                    <a
                                                                        key={document.id}
                                                                        href={document.file_url}
                                                                        target="_blank"
                                                                        rel="noreferrer"
                                                                        className="rounded-full border border-[#e6d3b2] px-3 py-1 text-xs font-medium text-[#8b6732] transition hover:bg-[#fbf1db]"
                                                                    >
                                                                        {document.type.toUpperCase()}
                                                                    </a>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    ))
                                                )}
                                            </div>
                                        </Surface>

                                        <Surface className="rounded-[32px] p-5 lg:p-6">
                                            <div className="flex items-center justify-between gap-3">
                                                <SectionTitle
                                                    description="Signaux récents sur les paiements, validations et arbitrages."
                                                    title="Activité récente"
                                                />
                                                <Link href={tabRoutes.transactions} className="text-sm font-medium text-[#8a6b3d] transition hover:text-[#6f531f]">
                                                    Ouvrir finance
                                                </Link>
                                            </div>

                                            <div className="mt-5 space-y-3">
                                                {analytics.recentActivity.length === 0 ? (
                                                    <EmptyState description="Aucune activité récente détectée." title="Journal vide" />
                                                ) : (
                                                    analytics.recentActivity.map((activity) => (
                                                        <div key={activity.id} className="flex items-start gap-3 rounded-[24px] border border-[var(--admin-border)] bg-white/60 p-4">
                                                            <div className={cn('mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl', toneIconClasses(activity.tone))}>
                                                                <PulseDot />
                                                            </div>
                                                            <div className="min-w-0">
                                                                <p className="text-sm font-semibold text-[var(--admin-text)]">{activity.title}</p>
                                                                <p className="mt-1 text-sm text-[var(--admin-text-soft)]">{activity.detail}</p>
                                                                <p className="mt-1 text-xs text-[var(--admin-muted)]">{shortDate(activity.date)}</p>
                                                            </div>
                                                        </div>
                                                    ))
                                                )}
                                            </div>
                                        </Surface>
                                    </div>

                                    <div className="grid gap-5 xl:grid-cols-3">
                                        <Surface className="rounded-[32px] p-5 lg:p-6">
                                            <SectionTitle description="Vue sur les fonds sous séquestre actuellement confirmés." title="Séquestre" />
                                            <p className="mt-5 text-4xl font-semibold text-[var(--admin-text)]">{money(analytics.escrowAmount)}</p>
                                            <p className="mt-2 text-sm text-[var(--admin-text-soft)]">
                                                Acomptes clients confirmés et immobilisés pour les missions.
                                            </p>
                                        </Surface>

                                        <Surface className="rounded-[32px] p-5 lg:p-6">
                                            <SectionTitle description="Montants déjà distribués selon OTP et règlements fournisseurs." title="Fonds libérés" />
                                            <p className="mt-5 text-4xl font-semibold text-[var(--admin-text)]">{money(analytics.releasedAmount)}</p>
                                            <p className="mt-2 text-sm text-[var(--admin-text-soft)]">
                                                Jalons validés et règlements matériaux exécutés.
                                            </p>
                                        </Surface>

                                        <Surface className="rounded-[32px] p-5 lg:p-6">
                                            <SectionTitle description="Comptes artisans les plus solides pour le matching et le micro-crédit." title="Top Score N'Zassa" />
                                            <div className="mt-5 space-y-3">
                                                {analytics.topArtisans.length === 0 ? (
                                                    <EmptyState description="Aucun artisan scoré pour l’instant." title="Pas de classement" />
                                                ) : (
                                                    analytics.topArtisans.map((artisan) => (
                                                        <div key={artisan.id} className="flex items-center justify-between rounded-[22px] border border-[var(--admin-border)] bg-white/60 px-4 py-3">
                                                            <div className="min-w-0">
                                                                <p className="truncate text-sm font-semibold text-[var(--admin-text)]">{artisan.name}</p>
                                                                <p className="text-xs text-[var(--admin-muted)]">{artisan.phone}</p>
                                                            </div>
                                                            <span className={cn('rounded-full border px-3 py-1 text-xs font-semibold', toneBadgeClasses('blue'))}>
                                                                {artisan.score_nzassa}/100
                                                            </span>
                                                        </div>
                                                    ))
                                                )}
                                            </div>
                                        </Surface>
                                    </div>
                                </section>
                            ) : null}

                            {!isInitialLoading && activeTab === 'kyc' ? (
                                <section className="mt-5 space-y-5">
                                    <div className="grid gap-4 xl:grid-cols-4">
                                        <MetricCard description="Clients, artisans et fournisseurs en attente" tone="amber" trend="À traiter sans friction" value={numberFormat.format(kycPending)}>
                                            Dossiers ouverts
                                        </MetricCard>
                                        <MetricCard
                                            description="Demandes à fort impact sur la disponibilité terrain"
                                            tone="green"
                                            trend="Priorité aux artisans"
                                            value={numberFormat.format(kycUsers.filter((user) => user.role === 'artisan').length)}
                                        >
                                            Artisans en attente
                                        </MetricCard>
                                        <MetricCard
                                            description="Boutiques à activer pour le scan des J-Codes"
                                            tone="blue"
                                            trend="Agrément fournisseur"
                                            value={numberFormat.format(fournisseurs.length)}
                                        >
                                            Fournisseurs à revoir
                                        </MetricCard>
                                        <MetricCard
                                            description="Comptes déjà bloqués après revue"
                                            tone="rose"
                                            trend="Historique des rejets"
                                            value={numberFormat.format(users.filter((user) => user.kyc_status === 'rejete').length)}
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

                                            <DataTable className="mt-5">
                                                <thead>
                                                    <tr>
                                                        <th>Utilisateur</th>
                                                        <th>Rôle</th>
                                                        <th>Documents</th>
                                                        <th>Déposé le</th>
                                                        <th className="text-right">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {analytics.filteredKyc.length === 0 ? (
                                                        <tr>
                                                            <td colSpan={5}>
                                                                <EmptyState
                                                                    description="Aucun dossier ne correspond à votre recherche."
                                                                    title="Rien à afficher"
                                                                />
                                                            </td>
                                                        </tr>
                                                    ) : (
                                                        analytics.filteredKyc.map((user) => (
                                                            <tr key={user.id}>
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
                                                                    <div className="flex justify-end gap-2">
                                                                        <button
                                                                            type="button"
                                                                            disabled={actionLoading}
                                                                            onClick={() => void handleKycDecision(user, 'approuve')}
                                                                            className={actionButtonClass('success')}
                                                                        >
                                                                            Approuver
                                                                        </button>
                                                                        <button
                                                                            type="button"
                                                                            disabled={actionLoading}
                                                                            onClick={() => void handleKycDecision(user, 'rejete')}
                                                                            className={actionButtonClass('danger')}
                                                                        >
                                                                            Rejeter
                                                                        </button>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        ))
                                                    )}
                                                </tbody>
                                            </DataTable>
                                        </Surface>

                                        <div className="space-y-5">
                                            <Surface className="rounded-[32px] p-5 lg:p-6">
                                                <SectionTitle
                                                    description="Demandes fournisseurs à activer avant les prochains scans J-Code."
                                                    title="Boutiques à agréer"
                                                />

                                                <div className="mt-5 space-y-3">
                                                    {analytics.filteredFournisseurs.length === 0 ? (
                                                        <EmptyState description="Aucune boutique en attente." title="File fournisseur vide" />
                                                    ) : (
                                                        analytics.filteredFournisseurs.map((fournisseur) => (
                                                            <div key={fournisseur.id} className="rounded-[24px] border border-[var(--admin-border)] bg-white/60 p-4">
                                                                <p className="text-sm font-semibold text-[var(--admin-text)]">{fournisseur.nom_boutique}</p>
                                                                <p className="mt-1 text-sm text-[var(--admin-text-soft)]">
                                                                    {fournisseur.user?.name ?? 'Contact inconnu'}
                                                                </p>
                                                                <p className="text-xs text-[var(--admin-muted)]">
                                                                    {fournisseur.user?.phone ?? 'Téléphone non renseigné'} • {shortDate(fournisseur.created_at)}
                                                                </p>
                                                                <div className="mt-4 flex gap-2">
                                                                    <button
                                                                        type="button"
                                                                        disabled={actionLoading}
                                                                        onClick={() => void handleFournisseurDecision(fournisseur, 'agree')}
                                                                        className={actionButtonClass('success')}
                                                                    >
                                                                        Agréer
                                                                    </button>
                                                                    <button
                                                                        type="button"
                                                                        disabled={actionLoading}
                                                                        onClick={() => void handleFournisseurDecision(fournisseur, 'suspendu')}
                                                                        className={actionButtonClass('danger')}
                                                                    >
                                                                        Suspendre
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        ))
                                                    )}
                                                </div>
                                            </Surface>

                                            <Surface className="rounded-[32px] p-5 lg:p-6">
                                                <SectionTitle description="Répartition des créations récentes de comptes." title="Nouveaux comptes / 15 jours" />
                                                <VolumeBarChart bars={analytics.registrationTrend} color="#d59a37" />
                                            </Surface>
                                        </div>
                                    </div>
                                </section>
                            ) : null}

                            {!isInitialLoading && activeTab === 'missions' ? (
                                <section className="mt-5 space-y-5">
                                    <div className="grid gap-4 xl:grid-cols-4">
                                        {analytics.missionStatusMetrics.map((metric) => (
                                            <MetricCard
                                                key={metric.title}
                                                description={metric.description}
                                                tone={metric.tone}
                                                trend="Lecture en temps réel"
                                                value={metric.value}
                                            >
                                                {metric.title}
                                            </MetricCard>
                                        ))}
                                    </div>

                                    <Surface className="rounded-[32px] p-5 lg:p-6">
                                        <SectionTitle
                                            description="Vision claire des missions, enrichissement Gemini et montant piloté."
                                            title="Pipeline missions"
                                        />

                                        <DataTable className="mt-5">
                                            <thead>
                                                <tr>
                                                    <th>Mission</th>
                                                    <th>Client / Artisan</th>
                                                    <th>Analyse Gemini</th>
                                                    <th>Montant</th>
                                                    <th>Zone</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {analytics.filteredMissions.length === 0 ? (
                                                    <tr>
                                                        <td colSpan={6}>
                                                            <EmptyState description="Aucune mission ne correspond à votre recherche." title="Mission introuvable" />
                                                        </td>
                                                    </tr>
                                                ) : (
                                                    analytics.filteredMissions.map((mission) => (
                                                        <tr key={mission.id}>
                                                            <td>
                                                                <div className="space-y-2">
                                                                    <div className="flex items-center gap-2">
                                                                        <span className="text-sm font-semibold text-[var(--admin-text)]">#{mission.id}</span>
                                                                        <MissionStatusBadge status={mission.status} />
                                                                    </div>
                                                                    <p className="max-w-[280px] text-sm text-[var(--admin-text-soft)]">{mission.description}</p>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div className="space-y-1 text-sm text-[var(--admin-text-soft)]">
                                                                    <p>
                                                                        <span className="font-medium text-[var(--admin-text)]">Client:</span> {mission.client?.name ?? 'Non renseigné'}
                                                                    </p>
                                                                    <p>
                                                                        <span className="font-medium text-[var(--admin-text)]">Artisan:</span> {mission.artisan?.name ?? 'Non affecté'}
                                                                    </p>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div className="space-y-1 text-sm text-[var(--admin-text-soft)]">
                                                                    <p className="font-semibold text-[var(--admin-text)]">{mission.gemini_category ?? 'Non classée'}</p>
                                                                    <p>Urgence: {mission.gemini_urgency ?? 'N/A'}</p>
                                                                    <p>
                                                                        Estimation:{' '}
                                                                        {mission.gemini_estimation_min && mission.gemini_estimation_max
                                                                            ? `${money(mission.gemini_estimation_min)} - ${money(mission.gemini_estimation_max)}`
                                                                            : 'Non disponible'}
                                                                    </p>
                                                                </div>
                                                            </td>
                                                            <td className="text-sm font-semibold text-[var(--admin-text)]">
                                                                {mission.montant_total ? money(mission.montant_total) : 'Non défini'}
                                                            </td>
                                                            <td className="text-sm text-[var(--admin-text-soft)]">{mission.client_address ?? 'Adresse non renseignée'}</td>
                                                            <td className="text-sm text-[var(--admin-text-soft)]">{shortDate(mission.created_at)}</td>
                                                        </tr>
                                                    ))
                                                )}
                                            </tbody>
                                        </DataTable>
                                    </Surface>
                                </section>
                            ) : null}

                            {!isInitialLoading && activeTab === 'litiges' ? (
                                <section className="mt-5 space-y-5">
                                    <div className="grid gap-4 xl:grid-cols-4">
                                        <MetricCard description="Dossiers non résolus" tone="rose" trend="À arbitrer" value={numberFormat.format(openDisputes)}>
                                            Litiges actifs
                                        </MetricCard>
                                        <MetricCard
                                            description="Dossiers au-dessus de 2 000 000 FCFA"
                                            tone="amber"
                                            trend="Visite Référent à prévoir"
                                            value={numberFormat.format(analytics.highRiskDisputes.length)}
                                        >
                                            Haute priorité
                                        </MetricCard>
                                        <MetricCard description="Missions au statut litige" tone="blue" trend="Impact opérationnel" value={numberFormat.format(dashboard?.missions_en_litige ?? 0)}>
                                            Missions bloquées
                                        </MetricCard>
                                        <MetricCard description="Signaux potentiels de fraude" tone="slate" trend="Surveillance J-Code" value={numberFormat.format(fraudAlerts)}>
                                            Alertes
                                        </MetricCard>
                                    </div>

                                    <div className="grid gap-5 xl:grid-cols-2">
                                        {analytics.filteredLitiges.length === 0 ? (
                                            <Surface className="rounded-[32px] p-6 xl:col-span-2">
                                                <EmptyState description="Aucun litige ne correspond à votre filtre." title="Aucun litige affiché" />
                                            </Surface>
                                        ) : (
                                            analytics.filteredLitiges.map((litige) => (
                                                <Surface key={litige.id} className="rounded-[32px] p-5 lg:p-6">
                                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                                        <div>
                                                            <div className="flex items-center gap-2">
                                                                <h3 className="text-lg font-semibold text-[var(--admin-text)]">Litige #{litige.id}</h3>
                                                                <LitigeStatusBadge status={litige.statut} />
                                                            </div>
                                                            <p className="mt-1 text-sm text-[var(--admin-muted)]">
                                                                Mission #{litige.mission_id} • {shortDate(litige.created_at)}
                                                            </p>
                                                        </div>

                                                        {litige.decision ? <DecisionBadge decision={litige.decision} /> : null}
                                                    </div>

                                                    <p className="mt-4 text-sm leading-6 text-[var(--admin-text-soft)]">{litige.description}</p>

                                                    <div className="mt-5 grid gap-3 sm:grid-cols-3">
                                                        <InfoPill label="Client" value={litige.mission.client?.name ?? 'N/A'} />
                                                        <InfoPill label="Artisan" value={litige.mission.artisan?.name ?? 'N/A'} />
                                                        <InfoPill label="Montant" value={money(litige.mission.montant_total ?? 0)} />
                                                    </div>

                                                    {litige.statut !== 'resolu' ? (
                                                        <div className="mt-5 flex flex-wrap gap-2">
                                                            <button
                                                                type="button"
                                                                disabled={actionLoading}
                                                                onClick={() => void handleLitigeDecision(litige, 'client')}
                                                                className={actionButtonClass('danger')}
                                                            >
                                                                Rembourser client
                                                            </button>
                                                            <button
                                                                type="button"
                                                                disabled={actionLoading}
                                                                onClick={() => void handleLitigeDecision(litige, 'artisan')}
                                                                className={actionButtonClass('success')}
                                                            >
                                                                Payer artisan
                                                            </button>
                                                            <button
                                                                type="button"
                                                                disabled={actionLoading}
                                                                onClick={() => void handleLitigeDecision(litige, 'gel')}
                                                                className={actionButtonClass('secondary')}
                                                            >
                                                                Geler et envoyer Référent
                                                            </button>
                                                        </div>
                                                    ) : (
                                                        <p className="mt-5 text-sm text-[var(--admin-text-soft)]">
                                                            Décision finale: {litige.decision ? litigeDecisionLabels[litige.decision] : 'Aucune'}
                                                        </p>
                                                    )}
                                                </Surface>
                                            ))
                                        )}
                                    </div>
                                </section>
                            ) : null}

                            {!isInitialLoading && activeTab === 'users' ? (
                                <section className="mt-5 space-y-5">
                                    <div className="grid gap-4 xl:grid-cols-4">
                                        <MetricCard description="Base complète des comptes connectés à la plateforme" tone="amber" trend="Communauté totale" value={numberFormat.format(totalUsers)}>
                                            Utilisateurs
                                        </MetricCard>
                                        <MetricCard description="Comptes artisans KYC actifs" tone="green" trend="Capacité terrain" value={numberFormat.format(artisansActifs)}>
                                            Artisans actifs
                                        </MetricCard>
                                        <MetricCard description="Clients opérationnels avec KYC actif" tone="blue" trend="Demande solvable" value={numberFormat.format(clientsActifs)}>
                                            Clients actifs
                                        </MetricCard>
                                        <MetricCard description="Boutiques déjà approuvées" tone="slate" trend="Réseau matériaux" value={numberFormat.format(fournisseursAgrees)}>
                                            Fournisseurs agréés
                                        </MetricCard>
                                    </div>

                                    <div className="grid gap-5 xl:grid-cols-[1.15fr_0.85fr]">
                                        <Surface className="rounded-[32px] p-5 lg:p-6">
                                            <SectionTitle
                                                description="Vue consolidée des comptes, rôles, KYC et activité mission."
                                                title="Répertoire utilisateurs"
                                            />

                                            <DataTable className="mt-5">
                                                <thead>
                                                    <tr>
                                                        <th>Utilisateur</th>
                                                        <th>Rôle</th>
                                                        <th>KYC</th>
                                                        <th>Score</th>
                                                        <th>Missions</th>
                                                        <th>Inscrit</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {analytics.filteredUsers.length === 0 ? (
                                                        <tr>
                                                            <td colSpan={6}>
                                                                <EmptyState description="Aucun compte ne correspond à votre recherche." title="Liste vide" />
                                                            </td>
                                                        </tr>
                                                    ) : (
                                                        analytics.filteredUsers.map((user) => (
                                                            <tr key={user.id}>
                                                                <td>
                                                                    <div className="flex items-center gap-3">
                                                                        <AvatarBubble label={user.name} />
                                                                        <div className="min-w-0">
                                                                            <p className="truncate font-semibold text-[var(--admin-text)]">{user.name}</p>
                                                                            <p className="text-xs text-[var(--admin-muted)]">
                                                                                #{user.id} • {user.phone}
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
                                                                <td className="text-sm font-semibold text-[var(--admin-text)]">{user.score_nzassa}/100</td>
                                                                <td className="text-sm text-[var(--admin-text-soft)]">
                                                                    Client: {user.missions_client_count} • Artisan: {user.missions_artisan_count}
                                                                </td>
                                                                <td className="text-sm text-[var(--admin-text-soft)]">{shortDate(user.created_at)}</td>
                                                            </tr>
                                                        ))
                                                    )}
                                                </tbody>
                                            </DataTable>
                                        </Surface>

                                        <div className="space-y-5">
                                            <Surface className="rounded-[32px] p-5 lg:p-6">
                                                <SectionTitle
                                                    description="Demandes boutiques toujours en attente d’agrément."
                                                    title="Fournisseurs en attente"
                                                />

                                                <div className="mt-5 space-y-3">
                                                    {analytics.filteredFournisseurs.length === 0 ? (
                                                        <EmptyState description="Aucune boutique en attente." title="Tout est à jour" />
                                                    ) : (
                                                        analytics.filteredFournisseurs.map((fournisseur) => (
                                                            <div key={fournisseur.id} className="rounded-[24px] border border-[var(--admin-border)] bg-white/60 p-4">
                                                                <p className="text-sm font-semibold text-[var(--admin-text)]">{fournisseur.nom_boutique}</p>
                                                                <p className="mt-1 text-sm text-[var(--admin-text-soft)]">{fournisseur.user?.name ?? 'Contact inconnu'}</p>
                                                                <p className="text-xs text-[var(--admin-muted)]">{fournisseur.user?.phone ?? 'Téléphone non renseigné'}</p>
                                                                <div className="mt-4 flex gap-2">
                                                                    <button
                                                                        type="button"
                                                                        disabled={actionLoading}
                                                                        onClick={() => void handleFournisseurDecision(fournisseur, 'agree')}
                                                                        className={actionButtonClass('success')}
                                                                    >
                                                                        Agréer
                                                                    </button>
                                                                    <button
                                                                        type="button"
                                                                        disabled={actionLoading}
                                                                        onClick={() => void handleFournisseurDecision(fournisseur, 'suspendu')}
                                                                        className={actionButtonClass('danger')}
                                                                    >
                                                                        Suspendre
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        ))
                                                    )}
                                                </div>
                                            </Surface>

                                            <Surface className="rounded-[32px] p-5 lg:p-6">
                                                <SectionTitle description="Comptes artisans les mieux scorés pour les futures affectations." title="Top artisans" />
                                                <div className="mt-5 space-y-3">
                                                    {analytics.topArtisans.map((artisan) => (
                                                        <div key={artisan.id} className="flex items-center justify-between rounded-[22px] border border-[var(--admin-border)] bg-white/60 px-4 py-3">
                                                            <div className="min-w-0">
                                                                <p className="truncate text-sm font-semibold text-[var(--admin-text)]">{artisan.name}</p>
                                                                <p className="text-xs text-[var(--admin-muted)]">{artisan.phone}</p>
                                                            </div>
                                                            <span className={cn('rounded-full border px-3 py-1 text-xs font-semibold', toneBadgeClasses('green'))}>
                                                                {artisan.score_nzassa}/100
                                                            </span>
                                                        </div>
                                                    ))}
                                                </div>
                                            </Surface>
                                        </div>
                                    </div>
                                </section>
                            ) : null}

                            {!isInitialLoading && activeTab === 'transactions' ? (
                                <section className="mt-5 space-y-5">
                                    <div className="grid gap-4 xl:grid-cols-4">
                                        <MetricCard description="Acomptes clients confirmés" tone="amber" trend="Argent séquestré" value={money(analytics.escrowAmount)}>
                                            Wallet matériaux
                                        </MetricCard>
                                        <MetricCard description="Paiements jalons et fournisseurs" tone="green" trend="Argent distribué" value={money(analytics.releasedAmount)}>
                                            Fonds libérés
                                        </MetricCard>
                                        <MetricCard description="Transactions en file ou en attente provider" tone="blue" trend="Suivi temps réel" value={numberFormat.format(analytics.pendingTransactions.length)}>
                                            En attente
                                        </MetricCard>
                                        <MetricCard description="Transactions à rejouer ou analyser" tone="rose" trend="Échecs" value={numberFormat.format(analytics.failedTransactions.length)}>
                                            Échouées
                                        </MetricCard>
                                    </div>

                                    <Surface className="rounded-[32px] p-5 lg:p-6">
                                        <SectionTitle
                                            description="Flux Wave, Orange Money et remboursements classés par statut."
                                            title="Journal financier"
                                        />

                                        <DataTable className="mt-5">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Type</th>
                                                    <th>Montant</th>
                                                    <th>Provider</th>
                                                    <th>Statut</th>
                                                    <th>Bénéficiaire</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {analytics.filteredTransactions.length === 0 ? (
                                                    <tr>
                                                        <td colSpan={7}>
                                                            <EmptyState description="Aucune transaction ne correspond à votre recherche." title="Aucun flux trouvé" />
                                                        </td>
                                                    </tr>
                                                ) : (
                                                    analytics.filteredTransactions.map((transaction) => (
                                                        <tr key={transaction.id}>
                                                            <td className="font-semibold text-[var(--admin-text)]">#{transaction.id}</td>
                                                            <td className="text-sm text-[var(--admin-text-soft)]">
                                                                {transactionTypeLabels[transaction.type] ?? transaction.type}
                                                            </td>
                                                            <td className="text-sm font-semibold text-[var(--admin-text)]">{money(transaction.montant)}</td>
                                                            <td>
                                                                <ProviderBadge provider={transaction.provider} />
                                                            </td>
                                                            <td>
                                                                <TransactionStatusBadge status={transaction.statut} />
                                                            </td>
                                                            <td className="text-sm text-[var(--admin-text-soft)]">{transaction.user?.name ?? 'Non renseigné'}</td>
                                                            <td className="text-sm text-[var(--admin-text-soft)]">{shortDate(transaction.created_at)}</td>
                                                        </tr>
                                                    ))
                                                )}
                                            </tbody>
                                        </DataTable>
                                    </Surface>
                                </section>
                            ) : null}

                            {!isInitialLoading && activeTab === 'settings' ? (
                                <section className="mt-5 grid gap-5 xl:grid-cols-2">
                                    <Surface className="rounded-[32px] p-5 lg:p-6">
                                        <SectionTitle
                                            description="Garde-fous fonctionnels à ne jamais contourner dans ProsArtisan."
                                            title="Règles métier critiques"
                                        />
                                        <ul className="mt-5 space-y-3 text-sm leading-6 text-[var(--admin-text-soft)]">
                                            <li>Client et artisan doivent être KYC actifs avant toute mission ou transaction.</li>
                                            <li>Le ratio de fragmentation matériaux / main d’œuvre reste figé après acceptation du devis.</li>
                                            <li>Le scan J-Code doit toujours vérifier une distance fournisseur inférieure ou égale à 100 m.</li>
                                            <li>Aucune libération de fonds sans OTP jalon validé côté client.</li>
                                            <li>Toute mission au-delà de 2 000 000 FCFA exige une validation physique Référent.</li>
                                            <li>Les montants financiers restent en BIGINT FCFA, sans float ni double.</li>
                                        </ul>
                                    </Surface>

                                    <Surface className="rounded-[32px] p-5 lg:p-6">
                                        <SectionTitle
                                            description="Configuration de session du poste d’administration courant."
                                            title="Connexion backoffice"
                                        />

                                        <form className="mt-5 space-y-4" onSubmit={submitAccess}>
                                            <label className="block space-y-2">
                                                <span className="text-xs font-semibold uppercase tracking-[0.22em] text-[var(--admin-muted)]">
                                                    URL API
                                                </span>
                                                <input
                                                    value={draftApiUrl}
                                                    onChange={(event) => setDraftApiUrl(event.target.value)}
                                                    className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                                                />
                                            </label>
                                            <label className="block space-y-2">
                                                <span className="text-xs font-semibold uppercase tracking-[0.22em] text-[var(--admin-muted)]">
                                                    Token Sanctum
                                                </span>
                                                <input
                                                    type="password"
                                                    value={draftToken}
                                                    onChange={(event) => setDraftToken(event.target.value)}
                                                    className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                                                />
                                            </label>
                                            <div className="flex flex-wrap gap-3">
                                                <button type="submit" className="admin-button admin-button--primary">
                                                    Mettre à jour l’accès
                                                </button>
                                                <button type="button" className="admin-button admin-button--ghost" onClick={refreshData}>
                                                    Rafraîchir les données
                                                </button>
                                            </div>
                                        </form>

                                        <div className="mt-5 grid gap-3 sm:grid-cols-2">
                                            <InfoPill label="Langue" value="Français" />
                                            <InfoPill label="Paiements" value="Wave CI / Orange Money CI" />
                                            <InfoPill label="Mobile" value="Android prioritaire" />
                                            <InfoPill label="Connectivité" value="Hors-ligne + USSD" />
                                        </div>
                                    </Surface>
                                </section>
                            ) : null}
                        </main>

                        <footer className="px-4 pb-6 lg:px-7">
                            <div className="border-t border-[var(--admin-border)] pt-6 text-sm text-[var(--admin-text-soft)]">
                                <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-[var(--admin-muted)]">Administration</p>
                                <p className="mt-2 text-xl font-semibold text-[var(--admin-text)]">ProsArtisan Backoffice</p>
                                <p className="mt-1 max-w-3xl">
                                    Pilotage des contenus, des validations, des opérations terrain et des flux financiers dans une seule interface.
                                </p>
                            </div>
                        </footer>
                    </div>
                </div>

                <BottomDock activeTab={activeTab} />
            </div>
        </>
    );
}

function AdminAccessScreen({
    apiUrl,
    error,
    onApiUrlChange,
    onSubmit,
    onThemeToggle,
    onTokenChange,
    themeMode,
    token,
}: {
    apiUrl: string;
    error: string;
    onApiUrlChange: (value: string) => void;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    onThemeToggle: () => void;
    onTokenChange: (value: string) => void;
    themeMode: ThemeMode;
    token: string;
}) {
    return (
        <div className={cn('admin-shell min-h-screen', themeMode === 'dark' && 'admin-shell--dark')}>
            <div className="relative z-10 flex min-h-screen flex-col px-4 py-5 lg:px-5">
                <div className="flex justify-end">
                    <button type="button" className="admin-button admin-button--ghost" onClick={onThemeToggle}>
                        {themeMode === 'light' ? <MoonIcon className="h-4 w-4" /> : <SunIcon className="h-4 w-4" />}
                        {themeMode === 'light' ? 'Sombre' : 'Clair'}
                    </button>
                </div>

                <div className="mx-auto flex w-full max-w-[1180px] flex-1 items-center justify-center py-10">
                    <div className="grid w-full items-center gap-10 lg:grid-cols-[0.9fr_0.8fr]">
                        <div className="hidden lg:block">
                            <div className="flex items-center gap-4">
                                <div className="flex h-16 w-16 items-center justify-center rounded-[22px] bg-[#ebb95e] text-[#241b16] shadow-[0_20px_50px_rgba(210,152,52,0.24)]">
                                    <img src="/img/prosartisan-logo.png" alt="ProsArtisan" className="h-10 w-10 object-contain" />
                                </div>
                                <div>
                                    <p className="text-sm font-semibold uppercase tracking-[0.26em] text-[var(--admin-muted)]">ProsArtisan</p>
                                    <h1 className="mt-1 text-4xl font-semibold text-[var(--admin-text)]">Backoffice</h1>
                                </div>
                            </div>

                            <div className="mt-10 max-w-xl space-y-4">
                                <p className="text-5xl font-semibold leading-tight text-[var(--admin-text)]">
                                    Un vrai poste de pilotage pour les validations, les missions et les flux terrain.
                                </p>
                                <p className="max-w-lg text-base leading-7 text-[var(--admin-text-soft)]">
                                    Connectez un token Sanctum administrateur existant et travaillez dans une interface pensée pour l’exploitation, pas pour lire une API.
                                </p>
                            </div>

                            <div className="mt-10 grid gap-4 sm:grid-cols-2">
                                <Surface className="rounded-[28px] p-5">
                                    <p className="text-xs font-semibold uppercase tracking-[0.22em] text-[var(--admin-muted)]">Opérations</p>
                                    <p className="mt-3 text-lg font-semibold text-[var(--admin-text)]">KYC, missions, litiges</p>
                                    <p className="mt-2 text-sm leading-6 text-[var(--admin-text-soft)]">
                                        Tout l’essentiel pour traiter les urgences du jour sans quitter l’écran.
                                    </p>
                                </Surface>
                                <Surface className="rounded-[28px] p-5">
                                    <p className="text-xs font-semibold uppercase tracking-[0.22em] text-[var(--admin-muted)]">Finance</p>
                                    <p className="mt-3 text-lg font-semibold text-[var(--admin-text)]">Wave, Orange, séquestre</p>
                                    <p className="mt-2 text-sm leading-6 text-[var(--admin-text-soft)]">
                                        Lecture claire des paiements, déblocages OTP et règlements fournisseurs.
                                    </p>
                                </Surface>
                            </div>
                        </div>

                        <Surface className="mx-auto w-full max-w-[520px] rounded-[34px] p-7 lg:p-10">
                            <div className="mx-auto mb-7 flex w-fit items-center gap-3 lg:hidden">
                                <div className="flex h-14 w-14 items-center justify-center rounded-[20px] bg-[#ebb95e] text-[#241b16] shadow-[0_18px_40px_rgba(210,152,52,0.24)]">
                                    <img src="/img/prosartisan-logo.png" alt="ProsArtisan" className="h-8 w-8 object-contain" />
                                </div>
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-[0.24em] text-[var(--admin-muted)]">ProsArtisan</p>
                                    <h2 className="text-2xl font-semibold text-[var(--admin-text)]">Backoffice</h2>
                                </div>
                            </div>

                            <p className="text-sm font-semibold uppercase tracking-[0.24em] text-[var(--admin-muted)]">Accès privé</p>
                            <h2 className="mt-3 text-4xl font-semibold text-[var(--admin-text)]">Accès BackOffice</h2>
                            <p className="mt-3 text-sm leading-6 text-[var(--admin-text-soft)]">
                                Réservé aux administrateurs disposant déjà d’un token Sanctum valide.
                            </p>

                            <form className="mt-8 space-y-5" onSubmit={onSubmit}>
                                <label className="block space-y-2">
                                    <span className="text-xs font-semibold uppercase tracking-[0.22em] text-[var(--admin-muted)]">URL API</span>
                                    <input
                                        value={apiUrl}
                                        onChange={(event) => onApiUrlChange(event.target.value)}
                                        className="admin-input w-full rounded-2xl px-4 py-4 text-sm outline-none"
                                        placeholder="/api/v1"
                                    />
                                </label>

                                <label className="block space-y-2">
                                    <span className="text-xs font-semibold uppercase tracking-[0.22em] text-[var(--admin-muted)]">Token Sanctum</span>
                                    <input
                                        type="password"
                                        value={token}
                                        onChange={(event) => onTokenChange(event.target.value)}
                                        className="admin-input w-full rounded-2xl px-4 py-4 text-sm outline-none"
                                        placeholder="Collez ici le token admin"
                                    />
                                </label>

                                <button type="submit" className="admin-button admin-button--primary w-full justify-center">
                                    Ouvrir le poste d’administration
                                </button>
                            </form>

                            {error ? (
                                <div className="mt-4 rounded-[22px] border border-[#efc1b9] bg-[#fff3ef] px-4 py-3 text-sm text-[#b24f43]">
                                    {error}
                                </div>
                            ) : null}

                            <p className="mt-6 text-center text-sm text-[var(--admin-text-soft)]">
                                Besoin du front public ?{' '}
                                <Link href="/" className="font-medium text-[#b77918] transition hover:text-[#8a5d16]">
                                    Retour à l’application
                                </Link>
                            </p>
                        </Surface>
                    </div>
                </div>
            </div>
        </div>
    );
}

function Surface({ children, className = '' }: { children: ReactNode; className?: string }) {
    return <section className={cn('admin-panel admin-surface border', className)}>{children}</section>;
}

function MetricCard({
    children,
    description,
    tone,
    trend,
    value,
}: {
    children: ReactNode;
    description: string;
    tone: Tone;
    trend: string;
    value: string;
}) {
    return (
        <Surface className="rounded-[30px] p-5 lg:p-6">
            <div className={cn('flex h-12 w-12 items-center justify-center rounded-2xl', toneIconClasses(tone))}>
                <PulseDot />
            </div>
            <p className="mt-6 text-sm text-[var(--admin-text-soft)]">{children}</p>
            <p className="mt-2 text-4xl font-semibold tracking-tight text-[var(--admin-text)]">{value}</p>
            <p className="mt-2 text-sm text-[var(--admin-text-soft)]">{description}</p>
            <p className="mt-3 text-xs font-medium text-[var(--admin-muted)]">{trend}</p>
        </Surface>
    );
}

function SectionTitle({ description, title }: { description: string; title: string }) {
    return (
        <div>
            <h3 className="text-2xl font-semibold tracking-tight text-[var(--admin-text)]">{title}</h3>
            <p className="mt-1 text-sm leading-6 text-[var(--admin-text-soft)]">{description}</p>
        </div>
    );
}

function DataTable({ children, className = '' }: { children: ReactNode; className?: string }) {
    return (
        <div className={cn('overflow-x-auto', className)}>
            <table className="admin-table min-w-full">{children}</table>
        </div>
    );
}

function EmptyState({ description, title }: { description: string; title: string }) {
    return (
        <div className="rounded-[24px] border border-dashed border-[var(--admin-border)] bg-white/45 px-5 py-8 text-center">
            <p className="text-base font-semibold text-[var(--admin-text)]">{title}</p>
            <p className="mt-2 text-sm text-[var(--admin-text-soft)]">{description}</p>
        </div>
    );
}

function AvatarBubble({ label }: { label: string }) {
    return (
        <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#ebb95e] text-sm font-bold text-[#241b16]">
            {getInitials(label)}
        </span>
    );
}

function InfoRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-3">
            <span className="text-[var(--admin-muted)]">{label}</span>
            <span className="text-right font-medium text-[var(--admin-text)]">{value}</span>
        </div>
    );
}

function InfoPill({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-[22px] border border-[var(--admin-border)] bg-white/60 px-4 py-3">
            <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-[var(--admin-muted)]">{label}</p>
            <p className="mt-2 text-sm font-medium text-[var(--admin-text)]">{value}</p>
        </div>
    );
}

function RoleBadge({ role }: { role: string }) {
    const toneMap: Record<string, Tone> = {
        admin: 'amber',
        artisan: 'green',
        client: 'blue',
        fournisseur: 'slate',
        referent: 'rose',
    };

    return <span className={cn('rounded-full border px-3 py-1 text-xs font-semibold', toneBadgeClasses(toneMap[role] ?? 'slate'))}>{roleLabels[role] ?? role}</span>;
}

function KycStatusBadge({ status }: { status: string }) {
    const toneMap: Record<string, Tone> = {
        actif: 'green',
        en_attente: 'amber',
        rejete: 'rose',
    };

    return <span className={cn('rounded-full border px-3 py-1 text-xs font-semibold', toneBadgeClasses(toneMap[status] ?? 'slate'))}>{kycStatusLabels[status] ?? status}</span>;
}

function MissionStatusBadge({ status }: { status: string }) {
    const toneMap: Record<string, Tone> = {
        annulee: 'slate',
        en_attente: 'amber',
        en_cours: 'green',
        financee: 'blue',
        litige: 'rose',
        terminee: 'slate',
    };

    return (
        <span className={cn('rounded-full border px-3 py-1 text-xs font-semibold', toneBadgeClasses(toneMap[status] ?? 'slate'))}>
            {missionStatusLabels[status] ?? status}
        </span>
    );
}

function LitigeStatusBadge({ status }: { status: string }) {
    const toneMap: Record<string, Tone> = {
        en_cours: 'amber',
        ouvert: 'rose',
        resolu: 'green',
    };

    return <span className={cn('rounded-full border px-3 py-1 text-xs font-semibold', toneBadgeClasses(toneMap[status] ?? 'slate'))}>{status}</span>;
}

function DecisionBadge({ decision }: { decision: string }) {
    const toneMap: Record<string, Tone> = {
        artisan: 'green',
        client: 'rose',
        gel: 'amber',
    };

    return (
        <span className={cn('rounded-full border px-3 py-1 text-xs font-semibold', toneBadgeClasses(toneMap[decision] ?? 'slate'))}>
            {litigeDecisionLabels[decision] ?? decision}
        </span>
    );
}

function ProviderBadge({ provider }: { provider: string }) {
    const toneMap: Record<string, Tone> = {
        orange_money: 'amber',
        virement_bancaire: 'slate',
        wave: 'blue',
    };

    return (
        <span className={cn('rounded-full border px-3 py-1 text-xs font-semibold', toneBadgeClasses(toneMap[provider] ?? 'slate'))}>
            {providerLabels[provider] ?? provider}
        </span>
    );
}

function TransactionStatusBadge({ status }: { status: string }) {
    const toneMap: Record<string, Tone> = {
        confirme: 'green',
        echoue: 'rose',
        en_attente: 'blue',
    };

    return <span className={cn('rounded-full border px-3 py-1 text-xs font-semibold', toneBadgeClasses(toneMap[status] ?? 'slate'))}>{status}</span>;
}

function DualLineChart({ series }: { series: DualSeries[] }) {
    const points = series[0]?.points ?? [];

    if (series.length === 0 || points.length === 0) {
        return <EmptyState description="Pas assez de données pour tracer ce graphique." title="Graphique indisponible" />;
    }

    const width = 720;
    const height = 290;
    const paddingX = 36;
    const paddingTop = 28;
    const paddingBottom = 46;
    const maxValue = Math.max(...series.flatMap((entry) => entry.points.map((point) => point.value)), 1);
    const xStep = points.length > 1 ? (width - paddingX * 2) / (points.length - 1) : 0;
    const graphHeight = height - paddingTop - paddingBottom;
    const toY = (value: number): number => paddingTop + graphHeight - (value / maxValue) * graphHeight;

    return (
        <div className="mt-5 rounded-[28px] border border-[var(--admin-border)] bg-white/60 p-4">
            <svg viewBox={`0 0 ${width} ${height}`} className="h-64 w-full overflow-visible">
                {Array.from({ length: 5 }, (_, index) => {
                    const ratio = index / 4;
                    const y = paddingTop + graphHeight * ratio;

                    return (
                        <line
                            key={index}
                            x1={paddingX}
                            y1={y}
                            x2={width - paddingX}
                            y2={y}
                            stroke="rgba(194, 170, 136, 0.45)"
                            strokeDasharray="4 6"
                        />
                    );
                })}

                {series.map((entry) => {
                    const polyline = entry.points.map((point, index) => `${paddingX + index * xStep},${toY(point.value)}`).join(' ');

                    return <polyline key={entry.label} fill="none" points={polyline} stroke={entry.color} strokeWidth="3.5" strokeLinecap="round" strokeLinejoin="round" />;
                })}

                {points.map((point, index) => {
                    const x = paddingX + index * xStep;

                    return (
                        <text key={point.label} x={x} y={height - 14} textAnchor="middle" fontSize="11" fill="rgba(110, 91, 66, 0.78)">
                            {point.label}
                        </text>
                    );
                })}
            </svg>

            <div className="mt-3 flex flex-wrap gap-4">
                {series.map((entry) => (
                    <div key={entry.label} className="flex items-center gap-2 text-sm text-[var(--admin-text-soft)]">
                        <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: entry.color }} />
                        {entry.label}
                    </div>
                ))}
            </div>
        </div>
    );
}

function VolumeBarChart({ bars, color }: { bars: ChartPoint[]; color: string }) {
    const maxValue = Math.max(...bars.map((bar) => bar.value), 1);

    return (
        <div className="mt-5 rounded-[28px] border border-[var(--admin-border)] bg-white/60 p-4">
            <div className="flex h-64 items-end gap-2 overflow-hidden rounded-[20px] bg-[rgba(255,255,255,0.55)] p-4">
                {bars.map((bar) => {
                    const heightPercent = Math.max((bar.value / maxValue) * 100, bar.value > 0 ? 10 : 4);

                    return (
                        <div key={bar.label} className="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-2">
                            <div className="w-full rounded-t-[16px]" style={{ backgroundColor: color, height: `${heightPercent}%`, opacity: 0.88 }} />
                            <span className="text-[11px] text-[var(--admin-muted)]">{bar.label}</span>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

function BottomDock({ activeTab }: { activeTab: AdminTab }) {
    return (
        <div className="pointer-events-none fixed bottom-4 right-4 z-30 hidden lg:block">
            <div className="pointer-events-auto flex items-center gap-2 rounded-full border border-[var(--admin-border)] bg-white/80 p-2 shadow-[0_18px_38px_rgba(125,96,57,0.16)] backdrop-blur-xl">
                {quickDockTabs.map((tab) => (
                    <Link
                        key={tab}
                        href={tabRoutes[tab]}
                        className={cn(
                            'rounded-full px-4 py-2 text-sm font-medium transition',
                            activeTab === tab ? 'bg-[#f4e2bf] text-[#7d571b]' : 'text-[var(--admin-text-soft)] hover:bg-[#f7efe2]',
                        )}
                    >
                        {tabMeta[tab].label}
                    </Link>
                ))}
            </div>
        </div>
    );
}

function TabIcon({ className = 'h-5 w-5', tab }: { className?: string; tab: AdminTab }) {
    switch (tab) {
        case 'dashboard':
            return <DashboardIcon className={className} />;
        case 'kyc':
            return <ShieldIcon className={className} />;
        case 'missions':
            return <ClipboardIcon className={className} />;
        case 'litiges':
            return <AlertIcon className={className} />;
        case 'users':
            return <UsersIcon className={className} />;
        case 'transactions':
            return <WalletIcon className={className} />;
        case 'settings':
            return <SettingsIcon className={className} />;
        default:
            return <DashboardIcon className={className} />;
    }
}

function PulseDot() {
    return <span className="h-3 w-3 rounded-full bg-current" />;
}

function actionButtonClass(variant: 'danger' | 'secondary' | 'success'): string {
    const base = 'inline-flex items-center justify-center rounded-full px-4 py-2 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-50';

    const variants: Record<typeof variant, string> = {
        danger: 'bg-[#f15f57] text-white hover:bg-[#dd4d45]',
        secondary: 'bg-[#f0e5d3] text-[#6f531f] hover:bg-[#e4d4bb]',
        success: 'bg-[#2f9a65] text-white hover:bg-[#248052]',
    };

    return `${base} ${variants[variant]}`;
}

function SearchIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-3.5-3.5" strokeLinecap="round" />
        </svg>
    );
}

function RefreshIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="M21 12a9 9 0 1 1-2.64-6.36" strokeLinecap="round" strokeLinejoin="round" />
            <path d="M21 4v6h-6" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

function MoonIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="M20.4 15.1A8.5 8.5 0 1 1 8.9 3.6a7 7 0 1 0 11.5 11.5Z" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

function SunIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="4" />
            <path d="M12 2v2.2M12 19.8V22M4.2 4.2l1.6 1.6M18.2 18.2l1.6 1.6M2 12h2.2M19.8 12H22M4.2 19.8l1.6-1.6M18.2 5.8l1.6-1.6" strokeLinecap="round" />
        </svg>
    );
}

function BellIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="M15 17H5.8A1.8 1.8 0 0 1 4 15.2c0-.4.1-.7.3-1l1.2-2a5 5 0 0 0 .7-2.5V9a5.8 5.8 0 1 1 11.6 0v.7a5 5 0 0 0 .7 2.5l1.2 2c.2.3.3.6.3 1A1.8 1.8 0 0 1 18.2 17H15Z" strokeLinecap="round" strokeLinejoin="round" />
            <path d="M10 20a2.5 2.5 0 0 0 4 0" strokeLinecap="round" />
        </svg>
    );
}

function LogoutIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="M10 7V5.5A2.5 2.5 0 0 1 12.5 3h5A2.5 2.5 0 0 1 20 5.5v13a2.5 2.5 0 0 1-2.5 2.5h-5A2.5 2.5 0 0 1 10 18.5V17" strokeLinecap="round" strokeLinejoin="round" />
            <path d="M15 12H4" strokeLinecap="round" />
            <path d="m8 8-4 4 4 4" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

function DashboardIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <rect x="3.5" y="3.5" width="7" height="7" rx="1.6" />
            <rect x="13.5" y="3.5" width="7" height="7" rx="1.6" />
            <rect x="3.5" y="13.5" width="7" height="7" rx="1.6" />
            <rect x="13.5" y="13.5" width="7" height="7" rx="1.6" />
        </svg>
    );
}

function ShieldIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="M12 3s5 2 7 3v5c0 5-3.4 8-7 10-3.6-2-7-5-7-10V6c2-1 7-3 7-3Z" strokeLinecap="round" strokeLinejoin="round" />
            <path d="m9.5 12 1.6 1.8 3.4-3.8" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

function ClipboardIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <rect x="6" y="4.5" width="12" height="16" rx="2" />
            <path d="M9 4.5h6a1.5 1.5 0 0 1 1.5 1.5v0A1.5 1.5 0 0 1 15 7.5H9A1.5 1.5 0 0 1 7.5 6v0A1.5 1.5 0 0 1 9 4.5Z" />
            <path d="M9 12h6M9 16h4" strokeLinecap="round" />
        </svg>
    );
}

function AlertIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="M12 4 3.8 18.2A1.2 1.2 0 0 0 4.8 20h14.4a1.2 1.2 0 0 0 1-1.8L12 4Z" strokeLinecap="round" strokeLinejoin="round" />
            <path d="M12 9v4.5M12 17h.01" strokeLinecap="round" />
        </svg>
    );
}

function UsersIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="M16 19v-1.2A3.8 3.8 0 0 0 12.2 14H7.8A3.8 3.8 0 0 0 4 17.8V19" strokeLinecap="round" />
            <circle cx="10" cy="8" r="3" />
            <path d="M20 19v-1.2a3.5 3.5 0 0 0-2.5-3.4" strokeLinecap="round" />
            <path d="M16.5 5.3a3 3 0 0 1 0 5.4" strokeLinecap="round" />
        </svg>
    );
}

function WalletIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="M4 7.5A2.5 2.5 0 0 1 6.5 5H18a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6.5A2.5 2.5 0 0 1 4 16.5v-9Z" strokeLinecap="round" strokeLinejoin="round" />
            <path d="M4 8h12.5A1.5 1.5 0 0 0 18 6.5v0A1.5 1.5 0 0 0 16.5 5H6.5" strokeLinecap="round" />
            <path d="M16 13h4" strokeLinecap="round" />
        </svg>
    );
}

function SettingsIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="M12 15.5A3.5 3.5 0 1 0 12 8.5a3.5 3.5 0 0 0 0 7Z" />
            <path d="m19.4 15 .9 1.6-1.7 3-1.8-.3a7.9 7.9 0 0 1-1.7 1l-.5 1.8H9.4l-.5-1.8a7.9 7.9 0 0 1-1.7-1l-1.8.3-1.7-3 .9-1.6a8.6 8.6 0 0 1 0-2l-.9-1.6 1.7-3 1.8.3c.5-.4 1.1-.7 1.7-1l.5-1.8h4.2l.5 1.8c.6.3 1.2.6 1.7 1l1.8-.3 1.7 3-.9 1.6c.2.7.2 1.3 0 2Z" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}
