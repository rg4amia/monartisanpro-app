// Onglet « Notifications & Alertes » du backoffice — extrait de console.tsx (Chantier C2).

import type { FormEvent, ReactNode } from 'react';

import { cn } from '@/lib/utils';

import { BellIcon, DataTable, EmptyState, shortDate, Surface } from '../shared';
import type { AdminNotificationItem, NotifFilter } from '../shared';

interface NotificationsPanelProps {
    notifTab: 'alerts' | 'history';
    onNotifTabChange: (tab: 'alerts' | 'history') => void;
    liveNotificationsCount: number;
    unreadNotifsCount: number;
    notifFilter: NotifFilter;
    onNotifFilterChange: (filter: NotifFilter) => void;
    filteredNotifs: AdminNotificationItem[];
    onMarkAllRead: () => void;
    onMarkNotifRead: (notif: AdminNotificationItem) => void;
    searchNotif: string;
    onSearchNotifChange: (value: string) => void;
    roleNotif: string;
    onRoleNotifChange: (value: string) => void;
    typeNotif: string;
    onTypeNotifChange: (value: string) => void;
    onFilterSubmit: (event: FormEvent) => void;
    onFilterReset: () => void;
    allNotifications: { data?: any[]; links?: any[] } | undefined;
    renderPagination: (links: any) => ReactNode;
}

