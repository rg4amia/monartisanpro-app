// Onglet « Tableau de bord » du backoffice — extrait de console.tsx (Chantier C2).

import { Link } from '@inertiajs/react';

import { cn } from '@/lib/utils';

import {
    ActivityToneIcon,
    AvatarBubble,
    DualLineChart,
    EmptyState,
    MetricCard,
    money,
    RoleBadge,
    SectionTitle,
    shortDate,
    Surface,
    tabRoutes,
    toneBadgeClasses,
    toneIconClasses,
    VolumeBarChart,
} from '../shared';
import type { AdminUser, ChartPoint, KycUser, Tone } from '../shared';

interface SummaryCard {
    description: string;
    title: string;
    tone: Tone;
    trend: string;
    value: string;
}

interface ActivityItem {
    id: string;
    date: string;
    title: string;
    detail: string;
    tone: Tone;
}

interface DashboardPanelProps {
    summaryCards: SummaryCard[];
    acompteTrend: ChartPoint[];
    releaseTrend: ChartPoint[];
    activityTrend: ChartPoint[];
    urgentKyc: KycUser[];
    recentActivity: ActivityItem[];
    escrowAmount: number;
    releasedAmount: number;
    topArtisans: AdminUser[];
}

export function DashboardPanel({
    summaryCards,
    acompteTrend,
    releaseTrend,
    activityTrend,
    urgentKyc,
    recentActivity,
    escrowAmount,
    releasedAmount,
    topArtisans,
}: DashboardPanelProps) {
    return (
        <section className="mt-5 space-y-5">
            <div className="grid gap-4 xl:grid-cols-4">
                {summaryCards.map((card) => (
                    <MetricCard
                        key={card.title}
                        description={card.description}
                        tone={card.tone}
                        trend={card.trend}
                        value={card.value}
                    >
                        {card.title}
                    </MetricCard>
                ))}
            </div>

            <div className="grid gap-5 xl:grid-cols-[1.1fr_0.9fr]">
                <Surface className="rounded-[32px] p-5 lg:p-6">
                    <SectionTitle
                        description="Acomptes encaissés versus fonds déjà libérés aux artisans et fournisseurs."
                        title="Flux financiers / 7 jours"
                    />
                    <DualLineChart
                        series={[
                            { color: '#dfab4e', label: 'Acomptes confirmés', points: acompteTrend },
                            { color: '#e16c5f', label: 'Fonds libérés', points: releaseTrend },
                        ]}
                    />
                </Surface>

                <Surface className="rounded-[32px] p-5 lg:p-6">
                    <SectionTitle
                        description="Charge combinée KYC, litiges et transactions à traiter."
                        title="Volume opérationnel / 15 jours"
                    />
                    <VolumeBarChart bars={activityTrend} color="#dfab4e" />
                </Surface>
            </div>

            <div className="grid gap-5 xl:grid-cols-[0.95fr_1.05fr]">
                <Surface className="rounded-[32px] p-5 lg:p-6">
                    <div className="flex items-center justify-between gap-3">
                        <SectionTitle
                            description="Dossiers les plus anciens ou sensibles à prendre en main."
                            title="File prioritaire"
                        />
                        <Link href={tabRoutes.kyc} className="text-sm font-medium text-[#8a6b3d] transition hover:text-[#6f531f]">
                            Voir tout
                        </Link>
                    </div>

                    <div className="mt-5 space-y-3">
                        {urgentKyc.length === 0 ? (
                            <EmptyState description="Aucun dossier urgent en attente de revue." title="File KYC vide" />
                        ) : (
                            urgentKyc.map((user) => (
                                <div key={user.id} className="rounded-[24px] border border-[var(--admin-border)] bg-white/60 p-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="flex min-w-0 items-start gap-3">
                                            <AvatarBubble label={user.name} />
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-semibold text-[var(--admin-text)]">{user.name}</p>
                                                <p className="text-xs text-[var(--admin-muted)]">{user.phone}</p>
                                            </div>
                                        </div>
                                        <RoleBadge role={user.role} />
                                    </div>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {user.kyc_documents.map((document) => (
                                            <a
                                                key={document.id}
                                                href={document.file_url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="rounded-full border border-[#e6d3b2] px-3 py-1 text-xs font-medium text-[#8b6732] transition hover:bg-[#fbf1db]"
                                            >
                                                {document.type.toUpperCase()}
                                            </a>
                                        ))}
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </Surface>

                <Surface className="rounded-[32px] p-5 lg:p-6">
                    <div className="flex items-center justify-between gap-3">
                        <SectionTitle
                            description="Signaux récents sur les paiements, validations et arbitrages."
                            title="Activité récente"
                        />
                        <Link href={tabRoutes.transactions} className="text-sm font-medium text-[#8a6b3d] transition hover:text-[#6f531f]">
                            Ouvrir finance
                        </Link>
                    </div>

                    <div className="mt-5 space-y-3">
                        {recentActivity.length === 0 ? (
                            <EmptyState description="Aucune activité récente détectée." title="Journal vide" />
                        ) : (
                            recentActivity.map((activity) => (
                                <div key={activity.id} className="flex items-start gap-3 rounded-[24px] border border-[var(--admin-border)] bg-white/60 p-4">
                                    <div className={cn('mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl', toneIconClasses(activity.tone))}>
                                        <ActivityToneIcon tone={activity.tone} />
                                    </div>
                                    <div className="min-w-0">
                                        <p className="text-sm font-semibold text-[var(--admin-text)]">{activity.title}</p>
                                        <p className="mt-1 text-sm text-[var(--admin-text-soft)]">{activity.detail}</p>
                                        <p className="mt-1 text-xs text-[var(--admin-muted)]">{shortDate(activity.date)}</p>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </Surface>
            </div>

            <div className="grid gap-5 xl:grid-cols-3">
                <Surface className="rounded-[32px] p-5 lg:p-6">
                    <SectionTitle description="Vue sur les fonds sous séquestre actuellement confirmés." title="Séquestre" />
                    <p className="mt-5 text-4xl font-semibold text-[var(--admin-text)]">{money(escrowAmount)}</p>
                    <p className="mt-2 text-sm text-[var(--admin-text-soft)]">Acomptes clients confirmés et immobilisés pour les missions.</p>
                </Surface>

                <Surface className="rounded-[32px] p-5 lg:p-6">
                    <SectionTitle description="Montants déjà distribués selon OTP et règlements fournisseurs." title="Fonds libérés" />
                    <p className="mt-5 text-4xl font-semibold text-[var(--admin-text)]">{money(releasedAmount)}</p>
                    <p className="mt-2 text-sm text-[var(--admin-text-soft)]">Jalons validés et règlements matériaux exécutés.</p>
                </Surface>

                <Surface className="rounded-[32px] p-5 lg:p-6">
                    <SectionTitle description="Comptes artisans les plus solides pour le matching et le micro-crédit." title="Top Score ProsArtisan" />
                    <div className="mt-5 space-y-3">
                        {topArtisans.length === 0 ? (
                            <EmptyState description="Aucun artisan scoré pour l’instant." title="Pas de classement" />
                        ) : (
                            topArtisans.map((artisan) => (
                                <div key={artisan.id} className="flex items-center justify-between rounded-[22px] border border-[var(--admin-border)] bg-white/60 px-4 py-3">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold text-[var(--admin-text)]">{artisan.name}</p>
                                        <p className="text-xs text-[var(--admin-muted)]">{artisan.phone}</p>
                                    </div>
                                    <span className={cn('rounded-full border px-3 py-1 text-xs font-semibold', toneBadgeClasses('blue'))}>
                                        {artisan.score_prosartisan}/1000
                                    </span>
                                </div>
                            ))
                        )}
                    </div>
                </Surface>
            </div>
        </section>
    );
}
