// Badges de statut du backoffice, extraits de console.tsx (Chantier C2).

import { cn } from '@/lib/utils';

import { kycStatusLabels, litigeDecisionLabels, missionStatusLabels, providerLabels, roleLabels } from './constants';
import { toneBadgeClasses } from './format';
import type { Tone } from './types';

export function RoleBadge({ role }: { role: string }) {
    const toneMap: Record<string, Tone> = {
        admin: 'amber',
        artisan: 'green',
        client: 'blue',
        fournisseur: 'slate',
        referent: 'rose',
        livreur: 'amber',
    };

    return <span className={cn('rounded-full border px-3 py-1 text-xs font-semibold', toneBadgeClasses(toneMap[role] ?? 'slate'))}>{roleLabels[role] ?? role}</span>;
}

export function KycStatusBadge({ status }: { status: string }) {
    const toneMap: Record<string, Tone> = {
        actif: 'green',
        en_attente: 'amber',
        rejete: 'rose',
    };

    return <span className={cn('rounded-full border px-3 py-1 text-xs font-semibold', toneBadgeClasses(toneMap[status] ?? 'slate'))}>{kycStatusLabels[status] ?? status}</span>;
}

export function AccountStatusBadge({ status }: { status?: string | null }) {
    const isActif = (status ?? 'actif') === 'actif';
    return (
        <span className={cn('rounded-full border px-3 py-1 text-xs font-semibold', toneBadgeClasses(isActif ? 'green' : 'rose'))}>
            {isActif ? 'Actif' : 'Suspendu'}
        </span>
    );
}

export function MissionStatusBadge({ status }: { status: string }) {
    const toneMap: Record<string, Tone> = {
        annulee: 'slate',
        en_attente: 'amber',
        en_cours: 'green',
        financee: 'blue',
        litige: 'rose',
        terminee: 'slate',
    };

    return (
        <span className={cn('rounded-full border px-3 py-1 text-xs font-semibold', toneBadgeClasses(toneMap[status] ?? 'slate'))}>
            {missionStatusLabels[status] ?? status}
        </span>
    );
}

export function LitigeStatusBadge({ status }: { status: string }) {
    const toneMap: Record<string, Tone> = {
        en_cours: 'amber',
        ouvert: 'rose',
        resolu: 'green',
    };

    return <span className={cn('rounded-full border px-3 py-1 text-xs font-semibold', toneBadgeClasses(toneMap[status] ?? 'slate'))}>{status}</span>;
}

export function DecisionBadge({ decision }: { decision: string }) {
    const toneMap: Record<string, Tone> = {
        artisan: 'green',
        client: 'rose',
        gel: 'amber',
    };

    return (
        <span className={cn('rounded-full border px-3 py-1 text-xs font-semibold', toneBadgeClasses(toneMap[decision] ?? 'slate'))}>
            {litigeDecisionLabels[decision] ?? decision}
        </span>
    );
}

export function ProviderBadge({ provider }: { provider: string }) {
    const toneMap: Record<string, Tone> = {
        orange_money: 'amber',
        virement_bancaire: 'slate',
        wave: 'blue',
    };

    return (
        <span className={cn('rounded-full border px-3 py-1 text-xs font-semibold', toneBadgeClasses(toneMap[provider] ?? 'slate'))}>
            {providerLabels[provider] ?? provider}
        </span>
    );
}

export function TransactionStatusBadge({ status }: { status: string }) {
    const toneMap: Record<string, Tone> = {
        confirme: 'green',
        echoue: 'rose',
        en_attente: 'blue',
    };

    return <span className={cn('rounded-full border px-3 py-1 text-xs font-semibold', toneBadgeClasses(toneMap[status] ?? 'slate'))}>{status}</span>;
}

export function DeliveryStatusBadge({ status }: { status: string }) {
    const toneMap: Record<string, Tone> = {
        paid: 'blue',
        prepared: 'amber',
        searching_driver: 'amber',
        driver_assigned: 'purple',
        driver_picked_up: 'purple',
        shipping: 'purple',
        delivered: 'green',
        disputed: 'rose',
    };

    const labelMap: Record<string, string> = {
        paid: 'Payée (En attente préparation)',
        prepared: 'Préparée (Prête pour coursier)',
        searching_driver: 'Recherche coursier...',
        driver_assigned: 'Livreur assigné',
        driver_picked_up: 'Colis récupéré',
        shipping: 'En livraison (En transit)',
        delivered: 'Livrée & Réceptionnée',
        disputed: 'En litige',
    };

    return (
        <span className={cn('rounded-full border px-2.5 py-1 text-xs font-semibold', toneBadgeClasses(toneMap[status] ?? 'slate'))}>
            {labelMap[status] ?? status}
        </span>
    );
}

export function DeliveryModeBadge({ mode }: { mode: string }) {
    const isDelivery = mode === 'delivery';
    return (
        <span className={cn('rounded-full border px-2.5 py-0.5 text-[11px] font-semibold', isDelivery ? 'bg-purple-50 text-purple-700 border-purple-200' : 'bg-blue-50 text-blue-700 border-blue-200')}>
            {isDelivery ? '🛵 Livraison coursier' : '🏪 Retrait direct'}
        </span>
    );
}
