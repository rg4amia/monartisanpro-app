// Barre de raccourcis flottante du backoffice, extraite de console.tsx (Chantier C2).

import { Link } from '@inertiajs/react';

import { cn } from '@/lib/utils';

import { quickDockTabs, tabMeta, tabRoutes } from './constants';
import type { AdminTab } from './types';

export function BottomDock({ activeTab }: { activeTab: AdminTab }) {
    return (
        <div className="pointer-events-none fixed bottom-4 right-4 z-30 hidden lg:block">
            <div className="pointer-events-auto flex items-center gap-2 rounded-full border border-[var(--admin-border)] bg-white/80 p-2 shadow-[0_18px_38px_rgba(125,96,57,0.16)] backdrop-blur-xl">
                {quickDockTabs.map((tab) => (
                    <Link
                        key={tab}
                        href={tabRoutes[tab]}
                        className={cn(
                            'rounded-full px-4 py-2 text-sm font-medium transition',
                            activeTab === tab ? 'bg-[#f4e2bf] text-[#7d571b]' : 'text-[var(--admin-text-soft)] hover:bg-[#f7efe2]',
                        )}
                    >
                        {tabMeta[tab].label}
                    </Link>
                ))}
            </div>
        </div>
    );
}
