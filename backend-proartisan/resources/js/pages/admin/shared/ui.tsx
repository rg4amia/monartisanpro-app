// Primitives de présentation du backoffice, extraites de console.tsx (Chantier C2).

import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

import { getInitials, toneIconClasses } from './format';
import { InboxIcon, ToneIcon } from './icons';
import type { Tone } from './types';

export function Surface({ children, className = '' }: { children: ReactNode; className?: string }) {
    return <section className={cn('admin-panel admin-surface border', className)}>{children}</section>;
}

export function MetricCard({
    children,
    description,
    tone,
    trend,
    value,
}: {
    children: ReactNode;
    description: string;
    tone: Tone;
    trend: string;
    value: string;
}) {
    return (
        <Surface className="admin-metric-card rounded-[30px] p-5 lg:p-6">
            <div className={cn('flex h-12 w-12 items-center justify-center rounded-2xl', toneIconClasses(tone))}>
                <ToneIcon tone={tone} className="h-5 w-5" />
            </div>
            <p className="mt-5 text-xs font-semibold uppercase tracking-[0.2em] text-[var(--admin-muted)]">{children}</p>
            <p className="mt-1.5 text-4xl font-semibold tracking-tight text-[var(--admin-text)]">{value}</p>
            <p className="mt-2 text-sm text-[var(--admin-text-soft)]">{description}</p>
            <p className="mt-3 text-xs font-medium text-[var(--admin-muted)]">{trend}</p>
        </Surface>
    );
}

export function SectionTitle({ description, title }: { description: string; title: string }) {
    return (
        <div>
            <h3 className="text-2xl font-semibold tracking-tight text-[var(--admin-text)]">{title}</h3>
            <p className="mt-1 text-sm leading-6 text-[var(--admin-text-soft)]">{description}</p>
        </div>
    );
}

export function DataTable({ children, className = '' }: { children: ReactNode; className?: string }) {
    return (
        <div className={cn('overflow-x-auto', className)}>
            <table className="admin-table min-w-full">{children}</table>
        </div>
    );
}

export function EmptyState({ description, title }: { description: string; title: string }) {
    return (
        <div className="rounded-[24px] border border-dashed border-[var(--admin-border)] bg-white/45 px-5 py-8 text-center">
            <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full border border-[var(--admin-border)] bg-white/60 text-[var(--admin-muted)]">
                <InboxIcon className="h-5 w-5" />
            </div>
            <p className="text-base font-semibold text-[var(--admin-text)]">{title}</p>
            <p className="mt-2 text-sm text-[var(--admin-text-soft)]">{description}</p>
        </div>
    );
}

export function AvatarBubble({ label }: { label: string }) {
    return (
        <span className="admin-avatar flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#ebb95e] text-sm font-bold text-[#241b16]">
            {getInitials(label)}
        </span>
    );
}

export function InfoRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-3">
            <span className="text-[var(--admin-muted)]">{label}</span>
            <span className="text-right font-medium text-[var(--admin-text)]">{value}</span>
        </div>
    );
}

export function InfoPill({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-[22px] border border-[var(--admin-border)] bg-white/60 px-4 py-3">
            <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-[var(--admin-muted)]">{label}</p>
            <p className="mt-2 text-sm font-medium text-[var(--admin-text)]">{value}</p>
        </div>
    );
}
