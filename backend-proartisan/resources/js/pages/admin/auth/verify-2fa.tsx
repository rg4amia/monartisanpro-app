import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { cn } from '@/lib/utils';

type ThemeMode = 'light' | 'dark';

interface Verify2faPageProps {
    isConfigured: boolean;
    secret?: string;
    qrCodeUrl?: string;
    errors: {
        code?: string;
    };
    flash?: {
        error?: string | null;
        success?: string | null;
    };
}

export default function Verify2faPage({ isConfigured, secret, qrCodeUrl, errors, flash }: Verify2faPageProps) {
    const [themeMode, setThemeMode] = useState<ThemeMode>('light');

    const form = useForm({
        code: '',
    });

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

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
            <Head title="Validation Double Facteur" />

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
                                    <div className="flex h-16 w-16 items-center justify-center rounded-[22px] bg-[#ebb95e] text-[#241b16] shadow-[0_20px_50px_rgba(210,152,52,0.24)]">
                                        <img src="/img/prosartisan-logo.png" alt="ProsArtisan" className="h-10 w-10 object-contain" />
                                    </div>
                                    <div>
                                        <p className="text-sm font-semibold uppercase tracking-[0.26em] text-[var(--admin-muted)]">ProsArtisan</p>
                                        <h1 className="mt-1 text-4xl font-semibold text-[var(--admin-text)]">Backoffice</h1>
                                    </div>
                                </div>

                                <div className="mt-10 max-w-xl space-y-4">
                                    <p className="text-5xl font-semibold leading-tight text-[var(--admin-text)]">
                                        Double Facteur Google Authenticator
                                    </p>
                                    <p className="max-w-lg text-base leading-7 text-[var(--admin-text-soft)]">
                                        Sécurisez vos accès administrateurs avec une clé à usage unique basée sur le temps (TOTP). Protégez les données de nos clients, artisans et partenaires.
                                    </p>
                                </div>

                                <div className="mt-10 grid gap-4 sm:grid-cols-2">
                                    <div className="admin-panel admin-surface rounded-[28px] border p-5">
                                        <p className="text-xs font-semibold uppercase tracking-[0.22em] text-[var(--admin-muted)]">TOTP</p>
                                        <p className="mt-3 text-lg font-semibold text-[var(--admin-text)]">Sécurité Maximale</p>
                                        <p className="mt-2 text-sm leading-6 text-[var(--admin-text-soft)]">
                                            Algorithme de validation hors-ligne conforme aux standards bancaires mondiaux.
                                        </p>
                                    </div>
                                    <div className="admin-panel admin-surface rounded-[28px] border p-5">
                                        <p className="text-xs font-semibold uppercase tracking-[0.22em] text-[var(--admin-muted)]">Configuration</p>
                                        <p className="mt-3 text-lg font-semibold text-[var(--admin-text)]">Enrôlement Rapide</p>
                                        <p className="mt-2 text-sm leading-6 text-[var(--admin-text-soft)]">
                                            Scannez simplement le code QR avec Google Authenticator ou l'application de votre choix.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <section className="admin-panel admin-surface mx-auto w-full max-w-[520px] rounded-[34px] border p-7 lg:p-10">
                                <div className="mx-auto mb-7 flex w-fit items-center gap-3 lg:hidden">
                                    <div className="flex h-14 w-14 items-center justify-center rounded-[20px] bg-[#ebb95e] text-[#241b16] shadow-[0_18px_40px_rgba(210,152,52,0.24)]">
                                        <img src="/img/prosartisan-logo.png" alt="ProsArtisan" className="h-8 w-8 object-contain" />
                                    </div>
                                    <div>
                                        <p className="text-xs font-semibold uppercase tracking-[0.24em] text-[var(--admin-muted)]">ProsArtisan</p>
                                        <h2 className="text-2xl font-semibold text-[var(--admin-text)]">Backoffice</h2>
                                    </div>
                                </div>

                                <p className="text-sm font-semibold uppercase tracking-[0.24em] text-[var(--admin-muted)]">Sécurité 2FA</p>
                                
                                {!isConfigured ? (
                                    <>
                                        <h2 className="mt-3 text-2xl font-semibold text-[var(--admin-text)]">Configuration Double Facteur</h2>
                                        <p className="mt-2 text-sm leading-6 text-[var(--admin-text-soft)]">
                                            Scannez le QR Code ci-dessous avec votre application d'authentification (Google Authenticator, Authy, etc.).
                                        </p>

                                        {qrCodeUrl && (
                                            <div className="my-6 flex flex-col items-center justify-center rounded-2xl border border-[var(--admin-border)] bg-white p-5 shadow-sm">
                                                <img src={qrCodeUrl} alt="QR Code 2FA" className="h-44 w-44 object-contain" />
                                                {secret && (
                                                    <div className="mt-3 text-center">
                                                        <p className="text-xs font-semibold uppercase tracking-wider text-gray-400">Clé de secours</p>
                                                        <code className="mt-1 block rounded bg-gray-100 px-2 py-1 text-sm font-mono text-gray-700 tracking-widest">{secret}</code>
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </>
                                ) : (
                                    <>
                                        <h2 className="mt-3 text-3xl font-semibold text-[var(--admin-text)]">Code de Sécurité</h2>
                                        <p className="mt-3 text-sm leading-6 text-[var(--admin-text-soft)]">
                                            Saisissez le code à 6 chiffres généré par votre application Authenticator pour valider votre connexion.
                                        </p>

                                        <div className="my-6 flex justify-center py-4">
                                            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-[#ebb95e]/10 text-[#c99537]">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="h-8 w-8">
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                                </svg>
                                            </div>
                                        </div>
                                    </>
                                )}

                                <form
                                    className="space-y-5"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        form.post('/admin/login/verify-2fa');
                                    }}
                                >
                                    <label className="block space-y-2">
                                        <span className="text-xs font-semibold uppercase tracking-[0.22em] text-[var(--admin-muted)]">
                                            Code à 6 chiffres
                                        </span>
                                        <input
                                            type="text"
                                            inputMode="numeric"
                                            pattern="[0-9]*"
                                            maxLength={6}
                                            value={form.data.code}
                                            onChange={(event) => form.setData('code', event.target.value.replace(/\D/g, ''))}
                                            className="admin-input w-full rounded-2xl px-4 py-4 text-center text-xl font-bold tracking-[0.4em] outline-none"
                                            placeholder="000000"
                                            autoFocus
                                            required
                                        />
                                        {errors.code ? <p className="text-sm text-[#b24f43]">{errors.code}</p> : null}
                                    </label>

                                    <button
                                        type="submit"
                                        disabled={form.processing}
                                        className="admin-button admin-button--primary w-full justify-center disabled:cursor-not-allowed disabled:opacity-70"
                                    >
                                        {form.processing ? 'Vérification...' : 'Valider'}
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
                                    Retour à la{' '}
                                    <Link href="/admin/login" className="font-medium text-[#b77918] transition hover:text-[#8a5d16]">
                                        connexion
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
