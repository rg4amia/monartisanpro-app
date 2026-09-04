// Calcul dérivé du backoffice (filtres de recherche, tendances, métriques) — extrait de console.tsx (Chantier C2).

import { useMemo } from 'react';

import {
    buildTimeline,
    money,
    normalizeSearch,
    numberFormat,
    roleLabels,
    transactionTypeLabels,
} from '../shared';
import type {
    AdminEvaluation,
    AdminMission,
    AdminOrder,
    AdminTransaction,
    AdminUser,
    ArtisanScoreItem,
    DashboardData,
    FournisseurItem,
    KycUser,
    LitigeItem,
    MetricItem,
    PromoCodeItem,
} from '../shared';

function sumAmount(items: AdminTransaction[]): number {
    return items.reduce((sum, item) => sum + item.montant, 0);
}

interface AdminAnalyticsInput {
    dashboard: DashboardData;
    deferredSearch: string;
    fournisseurs: FournisseurItem[];
    kycUsers: KycUser[];
    litiges: LitigeItem[];
    missions: AdminMission[];
    orders: AdminOrder[];
    deliveryStatusFilter: string;
    transactions: AdminTransaction[];
    users: AdminUser[];
    evaluationsList: AdminEvaluation[];
    artisansScores: ArtisanScoreItem[];
    promoCodes: PromoCodeItem[];
    now: number;
}

export function useAdminAnalytics({
    dashboard,
    deferredSearch,
    fournisseurs,
    kycUsers,
    litiges,
    missions,
    orders,
    deliveryStatusFilter,
    transactions,
    users,
    evaluationsList,
    artisansScores,
    promoCodes,
    now,
}: AdminAnalyticsInput) {
    return useMemo(() => {
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
                : normalizeSearch([user.id, user.name, user.email, user.phone, user.role, user.kyc_status, user.score_prosartisan]).includes(
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
            .sort((left, right) => right.score_prosartisan - left.score_prosartisan)
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
                    artisan.score_prosartisan,
                ]).includes(deferredSearch),
        );

        const filteredPromoCodes = (promoCodes || []).filter((promo) =>
            deferredSearch === ''
                ? true
                : normalizeSearch([
                    promo.id,
                    promo.code,
                    promo.description,
                    promo.discount_type,
                    promo.discount_value,
                ]).includes(deferredSearch),
        );

        const filteredOrders = (orders || []).filter((order) => {
            const matchesStatus = deliveryStatusFilter === 'all' || order.status === deliveryStatusFilter;
            const matchesSearch = deferredSearch === '' || normalizeSearch([
                order.id,
                order.pickup_code,
                order.reception_code,
                order.status,
                order.delivery_mode,
                order.client?.name,
                order.client?.phone,
                order.driver?.name,
                order.driver?.phone,
                order.supplier?.name,
                order.supplier?.phone,
                order.supplier?.fournisseur_agree?.nom_boutique,
                ...(order.items || []).map((i) => i.product?.name ?? ''),
            ]).includes(deferredSearch);

            return matchesStatus && matchesSearch;
        });

        const deliveryMetrics: MetricItem[] = [
            {
                description: 'Courses & livraisons enregistrées',
                title: 'Total livraisons',
                tone: 'blue',
                value: numberFormat.format(orders?.length ?? 0),
            },
            {
                description: 'Livreur en route / Colis récupéré',
                title: 'En transit',
                tone: 'purple',
                value: numberFormat.format((orders || []).filter((o) => ['shipping', 'driver_picked_up'].includes(o.status)).length),
            },
            {
                description: 'Recherche ou assignation coursier',
                title: 'En attente livreur',
                tone: 'amber',
                value: numberFormat.format((orders || []).filter((o) => ['searching_driver', 'driver_assigned', 'prepared'].includes(o.status)).length),
            },
            {
                description: 'Remises validées avec succès',
                title: 'Livrées & Clôturées',
                tone: 'green',
                value: numberFormat.format((orders || []).filter((o) => o.status === 'delivered').length),
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
            filteredOrders,
            deliveryMetrics,
            filteredTransactions,
            filteredUsers,
            filteredEvaluations,
            filteredArtisansScores,
            filteredPromoCodes,
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
    }, [dashboard, deferredSearch, fournisseurs, kycUsers, litiges, missions, orders, deliveryStatusFilter, transactions, users, evaluationsList, artisansScores, promoCodes, now]);
}