export function NotificationsPanel({
    notifTab,
    onNotifTabChange,
    liveNotificationsCount,
    unreadNotifsCount,
    notifFilter,
    onNotifFilterChange,
    filteredNotifs,
    onMarkAllRead,
    onMarkNotifRead,
    searchNotif,
    onSearchNotifChange,
    roleNotif,
    onRoleNotifChange,
    typeNotif,
    onTypeNotifChange,
    onFilterSubmit,
    onFilterReset,
    allNotifications,
    renderPagination,
}: NotificationsPanelProps) {
    return (
        <section className="mt-5 space-y-5">
            <div className="flex gap-2 border-b border-[var(--admin-border)] pb-4">
                <button
                    type="button"
                    onClick={() => onNotifTabChange('alerts')}
                    className={cn(
                        'rounded-xl px-4 py-2 text-sm font-semibold transition',
                        notifTab === 'alerts'
                            ? 'bg-[#ebb95e] text-[#241b16]'
                            : 'text-[var(--admin-text-soft)] hover:bg-white/40',
                    )}
                >
                    Mes Alertes Admin ({liveNotificationsCount})
                </button>
                <button
                    type="button"
                    onClick={() => onNotifTabChange('history')}
                    className={cn(
                        'rounded-xl px-4 py-2 text-sm font-semibold transition',
                        notifTab === 'history'
                            ? 'bg-[#ebb95e] text-[#241b16]'
                            : 'text-[var(--admin-text-soft)] hover:bg-white/40',
                    )}
                >
                    Historique Global & Audit
                </button>
            </div>

            {notifTab === 'alerts' ? (
                <Surface className="rounded-[32px] p-5 lg:p-6">
                    <div className="flex items-center justify-between gap-4 pb-4 border-b border-[var(--admin-border)]">
                        <div>
                            <h3 className="text-lg font-bold text-[var(--admin-text)]">Centre de Notifications & Alertes</h3>
                            <p className="text-xs text-[var(--admin-text-soft)]">Toutes les alertes système, alertes KYC, litiges et anomalies de sécurité de la plateforme ProsArtisan.</p>
                        </div>
                        <button
                            type="button"
                            onClick={onMarkAllRead}
                            className="admin-button admin-button--secondary text-xs px-4 py-2"
                        >
                            Tout marquer comme lu ({unreadNotifsCount})
                        </button>
                    </div>

                    <div className="mt-4 flex gap-2 border-b border-[var(--admin-border)] pb-3">
                        <button
                            type="button"
                            onClick={() => onNotifFilterChange('all')}
                            className={cn('rounded-xl px-3 py-1.5 text-xs font-semibold transition', notifFilter === 'all' ? 'bg-[#ebb95e] text-[#241b16]' : 'text-[var(--admin-text-soft)] hover:bg-white/40')}
                        >
                            Toutes ({liveNotificationsCount})
                        </button>
                        <button
                            type="button"
                            onClick={() => onNotifFilterChange('unread')}
                            className={cn('rounded-xl px-3 py-1.5 text-xs font-semibold transition', notifFilter === 'unread' ? 'bg-[#ebb95e] text-[#241b16]' : 'text-[var(--admin-text-soft)] hover:bg-white/40')}
                        >
                            Non lues ({unreadNotifsCount})
                        </button>
                        <button
                            type="button"
                            onClick={() => onNotifFilterChange('alerts')}
                            className={cn('rounded-xl px-3 py-1.5 text-xs font-semibold transition', notifFilter === 'alerts' ? 'bg-[#ebb95e] text-[#241b16]' : 'text-[var(--admin-text-soft)] hover:bg-white/40')}
                        >
                            Alertes critiques
                        </button>
                    </div>

                    <div className="mt-4 space-y-3">
                        {filteredNotifs.length > 0 ? (
                            filteredNotifs.map((n) => (
                                <div key={n.id} className={cn('flex items-start justify-between gap-4 p-4 rounded-2xl border transition', !n.read_at ? 'bg-amber-500/10 border-amber-500/30' : 'bg-white/40 border-[var(--admin-border)]')}>
                                    <div className="flex gap-3">
                                        <div className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#f8e4bc] text-[#b77918]">
                                            <BellIcon className="h-5 w-5" />
                                        </div>
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <h4 className="text-sm font-bold text-[var(--admin-text)]">{n.title}</h4>
                                                {!n.read_at && <span className="h-2 w-2 rounded-full bg-amber-500"></span>}
                                            </div>
                                            <p className="mt-1 text-xs text-[var(--admin-text-soft)]">{n.body}</p>
                                            <span className="mt-2 block text-[10px] text-[var(--admin-muted)]">{shortDate(n.created_at)}</span>
                                        </div>
                                    </div>
                                    {n.action_url && (
                                        <button
                                            type="button"
                                            onClick={() => onMarkNotifRead(n)}
                                            className="admin-button admin-button--primary text-xs px-3 py-1.5 shrink-0"
                                        >
                                            {n.action_label || 'Consulter'}
                                        </button>
                                    )}
                                </div>
                            ))
                        ) : (
                            <p className="py-8 text-center text-sm text-[var(--admin-text-soft)]">Aucune notification disponible.</p>
                        )}
                    </div>
                </Surface>
            ) : (
                <Surface className="rounded-[32px] p-5 lg:p-6 border border-[#e2d5c3]/60 bg-gradient-to-br from-white via-[#fcfaf7] to-[#f7f2ea]">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-[var(--admin-border)]">
                        <div>
                            <h3 className="text-lg font-bold text-[var(--admin-text)]">Historique Global & Preuve des échanges</h3>
                            <p className="text-xs text-[var(--admin-text-soft)]">Suivi de toutes les interactions et notifications envoyées entre clients, artisans, fournisseurs et livreurs.</p>
                        </div>
                    </div>

                    {/* FILTRES */}
                    <form onSubmit={onFilterSubmit} className="mt-5 grid gap-4 md:grid-cols-4 items-end bg-white/40 p-4 rounded-2xl border border-[var(--admin-border)]">
                        <div>
                            <label className="block text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)] mb-1.5">Rechercher</label>
                            <input
                                type="text"
                                placeholder="Message, nom, téléphone..."
                                value={searchNotif}
                                onChange={(e) => onSearchNotifChange(e.target.value)}
                                className="w-full rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                            />
                        </div>
                        <div>
                            <label className="block text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)] mb-1.5">Rôle destinataire</label>
                            <select
                                value={roleNotif}
                                onChange={(e) => onRoleNotifChange(e.target.value)}
                                className="w-full rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                            >
                                <option value="">Tous les rôles</option>
                                <option value="client">Client</option>
                                <option value="artisan">Artisan</option>
                                <option value="fournisseur">Fournisseur (Quincaillerie)</option>
                                <option value="livreur">Livreur (Driver)</option>
                                <option value="referent">Référent de zone</option>
                                <option value="admin">Administrateur</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-[10px] font-bold uppercase tracking-wider text-[var(--admin-muted)] mb-1.5">Type de notification</label>
                            <select
                                value={typeNotif}
                                onChange={(e) => onTypeNotifChange(e.target.value)}
                                className="w-full rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                            >
                                <option value="">Tous les types</option>
                                <option value="otp">OTP (SMS de validation)</option>
                                <option value="payment">Paiements / Séquestre</option>
                                <option value="litige">Litiges & Arbitrages</option>
                                <option value="kyc">KYC / Inscriptions</option>
                                <option value="mission">Missions / Devis</option>
                                <option value="fraud_alert">Alertes Anti-Fraude</option>
                            </select>
                        </div>
                        <div className="flex gap-2">
                            <button
                                type="submit"
                                className="flex-1 bg-[#ebb95e] hover:bg-[#dca850] text-[#241b16] font-bold rounded-xl text-xs px-4 py-2 transition"
                            >
                                Filtrer
                            </button>
                            <button
                                type="button"
                                onClick={onFilterReset}
                                className="bg-white/60 hover:bg-white/80 border border-[var(--admin-border)] text-[var(--admin-text-soft)] font-bold rounded-xl text-xs px-3 py-2 transition"
                            >
                                Réinitialiser
                            </button>
                        </div>
                    </form>

                    {/* TABLEAU */}
                    <DataTable className="mt-5">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Destinataire</th>
                                <th>Type</th>
                                <th>Notification</th>
                                <th>Données (Audit/Litiges)</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(!allNotifications?.data || allNotifications.data.length === 0) ? (
                                <tr>
                                    <td colSpan={5}>
                                        <EmptyState description="Aucune notification trouvée dans l'historique global." title="Aucune notification" />
                                    </td>
                                </tr>
                            ) : (
                                allNotifications.data.map((notif: any) => (
                                    <tr key={notif.id} className="hover:bg-black/[0.02] transition">
                                        <td className="text-xs text-[var(--admin-text-soft)] font-mono shrink-0 whitespace-nowrap">
                                            {shortDate(notif.created_at)}
                                        </td>
                                        <td>
                                            {notif.user ? (
                                                <div>
                                                    <p className="font-semibold text-[var(--admin-text)] text-xs">{notif.user.name}</p>
                                                    <p className="text-[10px] text-[var(--admin-muted)]">{notif.user.phone} ({notif.user.role})</p>
                                                </div>
                                            ) : (
                                                <p className="text-xs text-[var(--admin-muted)]">Système / Tous</p>
                                            )}
                                        </td>
                                        <td>
                                            <span className={cn(
                                                'px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider shrink-0',
                                                notif.type === 'otp' && 'bg-rose-100 text-rose-700 border border-rose-300',
                                                notif.type === 'payment' && 'bg-emerald-100 text-emerald-700 border border-emerald-300',
                                                notif.type === 'litige' && 'bg-red-100 text-red-700 border border-red-300',
                                                notif.type === 'kyc' && 'bg-blue-100 text-blue-700 border border-blue-300',
                                                notif.type === 'mission' && 'bg-indigo-100 text-indigo-700 border border-indigo-300',
                                                notif.type === 'fraud_alert' && 'bg-amber-100 text-amber-700 border border-amber-300',
                                            )}>
                                                {notif.type}
                                            </span>
                                        </td>
                                        <td className="max-w-xs md:max-w-md">
                                            <p className="font-bold text-xs text-[var(--admin-text)]">{notif.title}</p>
                                            <p className="text-[11px] text-[var(--admin-text-soft)] mt-0.5 whitespace-pre-line">{notif.body}</p>
                                        </td>
                                        <td>
                                            {notif.data_json && Object.keys(notif.data_json).length > 0 ? (
                                                <details className="cursor-pointer text-[10px] text-amber-800 font-mono">
                                                    <summary className="hover:underline text-[9px] font-bold text-amber-700">Voir JSON ({Object.keys(notif.data_json).length})</summary>
                                                    <pre className="mt-1 p-2 bg-black/[0.03] border border-black/10 rounded-lg overflow-x-auto text-[9px] leading-tight">
                                                        {JSON.stringify(notif.data_json, null, 2)}
                                                    </pre>
                                                </details>
                                            ) : (
                                                <span className="text-[9px] text-[var(--admin-muted)]">-</span>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </DataTable>

                    {/* PAGINATION */}
                    {renderPagination(allNotifications?.links)}
                </Surface>
            )}
        </section>
    );
}
