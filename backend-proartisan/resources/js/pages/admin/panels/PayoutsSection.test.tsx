import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const routerPost = vi.fn();
vi.mock('@inertiajs/react', () => ({
    router: {
        post: (...args: unknown[]) => routerPost(...args),
    },
}));

import { PayoutsSection } from './PayoutsSection';
import type { DriverCashoutItem, DriverCashoutsOverview, PayoutItem, PayoutsOverview } from './PayoutsSection';

function makePayout(overrides: Partial<PayoutItem> = {}): PayoutItem {
    return {
        id: 7,
        reference: 'VRS-20260926-A1B2C3',
        context: 'jalon',
        context_label: "Paiement d'étape de chantier",
        montant: 9000,
        montant_transfere: 9000,
        provider: 'wave',
        phone: '+2250701020304',
        statut: 'echoue',
        statut_label: 'Virement échoué',
        attempts: 2,
        last_error: 'Numéro Wave inconnu',
        next_retry_at: '2026-09-26T12:30:00Z',
        paid_at: null,
        external_reference: null,
        mission_id: 42,
        description: 'Paiement jalon #1 mission #42',
        beneficiary: { id: 3, name: 'Yao Artisan', phone: '+2250701020304', role: 'artisan' },
        can_retry: true,
        created_at: '2026-09-26T10:00:00Z',
        events: [
            { id: 1, action: 'tentative', action_label: 'Tentative de virement', statut_avant: 'en_cours', statut_apres: 'en_cours', message: null, actor: null, created_at: '2026-09-26T10:00:00Z' },
            { id: 2, action: 'echec', action_label: 'Virement échoué', statut_avant: 'en_cours', statut_apres: 'echoue', message: 'Numéro Wave inconnu', actor: null, created_at: '2026-09-26T10:00:01Z' },
        ],
        ...overrides,
    };
}

function makeCashout(overrides: Partial<DriverCashoutItem> = {}): DriverCashoutItem {
    return {
        id: 11,
        reference: 'RTL-20260926-9F0A',
        montant_brut: 15000,
        montant_commission: 0,
        montant_net: 15000,
        statut: 'en_attente',
        statut_label: 'En attente',
        mode_retrait: 'wave',
        beneficiary_name: 'Konan Livreur',
        beneficiary_phone: '+2250700112233',
        notes: null,
        payout: null,
        created_at: '2026-09-26T09:00:00Z',
        driver: { id: 5, name: 'Konan Livreur', phone: '+2250700112233' },
        available_balance: 20000,
        ...overrides,
    };
}

function renderSection(payouts: Partial<PayoutsOverview> = {}, cashouts: Partial<DriverCashoutsOverview> = {}) {
    return render(
        <PayoutsSection
            payoutsOverview={{
                pending: [makePayout()],
                recent: [],
                stats: { failed_count: 1, failed_amount: 9000, exhausted_count: 0, paid_today: 0 },
                ...payouts,
            }}
            driverCashoutsOverview={{ pending: [makeCashout()], recent: [], commission_rate: 0, ...cashouts }}
        />,
    );
}

describe('PayoutsSection', () => {
    beforeEach(() => routerPost.mockReset());

    it('affiche le virement échoué avec son motif et sa prochaine relance', () => {
        renderSection();

        expect(screen.getByText('VRS-20260926-A1B2C3')).toBeInTheDocument();
        expect(screen.getAllByText('Numéro Wave inconnu').length).toBeGreaterThan(0);
        expect(screen.getByText(/^Relance \d/)).toBeInTheDocument();
    });

    it("déplie l'historique des actions du versement", () => {
        renderSection();

        fireEvent.click(screen.getByRole('button', { name: 'Historique' }));

        const history = screen.getByRole('list', { name: 'Historique du versement' });
        expect(within(history).getByText('Tentative de virement')).toBeInTheDocument();
        expect(within(history).getByText('Virement échoué')).toBeInTheDocument();
    });

    it('relance un virement après confirmation', async () => {
        renderSection();

        fireEvent.click(screen.getByRole('button', { name: 'Relancer' }));
        const dialog = await screen.findByRole('dialog');
        fireEvent.click(within(dialog).getByRole('button', { name: 'Relancer' }));

        await waitFor(() => expect(routerPost).toHaveBeenCalledWith('/admin/payouts/7/retry', {}, { preserveScroll: true }));
    });

    it('exige une référence pour solder un versement hors plateforme', async () => {
        renderSection();

        fireEvent.click(screen.getByRole('button', { name: 'Versé hors plateforme' }));
        const dialog = await screen.findByRole('dialog');
        const confirmButton = within(dialog).getByRole('button', { name: 'Solder le versement' });
        expect(confirmButton).toBeDisabled();

        fireEvent.change(within(dialog).getByRole('textbox'), { target: { value: 'VIR-BICICI-778' } });
        fireEvent.click(confirmButton);

        await waitFor(() =>
            expect(routerPost).toHaveBeenCalledWith('/admin/payouts/7/mark-paid', { external_reference: 'VIR-BICICI-778' }, { preserveScroll: true }),
        );
    });

    it('approuve, verse et rejette une demande de retrait livreur', async () => {
        renderSection();

        fireEvent.click(screen.getByRole('button', { name: 'Approuver' }));
        expect(routerPost).toHaveBeenCalledWith('/admin/driver-cashouts/11/approve', {}, { preserveScroll: true });

        fireEvent.click(screen.getByRole('button', { name: 'Verser' }));
        let dialog = await screen.findByRole('dialog');
        fireEvent.click(within(dialog).getByRole('button', { name: 'Verser maintenant' }));
        await waitFor(() => expect(routerPost).toHaveBeenCalledWith('/admin/driver-cashouts/11/pay', {}, { preserveScroll: true }));

        fireEvent.click(screen.getByRole('button', { name: 'Rejeter' }));
        dialog = await screen.findByRole('dialog');
        fireEvent.change(within(dialog).getByRole('textbox'), { target: { value: 'Numéro invalide' } });
        fireEvent.click(within(dialog).getByRole('button', { name: 'Rejeter' }));
        await waitFor(() =>
            expect(routerPost).toHaveBeenCalledWith('/admin/driver-cashouts/11/reject', { reason: 'Numéro invalide' }, { preserveScroll: true }),
        );
    });

    it('demande la référence bancaire pour un retrait par virement', async () => {
        renderSection({}, { pending: [makeCashout({ mode_retrait: 'virement_bancaire', statut: 'approuve', statut_label: 'Approuvé' })] });

        fireEvent.click(screen.getByRole('button', { name: 'Verser' }));
        const dialog = await screen.findByRole('dialog');
        fireEvent.change(within(dialog).getByRole('textbox'), { target: { value: 'VIR-SGBCI-42' } });
        fireEvent.click(within(dialog).getByRole('button', { name: 'Confirmer le virement' }));

        await waitFor(() =>
            expect(routerPost).toHaveBeenCalledWith('/admin/driver-cashouts/11/pay', { external_reference: 'VIR-SGBCI-42' }, { preserveScroll: true }),
        );
    });

    it('annonce explicitement les listes vides', () => {
        renderSection({ pending: [], stats: { failed_count: 0, failed_amount: 0, exhausted_count: 0, paid_today: 0 } }, { pending: [] });

        expect(screen.getByText('Aucun virement en attente')).toBeInTheDocument();
        expect(screen.getByText('Aucune demande de retrait en attente')).toBeInTheDocument();
    });
});
