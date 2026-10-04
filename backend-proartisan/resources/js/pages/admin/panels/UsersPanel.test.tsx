import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import type { AdminUser, Paginated, UserStats } from '../shared';
import { UsersPanel } from './UsersPanel';

const userStats: UserStats = { total: 100, artisans_actifs: 40, clients_actifs: 55, fournisseurs_agrees: 5 };

function makeUser(overrides: Partial<AdminUser> = {}): AdminUser {
    return {
        id: 21,
        name: 'Awa Traoré',
        email: 'awa@example.test',
        phone: '+2250700000021',
        role: 'client',
        kyc_status: 'actif',
        score_prosartisan: 650,
        created_at: '2026-01-01T00:00:00Z',
        missions_client_count: 4,
        missions_artisan_count: 0,
        account_status: 'actif',
        ...overrides,
    };
}

function makePage(data: AdminUser[]): Paginated<AdminUser> {
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

function renderPanel(overrides: Partial<React.ComponentProps<typeof UsersPanel>> = {}) {
    const onImpersonate = vi.fn();
    const onDeleteUser = vi.fn();

    render(
        <UsersPanel
            users={makePage([makeUser()])}
            userStats={userStats}
            pendingFournisseurs={[]}
            topArtisans={[]}
            search=""
            onSearchChange={vi.fn()}
            roleFilter=""
            onRoleFilterChange={vi.fn()}
            kycFilter=""
            onKycFilterChange={vi.fn()}
            onSubmit={vi.fn((e: React.FormEvent) => e.preventDefault())}
            onReset={vi.fn()}
            exportParams={{}}
            renderPagination={() => null}
            actionLoading={false}
            isSelected={() => false}
            selectionCount={0}
            onToggleRow={vi.fn()}
            onToggleAll={vi.fn()}
            onClearSelection={vi.fn()}
            onBulkStatus={vi.fn()}
            onCreateUser={vi.fn()}
            onEditUser={vi.fn()}
            onToggleUserStatus={vi.fn()}
            onDeleteUser={onDeleteUser}
            onFournisseurDecision={vi.fn()}
            canManage
            canDelete
            canReviewFournisseurs
            onImpersonate={onImpersonate}
            {...overrides}
        />,
    );

    return { onImpersonate, onDeleteUser };
}

describe('UsersPanel', () => {
    it("n'affiche pas le bouton Usurper sans la capacité admin.users.impersonate", () => {
        renderPanel({ canImpersonate: false });
        expect(screen.queryByRole('button', { name: 'Usurper' })).not.toBeInTheDocument();
    });

    it('affiche le bouton Usurper pour un compte non-admin quand la capacité est accordée', () => {
        renderPanel({ canImpersonate: true, users: makePage([makeUser({ role: 'client' })]) });
        expect(screen.getByRole('button', { name: 'Usurper' })).toBeInTheDocument();
    });

    it("masque le bouton Usurper sur un compte administrateur, même avec la capacité accordée", () => {
        renderPanel({ canImpersonate: true, users: makePage([makeUser({ role: 'admin' })]) });
        expect(screen.queryByRole('button', { name: 'Usurper' })).not.toBeInTheDocument();
    });

    it('déclenche onImpersonate avec le bon utilisateur au clic', () => {
        const { onImpersonate } = renderPanel({ canImpersonate: true });
        fireEvent.click(screen.getByRole('button', { name: 'Usurper' }));
        expect(onImpersonate).toHaveBeenCalledWith(expect.objectContaining({ id: 21 }));
    });

    it('affiche "Lecture seule" quand aucune capacité de gestion n\'est accordée', () => {
        renderPanel({ canManage: false, canDelete: false, canViewRgpd: false, canImpersonate: false });
        expect(screen.getByText('Lecture seule')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Modifier' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Supprimer' })).not.toBeInTheDocument();
    });

    it('ne propose ni suspension ni suppression pour un super administrateur protégé', () => {
        renderPanel({ users: makePage([makeUser({ role: 'admin', is_protected: true })]) });

        expect(screen.getByText('Super administrateur protégé')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Modifier' })).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Suspendre' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Supprimer' })).not.toBeInTheDocument();
    });

    it('ne propose ni modification ni changement de statut pour un compte anonymisé', () => {
        renderPanel({ users: makePage([makeUser({ account_status: 'suspendu', anonymized_at: '2026-09-01T00:00:00Z' })]) });

        expect(screen.queryByRole('button', { name: 'Modifier' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Activer' })).not.toBeInTheDocument();
    });

    it('propose seulement la restauration pour un compte supprimé', () => {
        const onRestoreUser = vi.fn();
        const deleted = makeUser({ deleted_at: '2026-09-20T10:00:00Z' });
        renderPanel({ users: makePage([deleted]), stateFilter: 'supprimes', onRestoreUser });

        expect(screen.queryByRole('button', { name: 'Modifier' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Supprimer' })).not.toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Restaurer' }));
        expect(onRestoreUser).toHaveBeenCalledWith(deleted);
    });

    it('filtre les comptes supprimés', () => {
        const onStateFilterChange = vi.fn();
        renderPanel({ onStateFilterChange });

        fireEvent.change(screen.getByLabelText('État'), { target: { value: 'supprimes' } });
        expect(onStateFilterChange).toHaveBeenCalledWith('supprimes');

        // Le même filtre porte le statut du compte.
        const options = Array.from((screen.getByLabelText('État') as HTMLSelectElement).options).map((o) => o.value);
        expect(options).toEqual(['', 'actif', 'suspendu', 'banni', 'anonymises', 'supprimes']);
        fireEvent.change(screen.getByLabelText('État'), { target: { value: 'suspendu' } });
        expect(onStateFilterChange).toHaveBeenCalledWith('suspendu');
    });

    it("propose de retirer le KYC d'un compte actif au porteur de la revue KYC", () => {
        const onRevokeKyc = vi.fn();
        const user = makeUser({ kyc_status: 'actif' });
        renderPanel({ users: makePage([user]), canReviewKyc: true, onRevokeKyc });

        fireEvent.click(screen.getByRole('button', { name: 'Retirer le KYC' }));
        expect(onRevokeKyc).toHaveBeenCalledWith(user);
    });

    it("ne propose pas de retirer le KYC sans la capacité, ni pour un compte en attente ou un Référent", () => {
        renderPanel({ users: makePage([makeUser({ kyc_status: 'actif' })]), onRevokeKyc: vi.fn() });
        expect(screen.queryByRole('button', { name: 'Retirer le KYC' })).not.toBeInTheDocument();
    });

    it("indique si un appareil est lié, sans afficher son empreinte", () => {
        renderPanel({ users: makePage([makeUser({ has_device: true })]) });

        expect(screen.getByText('Appareil lié')).toBeInTheDocument();
    });

    it("masque la suppression sans la capacité admin.users.delete même avec admin.users.manage", () => {
        renderPanel({ canManage: true, canDelete: false });
        expect(screen.getByRole('button', { name: 'Modifier' })).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Supprimer' })).not.toBeInTheDocument();
    });

    it("signale un compte validé automatiquement par l'IA", () => {
        renderPanel({
            users: makePage([
                makeUser({
                    kyc_documents: [
                        { type: 'cni', statut: 'approuve', file_url: null, auto_verified: true, ai_confidence_score: 93 },
                        { type: 'selfie', statut: 'approuve', file_url: null, auto_verified: true, ai_confidence_score: 90 },
                    ],
                }),
            ]),
        });

        expect(screen.getByText('🤖 90 % · Auto-validé')).toBeInTheDocument();
    });

    it("n'affiche pas le badge IA pour un dossier validé par un administrateur", () => {
        renderPanel({
            users: makePage([
                makeUser({
                    kyc_documents: [
                        { type: 'cni', statut: 'approuve', file_url: null, auto_verified: false },
                        { type: 'selfie', statut: 'approuve', file_url: null, auto_verified: false },
                    ],
                }),
            ]),
        });

        expect(screen.queryByText(/Auto-validé/)).not.toBeInTheDocument();
    });

    it('affiche un état vide sans compte utilisateur', () => {
        renderPanel({ users: makePage([]) });
        expect(screen.getByText('Liste vide')).toBeInTheDocument();
    });
});
