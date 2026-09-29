// Modale d'édition d'un message push & SMS (Chantier 14, lot C) : textes,
// canaux, variables, aperçu, compteur SMS, envoi de test et retour au texte
// d'origine. Le serveur reste seul juge à l'enregistrement.

import { router } from '@inertiajs/react';
import { useMemo, useRef, useState } from 'react';

import { placeholders, renderTemplate, smsSegments, useConfirm, useDismissOnEscape } from '../shared';
import type { NotificationChannel, NotificationChannels, NotificationEventItem } from '../shared';

type TextField = 'push_title' | 'push_body' | 'sms_body';

const CHANNEL_LABELS: Record<NotificationChannel, string> = {
    in_app: "Dans l'application",
    push: 'Notification push',
    sms: 'SMS',
};

interface EditorProps {
    event: NotificationEventItem;
    audienceLabel: string;
    smsMaxSegments: number;
    onClose: () => void;
}

export function NotificationMessageEditor({ event, audienceLabel, smsMaxSegments, onClose }: EditorProps) {
    const { confirm, dialog } = useConfirm();
    useDismissOnEscape(onClose);

    const [texts, setTexts] = useState<Record<TextField, string>>({
        push_title: event.current.title,
        push_body: event.current.body,
        sms_body: event.current.sms_body ?? '',
    });
    const [channels, setChannels] = useState<NotificationChannels>(event.current.channels);
    const [testChannels, setTestChannels] = useState<Array<'push' | 'sms'>>(event.locked.push === false ? ['sms'] : ['push']);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const lastField = useRef<TextField>('push_body');
    const fieldRefs = useRef<Partial<Record<TextField, HTMLInputElement | HTMLTextAreaElement | null>>>({});

    const pushAllowed = event.locked.push !== false || event.locked.in_app !== false;
    const examples = useMemo(
        () => Object.fromEntries(event.variables.map((variable) => [variable.name, variable.example])),
        [event.variables],
    );
    const known = new Set(event.variables.map((variable) => variable.name));

    const smsSource = texts.sms_body.trim() !== '' ? texts.sms_body : (event.defaults.sms_body ?? `${texts.push_title}: ${texts.push_body}`);
    const smsPreview = renderTemplate(smsSource, examples);
    const segments = smsSegments(smsPreview);
    const unknown = Array.from(
        new Set(
            (['push_title', 'push_body', 'sms_body'] as TextField[]).flatMap((field) => placeholders(texts[field]).filter((name) => !known.has(name))),
        ),
    );

    const setText = (field: TextField, value: string) => setTexts((current) => ({ ...current, [field]: value }));

    const insertVariable = (name: string) => {
        const field = lastField.current;
        const element = fieldRefs.current[field];
        const token = `{${name}}`;
        const value = texts[field];
        const start = element?.selectionStart ?? value.length;
        const end = element?.selectionEnd ?? value.length;
        setText(field, value.slice(0, start) + token + value.slice(end));
    };

    const toggleChannel = (channel: NotificationChannel) => {
        if (event.locked[channel] !== undefined) return;
        setChannels((current) => ({ ...current, [channel]: !current[channel] }));
    };

    const handleErrors = (serverErrors: Record<string, string>) => setErrors(serverErrors);

    const save = () => {
        setProcessing(true);
        setErrors({});
        router.put(
            `/admin/messages/${event.key}`,
            {
                push_title: texts.push_title,
                push_body: texts.push_body,
                sms_body: texts.sms_body.trim() === '' ? null : texts.sms_body,
                channel_in_app: channels.in_app,
                channel_push: channels.push,
                channel_sms: channels.sms,
            },
            {
                preserveScroll: true,
                onSuccess: () => onClose(),
                onError: handleErrors,
                onFinish: () => setProcessing(false),
            },
        );
    };

    const sendTest = () => {
        if (testChannels.length === 0) return;
        router.post(`/admin/messages/${event.key}/test`, { channels: testChannels }, { preserveScroll: true, onError: handleErrors });
    };

    const resetToOrigin = async () => {
        const ok = await confirm({
            title: "Revenir au texte d'origine ?",
            message: `Les textes et canaux personnalisés de « ${event.label} » seront supprimés. Le message d'origine s'appliquera de nouveau.`,
            confirmLabel: "Rétablir l'origine",
            tone: 'danger',
        });
        if (!ok) return;
        router.delete(`/admin/messages/${event.key}`, { preserveScroll: true, onSuccess: () => onClose() });
    };

    const fieldClass =
        'w-full rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-2 text-sm text-[var(--admin-text)] focus:border-amber-500 focus:outline-none';

    const fieldError = (key: string) =>
        errors[key] ? <p className="mt-1 text-xs font-semibold text-rose-500">{errors[key]}</p> : null;

    return (
        <>
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" role="presentation" onClick={onClose}>
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="notification-editor-title"
                className="admin-panel admin-surface relative max-h-[92vh] w-full max-w-[1080px] overflow-y-auto rounded-[32px] border p-6 shadow-2xl lg:p-8"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-start justify-between gap-4 border-b border-[var(--admin-border)] pb-4">
                    <div>
                        <h2 id="notification-editor-title" className="text-xl font-bold text-[var(--admin-text)]">
                            {event.label}
                        </h2>
                        <p className="mt-1 text-xs text-[var(--admin-muted)]">
                            Destinataire : {audienceLabel} · <code>{event.key}</code>
                        </p>
                    </div>
                    <button type="button" onClick={onClose} aria-label="Fermer" className="rounded-full p-2 text-[var(--admin-muted)] hover:text-[var(--admin-text)]">
                        ✕
                    </button>
                </div>

                <div className="mt-5 grid gap-6 lg:grid-cols-[1.4fr_1fr]">
                    <div className="space-y-4">
                        <fieldset>
                            <legend className="text-xs font-bold uppercase tracking-wide text-[var(--admin-muted)]">Canaux</legend>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {(Object.keys(CHANNEL_LABELS) as NotificationChannel[]).map((channel) => {
                                    const locked = event.locked[channel] !== undefined;
                                    return (
                                        <label
                                            key={channel}
                                            className="flex cursor-pointer items-center gap-2 rounded-full border border-[var(--admin-border)] px-3 py-1.5 text-xs font-semibold text-[var(--admin-text)]"
                                        >
                                            <input type="checkbox" checked={channels[channel]} disabled={locked} onChange={() => toggleChannel(channel)} />
                                            {CHANNEL_LABELS[channel]}
                                            {locked ? <span className="text-[10px] text-[var(--admin-muted)]">(imposé)</span> : null}
                                        </label>
                                    );
                                })}
                            </div>
                            {event.security ? (
                                <p className="mt-2 text-xs text-[var(--admin-muted)]">Alerte de sécurité : le push ou le SMS doit rester actif.</p>
                            ) : null}
                            {fieldError('channels')}
                        </fieldset>

                        {pushAllowed ? (
                            <>
                                <div>
                                    <label htmlFor="push_title" className="text-xs font-bold text-[var(--admin-text)]">
                                        Titre (application et push)
                                    </label>
                                    <input
                                        id="push_title"
                                        ref={(el) => {
                                            fieldRefs.current.push_title = el;
                                        }}
                                        value={texts.push_title}
                                        onFocus={() => (lastField.current = 'push_title')}
                                        onChange={(e) => setText('push_title', e.target.value)}
                                        className={`mt-1 ${fieldClass}`}
                                    />
                                    {fieldError('push_title')}
                                </div>
                                <div>
                                    <label htmlFor="push_body" className="text-xs font-bold text-[var(--admin-text)]">
                                        Texte (application et push)
                                    </label>
                                    <textarea
                                        id="push_body"
                                        rows={3}
                                        ref={(el) => {
                                            fieldRefs.current.push_body = el;
                                        }}
                                        value={texts.push_body}
                                        onFocus={() => (lastField.current = 'push_body')}
                                        onChange={(e) => setText('push_body', e.target.value)}
                                        className={`mt-1 ${fieldClass}`}
                                    />
                                    {fieldError('push_body')}
                                </div>
                            </>
                        ) : null}

                        <div>
                            <label htmlFor="sms_body" className="text-xs font-bold text-[var(--admin-text)]">
                                Texte du SMS
                            </label>
                            <textarea
                                id="sms_body"
                                rows={3}
                                ref={(el) => {
                                    fieldRefs.current.sms_body = el;
                                }}
                                value={texts.sms_body}
                                placeholder={event.defaults.sms_body ?? 'Vide : « Titre : texte » est envoyé.'}
                                onFocus={() => (lastField.current = 'sms_body')}
                                onChange={(e) => setText('sms_body', e.target.value)}
                                className={`mt-1 ${fieldClass}`}
                            />
                            <p
                                className={`mt-1 text-xs ${segments.segments > smsMaxSegments ? 'font-semibold text-rose-500' : 'text-[var(--admin-muted)]'}`}
                                data-testid="sms-counter"
                            >
                                {segments.length} caractères · {segments.encoding === 'gsm' ? 'GSM' : 'Unicode (accents spéciaux)'} · {segments.segments} SMS
                                {segments.segments > smsMaxSegments ? ` — maximum ${smsMaxSegments}` : ''}
                            </p>
                            {fieldError('sms_body')}
                        </div>

                        {unknown.length > 0 ? (
                            <p className="rounded-xl border border-rose-300 bg-rose-50 p-2 text-xs text-rose-700">
                                Variable(s) inconnue(s) pour ce message : {unknown.map((name) => `{${name}}`).join(', ')}
                            </p>
                        ) : null}

                        {event.variables.length > 0 ? (
                            <div>
                                <p className="text-xs font-bold uppercase tracking-wide text-[var(--admin-muted)]">Variables (cliquer pour insérer)</p>
                                <ul className="mt-2 space-y-1">
                                    {event.variables.map((variable) => (
                                        <li key={variable.name} className="flex flex-wrap items-center gap-2 text-xs text-[var(--admin-text-soft)]">
                                            <button
                                                type="button"
                                                onClick={() => insertVariable(variable.name)}
                                                className="rounded-lg border border-[var(--admin-border)] px-2 py-0.5 font-mono text-[var(--admin-text)] hover:border-amber-500"
                                            >
                                                {`{${variable.name}}`}
                                            </button>
                                            <span>
                                                {variable.description}
                                                {variable.required ? ' — obligatoire' : ''} · ex. « {variable.example} »
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ) : null}
                    </div>

                    <div className="space-y-4">
                        <p className="text-xs font-bold uppercase tracking-wide text-[var(--admin-muted)]">Aperçu avec des valeurs d'exemple</p>
                        {pushAllowed ? (
                            <div className="rounded-2xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] p-4" aria-label="Aperçu de la notification">
                                <p className="text-[11px] text-[var(--admin-muted)]">ProsArtisan · maintenant</p>
                                <p className="mt-1 text-sm font-bold text-[var(--admin-text)]">{renderTemplate(texts.push_title, examples)}</p>
                                <p className="mt-0.5 text-sm text-[var(--admin-text-soft)]">{renderTemplate(texts.push_body, examples)}</p>
                            </div>
                        ) : null}
                        <div className="rounded-2xl border border-[var(--admin-border)] p-4" aria-label="Aperçu du SMS">
                            <p className="text-[11px] text-[var(--admin-muted)]">SMS · ProsArtisan{channels.sms ? '' : ' (canal SMS désactivé)'}</p>
                            <p className="mt-2 whitespace-pre-wrap rounded-2xl rounded-tl-sm bg-[#ebb95e]/15 p-3 text-sm text-[var(--admin-text)]">{smsPreview}</p>
                        </div>

                        <div className="rounded-2xl border border-[var(--admin-border)] p-4">
                            <p className="text-xs font-bold text-[var(--admin-text)]">Envoi de test à moi-même</p>
                            <p className="mt-1 text-xs text-[var(--admin-muted)]">Envoie le message enregistré (préfixé « [Test] ») sur votre téléphone. Enregistrez d'abord vos modifications.</p>
                            <div className="mt-2 flex flex-wrap gap-3 text-xs text-[var(--admin-text)]">
                                {(['push', 'sms'] as const).map((channel) => (
                                    <label key={channel} className="flex items-center gap-1.5">
                                        <input
                                            type="checkbox"
                                            disabled={channel === 'push' && event.locked.push === false}
                                            checked={testChannels.includes(channel)}
                                            onChange={() =>
                                                setTestChannels((current) => (current.includes(channel) ? current.filter((c) => c !== channel) : [...current, channel]))
                                            }
                                        />
                                        {channel === 'push' ? 'Tester en push' : 'Tester par SMS'}
                                    </label>
                                ))}
                            </div>
                            <button
                                type="button"
                                onClick={sendTest}
                                disabled={testChannels.length === 0}
                                className="mt-3 rounded-xl border border-[var(--admin-border)] px-3 py-1.5 text-xs font-semibold text-[var(--admin-text)] disabled:opacity-50"
                            >
                                Envoyer un test
                            </button>
                            {fieldError('channels.0')}
                        </div>
                    </div>
                </div>

                <div className="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-[var(--admin-border)] pt-4">
                    <div>
                        {event.overridden ? (
                            <button type="button" onClick={resetToOrigin} className="text-xs font-semibold text-rose-500 hover:underline">
                                Revenir au texte d'origine
                            </button>
                        ) : (
                            <span className="text-xs text-[var(--admin-muted)]">Texte d'origine en vigueur</span>
                        )}
                    </div>
                    <div className="flex gap-2">
                        <button type="button" onClick={onClose} className="rounded-xl px-4 py-2 text-sm font-semibold text-[var(--admin-muted)]">
                            Annuler
                        </button>
                        <button
                            type="button"
                            onClick={save}
                            disabled={processing || unknown.length > 0}
                            className="rounded-xl bg-[#ebb95e] px-4 py-2 text-sm font-bold text-[#1d1a14] disabled:opacity-50"
                        >
                            Enregistrer
                        </button>
                    </div>
                </div>
            </div>
        </div>
        {/* Hors du fond cliquable : un clic dans la confirmation ne ferme pas l'éditeur. */}
        {dialog}
        </>
    );
}
