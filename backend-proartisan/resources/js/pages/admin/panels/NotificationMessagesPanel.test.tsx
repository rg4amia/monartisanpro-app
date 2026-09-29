import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
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

import type { NotificationDeliveryRow, NotificationEventItem, Paginated } from '../shared';
import { NotificationMessagesPanel } from './NotificationMessagesPanel';

const channels = (inApp: boolean, push: boolean, sms: boolean) => ({ in_app: inApp, push, sms });

function makeEvent(overrides: Partial<NotificationEventItem> = {}): NotificationEventItem {
    return {
        key: 'jalon.paye.artisan',
        label: "Étape validée : paiement de l'artisan",
        domain: 'etapes',
        audience: 'artisan',
        variables: [{ name: 'etape', description: "Numéro de l'étape", example: '2', required: false }],
        defaults: { title: 'Paiement reçu !', body: 'Le jalon #{etape} a été validé. Paiement en cours.', sms_body: null, channels: channels(true, true, true) },
        current: { title: 'Paiement reçu !', body: 'Le jalon #{etape} a été validé. Paiement en cours.', sms_body: null, channels: channels(true, true, true) },
        locked: {},
        security: false,
        overridden: false,
        updated_at: null,
        updated_by: null,
        ...overrides,
    };
}

const otpEvent = makeEvent({
    key: 'auth.otp',
    label: 'Code de vérification (OTP)',
    domain: 'securite',
    audience: 'utilisateur',
    variables: [
        { name: 'code', description: 'Code à usage unique', example: '482915', required: true },
        { name: 'minutes', description: 'Durée de validité (minutes)', example: '5', required: true },
    ],
    defaults: {
        title: 'Code de vérification ProsArtisan',
        body: 'Votre code de vérification ProsArtisan est: {code}.',
        sms_body: 'Votre code est {code}, valide {minutes} minutes.',
        channels: channels(false, false, true),
    },
    current: {
        title: 'Code de vérification ProsArtisan',
        body: 'Votre code de vérification ProsArtisan est: {code}.',
        sms_body: null,
        channels: channels(false, false, true),
    },
    locked: { in_app: false, push: false, sms: true },
    security: true,
});

const modifiedEvent = makeEvent({
    key: 'devis.refuse.artisan',
    label: 'Devis refusé par le client',
    domain: 'missions',
    overridden: true,
    current: { title: 'Votre devis est refusé', body: 'Mission #{mission}', sms_body: null, channels: channels(true, true, false) },
    defaults: { title: 'Devis refusé', body: 'Mission #{mission}', sms_body: null, channels: channels(true, true, false) },
    variables: [{ name: 'mission', description: 'Numéro de la mission', example: '318', required: false }],
});

const deliveries: Paginated<NotificationDeliveryRow> = {
    data: [
        {
            id: 1,
            event: 'jalon.paye.artisan',
            event_label: "Étape validée : paiement de l'artisan",
            channel: 'sms',
            provider: 'orange',
            status: 'echoue',
            status_label: 'Échoué',
            reason: 'Failed to send SMS',
            user: { id: 7, name: 'Koffi Yao', role: 'artisan' },
            created_at: '2026-09-29T08:00:00Z',
        },
    ],
    current_page: 1,
    last_page: 1,
    total: 1,
    per_page: 50,
    from: 1,
    to: 1,
    links: [],
};

function renderPanel(events: NotificationEventItem[] = [makeEvent(), modifiedEvent, otpEvent]) {
    return render(
        <NotificationMessagesPanel
            events={events}
            domains={{ missions: 'Missions et devis', etapes: 'Étapes et paiements de chantier', securite: 'Sécurité et fraude' }}
            audiences={{ artisan: 'Artisan', utilisateur: 'Tout utilisateur' }}
            smsMaxSegments={3}
            deliveries={deliveries}
            deliveryStats={{ sent: 42, failed: 1, skipped: 3, sms_sent: 9 }}
        />,
    );
}

