import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type { AdminMission, AdminOrder, AdminTransaction, ArtisanScoreItem, ScoreLedgerEntryItem } from '../shared';
import {
    ArtisanLedgerModal,
    MissionDetailModal,
    OrderDetailModal,
    TransactionDetailModal,
} from './DetailModals';

function makeArtisan(overrides: Partial<ArtisanScoreItem> = {}): ArtisanScoreItem {
    return {
        id: 3,
        name: "Koffi N'Guessan",
        phone: '+2250700000002',
        score_prosartisan: 750,
        score_frozen: false,
        evaluations_recues_count: 12,
        ...overrides,
    };
}

function makeLedgerEntry(overrides: Partial<ScoreLedgerEntryItem> = {}): ScoreLedgerEntryItem {
    return {
        id: 1,
        user_id: 3,
        event_type: 'mission_completed',
        points: 25,
        credibility_factor: 1,
        description: 'Mission #12 terminée avec succès.',
        created_at: '2026-02-01T09:00:00Z',
        ...overrides,
    } as ScoreLedgerEntryItem;
}

function makeMission(overrides: Partial<AdminMission> = {}): AdminMission {
    return {
        id: 12,
        description: 'Réfection toiture villa Cocody',
        status: 'in_progress',
        montant_total: 350000,
        client: { id: 1, name: 'Awa Traoré', phone: '+2250700000001' },
        artisan: { id: 2, name: "Koffi N'Guessan", phone: '+2250700000002' },
        created_at: '2026-02-01T09:00:00Z',
        jalons: [],
        jcodes: [],
        transactions: [],
        ...overrides,
    } as AdminMission;
}

function makeOrder(overrides: Partial<AdminOrder> = {}): AdminOrder {
    return {
        id: 100,
        client_id: 1,
        supplier_id: 5,
        delivery_mode: 'livraison',
        status: 'shipping',
        subtotal: 45000,
        delivery_cost: 1500,
        platform_fee: 0,
        total_amount: 46500,
        pickup_code: '4821',
        reception_code: '7734',
        created_at: '2026-02-01T09:00:00Z',
        ...overrides,
    } as AdminOrder;
}

function makeTransaction(overrides: Partial<AdminTransaction> = {}): AdminTransaction {
    return {
        id: 9,
        type: 'acompte',
        montant: 100000,
        provider: 'wave',
        statut: 'confirme',
        wallet_source: 'client',
        wallet_dest: 'wallet_mo',
        created_at: '2026-02-01T09:00:00Z',
        reference_externe: 'WV-REF-1',
        user: { name: 'Awa Traoré', phone: '+2250700000001' },
        ...overrides,
    };
}

describe('ArtisanLedgerModal', () => {
    it('affiche uniquement les entrées du ledger de l\'artisan ciblé', () => {
        render(
            <ArtisanLedgerModal
                artisan={makeArtisan()}
                scoreLedger={[
                    makeLedgerEntry({ id: 1, user_id: 3 }),
                    makeLedgerEntry({ id: 2, user_id: 99, description: 'Entrée dun autre artisan' }),
                ]}
                onClose={vi.fn()}
            />,
        );

        expect(screen.getByText('Mission #12 terminée avec succès.')).toBeInTheDocument();
        expect(screen.queryByText('Entrée dun autre artisan')).not.toBeInTheDocument();
        expect(screen.getByText('+25 points')).toBeInTheDocument();
    });

    it('affiche un état vide sans événement pour cet artisan', () => {
        render(<ArtisanLedgerModal artisan={makeArtisan()} scoreLedger={[]} onClose={vi.fn()} />);
        expect(screen.getByText('Historique vide')).toBeInTheDocument();
    });

    it('déclenche onClose', () => {
        const onClose = vi.fn();
        render(<ArtisanLedgerModal artisan={makeArtisan()} scoreLedger={[]} onClose={onClose} />);

        // Deux boutons portent le nom accessible "Fermer" : la croix d'en-tête
        // (aria-label) et le bouton texte du pied de modale.
        const closeButtons = screen.getAllByRole('button', { name: 'Fermer' });
        fireEvent.click(closeButtons[closeButtons.length - 1]);

        expect(onClose).toHaveBeenCalledTimes(1);
    });
});

describe('MissionDetailModal', () => {
    it('affiche les informations clés de la mission', () => {
        render(<MissionDetailModal mission={makeMission()} orders={[]} onClose={vi.fn()} onSelectOrder={vi.fn()} />);

        expect(screen.getByText('Détails de la mission #12')).toBeInTheDocument();
        expect(screen.getByText('Awa Traoré')).toBeInTheDocument();
        expect(screen.getByText("Koffi N'Guessan")).toBeInTheDocument();
    });

    it('signale l\'absence de jalons/j-codes/transactions', () => {
        render(<MissionDetailModal mission={makeMission()} orders={[]} onClose={vi.fn()} onSelectOrder={vi.fn()} />);

        expect(screen.getByText('Aucun jalon défini.')).toBeInTheDocument();
        expect(screen.getByText('Aucun J-Code généré.')).toBeInTheDocument();
        expect(screen.getByText('Aucune transaction enregistrée.')).toBeInTheDocument();
    });

    it('affiche uniquement les courses liées à la mission et déclenche onSelectOrder', () => {
        const order = makeOrder({ client_id: 1, mission_id: 12 });
        const unrelatedOrder = makeOrder({ id: 101, client_id: 1, mission_id: 99 });
        const onSelectOrder = vi.fn();
        render(
            <MissionDetailModal
                mission={makeMission({ id: 12 })}
                orders={[order, unrelatedOrder]}
                onClose={vi.fn()}
                onSelectOrder={onSelectOrder}
            />,
        );

        expect(screen.getByText('Course #100')).toBeInTheDocument();
        expect(screen.queryByText('Course #101')).not.toBeInTheDocument();

        fireEvent.click(screen.getByText('Course #100'));

        expect(onSelectOrder).toHaveBeenCalledWith(order);
    });

    it('affiche un avertissement explicite si l artisan a refuse la demande', () => {
        render(
            <MissionDetailModal
                mission={makeMission({
                    id: 13,
                    artisan_rejected_at: '2026-09-22T08:00:00Z',
                })}
                orders={[]}
                onClose={vi.fn()}
                onSelectOrder={vi.fn()}
            />,
        );

        expect(screen.getByText("Demande d'intervention refusée par l'artisan")).toBeInTheDocument();
        expect(screen.getByText('Non affecté (Demande refusée)')).toBeInTheDocument();
    });
});

