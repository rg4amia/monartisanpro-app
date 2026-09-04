// Icônes SVG inline du backoffice, extraites de console.tsx (Chantier C2).

import type { AdminTab, Tone } from './types';

export function TabIcon({ className = 'h-5 w-5', tab }: { className?: string; tab: AdminTab }) {
    switch (tab) {
        case 'llm_admin':
            return <InboxIcon className={className} />;
        case 'ai_dashboard':
            return <SettingsIcon className={className} />;
        case 'dashboard':
            return <DashboardIcon className={className} />;
        case 'kyc':
            return <ShieldIcon className={className} />;
        case 'missions':
            return <ClipboardIcon className={className} />;
        case 'litiges':
            return <AlertIcon className={className} />;
        case 'users':
            return <UsersIcon className={className} />;
        case 'transactions':
            return <WalletIcon className={className} />;
        case 'settings':
            return <SettingsIcon className={className} />;
        case 'evaluations':
            return <StarIcon className={className} />;
        case 'communications':
            return <BellIcon className={className} />;
        case 'notifications':
            return <BellIcon className={className} />;
        case 'vitrine':
            return <ArchiveIcon className={className} />;
        case 'observability':
            return <AlertIcon className={className} />;
        default:
            return <DashboardIcon className={className} />;
    }
}

export function ToneIcon({ className = 'h-5 w-5', tone }: { className?: string; tone: Tone }) {
    switch (tone) {
        case 'amber':
            return <StarIcon className={className} />;
        case 'green':
            return <TrendUpIcon className={className} />;
        case 'rose':
            return <AlertIcon className={className} />;
        case 'blue':
            return <ClockIcon className={className} />;
        case 'slate':
            return <ArchiveIcon className={className} />;
    }
}

export function ActivityToneIcon({ tone }: { tone: Tone }) {
    const cls = 'h-5 w-5';
    switch (tone) {
        case 'amber': return <ShieldIcon className={cls} />;
        case 'green': return <CheckCircleIcon className={cls} />;
        case 'rose': return <AlertIcon className={cls} />;
        case 'blue': return <WalletIcon className={cls} />;
        case 'slate': return <ArchiveIcon className={cls} />;
    }
}

export function TrendUpIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="M3 17 9 11l4 4 8-9" strokeLinecap="round" strokeLinejoin="round" />
            <path d="M21 8h-5v5" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

export function StarIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="m12 2 2.68 5.44L21 8.6l-4.5 4.38 1.06 6.18L12 16.26l-5.56 2.9 1.06-6.18L3 8.6l6.32-.92L12 2Z" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

export function ClockIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 3" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

export function ArchiveIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <rect x="3" y="4" width="18" height="4" rx="1.5" />
            <path d="M5 8v9a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8" strokeLinecap="round" />
            <path d="M10 13h4" strokeLinecap="round" />
        </svg>
    );
}

export function CheckCircleIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="9" />
            <path d="m8.5 12.5 2 2 5-5" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

export function InboxIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="M4 4h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z" strokeLinecap="round" />
            <path d="M3 14h4l2 3h6l2-3h4" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

export function SearchIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-3.5-3.5" strokeLinecap="round" />
        </svg>
    );
}

export function RefreshIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="M21 12a9 9 0 1 1-2.64-6.36" strokeLinecap="round" strokeLinejoin="round" />
            <path d="M21 4v6h-6" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

export function MoonIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="M20.4 15.1A8.5 8.5 0 1 1 8.9 3.6a7 7 0 1 0 11.5 11.5Z" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

export function SunIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="4" />
            <path d="M12 2v2.2M12 19.8V22M4.2 4.2l1.6 1.6M18.2 18.2l1.6 1.6M2 12h2.2M19.8 12H22M4.2 19.8l1.6-1.6M18.2 5.8l1.6-1.6" strokeLinecap="round" />
        </svg>
    );
}

