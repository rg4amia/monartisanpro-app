import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type React from 'react';
import { tabRoutes } from '../shared';
import type { AdminNotificationItem, DashboardData } from '../shared';

export type NotifFilter = 'all' | 'unread' | 'alerts';

const ALERT_TYPES = ['kyc', 'litige', 'fraud', 'fraud_alert', 'referent'];

/**
 * Notifications admin persistées + alertes synthétiques dérivées des KPI du
 * tableau de bord (KYC en attente, litiges, fraude GPS, seuil Référent).
 */
export function buildLiveNotifications(adminNotifications: AdminNotificationItem[] | undefined, dashboard: DashboardData): AdminNotificationItem[] {
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
}

export function useAdminNotifications(adminNotifications: AdminNotificationItem[] | undefined, dashboard: DashboardData) {
    const params = new URLSearchParams(window.location.search);
    const [notificationsOpen, setNotificationsOpen] = useState<boolean>(false);
    const [notifFilter, setNotifFilter] = useState<NotifFilter>('all');
    const [notifTab, setNotifTab] = useState<'alerts' | 'history'>('alerts');
    const [searchNotif, setSearchNotif] = useState(params.get('search_notification') || '');
    const [roleNotif, setRoleNotif] = useState(params.get('role_notification') || '');
    const [typeNotif, setTypeNotif] = useState(params.get('type_notification') || '');

    const liveNotifications = useMemo(() => buildLiveNotifications(adminNotifications, dashboard), [adminNotifications, dashboard]);

    const unreadNotifsCount = useMemo(() => liveNotifications.filter((n) => !n.read_at).length, [liveNotifications]);

    const filteredNotifs = useMemo(() => {
        return liveNotifications.filter((n) => {
            if (notifFilter === 'unread') return !n.read_at;
            if (notifFilter === 'alerts') return ALERT_TYPES.includes(n.type);
            return true;
        });
    }, [liveNotifications, notifFilter]);

    const markAllRead = () => {
        router.post('/admin/notifications/mark-all-read', {}, { preserveScroll: true });
    };

    const markRead = (notif: AdminNotificationItem) => {
        if (typeof notif.id === 'number') {
            router.post(`/admin/notifications/${notif.id}/read`, {}, { preserveScroll: true });
        }
        if (notif.action_url) {
            setNotificationsOpen(false);
            router.visit(notif.action_url);
        }
    };

    const historyOnly = { preserveState: true, preserveScroll: true, only: ['allNotifications'] };

    const applyHistoryFilters = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/admin/notifications',
            { search_notification: searchNotif, role_notification: roleNotif, type_notification: typeNotif },
            historyOnly,
        );
    };

    const resetHistoryFilters = () => {
        setSearchNotif('');
        setRoleNotif('');
        setTypeNotif('');
        router.get('/admin/notifications', {}, historyOnly);
    };

    return {
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
        markAllRead,
        markRead,
        applyHistoryFilters,
        resetHistoryFilters,
    };
}
