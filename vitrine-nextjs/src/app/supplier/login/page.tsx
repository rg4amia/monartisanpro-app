'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';

export default function SupplierLogin() {
    const router = useRouter();
    const [phone, setPhone] = useState('');
    const [otp, setOtp] = useState('');
    const [step, setStep] = useState<'phone' | 'otp'>('phone');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState<string | null>(null);
    const [timer, setTimer] = useState(0);

    useEffect(() => {
        // Redirection si déjà connecté
        if (typeof window !== 'undefined' && localStorage.getItem('supplier_token')) {
            router.push('/supplier');
        }
    }, [router]);

    useEffect(() => {
        if (timer > 0) {
            const interval = setInterval(() => setTimer(t => t - 1), 1000);
            return () => clearInterval(interval);
        }
    }, [timer]);

    const handleSendOtp = async (e: React.FormEvent) => {
        e.preventDefault();
        setError(null);
        setSuccess(null);
        setLoading(true);

        const cleanPhone = phone.trim();
        if (!cleanPhone) {
            setError('Veuillez entrer un numéro de téléphone.');
            setLoading(false);
            return;
        }

        try {
            const sent = await api.supplierSendOtp(cleanPhone);
            if (sent) {
                setStep('otp');
                setTimer(60);
                setSuccess('Code OTP envoyé par SMS.');
            } else {
                setError("Échec de l'envoi du code. Vérifiez le numéro.");
            }
        } catch (err: any) {
            setError(err.message || "Erreur lors de l'envoi du code OTP.");
        } finally {
            setLoading(false);
        }
    };

    const handleVerifyOtp = async (e: React.FormEvent) => {
        e.preventDefault();
        setError(null);
        setSuccess(null);
        setLoading(true);

        if (!otp.trim() || otp.length < 4) {
            setError('Veuillez entrer le code OTP à 4 chiffres.');
            setLoading(false);
            return;
        }

        try {
            const credentials = await api.supplierVerifyOtp(phone, otp);
            
            // Vérifier le rôle de l'utilisateur
            const user = credentials.user;
            if (user.role !== 'fournisseur') {
                setError("Accès refusé. Cet espace est réservé exclusivement aux fournisseurs agréés.");
                setLoading(false);
                return;
            }

            // Sauvegarder les identifiants
            localStorage.setItem('supplier_token', credentials.token);
            localStorage.setItem('supplier_user', JSON.stringify(user));
            
            setSuccess('Connexion réussie ! Redirection...');
            setTimeout(() => {
                router.push('/supplier');
            }, 1000);
        } catch (err: any) {
            setError(err.message || 'Code OTP invalide ou expiré.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-4 relative overflow-hidden">
            {/* Background Gradients */}
            <div className="absolute top-[-20%] left-[-10%] w-[500px] h-[500px] rounded-full bg-amber-500/10 blur-[120px] pointer-events-none" />
            <div className="absolute bottom-[-20%] right-[-10%] w-[500px] h-[500px] rounded-full bg-blue-500/10 blur-[120px] pointer-events-none" />

            <div className="w-full max-w-md bg-slate-900/60 backdrop-blur-xl border border-slate-800 p-8 rounded-2xl shadow-2xl relative z-10">
                <div className="flex flex-col items-center mb-8">
                    <span className="bg-gradient-to-r from-amber-500 to-amber-600 text-slate-950 font-extrabold px-4 py-1.5 rounded-lg text-xs tracking-wider uppercase mb-3 shadow-lg shadow-amber-500/20">
                        PROSARTISAN
                    </span>
                    <h1 className="text-2xl font-bold tracking-tight text-white">Espace Fournisseur</h1>
                    <p className="text-slate-400 text-sm mt-1.5 text-center">
                        Accédez à votre console de vente et gérez vos stocks de quincaillerie.
                    </p>
                </div>

                {error && (
                    <div className="bg-rose-950/40 border border-rose-800 text-rose-300 px-4 py-3 rounded-lg text-sm mb-6 flex items-start gap-2.5">
                        <span className="font-semibold text-rose-400">⚠️</span>
                        <span>{error}</span>
                    </div>
                )}

                {success && (
                    <div className="bg-emerald-950/40 border border-emerald-800 text-emerald-300 px-4 py-3 rounded-lg text-sm mb-6 flex items-start gap-2.5">
                        <span className="font-semibold text-emerald-400">✓</span>
                        <span>{success}</span>
                    </div>
                )}

                {step === 'phone' ? (
                    <form onSubmit={handleSendOtp} className="space-y-5">
                        <div>
                            <label className="block text-slate-300 text-sm font-semibold mb-2" htmlFor="phone">
                                Numéro de Téléphone
                            </label>
                            <div className="relative">
                                <span className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 text-sm font-medium">
                                    +225
                                </span>
                                <input
                                    id="phone"
                                    type="tel"
                                    placeholder="0700000000"
                                    value={phone}
                                    onChange={(e) => setPhone(e.target.value.replace(/\D/g, ''))}
                                    className="w-full bg-slate-950/80 border border-slate-800 text-white rounded-lg pl-16 pr-4 py-3 text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none transition"
                                    required
                                    disabled={loading}
                                />
                            </div>
                            <p className="text-slate-500 text-xs mt-1.5">
                                Format 10 chiffres (ex: 07 00 00 00 00)
                            </p>
                        </div>

                        <button
                            type="submit"
                            disabled={loading}
                            className="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold py-3 rounded-lg text-sm transition shadow-lg shadow-amber-500/20 active:scale-[0.98]"
                        >
                            {loading ? 'Envoi en cours...' : 'Obtenir le code OTP'}
                        </button>
                    </form>
                ) : (
                    <form onSubmit={handleVerifyOtp} className="space-y-5">
                        <div>
                            <label className="block text-slate-300 text-sm font-semibold mb-2" htmlFor="otp">
                                Code OTP
                            </label>
                            <input
                                id="otp"
                                type="text"
                                maxLength={4}
                                placeholder="----"
                                value={otp}
                                onChange={(e) => setOtp(e.target.value.replace(/\D/g, ''))}
                                className="w-full bg-slate-950/80 border border-slate-800 text-white rounded-lg px-4 py-3 text-center text-lg font-bold tracking-widest focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none transition"
                                required
                                disabled={loading}
                            />
                            <p className="text-slate-500 text-xs mt-1.5 text-center">
                                Entrez le code à 4 chiffres envoyé au +225 {phone}
                            </p>
                        </div>

                        <button
                            type="submit"
                            disabled={loading}
                            className="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold py-3 rounded-lg text-sm transition shadow-lg shadow-amber-500/20 active:scale-[0.98]"
                        >
                            {loading ? 'Vérification...' : 'Se connecter'}
                        </button>

                        <div className="text-center pt-2">
                            <button
                                type="button"
                                onClick={() => setStep('phone')}
                                className="text-slate-400 hover:text-white text-xs font-semibold underline transition"
                                disabled={loading}
                            >
                                Modifier le numéro de téléphone
                            </button>
                        </div>

                        {timer > 0 ? (
                            <p className="text-slate-500 text-xs text-center">
                                Renvoyer le code dans {timer}s
                            </p>
                        ) : (
                            <div className="text-center">
                                <button
                                    type="button"
                                    onClick={handleSendOtp}
                                    className="text-amber-500 hover:text-amber-400 text-xs font-semibold transition"
                                    disabled={loading}
                                >
                                    Renvoyer un nouveau code OTP
                                </button>
                            </div>
                        )}
                    </form>
                )}
            </div>
        </div>
    );
}
