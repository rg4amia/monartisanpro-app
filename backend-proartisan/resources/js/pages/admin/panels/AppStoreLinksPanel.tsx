// Onglet « Applications mobiles » du backoffice (Chantier 16) : liens Google
// Play et App Store créés en brouillon, validés pour s'afficher sur la page
// d'accueil du site vitrine, désactivés à tout moment. Le serveur contrôle
// l'adresse et garde un seul lien publié par magasin.

import { router } from '@inertiajs/react';
import { useState } from 'react';

import { DataTable, dateTimeShort, EmptyState, SectionTitle, Surface, useConfirm } from '../shared';
import type { AppStoreLinkItem, AppStoreLinkOptions, AppStorePlatform } from '../shared';

interface AppStoreLinksPanelProps {
    links: AppStoreLinkItem[];
    options: AppStoreLinkOptions;
}

const STATUS_TONE: Record<AppStoreLinkItem['status'], string> = {
    publie: 'bg-emerald-500/15 text-emerald-600',
    brouillon: 'bg-amber-500/15 text-amber-600',
    desactive: 'bg-slate-500/15 text-[var(--admin-muted)]',
};

const URL_EXAMPLES: Record<AppStorePlatform, string> = {
    android: 'https://play.google.com/store/apps/details?id=com.prosartisan.app',
    ios: 'https://apps.apple.com/ci/app/prosartisan/id123456789',
};

const fieldClass =
    'w-full rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none';
const actionClass = 'rounded-lg border border-[var(--admin-border)] px-2 py-1 text-[11px] font-semibold text-[var(--admin-text)] hover:border-amber-500';

function firstError(errors: Record<string, string>, fallback: string): string {
    return Object.values(errors)[0] ?? fallback;
}

