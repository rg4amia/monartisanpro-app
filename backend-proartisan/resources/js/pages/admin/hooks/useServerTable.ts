// Filtres + pagination côté serveur pour les listes du backoffice (Chantier C4 / P1-6).
//
// Chaque liste volumineuse (utilisateurs, transactions, missions…) charge sa page
// via un rechargement partiel Inertia (`only`) au lieu de tout rapatrier puis
// filtrer côté client. L'état des filtres est amorcé depuis l'URL pour survivre
// à un rafraîchissement ou à un partage de lien, puis mémorisé en `localStorage`
// pour être restauré au retour sur l'onglet (Chantier C7 / P2-13).

import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';

interface UseServerTableOptions<T extends Record<string, string>> {
    /** Route Inertia de la liste, ex. `/admin/users`. */
    path: string;
    /** Props à recharger (partial reload), ex. `['users']`. */
    only: string[];
    /** Valeurs par défaut des filtres (clés = noms des query params). */
    initial: T;
    /** Clé de persistance `localStorage` ; si absente, aucune mémorisation. */
    storageKey?: string;
}

export function useServerTable<T extends Record<string, string>>({ path, only, initial, storageKey }: UseServerTableOptions<T>) {
    const keys = Object.keys(initial) as Array<keyof T>;
    const lsKey = storageKey ? `admin_table_filters:${storageKey}` : null;

    const readStored = (): Partial<T> | null => {
        if (!lsKey || typeof window === 'undefined') return null;
        try {
            const raw = window.localStorage.getItem(lsKey);
            return raw ? (JSON.parse(raw) as Partial<T>) : null;
        } catch {
            return null;
        }
    };

    const persist = (params: Partial<T>): void => {
        if (!lsKey || typeof window === 'undefined') return;
        try {
            const meaningful = keys.some((key) => params[key] && params[key] !== initial[key]);
            if (meaningful) {
                window.localStorage.setItem(lsKey, JSON.stringify(params));
            } else {
                window.localStorage.removeItem(lsKey);
            }
        } catch {
            /* stockage indisponible — on continue sans persistance */
        }
    };

    const seed = (): T => {
        const next = { ...initial };
        if (typeof window === 'undefined') return next;

        const params = new URLSearchParams(window.location.search);
        const urlHasFilter = keys.some((key) => params.has(key as string));

        if (urlHasFilter) {
            keys.forEach((key) => {
                const value = params.get(key as string);
                if (value !== null) next[key] = value as T[keyof T];
            });
            return next;
        }

        const stored = readStored();
        if (stored) {
            keys.forEach((key) => {
                if (stored[key] != null) next[key] = stored[key] as T[keyof T];
            });
        }
        return next;
    };

    const [filters, setFilters] = useState<T>(seed);
    const restoredRef = useRef(false);

    // Restaure les filtres mémorisés au montage (URL vierge) en réappliquant la requête.
    useEffect(() => {
        if (restoredRef.current || !lsKey || typeof window === 'undefined') return;
        restoredRef.current = true;

        const params = new URLSearchParams(window.location.search);
        if (keys.some((key) => params.has(key as string))) return;

        const stored = readStored();
        if (!stored) return;

        const meaningful = keys.some((key) => stored[key] && stored[key] !== initial[key]);
        if (meaningful) {
            router.get(path, stored, { only, preserveState: true, preserveScroll: true, replace: true });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const set = (key: keyof T, value: string) => {
        setFilters((current) => ({ ...current, [key]: value }));
    };

    const visit = (params: Partial<T>) => {
        persist(params);
        router.get(path, params, { only, preserveState: true, preserveScroll: true });
    };

    const apply = (event?: FormEvent) => {
        event?.preventDefault();
        visit(filters);
    };

    /** Met à jour un filtre et recharge immédiatement (boutons de filtre sans « Appliquer »). */
    const applyWith = (key: keyof T, value: string) => {
        const next = { ...filters, [key]: value };
        setFilters(next);
        visit(next);
    };

    const reset = () => {
        const cleared = Object.fromEntries(keys.map((key) => [key, ''])) as T;
        setFilters(cleared);
        if (lsKey && typeof window !== 'undefined') {
            try {
                window.localStorage.removeItem(lsKey);
            } catch {
                /* ignore */
            }
        }
        router.get(path, {}, { only, preserveState: true, preserveScroll: true });
    };

    const hasActiveFilters = keys.some((key) => filters[key] !== '' && filters[key] !== initial[key]);

    return { filters, set, apply, applyWith, reset, hasActiveFilters };
}
