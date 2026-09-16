import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { PersonalDataModal } from './PersonalDataModal';
import type { AdminUser, PersonalDataReport } from '../shared';

const baseUser: AdminUser = {
    id: 12,
    name: 'Awa Traoré',
    phone: '+2250700000012',
    role: 'client',
    kyc_status: 'actif',
    score_prosartisan: 0,
    created_at: '2026-01-01T00:00:00Z',
    missions_client_count: 0,
    missions_artisan_count: 0,
};

function makeReport(overrides: Partial<PersonalDataReport['user']> = {}): PersonalDataReport {
    return {
        user: {
            id: 12,
            name: 'Awa Traoré',
            email: 'awa@example.test',
            phone: '+2250700000012',
            role: 'client',
            kyc_status: 'actif',
            account_status: 'actif',
            created_at: '2026-01-01T00:00:00Z',
            cgu_accepted_at: '2026-01-01T00:00:00Z',
            anonymized_at: null,
            payment_phone: null,
            cnmci_number: null,
            cnmci_card_url: null,
            device_fingerprint: null,
            commune: 'Cocody',
            ...overrides,
        },
        position: null,
        kyc_documents: [],
        evaluations_given: 0,
        evaluations_received: 0,
        missions_as_client: 2,
        missions_as_artisan: 0,
        transactions_count: 3,
        notifications_count: 1,
        parrainages_count: 0,
        activity_trace: [],
    };
}

function mockFetchOnce(report: PersonalDataReport | null, ok = true) {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue({
            ok,
            status: ok ? 200 : 500,
            json: () => Promise.resolve(report),
        }),
    );
}

describe('PersonalDataModal', () => {
    beforeEach(() => {
        vi.stubGlobal('fetch', vi.fn());
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('affiche un état de chargement puis les données personnelles une fois reçues', async () => {
        mockFetchOnce(makeReport());

        render(
            <PersonalDataModal user={baseUser} canAnonymize actionLoading={false} onAnonymize={vi.fn()} onClose={vi.fn()} />,
        );

        expect(screen.getByText('Chargement…')).toBeInTheDocument();

        await waitFor(() => expect(screen.getByText('awa@example.test')).toBeInTheDocument());
        expect(screen.getByText('Cocody')).toBeInTheDocument();
    });

    it("affiche une erreur explicite si l'appel réseau échoue", async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('network down')));

        render(
            <PersonalDataModal user={baseUser} canAnonymize actionLoading={false} onAnonymize={vi.fn()} onClose={vi.fn()} />,
        );

        await waitFor(() => expect(screen.getByText('Impossible de charger les données personnelles.')).toBeInTheDocument());
    });

    it('propose « Anonymiser ce compte » quand la capacité est accordée et le compte non déjà anonymisé', async () => {
        mockFetchOnce(makeReport());
        const onAnonymize = vi.fn();

        render(
            <PersonalDataModal user={baseUser} canAnonymize actionLoading={false} onAnonymize={onAnonymize} onClose={vi.fn()} />,
        );

        await waitFor(() => expect(screen.getByText('awa@example.test')).toBeInTheDocument());

        fireEvent.click(screen.getByRole('button', { name: 'Anonymiser ce compte' }));
        expect(onAnonymize).toHaveBeenCalledWith(baseUser);
    });

    it('masque « Anonymiser ce compte » sans la capacité admin.rgpd.manage', async () => {
        mockFetchOnce(makeReport());

        render(
            <PersonalDataModal user={baseUser} canAnonymize={false} actionLoading={false} onAnonymize={vi.fn()} onClose={vi.fn()} />,
        );

        await waitFor(() => expect(screen.getByText('awa@example.test')).toBeInTheDocument());
        expect(screen.queryByRole('button', { name: 'Anonymiser ce compte' })).not.toBeInTheDocument();
    });

    it("masque « Anonymiser ce compte » quand le compte est déjà anonymisé — refuse le second passage", async () => {
        mockFetchOnce(makeReport({ anonymized_at: '2026-02-01T00:00:00Z', email: null, name: 'Compte anonymisé' }));

        render(
            <PersonalDataModal user={baseUser} canAnonymize actionLoading={false} onAnonymize={vi.fn()} onClose={vi.fn()} />,
        );

        await waitFor(() => expect(screen.getByText('Compte anonymisé')).toBeInTheDocument());
        expect(screen.queryByRole('button', { name: 'Anonymiser ce compte' })).not.toBeInTheDocument();
    });

    it('ferme la modale sur Échap', async () => {
        mockFetchOnce(makeReport());
        const onClose = vi.fn();

        render(
            <PersonalDataModal user={baseUser} canAnonymize actionLoading={false} onAnonymize={vi.fn()} onClose={onClose} />,
        );
        await waitFor(() => expect(screen.getByText('awa@example.test')).toBeInTheDocument());

        fireEvent.keyDown(document, { key: 'Escape' });
        expect(onClose).toHaveBeenCalled();
    });
});
