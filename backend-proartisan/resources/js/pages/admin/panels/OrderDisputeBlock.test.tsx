import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { post } = vi.hoisted(() => ({ post: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
    router: { post },
}));

import type { AdminOrder } from '../shared';
import { DisputeDebtsSection } from './DisputeDebtsSection';
import type { DisputeDebtsOverview } from './DisputeDebtsSection';
import { OrderDisputeBlock } from './OrderDisputeBlock';

function makeOrder(overrides: Partial<AdminOrder> = {}): AdminOrder {
    return {
        id: 100,
        status: 'disputed',
        subtotal: 20000,
        driver_id: 7,
        dispute_reason: 'Colis incomplet',
        dispute_opened_at: '2026-10-02T09:00:00Z',
        created_at: '2026-10-01T09:00:00Z',
        ...overrides,
    } as AdminOrder;
}

beforeEach(() => post.mockReset());

describe('OrderDisputeBlock', () => {
    it("n'affiche rien pour une commande sans litige", () => {
        const { container } = render(<OrderDisputeBlock order={makeOrder({ status: 'delivered' })} canResolve />);

        expect(container).toBeEmptyDOMElement();
    });

    it('affiche le motif du litige', () => {
        render(<OrderDisputeBlock order={makeOrder()} canResolve />);

        expect(screen.getByText('Litige ouvert par le client')).toBeInTheDocument();
        expect(screen.getByText(/Motif : Colis incomplet/)).toBeInTheDocument();
    });

    it('rejette la réclamation avec un motif obligatoire', async () => {
        render(<OrderDisputeBlock order={makeOrder()} canResolve />);

        fireEvent.click(screen.getByRole('button', { name: 'Rejeter la réclamation' }));

        const dialog = await screen.findByRole('dialog');
        expect(dialog).toHaveTextContent('sans remboursement');

        const confirmButton = screen.getByRole('button', { name: 'Clore le litige' });
        expect(confirmButton).toBeDisabled();

        fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Livraison conforme à la photo.' } });
        fireEvent.click(confirmButton);

        await waitFor(() =>
            expect(post).toHaveBeenCalledWith(
                '/admin/orders/100/dispute/resolve',
                { outcome: 'reclamation_rejetee', note: 'Livraison conforme à la photo.' },
                expect.anything(),
            ),
        );
    });

    it('accepte la réclamation avec un montant, un responsable et un motif', () => {
        render(<OrderDisputeBlock order={makeOrder()} canResolve />);

        fireEvent.click(screen.getByRole('button', { name: 'Accepter la réclamation' }));

        const submit = screen.getByRole('button', { name: 'Rembourser et clore le litige' });
        expect(submit).toBeDisabled();
        expect(screen.getByText(/Les frais de service ne sont pas remboursés/)).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText(/Montant à rembourser/), { target: { value: '8000' } });
        fireEvent.change(screen.getByLabelText('Responsable'), { target: { value: 'livreur' } });
        fireEvent.change(screen.getByLabelText('Motif de la décision'), { target: { value: 'Sacs éventrés pendant le transport.' } });
        fireEvent.click(submit);

        expect(post).toHaveBeenCalledWith(
            '/admin/orders/100/dispute/resolve',
            {
                outcome: 'reclamation_acceptee',
                note: 'Sacs éventrés pendant le transport.',
                refund_amount: 8000,
                responsible: 'livreur',
            },
            expect.anything(),
        );
    });

    it('refuse un montant au-delà du prix des articles', () => {
        render(<OrderDisputeBlock order={makeOrder()} canResolve />);
        fireEvent.click(screen.getByRole('button', { name: 'Accepter la réclamation' }));

        fireEvent.change(screen.getByLabelText(/Montant à rembourser/), { target: { value: '20001' } });
        fireEvent.change(screen.getByLabelText('Motif de la décision'), { target: { value: 'Décision motivée.' } });

        expect(screen.getByRole('alert')).toHaveTextContent('Le montant doit être compris entre 1 FCFA');
        expect(screen.getByRole('button', { name: 'Rembourser et clore le litige' })).toBeDisabled();
        expect(post).not.toHaveBeenCalled();
    });

    it('ne propose pas le livreur pour une commande sans livreur', () => {
        render(<OrderDisputeBlock order={makeOrder({ driver_id: null })} canResolve />);
        fireEvent.click(screen.getByRole('button', { name: 'Accepter la réclamation' }));

        expect(screen.getByLabelText('Responsable').querySelectorAll('option')).toHaveLength(1);
    });

    it("ne propose pas la clôture sans le droit d'arbitrer", () => {
        render(<OrderDisputeBlock order={makeOrder()} canResolve={false} />);

        expect(screen.queryByRole('button', { name: 'Rejeter la réclamation' })).not.toBeInTheDocument();
        expect(screen.getByText(/demande le droit d'arbitrer/)).toBeInTheDocument();
    });
});

describe('DisputeDebtsSection', () => {
    const overview: DisputeDebtsOverview = {
        stats: { en_cours: 1, restant: 7000, recouvre: 12000 },
        debts: [
            {
                id: 4,
                order_id: 100,
                montant: 19000,
                montant_recouvre: 12000,
                restant: 7000,
                statut: 'en_cours',
                statut_label: 'À rembourser',
                created_at: '2026-10-02T09:00:00Z',
                settled_at: null,
                entries: [{ id: 1, montant: 12000, source: 'prelevement_gains', source_label: 'Prélèvement sur vos gains', created_at: null }],
                user: { id: 5, name: 'Quincaillerie du Plateau', phone: '+2250700000005', role: 'fournisseur' },
            },
        ],
    };

    it('affiche les dettes en cours avec leur débiteur', () => {
        render(<DisputeDebtsSection overview={overview} />);

        expect(screen.getByText('Quincaillerie du Plateau')).toBeInTheDocument();
        expect(screen.getByText(/Fournisseur · \+2250700000005/)).toBeInTheDocument();
        expect(screen.getAllByText('À rembourser').length).toBeGreaterThan(0);
    });

    it("annule une dette après confirmation et motif", async () => {
        render(<DisputeDebtsSection overview={overview} />);

        fireEvent.click(screen.getByRole('button', { name: 'Annuler la dette' }));
        const dialog = await screen.findByRole('dialog');
        expect(dialog).toHaveTextContent('ProsArtisan supporte alors la somme');

        fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Erreur de responsable.' } });
        fireEvent.click(within(dialog, 'Annuler la dette'));

        await waitFor(() =>
            expect(post).toHaveBeenCalledWith('/admin/dispute-debts/4/cancel', { reason: 'Erreur de responsable.' }, expect.anything()),
        );
    });

    it('annonce une panne de chargement plutôt qu’une liste vide', () => {
        render(<DisputeDebtsSection overview={null} />);

        expect(screen.getByRole('alert')).toHaveTextContent("n'ont pas pu être chargées");
        expect(screen.queryByText('Aucune dette de litige')).not.toBeInTheDocument();
    });

    it('dit explicitement quand il n’y a aucune dette', () => {
        render(<DisputeDebtsSection overview={{ stats: { en_cours: 0, restant: 0, recouvre: 0 }, debts: [] }} />);

        expect(screen.getByText('Aucune dette de litige')).toBeInTheDocument();
    });
});

/** Bouton d'une modale, par son nom accessible. */
function within(container: HTMLElement, name: string): HTMLElement {
    const button = Array.from(container.querySelectorAll('button')).find((el) => el.textContent === name);
    if (!button) throw new Error(`Bouton « ${name} » introuvable dans la modale.`);
    return button;
}
