import { fireEvent, render, screen, within } from '@testing-library/react';
import { useState } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const routerDelete = vi.fn();
const routerPost = vi.fn();
const formPost = vi.fn();

vi.mock('@inertiajs/react', () => ({
    router: {
        delete: (...args: unknown[]) => routerDelete(...args),
        post: (...args: unknown[]) => routerPost(...args),
    },
    useForm: <T extends Record<string, unknown>>(initial: T) => {
        const [data, setDataState] = useState(initial);
        return {
            data,
            setData: (key: string | T, value?: unknown) =>
                setDataState((current) => (typeof key === 'string' ? { ...current, [key]: value } : { ...current, ...key })),
            post: (url: string) => formPost(url, data),
            reset: () => setDataState(initial),
            processing: false,
            errors: {},
        };
    },
}));

import { ContactsSubPanel } from './ContactsSubPanel';

function makeMessage(overrides: Record<string, unknown> = {}) {
    return {
        id: 3,
        nom: 'Awa Traoré',
        email: 'awa@example.ci',
        telephone: '+2250700000003',
        sujet: 'Fuite dans la cuisine',
        message: 'Besoin d’un plombier rapidement.',
        statut: 'nouveau',
        priorite: 'normale',
        created_at: '2026-09-20T10:00:00Z',
        ...overrides,
    };
}

describe('ContactsSubPanel', () => {
    beforeEach(() => {
        routerDelete.mockClear();
        routerPost.mockClear();
        formPost.mockClear();
    });

    it('liste les demandes de contact', () => {
        render(<ContactsSubPanel messages={[makeMessage()]} />);

        expect(screen.getByText('Fuite dans la cuisine')).toBeInTheDocument();
        expect(screen.getAllByText(/Awa Traoré/).length).toBeGreaterThan(0);
    });

    it('filtre les demandes par mot-clé', () => {
        render(
            <ContactsSubPanel
                messages={[makeMessage(), makeMessage({ id: 4, nom: 'Yao Konan', sujet: 'Devis toiture', email: 'yao@example.ci' })]}
            />,
        );

        fireEvent.change(screen.getByPlaceholderText(/Rechercher par nom/), { target: { value: 'toiture' } });

        expect(screen.getByText('Devis toiture')).toBeInTheDocument();
        expect(screen.queryByText('Fuite dans la cuisine')).not.toBeInTheDocument();
    });

    it('supprime une demande seulement après confirmation', async () => {
        render(<ContactsSubPanel messages={[makeMessage()]} />);

        fireEvent.click(screen.getByTitle('Supprimer'));
        const dialog = await screen.findByRole('dialog');
        expect(routerDelete).not.toHaveBeenCalled();

        fireEvent.click(within(dialog).getByRole('button', { name: 'Supprimer' }));

        await vi.waitFor(() => expect(routerDelete).toHaveBeenCalledWith('/admin/vitrine/contacts/3', expect.anything()));
    });

    it('marque une demande comme traitée en conservant sa priorité', () => {
        render(<ContactsSubPanel messages={[makeMessage({ priorite: 'urgente' })]} />);

        fireEvent.click(screen.getByTitle('Marquer comme traité'));

        expect(routerPost).toHaveBeenCalledWith(
            '/admin/vitrine/contacts/3',
            expect.objectContaining({ statut: 'traite', priorite: 'urgente' }),
            expect.anything(),
        );
    });
});
