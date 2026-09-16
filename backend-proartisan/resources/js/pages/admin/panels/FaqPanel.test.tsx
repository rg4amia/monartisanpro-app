import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { useState } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const routerDelete = vi.fn();
const formPost = vi.fn();
let mockErrors: Record<string, string> = {};

vi.mock('@inertiajs/react', () => ({
    router: { delete: (...args: unknown[]) => routerDelete(...args) },
    useForm: <T extends Record<string, unknown>>(initial: T) => {
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
            reset: () => setDataState(initial),
            processing: false,
            errors: mockErrors,
        };
    },
}));

import { FaqPanel } from './FaqPanel';
import type { FaqItem } from '../shared';

function makeFaq(overrides: Partial<FaqItem> = {}): FaqItem {
    return {
        id: 1,
        question: 'Comment fonctionne le séquestre ?',
        reponse: 'Le séquestre est débloqué jalon par jalon après validation OTP.',
        categorie: 'Paiement',
        roles: ['client', 'artisan'],
        ordre: 1,
        actif: true,
        created_at: '2026-01-01T00:00:00Z',
        updated_at: '2026-01-01T00:00:00Z',
        ...overrides,
    };
}

describe('FaqPanel', () => {
    beforeEach(() => {
        routerDelete.mockClear();
        formPost.mockClear();
        mockErrors = {};
    });

    it('affiche les questions avec leur catégorie et leurs rôles ciblés', () => {
        render(<FaqPanel faqs={[makeFaq()]} canManage />);

        expect(screen.getByText('Comment fonctionne le séquestre ?')).toBeInTheDocument();
        expect(screen.getByText('Paiement')).toBeInTheDocument();
    });

    it('signale une question masquée', () => {
        render(<FaqPanel faqs={[makeFaq({ actif: false })]} canManage />);
        expect(screen.getByText('Masquée')).toBeInTheDocument();
    });

    it('affiche un état vide sans aucune question', () => {
        render(<FaqPanel faqs={[]} canManage />);
        expect(screen.getByText("Aucune question pour l'instant")).toBeInTheDocument();
    });

    it('masque les actions de gestion sans la capacité admin.faq.manage', () => {
        render(<FaqPanel faqs={[makeFaq()]} canManage={false} />);

        expect(screen.queryByRole('button', { name: 'Modifier' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Supprimer' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Nouvelle Question' })).not.toBeInTheDocument();
    });

    it('passe une question en mode édition et enregistre les modifications', () => {
        const faq = makeFaq();
        render(<FaqPanel faqs={[faq]} canManage />);

        fireEvent.click(screen.getByRole('button', { name: 'Modifier' }));
        fireEvent.change(screen.getByDisplayValue(faq.question), {
            target: { value: 'Comment fonctionne le séquestre matériaux ?' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

        expect(formPost).toHaveBeenCalledWith(
            '/admin/faq/1',
            expect.objectContaining({ question: 'Comment fonctionne le séquestre matériaux ?' }),
        );
    });

    it('annule l\'édition sans appeler le backend', () => {
        const faq = makeFaq();
        render(<FaqPanel faqs={[faq]} canManage />);

        fireEvent.click(screen.getByRole('button', { name: 'Modifier' }));
        fireEvent.click(screen.getByRole('button', { name: 'Annuler' }));

        expect(formPost).not.toHaveBeenCalled();
        expect(screen.getByText(faq.question)).toBeInTheDocument();
    });

    it('supprime une question après confirmation', async () => {
        const faq = makeFaq();
        render(<FaqPanel faqs={[faq]} canManage />);

        fireEvent.click(screen.getByRole('button', { name: 'Supprimer' }));
        const dialog = await screen.findByRole('dialog');
        fireEvent.click(within(dialog).getByRole('button', { name: 'Supprimer' }));

        await waitFor(() =>
            expect(routerDelete).toHaveBeenCalledWith(
                '/admin/faq/1',
                expect.objectContaining({ preserveScroll: true }),
            ),
        );
    });

    it('n\'appelle pas le backend si la suppression est annulée', async () => {
        render(<FaqPanel faqs={[makeFaq()]} canManage />);

        fireEvent.click(screen.getByRole('button', { name: 'Supprimer' }));
        const dialog = await screen.findByRole('dialog');
        fireEvent.click(within(dialog).getByRole('button', { name: 'Annuler' }));

        expect(routerDelete).not.toHaveBeenCalled();
    });

    it('crée une nouvelle question via le formulaire dédié', () => {
        render(<FaqPanel faqs={[]} canManage />);

        fireEvent.click(screen.getByRole('button', { name: 'Nouvelle Question' }));
        fireEvent.change(screen.getByPlaceholderText('Question'), {
            target: { value: 'Comment scanner un J-Code ?' },
        });
        fireEvent.change(screen.getByPlaceholderText('Réponse'), {
            target: { value: 'Ouvrez le scanner depuis votre espace fournisseur.' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Ajouter la question' }));

        expect(formPost).toHaveBeenCalledWith(
            '/admin/faq',
            expect.objectContaining({ question: 'Comment scanner un J-Code ?' }),
        );
    });

    it('affiche les erreurs de validation du serveur lors de la création', () => {
        mockErrors = { question: 'La question est obligatoire.' };
        render(<FaqPanel faqs={[]} canManage />);

        fireEvent.click(screen.getByRole('button', { name: 'Nouvelle Question' }));

        expect(screen.getByText('La question est obligatoire.')).toBeInTheDocument();
    });
});
