import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const routerPost = vi.fn();
vi.mock('@inertiajs/react', () => ({
    router: { post: (...args: unknown[]) => routerPost(...args) },
}));

import { DeliveriesTrackingSection } from './DeliveriesTrackingSection';
import type { AdminOrder } from '../shared';

function makeOrder(overrides: Partial<AdminOrder> = {}): AdminOrder {
    return {
        id: 100,
        status: 'shipping',
        created_at: '2026-02-01T09:00:00Z',
        delivery_cost: 1500,
        pickup_code: '4821',
        reception_code: '7734',
        supplier: { id: 1, name: 'Quincaillerie Koffi' },
        client: { id: 2, name: 'Awa Traoré' },
        driver: { id: 3, name: 'Ismaël Ouattara', phone: '+2250700000009' },
        items: [],
        ...overrides,
    } as AdminOrder;
}

describe('DeliveriesTrackingSection', () => {
    beforeEach(() => {
        routerPost.mockClear();
        vi.stubGlobal('fetch', vi.fn());
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    it('affiche un message vide sans course active', () => {
        render(<DeliveriesTrackingSection orders={[]} />);
        expect(screen.getByText(/Aucune course de matériaux active/)).toBeInTheDocument();
    });

    it('n\'affiche que les livraisons dans un état actif', () => {
        render(
            <DeliveriesTrackingSection
                orders={[
                    makeOrder({ id: 1, status: 'shipping' }),
                    makeOrder({ id: 2, status: 'delivered' }),
                ]}
            />,
        );

        expect(screen.getByText('Course #1')).toBeInTheDocument();
        expect(screen.queryByText('Course #2')).not.toBeInTheDocument();
    });

    it('déclenche onSelectOrder au clic sur l\'icône de consultation', () => {
        const onSelectOrder = vi.fn();
        const order = makeOrder();
        render(<DeliveriesTrackingSection orders={[order]} onSelectOrder={onSelectOrder} />);

        fireEvent.click(screen.getByTitle('Consulter le dossier complet'));

        expect(onSelectOrder).toHaveBeenCalledWith(order);
    });

    it('charge la télémétrie en direct et affiche l\'itinéraire OSRM', async () => {
        (global.fetch as ReturnType<typeof vi.fn>).mockResolvedValue({
            ok: true,
            json: async () => ({
                tracking: {
                    route: { distance_km: 4.2, duration_min: 12, geometry: [1, 2, 3] },
                    driver: { position: { lat: 5.32, lng: -4.01, speed: 22 } },
                },
            }),
        });
        render(<DeliveriesTrackingSection orders={[makeOrder()]} />);

        fireEvent.click(screen.getByText('Télémétrie & OSRM'));

        await waitFor(() => expect(screen.getByText('4.2 km')).toBeInTheDocument());
        expect(screen.getByText('5.32000')).toBeInTheDocument();
    });

    it('demande confirmation via la modale accessible avant de réaffecter une course', async () => {
        render(<DeliveriesTrackingSection orders={[makeOrder({ id: 5 })]} />);

        fireEvent.click(screen.getByTitle('Réassigner un autre livreur'));
        const dialog = await screen.findByRole('dialog');
        fireEvent.click(within(dialog).getByRole('button', { name: 'Réaffecter' }));

        await waitFor(() =>
            expect(routerPost).toHaveBeenCalledWith(
                '/api/v1/orders/5/reassign',
                expect.objectContaining({ reason: expect.any(String) }),
                expect.objectContaining({ preserveScroll: true }),
            ),
        );
    });

    it('n\'appelle pas le backend si la réaffectation est annulée', async () => {
        render(<DeliveriesTrackingSection orders={[makeOrder()]} />);

        fireEvent.click(screen.getByTitle('Réassigner un autre livreur'));
        const dialog = await screen.findByRole('dialog');
        fireEvent.click(within(dialog).getByRole('button', { name: 'Annuler' }));

        expect(routerPost).not.toHaveBeenCalled();
    });

    it('ne propose pas de réaffectation pour une course en recherche de livreur', () => {
        render(<DeliveriesTrackingSection orders={[makeOrder({ status: 'searching_driver', driver: undefined })]} />);

        expect(screen.queryByTitle('Réassigner un autre livreur')).not.toBeInTheDocument();
    });
});
