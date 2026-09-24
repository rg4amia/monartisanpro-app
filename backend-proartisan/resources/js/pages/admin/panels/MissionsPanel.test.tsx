import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({
    router: { post: vi.fn() },
}));

import type { AdminMission, AdminOrder, DeliveryStats, MissionStats, Paginated } from '../shared';
import { MissionsPanel } from './MissionsPanel';

const missionStats: MissionStats = { en_cours: 4, en_litige: 1, referent_required: 2, enrichies: 3 };
const deliveryStats: DeliveryStats = { total: 5, in_transit: 2, awaiting_driver: 1, delivered: 2, by_status: {} };

function makeMission(overrides: Partial<AdminMission> = {}): AdminMission {
    return {
        id: 12,
        description: 'Réfection toiture villa Cocody',
        status: 'in_progress',
        montant_total: 350000,
        client: { id: 1, name: 'Awa Traoré' },
        artisan: { id: 2, name: 'Koffi N\'Guessan' },
        created_at: '2026-02-01T09:00:00Z',
        ...overrides,
    } as AdminMission;
}

function makeOrder(overrides: Partial<AdminOrder> = {}): AdminOrder {
    return {
        id: 100,
        status: 'shipping',
        created_at: '2026-02-01T09:00:00Z',
        delivery_cost: 1500,
        pickup_code: '4821',
        reception_code: '7734',
        items: [],
        ...overrides,
    } as AdminOrder;
}

function makePage<T>(data: T[]): Paginated<T> {
    return {
        data,
        links: [],
        current_page: 1,
        last_page: 1,
        total: data.length,
        per_page: 15,
        from: data.length > 0 ? 1 : null,
        to: data.length > 0 ? data.length : null,
    };
}

function renderPanel(overrides: Partial<React.ComponentProps<typeof MissionsPanel>> = {}) {
    const defaultProps: React.ComponentProps<typeof MissionsPanel> = {
        missionSubTab: 'chantiers',
        onMissionSubTabChange: vi.fn(),
        deliveryStatusFilter: 'all',
        onDeliveryStatusFilterChange: vi.fn(),
        missionsPage: makePage([makeMission()]),
        ordersPage: makePage([makeOrder()]),
        missionStats,
        deliveryStats,
        missionSearch: '',
        onMissionSearchChange: vi.fn(),
        onMissionSubmit: vi.fn((e: React.FormEvent) => e.preventDefault()),
        orderSearch: '',
        onOrderSearchChange: vi.fn(),
        onOrderSubmit: vi.fn((e: React.FormEvent) => e.preventDefault()),
        onResetFilters: vi.fn(),
        exportParams: {},
        renderMissionPagination: () => null,
        renderOrderPagination: () => null,
        onSelectMission: vi.fn(),
        onSelectOrder: vi.fn(),
        ...overrides,
    };

    render(<MissionsPanel {...defaultProps} />);
    return defaultProps;
}

describe('MissionsPanel', () => {
    it('affiche le pipeline des missions chantiers avec les métriques clés', () => {
        renderPanel();

        expect(screen.getByText('Réfection toiture villa Cocody')).toBeInTheDocument();
        expect(screen.getByText('Awa Traoré')).toBeInTheDocument();
    });

    it('signale les missions au-delà du seuil Référent (2M FCFA)', () => {
        renderPanel({
            missionsPage: makePage([makeMission({ montant_total: 2500000 })]),
        });

        expect(screen.getByText('🛡️ Référent Requis')).toBeInTheDocument();
    });

    it('ne signale pas le seuil Référent sous 2M FCFA', () => {
        renderPanel({
            missionsPage: makePage([makeMission({ montant_total: 500000 })]),
        });

        expect(screen.queryByText('🛡️ Référent Requis')).not.toBeInTheDocument();
    });

    it('déclenche onSelectMission au clic sur une ligne', () => {
        const props = renderPanel();
        fireEvent.click(screen.getByText('Réfection toiture villa Cocody'));

        expect(props.onSelectMission).toHaveBeenCalledWith(
            expect.objectContaining({ id: 12 }),
        );
    });

    it('affiche un état vide sans mission correspondante', () => {
        renderPanel({ missionsPage: makePage([]) });
        expect(screen.getByText('Mission introuvable')).toBeInTheDocument();
    });

    it('bascule vers l\'onglet livraisons et affiche le radar de courses', () => {
        const onMissionSubTabChange = vi.fn();
        renderPanel({ onMissionSubTabChange });

        fireEvent.click(screen.getByText(/Livraisons Matériaux/));

        expect(onMissionSubTabChange).toHaveBeenCalledWith('livraisons');
    });

    it('affiche la liste des livraisons quand l\'onglet livraisons est actif', () => {
        renderPanel({ missionSubTab: 'livraisons' });

        expect(screen.getByText('Suivi des Livraisons & Courses Livreurs')).toBeInTheDocument();
        expect(screen.getAllByText('Course #100').length).toBeGreaterThan(0);
    });

    it('filtre les livraisons par statut au clic sur un filtre rapide', () => {
        const onDeliveryStatusFilterChange = vi.fn();
        renderPanel({ missionSubTab: 'livraisons', onDeliveryStatusFilterChange });

        fireEvent.click(screen.getByRole('button', { name: /En cours de livraison/ }));

        expect(onDeliveryStatusFilterChange).toHaveBeenCalledWith('shipping');
    });

    it('déclenche onSelectOrder au clic sur "Suivi 360°"', () => {
        const props = renderPanel({ missionSubTab: 'livraisons' });

        fireEvent.click(screen.getByRole('button', { name: 'Suivi 360°' }));

        expect(props.onSelectOrder).toHaveBeenCalledWith(
            expect.objectContaining({ id: 100 }),
        );
    });
});
