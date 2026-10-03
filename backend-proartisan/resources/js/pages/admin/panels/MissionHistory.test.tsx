import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { post } = vi.hoisted(() => ({ post: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
    router: { post },
}));

import { MissionHistoryControl, MissionStateHistory } from './MissionHistory';

function respond(body: unknown, status = 200) {
    return { ok: status >= 200 && status < 300, status, json: async () => body };
}

beforeEach(() => {
    post.mockReset();
    vi.stubGlobal('fetch', vi.fn());
});

afterEach(() => vi.unstubAllGlobals());

describe('MissionStateHistory', () => {
    it('affiche chaque changement avec ses libellés, son acteur et son motif', async () => {
        (global.fetch as ReturnType<typeof vi.fn>).mockResolvedValue(respond({
            transitions: [
                {
                    id: 2, from_state_label: 'En litige', to_state_label: 'Terminée',
                    user: { id: 9, name: 'Mariam Koné', role: 'admin' },
                    reason: "Litige arbitré en faveur de l'artisan", reconstituted: false, unknown_date: false,
                    transitioned_at: '2026-10-02T10:00:00Z',
                },
                {
                    id: 1, from_state_label: 'En attente de financement', to_state_label: 'Financée',
                    user: null, reason: 'Acompte confirmé', reconstituted: true, unknown_date: false,
                    transitioned_at: '2026-09-01T10:00:00Z',
                },
            ],
        }));

        render(<MissionStateHistory missionId={12} />);

        expect(await screen.findByText('En litige → Terminée')).toBeInTheDocument();
        expect(screen.getByText(/Mariam Koné \(Administrateur\)/)).toBeInTheDocument();
        expect(screen.getByText('En attente de financement → Financée')).toBeInTheDocument();
        expect(screen.getByText(/Acteur non conservé — Acompte confirmé/)).toBeInTheDocument();
        expect(screen.getAllByText('Reconstitué')).toHaveLength(1);
        expect(global.fetch).toHaveBeenCalledWith('/admin/missions/12/historique', expect.anything());
    });

    it("signale une date non conservée plutôt que d'afficher la date du constat", async () => {
        (global.fetch as ReturnType<typeof vi.fn>).mockResolvedValue(respond({
            transitions: [{
                id: 1, from_state_label: 'Financée', to_state_label: 'Terminée', user: null,
                reason: 'État constaté à la reconstitution', reconstituted: true, unknown_date: true,
                transitioned_at: '2026-10-03T10:00:00Z',
            }],
        }));

        render(<MissionStateHistory missionId={12} />);

        expect(await screen.findByText('Date non conservée')).toBeInTheDocument();
    });

    it("annonce une mission sans changement d'état", async () => {
        (global.fetch as ReturnType<typeof vi.fn>).mockResolvedValue(respond({ transitions: [] }));

        render(<MissionStateHistory missionId={12} />);

        expect(await screen.findByText(/Aucun changement d'état enregistré/)).toBeInTheDocument();
    });

    it("annonce l'échec du chargement, jamais une liste vide", async () => {
        (global.fetch as ReturnType<typeof vi.fn>).mockResolvedValue(respond({}, 500));

        render(<MissionStateHistory missionId={12} />);

        expect(await screen.findByRole('alert')).toHaveTextContent("L'historique n'a pas pu être chargé");
        expect(screen.queryByText(/Aucun changement d'état enregistré/)).not.toBeInTheDocument();
    });
});

describe('MissionHistoryControl', () => {
    it('ne contrôle rien avant la demande', () => {
        render(<MissionHistoryControl canRebuild />);

        expect(global.fetch).not.toHaveBeenCalled();
    });

    it('annonce un historique complet sans proposer de reconstitution', async () => {
        (global.fetch as ReturnType<typeof vi.fn>).mockResolvedValue(respond({ missions: 40, incomplete: 0, lines: 0 }));
        render(<MissionHistoryControl canRebuild />);

        fireEvent.click(screen.getByRole('button', { name: "Contrôler l'historique" }));

        expect(await screen.findByRole('status')).toHaveTextContent('Historique complet sur les 40 mission(s)');
        expect(screen.queryByRole('button', { name: "Reconstituer l'historique" })).not.toBeInTheDocument();
    });

    it('reconstitue après confirmation', async () => {
        (global.fetch as ReturnType<typeof vi.fn>).mockResolvedValue(respond({ missions: 40, incomplete: 3, lines: 7 }));
        render(<MissionHistoryControl canRebuild />);

        fireEvent.click(screen.getByRole('button', { name: "Contrôler l'historique" }));
        fireEvent.click(await screen.findByRole('button', { name: "Reconstituer l'historique" }));

        const dialog = await screen.findByRole('dialog');
        expect(dialog).toHaveTextContent('7 ligne(s) seront ajoutées à 3 mission(s)');
        expect(post).not.toHaveBeenCalled();

        fireEvent.click(screen.getByRole('button', { name: 'Reconstituer' }));

        await waitFor(() => expect(post).toHaveBeenCalledWith('/admin/missions/historique/reconstituer', {}, expect.anything()));
    });

    it('ne propose pas la reconstitution sans la capacité de gestion', async () => {
        (global.fetch as ReturnType<typeof vi.fn>).mockResolvedValue(respond({ missions: 40, incomplete: 3, lines: 7 }));
        render(<MissionHistoryControl canRebuild={false} />);

        fireEvent.click(screen.getByRole('button', { name: "Contrôler l'historique" }));

        expect(await screen.findByRole('status')).toHaveTextContent('3 mission(s) sur 40');
        expect(screen.queryByRole('button', { name: "Reconstituer l'historique" })).not.toBeInTheDocument();
    });

    it("annonce l'échec du contrôle", async () => {
        (global.fetch as ReturnType<typeof vi.fn>).mockResolvedValue(respond({}, 500));
        render(<MissionHistoryControl canRebuild />);

        fireEvent.click(screen.getByRole('button', { name: "Contrôler l'historique" }));

        expect(await screen.findByRole('alert')).toHaveTextContent("Le contrôle n'a pas abouti");
    });
});
