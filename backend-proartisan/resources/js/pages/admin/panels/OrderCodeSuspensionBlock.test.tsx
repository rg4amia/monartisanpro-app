import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { post } = vi.hoisted(() => ({ post: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
    router: { post },
}));

import type { AdminOrder } from '../shared';
import { OrderCodeSuspensionBlock } from './OrderCodeSuspensionBlock';

function makeOrder(overrides: Partial<AdminOrder> = {}): AdminOrder {
    return {
        id: 100,
        status: 'driver_picked_up',
        created_at: '2026-10-05T09:00:00Z',
        code_suspensions: { pickup: null, reception: '2026-10-05T10:30:00Z' },
        ...overrides,
    } as AdminOrder;
}

beforeEach(() => post.mockReset());

describe('OrderCodeSuspensionBlock', () => {
    it("n'affiche rien quand aucun code n'est suspendu", () => {
        const { container } = render(
            <OrderCodeSuspensionBlock order={makeOrder({ code_suspensions: { pickup: null, reception: null } })} canLift />,
        );

        expect(container).toBeEmptyDOMElement();
    });

    it("n'affiche rien pour une commande reçue sans cet état", () => {
        const { container } = render(<OrderCodeSuspensionBlock order={makeOrder({ code_suspensions: undefined })} canLift />);

        expect(container).toBeEmptyDOMElement();
    });

    it('annonce le code suspendu, et lui seul', () => {
        render(<OrderCodeSuspensionBlock order={makeOrder()} canLift />);

        expect(screen.getByText('Saisie de code suspendue')).toBeInTheDocument();
        expect(screen.getByText(/Code de réception : suspendu jusqu/)).toBeInTheDocument();
        expect(screen.queryByText(/Code de retrait : suspendu/)).not.toBeInTheDocument();
    });

    it('sans le droit de gérer les missions, ne propose pas la levée', () => {
        render(<OrderCodeSuspensionBlock order={makeOrder()} canLift={false} />);

        expect(screen.getByText(/Code de réception : suspendu jusqu/)).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Lever la suspension/ })).not.toBeInTheDocument();
    });

    it('lève la suspension après confirmation, avec un motif obligatoire', async () => {
        render(<OrderCodeSuspensionBlock order={makeOrder()} canLift />);

        fireEvent.click(screen.getByRole('button', { name: 'Lever la suspension du code de réception' }));

        const dialog = await screen.findByRole('dialog');
        expect(dialog).toHaveTextContent('repartira de zéro');

        const confirmButton = screen.getByRole('button', { name: 'Lever la suspension' });
        expect(confirmButton).toBeDisabled();

        fireEvent.change(screen.getByLabelText('Motif de la levée'), { target: { value: 'Client joint par téléphone' } });
        fireEvent.click(confirmButton);

        await waitFor(() =>
            expect(post).toHaveBeenCalledWith(
                '/admin/orders/100/codes/unlock',
                { code: 'reception', reason: 'Client joint par téléphone' },
                expect.any(Object),
            ),
        );
    });

    it("n'envoie rien si l'administrateur annule", async () => {
        render(<OrderCodeSuspensionBlock order={makeOrder()} canLift />);

        fireEvent.click(screen.getByRole('button', { name: 'Lever la suspension du code de réception' }));
        await screen.findByRole('dialog');
        fireEvent.click(screen.getByRole('button', { name: 'Annuler' }));

        await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
        expect(post).not.toHaveBeenCalled();
    });
});
