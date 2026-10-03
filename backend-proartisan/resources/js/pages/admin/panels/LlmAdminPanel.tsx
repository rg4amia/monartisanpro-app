// Onglet « Administration LLM » du backoffice (Chantier 23) : base de
// connaissances de l'Assistant IA. Un document de référence est importé, l'IA en
// rédige des fiches, un administrateur les relit et les publie.

import axios from 'axios';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { ChangeEvent, FormEvent } from 'react';

import { redirectIfSessionExpired } from '../hooks/useIdleLogout';
import { actionButtonClass, DataTable, EmptyState, MetricCard, SectionTitle, Surface, toneBadgeClasses, useConfirm } from '../shared';
import type { Tone } from '../shared';
import { cleanSheet, emptySheet, KnowledgeSheetEditor } from './KnowledgeSheetEditor';
import type { KnowledgeSheet } from './KnowledgeSheetEditor';

const API = '/admin/api/llm';
const ACCEPTED = '.pdf,.txt,.md,.png,.jpg,.jpeg,.webp';
const POLL_INTERVAL_MS = 4000;

interface ImportRow {
    id: string;
    filename: string;
    file_size: number;
    imported_at: string;
    status: 'en_attente' | 'en_cours' | 'traite' | 'echec';
    status_label: string;
    error_message: string | null;
    sheets_count: number;
    has_file: boolean;
}

interface StagingRow {
    id: string;
    source: string;
    status: 'PENDING' | 'APPROVED' | 'REJECTED' | 'WITHDRAWN';
    status_label: string;
    origin: 'ia' | 'manuelle';
    origin_label: string;
    reviewer_notes: string | null;
    generated_json: KnowledgeSheet;
}

interface ChatLine {
    sender: 'vous' | 'assistant';
    text: string;
    sources?: Array<{ id: string | null; title: string }>;
}

const importTones: Record<ImportRow['status'], Tone> = { en_attente: 'slate', en_cours: 'blue', traite: 'green', echec: 'rose' };
const sheetTones: Record<StagingRow['status'], Tone> = { PENDING: 'amber', APPROVED: 'green', REJECTED: 'rose', WITHDRAWN: 'slate' };

function Badge({ tone, children }: { tone: Tone; children: string }) {
    return <span className={`inline-flex rounded-full border px-2.5 py-0.5 text-[11px] font-semibold ${toneBadgeClasses(tone)}`}>{children}</span>;
}

