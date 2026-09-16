import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { NotificationsPanel } from './NotificationsPanel';
import type { AdminNotificationItem } from '../shared';

function makeNotif(overrides: Partial<AdminNotificationItem> = {}): AdminNotificationItem {
    return {
        id: 1,
        title: 'Nouveau litige déclaré',
        body: 'Mission #12 : le client conteste la livraison.',
        created_at: '2026-02-01T09:00:00Z',
        read_at: null,
        action_url: '/admin/litiges/12',
        action_label: 'Instruire',
        ...overrides,
    } as AdminNotificationItem;
}

function renderPanel(overrides: Partial<React.ComponentProps<typeof NotificationsPanel>> = {}) {
    const props: React.ComponentProps<typeof NotificationsPanel> = {
        notifTab: 'alerts',
        onNotifTabChange: vi.fn(),
        liveNotificationsCount: 1,
        unreadNotifsCount: 1,
        notifFilter: 'all',
        onNotifFilterChange: vi.fn(),
        filteredNotifs: [makeNotif()],
        onMarkAllRead: vi.fn(),
        onMarkNotifRead: vi.fn(),
        searchNotif: '',
        onSearchNotifChange: vi.fn(),
        roleNotif: '',
        onRoleNotifChange: vi.fn(),
        typeNotif: '',
        onTypeNotifChange: vi.fn(),
        onFilterSubmit: vi.fn((e: React.FormEvent) => e.preventDefault()),
        onFilterReset: vi.fn(),
        allNotifications: { data: [], links: [] },
        renderPagination: () => null,
        ...overrides,
    };
    render(<NotificationsPanel {...props} />);
    return props;
}

describe('NotificationsPanel', () => {
    it('affiche les alertes admin non lues', () => {
        renderPanel();

        expect(screen.getByText('Nouveau litige déclaré')).toBeInTheDocument();
        expect(screen.getByText(/Mission #12/)).toBeInTheDocument();
    });

    it('affiche un message vide sans notification', () => {
        renderPanel({ filteredNotifs: [] });
        expect(screen.getByText('Aucune notification disponible.')).toBeInTheDocument();
    });

    it('déclenche onMarkAllRead', () => {
        const props = renderPanel();
        fireEvent.click(screen.getByRole('button', { name: /Tout marquer comme lu/ }));
        expect(props.onMarkAllRead).toHaveBeenCalledTimes(1);
    });

    it('déclenche onMarkNotifRead pour une notification actionnable', () => {
        const notif = makeNotif();
        const props = renderPanel({ filteredNotifs: [notif] });

        fireEvent.click(screen.getByRole('button', { name: 'Instruire' }));

        expect(props.onMarkNotifRead).toHaveBeenCalledWith(notif);
    });

    it('ne propose pas d\'action pour une notification sans action_url', () => {
        renderPanel({ filteredNotifs: [makeNotif({ action_url: undefined })] });
        expect(screen.queryByRole('button', { name: 'Instruire' })).not.toBeInTheDocument();
    });

    it('bascule les filtres rapides (toutes / non lues / critiques)', () => {
        const props = renderPanel();
        fireEvent.click(screen.getByRole('button', { name: /Non lues/ }));
        expect(props.onNotifFilterChange).toHaveBeenCalledWith('unread');

        fireEvent.click(screen.getByRole('button', { name: 'Alertes critiques' }));
        expect(props.onNotifFilterChange).toHaveBeenCalledWith('alerts');
    });

    it('bascule vers l\'onglet historique global', () => {
        const props = renderPanel();
        fireEvent.click(screen.getByRole('button', { name: 'Historique Global & Audit' }));
        expect(props.onNotifTabChange).toHaveBeenCalledWith('history');
    });

    it('affiche l\'historique global avec le destinataire et le type', () => {
        renderPanel({
            notifTab: 'history',
            allNotifications: {
                data: [
                    {
                        id: 2,
                        type: 'litige',
                        title: 'Litige ouvert',
                        body: 'Détail du litige',
                        created_at: '2026-02-01T09:00:00Z',
                        user: { name: 'Awa Traoré', phone: '+2250700000001', role: 'client' },
                        data_json: {},
                    },
                ],
                links: [],
            },
        });

        expect(screen.getByText('Awa Traoré')).toBeInTheDocument();
        expect(screen.getByText('litige')).toBeInTheDocument();
    });

    it('affiche un état vide sans historique', () => {
        renderPanel({ notifTab: 'history', allNotifications: { data: [], links: [] } });
        expect(screen.getByText("Aucune notification trouvée dans l'historique global.")).toBeInTheDocument();
    });

    it('déclenche les callbacks de filtre de l\'historique', () => {
        const props = renderPanel({ notifTab: 'history' });

        fireEvent.change(screen.getByPlaceholderText('Message, nom, téléphone...'), {
            target: { value: 'Awa' },
        });
        expect(props.onSearchNotifChange).toHaveBeenCalledWith('Awa');

        fireEvent.click(screen.getByRole('button', { name: 'Réinitialiser' }));
        expect(props.onFilterReset).toHaveBeenCalledTimes(1);
    });
});
