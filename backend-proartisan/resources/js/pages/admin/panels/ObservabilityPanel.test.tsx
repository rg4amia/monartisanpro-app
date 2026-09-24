import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({
    router: { post: vi.fn() },
}));

import type { ObservabilitySnapshot } from '../shared';
import { ObservabilityPanel } from './ObservabilityPanel';

function makeSnapshot(overrides: Partial<ObservabilitySnapshot> = {}): ObservabilitySnapshot {
    return {
        queue: { pending: 0, failed: 0, oldest_pending_minutes: 0, recent: [] },
        payments: { failed_24h: 0, failed_total: 0, recent: [] },
        fraud: {
            gps_attempts_7d: 0,
            gps_attempts_total: 0,
            unread_alerts: 0,
            alerts_list: [],
            recent: [],
        },
        referent: { blocked: 0, threshold: 2000000, recent: [] },
        generated_at: '2026-02-01T09:00:00Z',
        ...overrides,
    } as ObservabilitySnapshot;
}

describe('ObservabilityPanel', () => {
    it('affiche les 4 signaux critiques agrégés', () => {
        render(
            <ObservabilityPanel
                snapshot={makeSnapshot({
                    queue: { pending: 2, failed: 5, oldest_pending_minutes: 12, recent: [] },
                    payments: { failed_24h: 3, failed_total: 10, recent: [] },
                })}
                canManage
                actionLoading={false}
                onRetryJobs={vi.fn()}
                onFlushJobs={vi.fn()}
            />,
        );

        expect(screen.getByText('5')).toBeInTheDocument();
        expect(screen.getByText('3')).toBeInTheDocument();
    });

    it('affiche un état sain quand aucun job n\'est en échec', () => {
        render(
            <ObservabilityPanel
                snapshot={makeSnapshot()}
                canManage
                actionLoading={false}
                onRetryJobs={vi.fn()}
                onFlushJobs={vi.fn()}
            />,
        );

        expect(screen.getByText('File saine')).toBeInTheDocument();
        expect(screen.getByText('Flux sain')).toBeInTheDocument();
        expect(screen.getByText('Rien à valider')).toBeInTheDocument();
    });

    it('désactive les actions de file quand aucun job n\'a échoué', () => {
        render(
            <ObservabilityPanel
                snapshot={makeSnapshot()}
                canManage
                actionLoading={false}
                onRetryJobs={vi.fn()}
                onFlushJobs={vi.fn()}
            />,
        );

        expect(screen.getByRole('button', { name: 'Relancer tout' })).toBeDisabled();
        expect(screen.getByRole('button', { name: 'Purger' })).toBeDisabled();
    });

    it('déclenche onRetryJobs et onFlushJobs quand des jobs ont échoué', () => {
        const onRetryJobs = vi.fn();
        const onFlushJobs = vi.fn();
        render(
            <ObservabilityPanel
                snapshot={makeSnapshot({
                    queue: { pending: 1, failed: 4, oldest_pending_minutes: 30, recent: [] },
                })}
                canManage
                actionLoading={false}
                onRetryJobs={onRetryJobs}
                onFlushJobs={onFlushJobs}
            />,
        );

        fireEvent.click(screen.getByRole('button', { name: 'Relancer tout' }));
        fireEvent.click(screen.getByRole('button', { name: 'Purger' }));

        expect(onRetryJobs).toHaveBeenCalledTimes(1);
        expect(onFlushJobs).toHaveBeenCalledTimes(1);
    });

    it('masque les actions de gestion de file sans la capacité admin.observability.manage', () => {
        render(
            <ObservabilityPanel
                snapshot={makeSnapshot({
                    queue: { pending: 1, failed: 4, oldest_pending_minutes: 30, recent: [] },
                })}
                canManage={false}
                actionLoading={false}
                onRetryJobs={vi.fn()}
                onFlushJobs={vi.fn()}
            />,
        );

        expect(screen.queryByRole('button', { name: 'Relancer tout' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Purger' })).not.toBeInTheDocument();
    });

    it('liste les jobs en échec, transactions KO et missions bloquées au seuil Référent', () => {
        render(
            <ObservabilityPanel
                snapshot={makeSnapshot({
                    queue: {
                        pending: 1,
                        failed: 1,
                        oldest_pending_minutes: 5,
                        recent: [{ id: 1, uuid: 'u1', queue: 'default', exception: 'TimeoutException', failed_at: '2026-02-01T08:00:00Z' }],
                    },
                    payments: {
                        failed_24h: 1,
                        failed_total: 1,
                        recent: [
                            {
                                id: 3,
                                provider: 'wave',
                                type: 'acompte',
                                montant: 50000,
                                reference: 'WV-1',
                                error: 'Webhook non reçu',
                                created_at: '2026-02-01T08:10:00Z',
                            },
                        ],
                    },
                    referent: {
                        blocked: 1,
                        threshold: 2000000,
                        recent: [
                            {
                                id: 77,
                                client: 'Awa Traoré',
                                artisan: 'Koffi N\'Guessan',
                                status: 'pending_approval',
                                montant_total: 2500000,
                                created_at: '2026-02-01T07:00:00Z',
                            },
                        ],
                    },
                })}
                canManage
                actionLoading={false}
                onRetryJobs={vi.fn()}
                onFlushJobs={vi.fn()}
            />,
        );

        expect(screen.getByText('TimeoutException')).toBeInTheDocument();
        expect(screen.getByText('Webhook non reçu')).toBeInTheDocument();
        expect(screen.getByText('Mission #77')).toBeInTheDocument();
    });
});