function fileSize(bytes: number): string {
    return bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} Mo` : `${Math.max(1, Math.round(bytes / 1024))} Ko`;
}

/** Message d'erreur du serveur (refus de validation compris), en français. */
function errorMessage(error: unknown, fallback: string): string {
    const response = (error as { response?: { status?: number; data?: { message?: string; errors?: Record<string, string[]> } } })?.response;
    if (response?.status === 401 && redirectIfSessionExpired({ status: 401 })) return 'Votre session a expiré.';
    if (response?.status === 403) return "Vous n'avez pas le droit d'effectuer cette action.";
    const first = response?.data?.errors ? Object.values(response.data.errors)[0]?.[0] : undefined;

    return first ?? (response?.status === 422 ? response?.data?.message : undefined) ?? fallback;
}

export default function LlmAdminPanel() {
    const { confirm, dialog } = useConfirm();

    const [imports, setImports] = useState<ImportRow[]>([]);
    const [staging, setStaging] = useState<StagingRow[]>([]);
    const [published, setPublished] = useState<KnowledgeSheet[]>([]);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState(false);
    const [notice, setNotice] = useState<{ tone: 'success' | 'error'; text: string } | null>(null);
    const [busy, setBusy] = useState<string | null>(null);

    // Fiche ouverte dans le formulaire : identifiant d'une fiche à relire, ou « nouvelle ».
    const [editingId, setEditingId] = useState<string | 'nouvelle' | null>(null);
    const [draft, setDraft] = useState<KnowledgeSheet>(emptySheet());

    const [chatMessage, setChatMessage] = useState('');
    const [chat, setChat] = useState<ChatLine[]>([]);
    const [chatLoading, setChatLoading] = useState(false);

    const load = useCallback(async (passive = false) => {
        // Un rafraîchissement automatique ne prolonge pas la session (Règle d'or 87).
        const config = passive ? { headers: { 'X-Admin-Passive': '1' } } : undefined;
        try {
            const [importsRes, stagingRes, publishedRes] = await Promise.all([
                axios.get<ImportRow[]>(`${API}/imports`, config),
                axios.get<StagingRow[]>(`${API}/staging`, config),
                axios.get<KnowledgeSheet[]>(`${API}/production`, config),
            ]);
            setImports(importsRes.data);
            setStaging(stagingRes.data);
            setPublished(publishedRes.data);
            setLoadError(false);
        } catch (error) {
            errorMessage(error, '');
            if (!passive) setLoadError(true);
        } finally {
            setLoading(false);
        }
    }, []);

    const didInit = useRef(false);
    useEffect(() => {
        if (didInit.current) return;
        didInit.current = true;
        void load();
    }, [load]);

    // Tant qu'une génération est en cours, l'écran suit son avancement.
    const generating = imports.some((row) => row.status === 'en_cours');
    useEffect(() => {
        if (!generating) return;
        const timer = window.setInterval(() => void load(true), POLL_INTERVAL_MS);

        return () => window.clearInterval(timer);
    }, [generating, load]);

    const run = async (key: string, action: () => Promise<string>, fallback: string) => {
        setBusy(key);
        setNotice(null);
        try {
            setNotice({ tone: 'success', text: await action() });
            await load();
        } catch (error) {
            setNotice({ tone: 'error', text: errorMessage(error, fallback) });
        } finally {
            setBusy(null);
        }
    };

    // ── Documents ────────────────────────────────────────────────────────────

    const upload = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) return;

        const form = new FormData();
        form.append('document', file);

        void run(
            'import',
            async () => {
                await axios.post(`${API}/imports`, form);

                return `Document « ${file.name} » importé. Lancez la génération des fiches.`;
            },
            "Le document n'a pas pu être importé.",
        );
    };

    const generate = (row: ImportRow) =>
        run(
            `generer-${row.id}`,
            async () => {
                await axios.post(`${API}/imports/${row.id}/generate`);

                return `Génération des fiches lancée pour « ${row.filename} ». Elle peut durer une à deux minutes.`;
            },
            "La génération n'a pas pu être lancée.",
        );

    const deleteImport = async (row: ImportRow) => {
        const ok = await confirm({
            title: 'Supprimer ce document ?',
            message: `« ${row.filename} » et son fichier seront supprimés. Les fiches déjà rédigées à partir de ce document sont conservées.`,
            confirmLabel: 'Supprimer',
            tone: 'danger',
        });
        if (!ok) return;

        void run(
            `supprimer-${row.id}`,
            async () => {
                await axios.delete(`${API}/imports/${row.id}`);

                return 'Document supprimé.';
            },
            "Le document n'a pas pu être supprimé.",
        );
    };

    const clearImports = async () => {
        const ok = await confirm({
            title: 'Supprimer tous les documents ?',
            message: 'Tous les documents importés et leurs fichiers seront supprimés. Les fiches déjà rédigées sont conservées.',
            confirmLabel: 'Tout supprimer',
            tone: 'danger',
        });
        if (!ok) return;

        void run(
            'vider',
            async () => {
                await axios.delete(`${API}/imports`);

                return 'Documents supprimés.';
            },
            "Les documents n'ont pas pu être supprimés.",
        );
    };

    // ── Fiches ───────────────────────────────────────────────────────────────

    const openSheet = (row: StagingRow) => {
        setEditingId(row.id);
        setDraft(row.generated_json);
        setNotice(null);
    };

    const openNew = () => {
        setEditingId('nouvelle');
        setDraft(emptySheet());
        setNotice(null);
    };

    const editing = editingId && editingId !== 'nouvelle' ? (staging.find((row) => row.id === editingId) ?? null) : null;

    const save = async (): Promise<string | null> => {
        const payload = cleanSheet(draft);
        if (editingId === 'nouvelle') {
            const response = await axios.post<{ id: string }>(`${API}/staging`, payload);
            setEditingId(response.data.id);

            return response.data.id;
        }
        if (!editingId) return null;
        await axios.put(`${API}/staging/${editingId}`, payload);

        return editingId;
    };

    const saveSheet = () =>
        run(
            'enregistrer',
            async () => {
                await save();

                return 'Fiche enregistrée. Elle reste à relire tant que vous ne l’approuvez pas.';
            },
            "La fiche n'a pas pu être enregistrée.",
        );

    const approveSheet = async () => {
        const ok = await confirm({
            title: 'Publier cette fiche ?',
            message:
                "Une fois publiée, l'Assistant IA la transmet aux artisans comme référence validée. Vérifiez chaque dosage par rapport au passage du document.",
            confirmLabel: 'Approuver et publier',
        });
        if (!ok) return;

        void run(
            'approuver',
            async () => {
                // Les corrections en cours sont enregistrées avant la publication.
                const id = await save();
                await axios.post(`${API}/staging/${id}/approve`);
                setEditingId(null);

                return "Fiche publiée : l'Assistant IA peut maintenant s'en servir.";
            },
            "La fiche n'a pas pu être publiée.",
        );
    };

    const rejectSheet = async (row: StagingRow) => {
        const note = await confirm({
            title: 'Rejeter cette fiche ?',
            message: 'Une fiche rejetée ne sera jamais publiée.',
            confirmLabel: 'Rejeter',
            promptLabel: 'Motif du rejet',
            promptMinLength: 5,
            tone: 'danger',
        });
        if (typeof note !== 'string') return;

        void run(
            'rejeter',
            async () => {
                await axios.post(`${API}/staging/${row.id}/reject`, { reviewer_notes: note.trim() });
                setEditingId(null);

                return 'Fiche rejetée.';
            },
            "La fiche n'a pas pu être rejetée.",
        );
    };

    const deleteSheet = async (row: StagingRow) => {
        const ok = await confirm({
            title: 'Supprimer cette fiche ?',
            message: `« ${row.generated_json.alternative_prosartisan.titre_vulgarise || row.id} » sera supprimée définitivement.`,
            confirmLabel: 'Supprimer',
            tone: 'danger',
        });
        if (!ok) return;

        void run(
            'supprimer-fiche',
            async () => {
                await axios.delete(`${API}/staging/${row.id}`);
                if (editingId === row.id) setEditingId(null);

                return 'Fiche supprimée.';
            },
            "La fiche n'a pas pu être supprimée.",
        );
    };

    const withdraw = async (sheet: KnowledgeSheet) => {
        const reason = await confirm({
            title: 'Retirer cette fiche de la publication ?',
            message: "L'Assistant IA ne s'en servira plus. Elle retourne dans les fiches à relire, où vous pourrez la corriger.",
            confirmLabel: 'Retirer',
            promptLabel: 'Motif du retrait',
            promptMinLength: 5,
            tone: 'danger',
        });
        if (typeof reason !== 'string') return;

        void run(
            'retirer',
            async () => {
                await axios.post(`${API}/production/${sheet.id}/withdraw`, { reason: reason.trim() });

                return 'Fiche retirée de la publication.';
            },
            "La fiche n'a pas pu être retirée.",
        );
    };

    // ── Essai de l'Assistant ─────────────────────────────────────────────────

    const sendChat = async (event: FormEvent) => {
        event.preventDefault();
        const text = chatMessage.trim();
        if (!text || chatLoading) return;

        setChatMessage('');
        setChat((lines) => [...lines, { sender: 'vous', text }]);
        setChatLoading(true);
        try {
            const response = await axios.post<{ response: string; sources?: ChatLine['sources'] }>(`${API}/chat`, { message: text });
            setChat((lines) => [...lines, { sender: 'assistant', text: response.data.response, sources: response.data.sources ?? [] }]);
        } catch (error) {
            const quota = (error as { response?: { status?: number } })?.response?.status === 429;
            setChat((lines) => [
                ...lines,
                {
                    sender: 'assistant',
                    text: quota ? "Quota d'interactions IA atteint. Réessayez plus tard." : errorMessage(error, "L'Assistant n'a pas répondu."),
                },
            ]);
        } finally {
            setChatLoading(false);
        }
    };

    const toReview = staging.filter((row) => row.status === 'PENDING');
    const closed = staging.filter((row) => row.status === 'REJECTED' || row.status === 'WITHDRAWN');
    const readOnly = editing !== null && editing.status === 'APPROVED';

    if (loadError) {
        return (
            <Surface className="rounded-[32px] p-5 lg:p-6">
                <SectionTitle title="Base de connaissances de l'Assistant IA" description="Documents de référence, fiches à relire et fiches publiées." />
                <p role="alert" className="mt-4 text-xs text-rose-700">
                    La base de connaissances n'a pas pu être chargée.
                </p>
                <button type="button" className={`${actionButtonClass('secondary')} mt-3`} onClick={() => void load()}>
                    Réessayer
                </button>
            </Surface>
        );
    }

    return (
        <div className="space-y-5">
            {dialog}

            <Surface className="rounded-[32px] p-5 lg:p-6">
                <SectionTitle
                    title="Base de connaissances de l'Assistant IA"
                    description="Importez un document de référence : l'IA en rédige des fiches pratiques, que vous relisez avant de les publier. Seule une fiche approuvée est transmise aux artisans."
                />

                <div className="mt-4 grid gap-4 md:grid-cols-3">
                    <MetricCard description="Documents de référence" tone="blue" trend="Importés" value={String(imports.length)}>
                        Documents
                    </MetricCard>
                    <MetricCard description="En attente de votre relecture" tone="amber" trend="À relire" value={String(toReview.length)}>
                        Fiches à relire
                    </MetricCard>
                    <MetricCard description="Utilisées par l'Assistant IA" tone="green" trend="Publiées" value={String(published.length)}>
                        Fiches publiées
                    </MetricCard>
                </div>

                {notice ? (
                    <p
                        role={notice.tone === 'error' ? 'alert' : 'status'}
                        className={`mt-4 rounded-2xl border px-4 py-3 text-xs ${notice.tone === 'error' ? 'border-rose-300 bg-rose-50 text-rose-800' : 'border-emerald-300 bg-emerald-50 text-emerald-800'}`}
                    >
                        {notice.text}
                    </p>
                ) : null}
            </Surface>

            {/* ── Documents ─────────────────────────────────────────────── */}
            <Surface className="rounded-[32px] p-5 lg:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <SectionTitle
                        title="1. Documents de référence"
                        description="Normes et guides techniques (PDF, texte ou image, 15 Mo au plus). Les fichiers ne sont accessibles qu'aux administrateurs."
                    />
                    <div className="flex flex-wrap gap-2">
                        <label className={`${actionButtonClass('success')} cursor-pointer`}>
                            {busy === 'import' ? 'Import en cours…' : 'Importer un document'}
                            <input
                                type="file"
                                className="sr-only"
                                accept={ACCEPTED}
                                aria-label="Importer un document"
                                disabled={busy !== null}
                                onChange={upload}
                            />
                        </label>
                        {imports.length > 0 ? (
                            <button type="button" className={actionButtonClass('secondary')} disabled={busy !== null} onClick={() => void clearImports()}>
                                Tout supprimer
                            </button>
                        ) : null}
                    </div>
                </div>

                <DataTable className="mt-4">
                    <thead>
                        <tr>
                            <th>Document</th>
                            <th>Importé le</th>
                            <th>État</th>
                            <th>Fiches</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {imports.length === 0 ? (
                            <tr>
                                <td colSpan={5}>
                                    <EmptyState
                                        title={loading ? 'Chargement…' : 'Aucun document importé'}
                                        description="Importez une norme ou un guide technique pour en tirer des fiches."
                                    />
                                </td>
                            </tr>
                        ) : (
                            imports.map((row) => (
                                <tr key={row.id}>
                                    <td>
                                        <p className="text-sm font-semibold text-[var(--admin-text)]">{row.filename}</p>
                                        <p className="text-xs text-[var(--admin-muted)]">{fileSize(row.file_size)}</p>
                                    </td>
                                    <td className="text-xs text-[var(--admin-muted)]">{row.imported_at}</td>
                                    <td>
                                        <Badge tone={importTones[row.status] ?? 'slate'}>{row.status_label}</Badge>
                                        {row.error_message ? <p className="mt-1 max-w-xs text-xs text-rose-700">{row.error_message}</p> : null}
                                    </td>
                                    <td className="text-sm text-[var(--admin-text)]">{row.sheets_count}</td>
                                    <td>
                                        <div className="flex flex-wrap gap-2">
                                            <button
                                                type="button"
                                                className={actionButtonClass('success')}
                                                disabled={busy !== null || row.status === 'en_cours' || !row.has_file}
                                                onClick={() => void generate(row)}
                                            >
                                                {row.status === 'en_cours'
                                                    ? 'Génération…'
                                                    : row.status === 'traite'
                                                      ? 'Générer de nouveau'
                                                      : 'Générer les fiches'}
                                            </button>
                                            {row.has_file ? (
                                                <a className={actionButtonClass('secondary')} href={`${API}/imports/${row.id}/document`}>
                                                    Télécharger
                                                </a>
                                            ) : null}
                                            <button
                                                type="button"
                                                className={actionButtonClass('danger')}
                                                disabled={busy !== null || row.status === 'en_cours'}
                                                onClick={() => void deleteImport(row)}
                                            >
                                                Supprimer
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </DataTable>
            </Surface>

            {/* ── Fiches à relire ───────────────────────────────────────── */}
            <Surface className="rounded-[32px] p-5 lg:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <SectionTitle
                        title="2. Fiches à relire"
                        description="Une fiche rédigée par l'IA peut contenir une erreur : comparez chaque valeur au passage du document avant d'approuver."
                    />
                    <button type="button" className={actionButtonClass('secondary')} disabled={busy !== null} onClick={openNew}>
                        Nouvelle fiche manuelle
                    </button>
                </div>

                <DataTable className="mt-4">
                    <thead>
                        <tr>
                            <th>Fiche</th>
                            <th>Origine</th>
                            <th>État</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {toReview.length + closed.length === 0 ? (
                            <tr>
                                <td colSpan={4}>
                                    <EmptyState
                                        title={loading ? 'Chargement…' : 'Aucune fiche à relire'}
                                        description="Les fiches apparaissent ici après la génération à partir d'un document, ou après une saisie manuelle."
                                    />
                                </td>
                            </tr>
                        ) : (
                            [...toReview, ...closed].map((row) => (
                                <tr key={row.id}>
                                    <td>
                                        <p className="text-sm font-semibold text-[var(--admin-text)]">
                                            {row.generated_json.alternative_prosartisan.titre_vulgarise || 'Fiche sans titre'}
                                        </p>
                                        <p className="text-xs text-[var(--admin-muted)]">{row.source}</p>
                                    </td>
                                    <td>
                                        <Badge tone={row.origin === 'ia' ? 'blue' : 'slate'}>{row.origin_label}</Badge>
                                    </td>
                                    <td>
                                        <Badge tone={sheetTones[row.status] ?? 'slate'}>{row.status_label}</Badge>
                                        {row.reviewer_notes ? <p className="mt-1 max-w-xs text-xs text-[var(--admin-muted)]">{row.reviewer_notes}</p> : null}
                                    </td>
                                    <td>
                                        <div className="flex flex-wrap gap-2">
                                            <button type="button" className={actionButtonClass('secondary')} disabled={busy !== null} onClick={() => openSheet(row)}>
                                                {row.status === 'PENDING' ? 'Relire' : 'Corriger'}
                                            </button>
                                            {row.status === 'PENDING' ? (
                                                <button type="button" className={actionButtonClass('danger')} disabled={busy !== null} onClick={() => void rejectSheet(row)}>
                                                    Rejeter
                                                </button>
                                            ) : null}
                                            <button type="button" className={actionButtonClass('danger')} disabled={busy !== null} onClick={() => void deleteSheet(row)}>
                                                Supprimer
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </DataTable>

                {editingId ? (
                    <form
                        aria-label={editingId === 'nouvelle' ? 'Nouvelle fiche' : 'Relecture de la fiche'}
                        className="mt-5 rounded-2xl border border-[var(--admin-border)] p-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            void saveSheet();
                        }}
                    >
                        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <h3 className="text-sm font-bold text-[var(--admin-text)]">
                                {editingId === 'nouvelle' ? 'Nouvelle fiche manuelle' : 'Relecture de la fiche'}
                            </h3>
                            {editing?.origin === 'ia' ? <Badge tone="blue">Générée par IA — à relire</Badge> : null}
                        </div>

                        <KnowledgeSheetEditor sheet={draft} onChange={setDraft} readOnly={readOnly} />

                        <div className="mt-4 flex flex-wrap gap-2">
                            <button type="submit" className={actionButtonClass('secondary')} disabled={busy !== null}>
                                {busy === 'enregistrer' ? 'Enregistrement…' : 'Enregistrer'}
                            </button>
                            <button type="button" className={actionButtonClass('success')} disabled={busy !== null} onClick={() => void approveSheet()}>
                                Approuver et publier
                            </button>
                            <button type="button" className={actionButtonClass('secondary')} disabled={busy !== null} onClick={() => setEditingId(null)}>
                                Fermer
                            </button>
                        </div>
                    </form>
                ) : null}
            </Surface>

            {/* ── Fiches publiées ───────────────────────────────────────── */}
            <Surface className="rounded-[32px] p-5 lg:p-6">
                <SectionTitle title="3. Fiches publiées" description="Fiches que l'Assistant IA transmet aux artisans comme références validées." />

                <DataTable className="mt-4">
                    <thead>
                        <tr>
                            <th>Fiche</th>
                            <th>Mots-clés</th>
                            <th>Document d'origine</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {published.length === 0 ? (
                            <tr>
                                <td colSpan={4}>
                                    <EmptyState
                                        title={loading ? 'Chargement…' : 'Aucune fiche publiée'}
                                        description="Tant qu'aucune fiche n'est publiée, l'Assistant IA répond sans référence validée et le signale à l'artisan."
                                    />
                                </td>
                            </tr>
                        ) : (
                            published.map((sheet) => (
                                <tr key={sheet.id}>
                                    <td className="text-sm font-semibold text-[var(--admin-text)]">{sheet.alternative_prosartisan?.titre_vulgarise}</td>
                                    <td className="text-xs text-[var(--admin-muted)]">{(sheet.metadata?.tags_pathologies ?? []).join(', ')}</td>
                                    <td className="text-xs text-[var(--admin-muted)]">{sheet.norme_origine?.titre_original || sheet.norme_origine?.source || '—'}</td>
                                    <td>
                                        <button type="button" className={actionButtonClass('danger')} disabled={busy !== null} onClick={() => void withdraw(sheet)}>
                                            Retirer
                                        </button>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </DataTable>
            </Surface>

            {/* ── Essai de l'Assistant ──────────────────────────────────── */}
            <Surface className="rounded-[32px] p-5 lg:p-6">
                <SectionTitle
                    title="4. Essayer l'Assistant IA"
                    description="Posez une question comme un artisan : la réponse et les fiches citées sont celles que l'application renverrait. Chaque essai compte dans votre quota IA."
                />

                <div className="mt-4 max-h-80 space-y-3 overflow-y-auto" aria-live="polite">
                    {chat.length === 0 ? (
                        <p className="text-xs italic text-[var(--admin-muted)]">Aucun essai pour l'instant.</p>
                    ) : (
                        chat.map((line, index) => (
                            <div
                                key={index}
                                className={`rounded-2xl border px-4 py-3 text-sm ${line.sender === 'vous' ? 'border-[var(--admin-border)] bg-[var(--admin-panel-strong)]' : 'border-emerald-200 bg-emerald-50 text-emerald-950'}`}
                            >
                                <p className="mb-1 text-[11px] font-bold uppercase tracking-wider opacity-70">{line.sender === 'vous' ? 'Vous' : 'Assistant'}</p>
                                <p className="whitespace-pre-wrap">{line.text}</p>
                                {line.sender === 'assistant' ? (
                                    <p className="mt-2 text-[11px] opacity-80">
                                        {line.sources && line.sources.length > 0
                                            ? `Fiches citées : ${line.sources.map((source) => source.title).join(' · ')}`
                                            : 'Aucune fiche publiée citée.'}
                                    </p>
                                ) : null}
                            </div>
                        ))
                    )}
                </div>

                <form onSubmit={(event) => void sendChat(event)} className="mt-4 flex flex-wrap gap-2">
                    <input
                        aria-label="Question à l'Assistant"
                        className="min-w-[240px] flex-1 rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-2 text-sm text-[var(--admin-text)]"
                        placeholder="Ex. : quel dosage pour une dalle ?"
                        value={chatMessage}
                        onChange={(event) => setChatMessage(event.target.value)}
                    />
                    <button type="submit" className={actionButtonClass('success')} disabled={chatLoading || chatMessage.trim() === ''}>
                        {chatLoading ? 'Réponse en cours…' : 'Envoyer'}
                    </button>
                </form>
            </Surface>
        </div>
    );
}