export function AppStoreLinksPanel({ links, options }: AppStoreLinksPanelProps) {
    const { confirm, dialog } = useConfirm();
    const platforms = Object.keys(options.platforms) as AppStorePlatform[];
    const [platform, setPlatform] = useState<AppStorePlatform>('android');
    const [url, setUrl] = useState('');
    const [createError, setCreateError] = useState<string | null>(null);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editUrl, setEditUrl] = useState('');
    const [rowError, setRowError] = useState<{ id: number; message: string } | null>(null);

    const published = (p: AppStorePlatform) => links.find((link) => link.platform === p && link.status === 'publie');

    const create = (event: React.FormEvent) => {
        event.preventDefault();
        setCreateError(null);
        router.post(
            '/admin/applications-mobiles',
            { platform, url },
            {
                preserveScroll: true,
                onSuccess: () => setUrl(''),
                onError: (errors: Record<string, string>) => setCreateError(firstError(errors, 'Création impossible.')),
            },
        );
    };

    const rowOptions = (link: AppStoreLinkItem, onSuccess?: () => void) => ({
        preserveScroll: true,
        onSuccess: () => {
            setRowError(null);
            onSuccess?.();
        },
        onError: (errors: Record<string, string>) => setRowError({ id: link.id, message: firstError(errors, 'Action impossible.') }),
    });

    const saveEdit = (link: AppStoreLinkItem) => {
        router.put(`/admin/applications-mobiles/${link.id}`, { url: editUrl }, rowOptions(link, () => setEditingId(null)));
    };

    const publish = async (link: AppStoreLinkItem) => {
        const current = published(link.platform);
        const ok = await confirm({
            title: `Valider ce lien ${link.platform_label} ?`,
            message: current
                ? `Il s'affichera sur la page d'accueil du site et remplacera le lien publié actuel (${current.url}), qui sera désactivé.`
                : `Le badge ${link.platform_label} s'affichera sur la page d'accueil du site vitrine.`,
            confirmLabel: 'Valider et publier',
        });
        if (ok) router.post(`/admin/applications-mobiles/${link.id}/valider`, {}, rowOptions(link));
    };

    const disable = async (link: AppStoreLinkItem) => {
        const ok = await confirm({
            title: `Désactiver ce lien ${link.platform_label} ?`,
            message:
                link.status === 'publie'
                    ? `Le badge ${link.platform_label} disparaîtra du site vitrine. Le lien reste dans la liste et pourra être validé de nouveau.`
                    : 'Le brouillon sera conservé comme désactivé.',
            confirmLabel: 'Désactiver',
            tone: 'danger',
        });
        if (ok) router.post(`/admin/applications-mobiles/${link.id}/desactiver`, {}, rowOptions(link));
    };

    const remove = async (link: AppStoreLinkItem) => {
        const ok = await confirm({
            title: 'Supprimer ce lien ?',
            message: `Le lien ${link.platform_label} sera définitivement supprimé. L'opération reste tracée dans le journal d'audit.`,
            confirmLabel: 'Supprimer',
            tone: 'danger',
        });
        if (ok) router.delete(`/admin/applications-mobiles/${link.id}`, rowOptions(link));
    };

    return (
        <section className="mt-5 space-y-5">
            <div className="grid gap-4 md:grid-cols-2">
                {platforms.map((p) => {
                    const link = published(p);
                    return (
                        <Surface key={p} className="rounded-[28px] p-5">
                            <p className="text-[11px] font-bold uppercase tracking-wider text-[var(--admin-muted)]">{options.platforms[p]}</p>
                            {link ? (
                                <>
                                    <p className="mt-2 text-sm font-bold text-emerald-600">Affiché sur le site</p>
                                    <a href={link.url} target="_blank" rel="noopener noreferrer" className="mt-1 block break-all text-xs text-[var(--admin-text-soft)] underline">
                                        {link.url}
                                    </a>
                                </>
                            ) : (
                                <>
                                    <p className="mt-2 text-sm font-bold text-[var(--admin-text)]">Aucun lien publié</p>
                                    <p className="mt-1 text-xs text-[var(--admin-muted)]">Le badge {options.platforms[p]} n'apparaît pas sur le site.</p>
                                </>
                            )}
                        </Surface>
                    );
                })}
            </div>

            <Surface className="rounded-[32px] p-5 lg:p-6">
                <SectionTitle
                    title="Nouveau lien"
                    description="Le lien est créé en brouillon : il ne s'affiche sur le site qu'après validation. L'adresse doit mener à la fiche de l'application sur le magasin choisi."
                />
                <form onSubmit={create} className="mt-4 grid gap-3 md:grid-cols-[180px_1fr_auto]">
                    <select value={platform} onChange={(e) => setPlatform(e.target.value as AppStorePlatform)} aria-label="Magasin d'applications" className={fieldClass}>
                        {platforms.map((p) => (
                            <option key={p} value={p}>
                                {options.platforms[p]}
                            </option>
                        ))}
                    </select>
                    <input
                        type="url"
                        value={url}
                        onChange={(e) => setUrl(e.target.value)}
                        placeholder={URL_EXAMPLES[platform]}
                        aria-label="Adresse du lien"
                        required
                        className={fieldClass}
                    />
                    <button type="submit" className="rounded-xl bg-[#ebb95e] px-4 py-2 text-xs font-bold text-[#1d1a14]">
                        Créer le brouillon
                    </button>
                </form>
                {createError ? (
                    <p role="alert" className="mt-2 text-xs font-semibold text-rose-500">
                        {createError}
                    </p>
                ) : null}

                <DataTable className="mt-6">
                    <thead>
                        <tr>
                            <th>Magasin</th>
                            <th>Adresse</th>
                            <th>Statut</th>
                            <th>Historique</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {links.length === 0 ? (
                            <tr>
                                <td colSpan={5}>
                                    <EmptyState
                                        title="Aucun lien"
                                        description="Créez un lien Google Play ou App Store puis validez-le : la section « Téléchargez l'application » apparaîtra sur la page d'accueil du site."
                                    />
                                </td>
                            </tr>
                        ) : (
                            links.map((link) => (
                                <tr key={link.id}>
                                    <td className="text-xs font-semibold text-[var(--admin-text)]">{link.platform_label}</td>
                                    <td className="max-w-[360px] text-xs">
                                        {editingId === link.id ? (
                                            <div className="flex gap-1">
                                                <input
                                                    type="url"
                                                    value={editUrl}
                                                    onChange={(e) => setEditUrl(e.target.value)}
                                                    aria-label={`Nouvelle adresse du lien ${link.platform_label}`}
                                                    className={fieldClass}
                                                />
                                                <button type="button" className={`${actionClass} text-emerald-600`} onClick={() => saveEdit(link)}>
                                                    Enregistrer
                                                </button>
                                                <button type="button" className={actionClass} onClick={() => setEditingId(null)}>
                                                    Annuler
                                                </button>
                                            </div>
                                        ) : (
                                            <a href={link.url} target="_blank" rel="noopener noreferrer" className="break-all text-[var(--admin-text-soft)] underline">
                                                {link.url}
                                            </a>
                                        )}
                                        {rowError?.id === link.id ? (
                                            <p role="alert" className="mt-1 font-semibold text-rose-500">
                                                {rowError.message}
                                            </p>
                                        ) : null}
                                    </td>
                                    <td>
                                        <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${STATUS_TONE[link.status]}`}>{link.status_label}</span>
                                    </td>
                                    <td className="space-y-0.5 text-[11px] text-[var(--admin-muted)]">
                                        {link.created_at ? <p>Créé le {dateTimeShort(link.created_at)}{link.created_by ? ` par ${link.created_by}` : ''}</p> : null}
                                        {link.published_at ? <p>Validé le {dateTimeShort(link.published_at)}{link.published_by ? ` par ${link.published_by}` : ''}</p> : null}
                                        {link.disabled_at ? <p>Désactivé le {dateTimeShort(link.disabled_at)}{link.disabled_by ? ` par ${link.disabled_by}` : ''}</p> : null}
                                    </td>
                                    <td>
                                        <div className="flex flex-wrap gap-1">
                                            {link.status !== 'publie' ? (
                                                <>
                                                    <button type="button" className={`${actionClass} text-emerald-600`} onClick={() => publish(link)} aria-label={`Valider le lien ${link.platform_label} ${link.id}`}>
                                                        Valider
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className={actionClass}
                                                        onClick={() => {
                                                            setEditingId(link.id);
                                                            setEditUrl(link.url);
                                                        }}
                                                        aria-label={`Modifier le lien ${link.platform_label} ${link.id}`}
                                                    >
                                                        Modifier
                                                    </button>
                                                </>
                                            ) : null}
                                            {link.status !== 'desactive' ? (
                                                <button type="button" className={`${actionClass} text-rose-500`} onClick={() => disable(link)} aria-label={`Désactiver le lien ${link.platform_label} ${link.id}`}>
                                                    Désactiver
                                                </button>
                                            ) : null}
                                            {link.status !== 'publie' ? (
                                                <button type="button" className={`${actionClass} text-rose-500`} onClick={() => remove(link)} aria-label={`Supprimer le lien ${link.platform_label} ${link.id}`}>
                                                    Supprimer
                                                </button>
                                            ) : null}
                                        </div>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </DataTable>
            </Surface>
            {dialog}
        </section>
    );
}
