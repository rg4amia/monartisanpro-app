import { Head, Link, router, usePage, useForm } from '@inertiajs/react';
import { useDeferredValue, useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';
import LlmAdminPanel from './llm-admin-panel';
import RolesPermissionsPanel from './roles-permissions-panel';
import AiDashboardPanel from './ai-dashboard-panel';

type AdminTab = 'dashboard' | 'kyc' | 'missions' | 'litiges' | 'users' | 'transactions' | 'settings' | 'llm_admin' | 'roles_permissions' | 'evaluations' | 'ai_dashboard';
type ThemeMode = 'light' | 'dark';
type Tone = 'amber' | 'green' | 'rose' | 'blue' | 'slate';

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
    resolution_payload?: {
        invoice_path?: string;
        [key: string]: any;
    } | null;
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
    montant_materiaux?: number | null;
    montant_mo?: number | null;
    ratio_materiaux?: string | number | null;
    client_address?: string | null;
    created_at: string;
    client?: AdminMissionParty | null;
    artisan?: AdminMissionParty | null;
    jalons?: any[];
    jcodes?: any[];
    transactions?: any[];
    litiges?: any[];
    evaluations?: any[];
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
    email?: string | null;
    phone: string;
    role: string;
    kyc_status: string;
    score_nzassa: number;
    created_at: string;
    missions_client_count: number;
    missions_artisan_count: number;
    account_status?: string | null;
    account_status_reason?: string | null;
    score_frozen?: boolean;
    device_fingerprint?: string | null;
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
    reference_externe?: string | null;
    user?: {
        name: string;
        phone?: string;
    };
    mission?: {
        id: number;
        description: string;
    };
}

interface AdminEvaluation {
    id: number;
    mission_id: number;
    evaluateur_id: number;
    evalue_id: number;
    note: number;
    fiabilite: number;
    integrite: number;
    qualite: number;
    reactivite: number;
    commentaire?: string | null;
    created_at: string;
    mission?: {
        id: number;
        description: string;
    } | null;
    evaluateur?: {
        id: number;
        name: string;
        phone: string;
    } | null;
    evalue?: {
        id: number;
        name: string;
        phone: string;
        score_nzassa: number;
        score_frozen: boolean;
    } | null;
}

interface ArtisanScoreItem {
    id: number;
    name: string;
    phone: string;
    score_nzassa: number;
    score_frozen: boolean;
    evaluations_recues_count: number;
    evaluations_recues_avg_fiabilite?: number | string | null;
    evaluations_recues_avg_integrite?: number | string | null;
    evaluations_recues_avg_qualite?: number | string | null;
    evaluations_recues_avg_reactivite?: number | string | null;
}

interface ScoreLedgerEntryItem {
    id: number;
    user_id: number;
    event_type: string;
    points: number;
    credibility_factor: number;
    description: string;
    created_at: string;
    user?: {
        name: string;
        phone: string;
    } | null;
    mission?: {
        id: number;
        description: string;
    } | null;
}

