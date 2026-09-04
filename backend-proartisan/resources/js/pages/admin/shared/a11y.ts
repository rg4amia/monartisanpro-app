// Aides d'accessibilité des modales du backoffice (Chantier C7 / P2-13).

import { useEffect } from 'react';

/** Ferme la modale sur la touche Échap tant qu'elle est montée. */
export function useDismissOnEscape(onDismiss: () => void): void {
    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                onDismiss();
            }
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [onDismiss]);
}
