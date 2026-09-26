// Props Inertia partagées par tous les onglets de la console d'administration.

import type { DriverCashoutsOverview, PayoutsOverview } from '../panels/PayoutsSection';

import type {
    AdminEvaluation,
    AdminMission,
    AdminNotificationItem,
    AdminOrder,
    AdminTransaction,
    AdminUser,
    AiUserQuotaRow,
    ArtisanScoreItem,
    AuditAdminOption,
    CampagneParrainageItem,
    CommuneHeatmapItem,
    CommuneListItem,
    DashboardData,
    DeliveryStats,
    DistrictHeatmapItem,
    DistrictListItem,
    DocumentStats,
    EvaluationStats,
    FaqItem,
    FaqStats,
    FlashMessages,
    FournisseurItem,
    GeneratedDocumentItem,
    KycStats,
    KycUser,
    LitigeItem,
    LitigeStats,
    MissionStats,
    ObservabilitySnapshot,
    Paginated,
    PaginatedAuditLogs,
    PromoCodeItem,
    RecruitmentOfferItem,
    RecruitmentSettings,
    RecruitmentStats,
    ScoreLedgerEntryItem,
    SectorItem,
    SettingItem,
    TerritoryEntityItem,
    TerritorySummary,
    TransactionStats,
    UserStats,
    WhatsappClickLogItem,
    WhatsappClickStats,
    WhatsappSettings,
} from './index';

interface AuthUser {
    email?: string | null;
    name: string;
    phone?: string | null;
    role?: string | null;
}

export interface AdminPageProps {
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
        campagnes_parrainage_actives?: number;
        contact_messages_nouveaux?: number;
    };
    financialKpis?: any;
    promoCodes?: PromoCodeItem[];
    campagnesParrainage?: CampagneParrainageItem[];
    settingsList?: SettingItem[];
    bankTransferSettings?: { bank_name: string; account_name: string; iban: string };
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
    documentsPage?: Paginated<GeneratedDocumentItem>;
    documentStats?: DocumentStats;
    payoutsOverview?: PayoutsOverview | null;
    driverCashoutsOverview?: DriverCashoutsOverview | null;
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
    // Onglet « Suivi & Coûts IA » (props non typées via `pageProps as any` — sauf la liste paginée).
    aiUserQuotasPage?: Paginated<AiUserQuotaRow>;
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
    /** Plafond de televersement audio reellement applicable (borne par PHP). */
    audioUploadLimit?: string;
    vitrineSlides?: any[];
    vitrineArtisanDuMois?: any[];
    vitrineArticles?: any[];
    vitrineVideos?: any[];
    vitrineFormations?: any[];
    vitrineRecrutements?: any[];
    vitrinePopups?: any[];
    vitrineSettings?: any[];
    contactMessages?: any[];
    whatsappClicksPage?: Paginated<WhatsappClickLogItem> | null;
    whatsappClickStats?: WhatsappClickStats;
    whatsappSettings?: WhatsappSettings;
    faqs?: FaqItem[];
    faqStats?: FaqStats;
    recruitmentOffersPage?: Paginated<RecruitmentOfferItem> | null;
    recruitmentStats?: RecruitmentStats;
    recruitmentSettings?: RecruitmentSettings;
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
        district: string | null;
        commune: string | null;
        entity_type: string;
        search: string;
    };
}

export interface AuditNotificationItem extends AdminNotificationItem {
    user?: {
        id: number;
        name: string;
        phone: string;
        role: string;
    } | null;
}

export interface PaginatedNotifications {
    data: AuditNotificationItem[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}
