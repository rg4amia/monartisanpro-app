import { Link, router, usePage, useForm } from '@inertiajs/react';
import { useDeferredValue, useEffect, useMemo, useState } from 'react';

import { cn } from '@/lib/utils';

import AiDashboardPanel from './ai-dashboard-panel';
import { useAdminAnalytics } from './hooks/useAdminAnalytics';
import { useRowSelection } from './hooks/useRowSelection';
import { useServerTable } from './hooks/useServerTable';
import LlmAdminPanel from './llm-admin-panel';
import { AuditLogsPanel } from './panels/AuditLogsPanel';
import { CommunicationsPanel } from './panels/CommunicationsPanel';
import { DashboardPanel } from './panels/DashboardPanel';
import { ArtisanLedgerModal, MissionDetailModal, OrderDetailModal, TransactionDetailModal } from './panels/DetailModals';
import { EvaluationsPanel } from './panels/EvaluationsPanel';
import { CommunicationFormModal, PromoCodeFormModal, StatusFormModal, UserFormModal } from './panels/FormModals';
import { KycPanel } from './panels/KycPanel';
import { LitigesPanel } from './panels/LitigesPanel';
import { MissionsPanel } from './panels/MissionsPanel';
import { NotificationsPanel } from './panels/NotificationsPanel';
import { ObservabilityPanel } from './panels/ObservabilityPanel';
import { PersonalDataModal } from './panels/PersonalDataModal';
import { PromoCodesPanel } from './panels/PromoCodesPanel';
import { SettingsPanel } from './panels/SettingsPanel';
import { TransactionsPanel } from './panels/TransactionsPanel';
import { UsersPanel } from './panels/UsersPanel';
import RolesPermissionsPanel from './roles-permissions-panel';
import type {
    AdminEvaluation,
    AdminMission,
    AdminNotificationItem,
    AdminOrder,
    AdminTab,
    AdminTransaction,
    AdminUser,
    ArtisanScoreItem,
    AuditAdminOption,
    DashboardData,
    DeliveryStats,
    EvaluationStats,
    FlashMessages,
    FournisseurItem,
    KycStats,
    KycUser,
    LitigeItem,
    LitigeStats,
    MissionStats,
    NavigationGroup,
    ObservabilitySnapshot,
    Paginated,
    PaginatedAuditLogs,
    SectorItem,
    SettingItem,
    PromoCodeItem,
    ScoreLedgerEntryItem,
    ThemeMode,
    TransactionStats,
    UserStats,
} from './shared';
import {
    AdminShell,
    can,
    canOpenTab,
    money,
    numberFormat,
    tabMeta,
    tabRoutes,
    useConfirm,
} from './shared';
import VitrinePanel from './vitrine-panel';

interface AuthUser {
    email?: string | null;
    name: string;
    phone?: string | null;
    role?: string | null;
}

interface AdminPageProps {
    [key: string]: unknown;
    auth: {
        user?: AuthUser | null;
        // Capacités fines du backoffice — `['*']` = accès total (Chantier C6 / P2-10).
        permissions?: string[];
    };
    dashboard: DashboardData;
    errors: Record<string, string>;
    flash?: FlashMessages;
    fournisseurs: FournisseurItem[];
    kycUsers: KycUser[];
    cnmciUsers?: Array<{
        id: number;
        name: string;
        phone: string;
        cnmci_number?: string | null;
        cnmci_card_url?: string | null;
        cnmci_status: string;
        created_at: string;
    }>;
    litiges: LitigeItem[];
    missions: AdminMission[];
    orders?: AdminOrder[];
    transactions: AdminTransaction[];
    users: AdminUser[];
    evaluationsList: AdminEvaluation[];
    artisansScores: ArtisanScoreItem[];
    scoreLedger: ScoreLedgerEntryItem[];
    navBadges?: {
        transactions_en_attente?: number;
        communications_publiees?: number;
        promo_codes_actifs?: number;
        contact_messages_nouveaux?: number;
    };
    financialKpis?: any;
    promoCodes?: PromoCodeItem[];
    settingsList?: SettingItem[];
    sectors?: SectorItem[];
    rolesPermissions?: Record<string, string[]>;
    allPermissions?: Array<{ id: number; name: string; description: string; category: string }>;
    // Capacités fines du backoffice (Chantier C6 / P2-10).
    adminCapabilityCatalog?: Record<string, Record<string, string>>;
    admins?: Array<{ id: number; name: string; email: string | null; phone: string | null; capabilities: string[]; protected: boolean }>;
    // Santé & observabilité (Chantier C7 / P2-12).
    observability?: ObservabilitySnapshot;
    auditLogs?: PaginatedAuditLogs;
    auditActions?: string[];
    auditAdmins?: AuditAdminOption[];
    usersPage?: Paginated<AdminUser>;
    userStats?: UserStats;
    pendingFournisseurs?: FournisseurItem[];
    topArtisans?: AdminUser[];
    transactionsPage?: Paginated<AdminTransaction>;
    transactionStats?: TransactionStats;
    litigesPage?: Paginated<LitigeItem>;
    litigeStats?: LitigeStats;
    evaluationsPage?: Paginated<AdminEvaluation>;
    artisansScoresPage?: Paginated<ArtisanScoreItem>;
    evaluationStats?: EvaluationStats;
    missionsPage?: Paginated<AdminMission>;
    ordersPage?: Paginated<AdminOrder>;
    missionStats?: MissionStats;
    deliveryStats?: DeliveryStats;
    kycUsersPage?: Paginated<KycUser>;
    pendingFournisseursList?: FournisseurItem[];
    kycStats?: KycStats;
    adminNotifications?: AdminNotificationItem[];
    allNotifications?: PaginatedNotifications;
    communications?: Array<{
        id: number;
        titre: string;
        contenu: string;
        statut: string;
        canal: string;
        destinataires_role: string;
        scheduled_at: string | null;
        sent_at: string | null;
        created_at: string;
        updated_at: string;
        auteur?: { id: number; name: string; phone: string } | null;
    }>;
    vitrineSlides?: any[];
    vitrineArtisanDuMois?: any[];
    vitrineArticles?: any[];
    vitrineVideos?: any[];
    vitrineFormations?: any[];
    vitrineRecrutements?: any[];
    vitrinePopups?: any[];
    vitrineSettings?: any[];
    contactMessages?: any[];
}

interface AuditNotificationItem extends AdminNotificationItem {
    user?: {
        id: number;
        name: string;
        phone: string;
        role: string;
    } | null;
}

