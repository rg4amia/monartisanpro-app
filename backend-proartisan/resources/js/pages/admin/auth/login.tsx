import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { cn } from '@/lib/utils';

type ThemeMode = 'light' | 'dark';

interface ChallengeData {
    token: string;
    question: string;
    instruction: string;
    action: string;
    expires_in: number;
}

interface LoginPageProps {
    errors: {
        identifier?: string;
        password?: string;
        bot_answer?: string;
    };
    flash?: {
        error?: string | null;
        success?: string | null;
    };
    challenge?: ChallengeData;
}

export default function AdminLoginPage({ errors, flash, challenge }: LoginPageProps) {
    const [themeMode, setThemeMode] = useState<ThemeMode>('light');

    const form = useForm({
        identifier: '',
        password: '',
        remember: true,
        bot_trap: '',
        bot_token: challenge?.token || '',
        bot_answer: '',
    });

    useEffect(() => {
        if (challenge?.token) {
            form.setData('bot_token', challenge.token);
        }
    }, [challenge?.token]);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        // Lecture volontairement différée après l'hydratation : le serveur rend
        // toujours le thème clair, et initialiser l'état directement depuis
        // localStorage provoquerait une divergence d'hydratation côté client.
        // eslint-disable-next-line react-hooks/set-state-in-effect
        setThemeMode(localStorage.getItem('prosartisan_admin_theme') === 'dark' ? 'dark' : 'light');
    }, []);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        localStorage.setItem('prosartisan_admin_theme', themeMode);
    }, [themeMode]);

    return (
        <>
            <Head title="Connexion Backoffice" />

            <div className={cn('admin-shell min-h-screen', themeMode === 'dark' && 'admin-shell--dark')}>
                <div className="relative z-10 flex min-h-screen flex-col px-4 py-5 lg:px-5">
                    <div className="flex justify-end">
                        <button
                            type="button"
                            className="admin-button admin-button--ghost"
                            onClick={() => setThemeMode((current) => (current === 'light' ? 'dark' : 'light'))}
                        >
                            {themeMode === 'light' ? 'Sombre' : 'Clair'}
                        </button>
                    </div>

                    <div className="mx-auto flex w-full max-w-[1180px] flex-1 items-center justify-center py-10">
                        <div className="grid w-full items-center gap-10 lg:grid-cols-[0.92fr_0.78fr]">
                            <div className="hidden lg:block">
                                <div className="flex items-center gap-4">
                                    <div className="flex h-16 w-auto px-4 items-center justify-center rounded-[22px] bg-white text-[#241b16] shadow-[0_12px_30px_rgba(0,0,0,0.08)] border border-[var(--admin-border)]">
                                        <img src="/img/prosartisan-logo.png" alt="ProsArtisan" className="h-10 w-auto object-contain" />
                                    </div>
                                    <div>
                                        <p className="text-sm font-semibold uppercase tracking-[0.26em] text-[var(--admin-muted)]">ProsArtisan</p>
                                        <h1 className="mt-1 text-4xl font-semibold text-[var(--admin-text)]">Backoffice</h1>
                                    </div>
                                </div>

                                <div className="mt-10 max-w-xl space-y-4">
                                    <p className="text-5xl font-semibold leading-tight text-[var(--admin-text)]">
                                        Un espace admin normal, pensé pour piloter la plateforme.
                                    </p>
                                    <p className="max-w-lg text-base leading-7 text-[var(--admin-text-soft)]">
                                        Connectez-vous avec votre e-mail ou votre téléphone, puis gérez les validations KYC, les missions, les litiges et les flux financiers dans une interface unique.
                                    </p>
                                </div>

                                <div className="mt-10 grid gap-4 sm:grid-cols-2">
                                    <div className="admin-panel admin-surface rounded-[28px] border p-5">
                                        <p className="text-xs font-semibold uppercase tracking-[0.22em] text-[var(--admin-muted)]">Pilotage</p>
                                        <p className="mt-3 text-lg font-semibold text-[var(--admin-text)]">KYC, missions, litiges</p>
                                        <p className="mt-2 text-sm leading-6 text-[var(--admin-text-soft)]">
                                            Les équipes admin voient immédiatement ce qui est urgent et ce qui bloque le terrain.
                                        </p>
                                    </div>
                                    <div className="admin-panel admin-surface rounded-[28px] border p-5">
                                        <p className="text-xs font-semibold uppercase tracking-[0.22em] text-[var(--admin-muted)]">Finance</p>
                                        <p className="mt-3 text-lg font-semibold text-[var(--admin-text)]">Séquestre et paiements</p>
                                        <p className="mt-2 text-sm leading-6 text-[var(--admin-text-soft)]">
                                            Lecture claire des acomptes, libérations OTP et règlements fournisseurs.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <section className="admin-panel admin-surface mx-auto w-full max-w-[520px] rounded-[34px] border p-7 lg:p-10">
                                <div className="mx-auto mb-7 flex w-fit items-center gap-3 lg:hidden">
                                    <div className="flex h-14 w-auto px-3.5 items-center justify-center rounded-[20px] bg-white text-[#241b16] shadow-[0_10px_25px_rgba(0,0,0,0.08)] border border-[var(--admin-border)]">
                                        <img src="/img/prosartisan-logo.png" alt="ProsArtisan" className="h-8 w-auto object-contain" />
                                    </div>
                                    <div>
                                        <p className="text-xs font-semibold uppercase tracking-[0.24em] text-[var(--admin-muted)]">ProsArtisan</p>
                                        <h2 className="text-2xl font-semibold text-[var(--admin-text)]">Backoffice</h2>
                                    </div>
                                </div>

                                <p className="text-sm font-semibold uppercase tracking-[0.24em] text-[var(--admin-muted)]">Connexion admin</p>
                                <h2 className="mt-3 text-4xl font-semibold text-[var(--admin-text)]">Bienvenue</h2>
                                <p className="mt-3 text-sm leading-6 text-[var(--admin-text-soft)]">
                                    Réservé aux administrateurs ProsArtisan.
                                </p>

                                <form
                                    className="mt-8 space-y-5"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        form.post('/admin/login');
                                    }}
                                >
                                    <label className="block space-y-2">
                                        <span className="text-xs font-semibold uppercase tracking-[0.22em] text-[var(--admin-muted)]">
                                            E-mail ou téléphone
                                        </span>
                                        <input
                                            value={form.data.identifier}
                                            onChange={(event) => form.setData('identifier', event.target.value)}
                                            className="admin-input w-full rounded-2xl px-4 py-4 text-sm outline-none"
                                            placeholder="admin@prosartisan.ci ou +225..."
                                        />
                                        {errors.identifier ? <p className="text-sm text-[#b24f43]">{errors.identifier}</p> : null}
                                    </label>

                                    <label className="block space-y-2">
                                        <span className="text-xs font-semibold uppercase tracking-[0.22em] text-[var(--admin-muted)]">
                                            Mot de passe
                                        </span>
                                        <input
                                            type="password"
                                            value={form.data.password}
                                            onChange={(event) => form.setData('password', event.target.value)}
                                            className="admin-input w-full rounded-2xl px-4 py-4 text-sm outline-none"
                                            placeholder="Votre mot de passe"
                                        />
                                        {errors.password ? <p className="text-sm text-[#b24f43]">{errors.password}</p> : null}
                                    </label>

                                    {/* Honeypot invisible pour piéger les robots spammeurs */}
                                    <div style={{ display: 'none' }} aria-hidden="true">
                                        <input
                                            type="text"
                                            name="bot_trap"
                                            tabIndex={-1}
                                            autoComplete="off"
                                            value={form.data.bot_trap}
                                            onChange={(event) => form.setData('bot_trap', event.target.value)}
                                        />
                                    </div>

                                    {/* Défi de sécurité Anti-Robot */}
                                    {challenge ? (
                                        <div className="rounded-2xl border border-[var(--admin-border)] bg-[var(--admin-surface)] p-4 shadow-sm space-y-2.5">
                                            <div className="flex items-center justify-between">
                                                <span className="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.16em] text-[var(--admin-muted)]">
                                                    <svg className="w-4 h-4 text-[#c99537]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                                    </svg>
                                                    Vérification Anti-Robot
                                                </span>
                                                <span className="text-[11px] font-medium text-[var(--admin-text-soft)]">Humain requis</span>
                                            </div>
                                            <p className="text-sm font-medium text-[var(--admin-text)]">
                                                {challenge.question}
                                            </p>
                                            <input
                                                type="number"
                                                value={form.data.bot_answer}
                                                onChange={(event) => form.setData('bot_answer', event.target.value)}
                                                className="admin-input w-full rounded-xl px-4 py-2.5 text-sm outline-none"
                                                placeholder="Votre résultat (chiffre)"
                                                required
                                            />
                                            {errors.bot_answer ? <p className="text-sm text-[#b24f43]">{errors.bot_answer}</p> : null}
                                        </div>
                                    ) : null}

                                    <label className="flex items-center gap-3 text-sm text-[var(--admin-text-soft)]">
                                        <input
                                            type="checkbox"
                                            checked={form.data.remember}
                                            onChange={(event) => form.setData('remember', event.target.checked)}
                                            className="h-4 w-4 rounded border-[#cfb894] text-[#c99537] focus:ring-[#e5b763]"
                                        />
                                        Maintenir la session ouverte sur cet appareil
                                    </label>

                                    <button
                                        type="submit"
                                        disabled={form.processing}
                                        className="admin-button admin-button--primary w-full justify-center disabled:cursor-not-allowed disabled:opacity-70"
                                    >
                                        {form.processing ? 'Connexion...' : 'Se connecter'}
                                    </button>
                                </form>

                                {flash?.success ? (
                                    <div className="mt-4 rounded-[22px] border border-[#c5dfca] bg-[#eef8f0] px-4 py-3 text-sm text-[#24734f]">
                                        {flash.success}
                                    </div>
                                ) : null}

                                {flash?.error ? (
                                    <div className="mt-4 rounded-[22px] border border-[#efc1b9] bg-[#fff3ef] px-4 py-3 text-sm text-[#b24f43]">
                                        {flash.error}
                                    </div>
                                ) : null}

                                <p className="mt-6 text-center text-sm text-[var(--admin-text-soft)]">
                                    Retour à l’accueil{' '}
                                    <Link href="/" className="font-medium text-[#b77918] transition hover:text-[#8a5d16]">
                                        ProsArtisan
                                    </Link>
                                </p>
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
