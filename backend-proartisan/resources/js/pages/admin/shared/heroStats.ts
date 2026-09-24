// Indicateurs d'en-tête de la console, propres à chaque onglet.

import type { AdminPageProps } from './consoleProps';
import { money, numberFormat } from './format';
import type {
    AdminTab,
    AuditAdminOption,
    CampagneParrainageItem,
    DeliveryStats,
    EvaluationStats,
    FaqItem,
    KycStats,
    LitigeStats,
    MetricItem,
    MissionStats,
    PromoCodeItem,
    RecruitmentStats,
    TransactionStats,
    WhatsappClickStats,
    WhatsappSettings,
} from './index';

export type HeroStat = Pick<MetricItem, 'tone'> & { label: string; value: string };

export interface HeroStatsContext {
    dashboard: AdminPageProps['dashboard'];
    promoCodes: PromoCodeItem[];
    campagnesParrainage: CampagneParrainageItem[];
    liveNotificationsCount: number;
    unreadNotifsCount: number;
    totalUsers: number;
    missionsInProgress: number;
    kycPending: number;
    volume24h: number;
    referentRequired: number;
    openDisputes: number;
    fraudAlerts: number;
    artisansActifs: number;
    clientsActifs: number;
    fournisseursAgrees: number;
    territorySummary: AdminPageProps['territorySummary'];
    kycStats: KycStats;
    missionStats: MissionStats;
    deliveryStats: DeliveryStats;
    litigeStats: LitigeStats;
    transactionStats: TransactionStats;
    evaluationStats: EvaluationStats;
    allPermissions: AdminPageProps['allPermissions'];
    communications: AdminPageProps['communications'];
    contactMessages: any[];
    vitrineArticles: any[];
    vitrineFormations: any[];
    vitrineSlides: any[];
    vitrineVideos: any[];
    vitrineRecrutements: any[];
    whatsappClickStats: WhatsappClickStats;
    whatsappSettings: WhatsappSettings;
    faqs: FaqItem[];
    recruitmentStats: RecruitmentStats;
    auditLogs: AdminPageProps['auditLogs'];
    auditAdmins: AuditAdminOption[];
    observability: AdminPageProps['observability'];
}

/**
 * Calcule les quatre indicateurs d'en-tête de l'onglet actif. Fonction pure :
 * les agrégats viennent du serveur, indépendamment de la page courante
 * (Règle d'or 19).
 */
export function buildHeroStats(activeTab: AdminTab, ctx: HeroStatsContext): HeroStat[] {
    const {
        dashboard,
        promoCodes,
        campagnesParrainage,
        liveNotificationsCount,
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
    } = ctx;

    switch (activeTab) {
        case 'promo_codes':
            return [
                { label: 'Codes Promo Total', tone: 'amber' as const, value: `${(promoCodes ?? []).length}` },
                { label: 'Codes Actifs', tone: 'green' as const, value: `${(promoCodes ?? []).filter(p => p.is_active).length}` },
                { label: 'Utilisations Totales', tone: 'blue' as const, value: `${(promoCodes ?? []).reduce((sum, p) => sum + (p.used_count || 0), 0)}` },
                { label: 'Campagnes Fixes & %', tone: 'slate' as const, value: `${(promoCodes ?? []).filter(p => p.discount_type === 'percent').length} % / ${(promoCodes ?? []).filter(p => p.discount_type === 'fixed').length} Fixe` },
            ];
        case 'campagnes_parrainage':
            return [
                { label: 'Campagnes Total', tone: 'amber' as const, value: `${(campagnesParrainage ?? []).length}` },
                { label: 'Campagnes Actives', tone: 'green' as const, value: `${(campagnesParrainage ?? []).filter(c => c.is_active).length}` },
                { label: 'Campagnes Fixes & %', tone: 'slate' as const, value: `${(campagnesParrainage ?? []).filter(c => c.discount_type === 'percent').length} % / ${(campagnesParrainage ?? []).filter(c => c.discount_type === 'fixed').length} Fixe` },
            ];
        case 'notifications':
            return [
                { label: 'Alertes globales', tone: 'amber' as const, value: `${liveNotificationsCount}` },
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
        case 'cartography':
            return [
                { label: 'Territoire', tone: 'amber' as const, value: territorySummary?.zone?.name ?? 'Côte d’Ivoire' },
                { label: 'Districts', tone: 'blue' as const, value: '14' },
                { label: 'Communes Abidjan', tone: 'green' as const, value: '13' },
                { label: 'Couverture Réseau', tone: 'slate' as const, value: '100%' },
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
        case 'whatsapp':
            return [
                { label: 'Total des clics', tone: 'amber' as const, value: numberFormat.format(whatsappClickStats.total) },
                { label: "Aujourd'hui", tone: 'green' as const, value: numberFormat.format(whatsappClickStats.today) },
                { label: '7 derniers jours', tone: 'blue' as const, value: numberFormat.format(whatsappClickStats.last_7_days) },
                { label: 'Bouton', tone: 'slate' as const, value: whatsappSettings.whatsapp_widget_enabled === '1' ? 'Actif' : 'Désactivé' },
            ];
        case 'faq': {
            const rolesCovered = new Set(faqs.flatMap((f) => f.roles));
            return [
                { label: 'Questions publiées', tone: 'amber' as const, value: numberFormat.format(faqs.filter((f) => f.actif).length) },
                { label: 'Masquées', tone: 'slate' as const, value: numberFormat.format(faqs.filter((f) => !f.actif).length) },
                { label: 'Espaces couverts', tone: 'blue' as const, value: `${rolesCovered.size} / 4` },
                { label: 'Total', tone: 'green' as const, value: numberFormat.format(faqs.length) },
            ];
        }
        case 'recruitment':
            return [
                { label: 'Total des offres', tone: 'slate' as const, value: numberFormat.format(recruitmentStats.total) },
                { label: 'À modérer', tone: 'amber' as const, value: numberFormat.format(recruitmentStats.pending_review) },
                { label: 'Actives', tone: 'green' as const, value: numberFormat.format(recruitmentStats.active) },
                { label: 'Pourvues', tone: 'blue' as const, value: numberFormat.format(recruitmentStats.filled) },
            ];
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
}
