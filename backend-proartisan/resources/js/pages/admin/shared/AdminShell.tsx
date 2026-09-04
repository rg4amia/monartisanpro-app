// Chrome du backoffice (sidebar, header, centre de notifications, hero) — extrait de console.tsx (Chantier C2).

import { Head, Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

import { BottomDock } from './BottomDock';
import { searchPlaceholders, tabMeta, tabRoutes } from './constants';
import { fullDate, getInitials, toneBadgeClasses, toneIconClasses } from './format';
import { AlertIcon, BellIcon, CheckCircleIcon, ClipboardIcon, CloseIcon, LogoutIcon, MenuIcon, MoonIcon, RefreshIcon, SearchIcon, ShieldIcon, SunIcon, TabIcon, ToneIcon, WalletIcon } from './icons';
import { useInertiaVisit } from './loading';
import type { AdminNotificationItem, AdminTab, ExchangeRates, FlashMessages, MetricItem, NavigationGroup, NotifFilter, ThemeMode } from './types';
import { InfoRow, Surface } from './ui';

interface AdminShellProps {
    activeTab: AdminTab;
    navigation: NavigationGroup[];
    heroStats: Array<Pick<MetricItem, 'tone'> & { label: string; value: string }>;
    themeMode: ThemeMode;
    onToggleTheme: () => void;
    isMobileSidebarOpen: boolean;
    onMobileSidebarChange: (open: boolean) => void;
    search: string;
    onSearchChange: (value: string) => void;
    onRefresh: () => void;
    refreshing: boolean;
    actionLoading: boolean;
    offlineActive: boolean;
    exchangeRates: ExchangeRates | null;
    notificationsOpen: boolean;
    onNotificationsOpenChange: (open: boolean) => void;
    notifFilter: NotifFilter;
    onNotifFilterChange: (filter: NotifFilter) => void;
    liveNotifications: AdminNotificationItem[];
    filteredNotifs: AdminNotificationItem[];
    unreadNotifsCount: number;
    onMarkAllNotifsRead: () => void;
    onMarkNotifRead: (notif: AdminNotificationItem) => void;
    adminName: string;
    adminContact: string;
    flash?: FlashMessages;
    bannerError?: string | null;
    /**
     * Contenu de l'onglet actif. Les modales de la console sont passées ici en fin de
     * `children` : rendues dans `<main>` (donc sous `.admin-shell`), leur positionnement
     * `fixed` les sort du flux et elles héritent des variables CSS de thème.
     */
    children: ReactNode;
}

export function AdminShell({
    activeTab,
    navigation,
    heroStats,
    themeMode,
    onToggleTheme,
    isMobileSidebarOpen,
    onMobileSidebarChange,
    search,
    onSearchChange,
    onRefresh,
    refreshing,
    actionLoading,
    offlineActive,
    exchangeRates,
    notificationsOpen,
    onNotificationsOpenChange,
    notifFilter,
    onNotifFilterChange,
    liveNotifications,
    filteredNotifs,
    unreadNotifsCount,
    onMarkAllNotifsRead,
    onMarkNotifRead,
    adminName,
    adminContact,
    flash,
    bannerError,
    children,
}: AdminShellProps) {
    const navPending = useInertiaVisit();

    return (
        <>
            <Head title="ProsArtisan Backoffice" />

            <div className={cn('admin-shell min-h-screen', themeMode === 'dark' && 'admin-shell--dark')}>
                <div className="relative z-10 flex min-h-screen">
                    {isMobileSidebarOpen && (
                        <div
                            className="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm lg:hidden"
                            onClick={() => onMobileSidebarChange(false)}
                        />
                    )}

                    <aside className={cn(
                        'admin-panel shrink-0 border-r px-5 py-6 bg-[var(--admin-bg)] transition-all duration-300',
                        'fixed inset-y-0 left-0 z-50 w-[310px] flex flex-col lg:static lg:h-auto lg:translate-x-0',
                        isMobileSidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
                        'lg:flex lg:flex-col',
                    )}>
                        <div className="flex items-center justify-between gap-3">
                            <div className="flex items-center gap-3">
                                <div className="flex h-12 w-auto px-2.5 items-center justify-center rounded-2xl bg-white text-[#241b16] shadow-sm border border-[var(--admin-border)]">
                                    <img src="/img/prosartisan-logo.png" alt="ProsArtisan" className="h-7 w-auto object-contain" />
                                </div>
                                <div className="min-w-0">
                                    <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-[var(--admin-muted)]">ProsArtisan</p>
                                    <div className="flex items-center gap-2">
                                        <h1 className="truncate text-xl font-semibold text-[var(--admin-text)]">Backoffice</h1>
                                        <span className={cn('rounded-full border px-2 py-0.5 text-[11px] font-semibold', toneBadgeClasses('amber'))}>ADMIN</span>
                                    </div>
                                </div>
                            </div>

                            <button
                                type="button"
                                className="flex h-10 w-10 items-center justify-center rounded-xl border border-[var(--admin-border)] bg-white/50 text-[var(--admin-text)] lg:hidden hover:bg-white/80 transition"
                                onClick={() => onMobileSidebarChange(false)}
                                aria-label="Fermer le menu"
                            >
                                <CloseIcon className="h-5 w-5" />
                            </button>
                        </div>

                        <div className="mt-8 space-y-7">
                            {navigation.map((group) => (
                                <div key={group.label}>
                                    <p className="mb-3 px-3 text-[11px] font-semibold uppercase tracking-[0.24em] text-[var(--admin-muted)]">
                                        {group.label}
                                    </p>
                                    <div className="space-y-1.5">
                                        {group.items.map((item) => {
                                            const isActive = item.id === activeTab;

                                            return (
                                                <Link
                                                    key={item.id}
                                                    href={tabRoutes[item.id]}
                                                    className={cn(
                                                        'admin-nav-link flex items-center justify-between gap-3 rounded-2xl px-3 py-3 text-sm font-medium transition',
                                                        isActive && 'admin-nav-link--active',
                                                    )}
                                                >
                                                    <span className="flex min-w-0 items-center gap-3">
                                                        <span
                                                            className={cn(
                                                                'flex h-9 w-9 items-center justify-center rounded-xl',
                                                                isActive ? 'bg-[#f8e4bc] text-[#b77918]' : 'bg-[#f5ecdf] text-[var(--admin-muted)]',
                                                            )}
                                                        >
                                                            <TabIcon tab={item.id} />
                                                        </span>
                                                        <span className="truncate">{item.label}</span>
                                                    </span>

                                                    {typeof item.count === 'number' && item.count > 0 ? (
                                                        <span className="rounded-full bg-white/80 px-2.5 py-1 text-xs font-semibold text-[#8a6b3d]">
                                                            {item.count}
                                                        </span>
                                                    ) : null}
                                                </Link>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div className="mt-auto space-y-4">
                            <Surface className="rounded-[28px] p-4">
                                <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-[var(--admin-muted)]">Contexte marché</p>
                                <div className="mt-3 space-y-3 text-sm text-[var(--admin-text-soft)]">
                                    <InfoRow label="Pays" value="Côte d’Ivoire" />
                                    <InfoRow label="Devise" value="FCFA" />
                                    <InfoRow label="Paiements" value="Wave CI, Orange Money CI" />
                                    <InfoRow label="Connectivité" value="Mode hors-ligne + USSD" />
                                </div>
                                <div className="border-t border-[var(--admin-border)] pt-3 mt-3">
                                    <p className="text-[10px] font-semibold uppercase tracking-wider text-[var(--admin-muted)] mb-2">Cours des devises</p>
                                    <div className="grid grid-cols-1 gap-2 text-xs">
                                        <div className="flex justify-between items-center bg-white/45 rounded-xl px-2.5 py-1.5 border border-[var(--admin-border)]">
                                            <span className="font-medium text-[var(--admin-text)]">1 EUR</span>
                                            <span className="font-bold text-[#8a6b3d]">{exchangeRates ? `${exchangeRates.eurToXof} XOF` : '655.96 XOF'}</span>
                                        </div>
                                        <div className="flex justify-between items-center bg-white/45 rounded-xl px-2.5 py-1.5 border border-[var(--admin-border)]">
                                            <span className="font-medium text-[var(--admin-text)]">1 USD</span>
                                            <span className="font-bold text-[#8a6b3d]">{exchangeRates ? `${exchangeRates.usdToXof} XOF` : '605.50 XOF'}</span>
                                        </div>
                                        <div className="flex justify-between items-center bg-white/45 rounded-xl px-2.5 py-1.5 border border-[var(--admin-border)]">
                                            <span className="font-medium text-[var(--admin-text)]">1 EUR</span>
                                            <span className="font-bold text-[#8a6b3d]">{exchangeRates ? `${exchangeRates.eurToUsd} USD` : '1.0850 USD'}</span>
                                        </div>
                                    </div>
                                </div>
                            </Surface>

                            <Link
                                href={tabRoutes.settings}
                                className="flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-medium text-[var(--admin-text-soft)] transition hover:bg-white/50"
                            >
                                <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#f5ecdf] text-[var(--admin-muted)]">
                                    <TabIcon tab="settings" />
                                </span>
                                Paramètres
                            </Link>
                        </div>
                    </aside>

                    <div className="min-w-0 flex-1">
                        {offlineActive && (
                            <div className="bg-amber-500 text-[#241b16] px-4 py-2 text-center text-xs font-semibold flex items-center justify-center gap-2 shadow-inner border-b border-amber-600 animate-pulse">
                                <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                    <path d="M18.36 5.64a9 9 0 0 1 0 12.73m-2.82-9.9a6 6 0 0 1 0 7.07m-2.83-4.24a3 3 0 0 1 0 1.41m.01-1.42v.01" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                                <span>Mode hors-ligne actif • ProsArtisan bascule automatiquement sur les files d'attente locales et les interactions USSD.</span>
                            </div>
                        )}
                        <header className="admin-panel sticky top-0 z-20 border-b px-4 py-4 lg:px-7">
                            <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                                <div className="flex min-w-0 flex-1 items-center gap-3">
                                    <button
                                        type="button"
                                        className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-[var(--admin-border)] bg-white/50 text-[var(--admin-text)] lg:hidden hover:bg-white/80 transition"
                                        onClick={() => onMobileSidebarChange(true)}
                                        aria-label="Ouvrir le menu"
                                    >
                                        <MenuIcon className="h-6 w-6" />
                                    </button>

                                    <div className="admin-input flex w-full items-center gap-3 rounded-2xl px-4 py-3 xl:max-w-[420px]">
                                        <SearchIcon className="h-5 w-5 text-[var(--admin-muted)]" />
                                        <input
                                            value={search}
                                            onChange={(event) => onSearchChange(event.target.value)}
                                            placeholder={searchPlaceholders[activeTab]}
                                            className="w-full bg-transparent text-sm text-[var(--admin-text)] outline-none placeholder:text-[var(--admin-muted)]"
                                        />
                                    </div>

                                    <div className="hidden items-center gap-2 xl:flex">
                                        <button type="button" className="admin-button admin-button--ghost" onClick={onRefresh}>
                                            <RefreshIcon className="h-4 w-4" />
                                            Rafraîchir
                                        </button>
                                        <span className={cn('rounded-full border px-3 py-2 text-xs font-semibold', toneBadgeClasses('slate'))}>
                                            Session web active
                                        </span>
                                    </div>
                                </div>

                                <div className="flex items-center justify-between gap-3">
                                    <button
                                        type="button"
                                        className="admin-button admin-button--ghost"
                                        onClick={onToggleTheme}
                                    >
                                        {themeMode === 'light' ? <MoonIcon className="h-4 w-4" /> : <SunIcon className="h-4 w-4" />}
                                        {themeMode === 'light' ? 'Sombre' : 'Clair'}
                                    </button>

                                    <div className="relative">
                                        <button
                                            type="button"
                                            className={cn(
                                                'relative rounded-2xl border p-3 text-[var(--admin-muted)] transition hover:bg-white/55',
                                                notificationsOpen ? 'border-[#ebb95e]/50 bg-white/70 text-[#241b16]' : 'border-transparent',
                                            )}
                                            title="Notifications et alertes système"
                                            aria-label="Notifications"
                                            onClick={() => onNotificationsOpenChange(!notificationsOpen)}
                                        >
                                            <BellIcon className="h-5 w-5" />
                                            {unreadNotifsCount > 0 && (
                                                <span className="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-[#f15f57] px-1 text-[10px] font-extrabold text-white shadow-sm ring-2 ring-white">
                                                    {unreadNotifsCount > 99 ? '99+' : unreadNotifsCount}
                                                </span>
                                            )}
                                        </button>

                                        {notificationsOpen && (
                                            <>
                                                <div
                                                    className="fixed inset-0 z-40"
                                                    onClick={() => onNotificationsOpenChange(false)}
                                                />

                                                <div className="absolute right-0 top-full mt-3 z-50 w-[360px] sm:w-[420px] max-w-[95vw] rounded-3xl border border-[var(--admin-border)] bg-[var(--admin-card)] shadow-2xl backdrop-blur-xl animate-in fade-in zoom-in-95 duration-150 overflow-hidden">
                                                    <div className="flex items-center justify-between border-b border-[var(--admin-border)]/60 bg-[var(--admin-card-header)]/80 px-4 py-3.5">
                                                        <div className="flex items-center gap-2">
                                                            <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-[#ebb95e]/20 text-[#8a5d16]">
                                                                <BellIcon className="h-4.5 w-4.5" />
                                                            </div>
                                                            <div>
                                                                <h3 className="text-sm font-bold text-[var(--admin-text)]">Centre de Notifications</h3>
                                                                <p className="text-[11px] text-[var(--admin-muted)]">
                                                                    {unreadNotifsCount > 0 ? `${unreadNotifsCount} alerte(s) non lue(s)` : 'Toutes les alertes sont traitées'}
                                                                </p>
                                                            </div>
                                                        </div>
                                                        {unreadNotifsCount > 0 && (
                                                            <button
                                                                type="button"
                                                                onClick={onMarkAllNotifsRead}
                                                                className="rounded-full bg-[#ebb95e]/15 px-2.5 py-1 text-[11px] font-semibold text-[#8a5d16] hover:bg-[#ebb95e]/25 transition"
                                                                title="Marquer tout comme lu"
                                                            >
                                                                Tout marquer lu
                                                            </button>
                                                        )}
                                                    </div>

                                                    <div className="flex border-b border-[var(--admin-border)]/40 bg-black/[0.02] px-3 py-2 gap-1.5 text-xs font-medium">
                                                        <button
                                                            type="button"
                                                            onClick={() => onNotifFilterChange('all')}
                                                            className={cn(
                                                                'rounded-xl px-2.5 py-1 transition',
                                                                notifFilter === 'all' ? 'bg-white text-[var(--admin-text)] font-semibold shadow-xs' : 'text-[var(--admin-muted)] hover:text-[var(--admin-text)]',
                                                            )}
                                                        >
                                                            Toutes ({liveNotifications.length})
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => onNotifFilterChange('unread')}
                                                            className={cn(
                                                                'rounded-xl px-2.5 py-1 transition',
                                                                notifFilter === 'unread' ? 'bg-white text-[var(--admin-text)] font-semibold shadow-xs' : 'text-[var(--admin-muted)] hover:text-[var(--admin-text)]',
                                                            )}
                                                        >
                                                            Non lues ({unreadNotifsCount})
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => onNotifFilterChange('alerts')}
                                                            className={cn(
                                                                'rounded-xl px-2.5 py-1 transition',
                                                                notifFilter === 'alerts' ? 'bg-white text-[var(--admin-text)] font-semibold shadow-xs' : 'text-[var(--admin-muted)] hover:text-[var(--admin-text)]',
                                                            )}
                                                        >
                                                            Alertes critiques
                                                        </button>
                                                    </div>

                                                    <div className="max-h-[380px] overflow-y-auto divide-y divide-[var(--admin-border)]/40">
                                                        {filteredNotifs.length === 0 ? (
                                                            <div className="flex flex-col items-center justify-center py-10 px-4 text-center">
                                                                <div className="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 mb-2">
                                                                    <CheckCircleIcon className="h-6 w-6" />
                                                                </div>
                                                                <p className="text-sm font-semibold text-[var(--admin-text)]">Aucune notification</p>
                                                                <p className="text-xs text-[var(--admin-muted)] mt-0.5">Vous êtes parfaitement à jour sur toutes les activités du système.</p>
                                                            </div>
                                                        ) : (
                                                            filteredNotifs.map((notif) => {
                                                                const isUnread = !notif.read_at;
                                                                return (
                                                                    <div
                                                                        key={notif.id}
                                                                        className={cn(
                                                                            'group flex items-start gap-3 p-3.5 transition hover:bg-black/[0.02]',
                                                                            isUnread ? 'bg-[#ebb95e]/[0.06]' : '',
                                                                        )}
                                                                    >
                                                                        <div className="shrink-0 mt-0.5">
                                                                            {notif.type === 'kyc' && (
                                                                                <div className="flex h-9 w-9 items-center justify-center rounded-2xl bg-amber-500/15 text-amber-600">
                                                                                    <ShieldIcon className="h-5 w-5" />
                                                                                </div>
                                                                            )}
                                                                            {['litige', 'fraud', 'fraud_alert'].includes(notif.type) && (
                                                                                <div className="flex h-9 w-9 items-center justify-center rounded-2xl bg-rose-500/15 text-rose-600">
                                                                                    <AlertIcon className="h-5 w-5" />
                                                                                </div>
                                                                            )}
                                                                            {['mission', 'referent'].includes(notif.type) && (
                                                                                <div className="flex h-9 w-9 items-center justify-center rounded-2xl bg-blue-500/15 text-blue-600">
                                                                                    <ClipboardIcon className="h-5 w-5" />
                                                                                </div>
                                                                            )}
                                                                            {['payment', 'transaction'].includes(notif.type) && (
                                                                                <div className="flex h-9 w-9 items-center justify-center rounded-2xl bg-emerald-500/15 text-emerald-600">
                                                                                    <WalletIcon className="h-5 w-5" />
                                                                                </div>
                                                                            )}
                                                                            {!['kyc', 'litige', 'fraud', 'fraud_alert', 'mission', 'referent', 'payment', 'transaction'].includes(notif.type) && (
                                                                                <div className="flex h-9 w-9 items-center justify-center rounded-2xl bg-[#ebb95e]/20 text-[#8a5d16]">
                                                                                    <BellIcon className="h-5 w-5" />
                                                                                </div>
                                                                            )}
                                                                        </div>

                                                                        <div className="min-w-0 flex-1">
                                                                            <div className="flex items-center justify-between gap-1 mb-0.5">
                                                                                <p className={cn('text-xs font-semibold truncate', isUnread ? 'text-[var(--admin-text)]' : 'text-[var(--admin-text)]/80')}>
                                                                                    {notif.title}
                                                                                </p>
                                                                                {isUnread && (
                                                                                    <span className="h-2 w-2 rounded-full bg-[#f15f57] shrink-0" />
                                                                                )}
                                                                            </div>
                                                                            <p className="text-xs text-[var(--admin-muted)] line-clamp-2 leading-relaxed">
                                                                                {notif.body}
                                                                            </p>
                                                                            <div className="mt-2 flex items-center justify-between gap-2">
                                                                                <span className="text-[10px] text-[var(--admin-muted)]">
                                                                                    {new Date(notif.created_at).toLocaleDateString('fr-FR', {
                                                                                        day: '2-digit',
                                                                                        month: 'short',
                                                                                        hour: '2-digit',
                                                                                        minute: '2-digit',
                                                                                    })}
                                                                                </span>

                                                                                <div className="flex items-center gap-1.5">
                                                                                    {notif.action_url ? (
                                                                                        <button
                                                                                            type="button"
                                                                                            onClick={() => onMarkNotifRead(notif)}
                                                                                            className="rounded-lg bg-[var(--admin-text)] text-[var(--admin-card)] px-2.5 py-1 text-[11px] font-semibold hover:opacity-90 transition cursor-pointer"
                                                                                        >
                                                                                            {notif.action_label || 'Consulter'}
                                                                                        </button>
                                                                                    ) : isUnread && typeof notif.id === 'number' && (
                                                                                        <button
                                                                                            type="button"
                                                                                            onClick={() => onMarkNotifRead(notif)}
                                                                                            className="rounded-lg bg-black/5 hover:bg-black/10 text-[var(--admin-text)] px-2 py-1 text-[11px] font-semibold transition cursor-pointer"
                                                                                        >
                                                                                            Marquer lu
                                                                                        </button>
                                                                                    )}
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                );
                                                            })
                                                        )}
                                                    </div>

                                                    <div className="border-t border-[var(--admin-border)]/60 bg-[var(--admin-card-header)]/50 px-4 py-2.5 text-center">
                                                        <p className="text-[11px] text-[var(--admin-muted)] flex items-center justify-center gap-1.5">
                                                            <span className="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500 animate-ping" />
                                                            Synchronisation temps réel active
                                                        </p>
                                                    </div>
                                                </div>
                                            </>
                                        )}
                                    </div>

                                    <div className="hidden items-center gap-3 rounded-3xl bg-white/50 px-3 py-2 sm:flex">
                                        <div className="flex h-11 w-11 items-center justify-center rounded-full bg-[#ebb95e] text-sm font-bold text-[#241b16]">
                                            {getInitials(adminName)}
                                        </div>
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-semibold text-[var(--admin-text)]">{adminName}</p>
                                            <p className="truncate text-xs text-[var(--admin-muted)]">{adminContact}</p>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        className="rounded-2xl p-3 text-[var(--admin-muted)] transition hover:bg-white/55"
                                        onClick={() => router.post('/admin/logout')}
                                        title="Se déconnecter"
                                        aria-label="Se déconnecter"
                                    >
                                        <LogoutIcon className="h-5 w-5" />
                                    </button>
                                </div>
                            </div>
                        </header>

                        <main className="px-4 pb-32 pt-5 lg:px-7 lg:pb-24">
                            <div className="mb-4 flex gap-2 overflow-x-auto pb-1 lg:hidden">
                                {navigation.flatMap((group) => group.items).map((item) => (
                                    <Link
                                        key={item.id}
                                        href={tabRoutes[item.id]}
                                        className={cn(
                                            'admin-chip whitespace-nowrap rounded-full border px-4 py-2 text-sm',
                                            item.id === activeTab && 'border-[#ebb95e] bg-[#f8e8c8] text-[#8a5d16]',
                                        )}
                                    >
                                        {item.label}
                                    </Link>
                                ))}
                            </div>

                            <Surface className="admin-hero rounded-[34px] p-6 lg:p-8">
                                <div className="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                                    <div className="max-w-3xl">
                                        <nav aria-label="Fil d'Ariane" className="text-[11px] font-semibold uppercase tracking-[0.26em] text-[var(--admin-muted)]">
                                            <ol className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                                <li>
                                                    <Link href={tabRoutes.dashboard} className="hover:text-[var(--admin-text)] transition">Backoffice</Link>
                                                </li>
                                                <li aria-hidden="true">/</li>
                                                <li>{tabMeta[activeTab].section}</li>
                                                <li aria-hidden="true">/</li>
                                                <li className="text-[var(--admin-text-soft)]" aria-current="page">{tabMeta[activeTab].label}</li>
                                            </ol>
                                        </nav>
                                        <h2 className="mt-3 text-4xl font-semibold tracking-tight text-[var(--admin-text)]">
                                            {tabMeta[activeTab].label}
                                        </h2>
                                        <p className="mt-2 max-w-2xl text-sm leading-6 text-[var(--admin-text-soft)]">
                                            {tabMeta[activeTab].description}
                                        </p>
                                        <p className="mt-3 text-sm text-[var(--admin-muted)]">{fullDate(new Date())}</p>
                                    </div>

                                    <div className="grid gap-3 sm:grid-cols-2 xl:w-[480px]">
                                        {heroStats.map((stat) => (
                                            <div key={stat.label} className="rounded-[24px] bg-white/60 p-4 shadow-[0_18px_36px_rgba(147,119,74,0.08)]">
                                                <p className="text-xs uppercase tracking-[0.22em] text-[var(--admin-muted)]">{stat.label}</p>
                                                <div className="mt-2 flex items-center gap-2">
                                                    <span className={cn('inline-flex h-8 w-8 items-center justify-center rounded-xl', toneIconClasses(stat.tone))}>
                                                        <ToneIcon tone={stat.tone} className="h-4 w-4" />
                                                    </span>
                                                    <p className="text-lg font-semibold text-[var(--admin-text)]">{stat.value}</p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </Surface>

                            {flash?.success ? (
                                <div className="mt-4 rounded-[24px] border border-[#c5dfca] bg-[#eef8f0] px-4 py-3 text-sm text-[#24734f]">
                                    {flash.success}
                                </div>
                            ) : null}

                            {bannerError ? (
                                <div className="mt-4 rounded-[24px] border border-[#efc1b9] bg-[#fff3ef] px-4 py-3 text-sm text-[#b24f43]">
                                    {bannerError}
                                </div>
                            ) : null}

                            {refreshing || actionLoading ? (
                                <div className="mt-4 rounded-[24px] border border-[#e2d5c2] bg-white/70 px-4 py-3 text-sm text-[var(--admin-text-soft)]">
                                    Mise à jour du backoffice en cours...
                                </div>
                            ) : null}

                            <div
                                aria-busy={navPending}
                                className={cn('transition-opacity duration-200', navPending && 'pointer-events-none opacity-60')}
                            >
                                {children}
                            </div>
                        </main>

                        <footer className="px-4 pb-6 lg:px-7">
                            <div className="border-t border-[var(--admin-border)] pt-6 text-sm text-[var(--admin-text-soft)]">
                                <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-[var(--admin-muted)]">Administration</p>
                                <p className="mt-2 text-xl font-semibold text-[var(--admin-text)]">ProsArtisan Backoffice</p>
                                <p className="mt-1 max-w-3xl">
                                    Pilotage des validations, des opérations terrain, des litiges et des flux financiers dans une seule interface.
                                </p>
                                <div className="mt-4">
                                    <Link href="/cgu" className="hover:text-[var(--admin-text)] hover:underline">Conditions Générales d'Utilisation</Link>
                                </div>
                            </div>
                        </footer>
                    </div>
                </div>

                <BottomDock activeTab={activeTab} />
            </div>
        </>
    );
}
