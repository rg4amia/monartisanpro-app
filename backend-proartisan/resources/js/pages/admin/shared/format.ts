// Helpers de formatage et de style du backoffice, extraits de console.tsx (Chantier C2).

import type { TimelinePoint, Tone } from './types';

export const numberFormat = new Intl.NumberFormat('fr-FR');

export const money = (amount: number): string => `${numberFormat.format(amount)} FCFA`;

export const shortDate = (value: string): string =>
    new Date(value).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });

export const fullDate = (value: Date): string =>
    value.toLocaleDateString('fr-FR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

export const compactDate = (value: Date): string =>
    value.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
    });

// Date + heure — utilisé notamment par le journal d'audit (Chantier C3).
export const dateTimeShort = (value: string): string =>
    new Date(value).toLocaleString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });

export function toneBadgeClasses(tone: Tone): string {
    const classes: Record<Tone, string> = {
        amber: 'border-[#efcf95] bg-[#f8eed4] text-[#b77918]',
        blue: 'border-[#bcd4f6] bg-[#edf5ff] text-[#2d6aa6]',
        green: 'border-[#bfe0c8] bg-[#eef8f0] text-[#24734f]',
        rose: 'border-[#f2c1ba] bg-[#fff0ed] text-[#c55e50]',
        slate: 'border-[#dfd4c4] bg-[#f4eee6] text-[#746251]',
        purple: 'border-[#d8b4fe] bg-[#f3e8ff] text-[#7e22ce]',
    };

    return classes[tone];
}

export function toneIconClasses(tone: Tone): string {
    const classes: Record<Tone, string> = {
        amber: 'bg-[#f7e3bc] text-[#b77918]',
        blue: 'bg-[#dcebfb] text-[#2d6aa6]',
        green: 'bg-[#dff1e4] text-[#24734f]',
        rose: 'bg-[#fbe0da] text-[#c55e50]',
        slate: 'bg-[#efe6da] text-[#746251]',
        purple: 'bg-[#f3e8ff] text-[#7e22ce]',
    };

    return classes[tone];
}

export function normalizeSearch(parts: Array<number | string | null | undefined>): string {
    return parts
        .filter((part): part is number | string => part !== null && part !== undefined)
        .join(' ')
        .toLowerCase();
}

export function getInitials(value: string | null | undefined): string {
    if (!value) return 'PA';
    const initials = value
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');

    return initials || 'PA';
}

export function buildTimeline(days: number, now: number): TimelinePoint[] {
    const today = new Date(now);

    return Array.from({ length: days }, (_, index) => {
        const date = new Date(today);
        date.setDate(today.getDate() - (days - 1 - index));

        return {
            date: date.toISOString().slice(0, 10),
            label: compactDate(date),
        };
    });
}

export function actionButtonClass(variant: 'danger' | 'secondary' | 'success'): string {
    const base = 'inline-flex items-center justify-center rounded-full px-4 py-2 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-50';

    const variants: Record<typeof variant, string> = {
        danger: 'bg-[#f15f57] text-white hover:bg-[#dd4d45]',
        secondary: 'bg-[#f0e5d3] text-[#6f531f] hover:bg-[#e4d4bb]',
        success: 'bg-[#2f9a65] text-white hover:bg-[#248052]',
    };

    return `${base} ${variants[variant]}`;
}