export function BellIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="M15 17H5.8A1.8 1.8 0 0 1 4 15.2c0-.4.1-.7.3-1l1.2-2a5 5 0 0 0 .7-2.5V9a5.8 5.8 0 1 1 11.6 0v.7a5 5 0 0 0 .7 2.5l1.2 2c.2.3.3.6.3 1A1.8 1.8 0 0 1 18.2 17H15Z" strokeLinecap="round" strokeLinejoin="round" />
            <path d="M10 20a2.5 2.5 0 0 0 4 0" strokeLinecap="round" />
        </svg>
    );
}

export function LogoutIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="M10 7V5.5A2.5 2.5 0 0 1 12.5 3h5A2.5 2.5 0 0 1 20 5.5v13a2.5 2.5 0 0 1-2.5 2.5h-5A2.5 2.5 0 0 1 10 18.5V17" strokeLinecap="round" strokeLinejoin="round" />
            <path d="M15 12H4" strokeLinecap="round" />
            <path d="m8 8-4 4 4 4" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

export function DashboardIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <rect x="3.5" y="3.5" width="7" height="7" rx="1.6" />
            <rect x="13.5" y="3.5" width="7" height="7" rx="1.6" />
            <rect x="3.5" y="13.5" width="7" height="7" rx="1.6" />
            <rect x="13.5" y="13.5" width="7" height="7" rx="1.6" />
        </svg>
    );
}

export function ShieldIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="M12 3s5 2 7 3v5c0 5-3.4 8-7 10-3.6-2-7-5-7-10V6c2-1 7-3 7-3Z" strokeLinecap="round" strokeLinejoin="round" />
            <path d="m9.5 12 1.6 1.8 3.4-3.8" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

export function ClipboardIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <rect x="6" y="4.5" width="12" height="16" rx="2" />
            <path d="M9 4.5h6a1.5 1.5 0 0 1 1.5 1.5v0A1.5 1.5 0 0 1 15 7.5H9A1.5 1.5 0 0 1 7.5 6v0A1.5 1.5 0 0 1 9 4.5Z" />
            <path d="M9 12h6M9 16h4" strokeLinecap="round" />
        </svg>
    );
}

export function AlertIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="M12 4 3.8 18.2A1.2 1.2 0 0 0 4.8 20h14.4a1.2 1.2 0 0 0 1-1.8L12 4Z" strokeLinecap="round" strokeLinejoin="round" />
            <path d="M12 9v4.5M12 17h.01" strokeLinecap="round" />
        </svg>
    );
}

export function UsersIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="M16 19v-1.2A3.8 3.8 0 0 0 12.2 14H7.8A3.8 3.8 0 0 0 4 17.8V19" strokeLinecap="round" />
            <circle cx="10" cy="8" r="3" />
            <path d="M20 19v-1.2a3.5 3.5 0 0 0-2.5-3.4" strokeLinecap="round" />
            <path d="M16.5 5.3a3 3 0 0 1 0 5.4" strokeLinecap="round" />
        </svg>
    );
}

export function WalletIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="M4 7.5A2.5 2.5 0 0 1 6.5 5H18a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6.5A2.5 2.5 0 0 1 4 16.5v-9Z" strokeLinecap="round" strokeLinejoin="round" />
            <path d="M4 8h12.5A1.5 1.5 0 0 0 18 6.5v0A1.5 1.5 0 0 0 16.5 5H6.5" strokeLinecap="round" />
            <path d="M16 13h4" strokeLinecap="round" />
        </svg>
    );
}

export function SettingsIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="M12 15.5A3.5 3.5 0 1 0 12 8.5a3.5 3.5 0 0 0 0 7Z" />
            <path d="m19.4 15 .9 1.6-1.7 3-1.8-.3a7.9 7.9 0 0 1-1.7 1l-.5 1.8H9.4l-.5-1.8a7.9 7.9 0 0 1-1.7-1l-1.8.3-1.7-3 .9-1.6a8.6 8.6 0 0 1 0-2l-.9-1.6 1.7-3 1.8.3c.5-.4 1.1-.7 1.7-1l.5-1.8h4.2l.5 1.8c.6.3 1.2.6 1.7 1l1.8-.3 1.7 3-.9 1.6c.2.7.2 1.3 0 2Z" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

export function PlusIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="M12 4.5v15m7.5-7.5h-15" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

export function MenuIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

export function CloseIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
            <path d="M6 18 18 6M6 6l12 12" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}
