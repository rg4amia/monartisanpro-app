import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import type { KycStats, KycUser, Paginated } from '../shared';
import { KycPanel } from './KycPanel';

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

    it("signale un dossier que l'IA n'a pas analysé", () => {
        renderPanel();
        expect(screen.getByText('Non analysé')).toBeInTheDocument();
    });

    it("affiche le résultat de l'analyse IA et ses anomalies", () => {
        renderPanel({
            kycUsersPage: makePage([
                makeUser({
                    kyc_documents: [
                        {
                            id: 1,
                            type: 'cni',
                            file_url: 'https://example.test/cni.jpg',
                            ai_confidence_score: 91,
                            ai_analysis: { analysis_available: true, anomalies: [] },
                            ocr_data: { document_number: 'CI0029384756', last_name: 'TRAORE', first_name: 'Awa' },
                        },
                        {
                            id: 2,
                            type: 'selfie',
                            file_url: 'https://example.test/selfie.jpg',
                            ai_confidence_score: 30,
                            face_matched: false,
                            ai_analysis: { analysis_available: true, anomalies: ['photo_d_un_ecran'] },
                        },
                    ],
                }),
            ]),
        });

        // Le verdict retient le plus faible des deux scores (biométrie à 30).
        expect(screen.getByText('🤖 30 % · Risque élevé')).toBeInTheDocument();
        expect(screen.getByText(/Pièce : 91 \/ 100 · n° CI0029384756/)).toBeInTheDocument();
        expect(screen.getByText('Titulaire lu : TRAORE Awa')).toBeInTheDocument();
        expect(screen.getByText(/non concordant \(30 \/ 100\)/)).toBeInTheDocument();
        expect(screen.getByText('Anomalies : photo d un ecran')).toBeInTheDocument();
    });

    it('situe le score IA par rapport aux seuils transmis par le serveur et liste les motifs à vérifier', () => {
        const analysed = (score: number) => [
            { id: 1, type: 'cni' as const, file_url: 'x', ai_confidence_score: score, ai_analysis: { analysis_available: true } },
            { id: 2, type: 'selfie' as const, file_url: 'y', ai_confidence_score: score, face_matched: true, ai_analysis: { analysis_available: true } },
        ];

        renderPanel({
            kycStats: { ...kycStats, ai_auto_threshold: 90, ai_review_threshold: 60 },
            kycUsersPage: makePage([
                makeUser({ id: 1, name: 'A', kyc_documents: analysed(92), kyc_ai_blockers: ['role_soumis_a_revue_humaine'] }),
                makeUser({ id: 2, name: 'B', kyc_documents: analysed(88) }),
                makeUser({ id: 3, name: 'C', kyc_documents: analysed(55) }),
            ]),
        });

        expect(screen.getByText('🤖 92 % · IA favorable')).toBeInTheDocument();
        // 88 reste sous le seuil d'auto-approbation configuré (90).
        expect(screen.getByText('🤖 88 % · Revue conseillée')).toBeInTheDocument();
        expect(screen.getByText('🤖 55 % · Risque élevé')).toBeInTheDocument();
        expect(screen.getByText('Rôle soumis à revue humaine')).toBeInTheDocument();
    });

    it('affiche un état vide sans dossier KYC', () => {
        renderPanel({ kycUsersPage: makePage([]) });
        expect(screen.getByText('Rien à afficher')).toBeInTheDocument();
    });
});
