import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const routerPost = vi.fn();
const routerPut = vi.fn();
vi.mock('@inertiajs/react', () => ({
    router: {
        post: (...args: unknown[]) => routerPost(...args),
        put: (...args: unknown[]) => routerPut(...args),
    },
}));

import { CashoutQuincaillerieSection } from './CashoutQuincaillerieSection';

function makeFinancialKpis(overrides: Record<string, unknown> = {}) {
    return {
        solde_general: {
            total_entrees: 10000000,
            total_sorties: 6000000,
            sequestre_bloque_litiges: 500000,
            missions_bloquees_litiges_count: 2,
        },
        cashout_kpis: { total_commissions_quincailleries: 75000, commission_rate_percent: 2.5 },
        cashouts_by_supplier: [
            {
                supplier_id: 1,
                shop_name: 'Quincaillerie Koffi',
                supplier_phone: '+2250700000001',
                total_operations: 5,
                volume_brut_retraits: 500000,
                total_commissions_gagnees: 12500,
            },
        ],
        recent_cashouts: [
            {
                id: 9,
                reference: 'CO-0009',
                supplier_id: 1,
                supplier_name: 'Quincaillerie Koffi',
                supplier_phone: '+2250700000001',
                beneficiary_name: 'Kouassi Michel',
                beneficiary_phone: '+2250700000009',
                montant_brut: 50000,
                commission_rate: 2.5,
                montant_commission: 1250,
                montant_net: 48750,
                statut: 'en_attente',
                mode_retrait: 'especes_guichet',
                created_at: '2026-02-01T09:00:00Z',
            },
        ],
        ...overrides,
    };
}

function renderSection(overrides: Partial<React.ComponentProps<typeof CashoutQuincaillerieSection>> = {}) {
    const props: React.ComponentProps<typeof CashoutQuincaillerieSection> = {
        financialKpis: makeFinancialKpis(),
        settingsList: [{ id: 5, key: 'commission_cashout_quincaillerie', value: '0.025' }],
        suppliers: [{ id: 1, name: 'Quincaillerie Koffi', phone: '+2250700000001' }],
        ...overrides,
    };
    render(<CashoutQuincaillerieSection {...props} />);
    return props;
}

