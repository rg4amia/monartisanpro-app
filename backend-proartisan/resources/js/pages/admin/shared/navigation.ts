import { tabMeta } from './constants';
import { canOpenTab } from './permissions';
import type { NavigationGroup } from './types';

/** Compteurs affichés en pastille à côté des entrées de navigation. */
export interface NavigationCounts {
    kycPending: number;
    missionsInProgress: number;
    recruitmentPendingReview: number;
    openDisputes: number;
    unreadNotifications: number;
    totalUsers: number;
    pendingTransactions: number;
    publishedCommunications: number;
    activePromoCodes: number;
    activeCampagnes: number;
    newContactMessages: number;
    whatsappClicksToday: number;
    faqCount: number;
}

/**
 * Navigation latérale de la console, restreinte aux onglets que les capacités
 * fines de l'administrateur autorisent (Règle d'or 16). Un groupe vidé de
 * toutes ses entrées disparaît.
 */
export function buildNavigation(permissions: string[], counts: NavigationCounts): NavigationGroup[] {
    const groups: NavigationGroup[] = [
        {
            label: 'Pilotage',
            items: [
                { id: 'dashboard', label: tabMeta.dashboard.label },
                { id: 'cartography', label: tabMeta.cartography.label },
                { count: counts.kycPending, id: 'kyc', label: tabMeta.kyc.label },
                { count: counts.missionsInProgress, id: 'missions', label: tabMeta.missions.label },
                { count: counts.recruitmentPendingReview, id: 'recruitment', label: tabMeta.recruitment.label },
                { count: counts.openDisputes, id: 'litiges', label: tabMeta.litiges.label },
                { count: counts.unreadNotifications, id: 'notifications', label: 'Notifications' },
                { id: 'llm_admin', label: 'Administration LLM' },
                { id: 'ai_dashboard', label: 'Suivi & Coûts IA' },
            ],
        },
        {
            label: 'Réseau',
            items: [
                { count: counts.totalUsers, id: 'users', label: tabMeta.users.label },
                { id: 'evaluations', label: tabMeta.evaluations.label },
                { count: counts.pendingTransactions, id: 'transactions', label: tabMeta.transactions.label },
            ],
        },
        {
            label: 'Plateforme',
            items: [
                { id: 'settings', label: tabMeta.settings.label },
                { id: 'roles_permissions', label: tabMeta.roles_permissions.label },
                { id: 'audit_logs', label: tabMeta.audit_logs.label },
                { id: 'observability', label: tabMeta.observability.label },
                { count: counts.publishedCommunications, id: 'communications', label: tabMeta.communications.label },
                { count: counts.activePromoCodes, id: 'promo_codes', label: 'Codes Promo' },
                { count: counts.activeCampagnes, id: 'campagnes_parrainage', label: 'Parrainage Clients' },
                { count: counts.newContactMessages, id: 'vitrine', label: tabMeta.vitrine.label },
                { count: counts.whatsappClicksToday, id: 'whatsapp', label: tabMeta.whatsapp.label },
                { count: counts.faqCount, id: 'faq', label: tabMeta.faq.label },
                { id: 'manual', label: tabMeta.manual.label },
            ],
        },
    ];

    return groups
        .map((group) => ({ ...group, items: group.items.filter((item) => canOpenTab(permissions, item.id)) }))
        .filter((group) => group.items.length > 0);
}
