// Fermeture de la session du backoffice après inactivité (Chantier 18, lot A).
//
// Le serveur reste seul juge du délai (`EnforceAdminIdleTimeout`). Ce hook
// fait le reste : il prévient avant la fermeture, signale au serveur une
// activité sans requête (longue saisie dans un formulaire) et ramène à la
// page de connexion quand le délai est atteint.

import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

/** Durée de l'avertissement affiché avant la fermeture. */
export const IDLE_WARNING_SECONDS = 60;

/** Horodatage de la dernière activité, partagé entre les onglets du navigateur. */
export const IDLE_STORAGE_KEY = 'prosartisan_admin_last_activity';

const ACTIVITY_EVENTS = ['mousedown', 'mousemove', 'keydown', 'touchstart', 'wheel', 'scroll'] as const;

const JSON_HEADERS = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

const LOGIN_URL = '/admin/login';

function readSharedActivity(): number {
    try {
        return Number(window.localStorage.getItem(IDLE_STORAGE_KEY)) || 0;
    } catch {
        return 0;
    }
}

function writeSharedActivity(at: number): void {
    try {
        window.localStorage.setItem(IDLE_STORAGE_KEY, String(at));
    } catch {
        // Stockage indisponible (navigation privée) : le suivi reste propre à l'onglet.
    }
}

/**
 * Renvoie à la page de connexion quand un appel `fetch` du backoffice arrive
 * sur une session fermée pour inactivité. Vrai si la redirection est lancée.
 */
export function redirectIfSessionExpired(response: { status: number }): boolean {
    if (response.status !== 401) return false;
    window.location.assign(LOGIN_URL);
    return true;
}

interface IdleLogoutOptions {
    /** Redirection vers la page de connexion ; remplaçable dans les tests. */
    redirect?: (url: string) => void;
}

export function useIdleLogout(timeoutSeconds: number | null | undefined, options: IdleLogoutOptions = {}) {
    const [remaining, setRemaining] = useState<number | null>(null);

    const redirectRef = useRef(options.redirect);
    useEffect(() => {
        redirectRef.current = options.redirect;
    }, [options.redirect]);

    const lastActivityRef = useRef(0);
    const lastSyncRef = useRef(0);
    const lastSharedWriteRef = useRef(0);
    const warningRef = useRef(false);
    const expiredRef = useRef(false);

    const goToLogin = useCallback(() => {
        (redirectRef.current ?? ((url: string) => window.location.assign(url)))(LOGIN_URL);
    }, []);

    const markActivity = useCallback((at: number) => {
        lastActivityRef.current = at;
        // Les mouvements de souris arrivent par rafales : une écriture par seconde suffit.
        if (at - lastSharedWriteRef.current >= 1000) {
            lastSharedWriteRef.current = at;
            writeSharedActivity(at);
        }
    }, []);

    const keepAlive = useCallback(() => {
        lastSyncRef.current = Date.now();
        fetch('/admin/session/keep-alive', { method: 'POST', headers: JSON_HEADERS, credentials: 'same-origin' })
            .then((response) => {
                if (response.status === 401 && !expiredRef.current) {
                    expiredRef.current = true;
                    goToLogin();
                }
            })
            .catch(() => {
                // Coupure réseau : le serveur tranchera à la prochaine requête.
            });
    }, [goToLogin]);

    const stayConnected = useCallback(() => {
        warningRef.current = false;
        lastSharedWriteRef.current = 0;
        markActivity(Date.now());
        setRemaining(null);
        keepAlive();
    }, [keepAlive, markActivity]);

    const logout = useCallback(() => {
        router.post('/admin/logout');
    }, []);

    useEffect(() => {
        if (!timeoutSeconds || timeoutSeconds <= 0) return;

        const timeoutMs = timeoutSeconds * 1000;
        const warningMs = Math.min(IDLE_WARNING_SECONDS * 1000, timeoutMs / 2);
        // Le serveur est prévenu d'une activité sans requête au plus à ce rythme.
        const syncMs = timeoutMs / 3;

        const start = Date.now();
        lastSyncRef.current = start;
        lastSharedWriteRef.current = 0;
        expiredRef.current = false;
        warningRef.current = false;
        markActivity(start);

        const onActivity = () => {
            // Avertissement affiché : seul « Rester connecté » prolonge la session.
            if (warningRef.current || expiredRef.current) return;
            markActivity(Date.now());
        };

        const expire = () => {
            expiredRef.current = true;
            setRemaining(0);
            fetch('/admin/session/expire', { method: 'POST', headers: JSON_HEADERS, credentials: 'same-origin' })
                .catch(() => undefined)
                .finally(goToLogin);
        };

        const tick = () => {
            if (expiredRef.current) return;

            const now = Date.now();
            const last = Math.max(lastActivityRef.current, readSharedActivity());
            const idle = now - last;

            if (idle >= timeoutMs) {
                expire();
                return;
            }

            if (idle >= timeoutMs - warningMs) {
                warningRef.current = true;
                setRemaining(Math.ceil((timeoutMs - idle) / 1000));
                return;
            }

            // Activité reprise dans un autre onglet : l'avertissement se referme.
            if (warningRef.current) {
                warningRef.current = false;
                setRemaining(null);
            }

            if (last > lastSyncRef.current && now - lastSyncRef.current >= syncMs) {
                keepAlive();
            }
        };

        ACTIVITY_EVENTS.forEach((name) => window.addEventListener(name, onActivity, { passive: true }));
        const interval = window.setInterval(tick, 1000);
        // Toute navigation Inertia est vue par le serveur : inutile de le prévenir en plus.
        const stopListening = typeof router.on === 'function'
            ? router.on('finish', () => { lastSyncRef.current = Date.now(); })
            : undefined;

        return () => {
            ACTIVITY_EVENTS.forEach((name) => window.removeEventListener(name, onActivity));
            window.clearInterval(interval);
            stopListening?.();
        };
    }, [timeoutSeconds, goToLogin, keepAlive, markActivity]);

    const stayButtonRef = useRef<HTMLButtonElement | null>(null);
    const warningOpen = remaining !== null && remaining > 0;

    useEffect(() => {
        if (!warningOpen) return;

        stayButtonRef.current?.focus();

        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                stayConnected();
            }
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [warningOpen, stayConnected]);

    const dialog = warningOpen ? (
        <div
            className="fixed inset-0 z-[70] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
            role="presentation"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="idle-dialog-title"
                aria-describedby="idle-dialog-message"
                className="admin-panel admin-surface w-full max-w-[440px] rounded-[28px] border p-6 shadow-2xl"
            >
                <h2 id="idle-dialog-title" className="text-lg font-bold text-[var(--admin-text)]">
                    Votre session va se fermer
                </h2>
                <p id="idle-dialog-message" className="mt-2 text-sm text-[var(--admin-text-soft)]">
                    Aucune activité depuis un moment. Par sécurité, la session se ferme dans{' '}
                    <span aria-live="polite" className="font-bold text-[var(--admin-text)]">
                        {remaining} seconde{remaining > 1 ? 's' : ''}
                    </span>{' '}
                    et vous devrez vous reconnecter.
                </p>
                <div className="mt-6 flex justify-end gap-2">
                    <button type="button" onClick={logout} className="admin-button admin-button--ghost">
                        Se déconnecter
                    </button>
                    <button
                        ref={stayButtonRef}
                        type="button"
                        onClick={stayConnected}
                        className="admin-button admin-button--primary"
                    >
                        Rester connecté
                    </button>
                </div>
            </div>
        </div>
    ) : null;

    return { dialog, remaining, stayConnected };
}
