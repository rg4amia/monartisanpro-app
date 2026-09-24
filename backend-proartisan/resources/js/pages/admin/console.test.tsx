import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

// Rendu de bout en bout de la console : vérifie que la logique extraite en
// hooks (navigation, indicateurs d'en-tête, formulaires, décisions KYC) est
// correctement branchée, ce que les tests unitaires des panneaux ne couvrent pas.

const routerPost = vi.fn();
let pageProps: Record<string, unknown> = {};

function makeForm<T extends Record<string, unknown>>(initial: T) {
    const form = {
        data: { ...initial },
        errors: {} as Record<string, string>,
        processing: false,
        setData: vi.fn((key: string | T, value?: unknown) => {
            form.data = typeof key === 'string' ? { ...form.data, [key]: value } : { ...key };
        }),
        reset: vi.fn(),
        clearErrors: vi.fn(),
        transform: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    };
    return form;
}

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: pageProps }),
    useForm: (initial: Record<string, unknown>) => makeForm(initial),
    router: {
        post: (...args: unknown[]) => routerPost(...args),
        get: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
        visit: vi.fn(),
        on: () => () => undefined,
    },
    Link: ({ children, href }: { children?: React.ReactNode; href: string }) => <a href={href}>{children}</a>,
    Head: () => null,
}));

// Panneaux lourds sans rapport avec le branchement testé.
vi.mock('./ai-dashboard-panel', () => ({ default: () => <div>panneau-ia</div> }));
vi.mock('./llm-admin-panel', () => ({ default: () => <div>panneau-llm</div> }));
vi.mock('./roles-permissions-panel', () => ({ default: () => <div>panneau-roles</div> }));
vi.mock('./vitrine-panel', () => ({ default: () => <div>panneau-vitrine</div> }));
vi.mock('./panels/CartographyPanel', () => ({ CartographyPanel: () => <div>panneau-cartographie</div> }));
vi.mock('./panels/DashboardPanel', () => ({ DashboardPanel: () => <div>panneau-tableau-de-bord</div> }));

import AdminConsole from './console';

const dashboard = {
    users_total: 42,
    artisans_actifs: 10,
    clients_actifs: 25,
    fournisseurs_agrees: 3,
    missions_en_cours: 7,
    missions_en_litige: 1,
    litiges_ouverts: 2,
    kyc_en_attente: 4,
    referent_required_open: 0,
    recent_fraud_alerts: 0,
    volume_transactions_24h: 150000,
};

function setProps(overrides: Record<string, unknown> = {}) {
    pageProps = { auth: { user: { name: 'Admin Test', email: 'admin@prosartisan.ci' }, permissions: ['*'] }, dashboard, ...overrides };
}

describe('AdminConsole', () => {
    beforeEach(() => {
        routerPost.mockClear();
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => ({}) }));
        setProps();
    });

    it('affiche la navigation autorisée et les indicateurs du tableau de bord', () => {
        render(<AdminConsole initialTab="dashboard" />);

        expect(screen.getByText('panneau-tableau-de-bord')).toBeInTheDocument();
        expect(screen.getAllByText('Missions en cours').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Codes Promo').length).toBeGreaterThan(0);
        // Indicateur d'en-tête « Utilisateurs » calculé par buildHeroStats.
        expect(screen.getAllByText('42').length).toBeGreaterThan(0);
    });

    it('masque les onglets que les capacités de l’administrateur n’autorisent pas', () => {
        setProps({ auth: { user: { name: 'Admin Restreint' }, permissions: ['admin.kyc.view'] } });
        render(<AdminConsole initialTab="kyc" />);

        expect(screen.queryAllByText('Codes Promo')).toHaveLength(0);
    });

    it('ouvre le formulaire de création de code promo', () => {
        setProps({ promoCodes: [] });
        render(<AdminConsole initialTab="promo_codes" />);

        expect(screen.queryByText('Créer un nouveau code promo')).not.toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: /Nouveau Code Promo/ }));

        expect(screen.getByText('Créer un nouveau code promo')).toBeInTheDocument();
        fireEvent.click(screen.getByTitle('Fermer'));
        expect(screen.queryByText('Créer un nouveau code promo')).not.toBeInTheDocument();
    });

    it('rejette un dossier KYC via la modale, motif obligatoire', async () => {
        const user = {
            id: 9,
            name: 'Awa Traoré',
            phone: '+2250700000009',
            role: 'artisan',
            created_at: '2026-02-01T09:00:00Z',
            kyc_documents: [],
        };
        setProps({
            kycUsersPage: { data: [user], links: [], current_page: 1, last_page: 1, total: 1, per_page: 15, from: 1, to: 1 },
        });
        render(<AdminConsole initialTab="kyc" />);

        fireEvent.click(screen.getByRole('button', { name: 'Rejeter' }));
        const dialog = await screen.findByRole('dialog');
        const confirmButton = within(dialog).getByRole('button', { name: 'Rejeter' });
        expect(confirmButton).toBeDisabled();

        fireEvent.change(within(dialog).getByRole('textbox'), { target: { value: 'Pièce d’identité illisible' } });
        fireEvent.click(confirmButton);

        await waitFor(() =>
            expect(routerPost).toHaveBeenCalledWith(
                '/admin/kyc/9/review',
                { decision: 'rejete', rejection_reason: 'Pièce d’identité illisible' },
                expect.anything(),
            ),
        );
    });
});