interface AuthUser {
    email?: string | null;
    name: string;
    phone?: string | null;
    role?: string | null;
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

interface FlashMessages {
    error?: string | null;
    success?: string | null;
}

interface AdminPageProps {
    [key: string]: unknown;
    auth: {
        user?: AuthUser | null;
    };
    dashboard: DashboardData;
    errors: Record<string, string>;
    flash?: FlashMessages;
    fournisseurs: FournisseurItem[];
    kycUsers: KycUser[];
    litiges: LitigeItem[];
    missions: AdminMission[];
    transactions: AdminTransaction[];
    users: AdminUser[];
    evaluationsList: AdminEvaluation[];
    artisansScores: ArtisanScoreItem[];
    scoreLedger: ScoreLedgerEntryItem[];
    settingsList?: Array<{
        id: number;
        key: string;
        value: string;
        type: string;
        group: string;
        label: string;
        description: string;
    }>;
    sectors?: Array<{
        id: number;
        name: string;
        trades?: Array<{ id: number; sector_id: number; name: string }>;
    }>;
    rolesPermissions?: Record<string, string[]>;
    allPermissions?: Array<{ id: number; name: string; description: string; category: string }>;
}

const tabRoutes: Record<AdminTab, string> = {
    dashboard: '/admin/dashboard',
    kyc: '/admin/kyc',
    missions: '/admin/missions',
    litiges: '/admin/litiges',
    users: '/admin/users',
    transactions: '/admin/transactions',
    settings: '/admin/settings',
    llm_admin: '/admin/llm-admin',
    ai_dashboard: '/admin/ai-dashboard',
    roles_permissions: '/admin/roles-permissions',
    evaluations: '/admin/evaluations',
};

const tabMeta: Record<AdminTab, { description: string; label: string; section: string }> = {
    dashboard: {
        label: "Vue d'ensemble",
        section: 'PILOTAGE',
        description: 'Lecture rapide de la santé opérationnelle, financière et terrain de ProsArtisan.',
    },
    kyc: {
        label: 'KYC & Vérifications',
        section: 'VALIDATIONS',
        description: "Traitez les dossiers sensibles avant qu'ils ne bloquent les missions et paiements.",
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
        description: "Règles métier, configuration d'accès et garde-fous du backoffice.",
    },
    roles_permissions: {
        label: 'Rôles & Actions',
        section: 'PLATEFORME',
        description: 'Attribuez et révoquez dynamiquement les actions autorisées pour chaque rôle.',
    },
    llm_admin: {
        label: 'Administration LLM ProsArtisan',
        section: 'INTELLIGENCE',
        description: "Supervision de l'ingestion sémantique des documents et du pipeline RAG local.",
    },
    ai_dashboard: {
        label: 'Suivi & Coûts IA',
        section: 'INTELLIGENCE',
        description: 'Visualisation de l\'utilisation de l\'IA, des jetons consommés et contrôle des limites de coûts.',
    },
    evaluations: {
        label: 'Évaluations & Scores',
        section: 'QUALITÉ',
        description: 'Suivi de la réputation des artisans, calcul du score N\'Zassa et historiques des évaluations.',
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
    roles_permissions: 'Rechercher une action ou un rôle...',
    llm_admin: 'Rechercher une règle ou un document...',
    ai_dashboard: 'Rechercher un log ou un modèle...',
    evaluations: 'Rechercher une évaluation, un artisan ou un commentaire...',
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

function getInitials(value: string | null | undefined): string {
    if (!value) return 'PA';
    const initials = value
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');

    return initials || 'PA';
}

function buildTimeline(days: number, now: number): TimelinePoint[] {
    const today = new Date(now);

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
    const { props } = usePage<AdminPageProps>();
    const { auth, dashboard, errors, flash, fournisseurs, kycUsers, litiges, missions, transactions, users, settingsList, evaluationsList, artisansScores, scoreLedger } = props;

    const [themeMode, setThemeMode] = useState<ThemeMode>(() => {
        if (typeof window !== 'undefined' && window.localStorage) {
            return (localStorage.getItem('prosartisan_admin_theme') as ThemeMode) || 'light';
        }
        return 'light';
    });
    const [search, setSearch] = useState<string>('');
    const [actionLoading, setActionLoading] = useState<boolean>(false);
    const [refreshing, setRefreshing] = useState<boolean>(false);
    const deferredSearch = useDeferredValue(search.trim().toLowerCase());
    const [now] = useState(() => Date.now());
    const [isMobileSidebarOpen, setIsMobileSidebarOpen] = useState<boolean>(false);
    const [evalSubTab, setEvalSubTab] = useState<'list' | 'artisans'>('list');
    const [selectedArtisanForLedger, setSelectedArtisanForLedger] = useState<ArtisanScoreItem | null>(null);

    const [exchangeRates, setExchangeRates] = useState<{ usdToXof: number; eurToXof: number; eurToUsd: number } | null>(null);
    const [isOnline, setIsOnline] = useState(() => typeof navigator !== 'undefined' ? navigator.onLine : true);
    const [isOfflineSimulated, setIsOfflineSimulated] = useState(false);

    useEffect(() => {
        const handleOnline = () => setIsOnline(true);
        const handleOffline = () => setIsOnline(false);

        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);

        return () => {
            window.removeEventListener('online', handleOnline);
            window.removeEventListener('offline', handleOffline);
        };
    }, []);

    const offlineActive = !isOnline || isOfflineSimulated;

    useEffect(() => {
        fetch('https://open.er-api.com/v6/latest/EUR')
            .then(res => res.json())
            .then(data => {
                if (data && data.rates) {
                    const eurToXof = data.rates.XOF || 655.957;
                    const eurToUsd = data.rates.USD || 1.09;
                    const usdToXof = eurToXof / eurToUsd;
                    setExchangeRates({
                        usdToXof: Math.round(usdToXof * 100) / 100,
                        eurToXof: Math.round(eurToXof * 100) / 100,
                        eurToUsd: Math.round(eurToUsd * 10000) / 10000
                    });
                }
            })
            .catch(() => {
                setExchangeRates({
                    usdToXof: 605.5,
                    eurToXof: 655.96,
                    eurToUsd: 1.085
                });
            });
    }, []);

    const [userModalOpen, setUserModalOpen] = useState<boolean>(false);
    const [editingUser, setEditingUser] = useState<AdminUser | null>(null);
    const [statusModalOpen, setStatusModalOpen] = useState<boolean>(false);
    const [statusTargetUser, setStatusTargetUser] = useState<AdminUser | null>(null);
    const [selectedMissionForDetails, setSelectedMissionForDetails] = useState<AdminMission | null>(null);
    const [selectedTransactionForDetails, setSelectedTransactionForDetails] = useState<AdminTransaction | null>(null);

    const userForm = useForm({
        name: '',
        phone: '',
        email: '',
        role: 'client',
        password: '',
        kyc_status: 'en_attente',
        account_status: 'actif',
        score_frozen: false,
        device_fingerprint: '',
    });

    const statusForm = useForm({
        account_status: 'actif',
        account_status_reason: '',
    });

    const openCreateUserModal = (): void => {
        setEditingUser(null);
        userForm.reset();
        userForm.clearErrors();
        userForm.setData({
            name: '',
            phone: '',
            email: '',
            role: 'client',
            password: '',
            kyc_status: 'en_attente',
            account_status: 'actif',
            score_frozen: false,
            device_fingerprint: '',
        });
        setUserModalOpen(true);
    };

    const openEditUserModal = (user: AdminUser): void => {
        setEditingUser(user);
        userForm.clearErrors();
        userForm.setData({
            name: user.name,
            phone: user.phone,
            email: user.email ?? '',
            role: user.role as any,
            password: '',
            kyc_status: user.kyc_status as any,
            account_status: (user.account_status ?? 'actif') as any,
            score_frozen: Boolean(user.score_frozen),
            device_fingerprint: user.device_fingerprint ?? '',
        });
        setUserModalOpen(true);
    };

    const handleUserFormSubmit = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        if (editingUser) {
            userForm.put(`/admin/users/${editingUser.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    setUserModalOpen(false);
                    userForm.reset();
                },
            });
        } else {
            userForm.post('/admin/users', {
                preserveScroll: true,
                onSuccess: () => {
                    setUserModalOpen(false);
                    userForm.reset();
                },
            });
        }
    };

    const handleToggleUserStatus = (user: AdminUser): void => {
        if (auth.user?.phone === user.phone || auth.user?.email === user.email) {
            window.alert('Vous ne pouvez pas modifier votre propre statut.');
            return;
        }

        const isActif = (user.account_status ?? 'actif') === 'actif';
        if (isActif) {
            setStatusTargetUser(user);
            statusForm.reset();
            statusForm.clearErrors();
            statusForm.setData({
                account_status: 'suspendu',
                account_status_reason: '',
            });
            setStatusModalOpen(true);
        } else {
            if (window.confirm(`Voulez-vous réactiver le compte de ${user.name} ?`)) {
                router.post(`/admin/users/${user.id}/toggle-status`, {
                    account_status: 'actif',
                    account_status_reason: '',
                }, {
                    preserveScroll: true,
                });
            }
        }
    };

    const handleStatusSubmit = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        if (!statusTargetUser) return;

        statusForm.post(`/admin/users/${statusTargetUser.id}/toggle-status`, {
            preserveScroll: true,
            onSuccess: () => {
                setStatusModalOpen(false);
                statusForm.reset();
            },
        });
    };

    const handleDeleteUser = (user: AdminUser): void => {
        if (auth.user?.phone === user.phone || auth.user?.email === user.email) {
            window.alert('Vous ne pouvez pas supprimer votre propre compte.');
            return;
        }

        if (window.confirm(`Êtes-vous sûr de vouloir supprimer définitivement l'utilisateur ${user.name} ? Cette action est irréversible.`)) {
            router.delete(`/admin/users/${user.id}`, {
                preserveScroll: true,
            });
        }
    };

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        localStorage.setItem('prosartisan_admin_theme', themeMode);
    }, [themeMode]);

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
                : normalizeSearch([user.id, user.name, user.email, user.phone, user.role, user.kyc_status, user.score_nzassa]).includes(
                    deferredSearch,
                ),
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
        const todayTimeline = buildTimeline(7, now);
        const activityTimeline = buildTimeline(15, now);
        const monthlyUserCount = users.filter((user) => {
            const createdAt = new Date(user.created_at);
            const today = new Date(now);

            return createdAt.getMonth() === today.getMonth() && createdAt.getFullYear() === today.getFullYear();
        }).length;
        const weeklyUserCount = users.filter((user) => new Date(user.created_at) >= new Date(now - 7 * 24 * 60 * 60 * 1000)).length;
        const highRiskDisputes = filteredLitiges.filter((litige) => (litige.mission.montant_total ?? 0) >= 2_000_000);
        const topArtisans = [...users]
            .filter((user) => user.role === 'artisan')
            .sort((left, right) => right.score_nzassa - left.score_nzassa)
            .slice(0, 5);
        const urgentKyc = [...filteredKyc]
            .sort((left, right) => new Date(left.created_at).getTime() - new Date(right.created_at).getTime())
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
                value: numberFormat.format(dashboard.missions_en_cours ?? filteredMissions.filter((mission) => mission.status === 'en_cours').length),
            },
            {
                description: 'Escalade Référent nécessaire',
                title: 'Seuil > 2M FCFA',
                tone: 'amber',
                value: numberFormat.format(dashboard.referent_required_open ?? 0),
            },
            {
                description: 'Missions avec arbitrage',
                title: 'Missions en litige',
                tone: 'rose',
                value: numberFormat.format(dashboard.missions_en_litige ?? filteredMissions.filter((mission) => mission.status === 'litige').length),
            },
            {
                description: 'Analyses Gemini disponibles',
                title: 'Missions enrichies',
                tone: 'blue',
                value: numberFormat.format(filteredMissions.filter((mission) => Boolean(mission.gemini_category)).length),
            },
        ];

        const filteredEvaluations = (evaluationsList || []).filter((evaluation) =>
            deferredSearch === ''
                ? true
                : normalizeSearch([
                    evaluation.id,
                    evaluation.note,
                    evaluation.commentaire,
                    evaluation.evaluateur?.name,
                    evaluation.evalue?.name,
                    evaluation.mission?.description,
                ]).includes(deferredSearch),
        );

        const filteredArtisansScores = (artisansScores || []).filter((artisan) =>
            deferredSearch === ''
                ? true
                : normalizeSearch([
                    artisan.id,
                    artisan.name,
                    artisan.phone,
                    artisan.score_nzassa,
                ]).includes(deferredSearch),
        );

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
            filteredEvaluations,
            filteredArtisansScores,
            highRiskDisputes,
            missionStatusMetrics,
            monthlyUserCount,
            pendingTransactions,
            recentActivity,
            registrationTrend,
            releaseTrend,
            releasedAmount: sumAmount(releasedTransactions),
            topArtisans,
            urgentKyc,
            weeklyUserCount,
        };
    }, [dashboard, deferredSearch, fournisseurs, kycUsers, litiges, missions, transactions, users, evaluationsList, artisansScores, now]);

    const totalUsers = dashboard.users_total ?? users.length;
    const artisansActifs = useMemo(() => dashboard.artisans_actifs ?? users.filter((user) => user.role === 'artisan' && user.kyc_status === 'actif').length, [dashboard.artisans_actifs, users]);
    const clientsActifs = useMemo(() => dashboard.clients_actifs ?? users.filter((user) => user.role === 'client' && user.kyc_status === 'actif').length, [dashboard.clients_actifs, users]);
    const fournisseursAgrees = dashboard.fournisseurs_agrees ?? 0; // Already a primitive or stable
    const kycPending = useMemo(() => dashboard.kyc_en_attente ?? kycUsers.length, [dashboard.kyc_en_attente, kycUsers]);
    const openDisputes = useMemo(() => dashboard.litiges_ouverts ?? litiges.filter((litige) => litige.statut !== 'resolu').length, [dashboard.litiges_ouverts, litiges]);
    const missionsInProgress = useMemo(() => dashboard.missions_en_cours ?? missions.filter((mission) => mission.status === 'en_cours').length, [dashboard.missions_en_cours, missions]);
    const referentRequired = dashboard.referent_required_open ?? 0; // Already a primitive or stable
    const fraudAlerts = dashboard.recent_fraud_alerts ?? 0; // Already a primitive or stable
    const volume24h = dashboard.volume_transactions_24h ?? 0; // Already a primitive or stable

    const firstError = Object.values(errors ?? {})[0];
    const bannerError = flash?.error ?? firstError;

    const navigation: NavigationGroup[] = [
        {
            label: 'Pilotage',
            items: [
                { id: 'dashboard', label: tabMeta.dashboard.label },
                { count: kycPending, id: 'kyc', label: tabMeta.kyc.label },
                { count: missionsInProgress, id: 'missions', label: tabMeta.missions.label },
                { count: openDisputes, id: 'litiges', label: tabMeta.litiges.label },
                { id: 'llm_admin', label: 'Administration LLM' },
                { id: 'ai_dashboard', label: 'Suivi & Coûts IA' },
            ],
        },
        {
            label: 'Réseau',
            items: [
                { count: totalUsers, id: 'users', label: tabMeta.users.label },
                { id: 'evaluations', label: tabMeta.evaluations.label },
                { count: analytics.pendingTransactions.length, id: 'transactions', label: tabMeta.transactions.label },
            ],
        },
        {
            label: 'Plateforme',
            items: [
                { id: 'settings', label: tabMeta.settings.label },
                { id: 'roles_permissions', label: tabMeta.roles_permissions.label },
            ],
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
                    { label: 'Fournisseurs à valider', tone: 'blue' as const, value: numberFormat.format(fournisseurs.length) },
                    {
                        label: 'Rejets récents',
                        tone: 'rose' as const,
                        value: numberFormat.format(users.filter((user) => user.kyc_status === 'rejete').length),
                    },
                ];
            case 'missions':
                return [
                    { label: 'Missions en cours', tone: 'green' as const, value: numberFormat.format(missionsInProgress) },
                    { label: 'En litige', tone: 'rose' as const, value: numberFormat.format(dashboard.missions_en_litige ?? 0) },
                    { label: 'Référent requis', tone: 'amber' as const, value: numberFormat.format(referentRequired) },
                    {
                        label: 'Montant piloté',
                        tone: 'slate' as const,
                        value: money(analytics.filteredMissions.reduce((sum, mission) => sum + (mission.montant_total ?? 0), 0)),
                    },
                ];
            case 'litiges':
                return [
                    { label: 'Litiges ouverts', tone: 'rose' as const, value: numberFormat.format(openDisputes) },
                    { label: 'Haute priorité', tone: 'amber' as const, value: numberFormat.format(analytics.highRiskDisputes.length) },
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
                    { label: 'En attente', tone: 'blue' as const, value: numberFormat.format(analytics.pendingTransactions.length) },
                    { label: 'Échouées', tone: 'rose' as const, value: numberFormat.format(analytics.failedTransactions.length) },
                    { label: 'Fonds libérés', tone: 'green' as const, value: money(analytics.releasedAmount) },
                ];
            case 'settings':
                return [
                    { label: 'Pays', tone: 'amber' as const, value: "Côte d'Ivoire" },
                    { label: 'Devise', tone: 'green' as const, value: 'FCFA' },
                    { label: 'Paiements', tone: 'blue' as const, value: 'Wave / Orange' },
                    { label: 'Mode mobile', tone: 'slate' as const, value: 'Hors-ligne' },
                ];
            case 'roles_permissions':
                return [
                    { label: 'Rôles gérés', tone: 'amber' as const, value: '5 Rôles' },
                    { label: 'Actions système', tone: 'green' as const, value: `${props.allPermissions?.length ?? 0} Actions` },
                    { label: 'Sécurité d\'accès', tone: 'blue' as const, value: 'RBAC Actif' },
                    { label: 'Mode', tone: 'slate' as const, value: 'Cache Actif' },
                ];
            case 'evaluations':
                return [
                    { label: 'Évaluations', tone: 'amber' as const, value: numberFormat.format(evaluationsList?.length ?? 0) },
                    {
                        label: 'Note moyenne',
                        tone: 'green' as const,
                        value: evaluationsList && evaluationsList.length > 0
                            ? (evaluationsList.reduce((sum, e) => sum + e.note, 0) / evaluationsList.length).toFixed(1) + ' / 5'
                            : 'N/A',
                    },
                    { label: 'Artisans suivis', tone: 'blue' as const, value: numberFormat.format(artisansScores?.length ?? 0) },
                    {
                        label: 'Scores gelés',
                        tone: 'rose' as const,
                        value: numberFormat.format(artisansScores?.filter((a) => a.score_frozen).length ?? 0),
                    },
                ];
            default:
                return [];
        }
    }, [
        activeTab,
        analytics.failedTransactions.length,
        analytics.filteredMissions,
        analytics.highRiskDisputes.length,
        analytics.pendingTransactions.length,
        analytics.releasedAmount,
        artisansActifs,
        clientsActifs,
        dashboard.missions_en_litige,
        fournisseurs.length,
        fournisseursAgrees,
        fraudAlerts,
        kycPending,
        kycUsers,
        missionsInProgress,
        openDisputes,
        referentRequired,
        totalUsers,
        users,
        volume24h,
        evaluationsList,
        artisansScores,
        props.allPermissions?.length,
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

    const submitAction = (url: string, payload: Record<string, string>) => {
        setActionLoading(true);

        router.post(url, payload, {
            preserveScroll: true,
            onFinish: () => setActionLoading(false),
        });
    };

    const refreshData = (): void => {
        setRefreshing(true);

        router.visit(tabRoutes[activeTab], {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setRefreshing(false),
        });
    };

    const handleKycDecision = (user: KycUser, decision: 'approuve' | 'rejete'): void => {
        let rejectionReason = '';

        if (decision === 'rejete') {
            rejectionReason = window.prompt('Motif de rejet KYC (minimum 10 caractères) :') ?? '';

            if (rejectionReason.trim().length < 10) {
                window.alert('Motif trop court.');
                return;
            }
        }

        submitAction(`/admin/kyc/${user.id}/review`, {
            decision,
            rejection_reason: rejectionReason,
        });
    };

    const handleLitigeDecision = (litige: LitigeItem, decision: 'client' | 'artisan' | 'gel'): void => {
        submitAction(`/admin/litiges/${litige.id}/resolve`, { decision });
    };

    const handleFournisseurDecision = (fournisseur: FournisseurItem, decision: 'agree' | 'suspendu'): void => {
        submitAction(`/admin/fournisseurs/${fournisseur.id}/review`, { decision });
    };

    const handleToggleScoreFreeze = (artisan: ArtisanScoreItem): void => {
        const action = artisan.score_frozen ? 'dégeler' : 'geler';
        if (window.confirm(`Voulez-vous vraiment ${action} le score de l'artisan ${artisan.name} ?`)) {
            setActionLoading(true);
            router.post(`/admin/users/${artisan.id}/toggle-score-freeze`, {}, {
                preserveScroll: true,
                onFinish: () => setActionLoading(false),
            });
        }
    };

    const adminName = auth.user?.name ?? 'Admin ProsArtisan';
    const adminContact = auth.user?.email ?? auth.user?.phone ?? 'Administrateur';

    return (
        <>
            <Head title="ProsArtisan Backoffice" />

            <div className={cn('admin-shell min-h-screen', themeMode === 'dark' && 'admin-shell--dark')}>
                <div className="relative z-10 flex min-h-screen">
                    {/* Sidebar Mobile Overlay Backdrop */}
                    {isMobileSidebarOpen && (
                        <div 
                            className="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm lg:hidden"
                            onClick={() => setIsMobileSidebarOpen(false)}
                        />
                    )}

                    {/* Sidebar */}
                    <aside className={cn(
                        "admin-panel shrink-0 border-r px-5 py-6 bg-[var(--admin-bg)] transition-all duration-300",
                        // Classes de positionnement mobile
                        "fixed inset-y-0 left-0 z-50 w-[310px] flex flex-col lg:static lg:h-auto lg:translate-x-0",
                        isMobileSidebarOpen ? "translate-x-0" : "-translate-x-full lg:translate-x-0",
                        // Classes de positionnement desktop
                        "lg:flex lg:flex-col"
                    )}>
                        <div className="flex items-center justify-between gap-3">
                            <div className="flex items-center gap-3">
                                <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#ebb95e] text-[#241b16] shadow-[0_16px_35px_rgba(210,152,52,0.24)]">
                                    <img src="/img/prosartisan-logo.png" alt="ProsArtisan" className="h-8 w-8 object-contain" />
                                </div>
                                <div className="min-w-0">
                                    <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-[var(--admin-muted)]">ProsArtisan</p>
                                    <div className="flex items-center gap-2">
                                        <h1 className="truncate text-xl font-semibold text-[var(--admin-text)]">Backoffice</h1>
                                        <span className={cn('rounded-full border px-2 py-0.5 text-[11px] font-semibold', toneBadgeClasses('amber'))}>ADMIN</span>
                                    </div>
                                </div>
                            </div>
                            
                            {/* Bouton fermeture sur mobile */}
                            <button 
                                type="button" 
                                className="flex h-10 w-10 items-center justify-center rounded-xl border border-[var(--admin-border)] bg-white/50 text-[var(--admin-text)] lg:hidden hover:bg-white/80 transition"
                                onClick={() => setIsMobileSidebarOpen(false)}
                                aria-label="Fermer le menu"
                            >
                                <CloseIcon className="h-5 w-5" />
                            </button>
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
                                <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-[var(--admin-muted)]">Contexte marché</p>
                                <div className="mt-3 space-y-3 text-sm text-[var(--admin-text-soft)]">
                                    <InfoRow label="Pays" value="Côte d’Ivoire" />
                                    <InfoRow label="Devise" value="FCFA" />
                                    <InfoRow label="Paiements" value="Wave CI, Orange Money CI" />
                                    <InfoRow label="Connectivité" value="Mode hors-ligne + USSD" />
                                </div>
                                <div className="border-t border-[var(--admin-border)] pt-3 mt-3">
                                    <p className="text-[10px] font-semibold uppercase tracking-wider text-[var(--admin-muted)] mb-2">Cours des devises</p>
                                    <div className="grid grid-cols-1 gap-2 text-xs">
                                        <div className="flex justify-between items-center bg-white/45 rounded-xl px-2.5 py-1.5 border border-[var(--admin-border)]">
                                            <span className="font-medium text-[var(--admin-text)]">1 EUR</span>
                                            <span className="font-bold text-[#8a6b3d]">{exchangeRates ? `${exchangeRates.eurToXof} XOF` : '655.96 XOF'}</span>
                                        </div>
                                        <div className="flex justify-between items-center bg-white/45 rounded-xl px-2.5 py-1.5 border border-[var(--admin-border)]">
                                            <span className="font-medium text-[var(--admin-text)]">1 USD</span>
                                            <span className="font-bold text-[#8a6b3d]">{exchangeRates ? `${exchangeRates.usdToXof} XOF` : '605.50 XOF'}</span>
                                        </div>
                                        <div className="flex justify-between items-center bg-white/45 rounded-xl px-2.5 py-1.5 border border-[var(--admin-border)]">
                                            <span className="font-medium text-[var(--admin-text)]">1 EUR</span>
                                            <span className="font-bold text-[#8a6b3d]">{exchangeRates ? `${exchangeRates.eurToUsd} USD` : '1.0850 USD'}</span>
                                        </div>
                                    </div>
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
                        {offlineActive && (
                            <div className="bg-amber-500 text-[#241b16] px-4 py-2 text-center text-xs font-semibold flex items-center justify-center gap-2 shadow-inner border-b border-amber-600 animate-pulse">
                                <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                    <path d="M18.36 5.64a9 9 0 0 1 0 12.73m-2.82-9.9a6 6 0 0 1 0 7.07m-2.83-4.24a3 3 0 0 1 0 1.41m.01-1.42v.01" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                                <span>Mode hors-ligne actif • ProsArtisan bascule automatiquement sur les files d'attente locales et les interactions USSD.</span>
                            </div>
                        )}
                        <header className="admin-panel sticky top-0 z-20 border-b px-4 py-4 lg:px-7">
                            <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                                <div className="flex min-w-0 flex-1 items-center gap-3">
                                    {/* Bouton Hamburger sur mobile */}
                                    <button
                                        type="button"
                                        className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-[var(--admin-border)] bg-white/50 text-[var(--admin-text)] lg:hidden hover:bg-white/80 transition"
                                        onClick={() => setIsMobileSidebarOpen(true)}
                                        aria-label="Ouvrir le menu"
                                    >
                                        <MenuIcon className="h-6 w-6" />
                                    </button>

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
                                            Session web active
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

                                    <button
                                        type="button"
                                        className="relative rounded-2xl border border-transparent p-3 text-[var(--admin-muted)] transition hover:bg-white/55"
                                        title="Notifications"
                                        aria-label="Notifications"
                                    >
                                        <BellIcon className="h-5 w-5" />
                                        <span className="absolute right-2 top-2 h-2.5 w-2.5 rounded-full bg-[#f15f57]" />
                                    </button>

                                    <div className="hidden items-center gap-3 rounded-3xl bg-white/50 px-3 py-2 sm:flex">
                                        <div className="flex h-11 w-11 items-center justify-center rounded-full bg-[#ebb95e] text-sm font-bold text-[#241b16]">
                                            {getInitials(adminName)}
                                        </div>
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-semibold text-[var(--admin-text)]">{adminName}</p>
                                            <p className="truncate text-xs text-[var(--admin-muted)]">{adminContact}</p>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        className="rounded-2xl p-3 text-[var(--admin-muted)] transition hover:bg-white/55"
                                        onClick={() => router.post('/admin/logout')}
                                        title="Se déconnecter"
                                        aria-label="Se déconnecter"
                                    >
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
                                                        <ToneIcon tone={stat.tone} className="h-4 w-4" />
                                                    </span>
                                                    <p className="text-lg font-semibold text-[var(--admin-text)]">{stat.value}</p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </Surface>

                            {flash?.success ? (
                                <div className="mt-4 rounded-[24px] border border-[#c5dfca] bg-[#eef8f0] px-4 py-3 text-sm text-[#24734f]">
                                    {flash.success}
                                </div>
                            ) : null}

                            {bannerError ? (
                                <div className="mt-4 rounded-[24px] border border-[#efc1b9] bg-[#fff3ef] px-4 py-3 text-sm text-[#b24f43]">
                                    {bannerError}
                                </div>
                            ) : null}

                            {refreshing || actionLoading ? (
                                <div className="mt-4 rounded-[24px] border border-[#e2d5c2] bg-white/70 px-4 py-3 text-sm text-[var(--admin-text-soft)]">
                                    Mise à jour du backoffice en cours...
                                </div>
                            ) : null}

                            {activeTab === 'dashboard' ? (
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
                                                    <EmptyState description="Aucun dossier urgent en attente de revue." title="File KYC vide" />
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
                                                                <ActivityToneIcon tone={activity.tone} />
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
                                            <p className="mt-2 text-sm text-[var(--admin-text-soft)]">Acomptes clients confirmés et immobilisés pour les missions.</p>
                                        </Surface>

                                        <Surface className="rounded-[32px] p-5 lg:p-6">
                                            <SectionTitle description="Montants déjà distribués selon OTP et règlements fournisseurs." title="Fonds libérés" />
                                            <p className="mt-5 text-4xl font-semibold text-[var(--admin-text)]">{money(analytics.releasedAmount)}</p>
                                            <p className="mt-2 text-sm text-[var(--admin-text-soft)]">Jalons validés et règlements matériaux exécutés.</p>
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

                            {activeTab === 'kyc' ? (
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
                                                                <EmptyState description="Aucun dossier ne correspond à votre recherche." title="Rien à afficher" />
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
                                                                            onClick={() => handleKycDecision(user, 'approuve')}
                                                                            className={actionButtonClass('success')}
                                                                        >
                                                                            Approuver
                                                                        </button>
                                                                        <button
                                                                            type="button"
                                                                            disabled={actionLoading}
                                                                            onClick={() => handleKycDecision(user, 'rejete')}
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
                                                                <p className="mt-1 text-sm text-[var(--admin-text-soft)]">{fournisseur.user?.name ?? 'Contact inconnu'}</p>
                                                                <p className="text-xs text-[var(--admin-muted)]">
                                                                    {fournisseur.user?.phone ?? 'Téléphone non renseigné'} • {shortDate(fournisseur.created_at)}
                                                                </p>
                                                                <div className="mt-4 flex gap-2">
                                                                    <button
                                                                        type="button"
                                                                        disabled={actionLoading}
                                                                        onClick={() => handleFournisseurDecision(fournisseur, 'agree')}
                                                                        className={actionButtonClass('success')}
                                                                    >
                                                                        Agréer
                                                                    </button>
                                                                    <button
                                                                        type="button"
                                                                        disabled={actionLoading}
                                                                        onClick={() => handleFournisseurDecision(fournisseur, 'suspendu')}
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

                            {activeTab === 'missions' ? (
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
                                                        <tr 
                                                            key={mission.id}
                                                            onClick={() => setSelectedMissionForDetails(mission)}
                                                            className="cursor-pointer hover:bg-black/[0.02] transition"
                                                        >
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

                            {activeTab === 'litiges' ? (
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
                                        <MetricCard description="Missions au statut litige" tone="blue" trend="Impact opérationnel" value={numberFormat.format(dashboard.missions_en_litige ?? 0)}>
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
                                                                onClick={() => handleLitigeDecision(litige, 'client')}
                                                                className={actionButtonClass('danger')}
                                                            >
                                                                Rembourser client
                                                            </button>
                                                            <button
                                                                type="button"
                                                                disabled={actionLoading}
                                                                onClick={() => handleLitigeDecision(litige, 'artisan')}
                                                                className={actionButtonClass('success')}
                                                            >
                                                                Payer artisan
                                                            </button>
                                                            <button
                                                                type="button"
                                                                disabled={actionLoading}
                                                                onClick={() => handleLitigeDecision(litige, 'gel')}
                                                                className={actionButtonClass('secondary')}
                                                            >
                                                                Geler et envoyer Référent
                                                            </button>
                                                        </div>
                                                    ) : (
                                                        <div className="mt-5 space-y-2">
                                                            <p className="text-sm text-[var(--admin-text-soft)]">
                                                                Décision finale: {litige.decision ? litigeDecisionLabels[litige.decision] : 'Aucune'}
                                                            </p>
                                                            {litige.decision === 'artisan' && litige.resolution_payload?.invoice_path ? (
                                                                <div className="mt-2">
                                                                    <a
                                                                        href={`/admin/litiges/${litige.id}/invoice`}
                                                                        target="_blank"
                                                                        rel="noreferrer"
                                                                        className="inline-flex items-center gap-2 text-sm text-[#10B981] hover:underline font-semibold"
                                                                    >
                                                                        Télécharger la Facture de Décaissement
                                                                    </a>
                                                                </div>
                                                            ) : null}
                                                        </div>
                                                    )}
                                                </Surface>
                                            ))
                                        )}
                                    </div>
                                </section>
                            ) : null}

                            {activeTab === 'users' ? (
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
                                            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                                <SectionTitle
                                                    description="Vue consolidée des comptes, rôles, KYC et activité mission."
                                                    title="Répertoire utilisateurs"
                                                />
                                                <button
                                                    type="button"
                                                    onClick={openCreateUserModal}
                                                    className="admin-button admin-button--primary self-start sm:self-auto"
                                                >
                                                    Ajouter un utilisateur
                                                </button>
                                            </div>

                                            <DataTable className="mt-5">
                                                <thead>
                                                    <tr>
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
                                                    {analytics.filteredUsers.length === 0 ? (
                                                        <tr>
                                                            <td colSpan={7}>
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
                                                                    <AccountStatusBadge status={user.account_status} />
                                                                </td>
                                                                <td className="text-sm">
                                                                    <div className="flex flex-col gap-1">
                                                                        <div className="flex items-center gap-1.5">
                                                                            <span className="font-semibold text-[var(--admin-text)]">
                                                                                {user.score_nzassa} pts
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
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => openEditUserModal(user)}
                                                                            className={actionButtonClass('secondary')}
                                                                            title="Modifier"
                                                                        >
                                                                            Modifier
                                                                        </button>
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => handleToggleUserStatus(user)}
                                                                            className={actionButtonClass((user.account_status ?? 'actif') === 'actif' ? 'danger' : 'success')}
                                                                            title={(user.account_status ?? 'actif') === 'actif' ? 'Suspendre' : 'Activer'}
                                                                        >
                                                                            {(user.account_status ?? 'actif') === 'actif' ? 'Suspendre' : 'Activer'}
                                                                        </button>
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => handleDeleteUser(user)}
                                                                            className={actionButtonClass('danger')}
                                                                            title="Supprimer"
                                                                        >
                                                                            Supprimer
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
                                                                        onClick={() => handleFournisseurDecision(fournisseur, 'agree')}
                                                                        className={actionButtonClass('success')}
                                                                    >
                                                                        Agréer
                                                                    </button>
                                                                    <button
                                                                        type="button"
                                                                        disabled={actionLoading}
                                                                        onClick={() => handleFournisseurDecision(fournisseur, 'suspendu')}
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

                            {activeTab === 'transactions' ? (
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
                                                        <tr 
                                                            key={transaction.id}
                                                            onClick={() => setSelectedTransactionForDetails(transaction)}
                                                            className="cursor-pointer hover:bg-black/[0.02] transition"
                                                        >
                                                            <td className="font-semibold text-[var(--admin-text)]">#{transaction.id}</td>
                                                            <td className="text-sm text-[var(--admin-text-soft)]">{transactionTypeLabels[transaction.type] ?? transaction.type}</td>
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

                            {activeTab === 'settings' ? (
                                <section className="mt-5 grid gap-5 xl:grid-cols-2">
                                    <Surface className="rounded-[32px] p-5 lg:p-6">
                                        <SectionTitle
                                            description="Garde-fous fonctionnels à ne jamais contourner dans ProsArtisan."
                                            title="Règles métier critiques"
                                        />
                                        <ul className="mt-5 space-y-2.5">
                                            {[
                                                { icon: ShieldIcon, text: 'Client et artisan doivent être KYC actifs avant toute mission ou transaction.' },
                                                { icon: WalletIcon, text: "Le ratio de fragmentation matériaux / main d'œuvre reste figé après acceptation du devis." },
                                                { icon: AlertIcon, text: 'Le scan J-Code doit toujours vérifier une distance fournisseur ≤ 100 m.' },
                                                { icon: ClipboardIcon, text: 'Aucune libération de fonds sans OTP jalon validé côté client.' },
                                                { icon: UsersIcon, text: 'Toute mission au-delà de 2 000 000 FCFA exige une validation physique Référent.' },
                                                { icon: SettingsIcon, text: 'Les montants financiers restent en BIGINT FCFA, sans float ni double.' },
                                            ].map((rule, index) => (
                                                <li key={index} className="flex items-start gap-3 rounded-[18px] border border-[var(--admin-border)] bg-white/55 px-4 py-3">
                                                    <rule.icon className="mt-0.5 h-4 w-4 shrink-0 text-[var(--admin-muted)]" />
                                                    <span className="text-sm leading-6 text-[var(--admin-text-soft)]">{rule.text}</span>
                                                </li>
                                            ))}
                                        </ul>
                                    </Surface>

                                    <Surface className="rounded-[32px] p-5 lg:p-6">
                                        <SectionTitle
                                            description="Configuration métier et accès du poste administrateur courant."
                                            title="Session backoffice"
                                        />
                                        <div className="mt-5 grid gap-3 sm:grid-cols-2">
                                            <InfoPill label="Admin" value={adminName} />
                                            <InfoPill label="Contact" value={adminContact} />
                                            <InfoPill label="Langue" value="Français" />
                                            <InfoPill label="Paiements" value="Wave CI / Orange Money CI" />
                                            <div className="flex items-center justify-between gap-3 rounded-[18px] border border-[var(--admin-border)] bg-white/55 px-4 py-3">
                                                <div>
                                                    <p className="text-[10px] font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Connectivité</p>
                                                    <p className="mt-1 text-sm font-bold text-[var(--admin-text)]">
                                                        {offlineActive ? 'Hors-ligne (+ USSD)' : 'En ligne'}
                                                    </p>
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => setIsOfflineSimulated(prev => !prev)}
                                                    className={cn(
                                                        "rounded-lg px-2.5 py-1.5 text-xs font-semibold border transition",
                                                        offlineActive
                                                            ? "bg-amber-100 border-amber-300 text-amber-800"
                                                            : "bg-green-100 border-green-300 text-green-800"
                                                    )}
                                                >
                                                    {isOfflineSimulated ? 'Simulé' : 'Simuler Offline'}
                                                </button>
                                            </div>
                                            <InfoPill label="Mobile" value="Android prioritaire" />
                                        </div>
                                        <div className="mt-5 flex flex-wrap gap-3">
                                            <button type="button" className="admin-button admin-button--primary" onClick={refreshData}>
                                                Rafraîchir les données
                                            </button>
                                            <button type="button" className="admin-button admin-button--ghost" onClick={() => router.post('/admin/logout')}>
                                                Se déconnecter
                                            </button>
                                        </div>
                                    </Surface>

                                    <Surface className="rounded-[32px] p-5 lg:p-6 xl:col-span-2">
                                        <SectionTitle
                                            description="Modifiez en temps réel les pourcentages de commission, frais de service et configurations métier."
                                            title="Gestion des Commissions & Paramètres"
                                        />
                                        <div className="mt-6 space-y-6">
                                            {settingsList && settingsList.length > 0 ? (
                                                settingsList.map((setting) => (
                                                    <div key={setting.id} className="flex flex-col md:flex-row md:items-center justify-between gap-4 p-4 rounded-2xl border border-[var(--admin-border)] bg-white/60">
                                                        <div className="min-w-0 flex-1">
                                                            <h4 className="font-semibold text-sm text-[var(--admin-text)]">{setting.label ?? setting.key}</h4>
                                                            <p className="text-xs text-[var(--admin-muted)] mt-1">{setting.description}</p>
                                                        </div>
                                                        <div className="flex items-center gap-3 shrink-0">
                                                            {setting.key === 'otp_delivery_channel' ? (
                                                                <select
                                                                    defaultValue={setting.value}
                                                                    onChange={(e) => {
                                                                        router.put(`/admin/settings/${setting.id}`, {
                                                                            value: e.target.value,
                                                                        }, { preserveScroll: true });
                                                                    }}
                                                                    className="admin-input w-48 rounded-xl px-3 py-2 text-sm text-center outline-none bg-white border border-[var(--admin-border)]"
                                                                >
                                                                    <option value="sms">SMS uniquement</option>
                                                                    <option value="whatsapp">WhatsApp uniquement</option>
                                                                    <option value="both">SMS & WhatsApp</option>
                                                                </select>
                                                            ) : setting.key.startsWith('block_') ? (
                                                                <select
                                                                    defaultValue={setting.value}
                                                                    onChange={(e) => {
                                                                        router.put(`/admin/settings/${setting.id}`, {
                                                                            value: e.target.value,
                                                                        }, { preserveScroll: true });
                                                                    }}
                                                                    className="admin-input w-48 rounded-xl px-3 py-2 text-sm text-center outline-none bg-white border border-[var(--admin-border)]"
                                                                >
                                                                    <option value="none">Accès normal</option>
                                                                    <option value="new">Bloquer Nouveaux</option>
                                                                    <option value="old">Bloquer Anciens</option>
                                                                    <option value="all">Bloquer Tous</option>
                                                                </select>
                                                            ) : setting.key === 'app_access_disabled_message' ? (
                                                                <textarea
                                                                    defaultValue={setting.value}
                                                                    onBlur={(e) => {
                                                                        if (e.target.value !== setting.value) {
                                                                            router.put(`/admin/settings/${setting.id}`, {
                                                                                value: e.target.value,
                                                                            }, { preserveScroll: true });
                                                                        }
                                                                    }}
                                                                    rows={3}
                                                                    className="admin-input w-72 rounded-xl px-3 py-2 text-sm outline-none bg-white border border-[var(--admin-border)] resize-y"
                                                                />
                                                            ) : (
                                                                <input
                                                                    type="text"
                                                                    defaultValue={setting.value}
                                                                    onBlur={(e) => {
                                                                        if (e.target.value !== setting.value) {
                                                                            router.put(`/admin/settings/${setting.id}`, {
                                                                                value: e.target.value,
                                                                            }, { preserveScroll: true });
                                                                        }
                                                                    }}
                                                                    className="admin-input w-48 rounded-xl px-3 py-2 text-sm text-center outline-none"
                                                                />
                                                            )}
                                                            <span className="text-xs text-[var(--admin-muted)] uppercase tracking-wider font-semibold">
                                                                {setting.type}
                                                            </span>
                                                        </div>
                                                    </div>
                                                ))
                                            ) : (
                                                <p className="text-sm text-[var(--admin-muted)]">Aucun paramètre trouvé.</p>
                                            )}
                                        </div>
                                    </Surface>

                                    <Surface className="rounded-[32px] p-5 lg:p-6 xl:col-span-2">
                                        <SectionTitle
                                            description="Modifiez les noms des catégories (Secteurs) et sous-catégories (Métiers) de la plateforme."
                                            title="Gestion des Catégories & Métiers"
                                        />

                                        {/* Nouvelles créations */}
                                        <div className="mt-6 grid gap-6 md:grid-cols-2 p-5 rounded-2xl border border-[var(--admin-border)] bg-amber-50/20">
                                            {/* Création Catégorie */}
                                            <form onSubmit={(e) => {
                                                e.preventDefault();
                                                const form = e.currentTarget;
                                                const input = form.querySelector('input') as HTMLInputElement;
                                                if (input.value.trim() !== '') {
                                                    router.post('/admin/sectors', { name: input.value }, {
                                                        preserveScroll: true,
                                                        onSuccess: () => { input.value = ''; }
                                                    });
                                                }
                                            }} className="space-y-3">
                                                <h4 className="font-semibold text-sm text-[var(--admin-text)]">Créer une catégorie</h4>
                                                <div className="flex gap-2">
                                                    <input
                                                        type="text"
                                                        placeholder="Nom de la catégorie (ex: Électricité)"
                                                        className="admin-input flex-1 rounded-xl px-3 py-2 text-sm outline-none bg-white border border-[var(--admin-border)]"
                                                    />
                                                    <button type="submit" className="admin-button admin-button--primary text-xs py-2 px-3">
                                                        Ajouter
                                                    </button>
                                                </div>
                                            </form>

                                            {/* Création Sous-catégorie */}
                                            <form onSubmit={(e) => {
                                                e.preventDefault();
                                                const form = e.currentTarget;
                                                const select = form.querySelector('select') as HTMLSelectElement;
                                                const input = form.querySelector('input') as HTMLInputElement;
                                                if (select.value && input.value.trim() !== '') {
                                                    router.post('/admin/trades', {
                                                        sector_id: select.value,
                                                        name: input.value
                                                    }, {
                                                        preserveScroll: true,
                                                        onSuccess: () => { input.value = ''; }
                                                    });
                                                }
                                            }} className="space-y-3">
                                                <h4 className="font-semibold text-sm text-[var(--admin-text)]">Créer une sous-catégorie</h4>
                                                <div className="flex gap-2">
                                                    <select
                                                        className="admin-input rounded-xl px-3 py-2 text-sm outline-none bg-white border border-[var(--admin-border)]"
                                                        defaultValue=""
                                                        required
                                                    >
                                                        <option value="" disabled>Sélectionner catégorie</option>
                                                        {props.sectors?.map(s => (
                                                            <option key={s.id} value={s.id}>{s.name}</option>
                                                        ))}
                                                    </select>
                                                    <input
                                                        type="text"
                                                        placeholder="Nom du métier (ex: Bobineur)"
                                                        className="admin-input flex-1 rounded-xl px-3 py-2 text-sm outline-none bg-white border border-[var(--admin-border)]"
                                                        required
                                                    />
                                                    <button type="submit" className="admin-button admin-button--primary text-xs py-2 px-3">
                                                        Ajouter
                                                    </button>
                                                </div>
                                            </form>
                                        </div>

                                        <div className="mt-6 space-y-6">
                                            {props.sectors && props.sectors.length > 0 ? (
                                                props.sectors.map((sector) => (
                                                    <div key={sector.id} className="p-5 rounded-2xl border border-[var(--admin-border)] bg-white/60 space-y-4">
                                                        <div className="flex items-center justify-between gap-4">
                                                            <div className="flex-1">
                                                                <span className="text-[10px] font-bold text-amber-600 uppercase">Catégorie (Secteur)</span>
                                                                <input
                                                                    type="text"
                                                                    defaultValue={sector.name}
                                                                    onBlur={(e) => {
                                                                        if (e.target.value !== sector.name && e.target.value.trim() !== '') {
                                                                            router.put(`/admin/sectors/${sector.id}`, {
                                                                                name: e.target.value,
                                                                            }, { preserveScroll: true });
                                                                        }
                                                                    }}
                                                                    className="admin-input mt-1 w-full rounded-xl px-3 py-2 text-sm font-semibold outline-none"
                                                                />
                                                            </div>
                                                        </div>

                                                        <div className="pl-6 border-l-2 border-amber-200/50 space-y-3">
                                                            <span className="text-[10px] font-bold text-slate-500 uppercase">Sous-catégories (Métiers)</span>
                                                            <div className="grid gap-3 sm:grid-cols-2">
                                                                {sector.trades && sector.trades.length > 0 ? (
                                                                    sector.trades.map((trade) => (
                                                                        <div key={trade.id} className="flex flex-col p-2 bg-white/40 border border-slate-100 rounded-xl">
                                                                            <input
                                                                                type="text"
                                                                                defaultValue={trade.name}
                                                                                onBlur={(e) => {
                                                                                    if (e.target.value !== trade.name && e.target.value.trim() !== '') {
                                                                                        router.put(`/admin/trades/${trade.id}`, {
                                                                                            name: e.target.value,
                                                                                        }, { preserveScroll: true });
                                                                                    }
                                                                                }}
                                                                                className="admin-input w-full rounded-lg px-2 py-1 text-xs outline-none bg-transparent hover:bg-white focus:bg-white"
                                                                            />
                                                                        </div>
                                                                    ))
                                                                ) : (
                                                                    <p className="text-xs text-[var(--admin-muted)]">Aucun métier associé.</p>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </div>
                                                ))
                                            ) : (
                                                <p className="text-sm text-[var(--admin-muted)]">Aucune catégorie trouvée.</p>
                                            )}
                                        </div>
                                    </Surface>
                                </section>
                            ) : null}

                            {activeTab === 'roles_permissions' ? (
                                <section className="mt-5">
                                    <RolesPermissionsPanel
                                        allPermissions={props.allPermissions ?? []}
                                        rolesPermissions={props.rolesPermissions ?? {}}
                                    />
                                </section>
                            ) : null}

                            {activeTab === 'llm_admin' ? (
                                <section className="mt-5">
                                    <LlmAdminPanel />
                                </section>
                            ) : null}

                            {activeTab === 'ai_dashboard' ? (
                                <section className="mt-5">
                                    <AiDashboardPanel
                                        stats={props.stats as any}
                                        costsByModel={props.costsByModel as any}
                                        dailyUsage={props.dailyUsage as any}
                                        logs={props.logs as any}
                                        settings={props.settings as any}
                                    />
                                </section>
                            ) : null}

                            {activeTab === 'evaluations' ? (
                                <section className="mt-5 space-y-5">
                                    <div className="flex gap-2 border-b border-[var(--admin-border)] pb-4">
                                        <button
                                            type="button"
                                            onClick={() => setEvalSubTab('list')}
                                            className={cn(
                                                'rounded-xl px-4 py-2 text-sm font-semibold transition',
                                                evalSubTab === 'list'
                                                    ? 'bg-[#ebb95e] text-[#241b16]'
                                                    : 'text-[var(--admin-text-soft)] hover:bg-white/40'
                                            )}
                                        >
                                            Évaluations clients ({analytics.filteredEvaluations.length})
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setEvalSubTab('artisans')}
                                            className={cn(
                                                'rounded-xl px-4 py-2 text-sm font-semibold transition',
                                                evalSubTab === 'artisans'
                                                    ? 'bg-[#ebb95e] text-[#241b16]'
                                                    : 'text-[var(--admin-text-soft)] hover:bg-white/40'
                                            )}
                                        >
                                            Scores N'Zassa Artisans ({analytics.filteredArtisansScores.length})
                                        </button>
                                    </div>

                                    {evalSubTab === 'list' ? (
                                        <Surface className="rounded-[32px] p-5 lg:p-6">
                                            <SectionTitle
                                                description="Historique des évaluations des chantiers avec le détail des notes de fiabilité, intégrité, qualité et réactivité."
                                                title="Liste des Évaluations"
                                            />
                                            <DataTable className="mt-5">
                                                <thead>
                                                    <tr>
                                                        <th>Mission</th>
                                                        <th>Évaluateur (Client)</th>
                                                        <th>Évalué (Artisan)</th>
                                                        <th>Note Générale</th>
                                                        <th>Critères N'Zassa (F / I / Q / R)</th>
                                                        <th>Commentaire</th>
                                                        <th>Date</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {analytics.filteredEvaluations.length === 0 ? (
                                                        <tr>
                                                            <td colSpan={7}>
                                                                <EmptyState description="Aucune évaluation ne correspond à vos filtres." title="Aucune évaluation trouvée" />
                                                            </td>
                                                        </tr>
                                                    ) : (
                                                        analytics.filteredEvaluations.map((evaluation) => (
                                                            <tr key={evaluation.id}>
                                                                <td className="font-medium text-[var(--admin-text)]">
                                                                    Mission #{evaluation.mission_id}
                                                                    {evaluation.mission && (
                                                                        <span className="block text-xs text-[var(--admin-muted)] truncate max-w-[150px]">
                                                                            {evaluation.mission.description}
                                                                        </span>
                                                                    )}
                                                                </td>
                                                                <td>
                                                                    <div className="font-semibold text-[var(--admin-text)]">
                                                                        {evaluation.evaluateur?.name ?? 'Client inconnu'}
                                                                    </div>
                                                                    <div className="text-xs text-[var(--admin-muted)]">
                                                                        {evaluation.evaluateur?.phone}
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <div className="font-semibold text-[var(--admin-text)]">
                                                                        {evaluation.evalue?.name ?? 'Artisan inconnu'}
                                                                    </div>
                                                                    <div className="text-xs text-[var(--admin-muted)]">
                                                                        {evaluation.evalue?.phone}
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <div className="flex items-center gap-1">
                                                                        <span className="font-bold text-[#b77918]">{evaluation.note}</span>
                                                                        <span className="text-xs text-[var(--admin-muted)]">/ 5</span>
                                                                        <div className="flex text-amber-500">
                                                                            {Array.from({ length: 5 }).map((_, idx) => (
                                                                                <svg
                                                                                    key={idx}
                                                                                    className={cn("h-3.5 w-3.5", idx < evaluation.note ? "fill-current" : "stroke-current fill-none")}
                                                                                    viewBox="0 0 24 24"
                                                                                >
                                                                                    <path d="m12 2 2.68 5.44L21 8.6l-4.5 4.38 1.06 6.18L12 16.26l-5.56 2.9 1.06-6.18L3 8.6l6.32-.92L12 2Z" />
                                                                                </svg>
                                                                            ))}
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <div className="grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-[var(--admin-text-soft)]">
                                                                        <span>Fiabilité (40%) : <strong className="text-[var(--admin-text)]">{evaluation.fiabilite}/5</strong></span>
                                                                        <span>Intégrité (30%) : <strong className="text-[var(--admin-text)]">{evaluation.integrite}/5</strong></span>
                                                                        <span>Qualité (20%) : <strong className="text-[var(--admin-text)]">{evaluation.qualite}/5</strong></span>
                                                                        <span>Réactivité (10%) : <strong className="text-[var(--admin-text)]">{evaluation.reactivite}/5</strong></span>
                                                                    </div>
                                                                </td>
                                                                <td className="max-w-[200px] truncate text-sm italic text-[var(--admin-text-soft)]" title={evaluation.commentaire ?? ''}>
                                                                    {evaluation.commentaire ?? 'Aucun commentaire'}
                                                                </td>
                                                                <td className="text-xs text-[var(--admin-muted)]">
                                                                    {shortDate(evaluation.created_at)}
                                                                </td>
                                                            </tr>
                                                        ))
                                                    )}
                                                </tbody>
                                            </DataTable>
                                        </Surface>
                                    ) : (
                                        <Surface className="rounded-[32px] p-5 lg:p-6">
                                            <SectionTitle
                                                description="Administration des réputations d'artisans. Gelez les scores pour geler les droits au micro-crédit en cas de litige."
                                                title="Scores N'Zassa des Artisans"
                                            />
                                            <DataTable className="mt-5">
                                                <thead>
                                                    <tr>
                                                        <th>Artisan</th>
                                                        <th>Score N'Zassa</th>
                                                        <th>Évaluations reçues</th>
                                                        <th>Moyennes critères (F / I / Q / R)</th>
                                                        <th>Statut du Score</th>
                                                        <th className="text-right">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {analytics.filteredArtisansScores.length === 0 ? (
                                                        <tr>
                                                            <td colSpan={6}>
                                                                <EmptyState description="Aucun artisan ne correspond à votre recherche." title="Aucun résultat" />
                                                            </td>
                                                        </tr>
                                                    ) : (
                                                        analytics.filteredArtisansScores.map((artisan) => (
                                                            <tr key={artisan.id}>
                                                                <td>
                                                                    <div className="font-semibold text-[var(--admin-text)]">{artisan.name}</div>
                                                                    <div className="text-xs text-[var(--admin-muted)]">{artisan.phone}</div>
                                                                </td>
                                                                <td>
                                                                    <span className={cn(
                                                                        'rounded-full border px-3 py-1 text-xs font-bold',
                                                                        artisan.score_nzassa >= 70
                                                                            ? 'border-green-300 bg-green-50 text-green-700'
                                                                            : artisan.score_nzassa >= 40
                                                                                ? 'border-amber-300 bg-amber-50 text-amber-700'
                                                                                : 'border-rose-300 bg-rose-50 text-rose-700'
                                                                    )}>
                                                                        {artisan.score_nzassa} / 100
                                                                    </span>
                                                                    {artisan.score_nzassa >= 70 && (
                                                                        <span className="ml-2 inline-flex items-center rounded bg-yellow-100 px-2 py-0.5 text-[10px] font-semibold text-yellow-800">
                                                                            Micro-crédit éligible
                                                                        </span>
                                                                    )}
                                                                </td>
                                                                <td>
                                                                    <span className="text-sm font-semibold">{artisan.evaluations_recues_count}</span>
                                                                </td>
                                                                <td>
                                                                    <div className="grid grid-cols-2 gap-x-2 text-xs text-[var(--admin-text-soft)]">
                                                                        <span>F: <strong>{Number(artisan.evaluations_recues_avg_fiabilite ?? 0).toFixed(1)}/5</strong></span>
                                                                        <span>I: <strong>{Number(artisan.evaluations_recues_avg_integrite ?? 0).toFixed(1)}/5</strong></span>
                                                                        <span>Q: <strong>{Number(artisan.evaluations_recues_avg_qualite ?? 0).toFixed(1)}/5</strong></span>
                                                                        <span>R: <strong>{Number(artisan.evaluations_recues_avg_reactivite ?? 0).toFixed(1)}/5</strong></span>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    {artisan.score_frozen ? (
                                                                        <span className="rounded-full border border-rose-300 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-600">
                                                                            Gelé (Bloqué)
                                                                        </span>
                                                                    ) : (
                                                                        <span className="rounded-full border border-green-300 bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-600">
                                                                            Actif (Calculé)
                                                                        </span>
                                                                    )}
                                                                </td>
                                                                <td className="text-right">
                                                                    <div className="flex justify-end gap-2">
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => setSelectedArtisanForLedger(artisan)}
                                                                            className="rounded-full border border-[var(--admin-border)] hover:bg-[#f7efe2] text-[#8a6b3d] px-3.5 py-1.5 text-xs font-semibold transition"
                                                                        >
                                                                            Historique
                                                                        </button>
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => handleToggleScoreFreeze(artisan)}
                                                                            className={actionButtonClass(artisan.score_frozen ? 'success' : 'danger')}
                                                                        >
                                                                            {artisan.score_frozen ? 'Dégeler' : 'Geler'}
                                                                        </button>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        ))
                                                    )}
                                                </tbody>
                                            </DataTable>
                                        </Surface>
                                    )}
                                </section>
                            ) : null}
                        </main>

                        <footer className="px-4 pb-6 lg:px-7">
                            <div className="border-t border-[var(--admin-border)] pt-6 text-sm text-[var(--admin-text-soft)]">
                                <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-[var(--admin-muted)]">Administration</p>
                                <p className="mt-2 text-xl font-semibold text-[var(--admin-text)]">ProsArtisan Backoffice</p>
                                <p className="mt-1 max-w-3xl">
                                    Pilotage des validations, des opérations terrain, des litiges et des flux financiers dans une seule interface.
                                </p>
                                <div className="mt-4">
                                    <Link href="/cgu" className="hover:text-[var(--admin-text)] hover:underline">Conditions Générales d'Utilisation</Link>
                                </div>
                            </div>
                        </footer>
                    </div>
                </div>

                {userModalOpen && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
                        <div className="admin-panel admin-surface w-full max-w-[550px] rounded-[32px] border p-6 lg:p-8 shadow-2xl relative">
                            <div className="flex items-center justify-between border-b border-[var(--admin-border)] pb-4">
                                <h2 className="text-xl font-bold text-[var(--admin-text)]">
                                    {editingUser ? 'Modifier l’utilisateur' : 'Créer un utilisateur'}
                                </h2>
                                <button
                                    type="button"
                                    onClick={() => setUserModalOpen(false)}
                                    className="rounded-full p-2 text-[var(--admin-muted)] hover:bg-white/10 hover:text-[var(--admin-text)] transition"
                                    title="Fermer"
                                    aria-label="Fermer"
                                >
                                    <svg className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                        <path d="M6 18L18 6M6 6l12 12" strokeLinecap="round" strokeLinejoin="round" />
                                    </svg>
                                </button>
                            </div>

                            <form onSubmit={handleUserFormSubmit} className="mt-6 space-y-4">
                                <label className="block space-y-1">
                                    <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Nom complet</span>
                                    <input
                                        type="text"
                                        value={userForm.data.name}
                                        onChange={(e) => userForm.setData('name', e.target.value)}
                                        className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                                        required
                                    />
                                    {userForm.errors.name && <p className="text-xs text-[#b24f43]">{userForm.errors.name}</p>}
                                </label>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <label className="block space-y-1">
                                        <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Téléphone</span>
                                        <input
                                            type="text"
                                            value={userForm.data.phone}
                                            onChange={(e) => userForm.setData('phone', e.target.value)}
                                            className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                                            required
                                            placeholder="+225..."
                                        />
                                        {userForm.errors.phone && <p className="text-xs text-[#b24f43]">{userForm.errors.phone}</p>}
                                    </label>

                                    <label className="block space-y-1">
                                        <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">E-mail</span>
                                        <input
                                            type="email"
                                            value={userForm.data.email}
                                            onChange={(e) => userForm.setData('email', e.target.value)}
                                            className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                                            placeholder="exemple@email.com"
                                        />
                                        {userForm.errors.email && <p className="text-xs text-[#b24f43]">{userForm.errors.email}</p>}
                                    </label>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <label className="block space-y-1">
                                        <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Rôle</span>
                                        <select
                                            value={userForm.data.role}
                                            onChange={(e) => userForm.setData('role', e.target.value as any)}
                                            className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none bg-transparent"
                                        >
                                            <option value="client">Client</option>
                                            <option value="artisan">Artisan</option>
                                            <option value="fournisseur">Fournisseur</option>
                                            <option value="referent">Référent</option>
                                            <option value="admin">Administrateur</option>
                                        </select>
                                        {userForm.errors.role && <p className="text-xs text-[#b24f43]">{userForm.errors.role}</p>}
                                    </label>

                                    <label className="block space-y-1">
                                        <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Mot de passe</span>
                                        <input
                                            type="password"
                                            value={userForm.data.password}
                                            onChange={(e) => userForm.setData('password', e.target.value)}
                                            className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                                            required={!editingUser}
                                            placeholder={editingUser ? 'Laisser vide pour ne pas changer' : 'Minimum 6 caractères'}
                                        />
                                        {userForm.errors.password && <p className="text-xs text-[#b24f43]">{userForm.errors.password}</p>}
                                    </label>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <label className="block space-y-1">
                                        <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Statut KYC</span>
                                        <select
                                            value={userForm.data.kyc_status}
                                            onChange={(e) => userForm.setData('kyc_status', e.target.value as any)}
                                            className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none bg-transparent"
                                        >
                                            <option value="en_attente">En attente</option>
                                            <option value="actif">Actif (Approuvé)</option>
                                            <option value="rejete">Rejeté</option>
                                        </select>
                                        {userForm.errors.kyc_status && <p className="text-xs text-[#b24f43]">{userForm.errors.kyc_status}</p>}
                                    </label>

                                    <label className="block space-y-1">
                                        <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Statut du compte</span>
                                        <select
                                            value={userForm.data.account_status}
                                            onChange={(e) => userForm.setData('account_status', e.target.value as any)}
                                            className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none bg-transparent"
                                        >
                                            <option value="actif">Actif</option>
                                            <option value="suspendu">Suspendu</option>
                                        </select>
                                        {userForm.errors.account_status && <p className="text-xs text-[#b24f43]">{userForm.errors.account_status}</p>}
                                    </label>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <label className="block space-y-1">
                                        <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Gel de Score N'Zassa</span>
                                        <select
                                            value={userForm.data.score_frozen ? 'oui' : 'non'}
                                            onChange={(e) => userForm.setData('score_frozen', e.target.value === 'oui')}
                                            className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none bg-transparent"
                                        >
                                            <option value="non">Actif (Non gelé)</option>
                                            <option value="oui">Gelé (Bloqué)</option>
                                        </select>
                                        {userForm.errors.score_frozen && <p className="text-xs text-[#b24f43]">{userForm.errors.score_frozen}</p>}
                                    </label>

                                    <label className="block space-y-1">
                                        <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Empreinte de l'appareil (IMEI)</span>
                                        <input
                                            type="text"
                                            value={userForm.data.device_fingerprint}
                                            onChange={(e) => userForm.setData('device_fingerprint', e.target.value)}
                                            className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                                            placeholder="Empreinte IMEI / Appareil"
                                        />
                                        {userForm.errors.device_fingerprint && <p className="text-xs text-[#b24f43]">{userForm.errors.device_fingerprint}</p>}
                                    </label>
                                </div>

                                <div className="flex justify-end gap-3 pt-4 border-t border-[var(--admin-border)]">
                                    <button
                                        type="button"
                                        onClick={() => setUserModalOpen(false)}
                                        className="admin-button admin-button--ghost"
                                    >
                                        Annuler
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={userForm.processing}
                                        className="admin-button admin-button--primary"
                                    >
                                        {userForm.processing ? 'Enregistrement...' : 'Enregistrer'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {statusModalOpen && statusTargetUser && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
                        <div className="admin-panel admin-surface w-full max-w-[450px] rounded-[32px] border p-6 shadow-2xl relative">
                            <div className="flex items-center justify-between border-b border-[var(--admin-border)] pb-4">
                                <h2 className="text-lg font-bold text-[var(--admin-text)]">
                                    Suspendre le compte de {statusTargetUser.name}
                                </h2>
                                <button
                                    type="button"
                                    onClick={() => setStatusModalOpen(false)}
                                    className="rounded-full p-2 text-[var(--admin-muted)] hover:bg-white/10 hover:text-[var(--admin-text)] transition"
                                    title="Fermer"
                                    aria-label="Fermer"
                                >
                                    <svg className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                        <path d="M6 18L18 6M6 6l12 12" strokeLinecap="round" strokeLinejoin="round" />
                                    </svg>
                                </button>
                            </div>

                            <form onSubmit={handleStatusSubmit} className="mt-5 space-y-4">
                                <p className="text-sm text-[var(--admin-text-soft)]">
                                    Veuillez indiquer le motif de suspension du compte. Ce motif sera visible pour l'utilisateur.
                                </p>
                                <label className="block space-y-1">
                                    <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Motif de suspension</span>
                                    <textarea
                                        value={statusForm.data.account_status_reason}
                                        onChange={(e) => statusForm.setData('account_status_reason', e.target.value)}
                                        className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none h-24 resize-none"
                                        required
                                        placeholder="Ex: Documents non conformes ou comportement abusif signalé..."
                                    />
                                    {statusForm.errors.account_status_reason && (
                                        <p className="text-xs text-[#b24f43]">{statusForm.errors.account_status_reason}</p>
                                    )}
                                </label>

                                <div className="flex justify-end gap-3 pt-3">
                                    <button
                                        type="button"
                                        onClick={() => setStatusModalOpen(false)}
                                        className="admin-button admin-button--ghost"
                                    >
                                        Annuler
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={statusForm.processing}
                                        className="admin-button bg-[#f15f57] text-white hover:bg-[#dd4d45]"
                                    >
                                        {statusForm.processing ? 'Suspension...' : 'Suspendre le compte'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {selectedArtisanForLedger && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
                        <div className="admin-panel admin-surface w-full max-w-[700px] rounded-[32px] border p-6 lg:p-8 shadow-2xl relative">
                            <div className="flex items-center justify-between border-b border-[var(--admin-border)] pb-4">
                                <div>
                                    <h2 className="text-xl font-bold text-[var(--admin-text)]">
                                        Historique N'Zassa : {selectedArtisanForLedger.name}
                                    </h2>
                                    <p className="text-xs text-[var(--admin-muted)] mt-1">
                                        Score actuel : {selectedArtisanForLedger.score_nzassa}/100 • {selectedArtisanForLedger.score_frozen ? 'Score Gelé' : 'Score Dynamique'}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setSelectedArtisanForLedger(null)}
                                    className="rounded-full p-2 text-[var(--admin-muted)] hover:bg-white/10 hover:text-[var(--admin-text)] transition"
                                    title="Fermer"
                                    aria-label="Fermer"
                                >
                                    <svg className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                        <path d="M6 18L18 6M6 6l12 12" strokeLinecap="round" strokeLinejoin="round" />
                                    </svg>
                                </button>
                            </div>

                            <div className="mt-6 max-h-[400px] overflow-y-auto space-y-3 pr-1">
                                {scoreLedger.filter(entry => entry.user_id === selectedArtisanForLedger.id).length === 0 ? (
                                    <EmptyState description="Aucun événement enregistré dans le Ledger pour cet artisan." title="Historique vide" />
                                ) : (
                                    scoreLedger
                                        .filter(entry => entry.user_id === selectedArtisanForLedger.id)
                                        .map((entry) => (
                                            <div key={entry.id} className="rounded-2xl border border-[var(--admin-border)] bg-white/60 p-4 flex items-start justify-between gap-4">
                                                <div className="min-w-0">
                                                    <div className="flex items-center gap-2">
                                                        <span className={cn(
                                                            'rounded-md px-2 py-0.5 text-[10px] font-bold uppercase',
                                                            entry.points > 0
                                                                ? 'bg-green-100 text-green-800'
                                                                : 'bg-rose-100 text-rose-800'
                                                        )}>
                                                            {entry.points > 0 ? `+${entry.points}` : entry.points} points
                                                        </span>
                                                        <span className="text-xs text-[var(--admin-muted)]">
                                                            Poids de crédibilité: {entry.credibility_factor}
                                                        </span>
                                                    </div>
                                                    <p className="mt-2 text-sm font-semibold text-[var(--admin-text)]">{entry.description}</p>
                                                    <span className="mt-1 block text-xs text-[var(--admin-muted)]">Type: {entry.event_type}</span>
                                                </div>
                                                <span className="text-xs text-[var(--admin-muted)] shrink-0">
                                                    {shortDate(entry.created_at)}
                                                </span>
                                            </div>
                                        ))
                                )}
                            </div>

                            <div className="mt-6 flex justify-end">
                                <button
                                    type="button"
                                    onClick={() => setSelectedArtisanForLedger(null)}
                                    className="admin-button admin-button--ghost"
                                >
                                    Fermer
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                {selectedMissionForDetails && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
                        <div className="admin-panel admin-surface w-full max-w-[850px] rounded-[32px] border p-6 lg:p-8 shadow-2xl relative max-h-[85vh] overflow-y-auto">
                            <div className="flex items-center justify-between border-b border-[var(--admin-border)] pb-4">
                                <div className="space-y-1">
                                    <h2 className="text-xl font-bold text-[var(--admin-text)] flex items-center gap-3">
                                        <span>Détails de la mission #{selectedMissionForDetails.id}</span>
                                        <MissionStatusBadge status={selectedMissionForDetails.status} />
                                    </h2>
                                    <p className="text-xs text-[var(--admin-muted)]">Créée le {shortDate(selectedMissionForDetails.created_at)}</p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setSelectedMissionForDetails(null)}
                                    className="rounded-full p-2 text-[var(--admin-muted)] hover:bg-white/10 hover:text-[var(--admin-text)] transition"
                                    title="Fermer"
                                >
                                    <svg className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                        <path d="M6 18L18 6M6 6l12 12" strokeLinecap="round" strokeLinejoin="round" />
                                    </svg>
                                </button>
                            </div>

                            <div className="mt-6 space-y-6">
                                {/* Informations Générales */}
                                <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-3">
                                    <div className="p-4 rounded-2xl border border-[var(--admin-border)] bg-white/40">
                                        <p className="text-[10px] font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Description</p>
                                        <p className="mt-1 text-sm text-[var(--admin-text)] font-medium">{selectedMissionForDetails.description}</p>
                                    </div>
                                    <div className="p-4 rounded-2xl border border-[var(--admin-border)] bg-white/40">
                                        <p className="text-[10px] font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Client</p>
                                        <p className="mt-1 text-sm text-[var(--admin-text)] font-bold">{selectedMissionForDetails.client?.name ?? 'Non renseigné'}</p>
                                        <p className="text-xs text-[var(--admin-muted)]">{selectedMissionForDetails.client?.phone}</p>
                                    </div>
                                    <div className="p-4 rounded-2xl border border-[var(--admin-border)] bg-white/40">
                                        <p className="text-[10px] font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Artisan</p>
                                        <p className="mt-1 text-sm text-[var(--admin-text)] font-bold">{selectedMissionForDetails.artisan?.name ?? 'Non affecté'}</p>
                                        <p className="text-xs text-[var(--admin-muted)]">{selectedMissionForDetails.artisan?.phone}</p>
                                    </div>
                                </div>

                                {/* Analyse IA & Finance */}
                                <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-3">
                                    <div className="p-4 rounded-2xl border border-[var(--admin-border)] bg-white/40">
                                        <p className="text-[10px] font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Analyse Gemini IA</p>
                                        <p className="mt-1 text-sm text-[var(--admin-text)] font-semibold">{selectedMissionForDetails.gemini_category ?? 'Non classée'}</p>
                                        <p className="text-xs text-[var(--admin-text-soft)]">Urgence : {selectedMissionForDetails.gemini_urgency ?? 'N/A'}</p>
                                    </div>
                                    <div className="p-4 rounded-2xl border border-[var(--admin-border)] bg-white/40">
                                        <p className="text-[10px] font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Financement total</p>
                                        <p className="mt-1 text-base font-bold text-[#8a6b3d]">{selectedMissionForDetails.montant_total ? money(selectedMissionForDetails.montant_total) : 'Non défini'}</p>
                                        {selectedMissionForDetails.montant_materiaux && (
                                            <p className="text-xs text-[var(--admin-text-soft)]">Matériaux : {money(selectedMissionForDetails.montant_materiaux)}</p>
                                        )}
                                    </div>
                                    <div className="p-4 rounded-2xl border border-[var(--admin-border)] bg-white/40">
                                        <p className="text-[10px] font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Main d'œuvre & Ratio</p>
                                        <p className="mt-1 text-sm font-semibold text-[var(--admin-text)]">
                                            {selectedMissionForDetails.montant_mo ? money(selectedMissionForDetails.montant_mo) : 'Non défini'}
                                        </p>
                                        {selectedMissionForDetails.ratio_materiaux && (
                                            <p className="text-xs text-[var(--admin-text-soft)]">Ratio Mat : {(Number(selectedMissionForDetails.ratio_materiaux) * 100).toFixed(0)}%</p>
                                        )}
                                    </div>
                                </div>

                                {/* Historique des Jalons (Milestones) */}
                                <div className="space-y-2.5">
                                    <h3 className="text-sm font-bold text-[var(--admin-text)] uppercase tracking-wider">Historique des Jalons</h3>
                                    {selectedMissionForDetails.jalons && selectedMissionForDetails.jalons.length > 0 ? (
                                        <div className="overflow-x-auto rounded-2xl border border-[var(--admin-border)] bg-white/30">
                                            <table className="min-w-full divide-y divide-[var(--admin-border)] text-xs text-left">
                                                <thead className="bg-[#fcf8f2] text-[var(--admin-muted)] font-semibold uppercase">
                                                    <tr>
                                                        <th className="px-4 py-2">Ordre</th>
                                                        <th className="px-4 py-2">Description</th>
                                                        <th className="px-4 py-2">Montant</th>
                                                        <th className="px-4 py-2">Statut</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-[var(--admin-border)]">
                                                    {selectedMissionForDetails.jalons.map((jalon: any) => (
                                                        <tr key={jalon.id}>
                                                            <td className="px-4 py-2 font-bold">#{jalon.ordre}</td>
                                                            <td className="px-4 py-2">{jalon.description}</td>
                                                            <td className="px-4 py-2 font-medium">{money(jalon.montant)}</td>
                                                            <td className="px-4 py-2">
                                                                <span className={cn('px-2 py-0.5 rounded-full border text-[10px] font-bold', 
                                                                    jalon.statut === 'paye' || jalon.statut === 'valide' ? 'border-green-300 bg-green-50 text-green-700' : 'border-amber-300 bg-amber-50 text-amber-700'
                                                                )}>
                                                                    {jalon.statut}
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    ) : (
                                        <p className="text-xs text-[var(--admin-muted)] italic">Aucun jalon défini.</p>
                                    )}
                                </div>

                                {/* Historique des J-Codes */}
                                <div className="space-y-2.5">
                                    <h3 className="text-sm font-bold text-[var(--admin-text)] uppercase tracking-wider">Historique des J-Codes (Matériaux)</h3>
                                    {selectedMissionForDetails.jcodes && selectedMissionForDetails.jcodes.length > 0 ? (
                                        <div className="overflow-x-auto rounded-2xl border border-[var(--admin-border)] bg-white/30">
                                            <table className="min-w-full divide-y divide-[var(--admin-border)] text-xs text-left">
                                                <thead className="bg-[#fcf8f2] text-[var(--admin-muted)] font-semibold uppercase">
                                                    <tr>
                                                        <th className="px-4 py-2">Code</th>
                                                        <th className="px-4 py-2">Montant</th>
                                                        <th className="px-4 py-2">Statut</th>
                                                        <th className="px-4 py-2">Fournisseur</th>
                                                        <th className="px-4 py-2">Date d'utilisation</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-[var(--admin-border)]">
                                                    {selectedMissionForDetails.jcodes.map((jcode: any) => (
                                                        <tr key={jcode.id}>
                                                            <td className="px-4 py-2 font-mono font-bold text-[#8a6b3d]">{jcode.code}</td>
                                                            <td className="px-4 py-2 font-medium">{money(jcode.montant)}</td>
                                                            <td className="px-4 py-2">
                                                                <span className={cn('px-2 py-0.5 rounded-full border text-[10px] font-bold', 
                                                                    jcode.statut === 'utilise' ? 'border-green-300 bg-green-50 text-green-700' : 'border-amber-300 bg-amber-50 text-amber-700'
                                                                )}>
                                                                    {jcode.statut}
                                                                </span>
                                                            </td>
                                                            <td className="px-4 py-2">{jcode.fournisseur?.nom_boutique ?? jcode.fournisseur?.name ?? 'Non scanné'}</td>
                                                            <td className="px-4 py-2">{jcode.scanned_at ? shortDate(jcode.scanned_at) : 'En attente'}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    ) : (
                                        <p className="text-xs text-[var(--admin-muted)] italic">Aucun J-Code généré.</p>
                                    )}
                                </div>

                                {/* Historique des Transactions Financières */}
                                <div className="space-y-2.5">
                                    <h3 className="text-sm font-bold text-[var(--admin-text)] uppercase tracking-wider">Transactions liées</h3>
                                    {selectedMissionForDetails.transactions && selectedMissionForDetails.transactions.length > 0 ? (
                                        <div className="overflow-x-auto rounded-2xl border border-[var(--admin-border)] bg-white/30">
                                            <table className="min-w-full divide-y divide-[var(--admin-border)] text-xs text-left">
                                                <thead className="bg-[#fcf8f2] text-[var(--admin-muted)] font-semibold uppercase">
                                                    <tr>
                                                        <th className="px-4 py-2">Type</th>
                                                        <th className="px-4 py-2">Montant</th>
                                                        <th className="px-4 py-2">Moyen</th>
                                                        <th className="px-4 py-2">Statut</th>
                                                        <th className="px-4 py-2">Date</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-[var(--admin-border)]">
                                                    {selectedMissionForDetails.transactions.map((tx: any) => (
                                                        <tr key={tx.id}>
                                                            <td className="px-4 py-2 font-semibold">{transactionTypeLabels[tx.type] ?? tx.type}</td>
                                                            <td className="px-4 py-2 font-bold">{money(tx.montant)}</td>
                                                            <td className="px-4 py-2">
                                                                <ProviderBadge provider={tx.provider} />
                                                            </td>
                                                            <td className="px-4 py-2">
                                                                <TransactionStatusBadge status={tx.statut} />
                                                            </td>
                                                            <td className="px-4 py-2 text-[var(--admin-muted)]">{shortDate(tx.created_at)}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    ) : (
                                        <p className="text-xs text-[var(--admin-muted)] italic">Aucune transaction enregistrée.</p>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {selectedTransactionForDetails && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
                        <div className="admin-panel admin-surface w-full max-w-[550px] rounded-[32px] border p-6 lg:p-8 shadow-2xl relative animate-in fade-in zoom-in duration-200">
                            <div className="flex items-center justify-between border-b border-[var(--admin-border)] pb-4">
                                <div className="space-y-1">
                                    <h2 className="text-xl font-bold text-[var(--admin-text)] flex items-center gap-3">
                                        <span>Transaction #{selectedTransactionForDetails.id}</span>
                                        <TransactionStatusBadge status={selectedTransactionForDetails.statut} />
                                    </h2>
                                    <p className="text-xs text-[var(--admin-muted)]">Enregistrée le {shortDate(selectedTransactionForDetails.created_at)}</p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setSelectedTransactionForDetails(null)}
                                    className="rounded-full p-2 text-[var(--admin-muted)] hover:bg-white/10 hover:text-[var(--admin-text)] transition"
                                    title="Fermer"
                                >
                                    <svg className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                        <path d="M6 18L18 6M6 6l12 12" strokeLinecap="round" strokeLinejoin="round" />
                                    </svg>
                                </button>
                            </div>

                            <div className="mt-6 space-y-4 text-sm">
                                <div className="flex justify-between py-2.5 border-b border-[var(--admin-border)]">
                                    <span className="text-[var(--admin-muted)]">Type de transaction</span>
                                    <span className="font-semibold text-[var(--admin-text)]">{transactionTypeLabels[selectedTransactionForDetails.type] ?? selectedTransactionForDetails.type}</span>
                                </div>
                                <div className="flex justify-between py-2.5 border-b border-[var(--admin-border)]">
                                    <span className="text-[var(--admin-muted)]">Montant</span>
                                    <span className="font-bold text-[#8a6b3d] text-base">{money(selectedTransactionForDetails.montant)}</span>
                                </div>
                                <div className="flex justify-between py-2.5 border-b border-[var(--admin-border)]">
                                    <span className="text-[var(--admin-muted)]">Moyen de paiement</span>
                                    <span className="font-semibold text-[var(--admin-text)]">
                                        <ProviderBadge provider={selectedTransactionForDetails.provider} />
                                    </span>
                                </div>
                                <div className="flex justify-between py-2.5 border-b border-[var(--admin-border)]">
                                    <span className="text-[var(--admin-muted)]">Référence externe</span>
                                    <span className="font-mono text-xs bg-slate-100/80 border border-slate-200 rounded px-1.5 py-0.5 text-[var(--admin-text)]">
                                        {selectedTransactionForDetails.reference_externe ?? 'N/A'}
                                    </span>
                                </div>
                                <div className="flex justify-between py-2.5 border-b border-[var(--admin-border)]">
                                    <span className="text-[var(--admin-muted)]">Provenance (Source)</span>
                                    <span className="font-medium text-[var(--admin-text)]">{selectedTransactionForDetails.wallet_source}</span>
                                </div>
                                <div className="flex justify-between py-2.5 border-b border-[var(--admin-border)]">
                                    <span className="text-[var(--admin-muted)]">Destination</span>
                                    <span className="font-medium text-[var(--admin-text)]">{selectedTransactionForDetails.wallet_dest}</span>
                                </div>
                                <div className="flex justify-between py-2.5 border-b border-[var(--admin-border)]">
                                    <span className="text-[var(--admin-muted)]">Bénéficiaire</span>
                                    <span className="font-semibold text-[var(--admin-text)]">{selectedTransactionForDetails.user?.name ?? 'Non renseigné'}</span>
                                </div>
                                {selectedTransactionForDetails.mission && (
                                    <div className="flex justify-between py-2.5 border-b border-[var(--admin-border)]">
                                        <span className="text-[var(--admin-muted)]">Mission associée</span>
                                        <span className="font-semibold text-blue-700">#{selectedTransactionForDetails.mission.id} - {selectedTransactionForDetails.mission.description}</span>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                )}

                <BottomDock activeTab={activeTab} />
            </div>
        </>
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
        <Surface className="admin-metric-card rounded-[30px] p-5 lg:p-6">
            <div className={cn('flex h-12 w-12 items-center justify-center rounded-2xl', toneIconClasses(tone))}>
                <ToneIcon tone={tone} className="h-5 w-5" />
            </div>
            <p className="mt-5 text-xs font-semibold uppercase tracking-[0.2em] text-[var(--admin-muted)]">{children}</p>
            <p className="mt-1.5 text-4xl font-semibold tracking-tight text-[var(--admin-text)]">{value}</p>
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
            <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full border border-[var(--admin-border)] bg-white/60 text-[var(--admin-muted)]">
                <InboxIcon className="h-5 w-5" />
            </div>
            <p className="text-base font-semibold text-[var(--admin-text)]">{title}</p>
            <p className="mt-2 text-sm text-[var(--admin-text-soft)]">{description}</p>
        </div>
    );
}

function AvatarBubble({ label }: { label: string }) {
    return (
        <span className="admin-avatar flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#ebb95e] text-sm font-bold text-[#241b16]">
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

function AccountStatusBadge({ status }: { status?: string | null }) {
    const isActif = (status ?? 'actif') === 'actif';
    return (
        <span className={cn('rounded-full border px-3 py-1 text-xs font-semibold', toneBadgeClasses(isActif ? 'green' : 'rose'))}>
            {isActif ? 'Actif' : 'Suspendu'}
        </span>
    );
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
                <defs>
                    {series.map((entry, seriesIndex) => (
                        <linearGradient key={entry.label} id={`chart-grad-${seriesIndex}`} x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor={entry.color} stopOpacity="0.22" />
                            <stop offset="100%" stopColor={entry.color} stopOpacity="0" />
                        </linearGradient>
                    ))}
                </defs>

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
                            stroke="rgba(194, 170, 136, 0.35)"
                            strokeDasharray="4 7"
                        />
                    );
                })}

                {series.map((entry, seriesIndex) => {
                    if (entry.points.length < 2) return null;
                    const firstX = paddingX;
                    const lastX = paddingX + (entry.points.length - 1) * xStep;
                    const bottom = paddingTop + graphHeight;
                    const polygonPoints = [
                        ...entry.points.map((point, index) => `${paddingX + index * xStep},${toY(point.value)}`),
                        `${lastX},${bottom}`,
                        `${firstX},${bottom}`,
                    ].join(' ');

                    return (
                        <polygon
                            key={`fill-${entry.label}`}
                            points={polygonPoints}
                            fill={`url(#chart-grad-${seriesIndex})`}
                        />
                    );
                })}

                {series.map((entry) => {
                    const polyline = entry.points.map((point, index) => `${paddingX + index * xStep},${toY(point.value)}`).join(' ');

                    return <polyline key={entry.label} fill="none" points={polyline} stroke={entry.color} strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" />;
                })}

                {series.map((entry) =>
                    entry.points.map((point, index) => (
                        <circle
                            key={`${entry.label}-dot-${index}`}
                            cx={paddingX + index * xStep}
                            cy={toY(point.value)}
                            r="4"
                            fill="white"
                            stroke={entry.color}
                            strokeWidth="2.5"
                        />
                    )),
                )}

                {points.map((point, index) => {
                    const x = paddingX + index * xStep;

                    return (
                        <text key={point.label} x={x} y={height - 12} textAnchor="middle" fontSize="11" fill="rgba(110, 91, 66, 0.72)">
                            {point.label}
                        </text>
                    );
                })}
            </svg>

            <div className="mt-3 flex flex-wrap gap-5">
                {series.map((entry) => (
                    <div key={entry.label} className="flex items-center gap-2 text-sm text-[var(--admin-text-soft)]">
                        <span
                            className="h-3 w-3 rounded-full border-2 border-white shadow-sm"
                            ref={(el) => {
                                if (el) el.style.backgroundColor = entry.color;
                            }}
                        />
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
            <div className="mb-2 flex items-center justify-end gap-1">
                <span className="text-[11px] text-[var(--admin-muted)]">max</span>
                <span className="text-[11px] font-semibold text-[var(--admin-text)]">{maxValue}</span>
            </div>
            <div className="flex h-52 items-end gap-1.5 overflow-hidden rounded-[20px] bg-[rgba(255,255,255,0.55)] p-3">
                {bars.map((bar) => {
                    const heightPercent = Math.max((bar.value / maxValue) * 100, bar.value > 0 ? 8 : 3);

                    return (
                        <div key={bar.label} className="group flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1.5">
                            <span className="text-[10px] font-medium text-[var(--admin-text)] opacity-0 transition-opacity group-hover:opacity-100">
                                {bar.value > 0 ? bar.value : ''}
                            </span>
                            <div
                                className="w-full rounded-t-[10px] transition-all duration-500 group-hover:opacity-100"
                                ref={(el) => {
                                    if (el) {
                                        el.style.backgroundColor = color;
                                        el.style.height = `${heightPercent}%`;
                                        el.style.opacity = '0.82';
                                    }
                                }}
                            />
                            <span className="text-[10px] text-[var(--admin-muted)]">{bar.label}</span>
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
        case 'llm_admin':
            return <InboxIcon className={className} />;
        case 'ai_dashboard':
            return <SettingsIcon className={className} />;
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
        case 'evaluations':
            return <StarIcon className={className} />;
        default:
            return <DashboardIcon className={className} />;
    }
}


function ToneIcon({ className = 'h-5 w-5', tone }: { className?: string; tone: Tone }) {
    switch (tone) {
        case 'amber':
            return <StarIcon className={className} />;
        case 'green':
            return <TrendUpIcon className={className} />;
        case 'rose':
            return <AlertIcon className={className} />;
        case 'blue':
            return <ClockIcon className={className} />;
        case 'slate':
            return <ArchiveIcon className={className} />;
    }
}

function ActivityToneIcon({ tone }: { tone: Tone }) {
    const cls = 'h-5 w-5';
    switch (tone) {
        case 'amber': return <ShieldIcon className={cls} />;
        case 'green': return <CheckCircleIcon className={cls} />;
        case 'rose': return <AlertIcon className={cls} />;
        case 'blue': return <WalletIcon className={cls} />;
        case 'slate': return <ArchiveIcon className={cls} />;
    }
}

function TrendUpIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="M3 17 9 11l4 4 8-9" strokeLinecap="round" strokeLinejoin="round" />
            <path d="M21 8h-5v5" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

function StarIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="m12 2 2.68 5.44L21 8.6l-4.5 4.38 1.06 6.18L12 16.26l-5.56 2.9 1.06-6.18L3 8.6l6.32-.92L12 2Z" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

function ClockIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 3" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

function ArchiveIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <rect x="3" y="4" width="18" height="4" rx="1.5" />
            <path d="M5 8v9a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8" strokeLinecap="round" />
            <path d="M10 13h4" strokeLinecap="round" />
        </svg>
    );
}

function CheckCircleIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="9" />
            <path d="m8.5 12.5 2 2 5-5" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

function InboxIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="M4 4h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z" strokeLinecap="round" />
            <path d="M3 14h4l2 3h6l2-3h4" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
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

function MenuIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

function CloseIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="M6 18 18 6M6 6l12 12" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}
