// Saisie de la disponibilité d'un artisan par un administrateur (Chantier 15) :
// statut, date de retour, jours et horaires, nuit. Publiée directement ; le
// serveur reste seul juge (plages valides, sans chevauchement, date à venir).

import { router } from '@inertiajs/react';
import { useState } from 'react';

import { useDismissOnEscape } from '../shared';
import type { AvailabilitySlot, AvailabilityStatus, DirectoryArtisanRow, DirectoryOptions } from '../shared';

interface EditorProps {
    artisan: DirectoryArtisanRow;
    options: DirectoryOptions;
    onClose: () => void;
}

export function ArtisanAvailabilityEditor({ artisan, options, onClose }: EditorProps) {
    useDismissOnEscape(onClose);

    // Point de départ : la déclaration en attente, sinon la version publiée.
    const base = artisan.pending ?? artisan.published;
    const [status, setStatus] = useState<AvailabilityStatus>(base?.status ?? 'disponible');
    const [until, setUntil] = useState(base?.until_date ?? '');
    const [slots, setSlots] = useState<AvailabilitySlot[]>(base?.schedule ?? []);
    const [night, setNight] = useState(base?.night_work ?? false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const updateSlot = (index: number, patch: Partial<AvailabilitySlot>) =>
        setSlots((current) => current.map((slot, i) => (i === index ? { ...slot, ...patch } : slot)));

    const save = () => {
        setProcessing(true);
        setErrors({});
        router.put(
            `/admin/annuaire-artisans/${artisan.id}/disponibilite`,
            { status, until_date: status === 'disponible' ? null : until || null, schedule: slots, night_work: night },
            {
                preserveScroll: true,
                onSuccess: () => onClose(),
                onError: (serverErrors: Record<string, string>) => setErrors(serverErrors),
                onFinish: () => setProcessing(false),
            },
        );
    };

    const fieldClass =
        'rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-2 text-sm text-[var(--admin-text)] focus:border-amber-500 focus:outline-none';
    const slotErrors = Object.entries(errors).filter(([key]) => key.startsWith('schedule'));

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" role="presentation" onClick={onClose}>
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="availability-editor-title"
                className="admin-panel admin-surface relative max-h-[92vh] w-full max-w-[640px] overflow-y-auto rounded-[32px] border p-6 shadow-2xl"
                onClick={(e) => e.stopPropagation()}
            >
                <h2 id="availability-editor-title" className="text-xl font-bold text-[var(--admin-text)]">
                    Disponibilité de {artisan.name}
                </h2>
                <p className="mt-1 text-xs text-[var(--admin-muted)]">
                    Publiée directement dans l'annuaire ; une déclaration de l'artisan encore en attente est remplacée.
                </p>

                <fieldset className="mt-5">
                    <legend className="text-xs font-bold uppercase tracking-wide text-[var(--admin-muted)]">Statut</legend>
                    <div className="mt-2 flex flex-wrap gap-2">
                        {(Object.entries(options.statuses) as Array<[AvailabilityStatus, string]>).map(([key, label]) => (
                            <label key={key} className="flex cursor-pointer items-center gap-2 rounded-full border border-[var(--admin-border)] px-3 py-1.5 text-xs font-semibold text-[var(--admin-text)]">
                                <input type="radio" name="availability-status" checked={status === key} onChange={() => setStatus(key)} />
                                {label}
                            </label>
                        ))}
                    </div>
                    {status !== 'disponible' ? (
                        <label className="mt-3 flex flex-col gap-1 text-xs font-semibold text-[var(--admin-text)]">
                            Jusqu'au
                            <input type="date" aria-label="Date de retour" value={until} onChange={(e) => setUntil(e.target.value)} className={fieldClass} />
                        </label>
                    ) : null}
                    {errors.status ? <p className="mt-1 text-xs font-semibold text-rose-500">{errors.status}</p> : null}
                    {errors.until_date ? <p className="mt-1 text-xs font-semibold text-rose-500">{errors.until_date}</p> : null}
                </fieldset>

                <fieldset className="mt-5">
                    <legend className="text-xs font-bold uppercase tracking-wide text-[var(--admin-muted)]">Jours et horaires habituels</legend>
                    {slots.length === 0 ? <p className="mt-2 text-xs text-[var(--admin-muted)]">Aucun horaire : l'annuaire n'en affichera pas.</p> : null}
                    <ul className="mt-2 space-y-2">
                        {slots.map((slot, index) => (
                            <li key={index} className="flex flex-wrap items-center gap-2">
                                <select
                                    aria-label={`Jour de la plage ${index + 1}`}
                                    value={slot.day}
                                    onChange={(e) => updateSlot(index, { day: Number(e.target.value) })}
                                    className={fieldClass}
                                >
                                    {Object.entries(options.days).map(([day, label]) => (
                                        <option key={day} value={day}>
                                            {label}
                                        </option>
                                    ))}
                                </select>
                                <input type="time" aria-label={`Début de la plage ${index + 1}`} value={slot.start} onChange={(e) => updateSlot(index, { start: e.target.value })} className={fieldClass} />
                                <span className="text-xs text-[var(--admin-muted)]">à</span>
                                <input type="time" aria-label={`Fin de la plage ${index + 1}`} value={slot.end} onChange={(e) => updateSlot(index, { end: e.target.value })} className={fieldClass} />
                                <button
                                    type="button"
                                    onClick={() => setSlots((current) => current.filter((_, i) => i !== index))}
                                    aria-label={`Retirer la plage ${index + 1}`}
                                    className="text-xs font-semibold text-rose-500"
                                >
                                    Retirer
                                </button>
                            </li>
                        ))}
                    </ul>
                    {slots.length < options.max_slots ? (
                        <button
                            type="button"
                            onClick={() => setSlots((current) => [...current, { day: 1, start: '08:00', end: '17:00' }])}
                            className="mt-2 rounded-xl border border-[var(--admin-border)] px-3 py-1.5 text-xs font-semibold text-[var(--admin-text)]"
                        >
                            Ajouter une plage horaire
                        </button>
                    ) : null}
                    {slotErrors.map(([key, message]) => (
                        <p key={key} className="mt-1 text-xs font-semibold text-rose-500">
                            {message}
                        </p>
                    ))}
                </fieldset>

                <label className="mt-5 flex items-center gap-2 text-sm text-[var(--admin-text)]">
                    <input type="checkbox" checked={night} onChange={() => setNight((value) => !value)} />
                    Intervient la nuit
                </label>

                <div className="mt-6 flex justify-end gap-2 border-t border-[var(--admin-border)] pt-4">
                    <button type="button" onClick={onClose} className="rounded-xl px-4 py-2 text-sm font-semibold text-[var(--admin-muted)]">
                        Annuler
                    </button>
                    <button
                        type="button"
                        onClick={save}
                        disabled={processing || (status !== 'disponible' && until === '')}
                        className="rounded-xl bg-[#ebb95e] px-4 py-2 text-sm font-bold text-[#1d1a14] disabled:opacity-50"
                    >
                        Publier
                    </button>
                </div>
            </div>
        </div>
    );
}
