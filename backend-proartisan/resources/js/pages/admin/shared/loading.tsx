// États de chargement du backoffice (Chantier C7 / P2-13).

import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { cn } from '@/lib/utils';

/**
 * `true` pendant qu'une visite Inertia (changement d'onglet, filtre serveur,
 * pagination) est en cours. Permet d'afficher un squelette ou d'estomper le
 * contenu sans bloquer l'interface.
 */
export function useInertiaVisit(): boolean {
    const [pending, setPending] = useState(false);

    useEffect(() => {
        const start = router.on('start', () => setPending(true));
        const finish = router.on('finish', () => setPending(false));
        return () => {
            start();
            finish();
        };
    }, []);

    return pending;
}

export function Skeleton({ className }: { className?: string }) {
    return <div className={cn('animate-pulse rounded-lg bg-[var(--admin-border)]/60', className)} />;
}

/** Squelette générique de tableau, à afficher pendant un rechargement de liste. */
export function TableSkeleton({ rows = 6, columns = 4 }: { rows?: number; columns?: number }) {
    return (
        <div className="space-y-2" aria-hidden="true">
            <Skeleton className="h-9 w-full" />
            {Array.from({ length: rows }).map((_, rowIndex) => (
                <div key={rowIndex} className="grid gap-3" style={{ gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` }}>
                    {Array.from({ length: columns }).map((__, colIndex) => (
                        <Skeleton key={colIndex} className="h-6" />
                    ))}
                </div>
            ))}
        </div>
    );
}
