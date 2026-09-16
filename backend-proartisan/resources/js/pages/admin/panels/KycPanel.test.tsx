import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { KycPanel } from './KycPanel';
import type { KycStats, KycUser, Paginated } from '../shared';

const kycStats: KycStats = {
    pending: 5,
    artisans_pending: 3,
    fournisseurs_pending: 1,
    rejected: 2,
    registration_trend: [],
};

function makeUser(overrides: Partial<KycUser> = {}): KycUser {
    return {
        id: 9,
        name: 'Awa Traoré',
        phone: '+2250700000009',
        role: 'artisan',
        created_at: '2026-02-01T09:00:00Z',
        kyc_documents: [
            { id: 1, type: 'cni', file_url: 'https://example.test/cni.jpg' },
            { id: 2, type: 'selfie', file_url: 'https://example.test/selfie.jpg' },
        ],
        ...overrides,
    };
}

function makePage(data: KycUser[]): Paginated<KycUser> {
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

function renderPanel(overrides: Partial<React.ComponentProps<typeof KycPanel>> = {}) {
    const onKycDecision = vi.fn();
    const onBulkKyc = vi.fn();
    const onToggleRow = vi.fn();
    const onSubmit = vi.fn((e: React.FormEvent) => e.preventDefault());

    render(
        <KycPanel
            kycUsersPage={makePage([makeUser()])}
            pendingFournisseursList={[]}
            kycStats={kycStats}
            cnmciUsers={[]}
            search=""
            onSearchChange={vi.fn()}
            onSubmit={onSubmit}
            onReset={vi.fn()}
            renderPagination={() => null}
            actionLoading={false}
            isSelected={() => false}
            selectionCount={0}
            onToggleRow={onToggleRow}
            onToggleAll={vi.fn()}
            onClearSelection={vi.fn()}
            onBulkKyc={onBulkKyc}
            onKycDecision={onKycDecision}
            onFournisseurDecision={vi.fn()}
            onCnmciDecision={vi.fn()}
            canReview
            canReviewFournisseurs
            {...overrides}
        />,
    );

    return { onKycDecision, onBulkKyc, onToggleRow };
}

describe('KycPanel', () => {
    it('affiche le dossier avec ses liens de pièces justificatives', () => {
        renderPanel();
        expect(screen.getByText('Awa Traoré')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Voir CNI' })).toHaveAttribute('href', 'https://example.test/cni.jpg');
        expect(screen.getByRole('link', { name: 'Voir SELFIE' })).toHaveAttribute('href', 'https://example.test/selfie.jpg');
    });

    it('déclenche onKycDecision avec le bon utilisateur et la bonne décision', () => {
        const { onKycDecision } = renderPanel();

        fireEvent.click(screen.getByRole('button', { name: 'Approuver' }));

        expect(onKycDecision).toHaveBeenCalledWith(expect.objectContaining({ id: 9 }), 'approuve');
    });

    it("masque les actions de revue et le bandeau d'actions groupées sans la capacité admin.kyc.review", () => {
        renderPanel({ canReview: false, selectionCount: 2 });

        expect(screen.queryByRole('button', { name: 'Approuver' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Rejeter' })).not.toBeInTheDocument();
        expect(screen.queryByText('Approuver la sélection')).not.toBeInTheDocument();
        expect(screen.getByText('Lecture seule')).toBeInTheDocument();
    });

    it('la sélection individuelle appelle onToggleRow pour la bonne ligne', () => {
        const { onToggleRow } = renderPanel();

        fireEvent.click(screen.getByLabelText('Sélectionner Awa Traoré'));

        expect(onToggleRow).toHaveBeenCalledWith(9);
    });

    it('affiche un état vide sans dossier KYC', () => {
        renderPanel({ kycUsersPage: makePage([]) });
        expect(screen.getByText('Rien à afficher')).toBeInTheDocument();
    });
});
