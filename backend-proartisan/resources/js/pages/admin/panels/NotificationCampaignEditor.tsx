// Modale de rédaction d'une campagne push & SMS (Chantier 14, lot D) : textes,
// canaux, nature, ciblage, écran d'ouverture et aperçu. Le serveur reste seul
// juge à l'enregistrement (variables interdites, SMS promotionnel, 3 segments).

import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { placeholders, smsSegments, useDismissOnEscape } from '../shared';
import type {
    NotificationCampaignItem,
    NotificationCampaignNature,
    NotificationCampaignOptions,
    NotificationCampaignScreen,
    NotificationCampaignTarget,
    NotificationCampaignUser,
    NotificationChannel,
    NotificationChannels,
} from '../shared';

const CHANNEL_LABELS: Record<NotificationChannel, string> = {
    in_app: "Dans l'application",
    push: 'Notification push',
    sms: 'SMS',
};

interface EditorProps {
    campaign: NotificationCampaignItem | null;
    options: NotificationCampaignOptions;
    onClose: () => void;
}

const EMPTY_TARGET: NotificationCampaignTarget = { roles: [], commune_ids: [], kyc_statuses: [], user_ids: [] };

function toggle<T>(list: T[], value: T): T[] {
    return list.includes(value) ? list.filter((item) => item !== value) : [...list, value];
}

