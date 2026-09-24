import { fireEvent, render, screen } from '@testing-library/react';
import { useState } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const formPost = vi.fn();
let mockErrors: Record<string, string> = {};

vi.mock('@inertiajs/react', () => ({
    useForm: (initial: Record<string, unknown>) => {
        const [data, setDataState] = useState(initial);
        return {
            data,
            setData: (key: string, value: unknown) =>
                setDataState((current) => ({ ...current, [key]: value })),
            post: (url: string, opts?: { onSuccess?: () => void; onFinish?: () => void }) => {
                formPost(url, data);
                opts?.onSuccess?.();
                opts?.onFinish?.();
            },
            processing: false,
            errors: mockErrors,
            recentlySuccessful: false,
        };
    },
}));

import type { Paginated, WhatsappClickLogItem, WhatsappClickStats, WhatsappSettings } from '../shared';
import { WhatsAppPanel } from './WhatsAppPanel';

const stats: WhatsappClickStats = { total: 42, today: 3, last_7_days: 15 };
const settings: WhatsappSettings = {
    whatsapp_widget_enabled: '1',
    whatsapp_widget_phone: '+2250160606183',
    whatsapp_widget_message: 'Bonjour, je souhaite un devis.',
};

function makeLog(overrides: Partial<WhatsappClickLogItem> = {}): WhatsappClickLogItem {
    return {
        id: 1,
        page: '/artisans',
        source: 'floating_button',
        referrer: 'https://google.com',
        created_at: '2026-02-01T09:00:00Z',
        ...overrides,
    };
}

function makePage(data: WhatsappClickLogItem[]): Paginated<WhatsappClickLogItem> {
    return {
        data,
        links: [],
        current_page: 1,
        last_page: 1,
        total: data.length,
        per_page: 15,
        from: data.length > 0 ? 1 : null,
        to: data.length > 0 ? data.length : null,
    };
}

function renderPanel(overrides: Partial<React.ComponentProps<typeof WhatsAppPanel>> = {}) {
    const props: React.ComponentProps<typeof WhatsAppPanel> = {
        whatsappClicksPage: makePage([makeLog()]),
        whatsappClickStats: stats,
        whatsappSettings: settings,
        search: '',
        onSearchChange: vi.fn(),
        onSubmit: vi.fn((e: React.FormEvent) => e.preventDefault()),
        onReset: vi.fn(),
        renderPagination: () => null,
        canManage: true,
        ...overrides,
    };
    render(<WhatsAppPanel {...props} />);
    return props;
}

describe('WhatsAppPanel', () => {
    beforeEach(() => {
        formPost.mockClear();
        mockErrors = {};
    });

    it('affiche les KPI de clics et le journal', () => {
        renderPanel();

        expect(screen.getByText('42')).toBeInTheDocument();
        expect(screen.getByText('/artisans')).toBeInTheDocument();
        expect(screen.getByText('Bouton flottant')).toBeInTheDocument();
    });

    it('affiche un état vide sans aucun clic enregistré', () => {
        renderPanel({ whatsappClicksPage: makePage([]) });
        expect(screen.getByText('Aucun clic enregistré')).toBeInTheDocument();
    });

    it('pré-remplit le formulaire de réglages avec les valeurs actuelles', () => {
        renderPanel();

        expect(screen.getByDisplayValue('+2250160606183')).toBeInTheDocument();
        expect(screen.getByDisplayValue('Bonjour, je souhaite un devis.')).toBeInTheDocument();
    });

    it('enregistre les nouveaux réglages du bouton WhatsApp', () => {
        renderPanel();

        fireEvent.change(screen.getByPlaceholderText('+2250160606183'), {
            target: { value: '+2250700000000' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

        expect(formPost).toHaveBeenCalledWith(
            '/admin/vitrine/settings',
            expect.objectContaining({ whatsapp_widget_phone: '+2250700000000' }),
        );
    });

    it('désactive le formulaire de réglages sans la capacité admin.whatsapp.manage', () => {
        renderPanel({ canManage: false });

        expect(screen.getByRole('button', { name: 'Enregistrer' })).toBeDisabled();
        expect(screen.getByPlaceholderText('+2250160606183')).toBeDisabled();
    });

    it('affiche les erreurs de validation du serveur', () => {
        mockErrors = { whatsapp_widget_phone: 'Le numéro doit être au format international.' };
        renderPanel();

        expect(screen.getByText('Le numéro doit être au format international.')).toBeInTheDocument();
    });
});
