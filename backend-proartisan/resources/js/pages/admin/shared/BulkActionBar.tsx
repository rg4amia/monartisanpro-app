// Barre d'actions groupées, affichée quand des lignes sont sélectionnées (Chantier C5 / P1-9).

import type { ReactNode } from 'react';

interface BulkAction {
    label: string;
    tone?: 'primary' | 'danger' | 'success' | 'neutral';
    onClick: () => void;
}

interface BulkActionBarProps {
    count: number;
    onClear: () => void;
    actions: BulkAction[];
    children?: ReactNode;
}

const toneClass: Record<NonNullable<BulkAction['tone']>, string> = {
    primary: 'bg-[#ebb95e] text-[#241b16] hover:bg-[#dca850]',
    danger: 'bg-rose-600 text-white hover:bg-rose-700',
    success: 'bg-emerald-600 text-white hover:bg-emerald-700',
    neutral: 'border border-[var(--admin-border)] bg-white/70 text-[var(--admin-text-soft)] hover:bg-white',
};

export function BulkActionBar({ count, onClear, actions, children }: BulkActionBarProps) {
    if (count === 0) {
        return null;
    }

    return (
        <div className="sticky top-2 z-20 flex flex-wrap items-center gap-2 rounded-2xl border border-[var(--admin-border)] bg-[var(--admin-surface,#fff)] p-3 shadow-lg">
            <span className="rounded-full bg-[#1e293b] px-3 py-1 text-xs font-bold text-white">
                {count} sélectionné{count > 1 ? 's' : ''}
            </span>
            {children}
            <div className="ml-auto flex flex-wrap gap-2">
                {actions.map((action) => (
                    <button
                        key={action.label}
                        type="button"
                        onClick={action.onClick}
                        className={`rounded-xl px-3 py-1.5 text-xs font-bold transition ${toneClass[action.tone ?? 'primary']}`}
                    >
                        {action.label}
                    </button>
                ))}
                <button
                    type="button"
                    onClick={onClear}
                    className="rounded-xl px-3 py-1.5 text-xs font-bold text-[var(--admin-muted)] transition hover:text-[var(--admin-text)]"
                >
                    Annuler
                </button>
            </div>
        </div>
    );
}
