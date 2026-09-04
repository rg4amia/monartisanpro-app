// Sélection de lignes pour les actions groupées du backoffice (Chantier C5 / P1-9).

import { useCallback, useMemo, useState } from 'react';

export function useRowSelection() {
    const [selected, setSelected] = useState<Set<number>>(() => new Set());

    const toggle = useCallback((id: number) => {
        setSelected((current) => {
            const next = new Set(current);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    }, []);

    /** Coche / décoche toutes les lignes de la page courante. */
    const toggleAll = useCallback((ids: number[]) => {
        setSelected((current) => {
            const allSelected = ids.length > 0 && ids.every((id) => current.has(id));
            if (allSelected) {
                const next = new Set(current);
                ids.forEach((id) => next.delete(id));
                return next;
            }
            return new Set([...current, ...ids]);
        });
    }, []);

    const clear = useCallback(() => setSelected(new Set()), []);

    const isSelected = useCallback((id: number) => selected.has(id), [selected]);

    const ids = useMemo(() => Array.from(selected), [selected]);

    return { selected, ids, count: selected.size, toggle, toggleAll, clear, isSelected };
}