export function NotificationCampaignEditor({ campaign, options, onClose }: EditorProps) {
    useDismissOnEscape(onClose);

    const [name, setName] = useState(campaign?.name ?? '');
    const [nature, setNature] = useState<NotificationCampaignNature>(campaign?.nature ?? 'service');
    const [title, setTitle] = useState(campaign?.push_title ?? '');
    const [body, setBody] = useState(campaign?.push_body ?? '');
    const [sms, setSms] = useState(campaign?.sms_body ?? '');
    const [channels, setChannels] = useState<NotificationChannels>(campaign?.channels ?? { in_app: true, push: true, sms: false });
    const [target, setTarget] = useState<NotificationCampaignTarget>(campaign?.target ?? EMPTY_TARGET);
    const [selectedUsers, setSelectedUsers] = useState<NotificationCampaignUser[]>(campaign?.selected_users ?? []);
    const [screen, setScreen] = useState<NotificationCampaignScreen>(campaign?.open_screen ?? 'notifications');
    const [communicationId, setCommunicationId] = useState<number | ''>(campaign?.communication?.id ?? '');
    const [search, setSearch] = useState('');
    const [results, setResults] = useState<NotificationCampaignUser[]>([]);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const promotional = nature === 'promotionnel';
    const smsActive = channels.sms && !promotional;
    const segments = smsSegments(sms);
    const forbidden = Array.from(new Set([title, body, sms].flatMap((text) => placeholders(text))));

    // Recherche d'utilisateurs à désigner un à un (jamais un administrateur).
    useEffect(() => {
        const term = search.trim();
        if (term.length < 2) return;
        let active = true;
        const timer = window.setTimeout(() => {
            fetch(`/admin/campagnes-notifications/utilisateurs?q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' } })
                .then((response) => (response.ok ? response.json() : { data: [] }))
                .then((payload: { data: NotificationCampaignUser[] }) => {
                    if (active) setResults(payload.data ?? []);
                })
                .catch(() => {
                    if (active) setResults([]);
                });
        }, 300);
        return () => {
            active = false;
            window.clearTimeout(timer);
        };
    }, [search]);

    const addUser = (user: NotificationCampaignUser) => {
        if (target.user_ids.includes(user.id)) return;
        setTarget((current) => ({ ...current, user_ids: [...current.user_ids, user.id] }));
        setSelectedUsers((current) => [...current, user]);
        setSearch('');
        setResults([]);
    };

    const removeUser = (id: number) => {
        setTarget((current) => ({ ...current, user_ids: current.user_ids.filter((userId) => userId !== id) }));
        setSelectedUsers((current) => current.filter((user) => user.id !== id));
    };

    const save = () => {
        setProcessing(true);
        setErrors({});
        const payload = {
            name,
            nature,
            push_title: title,
            push_body: body,
            sms_body: smsActive ? sms : null,
            channel_in_app: channels.in_app,
            channel_push: channels.push,
            channel_sms: smsActive,
            target,
            open_screen: screen,
            communication_id: screen === 'communication' && communicationId !== '' ? communicationId : null,
        };
        const callbacks = {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: (serverErrors: Record<string, string>) => setErrors(serverErrors),
            onFinish: () => setProcessing(false),
        };
        if (campaign) {
            router.put(`/admin/campagnes-notifications/${campaign.id}`, payload, callbacks);
        } else {
            router.post('/admin/campagnes-notifications', payload, callbacks);
        }
    };

    const fieldClass =
        'w-full rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-2 text-sm text-[var(--admin-text)] focus:border-amber-500 focus:outline-none';
    const chipClass = 'flex cursor-pointer items-center gap-2 rounded-full border border-[var(--admin-border)] px-3 py-1.5 text-xs font-semibold text-[var(--admin-text)]';
    const fieldError = (key: string) => (errors[key] ? <p className="mt-1 text-xs font-semibold text-rose-500">{errors[key]}</p> : null);
    const targetError = Object.entries(errors).find(([key]) => key.startsWith('target'))?.[1];

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" role="presentation" onClick={onClose}>
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="campaign-editor-title"
                className="admin-panel admin-surface relative max-h-[92vh] w-full max-w-[1080px] overflow-y-auto rounded-[32px] border p-6 shadow-2xl lg:p-8"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-start justify-between gap-4 border-b border-[var(--admin-border)] pb-4">
                    <div>
                        <h2 id="campaign-editor-title" className="text-xl font-bold text-[var(--admin-text)]">
                            {campaign ? 'Modifier la campagne' : 'Nouvelle campagne'}
                        </h2>
                        <p className="mt-1 text-xs text-[var(--admin-muted)]">Enregistrée en brouillon : rien ne part avant la programmation.</p>
                    </div>
                    <button type="button" onClick={onClose} aria-label="Fermer" className="rounded-full p-2 text-[var(--admin-muted)] hover:text-[var(--admin-text)]">
                        ✕
                    </button>
                </div>

                {errors.campaign ? <p className="mt-4 rounded-xl bg-rose-50 p-3 text-xs font-semibold text-rose-700">{errors.campaign}</p> : null}

                <div className="mt-5 grid gap-6 lg:grid-cols-[1.4fr_1fr]">
                    <div className="space-y-4">
                        <div>
                            <label htmlFor="campaign_name" className="text-xs font-bold text-[var(--admin-text)]">
                                Nom de la campagne (interne)
                            </label>
                            <input id="campaign_name" value={name} onChange={(e) => setName(e.target.value)} className={`mt-1 ${fieldClass}`} />
                            {fieldError('name')}
                        </div>

                        <fieldset>
                            <legend className="text-xs font-bold uppercase tracking-wide text-[var(--admin-muted)]">Nature</legend>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {(Object.entries(options.natures) as Array<[NotificationCampaignNature, string]>).map(([key, label]) => (
                                    <label key={key} className={chipClass}>
                                        <input type="radio" name="nature" checked={nature === key} onChange={() => setNature(key)} />
                                        {label}
                                    </label>
                                ))}
                            </div>
                            {promotional ? (
                                <p className="mt-2 text-xs text-[var(--admin-muted)]">
                                    Push uniquement, et seulement vers les utilisateurs ayant accepté les offres et nouveautés.
                                </p>
                            ) : null}
                        </fieldset>

                        <fieldset>
                            <legend className="text-xs font-bold uppercase tracking-wide text-[var(--admin-muted)]">Canaux</legend>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {(Object.keys(CHANNEL_LABELS) as NotificationChannel[])
                                    .filter((channel) => channel !== 'sms' || !promotional)
                                    .map((channel) => (
                                        <label key={channel} className={chipClass}>
                                            <input
                                                type="checkbox"
                                                checked={channels[channel]}
                                                onChange={() => setChannels((current) => ({ ...current, [channel]: !current[channel] }))}
                                            />
                                            {CHANNEL_LABELS[channel]}
                                        </label>
                                    ))}
                            </div>
                            {fieldError('channels')}
                            {fieldError('channel_sms')}
                        </fieldset>

                        {channels.in_app || channels.push ? (
                            <>
                                <div>
                                    <label htmlFor="campaign_title" className="text-xs font-bold text-[var(--admin-text)]">
                                        Titre (application et push)
                                    </label>
                                    <input id="campaign_title" value={title} onChange={(e) => setTitle(e.target.value)} className={`mt-1 ${fieldClass}`} />
                                    {fieldError('push_title')}
                                </div>
                                <div>
                                    <label htmlFor="campaign_body" className="text-xs font-bold text-[var(--admin-text)]">
                                        Texte (application et push)
                                    </label>
                                    <textarea id="campaign_body" rows={3} value={body} onChange={(e) => setBody(e.target.value)} className={`mt-1 ${fieldClass}`} />
                                    {fieldError('push_body')}
                                </div>
                            </>
                        ) : null}

                        {smsActive ? (
                            <div>
                                <label htmlFor="campaign_sms" className="text-xs font-bold text-[var(--admin-text)]">
                                    Texte du SMS
                                </label>
                                <textarea id="campaign_sms" rows={3} value={sms} onChange={(e) => setSms(e.target.value)} className={`mt-1 ${fieldClass}`} />
                                <p
                                    className={`mt-1 text-xs ${segments.segments > options.sms_max_segments ? 'font-semibold text-rose-500' : 'text-[var(--admin-muted)]'}`}
                                    data-testid="campaign-sms-counter"
                                >
                                    {segments.length} caractères · {segments.encoding === 'gsm' ? 'GSM' : 'Unicode (accents spéciaux)'} · {segments.segments} SMS par destinataire
                                    {segments.segments > options.sms_max_segments ? ` — maximum ${options.sms_max_segments}` : ''}
                                </p>
                                {fieldError('sms_body')}
                            </div>
                        ) : null}

                        {forbidden.length > 0 ? (
                            <p className="rounded-xl border border-rose-300 bg-rose-50 p-2 text-xs text-rose-700">
                                Les campagnes n'acceptent pas de variable : {forbidden.map((variable) => `{${variable}}`).join(', ')} serait envoyé tel quel.
                            </p>
                        ) : null}

                        <fieldset>
                            <legend className="text-xs font-bold uppercase tracking-wide text-[var(--admin-muted)]">Public visé (critères cumulés)</legend>
                            <p className="mt-1 text-xs font-semibold text-[var(--admin-text)]">Rôles</p>
                            <div className="mt-1 flex flex-wrap gap-2">
                                {Object.entries(options.roles).map(([key, label]) => (
                                    <label key={key} className={chipClass}>
                                        <input type="checkbox" checked={target.roles.includes(key)} onChange={() => setTarget((current) => ({ ...current, roles: toggle(current.roles, key) }))} />
                                        {label}
                                    </label>
                                ))}
                            </div>
                            <p className="mt-3 text-xs font-semibold text-[var(--admin-text)]">Statut KYC (tous si aucun)</p>
                            <div className="mt-1 flex flex-wrap gap-2">
                                {Object.entries(options.kyc_statuses).map(([key, label]) => (
                                    <label key={key} className={chipClass}>
                                        <input
                                            type="checkbox"
                                            checked={target.kyc_statuses.includes(key)}
                                            onChange={() => setTarget((current) => ({ ...current, kyc_statuses: toggle(current.kyc_statuses, key) }))}
                                        />
                                        {label}
                                    </label>
                                ))}
                            </div>
                            {options.communes.length > 0 ? (
                                <>
                                    <label htmlFor="campaign_communes" className="mt-3 block text-xs font-semibold text-[var(--admin-text)]">
                                        Communes (toutes si aucune)
                                    </label>
                                    <select
                                        id="campaign_communes"
                                        multiple
                                        value={target.commune_ids.map(String)}
                                        onChange={(e) =>
                                            setTarget((current) => ({ ...current, commune_ids: Array.from(e.target.selectedOptions, (option) => Number(option.value)) }))
                                        }
                                        className={`mt-1 h-28 ${fieldClass}`}
                                    >
                                        {options.communes.map((commune) => (
                                            <option key={commune.id} value={commune.id}>
                                                {commune.name}
                                            </option>
                                        ))}
                                    </select>
                                </>
                            ) : null}
                            <label htmlFor="campaign_user_search" className="mt-3 block text-xs font-semibold text-[var(--admin-text)]">
                                Utilisateurs précis (facultatif, {options.max_selected_users} au plus)
                            </label>
                            <input
                                id="campaign_user_search"
                                type="search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Nom ou téléphone..."
                                className={`mt-1 ${fieldClass}`}
                            />
                            {search.trim().length >= 2 && results.length > 0 ? (
                                <ul className="mt-1 max-h-40 overflow-y-auto rounded-xl border border-[var(--admin-border)]">
                                    {results.map((user) => (
                                        <li key={user.id}>
                                            <button type="button" onClick={() => addUser(user)} className="w-full px-3 py-1.5 text-left text-xs text-[var(--admin-text)] hover:bg-amber-500/10">
                                                {user.name} · {user.phone ?? '—'} · {options.roles[user.role] ?? user.role}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            ) : null}
                            {selectedUsers.length > 0 ? (
                                <div className="mt-2 flex flex-wrap gap-1">
                                    {selectedUsers.map((user) => (
                                        <span key={user.id} className="flex items-center gap-1 rounded-full bg-[#ebb95e]/20 px-2 py-0.5 text-[11px] text-[var(--admin-text)]">
                                            {user.name}
                                            <button type="button" onClick={() => removeUser(user.id)} aria-label={`Retirer ${user.name}`}>
                                                ✕
                                            </button>
                                        </span>
                                    ))}
                                </div>
                            ) : null}
                            {targetError ? <p className="mt-1 text-xs font-semibold text-rose-500">{targetError}</p> : null}
                        </fieldset>

                        <div>
                            <label htmlFor="campaign_screen" className="text-xs font-bold text-[var(--admin-text)]">
                                Écran ouvert au toucher
                            </label>
                            <select id="campaign_screen" value={screen} onChange={(e) => setScreen(e.target.value as NotificationCampaignScreen)} className={`mt-1 ${fieldClass}`}>
                                {(Object.entries(options.screens) as Array<[NotificationCampaignScreen, string]>).map(([key, label]) => (
                                    <option key={key} value={key}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                            {screen === 'communication' ? (
                                <select
                                    aria-label="Communication publiée"
                                    value={communicationId}
                                    onChange={(e) => setCommunicationId(e.target.value === '' ? '' : Number(e.target.value))}
                                    className={`mt-2 ${fieldClass}`}
                                >
                                    <option value="">Choisir une communication publiée…</option>
                                    {options.communications.map((communication) => (
                                        <option key={communication.id} value={communication.id}>
                                            {communication.titre}
                                        </option>
                                    ))}
                                </select>
                            ) : null}
                            {fieldError('communication_id')}
                        </div>
                    </div>

                    <div className="space-y-4">
                        <p className="text-xs font-bold uppercase tracking-wide text-[var(--admin-muted)]">Aperçu</p>
                        {channels.in_app || channels.push ? (
                            <div className="rounded-2xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] p-4" aria-label="Aperçu de la notification">
                                <p className="text-[11px] text-[var(--admin-muted)]">ProsArtisan · maintenant</p>
                                <p className="mt-1 text-sm font-bold text-[var(--admin-text)]">{title || 'Titre'}</p>
                                <p className="mt-0.5 text-sm text-[var(--admin-text-soft)]">{body || 'Texte de la notification'}</p>
                            </div>
                        ) : null}
                        {smsActive ? (
                            <div className="rounded-2xl border border-[var(--admin-border)] p-4" aria-label="Aperçu du SMS">
                                <p className="text-[11px] text-[var(--admin-muted)]">SMS · ProsArtisan</p>
                                <p className="mt-2 whitespace-pre-wrap rounded-2xl rounded-tl-sm bg-[#ebb95e]/15 p-3 text-sm text-[var(--admin-text)]">{sms || 'Texte du SMS'}</p>
                            </div>
                        ) : null}
                        <p className="text-xs text-[var(--admin-muted)]">
                            Le nombre de destinataires et de SMS s'affiche dans la liste après l'enregistrement, et de nouveau avant l'envoi.
                        </p>
                    </div>
                </div>

                <div className="mt-6 flex justify-end gap-2 border-t border-[var(--admin-border)] pt-4">
                    <button type="button" onClick={onClose} className="rounded-xl px-4 py-2 text-sm font-semibold text-[var(--admin-muted)]">
                        Annuler
                    </button>
                    <button
                        type="button"
                        onClick={save}
                        disabled={processing || forbidden.length > 0}
                        className="rounded-xl bg-[#ebb95e] px-4 py-2 text-sm font-bold text-[#1d1a14] disabled:opacity-50"
                    >
                        Enregistrer le brouillon
                    </button>
                </div>
            </div>
        </div>
    );
}