describe('OrderDetailModal', () => {
    beforeEach(() => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false }));
    });

    afterEach(() => vi.unstubAllGlobals());

    it('affiche le statut et la progression de la course', () => {
        render(<OrderDetailModal order={makeOrder({ status: 'shipping' })} onClose={vi.fn()} />);

        expect(screen.getByText('Suivi 360° Livraison #100')).toBeInTheDocument();
        expect(screen.getByText('3. Coursier assigné')).toBeInTheDocument();
    });

    it('recharge la télémétrie au clic sur "Actualiser OSRM"', async () => {
        (global.fetch as ReturnType<typeof vi.fn>).mockResolvedValue({
            ok: true,
            json: async () => ({ tracking: { routing: { source: 'osrm', distance_km: 4.2, duration_minutes: 12 } } }),
        });
        render(<OrderDetailModal order={makeOrder({ status: 'delivered' })} onClose={vi.fn()} />);

        fireEvent.click(screen.getByRole('button', { name: /Actualiser OSRM/ }));

        await waitFor(() => expect(screen.getByText(/OSRM API v5/)).toBeInTheDocument());
    });

    it('déclenche onClose', () => {
        const onClose = vi.fn();
        render(<OrderDetailModal order={makeOrder()} onClose={onClose} />);

        fireEvent.click(screen.getByRole('button', { name: 'Fermer' }));

        expect(onClose).toHaveBeenCalledTimes(1);
    });

    it("affiche l'adresse de livraison figée sur la commande plutôt que le profil du client", () => {
        render(
            <OrderDetailModal
                order={makeOrder({
                    delivery_mode: 'delivery',
                    client: { id: 1, name: 'Awa Traoré', phone: '+2250700000001', role: 'client' },
                    recipient_name: 'Bamba Inza',
                    recipient_phone: '+2250707262811',
                    delivery_address_line: 'Cocody Angré 8e Tranche',
                    delivery_city: 'Abidjan',
                })}
                onClose={vi.fn()}
            />,
        );

        // Le destinataire de la commande peut différer du titulaire du compte
        // (cadeau, livraison pour un tiers) : c'est le snapshot figé sur la
        // commande qui doit primer, jamais le profil du client connecté.
        expect(screen.getByText('Bamba Inza')).toBeInTheDocument();
        expect(screen.getByText(/Cocody Angré 8e Tranche, Abidjan/)).toBeInTheDocument();
        expect(screen.queryByText('Awa Traoré')).not.toBeInTheDocument();
    });

    it('signale une adresse manquante sur une commande en livraison', () => {
        render(
            <OrderDetailModal
                order={makeOrder({
                    delivery_mode: 'delivery',
                    recipient_name: undefined,
                    recipient_phone: undefined,
                    delivery_address_line: undefined,
                    delivery_city: undefined,
                })}
                onClose={vi.fn()}
            />,
        );

        expect(
            screen.getByText(/Adresse de livraison non renseignée sur cette commande/),
        ).toBeInTheDocument();
    });

    it("n'affiche pas de ligne d'adresse pour un retrait en magasin", () => {
        render(<OrderDetailModal order={makeOrder({ delivery_mode: 'pickup' })} onClose={vi.fn()} />);

        expect(
            screen.queryByText(/Adresse de livraison non renseignée sur cette commande/),
        ).not.toBeInTheDocument();
    });
});

describe('TransactionDetailModal', () => {
    it('affiche le détail de la transaction et le lien de reçu', () => {
        render(<TransactionDetailModal transaction={makeTransaction()} onClose={vi.fn()} />);

        expect(screen.getByText('Transaction #9')).toBeInTheDocument();
        expect(screen.getByText('WV-REF-1')).toBeInTheDocument();
        expect(screen.getByText('Awa Traoré')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Télécharger le Reçu Officiel/ })).toHaveAttribute(
            'href',
            '/admin/transactions/9/receipt',
        );
    });

    it('affiche la mission associée quand elle est fournie', () => {
        render(
            <TransactionDetailModal
                transaction={makeTransaction({ mission: { id: 12, description: 'Réfection toiture' } })}
                onClose={vi.fn()}
            />,
        );

        expect(screen.getByText('#12 - Réfection toiture')).toBeInTheDocument();
    });

    it('déclenche onClose', () => {
        const onClose = vi.fn();
        render(<TransactionDetailModal transaction={makeTransaction()} onClose={onClose} />);

        fireEvent.click(screen.getByRole('button', { name: 'Fermer' }));

        expect(onClose).toHaveBeenCalledTimes(1);
    });
});
