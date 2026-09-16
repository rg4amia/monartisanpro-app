import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const routerPost = vi.fn();
vi.mock('@inertiajs/react', () => ({
    router: {
        post: (...args: unknown[]) => routerPost(...args),
    },
}));

import { FraudAlertsSection, type FraudAlertItem } from './FraudAlertsSection';

function makeAlert(overrides: Partial<FraudAlertItem> = {}): FraudAlertItem {
    return {
        id: 9,
        reference: 'FA-0009',
        mission_id: 42,
        user_name: 'Koffi N\'Guessan',
        user_role: 'artisan',
        type: 'gps_anomaly_jcode',
        severity: 'critical',
        risk_score: 88,
        statut: 'ouverte',
        action_taken: 'none',
        reasons: ['Scan effectué à 340 m de la boutique enregistrée.'],
        metadata: {},
        created_at: '2026-02-01T09:00:00Z',
        ...overrides,
    };
}

describe('FraudAlertsSection', () => {
    beforeEach(() => routerPost.mockClear());

    it('affiche les KPI de sécurité et les alertes de la liste', () => {
        render(
            <FraudAlertsSection
                fraudData={{
                    open_alerts_count: 1,
                    critical_alerts_count: 1,
                    payment_holds_count: 0,
                    gps_attempts_7d: 3,
                    alerts_list: [makeAlert()],
                }}
            />,
        );

        expect(screen.getByText('FA-0009')).toBeInTheDocument();
        expect(screen.getByText('Mission #42')).toBeInTheDocument();
        expect(screen.getByText(/Koffi N'Guessan/)).toBeInTheDocument();
        expect(screen.getByText('88/100')).toBeInTheDocument();
    });

    it('affiche un état vide sans aucune alerte', () => {
        render(<FraudAlertsSection fraudData={{ alerts_list: [] }} />);
        expect(screen.getByText('Réseau sécurisé et conforme')).toBeInTheDocument();
    });

    it('filtre les alertes par gravité', () => {
        render(
            <FraudAlertsSection
                fraudData={{
                    alerts_list: [
                        makeAlert({ id: 1, reference: 'FA-0001', severity: 'critical' }),
                        makeAlert({ id: 2, reference: 'FA-0002', severity: 'low' }),
                    ],
                }}
            />,
        );

        fireEvent.change(screen.getByDisplayValue('Toutes gravités'), {
            target: { value: 'low' },
        });

        expect(screen.queryByText('FA-0001')).not.toBeInTheDocument();
        expect(screen.getByText('FA-0002')).toBeInTheDocument();
    });

    it('ouvre la modale d\'instruction avec les motifs détectés', () => {
        render(<FraudAlertsSection fraudData={{ alerts_list: [makeAlert()] }} />);

        fireEvent.click(screen.getByRole('button', { name: 'Instruire' }));

        expect(screen.getByText('Instruction Alerte #FA-0009')).toBeInTheDocument();
        expect(
            screen.getByText('Scan effectué à 340 m de la boutique enregistrée.'),
        ).toBeInTheDocument();
    });

    it('masque les actions d\'arbitrage sans la capacité admin.fraud.manage', () => {
        render(<FraudAlertsSection canManage={false} fraudData={{ alerts_list: [makeAlert()] }} />);

        fireEvent.click(screen.getByRole('button', { name: 'Instruire' }));

        expect(screen.queryByRole('button', { name: 'Classer sans suite' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Activer Gel Préventif' })).not.toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Annuler' })).toBeInTheDocument();
    });

    it('active un gel préventif via router.post', () => {
        render(<FraudAlertsSection fraudData={{ alerts_list: [makeAlert()] }} />);
        fireEvent.click(screen.getByRole('button', { name: 'Instruire' }));

        fireEvent.click(screen.getByRole('button', { name: 'Activer Gel Préventif' }));

        expect(routerPost).toHaveBeenCalledWith(
            '/admin/fraud-alerts/9/hold',
            { notes: '' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('propose la levée de gel quand un paiement est déjà bloqué', () => {
        render(
            <FraudAlertsSection
                fraudData={{ alerts_list: [makeAlert({ action_taken: 'payment_hold' })] }}
            />,
        );
        fireEvent.click(screen.getByRole('button', { name: 'Instruire' }));

        fireEvent.click(screen.getByRole('button', { name: 'Lever le Gel & Libérer' }));

        expect(routerPost).toHaveBeenCalledWith(
            '/admin/fraud-alerts/9/release',
            { notes: '' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('exige une note avant de confirmer la fraude et envoie le motif saisi', () => {
        render(<FraudAlertsSection fraudData={{ alerts_list: [makeAlert()] }} />);
        fireEvent.click(screen.getByRole('button', { name: 'Instruire' }));

        const confirmButton = screen.getByRole('button', { name: 'Confirmer Fraude & Sanctionner' });
        expect(confirmButton).toBeDisabled();

        fireEvent.change(
            screen.getByPlaceholderText('Précisez la décision, les constatations ou le motif de classement...'),
            { target: { value: 'Collusion confirmée par recoupement téléphonique.' } },
        );
        expect(confirmButton).not.toBeDisabled();

        fireEvent.click(confirmButton);

        expect(routerPost).toHaveBeenCalledWith(
            '/admin/fraud-alerts/9/confirm',
            { notes: 'Collusion confirmée par recoupement téléphonique.' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});