interface PaginatedNotifications {
    data: AuditNotificationItem[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

export default function AdminConsole({ initialTab }: { initialTab: AdminTab }) {
    const activeTab = initialTab;
    const pageProps = usePage<AdminPageProps>().props;
    const {
        auth = { user: null },
        dashboard = {
            users_total: 0,
            artisans_actifs: 0,
            clients_actifs: 0,
            fournisseurs_agrees: 0,
            missions_en_cours: 0,
            missions_en_litige: 0,
            litiges_ouverts: 0,
            kyc_en_attente: 0,
            referent_required_open: 0,
            recent_fraud_alerts: 0,
            volume_transactions_24h: 0,
        },
        errors = {},
        flash = {},
        fournisseurs = [] as FournisseurItem[],
        kycUsers = [] as KycUser[],
        cnmciUsers = [],
        litiges = [] as LitigeItem[],
        missions = [] as AdminMission[],
        orders = [] as AdminOrder[],
        transactions = [] as AdminTransaction[],
        users = [] as AdminUser[],
        settingsList = [],
        evaluationsList = [] as AdminEvaluation[],
        artisansScores = [] as ArtisanScoreItem[],
        scoreLedger = [] as ScoreLedgerEntryItem[],
        navBadges = {} as NonNullable<AdminPageProps['navBadges']>,
        financialKpis = {} as any,
        promoCodes = [] as PromoCodeItem[],
        communications = [],
        adminNotifications = [] as AdminNotificationItem[],
        allNotifications = { data: [] } as any,
        sectors = [],
        rolesPermissions = {},
        allPermissions = [],
        auditLogs = undefined,
        auditActions = [],
        auditAdmins = [],
        usersPage = undefined,
        userStats = { total: 0, artisans_actifs: 0, clients_actifs: 0, fournisseurs_agrees: 0 },
        pendingFournisseurs = [],
        topArtisans: topArtisansProp = [],
        transactionsPage = undefined,
        transactionStats = { pending: 0, failed: 0, confirmed: 0, volume_24h: 0, escrow: 0, released: 0 },
        litigesPage = undefined,
        litigeStats = { open: 0, resolved: 0, high_risk: 0, missions_disputed: 0 },
        evaluationsPage = undefined,
        artisansScoresPage = undefined,
        evaluationStats = { evaluations_total: 0, note_moyenne: 0, artisans_suivis: 0, scores_geles: 0 },
        missionsPage = undefined,
        ordersPage = undefined,
        missionStats = { en_cours: 0, en_litige: 0, referent_required: 0, enrichies: 0 },
        deliveryStats = { total: 0, in_transit: 0, awaiting_driver: 0, delivered: 0, by_status: {} },
        kycUsersPage = undefined,
        pendingFournisseursList = [],
        kycStats = { pending: 0, artisans_pending: 0, fournisseurs_pending: 0, rejected: 0, registration_trend: [] },
        vitrineSlides = [],
        vitrineArtisanDuMois = [],
        vitrineArticles = [],
        vitrineVideos = [],
        vitrineFormations = [],
        vitrineRecrutements = [],
        vitrinePopups = [],
        vitrineSettings = [],
        contactMessages = [],
        observability = undefined,
    } = (pageProps || {}) as Partial<AdminPageProps>;

    // Capacités fines du backoffice (Chantier C6 / P2-10). `['*']` = accès total.
    const permissions = auth?.permissions ?? [];
    const canReviewKyc = can(permissions, 'admin.kyc.review');
    const canManageUsers = can(permissions, 'admin.users.manage');
    const canDeleteUsers = can(permissions, 'admin.users.delete');
    const canArbitrateLitiges = can(permissions, 'admin.litiges.arbitrate');
    const canViewRgpd = can(permissions, 'admin.rgpd.view');
    const canManageRgpd = can(permissions, 'admin.rgpd.manage');
    const canManageObservability = can(permissions, 'admin.observability.manage');
    const canImpersonate = can(permissions, 'admin.users.impersonate');

    const [missionSubTab, setMissionSubTab] = useState<'chantiers' | 'livraisons'>('chantiers');
    const [selectedOrderForDetails, setSelectedOrderForDetails] = useState<AdminOrder | null>(null);

    const [notificationsOpen, setNotificationsOpen] = useState<boolean>(false);
    const [notifFilter, setNotifFilter] = useState<'all' | 'unread' | 'alerts'>('all');

    const [notifTab, setNotifTab] = useState<'alerts' | 'history'>('alerts');
    const [searchNotif, setSearchNotif] = useState(new URLSearchParams(window.location.search).get('search_notification') || '');
    const [roleNotif, setRoleNotif] = useState(new URLSearchParams(window.location.search).get('role_notification') || '');
    const [typeNotif, setTypeNotif] = useState(new URLSearchParams(window.location.search).get('type_notification') || '');

    const liveNotifications = useMemo<AdminNotificationItem[]>(() => {
        const list: AdminNotificationItem[] = [];

        if (adminNotifications && Array.isArray(adminNotifications)) {
            list.push(...adminNotifications);
        }

        if (dashboard.kyc_en_attente > 0) {
            list.push({
                id: 'alert-kyc',
                type: 'kyc',
                title: 'Dossiers KYC en attente',
                body: `${dashboard.kyc_en_attente} dossier(s) KYC requièrent une validation administrative.`,
                read_at: null,
                created_at: new Date().toISOString(),
                action_url: tabRoutes.kyc,
                action_label: 'Vérifier KYC',
            });
        }
        if (dashboard.litiges_ouverts > 0) {
            list.push({
                id: 'alert-litiges',
                type: 'litige',
                title: 'Litiges ouverts',
                body: `${dashboard.litiges_ouverts} litige(s) en attente d'arbitrage ou d'intervention.`,
                read_at: null,
                created_at: new Date().toISOString(),
                action_url: tabRoutes.litiges,
                action_label: 'Arbitrer litiges',
            });
        }
        if (dashboard.recent_fraud_alerts > 0) {
            list.push({
                id: 'alert-fraud',
                type: 'fraud',
                title: 'Alertes Fraude / Écart GPS',
                body: `${dashboard.recent_fraud_alerts} anomalie(s) détectée(s) lors de scans J-Code ou d'interventions.`,
                read_at: null,
                created_at: new Date().toISOString(),
                action_url: tabRoutes.transactions,
                action_label: 'Vérifier fraudes',
            });
        }
        if (dashboard.referent_required_open > 0) {
            list.push({
                id: 'alert-referent',
                type: 'referent',
                title: 'Missions seuil Référent (> 2M)',
                body: `${dashboard.referent_required_open} mission(s) dépassent 2 000 000 FCFA et exigent un visa terrain.`,
                read_at: null,
                created_at: new Date().toISOString(),
                action_url: tabRoutes.missions,
                action_label: 'Consulter missions',
            });
        }

        return list;
    }, [adminNotifications, dashboard]);

    const unreadNotifsCount = useMemo(() => {
        return liveNotifications.filter((n) => !n.read_at).length;
    }, [liveNotifications]);

    const filteredNotifs = useMemo(() => {
        return liveNotifications.filter((n) => {
            if (notifFilter === 'unread') return !n.read_at;
            if (notifFilter === 'alerts') return ['kyc', 'litige', 'fraud', 'fraud_alert', 'referent'].includes(n.type);
            return true;
        });
    }, [liveNotifications, notifFilter]);

    const handleMarkAllNotifsRead = () => {
        router.post('/admin/notifications/mark-all-read', {}, { preserveScroll: true });
    };

    const handleMarkNotifRead = (notif: AdminNotificationItem) => {
        if (typeof notif.id === 'number') {
            router.post(`/admin/notifications/${notif.id}/read`, {}, { preserveScroll: true });
        }
        if (notif.action_url) {
            setNotificationsOpen(false);
            router.visit(notif.action_url);
        }
    };

    const handleFilterNotifications = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/notifications', {
            search_notification: searchNotif,
            role_notification: roleNotif,
            type_notification: typeNotif,
        }, {
            preserveState: true,
            preserveScroll: true,
            only: ['allNotifications'],
        });
    };

    const handleResetFilters = () => {
        setSearchNotif('');
        setRoleNotif('');
        setTypeNotif('');
        router.get('/admin/notifications', {}, {
            preserveState: true,
            preserveScroll: true,
            only: ['allNotifications'],
        });
    };

    // Journal d'audit (Chantier C3 / P0-4) — filtres serveur via rechargement partiel.
    const auditParams = new URLSearchParams(window.location.search);
    const [searchAudit, setSearchAudit] = useState(auditParams.get('search_audit') || '');
    const [actionAudit, setActionAudit] = useState(auditParams.get('action_audit') || '');
    const [adminAudit, setAdminAudit] = useState(auditParams.get('admin_audit') || '');
    const [dateFromAudit, setDateFromAudit] = useState(auditParams.get('date_from_audit') || '');
    const [dateToAudit, setDateToAudit] = useState(auditParams.get('date_to_audit') || '');

    const auditOnly = { only: ['auditLogs', 'auditActions', 'auditAdmins'], preserveState: true, preserveScroll: true };

    const handleFilterAudit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/audit-logs', {
            search_audit: searchAudit,
            action_audit: actionAudit,
            admin_audit: adminAudit,
            date_from_audit: dateFromAudit,
            date_to_audit: dateToAudit,
        }, auditOnly);
    };

    const handleResetAudit = () => {
        setSearchAudit('');
        setActionAudit('');
        setAdminAudit('');
        setDateFromAudit('');
        setDateToAudit('');
        router.get('/admin/audit-logs', {}, auditOnly);
    };

    // Utilisateurs (Chantier C4 / P1-6) — liste paginée + filtres serveur.
    const usersTable = useServerTable({
        path: '/admin/users',
        only: ['usersPage'],
        initial: { search_users: '', role_users: '', kyc_users: '' },
        storageKey: 'users',
    });

    // Transactions (Chantier C4 / P1-6) — journal financier paginé + filtres serveur.
    const txTable = useServerTable({
        path: '/admin/transactions',
        only: ['transactionsPage'],
        initial: { search_tx: '', status_tx: '', type_tx: '', provider_tx: '' },
        storageKey: 'transactions',
    });

    // Litiges (Chantier C4 / P1-6) — liste paginée + filtres serveur.
    const litigesTable = useServerTable({
        path: '/admin/litiges',
        only: ['litigesPage', 'litigeStats'],
        initial: { search_litige: '', statut_litige: '' },
        storageKey: 'litiges',
    });

    // Évaluations & scores (Chantier C4 / P1-6) — deux listes paginées + recherche.
    const evalTable = useServerTable({
        path: '/admin/evaluations',
        only: ['evaluationsPage', 'artisansScoresPage'],
        initial: { search_eval: '', search_score: '' },
        storageKey: 'evaluations',
    });

    // Missions (Chantier C4 / P1-6) — chantiers + livraisons paginés + filtres serveur.
    const missionsTable = useServerTable({
        path: '/admin/missions',
        only: ['missionsPage', 'ordersPage', 'missionStats', 'deliveryStats'],
        initial: { search_mission: '', search_order: '', status_order: '' },
        storageKey: 'missions',
    });

    // KYC (Chantier C4 / P1-6) — file paginée + recherche serveur.
    const kycTable = useServerTable({
        path: '/admin/kyc',
        only: ['kycUsersPage', 'kycStats'],
        initial: { search_kyc: '' },
        storageKey: 'kyc',
    });

    // Confirmations destructives normalisées + accessibles (Chantier C7 / P2-13).
    const { confirm: askConfirm, dialog: confirmDialog } = useConfirm();

    // Actions groupées (Chantier C5 / P1-9).
    const kycSelection = useRowSelection();
    const userSelection = useRowSelection();

    const handleBulkKyc = async (decision: 'approuve' | 'rejete') => {
        if (kycSelection.count === 0 || !canReviewKyc) return;
        let rejection_reason = '';
        if (decision === 'rejete') {
            const answer = await askConfirm({
                title: `Rejeter ${kycSelection.count} dossier(s) KYC`,
                tone: 'danger',
                confirmLabel: 'Rejeter',
                promptLabel: 'Motif de rejet (10 caractères min.)',
                promptMinLength: 10,
            });
            if (answer === false) return;
            rejection_reason = String(answer);
        } else {
            const ok = await askConfirm({
                title: `Approuver ${kycSelection.count} dossier(s) KYC ?`,
                confirmLabel: 'Approuver',
            });
            if (!ok) return;
        }
        setActionLoading(true);
        router.post('/admin/kyc/bulk-review', { user_ids: kycSelection.ids, decision, rejection_reason }, {
            preserveScroll: true,
            onSuccess: () => kycSelection.clear(),
            onFinish: () => setActionLoading(false),
        });
    };

    const handleBulkUserStatus = async (account_status: 'actif' | 'suspendu') => {
        if (userSelection.count === 0 || !canManageUsers) return;
        let account_status_reason = '';
        if (account_status === 'suspendu') {
            const answer = await askConfirm({
                title: `Suspendre ${userSelection.count} compte(s)`,
                tone: 'danger',
                confirmLabel: 'Suspendre',
                promptLabel: 'Motif de suspension (optionnel)',
                promptOptional: true,
            });
            if (answer === false) return;
            account_status_reason = String(answer);
        } else {
            const ok = await askConfirm({
                title: `Réactiver ${userSelection.count} compte(s) ?`,
                confirmLabel: 'Réactiver',
            });
            if (!ok) return;
        }
        setActionLoading(true);
        router.post('/admin/users/bulk-status', { user_ids: userSelection.ids, account_status, account_status_reason }, {
            preserveScroll: true,
            onSuccess: () => userSelection.clear(),
            onFinish: () => setActionLoading(false),
        });
    };

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
    const [expandedSectors, setExpandedSectors] = useState<Record<number, boolean>>({});

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
    const [selectedUserForRgpd, setSelectedUserForRgpd] = useState<AdminUser | null>(null);

    const [commModalOpen, setCommModalOpen] = useState<boolean>(false);
    const [editingComm, setEditingComm] = useState<any>(null);
    const [commTypeFilter, setCommTypeFilter] = useState<string>('all');
    const [commStatusFilter, setCommStatusFilter] = useState<string>('all');
    const commForm = useForm({
        type: 'annonce',
        titre: '',
        contenu: '',
        cibles: [] as string[],
    });

    const filteredCommunications = useMemo(() => {
        if (!communications) return [];
        return communications.filter((comm: any) => {
            const matchesSearch = !deferredSearch || 
                comm.titre.toLowerCase().includes(deferredSearch) || 
                comm.contenu.toLowerCase().includes(deferredSearch);
            const matchesType = commTypeFilter === 'all' || comm.type === commTypeFilter;
            const matchesStatus = commStatusFilter === 'all' || comm.statut === commStatusFilter;
            return matchesSearch && matchesType && matchesStatus;
        });
    }, [communications, deferredSearch, commTypeFilter, commStatusFilter]);

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
        if (auth?.user?.phone === user.phone || auth?.user?.email === user.email) {
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

    const handleDeleteUser = async (user: AdminUser): Promise<void> => {
        if (!canDeleteUsers) return;
        if (auth?.user?.phone === user.phone || auth?.user?.email === user.email) {
            await askConfirm({
                title: 'Action impossible',
                message: 'Vous ne pouvez pas supprimer votre propre compte.',
                confirmLabel: 'Compris',
                cancelLabel: 'Fermer',
            });
            return;
        }

        const ok = await askConfirm({
            title: `Supprimer ${user.name} ?`,
            message: 'Cette action est irréversible.',
            tone: 'danger',
            confirmLabel: 'Supprimer',
        });
        if (!ok) return;
        router.delete(`/admin/users/${user.id}`, { preserveScroll: true });
    };

    const handleImpersonate = async (user: AdminUser): Promise<void> => {
        if (!canImpersonate) return;
        const ok = await askConfirm({
            title: `Se connecter en tant que ${user.name} ?`,
            message: 'Votre session basculera sur ce compte. Un bandeau permettra de revenir à votre compte administrateur.',
            confirmLabel: 'Usurper la session',
        });
        if (!ok) return;
        router.post(`/admin/users/${user.id}/impersonate`);
    };

    const handleAnonymizeUser = async (user: AdminUser): Promise<void> => {
        if (!canManageRgpd) return;
        const ok = await askConfirm({
            title: `Anonymiser le compte #${user.id}`,
            message: `Anonymisation RGPD irréversible de ${user.name}. Toutes les données personnelles seront expurgées.`,
            tone: 'danger',
            confirmLabel: 'Anonymiser',
            requireText: 'ANONYMISER',
        });
        if (!ok) return;
        setActionLoading(true);
        router.post(`/admin/users/${user.id}/anonymize`, {}, {
            preserveScroll: true,
            onSuccess: () => setSelectedUserForRgpd(null),
            onFinish: () => setActionLoading(false),
        });
    };

    const handleRetryFailedJobs = async (): Promise<void> => {
        if (!canManageObservability) return;
        const ok = await askConfirm({ title: 'Relancer tous les jobs en échec ?', confirmLabel: 'Relancer' });
        if (!ok) return;
        setActionLoading(true);
        router.post('/admin/observability/retry-failed-jobs', {}, {
            preserveScroll: true,
            onFinish: () => setActionLoading(false),
        });
    };

    const handleFlushFailedJobs = async (): Promise<void> => {
        if (!canManageObservability) return;
        const ok = await askConfirm({
            title: 'Purger la file des jobs en échec ?',
            message: 'Les jobs en échec seront définitivement supprimés.',
            tone: 'danger',
            confirmLabel: 'Purger',
        });
        if (!ok) return;
        setActionLoading(true);
        router.post('/admin/observability/flush-failed-jobs', {}, {
            preserveScroll: true,
            onFinish: () => setActionLoading(false),
        });
    };

    const openCreateCommModal = (): void => {
        setEditingComm(null);
        commForm.reset();
        commForm.clearErrors();
        commForm.setData({
            type: 'annonce',
            titre: '',
            contenu: '',
            cibles: [],
        });
        setCommModalOpen(true);
    };

    const openEditCommModal = (comm: any): void => {
        setEditingComm(comm);
        commForm.clearErrors();
        commForm.setData({
            type: comm.type,
            titre: comm.titre,
            contenu: comm.contenu,
            cibles: comm.cibles_json,
        });
        setCommModalOpen(true);
    };

    const handleCommSubmit = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        if (commForm.processing) return;
        if (editingComm) {
            commForm.put(`/admin/communications/${editingComm.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    setCommModalOpen(false);
                    commForm.reset();
                },
            });
        } else {
            commForm.post('/admin/communications', {
                preserveScroll: true,
                onSuccess: () => {
                    setCommModalOpen(false);
                    commForm.reset();
                },
            });
        }
    };

    const [promoModalOpen, setPromoModalOpen] = useState<boolean>(false);
    const [editingPromo, setEditingPromo] = useState<PromoCodeItem | null>(null);

    const promoForm = useForm({
        code: '',
        description: '',
        discount_type: 'percent' as 'percent' | 'fixed',
        discount_value: 10,
        min_order_amount: 0,
        max_discount_amount: 0,
        usage_limit: 0,
        starts_at: '',
        expires_at: '',
        is_active: true,
    });

    const openCreatePromoModal = (): void => {
        setEditingPromo(null);
        promoForm.reset();
        promoForm.clearErrors();
        promoForm.setData({
            code: '',
            description: '',
            discount_type: 'percent',
            discount_value: 10,
            min_order_amount: 0,
            max_discount_amount: 0,
            usage_limit: 0,
            starts_at: '',
            expires_at: '',
            is_active: true,
        });
        setPromoModalOpen(true);
    };

    const openEditPromoModal = (promo: PromoCodeItem): void => {
        setEditingPromo(promo);
        promoForm.clearErrors();
        promoForm.setData({
            code: promo.code,
            description: promo.description || '',
            discount_type: promo.discount_type,
            discount_value: promo.discount_value,
            min_order_amount: promo.min_order_amount || 0,
            max_discount_amount: promo.max_discount_amount || 0,
            usage_limit: promo.usage_limit || 0,
            starts_at: promo.starts_at ? promo.starts_at.slice(0, 10) : '',
            expires_at: promo.expires_at ? promo.expires_at.slice(0, 10) : '',
            is_active: promo.is_active,
        });
        setPromoModalOpen(true);
    };

    const handlePromoSubmit = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        if (promoForm.processing) return;

        promoForm.transform(() => ({
            ...promoForm.data,
            code: promoForm.data.code.trim().toUpperCase(),
            min_order_amount: Number(promoForm.data.min_order_amount) || 0,
            max_discount_amount: Number(promoForm.data.max_discount_amount) || null,
            usage_limit: Number(promoForm.data.usage_limit) || null,
            starts_at: promoForm.data.starts_at || null,
            expires_at: promoForm.data.expires_at || null,
        }));

        if (editingPromo) {
            promoForm.put(`/admin/promo-codes/${editingPromo.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    setPromoModalOpen(false);
                    promoForm.reset();
                },
            });
        } else {
            promoForm.post('/admin/promo-codes', {
                preserveScroll: true,
                onSuccess: () => {
                    setPromoModalOpen(false);
                    promoForm.reset();
                },
            });
        }
    };

    const handleDeletePromo = (promo: PromoCodeItem): void => {
        if (window.confirm(`Supprimer définitivement le code promo "${promo.code}" ?`)) {
            router.delete(`/admin/promo-codes/${promo.id}`, {
                preserveScroll: true,
            });
        }
    };

    const handleTogglePromo = (promo: PromoCodeItem): void => {
        router.post(`/admin/promo-codes/${promo.id}/toggle`, {}, {
            preserveScroll: true,
        });
    };

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        localStorage.setItem('prosartisan_admin_theme', themeMode);
    }, [themeMode]);

    const analytics = useAdminAnalytics({
        dashboard,
        deferredSearch,
        fournisseurs,
        kycUsers,
        litiges,
        missions,
        orders,
        deliveryStatusFilter: 'all',
        transactions,
        users,
        evaluationsList,
        artisansScores,
        promoCodes,
        now,
    });

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

    const navigation: NavigationGroup[] = ([
        {
            label: 'Pilotage',
            items: [
                { id: 'dashboard', label: tabMeta.dashboard.label },
                { count: kycPending, id: 'kyc', label: tabMeta.kyc.label },
                { count: missionsInProgress, id: 'missions', label: tabMeta.missions.label },
                { count: openDisputes, id: 'litiges', label: tabMeta.litiges.label },
                { count: unreadNotifsCount, id: 'notifications', label: 'Notifications' },
                { id: 'llm_admin', label: 'Administration LLM' },
                { id: 'ai_dashboard', label: 'Suivi & Coûts IA' },
            ],
        },
        {
            label: 'Réseau',
            items: [
                { count: totalUsers, id: 'users', label: tabMeta.users.label },
                { id: 'evaluations', label: tabMeta.evaluations.label },
                { count: navBadges.transactions_en_attente ?? analytics.pendingTransactions.length, id: 'transactions', label: tabMeta.transactions.label },
            ],
        },
        {
            label: 'Plateforme',
            items: [
                { id: 'settings', label: tabMeta.settings.label },
                { id: 'roles_permissions', label: tabMeta.roles_permissions.label },
                { id: 'audit_logs', label: tabMeta.audit_logs.label },
                { id: 'observability', label: tabMeta.observability.label },
                { count: navBadges.communications_publiees ?? (communications ?? []).filter(c => c.statut === 'publie').length, id: 'communications', label: tabMeta.communications.label },
                { count: navBadges.promo_codes_actifs ?? (promoCodes ?? []).filter(p => p.is_active).length, id: 'promo_codes', label: 'Codes Promo' },
                { count: navBadges.contact_messages_nouveaux ?? (contactMessages ?? []).filter(c => c.statut === 'nouveau').length, id: 'vitrine', label: tabMeta.vitrine.label },
            ],
        },
    ] as NavigationGroup[])
        .map((group) => ({ ...group, items: group.items.filter((item) => canOpenTab(permissions, item.id)) }))
        .filter((group) => group.items.length > 0);

    const heroStats = useMemo(() => {
        switch (activeTab) {
            case 'promo_codes':
                return [
                    { label: 'Codes Promo Total', tone: 'amber' as const, value: `${(promoCodes ?? []).length}` },
                    { label: 'Codes Actifs', tone: 'green' as const, value: `${(promoCodes ?? []).filter(p => p.is_active).length}` },
                    { label: 'Utilisations Totales', tone: 'blue' as const, value: `${(promoCodes ?? []).reduce((sum, p) => sum + (p.used_count || 0), 0)}` },
                    { label: 'Campagnes Fixes & %', tone: 'slate' as const, value: `${(promoCodes ?? []).filter(p => p.discount_type === 'percent').length} % / ${(promoCodes ?? []).filter(p => p.discount_type === 'fixed').length} Fixe` },
                ];
            case 'notifications':
                return [
                    { label: 'Alertes globales', tone: 'amber' as const, value: `${liveNotifications.length}` },
                    { label: 'Non lues', tone: 'rose' as const, value: `${unreadNotifsCount}` },
                    { label: 'Dossiers KYC', tone: 'blue' as const, value: `${dashboard.kyc_en_attente ?? 0}` },
                    { label: 'Litiges ouverts', tone: 'green' as const, value: `${dashboard.litiges_ouverts ?? 0}` },
                ];
            case 'dashboard':
                return [
                    { label: 'Utilisateurs', tone: 'amber' as const, value: numberFormat.format(totalUsers) },
                    { label: 'Missions en cours', tone: 'green' as const, value: numberFormat.format(missionsInProgress) },
                    { label: 'KYC à valider', tone: 'blue' as const, value: numberFormat.format(kycPending) },
                    { label: 'Volume 24h', tone: 'slate' as const, value: money(volume24h) },
                ];
            case 'kyc':
                return [
                    { label: 'Dossiers ouverts', tone: 'amber' as const, value: numberFormat.format(kycStats.pending || kycPending) },
                    { label: 'Artisans prioritaires', tone: 'green' as const, value: numberFormat.format(kycStats.artisans_pending) },
                    { label: 'Fournisseurs à valider', tone: 'blue' as const, value: numberFormat.format(kycStats.fournisseurs_pending) },
                    { label: 'Rejets récents', tone: 'rose' as const, value: numberFormat.format(kycStats.rejected) },
                ];
            case 'missions':
                return [
                    { label: 'Missions en cours', tone: 'green' as const, value: numberFormat.format(missionStats.en_cours || missionsInProgress) },
                    { label: 'En litige', tone: 'rose' as const, value: numberFormat.format(missionStats.en_litige) },
                    { label: 'Référent requis', tone: 'amber' as const, value: numberFormat.format(missionStats.referent_required || referentRequired) },
                    { label: 'Livraisons actives', tone: 'slate' as const, value: numberFormat.format(deliveryStats.in_transit + deliveryStats.awaiting_driver) },
                ];
            case 'litiges':
                return [
                    { label: 'Litiges ouverts', tone: 'rose' as const, value: numberFormat.format(litigeStats.open || openDisputes) },
                    { label: 'Haute priorité', tone: 'amber' as const, value: numberFormat.format(litigeStats.high_risk) },
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
                    { label: 'Volume 24h', tone: 'amber' as const, value: money(transactionStats.volume_24h || volume24h) },
                    { label: 'En attente', tone: 'blue' as const, value: numberFormat.format(transactionStats.pending) },
                    { label: 'Échouées', tone: 'rose' as const, value: numberFormat.format(transactionStats.failed) },
                    { label: 'Fonds libérés', tone: 'green' as const, value: money(transactionStats.released) },
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
                    { label: 'Actions système', tone: 'green' as const, value: `${allPermissions?.length ?? 0} Actions` },
                    { label: 'Sécurité d\'accès', tone: 'blue' as const, value: 'RBAC Actif' },
                    { label: 'Mode', tone: 'slate' as const, value: 'Cache Actif' },
                ];
            case 'evaluations':
                return [
                    { label: 'Évaluations', tone: 'amber' as const, value: numberFormat.format(evaluationStats.evaluations_total) },
                    { label: 'Note moyenne', tone: 'green' as const, value: evaluationStats.evaluations_total > 0 ? `${evaluationStats.note_moyenne} / 5` : 'N/A' },
                    { label: 'Artisans suivis', tone: 'blue' as const, value: numberFormat.format(evaluationStats.artisans_suivis) },
                    { label: 'Scores gelés', tone: 'rose' as const, value: numberFormat.format(evaluationStats.scores_geles) },
                ];
            case 'communications': {
                const comms = communications ?? [];
                return [
                    { label: 'Total', tone: 'amber' as const, value: numberFormat.format(comms.length) },
                    { label: 'Publi\u00e9es', tone: 'green' as const, value: numberFormat.format(comms.filter(c => c.statut === 'publie').length) },
                    { label: 'Brouillons', tone: 'blue' as const, value: numberFormat.format(comms.filter(c => c.statut === 'brouillon').length) },
                    { label: 'Cl\u00f4tur\u00e9es', tone: 'slate' as const, value: numberFormat.format(comms.filter(c => c.statut === 'cloture').length) },
                ];
            }
            case 'vitrine': {
                const newContacts = (contactMessages ?? []).filter(c => c.statut === 'nouveau').length;
                return [
                    { label: 'Demandes Contact', tone: newContacts > 0 ? ('rose' as const) : ('amber' as const), value: `${newContacts} Nouvelle${newContacts > 1 ? 's' : ''}` },
                    { label: 'Articles & Actus', tone: 'green' as const, value: `${(vitrineArticles ?? []).length}` },
                    { label: 'Formations & Slides', tone: 'blue' as const, value: `${(vitrineFormations ?? []).length} / ${(vitrineSlides ?? []).length}` },
                    { label: 'Vidéos & Recrut.', tone: 'slate' as const, value: `${(vitrineVideos ?? []).length} / ${(vitrineRecrutements ?? []).length}` },
                ];
            }
            case 'audit_logs': {
                const logs = auditLogs?.data ?? [];
                const failures = logs.filter((l) => l.action.includes('failed') || l.action.includes('denied')).length;
                return [
                    { label: 'Entrées (total)', tone: 'amber' as const, value: numberFormat.format(auditLogs?.total ?? 0) },
                    { label: 'Page courante', tone: 'blue' as const, value: `${auditLogs?.current_page ?? 1} / ${auditLogs?.last_page ?? 1}` },
                    { label: 'Admins actifs', tone: 'green' as const, value: numberFormat.format(auditAdmins.length) },
                    { label: 'Échecs de connexion (page)', tone: failures > 0 ? ('rose' as const) : ('slate' as const), value: numberFormat.format(failures) },
                ];
            }
            case 'observability': {
                if (!observability) return [];
                return [
                    { label: 'Jobs en échec', tone: observability.queue.failed > 0 ? ('rose' as const) : ('green' as const), value: numberFormat.format(observability.queue.failed) },
                    { label: 'Paiements KO (24 h)', tone: observability.payments.failed_24h > 0 ? ('rose' as const) : ('green' as const), value: numberFormat.format(observability.payments.failed_24h) },
                    { label: 'Fraude GPS (7 j)', tone: observability.fraud.gps_attempts_7d > 0 ? ('amber' as const) : ('green' as const), value: numberFormat.format(observability.fraud.gps_attempts_7d) },
                    { label: 'Bloquées Référent', tone: observability.referent.blocked > 0 ? ('amber' as const) : ('green' as const), value: numberFormat.format(observability.referent.blocked) },
                ];
            }
            default:
                return [];
        }
    }, [
        activeTab,
        auditLogs,
        observability,
        auditAdmins.length,
        transactionStats,
        litigeStats,
        evaluationStats,
        missionStats,
        deliveryStats,
        kycStats,
        artisansActifs,
        clientsActifs,
        dashboard.kyc_en_attente,
        dashboard.litiges_ouverts,
        fournisseursAgrees,
        fraudAlerts,
        kycPending,
        liveNotifications.length,
        missionsInProgress,
        openDisputes,
        promoCodes,
        referentRequired,
        totalUsers,
        unreadNotifsCount,
        volume24h,
        allPermissions?.length,
        communications,
        vitrineSlides,
        vitrineArticles,
        vitrineFormations,
        vitrineVideos,
        vitrineRecrutements,
        contactMessages,
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

    const handleCnmciDecision = (userId: number, decision: 'valide' | 'rejete'): void => {
        if (window.confirm(`Confirmer la décision : ${decision === 'valide' ? 'Valider' : 'Rejeter'} cette affiliation CNMCI ?`)) {
            submitAction(`/admin/kyc/${userId}/cnmci-review`, { decision });
        }
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

    const renderPagination = (links: any[], only: string[] = ['allNotifications']) => {
        if (!links || links.length <= 3) return null;
        return (
            <div className="flex justify-center gap-1.5 mt-5">
                {links.map((link: any, idx: number) => (
                    <Link
                        key={idx}
                        href={link.url || '#'}
                        className={cn(
                            "px-3 py-1.5 rounded-xl text-xs font-semibold border transition",
                            link.active
                                ? "bg-[#ebb95e] border-[#ebb95e] text-[#241b16]"
                                : "border-[var(--admin-border)] text-[var(--admin-text-soft)] hover:bg-white/40",
                            !link.url && "opacity-50 cursor-not-allowed"
                        )}
                        only={only}
                        preserveScroll
                        preserveState
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ))}
            </div>
        );
    };

    const adminName = auth?.user?.name ?? 'Admin ProsArtisan';
    const adminContact = auth?.user?.email ?? auth?.user?.phone ?? 'Administrateur';

    return (
        <AdminShell
            activeTab={activeTab}
            navigation={navigation}
            heroStats={heroStats}
            themeMode={themeMode}
            onToggleTheme={() => setThemeMode((current) => (current === 'light' ? 'dark' : 'light'))}
            isMobileSidebarOpen={isMobileSidebarOpen}
            onMobileSidebarChange={setIsMobileSidebarOpen}
            search={search}
            onSearchChange={setSearch}
            onRefresh={refreshData}
            refreshing={refreshing}
            actionLoading={actionLoading}
            offlineActive={offlineActive}
            exchangeRates={exchangeRates}
            notificationsOpen={notificationsOpen}
            onNotificationsOpenChange={setNotificationsOpen}
            notifFilter={notifFilter}
            onNotifFilterChange={setNotifFilter}
            liveNotifications={liveNotifications}
            filteredNotifs={filteredNotifs}
            unreadNotifsCount={unreadNotifsCount}
            onMarkAllNotifsRead={handleMarkAllNotifsRead}
            onMarkNotifRead={handleMarkNotifRead}
            adminName={adminName}
            adminContact={adminContact}
            flash={flash}
            bannerError={bannerError}
        >
                            {activeTab === 'dashboard' ? (
                                <DashboardPanel
                                    summaryCards={summaryCards}
                                    acompteTrend={analytics.acompteTrend}
                                    releaseTrend={analytics.releaseTrend}
                                    activityTrend={analytics.activityTrend}
                                    urgentKyc={analytics.urgentKyc}
                                    recentActivity={analytics.recentActivity}
                                    escrowAmount={analytics.escrowAmount}
                                    releasedAmount={analytics.releasedAmount}
                                    topArtisans={analytics.topArtisans}
                                />
                            ) : null}

                            {activeTab === 'kyc' ? (
                                <KycPanel
                                    kycUsersPage={kycUsersPage}
                                    pendingFournisseursList={pendingFournisseursList}
                                    kycStats={kycStats}
                                    cnmciUsers={cnmciUsers}
                                    search={kycTable.filters.search_kyc}
                                    onSearchChange={(v) => kycTable.set('search_kyc', v)}
                                    onSubmit={kycTable.apply}
                                    onReset={kycTable.reset}
                                    renderPagination={(links) => renderPagination(links as any[], ['kycUsersPage', 'kycStats'])}
                                    actionLoading={actionLoading}
                                    isSelected={kycSelection.isSelected}
                                    selectionCount={kycSelection.count}
                                    onToggleRow={kycSelection.toggle}
                                    onToggleAll={kycSelection.toggleAll}
                                    onClearSelection={kycSelection.clear}
                                    onBulkKyc={handleBulkKyc}
                                    onKycDecision={handleKycDecision}
                                    onFournisseurDecision={handleFournisseurDecision}
                                    onCnmciDecision={handleCnmciDecision}
                                    canReview={canReviewKyc}
                                    canReviewFournisseurs={can(permissions, 'admin.fournisseurs.review')}
                                />
                            ) : null}

                            {activeTab === 'missions' ? (
                                <MissionsPanel
                                    missionSubTab={missionSubTab}
                                    onMissionSubTabChange={setMissionSubTab}
                                    deliveryStatusFilter={missionsTable.filters.status_order || 'all'}
                                    onDeliveryStatusFilterChange={(id) => missionsTable.applyWith('status_order', id === 'all' ? '' : id)}
                                    missionsPage={missionsPage}
                                    ordersPage={ordersPage}
                                    missionStats={missionStats}
                                    deliveryStats={deliveryStats}
                                    missionSearch={missionsTable.filters.search_mission}
                                    onMissionSearchChange={(v) => missionsTable.set('search_mission', v)}
                                    onMissionSubmit={missionsTable.apply}
                                    orderSearch={missionsTable.filters.search_order}
                                    onOrderSearchChange={(v) => missionsTable.set('search_order', v)}
                                    onOrderSubmit={missionsTable.apply}
                                    onResetFilters={missionsTable.reset}
                                    exportParams={missionsTable.filters}
                                    renderMissionPagination={(links) => renderPagination(links as any[], ['missionsPage'])}
                                    renderOrderPagination={(links) => renderPagination(links as any[], ['ordersPage'])}
                                    onSelectMission={setSelectedMissionForDetails}
                                    onSelectOrder={setSelectedOrderForDetails}
                                />
                            ) : null}

                            {activeTab === 'litiges' ? (
                                <LitigesPanel
                                    litigesPage={litigesPage}
                                    litigeStats={litigeStats}
                                    fraudAlerts={fraudAlerts}
                                    search={litigesTable.filters.search_litige}
                                    onSearchChange={(v) => litigesTable.set('search_litige', v)}
                                    statusFilter={litigesTable.filters.statut_litige}
                                    onStatusFilterChange={(v) => litigesTable.set('statut_litige', v)}
                                    onSubmit={litigesTable.apply}
                                    onReset={litigesTable.reset}
                                    exportParams={litigesTable.filters}
                                    renderPagination={(links) => renderPagination(links as any[], ['litigesPage', 'litigeStats'])}
                                    actionLoading={actionLoading}
                                    onDecision={handleLitigeDecision}
                                    canArbitrate={canArbitrateLitiges}
                                />
                            ) : null}

                            {activeTab === 'users' ? (
                                <UsersPanel
                                    users={usersPage}
                                    userStats={userStats}
                                    pendingFournisseurs={pendingFournisseurs}
                                    topArtisans={topArtisansProp}
                                    search={usersTable.filters.search_users}
                                    onSearchChange={(v) => usersTable.set('search_users', v)}
                                    roleFilter={usersTable.filters.role_users}
                                    onRoleFilterChange={(v) => usersTable.set('role_users', v)}
                                    kycFilter={usersTable.filters.kyc_users}
                                    onKycFilterChange={(v) => usersTable.set('kyc_users', v)}
                                    onSubmit={usersTable.apply}
                                    onReset={usersTable.reset}
                                    exportParams={usersTable.filters}
                                    renderPagination={(links) => renderPagination(links as any[], ['usersPage'])}
                                    actionLoading={actionLoading}
                                    isSelected={userSelection.isSelected}
                                    selectionCount={userSelection.count}
                                    onToggleRow={userSelection.toggle}
                                    onToggleAll={userSelection.toggleAll}
                                    onClearSelection={userSelection.clear}
                                    onBulkStatus={handleBulkUserStatus}
                                    onCreateUser={openCreateUserModal}
                                    onEditUser={openEditUserModal}
                                    onToggleUserStatus={handleToggleUserStatus}
                                    onDeleteUser={handleDeleteUser}
                                    onFournisseurDecision={handleFournisseurDecision}
                                    canManage={canManageUsers}
                                    canDelete={canDeleteUsers}
                                    canReviewFournisseurs={can(permissions, 'admin.fournisseurs.review')}
                                    canViewRgpd={canViewRgpd}
                                    onOpenRgpd={setSelectedUserForRgpd}
                                    canImpersonate={canImpersonate}
                                    onImpersonate={handleImpersonate}
                                />
                            ) : null}

                            {activeTab === 'transactions' ? (
                                <TransactionsPanel
                                    financialKpis={financialKpis}
                                    transactionStats={transactionStats}
                                    transactionsPage={transactionsPage}
                                    search={txTable.filters.search_tx}
                                    onSearchChange={(v) => txTable.set('search_tx', v)}
                                    statusFilter={txTable.filters.status_tx}
                                    onStatusFilterChange={(v) => txTable.set('status_tx', v)}
                                    typeFilter={txTable.filters.type_tx}
                                    onTypeFilterChange={(v) => txTable.set('type_tx', v)}
                                    providerFilter={txTable.filters.provider_tx}
                                    onProviderFilterChange={(v) => txTable.set('provider_tx', v)}
                                    onSubmit={txTable.apply}
                                    onReset={txTable.reset}
                                    exportParams={txTable.filters}
                                    renderPagination={(links) => renderPagination(links as any[], ['transactionsPage'])}
                                    onSelectTransaction={setSelectedTransactionForDetails}
                                />
                            ) : null}

                            {activeTab === 'settings' ? (
                                <SettingsPanel
                                    adminName={adminName}
                                    adminContact={adminContact}
                                    offlineActive={offlineActive}
                                    isOfflineSimulated={isOfflineSimulated}
                                    onToggleOfflineSimulated={() => setIsOfflineSimulated((prev) => !prev)}
                                    onRefresh={refreshData}
                                    settingsList={settingsList}
                                    sectors={sectors}
                                    expandedSectors={expandedSectors}
                                    onToggleSector={(sectorId) => setExpandedSectors((prev) => ({ ...prev, [sectorId]: !prev[sectorId] }))}
                                />
                            ) : null}

                            {activeTab === 'notifications' ? (
                                <NotificationsPanel
                                    notifTab={notifTab}
                                    onNotifTabChange={setNotifTab}
                                    liveNotificationsCount={liveNotifications.length}
                                    unreadNotifsCount={unreadNotifsCount}
                                    notifFilter={notifFilter}
                                    onNotifFilterChange={setNotifFilter}
                                    filteredNotifs={filteredNotifs}
                                    onMarkAllRead={handleMarkAllNotifsRead}
                                    onMarkNotifRead={handleMarkNotifRead}
                                    searchNotif={searchNotif}
                                    onSearchNotifChange={setSearchNotif}
                                    roleNotif={roleNotif}
                                    onRoleNotifChange={setRoleNotif}
                                    typeNotif={typeNotif}
                                    onTypeNotifChange={setTypeNotif}
                                    onFilterSubmit={handleFilterNotifications}
                                    onFilterReset={handleResetFilters}
                                    allNotifications={allNotifications}
                                    renderPagination={renderPagination}
                                />
                            ) : null}

                            {activeTab === 'roles_permissions' ? (
                                <section className="mt-5">
                                    <RolesPermissionsPanel
                                        allPermissions={allPermissions ?? []}
                                        rolesPermissions={rolesPermissions ?? {}}
                                        adminCapabilityCatalog={pageProps.adminCapabilityCatalog ?? {}}
                                        admins={pageProps.admins ?? []}
                                    />
                                </section>
                            ) : null}

                            {activeTab === 'audit_logs' ? (
                                <AuditLogsPanel
                                    auditLogs={auditLogs}
                                    auditActions={auditActions}
                                    auditAdmins={auditAdmins}
                                    search={searchAudit}
                                    onSearchChange={setSearchAudit}
                                    actionFilter={actionAudit}
                                    onActionFilterChange={setActionAudit}
                                    adminFilter={adminAudit}
                                    onAdminFilterChange={setAdminAudit}
                                    dateFrom={dateFromAudit}
                                    onDateFromChange={setDateFromAudit}
                                    dateTo={dateToAudit}
                                    onDateToChange={setDateToAudit}
                                    onSubmit={handleFilterAudit}
                                    onReset={handleResetAudit}
                                    renderPagination={(links) => renderPagination(links as any[], ['auditLogs'])}
                                />
                            ) : null}

                            {activeTab === 'observability' && observability ? (
                                <ObservabilityPanel
                                    snapshot={observability}
                                    canManage={canManageObservability}
                                    actionLoading={actionLoading}
                                    onRetryJobs={handleRetryFailedJobs}
                                    onFlushJobs={handleFlushFailedJobs}
                                />
                            ) : null}

                            {activeTab === 'llm_admin' ? (
                                <section className="mt-5">
                                    <LlmAdminPanel />
                                </section>
                            ) : null}

                            {activeTab === 'ai_dashboard' ? (
                                <section className="mt-5">
                                    <AiDashboardPanel
                                        stats={(pageProps as any).stats}
                                        costsByModel={(pageProps as any).costsByModel}
                                        dailyUsage={(pageProps as any).dailyUsage}
                                        logs={(pageProps as any).logs}
                                        settings={(pageProps as any).settings}
                                    />
                                </section>
                            ) : null}

                            {activeTab === 'evaluations' ? (
                                <EvaluationsPanel
                                    evalSubTab={evalSubTab}
                                    onEvalSubTabChange={setEvalSubTab}
                                    evaluationsPage={evaluationsPage}
                                    artisansScoresPage={artisansScoresPage}
                                    evaluationStats={evaluationStats}
                                    evalSearch={evalTable.filters.search_eval}
                                    onEvalSearchChange={(v) => evalTable.set('search_eval', v)}
                                    onEvalSubmit={evalTable.apply}
                                    scoreSearch={evalTable.filters.search_score}
                                    onScoreSearchChange={(v) => evalTable.set('search_score', v)}
                                    onScoreSubmit={evalTable.apply}
                                    onResetFilters={evalTable.reset}
                                    exportParams={{ search_eval: evalTable.filters.search_eval }}
                                    renderEvalPagination={(links) => renderPagination(links as any[], ['evaluationsPage', 'artisansScoresPage'])}
                                    renderScorePagination={(links) => renderPagination(links as any[], ['evaluationsPage', 'artisansScoresPage'])}
                                    onSelectArtisanLedger={setSelectedArtisanForLedger}
                                    onToggleScoreFreeze={handleToggleScoreFreeze}
                                />
                            ) : null}

                            {activeTab === 'communications' ? (
                                <CommunicationsPanel
                                    communications={communications}
                                    filteredCommunications={filteredCommunications}
                                    search={search}
                                    onSearchChange={setSearch}
                                    commTypeFilter={commTypeFilter}
                                    onCommTypeFilterChange={setCommTypeFilter}
                                    commStatusFilter={commStatusFilter}
                                    onCommStatusFilterChange={setCommStatusFilter}
                                    onCreate={openCreateCommModal}
                                    onEdit={openEditCommModal}
                                />
                            ) : null}

                            {activeTab === 'promo_codes' ? (
                                <PromoCodesPanel
                                    filteredPromoCodes={analytics.filteredPromoCodes}
                                    onCreate={openCreatePromoModal}
                                    onEdit={openEditPromoModal}
                                    onToggle={handleTogglePromo}
                                    onDelete={handleDeletePromo}
                                />
                            ) : null}

                            {activeTab === 'vitrine' ? (
                                <section className="mt-5">
                                    <VitrinePanel
                                        vitrineSlides={vitrineSlides}
                                        vitrineArtisanDuMois={vitrineArtisanDuMois}
                                        vitrineArticles={vitrineArticles}
                                        vitrineVideos={vitrineVideos}
                                        vitrineFormations={vitrineFormations}
                                        vitrineRecrutements={vitrineRecrutements}
                                        vitrinePopups={vitrinePopups}
                                        vitrineSettings={vitrineSettings}
                                        contactMessages={contactMessages}
                                        users={users}
                                    />
                                </section>
                            ) : null}
                {commModalOpen && (
                    <CommunicationFormModal
                        form={commForm}
                        editing={editingComm}
                        adminName={auth?.user?.name ?? ''}
                        onSubmit={handleCommSubmit}
                        onClose={() => setCommModalOpen(false)}
                    />
                )}

                {promoModalOpen && (
                    <PromoCodeFormModal
                        form={promoForm}
                        editing={editingPromo}
                        onSubmit={handlePromoSubmit}
                        onClose={() => setPromoModalOpen(false)}
                    />
                )}

                {userModalOpen && (
                    <UserFormModal
                        form={userForm}
                        editing={editingUser}
                        onSubmit={handleUserFormSubmit}
                        onClose={() => setUserModalOpen(false)}
                    />
                )}

                {statusModalOpen && statusTargetUser && (
                    <StatusFormModal
                        form={statusForm}
                        targetUser={statusTargetUser}
                        onSubmit={handleStatusSubmit}
                        onClose={() => setStatusModalOpen(false)}
                    />
                )}


                {selectedArtisanForLedger && (
                    <ArtisanLedgerModal
                        artisan={selectedArtisanForLedger}
                        scoreLedger={scoreLedger}
                        onClose={() => setSelectedArtisanForLedger(null)}
                    />
                )}

                {selectedMissionForDetails && (
                    <MissionDetailModal
                        mission={selectedMissionForDetails}
                        orders={ordersPage?.data ?? orders}
                        onClose={() => setSelectedMissionForDetails(null)}
                        onSelectOrder={setSelectedOrderForDetails}
                    />
                )}

                {selectedOrderForDetails && (
                    <OrderDetailModal
                        order={selectedOrderForDetails}
                        onClose={() => setSelectedOrderForDetails(null)}
                    />
                )}

                {selectedTransactionForDetails && (
                    <TransactionDetailModal
                        transaction={selectedTransactionForDetails}
                        onClose={() => setSelectedTransactionForDetails(null)}
                    />
                )}

                {selectedUserForRgpd && (
                    <PersonalDataModal
                        user={selectedUserForRgpd}
                        canAnonymize={canManageRgpd}
                        actionLoading={actionLoading}
                        onAnonymize={handleAnonymizeUser}
                        onClose={() => setSelectedUserForRgpd(null)}
                    />
                )}

                {confirmDialog}

        </AdminShell>
    );
}
