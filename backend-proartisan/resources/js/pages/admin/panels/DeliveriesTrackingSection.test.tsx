import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const routerPost = vi.fn();
vi.mock('@inertiajs/react', () => ({
    router: { post: (...args: unknown[]) => routerPost(...args) },
}));

import type { AdminOrder } from '../shared';
import { DeliveriesTrackingSection } from './DeliveriesTrackingSection';

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

    it('bascule sur la vue carte flotte et affiche les indicateurs agrégés', () => {
        const fleetData = {
            summary: {
                total_drivers: 3,
                online_drivers: 3,
                in_transit: 1,
                available: 2,
                stalled_alerts: 1,
                active_orders_count: 1,
                updated_at: '2026-09-25T19:00:00Z',
            },
            drivers: [
                {
                    id: 10,
                    name: 'Moussa Koné',
                    phone: '+2250700000010',
                    status: 'delivering' as const,
                    is_online: true,
                    is_stalled: true,
                    stalled_reason: 'Immobilisme prolongé détecté',
                    estimated_commune: 'Cocody',
                    speed_kmh: 32,
                    battery_level: 85,
                    position: { lat: 5.35, lng: -3.98 },
                    active_order: {
                        id: 101,
                        status: 'shipping',
                        delivery_cost: 1500,
                        total_amount: 15000,
                        supplier_name: 'Quincaillerie Centrale',
                        client_name: 'Adama Diop',
                        pickup_code: '4821',
                        reception_code: '7734',
                    },
                    last_ping_at: '2026-09-25T18:58:00Z',
                },
                {
                    id: 11,
                    name: 'Kouassi Yves',
                    phone: '+2250700000011',
                    status: 'available' as const,
                    is_online: true,
                    is_stalled: false,
                    estimated_commune: 'Yopougon',
                    speed_kmh: 0,
                    battery_level: 95,
                    position: { lat: 5.34, lng: -4.08 },
                    last_ping_at: '2026-09-25T18:55:00Z',
                },
            ],
        };

        render(<DeliveriesTrackingSection orders={[makeOrder()]} fleetOverview={fleetData} />);

        // Bascule d'onglet
        const mapTabButton = screen.getByRole('button', { name: /Carte Flotte en Direct/i });
        fireEvent.click(mapTabButton);

        // Vérification des indicateurs et de la présence des coursiers dans la liste
        expect(screen.getByText('Moussa Koné')).toBeInTheDocument();
        expect(screen.getByText('Kouassi Yves')).toBeInTheDocument();
    });

    it('permet de sélectionner un coursier sur la carte pour afficher sa télémétrie', () => {
        const fleetData = {
            summary: {
                total_drivers: 1,
                online_drivers: 1,
                in_transit: 1,
                available: 0,
                stalled_alerts: 0,
                active_orders_count: 1,
                updated_at: '2026-09-25T19:00:00Z',
            },
            drivers: [
                {
                    id: 20,
                    name: 'Bakary Diabaté',
                    phone: '+2250700000020',
                    status: 'delivering' as const,
                    is_online: true,
                    is_stalled: false,
                    estimated_commune: 'Plateau',
                    speed_kmh: 28,
                    battery_level: 60,
                    position: { lat: 5.32, lng: -4.02 },
                    active_order: {
                        id: 202,
                        status: 'driver_picked_up',
                        delivery_cost: 2000,
                        total_amount: 25000,
                        supplier_name: 'San Pedro Quincaillerie',
                        client_name: 'Fatou Bamba',
                        pickup_code: '1234',
                        reception_code: '5678',
                    },
                    last_ping_at: '2026-09-25T18:59:00Z',
                },
            ],
        };

        render(<DeliveriesTrackingSection orders={[]} fleetOverview={fleetData} />);

        fireEvent.click(screen.getByRole('button', { name: /Carte Flotte en Direct/i }));

        // Clic sur le livreur dans la liste latérale
        fireEvent.click(screen.getByText('Bakary Diabaté'));

        // Vérification de la fiche détaillée
        expect(screen.getByText(/Course #202/i)).toBeInTheDocument();
        expect(screen.getByText(/28 km\/h/i)).toBeInTheDocument();
        expect(screen.getByText(/60%/i)).toBeInTheDocument();
        expect(screen.getByText('San Pedro Quincaillerie')).toBeInTheDocument();
    });
});

