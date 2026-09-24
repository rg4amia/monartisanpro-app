import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const routerPost = vi.fn();
vi.mock('@inertiajs/react', () => ({
    router: { post: (...args: unknown[]) => routerPost(...args) },
}));

import type { GeneratedDocumentItem, Paginated } from '../shared';
import { DocumentsReportsSection } from './DocumentsReportsSection';

function makeDoc(overrides: Partial<GeneratedDocumentItem> = {}): GeneratedDocumentItem {
    return {
        id: 1,
        reference: 'DOC-0001',
        document_type: 'recu_liberation_jalon',
        title: 'Reçu de libération de jalon',
        mission_id: 12,
        montant: 50000,
        mime_type: 'application/pdf',
        created_at: '2026-02-01T09:00:00Z',
        user: { id: 2, name: "Koffi N'Guessan", phone: '+2250700000001', role: 'artisan' },
        ...overrides,
    } as GeneratedDocumentItem;
}

function makePage(data: GeneratedDocumentItem[]): Paginated<GeneratedDocumentItem> {
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

function renderSection(overrides: Partial<React.ComponentProps<typeof DocumentsReportsSection>> = {}) {
    const props: React.ComponentProps<typeof DocumentsReportsSection> = {
        documentsPage: makePage([makeDoc()]),
        ...overrides,
    };
    render(<DocumentsReportsSection {...props} />);
    return props;
}

describe('DocumentsReportsSection', () => {
    beforeEach(() => routerPost.mockClear());

    it('affiche les documents avec leur type et leur montant certifié', () => {
        renderSection();

        expect(screen.getByText('DOC-0001')).toBeInTheDocument();
        expect(screen.getByText('Reçu Jalon MO')).toBeInTheDocument();
        expect(screen.getByText("Koffi N'Guessan")).toBeInTheDocument();
        expect(screen.getByText('Mission #12')).toBeInTheDocument();
    });

    it('affiche un état vide sans document', () => {
        renderSection({ documentsPage: makePage([]) });
        expect(screen.getByText('Aucun document répertorié')).toBeInTheDocument();
    });

    it('filtre les documents par recherche textuelle', () => {
        renderSection({
            documentsPage: makePage([
                makeDoc({ id: 1, reference: 'DOC-0001' }),
                makeDoc({ id: 2, reference: 'DOC-0002', user: { id: 3, name: 'Awa Traoré', phone: '+2250700000002', role: 'client' } }),
            ]),
        });

        fireEvent.change(
            screen.getByPlaceholderText('Rechercher réf, artisan, client, tél, mission...'),
            { target: { value: 'Awa' } },
        );

        expect(screen.queryByText('DOC-0001')).not.toBeInTheDocument();
        expect(screen.getByText('DOC-0002')).toBeInTheDocument();
    });

    it('filtre les documents par type', () => {
        renderSection({
            documentsPage: makePage([
                makeDoc({ id: 1, reference: 'DOC-0001', document_type: 'recu_liberation_jalon' }),
                makeDoc({ id: 2, reference: 'DOC-0002', document_type: 'facture_litige' }),
            ]),
        });

        fireEvent.change(screen.getByRole('combobox'), { target: { value: 'facture_litige' } });

        expect(screen.queryByText('DOC-0001')).not.toBeInTheDocument();
        expect(screen.getByText('DOC-0002')).toBeInTheDocument();
    });

    it('déclenche la synchronisation des pièces', () => {
        renderSection();

        fireEvent.click(screen.getByRole('button', { name: /Synchroniser les pièces/ }));

        expect(routerPost).toHaveBeenCalledWith(
            '/admin/documents/sync',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('ouvre l\'aperçu détaillé d\'un document', () => {
        renderSection({
            documentsPage: makePage([
                makeDoc({ transaction_id: 77, metadata: { reference_externe: 'WV-REF-1' } }),
            ]),
        });

        fireEvent.click(screen.getByRole('button', { name: /Aperçu/ }));

        expect(screen.getByText('Document Officiel ProsArtisan CI')).toBeInTheDocument();
        expect(screen.getByText('#77')).toBeInTheDocument();
        expect(screen.getByText('WV-REF-1')).toBeInTheDocument();
    });

    it('propose le téléchargement du PDF officiel', () => {
        renderSection();

        const links = screen.getAllByRole('link', { name: /PDF/ });
        expect(links[0]).toHaveAttribute('href', '/admin/documents/1/download');
    });

    it('calcule les statistiques par défaut quand documentStats est absent', () => {
        renderSection({
            documentsPage: makePage([
                makeDoc({ id: 1, document_type: 'recu_liberation_jalon', montant: 20000 }),
                makeDoc({ id: 2, document_type: 'facture_litige', montant: 5000 }),
            ]),
        });

        expect(screen.getByText('2')).toBeInTheDocument();
        expect(screen.getByText(/25\s?000/)).toBeInTheDocument();
    });
});
