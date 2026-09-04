// Constantes de navigation et libellés du backoffice, extraits de console.tsx (Chantier C2).

import type { AdminTab } from './types';

export const tabRoutes: Record<AdminTab, string> = {
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
    communications: '/admin/communications',
    notifications: '/admin/notifications',
    promo_codes: '/admin/promo-codes',
    audit_logs: '/admin/audit-logs',
    observability: '/admin/observability',
    vitrine: '/admin/vitrine',
};

export const tabMeta: Record<AdminTab, { description: string; label: string; section: string }> = {
    dashboard: {
        label: "Vue d'ensemble",
        section: 'PILOTAGE',
        description: 'Lecture rapide de la santé opérationnelle, financière et terrain de ProsArtisan.',
    },
    promo_codes: {
        label: 'Codes Promo',
        section: 'MARKETING',
        description: 'Gestion des campagnes promotionnelles, remises en pourcentage ou en montant fixe et plafonds.',
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
        description: "Visualisation de l'utilisation de l'IA, des jetons consommés et contrôle des limites de coûts.",
    },
    evaluations: {
        label: 'Évaluations & Scores',
        section: 'QUALITÉ',
        description: 'Suivi de la réputation des artisans, calcul du score ProsArtisan et historiques des évaluations.',
    },
    communications: {
        label: 'Communications',
        section: 'COMMUNICATION',
        description: 'Gérez les annonces et astuces "Le saviez-vous ?" diffusées aux utilisateurs de la plateforme.',
    },
    notifications: {
        label: 'Notifications & Alertes',
        section: 'COMMUNICATION',
        description: 'Centre de notifications système, alertes KYC, litiges et anomalies de sécurité de la plateforme.',
    },
    vitrine: {
        label: 'CMS Vitrine & Contacts',
        section: 'COMMUNICATION',
        description: 'Administration et gestion du contenu éditorial de la vitrine ProsArtisan et des demandes de contact.',
    },
    audit_logs: {
        label: "Journal d'audit",
        section: 'PLATEFORME',
        description: 'Traçabilité horodatée et attribuée des actions sensibles : KYC, litiges, gels de score, comptes, paramètres et connexions admin.',
    },
    observability: {
        label: 'Santé & Observabilité',
        section: 'PLATEFORME',
        description: 'Jobs en échec, webhooks de paiement KO, tentatives de fraude GPS J-Code et missions bloquées au seuil Référent.',
    },
};

export const searchPlaceholders: Record<AdminTab, string> = {
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
    communications: 'Rechercher une communication, un titre ou une cible...',
    notifications: 'Rechercher une notification ou alerte...',
    promo_codes: 'Rechercher un code promo, une description ou un type...',
    audit_logs: 'Rechercher une action, un admin, une entité ou une IP...',
    observability: 'Filtrer les signaux de santé...',
    vitrine: 'Rechercher un slide, article, vidéo ou formation...',
};

export const quickDockTabs: AdminTab[] = ['dashboard', 'missions', 'users', 'settings'];

export const roleLabels: Record<string, string> = {
    admin: 'Admin',
    artisan: 'Artisan',
    client: 'Client',
    fournisseur: 'Fournisseur',
    referent: 'Référent',
    livreur: 'Livreur',
};

export const kycStatusLabels: Record<string, string> = {
    actif: 'Actif',
    en_attente: 'En attente',
    rejete: 'Rejeté',
};

export const missionStatusLabels: Record<string, string> = {
    annulee: 'Annulée',
    en_attente: 'En attente',
    en_cours: 'En cours',
    financee: 'Financée',
    litige: 'Litige',
    terminee: 'Terminée',
};

export const transactionTypeLabels: Record<string, string> = {
    acompte: 'Acompte',
    credit: 'Crédit',
    liberation_jalon: 'Libération jalon',
    paiement_fournisseur: 'Paiement fournisseur',
    remboursement: 'Remboursement',
};

export const providerLabels: Record<string, string> = {
    orange_money: 'Orange Money',
    virement_bancaire: 'Virement',
    wave: 'Wave',
};

export const litigeDecisionLabels: Record<string, string> = {
    artisan: 'Payer artisan',
    client: 'Rembourser client',
    gel: 'Geler et envoyer Référent',
};

// Libellés lisibles des actions du journal d'audit (Chantier C3 / P0-4).
export const auditActionLabels: Record<string, string> = {
    'kyc.reviewed': 'Revue KYC',
    'kyc.bulk_reviewed': 'Revue KYC groupée',
    'export.generated': 'Export CSV',
    'litige.arbitrated': 'Arbitrage de litige',
    'fournisseur.reviewed': 'Revue fournisseur',
    'user.created': 'Création de compte',
    'user.updated': 'Modification de compte',
    'user.deleted': 'Suppression de compte',
    'user.status_changed': 'Changement de statut de compte',
    'user.bulk_status_changed': 'Changement de statut groupé',
    'user.score_freeze_toggled': 'Gel / dégel de score',
    'user.cnmci_reviewed': 'Revue affiliation CNMCI',
    'promo_code.created': 'Création de code promo',
    'promo_code.updated': 'Modification de code promo',
    'promo_code.toggled': 'Activation / désactivation de code promo',
    'sector.created': 'Création de catégorie',
    'sector.updated': 'Modification de catégorie',
    'trade.created': 'Création de sous-catégorie',
    'trade.updated': 'Modification de sous-catégorie',
    'setting.updated': 'Modification de paramètre',
    'ai_settings.updated': 'Modification des paramètres IA',
    'admin.permissions_updated': "Droits d'un administrateur modifiés",
    'observability.jobs_retried': 'Relance des jobs en échec',
    'observability.jobs_flushed': 'Purge des jobs en échec',
    'user.anonymized': 'Anonymisation RGPD',
    'user.impersonation_started': 'Usurpation de session — début',
    'user.impersonation_stopped': 'Usurpation de session — fin',
    'admin.login.success': 'Connexion admin réussie',
    'admin.login.failed': 'Échec de connexion admin',
    'admin.login.denied': 'Connexion admin refusée (rôle)',
    'admin.login.2fa_failed': 'Échec 2FA admin',
    'admin.logout': 'Déconnexion admin',
};

// Familles d'actions → tonalité de badge.
export const auditActionTone: Record<string, 'green' | 'amber' | 'rose' | 'blue' | 'slate'> = {
    'kyc.reviewed': 'blue',
    'litige.arbitrated': 'amber',
    'fournisseur.reviewed': 'blue',
    'user.deleted': 'rose',
    'user.status_changed': 'amber',
    'user.score_freeze_toggled': 'amber',
    'admin.permissions_updated': 'blue',
    'observability.jobs_flushed': 'amber',
    'user.anonymized': 'rose',
    'user.impersonation_started': 'amber',
    'user.impersonation_stopped': 'slate',
    'admin.login.failed': 'rose',
    'admin.login.denied': 'rose',
    'admin.login.2fa_failed': 'rose',
    'admin.login.success': 'green',
};
