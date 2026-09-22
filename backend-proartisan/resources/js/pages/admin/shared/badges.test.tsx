import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { MissionStatusBadge } from './badges';
import { missionStatusLabels } from './constants';

/**
 * Les missions sont stockées en base avec le nom technique de l'état du FSM
 * (app/States/Mission/*State.php). Le badge retombant sur `?? status`, tout
 * état absent de `missionStatusLabels` s'affichait tel quel à l'admin —
 * c'est-à-dire en anglais brut (« in_progress », « pending_approval »…).
 * Ces tests verrouillent la couverture des 9 états du FSM.
 */
const FSM_STATES = [
    'draft',
    'pending_artisan_acceptance',
    'pending_funding',
    'funded_locked',
    'in_progress',
    'pending_approval',
    'completed',
    'disputed',
    'cancelled',
] as const;

describe('MissionStatusBadge', () => {
    it.each(FSM_STATES)('traduit en français l’état technique « %s »', (status) => {
        render(<MissionStatusBadge status={status} />);

        const rendered = screen.getByText(missionStatusLabels[status]);

        expect(rendered).toBeTruthy();
        expect(rendered.textContent).not.toBe(status);
    });

    it('couvre tous les états du FSM sans laisser fuiter de terme anglais', () => {
        const missing = FSM_STATES.filter((status) => !missionStatusLabels[status]);

        expect(missing).toEqual([]);
    });

    it('conserve les statuts français historiques', () => {
        render(<MissionStatusBadge status="terminee" />);

        expect(screen.getByText('Terminée')).toBeTruthy();
    });

    it('affiche "Demande refusée" si la mission a été rejetée par l\'artisan', () => {
        render(
            <MissionStatusBadge
                status="draft"
                mission={{
                    id: 1,
                    status: 'draft',
                    artisan_rejected_at: '2026-09-22T10:00:00Z',
                } as any}
            />,
        );

        expect(screen.getByText('Demande refusée')).toBeTruthy();
    });

    it('affiche "Devis soumis" si un devis est en attente', () => {
        render(
            <MissionStatusBadge
                status="draft"
                mission={{
                    id: 2,
                    status: 'draft',
                    devis: [{ id: 1, statut: 'soumis' }],
                } as any}
            />,
        );

        expect(screen.getByText('Devis soumis (En attente client)')).toBeTruthy();
    });

    it('affiche "En attente d\'artisan" si aucun artisan n\'est assigné', () => {
        render(
            <MissionStatusBadge
                status="draft"
                mission={{
                    id: 3,
                    status: 'draft',
                    artisan_id: null,
                    artisan: null,
                } as any}
            />,
        );

        expect(screen.getByText("En attente d'artisan")).toBeTruthy();
    });
});
