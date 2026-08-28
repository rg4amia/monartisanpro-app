'use client';

import { useState } from 'react';
import { api } from '@/lib/api';
import { Phone, Mail, MapPin, Send, CheckCircle2, ShieldAlert } from 'lucide-react';

export default function ContactPage() {
    const [nom, setNom] = useState('');
    const [email, setEmail] = useState('');
    const [sujet, setSujet] = useState('');
    const [message, setMessage] = useState('');

    const [loading, setLoading] = useState(false);
    const [result, setResult] = useState<{ success: boolean; message: string } | null>(null);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setResult(null);

        try {
            const res = await api.sendContact({ nom, email, sujet, message });
            setResult(res);
            if (res.success) {
                setNom('');
                setEmail('');
                setSujet('');
                setMessage('');
            }
        } catch (err) {
            console.error('Contact submission error:', err);
            setResult({ success: false, message: "Une erreur est survenue lors de l'envoi." });
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="bg-[#fbf9f6] py-16 min-h-screen">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                {/* Header */}
                <div className="text-center max-w-3xl mx-auto space-y-4 mb-20">
                    <span className="text-[11px] font-extrabold uppercase tracking-[0.24em] text-[#8a5d16]">contactez-nous</span>
                    <h1 className="text-4xl sm:text-5xl font-extrabold text-[#241b16] tracking-tight leading-tight">
                        Une question ? Un projet de chantier ?
                    </h1>
                    <p className="text-sm text-[#746251] leading-relaxed">
                        Nos conseillers et référents de zone ProsArtisan vous répondent rapidement. Que vous soyez un artisan souhaitant s&apos;inscrire ou un client avec un projet de construction/rénovation, écrivez-nous.
                    </p>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-12 max-w-5xl mx-auto">
                    {/* Contact Details (1/3) */}
                    <div className="space-y-8">
                        <div className="bg-white border border-[#e6d3b2] rounded-[32px] p-8 space-y-6 shadow-sm">
                            <h3 className="font-extrabold text-[#241b16] text-lg">Nos coordonnées</h3>
                            
                            <div className="space-y-4 text-xs text-[#746251]">
                                <div className="flex items-center gap-3">
                                    <div className="p-2.5 bg-[#f7efe2]/60 rounded-xl text-[#8a5d16]">
                                        <Phone className="h-4 w-4" />
                                    </div>
                                    <div className="space-y-0.5">
                                        <p className="font-bold text-[#241b16]">Téléphone</p>
                                        <a href="tel:+2250700000000" className="hover:underline">+225 07 00 00 00 00</a>
                                    </div>
                                </div>

                                <div className="flex items-center gap-3">
                                    <div className="p-2.5 bg-[#f7efe2]/60 rounded-xl text-[#8a5d16]">
                                        <Mail className="h-4 w-4" />
                                    </div>
                                    <div className="space-y-0.5">
                                        <p className="font-bold text-[#241b16]">Email</p>
                                        <a href="mailto:contact@prosartisan.ci" className="hover:underline">contact@prosartisan.ci</a>
                                    </div>
                                </div>

                                <div className="flex items-start gap-3">
                                    <div className="p-2.5 bg-[#f7efe2]/60 rounded-xl text-[#8a5d16] mt-0.5">
                                        <MapPin className="h-4 w-4" />
                                    </div>
                                    <div className="space-y-0.5">
                                        <p className="font-bold text-[#241b16]">Siège social</p>
                                        <p>Plateau, Boulevard de la République,<br />Abidjan, Côte d&apos;Ivoire</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Contact Form (2/3) */}
                    <div className="lg:col-span-2">
                        <div className="bg-white border border-[#e6d3b2] rounded-[32px] p-8 shadow-sm">
                            <h3 className="font-extrabold text-[#241b16] text-lg mb-6">Formulaire de contact</h3>

                            {result && (
                                <div className={`mb-6 p-4 rounded-2xl border text-xs flex items-start gap-3 ${
                                    result.success 
                                        ? 'bg-[#eef8f0] border-[#c5dfca] text-[#24734f]' 
                                        : 'bg-[#fff3ef] border-[#efc1b9] text-[#b24f43]'
                                }`}>
                                    {result.success ? (
                                        <CheckCircle2 className="h-5 w-5 shrink-0" />
                                    ) : (
                                        <ShieldAlert className="h-5 w-5 shrink-0" />
                                    )}
                                    <span>{result.message}</span>
                                </div>
                            )}

                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <label className="block space-y-1">
                                        <span className="text-[10px] font-bold uppercase tracking-wider text-[#746251]">Votre Nom</span>
                                        <input
                                            type="text"
                                            required
                                            value={nom}
                                            onChange={(e) => setNom(e.target.value)}
                                            placeholder="Ex: Kouamé Koffi"
                                            className="w-full rounded-xl border border-[#e6d3b2]/60 px-4 py-3 text-xs text-[#241b16] focus:border-[#8a5d16] focus:outline-none"
                                        />
                                    </label>
                                    
                                    <label className="block space-y-1">
                                        <span className="text-[10px] font-bold uppercase tracking-wider text-[#746251]">Votre Adresse Email</span>
                                        <input
                                            type="email"
                                            required
                                            value={email}
                                            onChange={(e) => setEmail(e.target.value)}
                                            placeholder="Ex: kouame@gmail.com"
                                            className="w-full rounded-xl border border-[#e6d3b2]/60 px-4 py-3 text-xs text-[#241b16] focus:border-[#8a5d16] focus:outline-none"
                                        />
                                    </label>
                                </div>

                                <label className="block space-y-1">
                                    <span className="text-[10px] font-bold uppercase tracking-wider text-[#746251]">Sujet du message</span>
                                    <input
                                        type="text"
                                        required
                                        value={sujet}
                                        onChange={(e) => setSujet(e.target.value)}
                                        placeholder="Ex: Demande d'estimation devis de plomberie"
                                        className="w-full rounded-xl border border-[#e6d3b2]/60 px-4 py-3 text-xs text-[#241b16] focus:border-[#8a5d16] focus:outline-none"
                                    />
                                </label>

                                <label className="block space-y-1">
                                    <span className="text-[10px] font-bold uppercase tracking-wider text-[#746251]">Votre Message</span>
                                    <textarea
                                        required
                                        rows={5}
                                        value={message}
                                        onChange={(e) => setMessage(e.target.value)}
                                        placeholder="Décrivez votre besoin en détail..."
                                        className="w-full rounded-xl border border-[#e6d3b2]/60 px-4 py-3 text-xs text-[#241b16] focus:border-[#8a5d16] focus:outline-none resize-none"
                                    />
                                </label>

                                <div className="pt-2">
                                    <button
                                        type="submit"
                                        disabled={loading}
                                        className="w-full bg-[#241b16] hover:bg-[#8a5d16] text-white text-xs font-bold py-3.5 rounded-full flex items-center justify-center gap-2 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <Send className="h-4 w-4" />
                                        <span>{loading ? "Envoi en cours..." : "Envoyer le message"}</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
