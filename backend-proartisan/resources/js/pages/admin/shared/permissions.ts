// Capacités fines du backoffice admin (Chantier C6 / P2-10).
// La liste effective est partagée par Inertia dans `auth.permissions` :
// `['*']` = accès total (super admin), sinon la liste explicite des capacités.

import type { AdminTab } from './types';

export type AdminCapabilities = string[];

/** L'admin possède-t-il la capacité demandée ? */
export function can(permissions: AdminCapabilities | undefined, capability: string): boolean {
    if (!permissions) return false;
    return permissions.includes('*') || permissions.includes(capability);
}

/** Onglet → capacité requise pour l'afficher (`null` = ouvert à tout admin). */
export const tabCapability: Record<AdminTab, string | null> = {
    dashboard: null,
    notifications: null,
    kyc: 'admin.kyc.view',
    missions: 'admin.missions.view',
    litiges: 'admin.litiges.view',
    users: 'admin.users.view',
    transactions: 'admin.transactions.view',
    evaluations: 'admin.evaluations.view',
    settings: 'admin.settings.manage',
    roles_permissions: 'admin.roles.manage',
    audit_logs: 'admin.audit.view',
    observability: 'admin.observability.view',
    communications: 'admin.communications.manage',
    promo_codes: 'admin.promo.manage',
    vitrine: 'admin.vitrine.manage',
    llm_admin: 'admin.llm.manage',
    ai_dashboard: 'admin.ai.manage',
};

export function canOpenTab(permissions: AdminCapabilities | undefined, tab: AdminTab): boolean {
    const required = tabCapability[tab];
    return required === null || can(permissions, required);
}