describe('CashoutQuincaillerieSection', () => {
    beforeEach(() => {
        routerPost.mockClear();
        routerPut.mockClear();
    });

    it('affiche la trésorerie globale et les commissions cash-out', () => {
        renderSection();

        expect(screen.getByText(/10\s?000\s?000/)).toBeInTheDocument();
        expect(screen.getByText(/6\s?000\s?000/)).toBeInTheDocument();
        expect(screen.getByText(/75\s?000/)).toBeInTheDocument();
    });

    it('affiche la répartition par quincaillerie et le journal des retraits', () => {
        renderSection();

        expect(screen.getAllByText('Quincaillerie Koffi').length).toBeGreaterThan(0);
        expect(screen.getByText('CO-0009')).toBeInTheDocument();
        expect(screen.getByText('Kouassi Michel')).toBeInTheDocument();
        expect(screen.getByText('En attente')).toBeInTheDocument();
    });

    it('affiche des états vides sans historique', () => {
        renderSection({
            financialKpis: makeFinancialKpis({ cashouts_by_supplier: [], recent_cashouts: [] }),
        });

        expect(screen.getByText('Aucun historique quincaillerie')).toBeInTheDocument();
        expect(screen.getByText('Historique vide')).toBeInTheDocument();
    });

    it('met à jour le taux de commission valide', () => {
        renderSection();

        const rateInput = screen.getByPlaceholderText('Ex: 2.5');
        fireEvent.change(rateInput, { target: { value: '3' } });
        fireEvent.click(screen.getByRole('button', { name: 'Appliquer' }));

        expect(routerPut).toHaveBeenCalledWith(
            '/admin/settings/5',
            { value: '0.0300' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('refuse un taux de commission invalide sans appeler le backend', () => {
        renderSection();

        const rateInput = screen.getByPlaceholderText('Ex: 2.5');
        fireEvent.change(rateInput, { target: { value: '150' } });
        // `fireEvent.submit` sur le formulaire (plutôt qu'un clic sur le bouton
        // submit) contourne la validation HTML5 native de l'attribut `max="50"`
        // du champ, qui bloquerait sinon silencieusement l'événement `submit`
        // avant même que la validation applicative (> 100) ne s'exécute.
        fireEvent.submit(rateInput.closest('form')!);

        expect(routerPut).not.toHaveBeenCalled();
        expect(screen.getByRole('alert')).toHaveTextContent('Veuillez renseigner un pourcentage valide');
    });

    it('recalcule la commission et le net décaissé du simulateur', () => {
        renderSection();

        fireEvent.change(screen.getByRole('slider'), { target: { value: '100000' } });

        // "Net Décaissé" apparaît aussi comme en-tête de colonne du journal :
        // on cible spécifiquement le libellé du simulateur (un <p>).
        const netLabel = screen
            .getAllByText('Net Décaissé')
            .find((el) => el.tagName === 'P');
        const commissionValue = screen.getByText('Commission Quincaillerie').nextElementSibling;
        expect(commissionValue).toHaveTextContent(/^2\s500\sFCFA$/);
        expect(netLabel?.nextElementSibling).toHaveTextContent(/^97\s500\sFCFA$/);
    });

    it('approuve une demande de cash-out en attente', () => {
        renderSection();

        fireEvent.click(screen.getByRole('button', { name: 'Approuver' }));

        expect(routerPost).toHaveBeenCalledWith(
            '/admin/cashouts/9/approve',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('rejette une demande après motif saisi', async () => {
        renderSection();

        fireEvent.click(screen.getByRole('button', { name: 'Rejeter' }));
        const dialog = await screen.findByRole('dialog');
        fireEvent.change(within(dialog).getByRole('textbox'), {
            target: { value: 'Justificatif manquant' },
        });
        fireEvent.click(within(dialog).getByRole('button', { name: 'Rejeter' }));

        await waitFor(() =>
            expect(routerPost).toHaveBeenCalledWith(
                '/admin/cashouts/9/reject',
                { reason: 'Justificatif manquant' },
                expect.objectContaining({ preserveScroll: true }),
            ),
        );
    });

    it('propose "Décaisser" pour une demande déjà approuvée', () => {
        renderSection({
            financialKpis: makeFinancialKpis({
                recent_cashouts: [
                    {
                        id: 10,
                        reference: 'CO-0010',
                        supplier_id: 1,
                        supplier_name: 'Quincaillerie Koffi',
                        supplier_phone: '+2250700000001',
                        beneficiary_name: 'Kouassi Michel',
                        beneficiary_phone: '+2250700000009',
                        montant_brut: 50000,
                        commission_rate: 2.5,
                        montant_commission: 1250,
                        montant_net: 48750,
                        statut: 'approuve',
                        mode_retrait: 'especes_guichet',
                    },
                ],
            }),
        });

        fireEvent.click(screen.getByRole('button', { name: 'Décaisser' }));

        expect(routerPost).toHaveBeenCalledWith(
            '/admin/cashouts/10/complete',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('ouvre le formulaire de nouveau retrait et refuse une soumission incomplète', () => {
        renderSection();

        fireEvent.click(screen.getByRole('button', { name: '+ Enregistrer un Retrait Cash' }));
        expect(screen.getByText('Enregistrer un Retrait Cash')).toBeInTheDocument();

        // `fireEvent.submit` sur le formulaire contourne la validation HTML5
        // native des champs `required`, qui bloquerait sinon l'événement
        // `submit` avant que la validation applicative ne s'exécute.
        fireEvent.submit(screen.getByRole('button', { name: 'Enregistrer' }).closest('form')!);

        expect(routerPost).not.toHaveBeenCalled();
        expect(screen.getByRole('alert')).toHaveTextContent('Veuillez remplir tous les champs obligatoires.');
    });

    it('crée un nouveau retrait cash avec un formulaire complet', () => {
        renderSection();

        fireEvent.click(screen.getByRole('button', { name: '+ Enregistrer un Retrait Cash' }));
        fireEvent.change(screen.getByDisplayValue('Sélectionner une quincaillerie...'), {
            target: { value: '1' },
        });
        fireEvent.change(screen.getByPlaceholderText('Ex: Kouassi Michel'), {
            target: { value: 'Awa Traoré' },
        });
        fireEvent.change(screen.getByPlaceholderText('0701020304'), {
            target: { value: '0700000009' },
        });
        fireEvent.change(screen.getByPlaceholderText('Ex: 50000'), {
            target: { value: '20000' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

        expect(routerPost).toHaveBeenCalledWith(
            '/admin/cashouts',
            expect.objectContaining({
                beneficiary_name: 'Awa Traoré',
                beneficiary_phone: '0700000009',
                montant_brut: 20000,
            }),
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});
