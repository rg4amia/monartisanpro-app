// Historique des états des missions (Chantier 19) : frise d'une mission dans sa
// fiche, contrôle et reconstitution des historiques incomplets dans l'onglet Missions.

import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { redirectIfSessionExpired } from '../hooks/useIdleLogout';
import { numberFormat, shortDate } from '../shared';
import { useConfirm } from '../shared/ConfirmDialog';

interface HistoryLine {
    id: number;
    from_state_label: string;
    to_state_label: string;
    user: { id: number; name: string; role: string } | null;
    reason: string | null;
    reconstituted: boolean;
    unknown_date: boolean;
    transitioned_at: string;
}

interface HistoryReport {
    missions: number;
    incomplete: number;
    lines: number;
}

const roleLabels: Record<string, string> = {
    client: 'Client',
    artisan: 'Artisan',
    admin: 'Administrateur',
    referent: 'Référent',
    fournisseur: 'Fournisseur',
    livreur: 'Livreur',
};

const JSON_HEADERS = { Accept: 'application/json' };

/** Frise des changements d'état d'une mission, du plus récent au plus ancien. */
export function MissionStateHistory({ missionId }: { missionId: number }) {
    const [lines, setLines] = useState<HistoryLine[] | null>(null);
    const [error, setError] = useState(false);

    useEffect(() => {
        let active = true;

        fetch(`/admin/missions/${missionId}/historique`, { headers: JSON_HEADERS, credentials: 'same-origin' })
            .then((response) => {
                if (redirectIfSessionExpired(response)) return null;
                if (!response.ok) throw new Error(String(response.status));
                return response.json();
            })
            .then((data: { transitions?: HistoryLine[] } | null) => {
                if (active && data) setLines(data.transitions ?? []);
            })
            .catch(() => {
                if (active) setError(true);
            });

        return () => {
            active = false;
        };
    }, [missionId]);

    return (
        <div className="space-y-2.5">
            <h3 className="text-sm font-bold text-[var(--admin-text)] uppercase tracking-wider">Historique des états</h3>

            {error ? (
                <p role="alert" className="text-xs text-rose-700">
                    L'historique n'a pas pu être chargé. Fermez la fiche puis rouvrez-la.
                </p>
            ) : lines === null ? (
                <p className="text-xs text-[var(--admin-muted)] italic">Chargement de l'historique…</p>
            ) : lines.length === 0 ? (
                <p className="text-xs text-[var(--admin-muted)] italic">
                    Aucun changement d'état enregistré : la mission est dans son état d'origine.
                </p>
            ) : (
                <ol className="space-y-2">
                    {lines.map((line) => (
                        <li key={line.id} className="rounded-2xl border border-[var(--admin-border)] bg-[var(--admin-panel)] p-3 text-xs">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <p className="font-semibold text-[var(--admin-text)]">
                                    {line.from_state_label} → {line.to_state_label}
                                </p>
                                <span className="text-[var(--admin-muted)]">
                                    {line.unknown_date ? 'Date non conservée' : shortDate(line.transitioned_at)}
                                </span>
                            </div>
                            <p className="mt-1 text-[var(--admin-text-soft)]">
                                {line.user
                                    ? `${line.user.name} (${roleLabels[line.user.role] ?? line.user.role})`
                                    : line.reconstituted
                                        ? 'Acteur non conservé'
                                        : 'Automatique'}
                                {line.reason ? ` — ${line.reason}` : ''}
                            </p>
                            {line.reconstituted && (
                                <span
                                    className="mt-1.5 inline-flex rounded-full border border-amber-300 bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-800"
                                    title="Ligne déduite après coup des faits enregistrés (paiement, étape, litige)"
                                >
                                    Reconstitué
                                </span>
                            )}
                        </li>
                    ))}
                </ol>
            )}
        </div>
    );
}

/** Contrôle des historiques incomplets et reconstitution, à la demande. */
export function MissionHistoryControl({ canRebuild }: { canRebuild: boolean }) {
    const { confirm, dialog } = useConfirm();
    const [report, setReport] = useState<HistoryReport | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(false);

    const check = () => {
        setLoading(true);
        setError(false);

        fetch('/admin/missions/historique/controle', { headers: JSON_HEADERS, credentials: 'same-origin' })
            .then((response) => {
                if (redirectIfSessionExpired(response)) return null;
                if (!response.ok) throw new Error(String(response.status));
                return response.json();
            })
            .then((data: HistoryReport | null) => {
                if (data) setReport(data);
            })
            .catch(() => setError(true))
            .finally(() => setLoading(false));
    };

    const rebuild = async () => {
        if (!report) return;

        const ok = await confirm({
            title: "Reconstituer l'historique ?",
            message: `${numberFormat.format(report.lines)} ligne(s) seront ajoutées à ${numberFormat.format(report.incomplete)} mission(s), d'après les paiements, étapes et litiges enregistrés. Elles seront marquées « Reconstitué ». Aucune ligne existante n'est modifiée.`,
            confirmLabel: 'Reconstituer',
            tone: 'primary',
        });
        if (!ok) return;

        router.post('/admin/missions/historique/reconstituer', {}, {
            preserveScroll: true,
            onSuccess: () => setReport(null),
        });
    };

    return (
        <div className="rounded-[24px] border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="text-sm font-bold text-[var(--admin-text)]">Historique des états</p>
                    <p className="text-xs text-[var(--admin-text-soft)]">
                        L'historique de chaque mission se consulte dans sa fiche. Ce contrôle recherche les missions financées ou
                        clôturées dont l'historique est incomplet.
                    </p>
                </div>
                <button
                    type="button"
                    onClick={check}
                    disabled={loading}
                    className="rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel)] px-3 py-2 text-xs font-bold text-[var(--admin-text)] transition hover:bg-black/5 disabled:opacity-50"
                >
                    {loading ? 'Contrôle en cours…' : "Contrôler l'historique"}
                </button>
            </div>

            {error && (
                <p role="alert" className="mt-3 text-xs text-rose-700">
                    Le contrôle n'a pas abouti. Réessayez.
                </p>
            )}

            {report && (
                <div className="mt-3 flex flex-wrap items-center justify-between gap-3 text-xs text-[var(--admin-text-soft)]">
                    <p role="status">
                        {report.incomplete === 0
                            ? `Historique complet sur les ${numberFormat.format(report.missions)} mission(s) contrôlée(s).`
                            : `${numberFormat.format(report.incomplete)} mission(s) sur ${numberFormat.format(report.missions)} ont un historique incomplet (${numberFormat.format(report.lines)} ligne(s) à reconstituer).`}
                    </p>
                    {report.incomplete > 0 && canRebuild && (
                        <button
                            type="button"
                            onClick={rebuild}
                            className="rounded-xl bg-[#1e293b] px-3 py-2 text-xs font-bold text-white transition hover:bg-[#0f172a]"
                        >
                            Reconstituer l'historique
                        </button>
                    )}
                </div>
            )}

            {dialog}
        </div>
    );
}
