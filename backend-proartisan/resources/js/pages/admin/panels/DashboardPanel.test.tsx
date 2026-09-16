import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { DashboardPanel } from './DashboardPanel';
import type { AdminUser, ChartPoint, FaqStats, KycUser, WhatsappClickStats } from '../shared';

const point = (label: string, value: number): ChartPoint => ({ label, value });

function makeKycUser(overrides: Partial<KycUser> = {}): KycUser {
    return {
        id: 1,
        name: 'Awa Traoré',
        phone: '+2250700000001',
        role: 'client',
        created_at: '2026-02-01T00:00:00Z',
        kyc_documents: [{ id: 1, type: 'cni', file_url: 'https://example.com/cni.jpg' } as any],
        ...overrides,
    };
}

function makeArtisan(overrides: Partial<AdminUser> = {}): AdminUser {
    return {
        id: 3,
        name: "Koffi N'Guessan",
        phone: '+2250700000002',
        score_prosartisan: 820,
        ...overrides,
    } as AdminUser;
}

function renderPanel(overrides: Partial<React.ComponentProps<typeof DashboardPanel>> = {}) {
    const whatsappClickStats: WhatsappClickStats = { total: 100, today: 5, last_7_days: 30 };
    const faqStats: FaqStats = { total: 12, actives: 10, roles_covered: 3 };

    const props: React.ComponentProps<typeof DashboardPanel> = {
        summaryCards: [
            { title: 'Missions actives', description: 'En cours', tone: 'green', trend: '+3', value: '12' },
        ],
        acompteTrend: [point('Lun', 100000)],
        releaseTrend: [point('Lun', 50000)],
        activityTrend: [point('J1', 5)],
        urgentKyc: [makeKycUser()],
        recentActivity: [
            { id: 'a1', date: '2026-02-01T09:00:00Z', title: 'Paiement confirmé', detail: 'Wave — 50 000 FCFA', tone: 'green' },
        ],
        escrowAmount: 2500000,
        releasedAmount: 1200000,
        topArtisans: [makeArtisan()],
        whatsappClickStats,
        faqStats,
        ...overrides,
    };
    render(<DashboardPanel {...props} />);
    return props;
}

describe('DashboardPanel', () => {
    it('affiche les cartes de synthèse', () => {
        renderPanel();
        expect(screen.getByText('Missions actives')).toBeInTheDocument();
        expect(screen.getByText('12')).toBeInTheDocument();
    });

    it('affiche la file KYC prioritaire avec les pièces à instruire', () => {
        renderPanel();
        expect(screen.getByText('Awa Traoré')).toBeInTheDocument();
        expect(screen.getByText('CNI')).toBeInTheDocument();
    });

    it('affiche un état vide sans dossier KYC urgent', () => {
        renderPanel({ urgentKyc: [] });
        expect(screen.getByText('File KYC vide')).toBeInTheDocument();
    });

    it('affiche l\'activité récente', () => {
        renderPanel();
        expect(screen.getByText('Paiement confirmé')).toBeInTheDocument();
        expect(screen.getByText('Wave — 50 000 FCFA')).toBeInTheDocument();
    });

    it('affiche un état vide sans activité récente', () => {
        renderPanel({ recentActivity: [] });
        expect(screen.getByText('Journal vide')).toBeInTheDocument();
    });

    it('affiche les montants de séquestre et de fonds libérés', () => {
        renderPanel({ escrowAmount: 2500000, releasedAmount: 1200000 });
        expect(screen.getByText(/2\s?500\s?000/)).toBeInTheDocument();
        expect(screen.getByText(/1\s?200\s?000/)).toBeInTheDocument();
    });

    it('affiche le classement des artisans par Score ProsArtisan', () => {
        renderPanel();
        expect(screen.getByText("Koffi N'Guessan")).toBeInTheDocument();
        expect(screen.getByText('820/1000')).toBeInTheDocument();
    });

    it('affiche un état vide sans artisan classé', () => {
        renderPanel({ topArtisans: [] });
        expect(screen.getByText('Pas de classement')).toBeInTheDocument();
    });

    it('affiche les KPI WhatsApp et FAQ', () => {
        renderPanel();
        expect(screen.getByText('30')).toBeInTheDocument();
        expect(screen.getByText('10')).toBeInTheDocument();
        expect(screen.getByText(/3\/4 espaces couverts/)).toBeInTheDocument();
    });
});
