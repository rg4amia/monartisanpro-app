// Confirmation destructive normalisée + accessible (Chantier C7 / P2-13).
//
// Remplace les `window.confirm` / `window.prompt` dispersés par une modale
// unique : piège de focus, fermeture Échap, `aria-modal`, saisie optionnelle
// d'un motif ou d'un mot de confirmation.

import { useCallback, useEffect, useRef, useState } from 'react';

export interface ConfirmOptions {
    title: string;
    message?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    tone?: 'danger' | 'primary';
    /** Le bouton de confirmation reste inactif tant que ce mot n'est pas saisi à l'identique. */
    requireText?: string;
    /** Affiche un champ ; la promesse se résout alors avec sa valeur (string) plutôt qu'avec `true`. */
    promptLabel?: string;
    promptPlaceholder?: string;
    promptMinLength?: number;
    promptOptional?: boolean;
}

type Resolver = (value: boolean | string) => void;

interface DialogState extends ConfirmOptions {
    open: boolean;
}

const CLOSED: DialogState = { open: false, title: '' };

export function useConfirm() {
    const [state, setState] = useState<DialogState>(CLOSED);
    const [text, setText] = useState('');
    const resolverRef = useRef<Resolver | null>(null);
    const confirmButtonRef = useRef<HTMLButtonElement | null>(null);
    const inputRef = useRef<HTMLInputElement | HTMLTextAreaElement | null>(null);
    const previousFocusRef = useRef<HTMLElement | null>(null);

    const close = useCallback((value: boolean | string) => {
        resolverRef.current?.(value);
        resolverRef.current = null;
        setState(CLOSED);
        setText('');
        previousFocusRef.current?.focus?.();
    }, []);

    const confirm = useCallback((options: ConfirmOptions): Promise<boolean | string> => {
        previousFocusRef.current = (document.activeElement as HTMLElement) ?? null;
        setText('');
        setState({ ...options, open: true });
        return new Promise<boolean | string>((resolve) => {
            resolverRef.current = resolve;
        });
    }, []);

    useEffect(() => {
        if (!state.open) return;

        const focusTarget = state.promptLabel || state.requireText ? inputRef.current : confirmButtonRef.current;
        focusTarget?.focus();

        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                close(false);
            }
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [state.open, state.promptLabel, state.requireText, close]);

    const trimmed = text.trim();
    const requireOk = !state.requireText || text === state.requireText;
    const promptOk =
        !state.promptLabel ||
        state.promptOptional ||
        trimmed.length >= (state.promptMinLength ?? 1);
    const canConfirm = requireOk && promptOk;

    const onConfirm = () => {
        if (!canConfirm) return;
        close(state.promptLabel ? text : true);
    };

    const dialog = state.open ? (
        <div
            className="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
            role="presentation"
            onClick={() => close(false)}
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="confirm-dialog-title"
                className="admin-panel admin-surface w-full max-w-[440px] rounded-[28px] border p-6 shadow-2xl"
                onClick={(event) => event.stopPropagation()}
            >
                <h2 id="confirm-dialog-title" className="text-lg font-bold text-[var(--admin-text)]">
                    {state.title}
                </h2>
                {state.message ? (
                    <p className="mt-2 text-sm text-[var(--admin-text-soft)] whitespace-pre-line">{state.message}</p>
                ) : null}

                {state.requireText ? (
                    <input
                        ref={(el) => { inputRef.current = el; }}
                        type="text"
                        value={text}
                        onChange={(event) => setText(event.target.value)}
                        placeholder={state.requireText}
                        aria-label={`Tapez ${state.requireText} pour confirmer`}
                        className="mt-4 w-full rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-sm text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                    />
                ) : null}

                {state.promptLabel ? (
                    <label className="mt-4 block">
                        <span className="mb-1.5 block text-xs font-semibold text-[var(--admin-muted)]">{state.promptLabel}</span>
                        <textarea
                            ref={(el) => { inputRef.current = el; }}
                            value={text}
                            onChange={(event) => setText(event.target.value)}
                            placeholder={state.promptPlaceholder}
                            rows={2}
                            className="w-full rounded-xl border border-[var(--admin-border)] bg-white px-3 py-2 text-sm text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                        />
                    </label>
                ) : null}

                <div className="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        onClick={() => close(false)}
                        className="admin-button admin-button--ghost"
                    >
                        {state.cancelLabel ?? 'Annuler'}
                    </button>
                    <button
                        ref={confirmButtonRef}
                        type="button"
                        onClick={onConfirm}
                        disabled={!canConfirm}
                        className={
                            state.tone === 'danger'
                                ? 'admin-button admin-button--danger disabled:opacity-50'
                                : 'admin-button admin-button--primary disabled:opacity-50'
                        }
                    >
                        {state.confirmLabel ?? 'Confirmer'}
                    </button>
                </div>
            </div>
        </div>
    ) : null;

    return { confirm, dialog };
}
