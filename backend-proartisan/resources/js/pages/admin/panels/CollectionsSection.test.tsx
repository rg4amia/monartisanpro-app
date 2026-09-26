import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const routerPost = vi.fn();
vi.mock('@inertiajs/react', () => ({
    router: {
        post: (...args: unknown[]) => routerPost(...args),
    },
}));

import { CollectionsSection } from './CollectionsSection';
import type { CollectionsOverview } from './CollectionsSection';

function overview(overrides: Partial<CollectionsOverview> = {}): CollectionsOverview {
    return {
        unpaid: [
            {
                order_id: 42,
                client: { id: 7, name: 'Awa Koné', phone: '+2250700000007', restricted: true },
                driver: { id: 9, name: 'Koffi Livreur' },
                montant: 3500,
                delivered_at: '2026-09-20T10:00:00+00:00',
                reminders_count: 5,
                last_reminder_at: '2026-09-25T10:00:00+00:00',
                next_reminder_at: '2026-09-26T10:00:00+00:00',
            },
        ],
        restricted: [
            { id: 7, name: 'Awa Koné', phone: '+2250700000007', restricted_at: '2026-09-26T10:00:00+00:00', reason: 'Course de la commande #42 impayée après 5 relances.' },
        ],
        pending_bank_transfers: [
            { transaction_id: 88, reference: 'REF-CMD-12-004', montant: 2500000, client: { id: 3, name: 'Bâtir Plus SARL', phone: null }, order_ids: [12, 13], created_at: '2026-09-26T08:00:00+00:00' },
        ],
        settings: { interval_hours: 24, max_reminders: 5 },
        stats: { unpaid_count: 1, unpaid_amount: 3500, restricted_count: 1 },
        ...overrides,
    };
}

describe('CollectionsSection', () => {
    beforeEach(() => routerPost.mockReset());

    it('affiche la course impayée avec ses relances et le compte restreint', () => {
        render(<CollectionsSection collectionsOverview={overview()} />);

        expect(screen.getByText('#42')).toBeInTheDocument();
        expect(screen.getByText('5/5')).toBeInTheDocument();
        expect(screen.getByText('Restreint')).toBeInTheDocument();
        expect(screen.getByText('Course de la commande #42 impayée après 5 relances.')).toBeInTheDocument();
    });

    it('relance un client après confirmation', async () => {
        render(<CollectionsSection collectionsOverview={overview()} />);

        fireEvent.click(screen.getByRole('button', { name: 'Relancer maintenant' }));
        const dialog = await screen.findByRole('dialog');
        fireEvent.click(within(dialog).getByRole('button', { name: 'Relancer' }));

        await waitFor(() => expect(routerPost).toHaveBeenCalledWith('/admin/collections/orders/42/remind', {}, { preserveScroll: true }));
    });

    it('exige un motif pour lever une restriction', async () => {
        render(<CollectionsSection collectionsOverview={overview()} />);

        fireEvent.click(screen.getByRole('button', { name: 'Lever la restriction' }));
        const dialog = await screen.findByRole('dialog');
        const confirmButton = within(dialog).getByRole('button', { name: 'Lever la restriction' });
        expect(confirmButton).toBeDisabled();

        fireEvent.change(within(dialog).getByRole('textbox'), { target: { value: 'Règlement constaté en agence' } });
        fireEvent.click(confirmButton);

        await waitFor(() =>
            expect(routerPost).toHaveBeenCalledWith('/admin/collections/users/7/lift-restriction', { reason: 'Règlement constaté en agence' }, { preserveScroll: true }),
        );
    });

    it('confirme un virement de commande sur référence', async () => {
        render(<CollectionsSection collectionsOverview={overview()} />);

        fireEvent.click(screen.getByRole('button', { name: 'Virement reçu' }));
        const dialog = await screen.findByRole('dialog');
        fireEvent.change(within(dialog).getByRole('textbox'), { target: { value: 'VIR-SGCI-2291' } });
        fireEvent.click(within(dialog).getByRole('button', { name: 'Confirmer la réception' }));

        await waitFor(() =>
            expect(routerPost).toHaveBeenCalledWith(
                '/admin/collections/transactions/88/confirm-bank-transfer',
                { bank_reference: 'VIR-SGCI-2291' },
                { preserveScroll: true },
            ),
        );
    });

    it('annonce explicitement les listes vides', () => {
        render(
            <CollectionsSection
                collectionsOverview={overview({ unpaid: [], restricted: [], pending_bank_transfers: [], stats: { unpaid_count: 0, unpaid_amount: 0, restricted_count: 0 } })}
            />,
        );

        expect(screen.getByText('Aucune course impayée')).toBeInTheDocument();
        expect(screen.getByText('Aucun compte restreint')).toBeInTheDocument();
        expect(screen.getByText('Aucun virement en attente')).toBeInTheDocument();
    });
});
