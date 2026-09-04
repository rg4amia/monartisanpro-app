// Interfaces des entités métier du backoffice, extraites de console.tsx (Chantier C2).

export interface PromoCodeItem {
    id: number;
    code: string;
    description?: string | null;
    discount_type: 'percent' | 'fixed';
    discount_value: number;
    min_order_amount: number;
    max_discount_amount?: number | null;
    usage_limit?: number | null;
    used_count: number;
    starts_at?: string | null;
    expires_at?: string | null;
    is_active: boolean;
    created_at: string;
}

export interface DashboardData {
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

export interface KycDocument {
    id: number;
    type: 'cni' | 'selfie';
    file_url: string;
}

export interface KycUser {
    id: number;
    name: string;
    phone: string;
    role: 'client' | 'artisan' | 'fournisseur' | 'admin' | 'referent';
    created_at: string;
    kyc_documents: KycDocument[];
}

export interface LitigeActor {
    name: string;
}

export interface LitigeMission {
    montant_total: number;
    client?: LitigeActor;
    artisan?: LitigeActor;
}

export interface LitigeItem {
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

export interface AdminMissionParty {
    name: string;
    phone?: string;
}

export interface AdminMission {
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

export interface AdminOrderItem {
    id: number;
    order_id: number;
    supplier_product_id: number;
    quantity: number;
    unit_price: number;
    product?: {
        id: number;
        name: string;
        price: number;
        unit?: string;
    } | null;
}

export interface AdminOrder {
    id: number;
    client_id: number;
    supplier_id: number;
    driver_id?: number | null;
    delivery_mode: string;
    status: string;
    subtotal: number;
    delivery_cost: number;
    platform_fee: number;
    total_amount: number;
    pickup_code: string;
    reception_code: string;
    vehicle_class?: string | null;
    surge_multiplier?: number | null;
    delivered_at?: string | null;
    pickup_photo_url?: string | null;
    delivery_photo_url?: string | null;
    waiting_time_minutes?: number | null;
    dispute_reason?: string | null;
    dispute_opened_at?: string | null;
    created_at: string;
    client?: {
        id: number;
        name: string;
        phone: string;
        role: string;
    } | null;
    supplier?: {
        id: number;
        name: string;
        phone: string;
        role: string;
        fournisseur_agree?: {
            id: number;
            nom_boutique: string;
            statut: string;
        } | null;
    } | null;
    driver?: {
        id: number;
        name: string;
        phone: string;
        role: string;
    } | null;
    items?: AdminOrderItem[];
    transactions?: any[];
}

export interface FournisseurUser {
    name: string;
    phone: string;
}

export interface FournisseurItem {
    id: number;
    nom_boutique: string;
    created_at: string;
    user?: FournisseurUser;
}

export interface AdminUser {
    id: number;
    name: string;
    email?: string | null;
    phone: string;
    role: string;
    kyc_status: string;
    score_prosartisan: number;
    created_at: string;
    missions_client_count: number;
    missions_artisan_count: number;
    account_status?: string | null;
    account_status_reason?: string | null;
    score_frozen?: boolean;
    device_fingerprint?: string | null;
    cgu_accepted_at?: string | null;
    anonymized_at?: string | null;
}

/** Instantané de santé opérationnelle (Chantier C7 / P2-12). */
export interface ObservabilitySnapshot {
    queue: {
        pending: number;
        failed: number;
        oldest_pending_minutes: number;
        recent: Array<{ id: number; uuid: string; queue: string; exception: string; failed_at: string | null }>;
    };
    payments: {
        failed_24h: number;
        failed_total: number;
        recent: Array<{
            id: number;
            provider: string;
            type: string;
            montant: number;
            reference: string | null;
            error: string | null;
            created_at: string | null;
        }>;
    };
    fraud: {
        gps_attempts_7d: number;
        gps_attempts_total: number;
        unread_alerts: number;
        recent: Array<{
            id: number;
            user: string | null;
            phone: string | null;
            mission_id: number | null;
            description: string | null;
            created_at: string | null;
        }>;
    };
    referent: {
        blocked: number;
        threshold: number;
        recent: Array<{
            id: number;
            client: string | null;
            artisan: string | null;
            status: string;
            montant_total: number;
            created_at: string | null;
        }>;
    };
    generated_at: string;
}

/** Vue RGPD des données personnelles d'un utilisateur (Chantier C6 / P2-11). */
export interface PersonalDataReport {
    user: {
        id: number;
        name: string;
        email: string | null;
        phone: string;
        role: string;
        kyc_status: string;
        account_status: string | null;
        created_at: string | null;
        cgu_accepted_at: string | null;
        anonymized_at: string | null;
        payment_phone: string | null;
        cnmci_number: string | null;
        device_fingerprint: string | null;
        commune: string | null;
    };
    position: { lat: number; lng: number } | null;
    kyc_documents: Array<{ id: number; type: string; status: string | null; created_at: string | null }>;
    evaluations_given: number;
    evaluations_received: number;
    missions_as_client: number;
    missions_as_artisan: number;
    transactions_count: number;
    notifications_count: number;
    parrainages_count: number;
    activity_trace: Array<{ action: string; created_at: string | null; ip_address: string | null }>;
}

export interface AdminTransaction {
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

export interface AdminEvaluation {
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
        score_prosartisan: number;
        score_frozen: boolean;
    } | null;
}

export interface ArtisanScoreItem {
    id: number;
    name: string;
    phone: string;
    score_prosartisan: number;
    score_frozen: boolean;
    evaluations_recues_count: number;
    evaluations_recues_avg_fiabilite?: number | string | null;
    evaluations_recues_avg_integrite?: number | string | null;
    evaluations_recues_avg_qualite?: number | string | null;
    evaluations_recues_avg_reactivite?: number | string | null;
}

export interface ScoreLedgerEntryItem {
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

export interface SettingItem {
    id: number;
    key: string;
    value: string;
    type: string;
    group: string;
    label: string;
    description: string;
}

export interface TradeItem {
    id: number;
    sector_id: number;
    name: string;
}

export interface SectorItem {
    id: number;
    name: string;
    trades?: TradeItem[];
}

// Journal d'audit des actions administrateur (Chantier C3 / P0-4).
export interface AdminActivityLogItem {
    id: number;
    admin_id: number | null;
    admin_name: string | null;
    action: string;
    subject_type: string | null;
    subject_id: number | null;
    subject_label: string | null;
    context: Record<string, unknown> | null;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string;
    admin?: { id: number; name: string; phone: string } | null;
}

export interface AuditAdminOption {
    admin_id: number;
    admin_name: string | null;
}

export interface PaginatedAuditLogs {
    data: AdminActivityLogItem[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

// Réponse `LengthAwarePaginator` sérialisée par Inertia (Chantier C4 / P1-6).
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    from: number | null;
    to: number | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

export interface UserStats {
    total: number;
    artisans_actifs: number;
    clients_actifs: number;
    fournisseurs_agrees: number;
}

export interface TransactionStats {
    pending: number;
    failed: number;
    confirmed: number;
    volume_24h: number;
    escrow: number;
    released: number;
}

export interface LitigeStats {
    open: number;
    resolved: number;
    high_risk: number;
    missions_disputed: number;
}

export interface EvaluationStats {
    evaluations_total: number;
    note_moyenne: number;
    artisans_suivis: number;
    scores_geles: number;
}

export interface MissionStats {
    en_cours: number;
    en_litige: number;
    referent_required: number;
    enrichies: number;
}

export interface DeliveryStats {
    total: number;
    in_transit: number;
    awaiting_driver: number;
    delivered: number;
    by_status: Record<string, number>;
}

export interface KycStats {
    pending: number;
    artisans_pending: number;
    fournisseurs_pending: number;
    rejected: number;
    registration_trend: Array<{ label: string; value: number }>;
}
