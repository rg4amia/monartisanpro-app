import { fireEvent, render, screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const routerPut = vi.fn();
const routerPost = vi.fn();
const routerDelete = vi.fn();
const routerGet = vi.fn();

vi.mock('@inertiajs/react', () => ({
    router: {
        put: (...args: unknown[]) => routerPut(...args),
        post: (...args: unknown[]) => routerPost(...args),
        delete: (...args: unknown[]) => routerDelete(...args),
        get: (...args: unknown[]) => routerGet(...args),
    },
    Link: ({ children }: { children: React.ReactNode }) => <a>{children}</a>,
}));

import type { NotificationCampaignItem, NotificationCampaignOptions, Paginated } from '../shared';
import { NotificationCampaignsPanel } from './NotificationCampaignsPanel';

const options: NotificationCampaignOptions = {
    roles: { client: 'Clients', artisan: 'Artisans', fournisseur: 'Fournisseurs', livreur: 'Livreurs', referent: 'Référents' },
    kyc_statuses: { actif: 'KYC validé', en_attente: 'KYC en attente', rejete: 'KYC rejeté' },
    natures: { service: 'Information de service', promotionnel: 'Promotionnel' },
    screens: { notifications: 'Liste des notifications', home: 'Accueil', communication: 'Communication publiée (accueil)' },
    statuses: { brouillon: 'Brouillon', programmee: 'Programmée', en_cours: "En cours d'envoi", envoyee: 'Envoyée', annulee: 'Annulée' },
    communes: [{ id: 1, name: 'Cocody' }],
    communications: [],
    max_recipients: 20000,
    max_selected_users: 500,
    sms_max_segments: 3,
};

function makeCampaign(overrides: Partial<NotificationCampaignItem> = {}): NotificationCampaignItem {
    return {
        id: 7,
        name: 'Maintenance du 5 octobre',
        nature: 'service',
        nature_label: 'Information de service',
        push_title: 'Maintenance programmée',
        push_body: "L'application sera indisponible dimanche.",
        sms_body: 'ProsArtisan : maintenance dimanche.',
        channels: { in_app: true, push: true, sms: true },
        target: { roles: ['artisan'], commune_ids: [], kyc_statuses: [], user_ids: [] },
        selected_users: [],
        open_screen: 'notifications',
        communication: null,
        status: 'brouillon',
        status_label: 'Brouillon',
        scheduled_at: null,
        started_at: null,
        finished_at: null,
        cancelled_at: null,
        counts: { recipients: 0, served: 0, sent: 0, failed: 0 },
        estimate: { recipients: 1250, max_recipients: 20000, over_limit: false, sms_recipients: 1200, sms_segments: 1, sms_messages: 1200 },
        editable: true,
        cancellable: false,
        deletable: true,
        created_by: 'Admin',
        created_at: '2026-09-29T10:00:00+00:00',
        ...overrides,
    };
}

const page = (data: NotificationCampaignItem[]): Paginated<NotificationCampaignItem> =>
    ({ data, links: [], current_page: 1, last_page: 1, total: data.length, per_page: 20 }) as unknown as Paginated<NotificationCampaignItem>;

describe('NotificationCampaignsPanel', () => {
    beforeEach(() => {
        routerPut.mockReset();
        routerPost.mockReset();
        routerDelete.mockReset();
        localStorage.clear();
    });

    it('affiche les destinataires et SMS prévus, jamais un coût', () => {
        render(<NotificationCampaignsPanel campaigns={page([makeCampaign()])} options={options} />);

        const row = screen.getByText('Maintenance du 5 octobre').closest('tr')!;
        expect(within(row).getByText(/1\s?250 prévu\(s\) · 1\s?200 SMS/)).toBeInTheDocument();
        expect(screen.queryByText(/FCFA/)).not.toBeInTheDocument();
    });

    it("confirme l'envoi en affichant les nombres calculés par le serveur", () => {
        render(<NotificationCampaignsPanel campaigns={page([makeCampaign()])} options={options} />);

        fireEvent.click(screen.getByRole('button', { name: 'Envoyer Maintenance du 5 octobre' }));
        const dialog = screen.getByRole('dialog');
        expect(within(dialog).getByTestId('schedule-estimate')).toHaveTextContent(/1\s?250 destinataire/);
        expect(within(dialog).getByTestId('schedule-estimate')).toHaveTextContent(/1\s?200 SMS/);

        fireEvent.click(within(dialog).getByRole('button', { name: 'Envoyer' }));
        expect(routerPost).toHaveBeenCalledWith('/admin/campagnes-notifications/7/programmer', { scheduled_at: null }, expect.anything());
    });

    it('annule une campagne en cours après confirmation', async () => {
        render(
            <NotificationCampaignsPanel
                campaigns={page([makeCampaign({ status: 'en_cours', status_label: "En cours d'envoi", editable: false, cancellable: true, deletable: false, estimate: null, counts: { recipients: 10, served: 4, sent: 4, failed: 0 } })])}
                options={options}
            />,
        );

        expect(screen.queryByRole('button', { name: 'Modifier Maintenance du 5 octobre' })).not.toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'Annuler Maintenance du 5 octobre' }));
        const dialog = await screen.findByRole('dialog');
        expect(dialog).toHaveTextContent('4 destinataire(s) déjà servi(s)');
        fireEvent.click(within(dialog).getByRole('button', { name: 'Annuler la campagne' }));
        await vi.waitFor(() => expect(routerPost).toHaveBeenCalledWith('/admin/campagnes-notifications/7/annuler', {}, expect.anything()));
    });

    it('une campagne envoyée ne propose que la duplication', () => {
        render(
            <NotificationCampaignsPanel
                campaigns={page([makeCampaign({ status: 'envoyee', status_label: 'Envoyée', editable: false, deletable: false, estimate: null })])}
                options={options}
            />,
        );

        expect(screen.getByRole('button', { name: 'Dupliquer Maintenance du 5 octobre' })).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Supprimer Maintenance du 5 octobre' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Envoyer Maintenance du 5 octobre' })).not.toBeInTheDocument();
    });

    it("retire le SMS d'une campagne promotionnelle et crée un brouillon", () => {
        render(<NotificationCampaignsPanel campaigns={page([])} options={options} />);

        fireEvent.click(screen.getByRole('button', { name: 'Nouvelle campagne' }));
        const dialog = screen.getByRole('dialog');
        expect(within(dialog).getByLabelText('SMS')).toBeInTheDocument();

        fireEvent.click(within(dialog).getByLabelText('Promotionnel'));
        expect(within(dialog).queryByLabelText('SMS')).not.toBeInTheDocument();

        fireEvent.change(within(dialog).getByLabelText('Nom de la campagne (interne)'), { target: { value: 'Offre de rentrée' } });
        fireEvent.change(within(dialog).getByLabelText('Titre (application et push)'), { target: { value: 'Nouveautés' } });
        fireEvent.change(within(dialog).getByLabelText('Texte (application et push)'), { target: { value: 'Découvrez les artisans près de chez vous.' } });
        fireEvent.click(within(dialog).getByLabelText('Clients'));
        fireEvent.click(within(dialog).getByRole('button', { name: 'Enregistrer le brouillon' }));

        expect(routerPost).toHaveBeenCalledWith(
            '/admin/campagnes-notifications',
            expect.objectContaining({ nature: 'promotionnel', channel_sms: false, sms_body: null, target: expect.objectContaining({ roles: ['client'] }) }),
            expect.anything(),
        );
    });

    it('refuse une variable dans le texte avant même l’envoi au serveur', () => {
        render(<NotificationCampaignsPanel campaigns={page([makeCampaign()])} options={options} />);

        fireEvent.click(screen.getByRole('button', { name: 'Modifier Maintenance du 5 octobre' }));
        const dialog = screen.getByRole('dialog');
        fireEvent.change(within(dialog).getByLabelText('Texte (application et push)'), { target: { value: 'Bonjour {nom}' } });

        expect(within(dialog).getByText(/n'acceptent pas de variable/)).toBeInTheDocument();
        expect(within(dialog).getByRole('button', { name: 'Enregistrer le brouillon' })).toBeDisabled();
    });

    it('compte les SMS par destinataire', () => {
        render(<NotificationCampaignsPanel campaigns={page([makeCampaign()])} options={options} />);

        fireEvent.click(screen.getByRole('button', { name: 'Modifier Maintenance du 5 octobre' }));
        const dialog = screen.getByRole('dialog');
        fireEvent.change(within(dialog).getByLabelText('Texte du SMS'), { target: { value: 'a'.repeat(200) } });

        expect(within(dialog).getByTestId('campaign-sms-counter')).toHaveTextContent('200 caractères · GSM · 2 SMS par destinataire');
    });
});
