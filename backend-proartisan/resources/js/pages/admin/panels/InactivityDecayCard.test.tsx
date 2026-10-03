import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import type { InactivityDecayOverview } from '../shared';
import { InactivityDecayCard } from './InactivityDecayCard';

const routerPut = vi.fn();

vi.mock('@inertiajs/react', () => ({
    router: { put: (...args: unknown[]) => routerPut(...args) },
}));

function makeOverview(overrides: Partial<InactivityDecayOverview> = {}): InactivityDecayOverview {
    return {
        enabled: false,
        source: 'configuration',
        threshold_days: 60,
        points: 5,
        concerned: 12,
        penalties_30d: 0,
        points_removed_30d: 0,
        updated_at: null,
        ...overrides,
    };
}

describe('InactivityDecayCard', () => {
    beforeEach(() => {
        routerPut.mockReset();
    });

    it('annonce la dégradation désactivée et les artisans qui seraient visés', () => {
        render(<InactivityDecayCard overview={makeOverview()} canManage />);

        expect(screen.getByText('Désactivée')).toBeInTheDocument();
        expect(screen.getByText('Artisans inactifs qui seraient visés')).toBeInTheDocument();
        expect(screen.getByText('12')).toBeInTheDocument();
        expect(screen.getByText(/Jamais réglée depuis le backoffice/)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Activer la dégradation' })).toBeInTheDocument();
    });

    it("n'active la dégradation qu'après confirmation", async () => {
        render(<InactivityDecayCard overview={makeOverview()} canManage />);

        fireEvent.click(screen.getByRole('button', { name: 'Activer la dégradation' }));

        const dialog = await screen.findByRole('dialog');
        expect(within(dialog).getByText(/12 artisans concernés aujourd'hui/)).toBeInTheDocument();
        expect(within(dialog).getByText(/perdra 5 points par semaine/)).toBeInTheDocument();
        expect(routerPut).not.toHaveBeenCalled();

        fireEvent.click(within(dialog).getByRole('button', { name: 'Activer' }));

        await waitFor(() => expect(routerPut).toHaveBeenCalledTimes(1));
        expect(routerPut.mock.calls[0][0]).toBe('/admin/evaluations/inactivity-decay');
        expect(routerPut.mock.calls[0][1]).toEqual({ enabled: true });
    });

    it("n'envoie rien si la confirmation est refusée", async () => {
        render(<InactivityDecayCard overview={makeOverview()} canManage />);

        fireEvent.click(screen.getByRole('button', { name: 'Activer la dégradation' }));
        const dialog = await screen.findByRole('dialog');
        fireEvent.click(within(dialog).getByRole('button', { name: 'Annuler' }));

        await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
        expect(routerPut).not.toHaveBeenCalled();
    });

    it('désactive une dégradation activée', async () => {
        render(
            <InactivityDecayCard
                overview={makeOverview({ enabled: true, source: 'reglage', penalties_30d: 4, points_removed_30d: 20, updated_at: '2026-10-03T09:00:00Z' })}
                canManage
            />,
        );

        expect(screen.getByText('Activée')).toBeInTheDocument();
        expect(screen.getByText('Artisans inactifs visés')).toBeInTheDocument();
        expect(screen.getByText('20')).toBeInTheDocument();
        expect(screen.getByText(/Réglé depuis le backoffice/)).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Désactiver la dégradation' }));
        const dialog = await screen.findByRole('dialog');
        fireEvent.click(within(dialog).getByRole('button', { name: 'Désactiver' }));

        await waitFor(() => expect(routerPut).toHaveBeenCalledTimes(1));
        expect(routerPut.mock.calls[0][1]).toEqual({ enabled: false });
    });

    it('ne propose aucune action sans la capacité des réglages', () => {
        render(<InactivityDecayCard overview={makeOverview()} canManage={false} />);

        expect(screen.queryByRole('button', { name: 'Activer la dégradation' })).not.toBeInTheDocument();
        expect(screen.getByText(/La capacité « Paramètres » est requise/)).toBeInTheDocument();
    });

    it("annonce l'échec du chargement plutôt qu'un état inventé", () => {
        render(<InactivityDecayCard overview={undefined} canManage />);

        expect(screen.getByRole('alert')).toHaveTextContent("n'a pas pu être chargé");
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });
});
