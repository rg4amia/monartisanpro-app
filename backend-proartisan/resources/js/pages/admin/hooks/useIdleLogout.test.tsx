import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const routerPost = vi.fn();
vi.mock('@inertiajs/react', () => ({
    router: {
        post: (...args: unknown[]) => routerPost(...args),
        on: () => () => undefined,
    },
}));

import { IDLE_STORAGE_KEY, redirectIfSessionExpired, useIdleLogout } from './useIdleLogout';

const redirect = vi.fn();
const fetchMock = vi.fn();

function Harness({ timeout }: { timeout: number | null }) {
    const { dialog } = useIdleLogout(timeout, { redirect });
    return <div>{dialog}</div>;
}

/** Fait avancer l'horloge simulée, et laisse se résoudre les appels `fetch`. */
async function advance(seconds: number) {
    await act(async () => {
        await vi.advanceTimersByTimeAsync(seconds * 1000);
    });
}

function calls(url: string) {
    return fetchMock.mock.calls.filter(([called]) => called === url);
}

describe('useIdleLogout', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-10-03T10:00:00Z'));
        window.localStorage.clear();
        redirect.mockClear();
        routerPost.mockClear();
        fetchMock.mockReset();
        fetchMock.mockResolvedValue({ status: 200 });
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        vi.useRealTimers();
    });

    it('ne fait rien hors du backoffice (délai absent)', async () => {
        render(<Harness timeout={null} />);
        await advance(2000);

        expect(screen.queryByRole('dialog')).toBeNull();
        expect(fetchMock).not.toHaveBeenCalled();
        expect(redirect).not.toHaveBeenCalled();
    });

    it('avertit une minute avant la fermeture, avec un compte à rebours', async () => {
        render(<Harness timeout={900} />);

        await advance(839);
        expect(screen.queryByRole('dialog')).toBeNull();

        await advance(1);
        const dialog = screen.getByRole('dialog');
        expect(dialog.textContent).toContain('Votre session va se fermer');
        expect(dialog.textContent).toContain('60 secondes');

        await advance(30);
        expect(screen.getByRole('dialog').textContent).toContain('30 secondes');
    });

    it('ferme la session et renvoie à la connexion à 15 minutes', async () => {
        render(<Harness timeout={900} />);

        await advance(900);

        expect(calls('/admin/session/expire')).toHaveLength(1);
        expect(redirect).toHaveBeenCalledWith('/admin/login');
        expect(screen.queryByRole('dialog')).toBeNull();
    });

    it('renvoie à la connexion même si le serveur ne répond pas', async () => {
        fetchMock.mockRejectedValue(new Error('réseau coupé'));
        render(<Harness timeout={900} />);

        await advance(900);

        expect(redirect).toHaveBeenCalledWith('/admin/login');
    });

    it('« Rester connecté » prolonge la session et prévient le serveur', async () => {
        render(<Harness timeout={900} />);
        await advance(850);

        await act(async () => {
            fireEvent.click(screen.getByRole('button', { name: 'Rester connecté' }));
        });

        expect(screen.queryByRole('dialog')).toBeNull();
        expect(calls('/admin/session/keep-alive')).toHaveLength(1);

        // Le délai est reparti : rien ne se ferme 14 minutes plus tard.
        await advance(830);
        expect(redirect).not.toHaveBeenCalled();
        expect(screen.queryByRole('dialog')).toBeNull();
    });

    it('une activité fait repartir le délai sans avertissement', async () => {
        render(<Harness timeout={900} />);

        await advance(800);
        fireEvent.keyDown(window, { key: 'a' });
        await advance(800);

        expect(screen.queryByRole('dialog')).toBeNull();
        expect(redirect).not.toHaveBeenCalled();
    });

    it('prévient le serveur d’une activité sans requête, au plus toutes les 5 minutes', async () => {
        render(<Harness timeout={900} />);

        // Saisie continue pendant 11 minutes, sans aucune navigation.
        for (let elapsed = 0; elapsed < 660; elapsed += 20) {
            fireEvent.keyDown(window, { key: 'a' });
            await advance(20);
        }

        expect(calls('/admin/session/keep-alive')).toHaveLength(2);
    });

    it('ne prévient pas le serveur sans activité', async () => {
        render(<Harness timeout={900} />);
        await advance(700);

        expect(calls('/admin/session/keep-alive')).toHaveLength(0);
    });

    it('une fois l’avertissement affiché, bouger la souris ne le referme pas', async () => {
        render(<Harness timeout={900} />);
        await advance(850);

        fireEvent.mouseMove(window);
        await advance(5);

        expect(screen.getByRole('dialog')).toBeTruthy();
    });

    it('une activité dans un autre onglet referme l’avertissement', async () => {
        render(<Harness timeout={900} />);
        await advance(850);
        expect(screen.getByRole('dialog')).toBeTruthy();

        window.localStorage.setItem(IDLE_STORAGE_KEY, String(Date.now()));
        await advance(1);

        expect(screen.queryByRole('dialog')).toBeNull();
        await advance(600);
        expect(redirect).not.toHaveBeenCalled();
    });

    it('Échap vaut « Rester connecté »', async () => {
        render(<Harness timeout={900} />);
        await advance(850);

        await act(async () => {
            fireEvent.keyDown(document, { key: 'Escape' });
        });

        expect(screen.queryByRole('dialog')).toBeNull();
        expect(calls('/admin/session/keep-alive')).toHaveLength(1);
    });

    it('« Se déconnecter » passe par la déconnexion habituelle', async () => {
        render(<Harness timeout={900} />);
        await advance(850);

        fireEvent.click(screen.getByRole('button', { name: 'Se déconnecter' }));

        expect(routerPost).toHaveBeenCalledWith('/admin/logout');
    });

    it('un maintien de session refusé (401) renvoie à la connexion', async () => {
        fetchMock.mockResolvedValue({ status: 401 });
        render(<Harness timeout={900} />);

        await advance(290);
        fireEvent.keyDown(window, { key: 'a' });
        await advance(15);

        expect(redirect).toHaveBeenCalledWith('/admin/login');
    });
});

describe('redirectIfSessionExpired', () => {
    it('ne réagit qu’à un 401', () => {
        const assign = vi.fn();
        vi.stubGlobal('location', { ...window.location, assign });

        expect(redirectIfSessionExpired({ status: 200 })).toBe(false);
        expect(redirectIfSessionExpired({ status: 500 })).toBe(false);
        expect(assign).not.toHaveBeenCalled();

        expect(redirectIfSessionExpired({ status: 401 })).toBe(true);
        expect(assign).toHaveBeenCalledWith('/admin/login');

        vi.unstubAllGlobals();
    });
});

