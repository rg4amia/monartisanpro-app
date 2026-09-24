import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import type { AdminActivityLogItem, PaginatedAuditLogs } from '../shared';
import { AuditLogsPanel } from './AuditLogsPanel';

function makeLog(overrides: Partial<AdminActivityLogItem> = {}): AdminActivityLogItem {
    return {
        id: 1,
        admin_id: 3,
        admin_name: 'Admin Kouassi',
        action: 'kyc.reviewed',
        subject_type: 'App\\Models\\User',
        subject_id: 55,
        subject_label: 'Awa Traoré',
        context: { decision: 'approuve' },
        ip_address: '10.0.0.5',
        user_agent: 'vitest',
        created_at: '2026-02-10T08:30:00Z',
        ...overrides,
    };
}

function makePage(data: AdminActivityLogItem[]): PaginatedAuditLogs {
    return { data, current_page: 1, last_page: 1, total: data.length, per_page: 15, links: [] };
}

function renderPanel(overrides: Partial<React.ComponentProps<typeof AuditLogsPanel>> = {}) {
    const onSubmit = vi.fn((e: React.FormEvent) => e.preventDefault());

    render(
        <AuditLogsPanel
            auditLogs={makePage([makeLog()])}
            auditActions={['kyc.reviewed', 'user.impersonation_started']}
            auditAdmins={[{ admin_id: 3, admin_name: 'Admin Kouassi' }]}
            search=""
            onSearchChange={vi.fn()}
            actionFilter=""
            onActionFilterChange={vi.fn()}
            adminFilter=""
            onAdminFilterChange={vi.fn()}
            dateFrom=""
            onDateFromChange={vi.fn()}
            dateTo=""
            onDateToChange={vi.fn()}
            onSubmit={onSubmit}
            onReset={vi.fn()}
            renderPagination={() => null}
            {...overrides}
        />,
    );
}

describe('AuditLogsPanel', () => {
    it("affiche l'administrateur, l'action et l'entité concernée d'une entrée", () => {
        renderPanel();
        const table = within(screen.getByRole('table'));
        expect(table.getByText('Admin Kouassi')).toBeInTheDocument();
        expect(table.getByText('Awa Traoré')).toBeInTheDocument();
        expect(table.getByText('10.0.0.5')).toBeInTheDocument();
    });

    it("n'expose aucune action de modification ou de suppression — journal append-only", () => {
        renderPanel();

        // Seuls les contrôles de filtre (Filtrer/Réinitialiser) sont des boutons ;
        // aucune action n'agit sur une ligne du journal lui-même.
        expect(screen.queryByRole('button', { name: /modifier/i })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /supprimer/i })).not.toBeInTheDocument();
        expect(screen.getAllByRole('button')).toHaveLength(2);
    });

    it('affiche le détail du contexte JSON au clic sur "Voir le détail"', () => {
        renderPanel();

        fireEvent.click(screen.getByText(/Voir le détail/));
        expect(screen.getByText(/"decision": "approuve"/)).toBeInTheDocument();
    });

    it('affiche "Système / anonyme" quand aucun administrateur n\'est rattaché à l\'entrée', () => {
        renderPanel({
            auditLogs: makePage([makeLog({ admin_id: null, admin_name: null, admin: null })]),
        });

        expect(screen.getByText('Système / anonyme')).toBeInTheDocument();
    });

    it('affiche un état vide sans entrée de journal', () => {
        renderPanel({ auditLogs: makePage([]) });
        expect(screen.getByText('Journal vide')).toBeInTheDocument();
    });
});
