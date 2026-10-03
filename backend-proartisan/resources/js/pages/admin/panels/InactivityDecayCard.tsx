// Dégradation d'inactivité du Score ProsArtisan (Chantier 26) : état, artisans
// visés, points déjà retirés, activation et désactivation confirmées.
// Affichée dans le sous-onglet « Scores ProsArtisan Artisans ».

import { router } from '@inertiajs/react';
import { useState } from 'react';

import { numberFormat, SectionTitle, shortDate, Surface, useConfirm } from '../shared';
import type { InactivityDecayOverview } from '../shared';

interface InactivityDecayCardProps {
    overview?: InactivityDecayOverview | null;
    canManage: boolean;
}

const title = "Dégradation d'inactivité";

export function InactivityDecayCard({ overview, canManage }: InactivityDecayCardProps) {
    const { confirm, dialog } = useConfirm();
    const [saving, setSaving] = useState(false);

    if (!overview) {
        return (
            <Surface className="rounded-[32px] p-5 lg:p-6">
                <SectionTitle title={title} description="Retrait de points aux artisans sans activité." />
                <p role="alert" className="mt-4 text-xs text-rose-700">
                    L'état de la dégradation d'inactivité n'a pas pu être chargé. Rechargez la page.
                </p>
            </Surface>
        );
    }

    const artisans = `${numberFormat.format(overview.concerned)} artisan${overview.concerned > 1 ? 's' : ''}`;

    const toggle = async () => {
        const enable = !overview.enabled;
        const ok = await confirm(
            enable
                ? {
                      title: "Activer la dégradation d'inactivité ?",
                      message: `Chaque artisan sans activité depuis ${overview.threshold_days} jours perdra ${overview.points} points par semaine, dès le prochain passage quotidien. ${artisans} concerné${overview.concerned > 1 ? 's' : ''} aujourd'hui. Les points retirés ne sont pas rendus à la désactivation.`,
                      confirmLabel: 'Activer',
                      tone: 'danger',
                  }
                : {
                      title: "Désactiver la dégradation d'inactivité ?",
                      message: "Plus aucun point ne sera retiré pour inactivité. Les points déjà retirés restent retirés.",
                      confirmLabel: 'Désactiver',
                      tone: 'primary',
                  },
        );
        if (!ok) return;

        setSaving(true);
        router.put(
            '/admin/evaluations/inactivity-decay',
            { enabled: enable },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    return (
        <Surface className="rounded-[32px] p-5 lg:p-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <SectionTitle
                    title={title}
                    description={`Un artisan sans étape validée ni mouvement de mission depuis ${overview.threshold_days} jours perd ${overview.points} points par semaine. Les scores gelés ou nuls ne sont pas touchés.`}
                />
                <span
                    className={
                        overview.enabled
                            ? 'rounded-full border border-green-300 bg-green-50 px-3 py-1 text-xs font-semibold text-green-700'
                            : 'rounded-full border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-1 text-xs font-semibold text-[var(--admin-text-soft)]'
                    }
                >
                    {overview.enabled ? 'Activée' : 'Désactivée'}
                </span>
            </div>

            <div className="mt-4 grid gap-3 sm:grid-cols-3">
                <div className="rounded-2xl border border-[var(--admin-border)] bg-[var(--admin-panel)] p-3 text-xs">
                    <p className="text-[var(--admin-muted)]">{overview.enabled ? 'Artisans inactifs visés' : 'Artisans inactifs qui seraient visés'}</p>
                    <p className="text-lg font-bold text-[var(--admin-text)]">{numberFormat.format(overview.concerned)}</p>
                </div>
                <div className="rounded-2xl border border-[var(--admin-border)] bg-[var(--admin-panel)] p-3 text-xs">
                    <p className="text-[var(--admin-muted)]">Pénalités sur 30 jours</p>
                    <p className="text-lg font-bold text-[var(--admin-text)]">{numberFormat.format(overview.penalties_30d)}</p>
                </div>
                <div className="rounded-2xl border border-[var(--admin-border)] bg-[var(--admin-panel)] p-3 text-xs">
                    <p className="text-[var(--admin-muted)]">Points retirés sur 30 jours</p>
                    <p className="text-lg font-bold text-[var(--admin-text)]">{numberFormat.format(overview.points_removed_30d)}</p>
                </div>
            </div>

            <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                <p className="text-xs text-[var(--admin-muted)]">
                    {overview.source === 'reglage'
                        ? `Réglé depuis le backoffice${overview.updated_at ? ` le ${shortDate(overview.updated_at)}` : ''}.`
                        : "Jamais réglée depuis le backoffice : l'état vient de la configuration du serveur."}
                </p>
                {canManage ? (
                    <button
                        type="button"
                        onClick={toggle}
                        disabled={saving}
                        className={`admin-button ${overview.enabled ? 'admin-button--ghost' : 'admin-button--danger'} disabled:opacity-50`}
                    >
                        {overview.enabled ? 'Désactiver la dégradation' : 'Activer la dégradation'}
                    </button>
                ) : (
                    <p className="text-xs italic text-[var(--admin-muted)]">La capacité « Paramètres » est requise pour modifier ce réglage.</p>
                )}
            </div>
            {dialog}
        </Surface>
    );
}
