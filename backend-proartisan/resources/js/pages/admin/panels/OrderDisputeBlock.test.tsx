import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { post } = vi.hoisted(() => ({ post: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
    router: { post },
}));

import type { AdminOrder } from '../shared';
import { OrderDisputeBlock } from './OrderDisputeBlock';

function makeOrder(overrides: Partial<AdminOrder> = {}): AdminOrder {
    return {
        id: 100,
        status: 'disputed',
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

    it('clôt le litige avec son issue et un motif obligatoire', async () => {
        render(<OrderDisputeBlock order={makeOrder()} canResolve />);

        fireEvent.click(screen.getByRole('button', { name: 'Rejeter la réclamation' }));

        const dialog = await screen.findByRole('dialog');
        expect(dialog).toHaveTextContent("Aucun fonds n'est déplacé");

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

    it("n'envoie rien si l'administrateur annule", async () => {
        render(<OrderDisputeBlock order={makeOrder()} canResolve />);

        fireEvent.click(screen.getByRole('button', { name: 'Accepter la réclamation' }));
        await screen.findByRole('dialog');
        fireEvent.click(screen.getByRole('button', { name: 'Annuler' }));

        await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
        expect(post).not.toHaveBeenCalled();
    });

    it("ne propose pas la clôture sans le droit d'arbitrer", () => {
        render(<OrderDisputeBlock order={makeOrder()} canResolve={false} />);

        expect(screen.queryByRole('button', { name: 'Rejeter la réclamation' })).not.toBeInTheDocument();
        expect(screen.getByText(/demande le droit d'arbitrer/)).toBeInTheDocument();
    });
});