describe('NotificationMessagesPanel', () => {
    beforeEach(() => {
        [routerPut, routerPost, routerDelete, routerGet].forEach((fn) => fn.mockClear());
        window.localStorage.clear();
    });

    it('groupe les messages par domaine et distingue les messages modifiés', () => {
        renderPanel();

        expect(screen.getByRole('heading', { name: 'Étapes et paiements de chantier' })).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Sécurité et fraude' })).toBeInTheDocument();
        expect(screen.getByText('Modifié')).toBeInTheDocument();
        expect(screen.getAllByText("Texte d'origine")).toHaveLength(2);
    });

    it('filtre sur les messages modifiés', () => {
        renderPanel();

        fireEvent.change(screen.getByLabelText('Afficher'), { target: { value: 'modified' } });

        expect(screen.getByText('Devis refusé par le client')).toBeInTheDocument();
        expect(screen.queryByText("Étape validée : paiement de l'artisan")).not.toBeInTheDocument();
    });

    it('enregistre un message modifié', async () => {
        renderPanel();

        fireEvent.click(screen.getByRole('button', { name: "Modifier : Étape validée : paiement de l'artisan" }));
        const dialog = await screen.findByRole('dialog');
        fireEvent.change(within(dialog).getByLabelText('Titre (application et push)'), { target: { value: 'Étape {etape} payée' } });
        fireEvent.click(within(dialog).getByLabelText('SMS'));
        fireEvent.click(within(dialog).getByRole('button', { name: 'Enregistrer' }));

        expect(routerPut).toHaveBeenCalledWith(
            '/admin/messages/jalon.paye.artisan',
            expect.objectContaining({ push_title: 'Étape {etape} payée', sms_body: null, channel_sms: false, channel_push: true }),
            expect.any(Object),
        );
        // Aperçu rendu avec la valeur d'exemple.
        expect(within(dialog).getByText('Étape 2 payée')).toBeInTheDocument();
    });

    it('bloque l\'enregistrement d\'une variable inconnue', async () => {
        renderPanel();

        fireEvent.click(screen.getByRole('button', { name: "Modifier : Étape validée : paiement de l'artisan" }));
        const dialog = await screen.findByRole('dialog');
        fireEvent.change(within(dialog).getByLabelText('Texte (application et push)'), { target: { value: 'Payé à {client}' } });

        expect(within(dialog).getByText(/Variable\(s\) inconnue\(s\) pour ce message : \{client\}/)).toBeInTheDocument();
        expect(within(dialog).getByRole('button', { name: 'Enregistrer' })).toBeDisabled();
    });

    it('verrouille l\'OTP : SMS seul, pas de texte push', async () => {
        renderPanel();

        fireEvent.click(screen.getByRole('button', { name: 'Modifier : Code de vérification (OTP)' }));
        const dialog = await screen.findByRole('dialog');

        expect(within(dialog).queryByLabelText('Titre (application et push)')).not.toBeInTheDocument();
        expect(within(dialog).getByLabelText(/Notification push/)).toBeDisabled();
        expect(within(dialog).getByLabelText(/^SMS/)).toBeDisabled();
        expect(within(dialog).getByText('Votre code est 482915, valide 5 minutes.')).toBeInTheDocument();
        expect(within(dialog).getByText('Alerte de sécurité : le push ou le SMS doit rester actif.')).toBeInTheDocument();
    });

    it('signale un SMS trop long', async () => {
        renderPanel();

        fireEvent.click(screen.getByRole('button', { name: "Modifier : Étape validée : paiement de l'artisan" }));
        const dialog = await screen.findByRole('dialog');
        fireEvent.change(within(dialog).getByLabelText('Texte du SMS'), { target: { value: 'a'.repeat(470) } });

        expect(within(dialog).getByTestId('sms-counter')).toHaveTextContent('470 caractères · GSM · 4 SMS — maximum 3');
    });

    it('envoie un test et revient au texte d\'origine après confirmation', async () => {
        renderPanel();

        fireEvent.click(screen.getByRole('button', { name: 'Modifier : Devis refusé par le client' }));
        const dialog = await screen.findByRole('dialog');
        fireEvent.click(within(dialog).getByRole('button', { name: 'Envoyer un test' }));
        expect(routerPost).toHaveBeenCalledWith('/admin/messages/devis.refuse.artisan/test', { channels: ['push'] }, expect.any(Object));

        fireEvent.click(within(dialog).getByRole('button', { name: "Revenir au texte d'origine" }));
        fireEvent.click(await screen.findByRole('button', { name: "Rétablir l'origine" }));

        await waitFor(() => expect(routerDelete).toHaveBeenCalledWith('/admin/messages/devis.refuse.artisan', expect.any(Object)));
    });

    it('affiche le journal des envois et ses totaux', () => {
        renderPanel();

        fireEvent.click(screen.getByRole('tab', { name: 'Journal des envois' }));

        expect(screen.getByText('Koffi Yao')).toBeInTheDocument();
        expect(within(screen.getByRole('table')).getByText('Échoué')).toBeInTheDocument();
        expect(screen.getByText('Failed to send SMS')).toBeInTheDocument();
        expect(screen.getByText('42')).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Statut'), { target: { value: 'echoue' } });
        expect(routerGet).toHaveBeenCalledWith('/admin/messages', expect.objectContaining({ delivery_status: 'echoue' }), expect.objectContaining({ only: ['notificationDeliveries'] }));
    });
});
