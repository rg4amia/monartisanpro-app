import { router, usePage } from '@inertiajs/react';
import { useDeferredValue, useMemo, useState } from 'react';

import AiDashboardPanel from './ai-dashboard-panel';
import { useAdminAnalytics } from './hooks/useAdminAnalytics';
import { useAdminNotifications } from './hooks/useAdminNotifications';
import { useAiQuotaForm } from './hooks/useAiQuotaForm';
import { useAuditLogFilters } from './hooks/useAuditLogFilters';
import { useCampagneParrainageForm } from './hooks/useCampagneParrainageForm';
import { useCommunicationForm } from './hooks/useCommunicationForm';
import { useExchangeRates, useOnlineStatus, useThemeMode } from './hooks/useConsoleEnvironment';
import { usePromoCodeForm } from './hooks/usePromoCodeForm';
import { useRowSelection } from './hooks/useRowSelection';
import { useServerTable } from './hooks/useServerTable';
import { useUserManagement } from './hooks/useUserManagement';
import LlmAdminPanel from './llm-admin-panel';
import { AiQuotasPanel } from './panels/AiQuotasPanel';
import { AuditLogsPanel } from './panels/AuditLogsPanel';
import { CampagnesParrainagePanel } from './panels/CampagnesParrainagePanel';
import { CartographyPanel } from './panels/CartographyPanel';
import { CommunicationsPanel } from './panels/CommunicationsPanel';
import { DashboardPanel } from './panels/DashboardPanel';
import { ArtisanLedgerModal, MissionDetailModal, OrderDetailModal, TransactionDetailModal } from './panels/DetailModals';
import { EvaluationsPanel } from './panels/EvaluationsPanel';
import { FaqPanel } from './panels/FaqPanel';
import { AiQuotaFormModal, CampagneParrainageFormModal, CommunicationFormModal, PromoCodeFormModal, StatusFormModal, UserFormModal } from './panels/FormModals';
import { KycPanel } from './panels/KycPanel';
import { LitigesPanel } from './panels/LitigesPanel';
import { MissionsPanel } from './panels/MissionsPanel';
import { NotificationsPanel } from './panels/NotificationsPanel';
import { ObservabilityPanel } from './panels/ObservabilityPanel';
import { PersonalDataModal } from './panels/PersonalDataModal';
import { PromoCodesPanel } from './panels/PromoCodesPanel';
import { RecruitmentPanel } from './panels/RecruitmentPanel';
import { SettingsPanel } from './panels/SettingsPanel';
import { TransactionsPanel } from './panels/TransactionsPanel';
import { UsersPanel } from './panels/UsersPanel';
import { WhatsAppPanel } from './panels/WhatsAppPanel';
import RolesPermissionsPanel from './roles-permissions-panel';
import type {
    AdminEvaluation,
    AdminMission,
    AdminNotificationItem,
    AdminOrder,
    AdminPageProps,
    AdminTab,
    AdminTransaction,
    AdminUser,
    ArtisanScoreItem,
    CampagneParrainageItem,
    FournisseurItem,
    KycUser,
    LitigeItem,
    PromoCodeItem,
    ScoreLedgerEntryItem,
} from './shared';
import {
    AdminShell,
    buildHeroStats,
    buildNavigation,
    can,
    money,
    numberFormat,
    renderPagination,
    tabRoutes,
    useConfirm,
} from './shared';
import VitrinePanel from './vitrine-panel';

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
        bankTransferSettings,
        evaluationsList = [] as AdminEvaluation[],
        artisansScores = [] as ArtisanScoreItem[],
        scoreLedger = [] as ScoreLedgerEntryItem[],
        navBadges = {} as NonNullable<AdminPageProps['navBadges']>,
        financialKpis = {} as any,
        promoCodes = [] as PromoCodeItem[],
        campagnesParrainage = [] as CampagneParrainageItem[],
        communications = [],
        audioUploadLimit,
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
        documentsPage = undefined,
        documentStats = { total_documents: 0, total_montant_certifie: 0, recus_jalons_mo: 0, recus_quincaillerie: 0, rapports_et_litiges: 0 },
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
        aiUserQuotasPage = undefined,
        vitrineSlides = [],
        vitrineArtisanDuMois = [],
        vitrineArticles = [],
        vitrineVideos = [],
        vitrineFormations = [],
        vitrineRecrutements = [],
        vitrinePopups = [],
        vitrineSettings = [],
        contactMessages = [],
        whatsappClicksPage = undefined,
        whatsappClickStats = { total: 0, today: 0, last_7_days: 0 },
        whatsappSettings = { whatsapp_widget_enabled: '1', whatsapp_widget_phone: '', whatsapp_widget_message: '' },
        faqs = [],
        faqStats = { total: 0, actives: 0, roles_covered: 0 },
        recruitmentOffersPage = undefined,
        recruitmentStats = { total: 0, pending_review: 0, active: 0, filled: 0 },
        recruitmentSettings = { client_posting_enabled: '1', fournisseur_posting_enabled: '1' },
        observability = undefined,
        territorySummary = undefined,
        districtsHeatmap = {},
        communesHeatmap = {},
        districtsList = [],
        communesList = [],
        entities = { data: [], current_page: 1, last_page: 1, total: 0, per_page: 15 },
        filters = { district: null, commune: null, entity_type: 'all', search: '' },
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
    const canManageAi = can(permissions, 'admin.ai.manage');
    const canManageWhatsapp = can(permissions, 'admin.whatsapp.manage');
    const canManageFaq = can(permissions, 'admin.faq.manage');
    const canManageRecruitment = can(permissions, 'admin.recruitment.manage');

    const [missionSubTab, setMissionSubTab] = useState<'chantiers' | 'livraisons'>('chantiers');
    const [selectedOrderForDetails, setSelectedOrderForDetails] = useState<AdminOrder | null>(null);

    const {
        notificationsOpen,
        setNotificationsOpen,
        notifFilter,
        setNotifFilter,
        notifTab,
        setNotifTab,
        searchNotif,
        setSearchNotif,
        roleNotif,
        setRoleNotif,
        typeNotif,
        setTypeNotif,
        liveNotifications,
        unreadNotifsCount,
        filteredNotifs,
        markAllRead: handleMarkAllNotifsRead,
        markRead: handleMarkNotifRead,
        applyHistoryFilters: handleFilterNotifications,
        resetHistoryFilters: handleResetFilters,
    } = useAdminNotifications(adminNotifications, dashboard);

    // Journal d'audit (Chantier C3 / P0-4) — filtres serveur via rechargement partiel.
    const {
        search: searchAudit,
        setSearch: setSearchAudit,
        action: actionAudit,
        setAction: setActionAudit,
        admin: adminAudit,
        setAdmin: setAdminAudit,
        dateFrom: dateFromAudit,
        setDateFrom: setDateFromAudit,
        dateTo: dateToAudit,
        setDateTo: setDateToAudit,
        apply: handleFilterAudit,
        reset: handleResetAudit,
    } = useAuditLogFilters();

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
        initial: { search_mission: '', search_order: '', status_order: '', status_mission: '' },
        storageKey: 'missions',
    });

    // KYC (Chantier C4 / P1-6) — file paginée + recherche serveur.
    const kycTable = useServerTable({
        path: '/admin/kyc',
        only: ['kycUsersPage', 'kycStats'],
        initial: { search_kyc: '' },
        storageKey: 'kyc',
    });

    // Clics WhatsApp (onglet « WhatsApp ») — journal paginé + recherche par page.
    const whatsappTable = useServerTable({
        path: '/admin/whatsapp',
        only: ['whatsappClicksPage'],
        initial: { search_whatsapp: '' },
        storageKey: 'whatsapp_clicks',
    });

    // Offres de recrutement (onglet « Recrutement ») — liste paginée + recherche par titre.
    const recruitmentTable = useServerTable({
        path: '/admin/recruitment',
        only: ['recruitmentOffersPage'],
        initial: { search_recruitment: '' },
        storageKey: 'recruitment_offers',
    });

    // Quotas IA par utilisateur (onglet « Suivi & Coûts IA »).
    const aiQuotaTable = useServerTable({
        path: '/admin/ai-dashboard',
        only: ['aiUserQuotasPage'],
        initial: { search_aiq: '', role_aiq: '' },
        storageKey: 'ai_quotas',
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

    const { themeMode, toggleTheme } = useThemeMode();
    const [search, setSearch] = useState<string>('');
    const [actionLoading, setActionLoading] = useState<boolean>(false);
    const [refreshing, setRefreshing] = useState<boolean>(false);
    const deferredSearch = useDeferredValue(search.trim().toLowerCase());
    const [now] = useState(() => Date.now());
    const [isMobileSidebarOpen, setIsMobileSidebarOpen] = useState<boolean>(false);
    const [evalSubTab, setEvalSubTab] = useState<'list' | 'artisans'>('list');
    const [selectedArtisanForLedger, setSelectedArtisanForLedger] = useState<ArtisanScoreItem | null>(null);
    const [expandedSectors, setExpandedSectors] = useState<Record<number, boolean>>({});

    const exchangeRates = useExchangeRates();
    const { offlineActive, isOfflineSimulated, toggleOfflineSimulated } = useOnlineStatus();

    const [selectedMissionForDetails, setSelectedMissionForDetails] = useState<AdminMission | null>(null);
    const [selectedTransactionForDetails, setSelectedTransactionForDetails] = useState<AdminTransaction | null>(null);

    const {
        userForm,
        statusForm,
        userModalOpen,
        closeUserModal,
        editingUser,
        statusModalOpen,
        closeStatusModal,
        statusTargetUser,
        selectedUserForRgpd,
        setSelectedUserForRgpd,
        openCreateUserModal,
        openEditUserModal,
        submitUserForm: handleUserFormSubmit,
        toggleUserStatus: handleToggleUserStatus,
        submitStatusForm: handleStatusSubmit,
        deleteUser: handleDeleteUser,
        impersonate: handleImpersonate,
        anonymize: handleAnonymizeUser,
    } = useUserManagement({ currentAdmin: auth?.user, askConfirm, setActionLoading, canDeleteUsers, canImpersonate, canManageRgpd });

    const {
        commForm,
        commModalOpen,
        closeCommModal,
        editingComm,
        commTypeFilter,
        setCommTypeFilter,
        commStatusFilter,
        setCommStatusFilter,
        filteredCommunications,
        openCreateCommModal,
        openEditCommModal,
        submitCommForm: handleCommSubmit,
    } = useCommunicationForm(communications, deferredSearch);

    const {
        promoForm,
        promoModalOpen,
        closePromoModal,
        editingPromo,
        openCreatePromoModal,
        openEditPromoModal,
        submitPromoForm: handlePromoSubmit,
        deletePromo: handleDeletePromo,
        togglePromo: handleTogglePromo,
    } = usePromoCodeForm(askConfirm);

    const {
        campagneForm,
        campagneModalOpen,
        closeCampagneModal,
        editingCampagne,
        openCreateCampagneModal,
        openEditCampagneModal,
        submitCampagneForm: handleCampagneSubmit,
        deleteCampagne: handleDeleteCampagne,
        toggleCampagne: handleToggleCampagne,
    } = useCampagneParrainageForm(askConfirm);

    const {
        aiQuotaForm,
        aiQuotaModalOpen,
        closeAiQuotaModal,
        aiQuotaTarget,
        openEditAiQuota,
        submitAiQuotaForm: handleAiQuotaSubmit,
    } = useAiQuotaForm();

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
        campagnesParrainage,
        now,
    });

    const totalUsers = dashboard.users_total ?? users.length;
    const artisansActifs = useMemo(() => dashboard.artisans_actifs ?? users.filter((user) => user.role === 'artisan' && user.kyc_status === 'actif').length, [dashboard.artisans_actifs, users]);
    const clientsActifs = useMemo(() => dashboard.clients_actifs ?? users.filter((user) => user.role === 'client' && user.kyc_status === 'actif').length, [dashboard.clients_actifs, users]);
    const fournisseursAgrees = dashboard.fournisseurs_agrees ?? 0; // Already a primitive or stable
    const kycPending = useMemo(() => dashboard.kyc_en_attente ?? kycUsers.length, [dashboard.kyc_en_attente, kycUsers]);
    const openDisputes = useMemo(() => dashboard.litiges_ouverts ?? litiges.filter((litige) => litige.statut !== 'resolu').length, [dashboard.litiges_ouverts, litiges]);
    const missionsInProgress = useMemo(() => dashboard.missions_en_cours ?? missions.filter((mission) => ['in_progress', 'pending_approval'].includes(mission.status)).length, [dashboard.missions_en_cours, missions]);
    const referentRequired = dashboard.referent_required_open ?? 0; // Already a primitive or stable
    const fraudAlerts = dashboard.recent_fraud_alerts ?? 0; // Already a primitive or stable
    const volume24h = dashboard.volume_transactions_24h ?? 0; // Already a primitive or stable

    const firstError = Object.values(errors ?? {})[0];
    const bannerError = flash?.error ?? firstError;

    const navigation = buildNavigation(permissions, {
        kycPending,
        missionsInProgress,
        recruitmentPendingReview: recruitmentStats.pending_review,
        openDisputes,
        unreadNotifications: unreadNotifsCount,
        totalUsers,
        pendingTransactions: navBadges.transactions_en_attente ?? analytics.pendingTransactions.length,
        publishedCommunications: navBadges.communications_publiees ?? (communications ?? []).filter(c => c.statut === 'publie').length,
        activePromoCodes: navBadges.promo_codes_actifs ?? (promoCodes ?? []).filter(p => p.is_active).length,
        activeCampagnes: navBadges.campagnes_parrainage_actives ?? (campagnesParrainage ?? []).filter(c => c.is_active).length,
        newContactMessages: navBadges.contact_messages_nouveaux ?? (contactMessages ?? []).filter(c => c.statut === 'nouveau').length,
        whatsappClicksToday: whatsappClickStats.today,
        faqCount: faqs.length,
    });

    const heroStats = buildHeroStats(activeTab, {
        dashboard,
        promoCodes,
        campagnesParrainage,
        liveNotificationsCount: liveNotifications.length,
        unreadNotifsCount,
        totalUsers,
        missionsInProgress,
        kycPending,
        volume24h,
        referentRequired,
        openDisputes,
        fraudAlerts,
        artisansActifs,
        clientsActifs,
        fournisseursAgrees,
        territorySummary,
        kycStats,
        missionStats,
        deliveryStats,
        litigeStats,
        transactionStats,
        evaluationStats,
        allPermissions,
        communications,
        contactMessages,
        vitrineArticles,
        vitrineFormations,
        vitrineSlides,
        vitrineVideos,
        vitrineRecrutements,
        whatsappClickStats,
        whatsappSettings,
        faqs,
        recruitmentStats,
        auditLogs,
        auditAdmins,
        observability,
    });

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

    const handleKycDecision = async (user: KycUser, decision: 'approuve' | 'rejete'): Promise<void> => {
        let rejectionReason = '';

        if (decision === 'rejete') {
            const reason = await askConfirm({
                title: 'Rejeter le dossier KYC',
                message: `Le dossier de ${user.name} sera rejeté et l'utilisateur notifié.`,
                confirmLabel: 'Rejeter',
                tone: 'danger',
                promptLabel: 'Motif de rejet (minimum 10 caractères)',
                promptMinLength: 10,
            });
            if (typeof reason !== 'string') return;
            rejectionReason = reason.trim();
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

    const handleCnmciDecision = async (userId: number, decision: 'valide' | 'rejete'): Promise<void> => {
        const ok = await askConfirm({
            title: 'Affiliation CNMCI',
            message: `Confirmer la décision : ${decision === 'valide' ? 'Valider' : 'Rejeter'} cette affiliation CNMCI ?`,
            confirmLabel: decision === 'valide' ? 'Valider' : 'Rejeter',
            tone: decision === 'rejete' ? 'danger' : 'primary',
        });
        if (!ok) return;
        submitAction(`/admin/kyc/${userId}/cnmci-review`, { decision });
    };

    const handleToggleScoreFreeze = async (artisan: ArtisanScoreItem): Promise<void> => {
        const action = artisan.score_frozen ? 'dégeler' : 'geler';
        const ok = await askConfirm({
            title: 'Score ProsArtisan',
            message: `Voulez-vous vraiment ${action} le score de l'artisan ${artisan.name} ?`,
            confirmLabel: action === 'geler' ? 'Geler' : 'Dégeler',
            tone: action === 'geler' ? 'danger' : 'primary',
        });
        if (!ok) return;
        setActionLoading(true);
        router.post(`/admin/users/${artisan.id}/toggle-score-freeze`, {}, {
            preserveScroll: true,
            onFinish: () => setActionLoading(false),
        });
    };

    const adminName = auth?.user?.name ?? 'Admin ProsArtisan';
    const adminContact = auth?.user?.email ?? auth?.user?.phone ?? 'Administrateur';

    return (
        <AdminShell
            activeTab={activeTab}
            navigation={navigation}
            heroStats={heroStats}
            themeMode={themeMode}
            onToggleTheme={toggleTheme}
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
                                    whatsappClickStats={whatsappClickStats}
                                    faqStats={faqStats}
                                />
                            ) : null}

                            {activeTab === 'cartography' ? (
                                <CartographyPanel
                                    territorySummary={territorySummary}
                                    districtsHeatmap={districtsHeatmap ?? {}}
                                    communesHeatmap={communesHeatmap ?? {}}
                                    districtsList={districtsList ?? []}
                                    communesList={communesList ?? []}
                                    entities={entities ?? { data: [], current_page: 1, last_page: 1, total: 0, per_page: 15 }}
                                    filters={filters}
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
                                    missionStatusFilter={missionsTable.filters.status_mission || 'all'}
                                    onMissionStatusFilterChange={(id) => missionsTable.applyWith('status_mission', id === 'all' ? '' : id)}
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
                                    settingsList={settingsList}
                                    documentsPage={documentsPage}
                                    documentStats={documentStats}
                                />
                            ) : null}

                            {activeTab === 'settings' ? (
                                <SettingsPanel
                                    adminName={adminName}
                                    adminContact={adminContact}
                                    offlineActive={offlineActive}
                                    isOfflineSimulated={isOfflineSimulated}
                                    onToggleOfflineSimulated={toggleOfflineSimulated}
                                    onRefresh={refreshData}
                                    settingsList={settingsList}
                                    bankTransferSettings={bankTransferSettings}
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
                                <section className="mt-5 space-y-6">
                                    <AiDashboardPanel
                                        stats={(pageProps as any).stats}
                                        costsByModel={(pageProps as any).costsByModel}
                                        dailyUsage={(pageProps as any).dailyUsage}
                                        logs={(pageProps as any).logs}
                                        settings={(pageProps as any).settings}
                                    />
                                    <AiQuotasPanel
                                        aiUserQuotasPage={aiUserQuotasPage}
                                        globalDailyLimit={Number((pageProps as any).settings?.daily_user_limit ?? 0)}
                                        search={aiQuotaTable.filters.search_aiq}
                                        onSearchChange={(v) => aiQuotaTable.set('search_aiq', v)}
                                        roleFilter={aiQuotaTable.filters.role_aiq}
                                        onRoleFilterChange={(v) => aiQuotaTable.applyWith('role_aiq', v)}
                                        onSubmit={aiQuotaTable.apply}
                                        onReset={aiQuotaTable.reset}
                                        renderPagination={(links) => renderPagination(links as any[], ['aiUserQuotasPage'])}
                                        canManage={canManageAi}
                                        onEditQuota={openEditAiQuota}
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

                            {activeTab === 'campagnes_parrainage' ? (
                                <CampagnesParrainagePanel
                                    filteredCampagnes={analytics.filteredCampagnes}
                                    onCreate={openCreateCampagneModal}
                                    onEdit={openEditCampagneModal}
                                    onToggle={handleToggleCampagne}
                                    onDelete={handleDeleteCampagne}
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

                            {activeTab === 'whatsapp' ? (
                                <WhatsAppPanel
                                    whatsappClicksPage={whatsappClicksPage}
                                    whatsappClickStats={whatsappClickStats}
                                    whatsappSettings={whatsappSettings}
                                    search={whatsappTable.filters.search_whatsapp}
                                    onSearchChange={(v) => whatsappTable.set('search_whatsapp', v)}
                                    onSubmit={whatsappTable.apply}
                                    onReset={whatsappTable.reset}
                                    renderPagination={(links) => renderPagination(links as any[], ['whatsappClicksPage'])}
                                    canManage={canManageWhatsapp}
                                />
                            ) : null}

                            {activeTab === 'faq' ? (
                                <FaqPanel faqs={faqs} canManage={canManageFaq} />
                            ) : null}

                            {activeTab === 'recruitment' ? (
                                <RecruitmentPanel
                                    recruitmentOffersPage={recruitmentOffersPage}
                                    recruitmentStats={recruitmentStats}
                                    recruitmentSettings={recruitmentSettings}
                                    search={recruitmentTable.filters.search_recruitment}
                                    onSearchChange={(v) => recruitmentTable.set('search_recruitment', v)}
                                    onSubmit={recruitmentTable.apply}
                                    onReset={recruitmentTable.reset}
                                    renderPagination={(links) => renderPagination(links as any[], ['recruitmentOffersPage'])}
                                    canManage={canManageRecruitment}
                                />
                            ) : null}
                {commModalOpen && (
                    <CommunicationFormModal
                        form={commForm}
                        audioUploadLimit={audioUploadLimit}
                        editing={editingComm}
                        adminName={auth?.user?.name ?? ''}
                        onSubmit={handleCommSubmit}
                        onClose={closeCommModal}
                    />
                )}

                {promoModalOpen && (
                    <PromoCodeFormModal
                        form={promoForm}
                        editing={editingPromo}
                        onSubmit={handlePromoSubmit}
                        onClose={closePromoModal}
                    />
                )}

                {campagneModalOpen && (
                    <CampagneParrainageFormModal
                        form={campagneForm}
                        editing={editingCampagne}
                        onSubmit={handleCampagneSubmit}
                        onClose={closeCampagneModal}
                    />
                )}

                {userModalOpen && (
                    <UserFormModal
                        form={userForm}
                        editing={editingUser}
                        sectors={sectors}
                        onSubmit={handleUserFormSubmit}
                        onClose={closeUserModal}
                    />
                )}

                {statusModalOpen && statusTargetUser && (
                    <StatusFormModal
                        form={statusForm}
                        targetUser={statusTargetUser}
                        onSubmit={handleStatusSubmit}
                        onClose={closeStatusModal}
                    />
                )}

                {aiQuotaModalOpen && aiQuotaTarget && (
                    <AiQuotaFormModal
                        form={aiQuotaForm}
                        targetName={aiQuotaTarget.name}
                        globalDailyLimit={Number((pageProps as any).settings?.daily_user_limit ?? 0)}
                        globalMonthlyLimit={Number((pageProps as any).settings?.monthly_user_limit ?? 0)}
                        onSubmit={handleAiQuotaSubmit}
                        onClose={closeAiQuotaModal}
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
