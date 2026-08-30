'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import { Cookie, Check, X, Settings2, ExternalLink } from 'lucide-react';

const STORAGE_KEY = 'prosartisan_cookie_consent_v1';

export interface CookiePreferences {
    essential: boolean;
    analytics: boolean;
    preferences: boolean;
    timestamp: number;
}

export default function CookieConsent() {
    const [isVisible, setIsVisible] = useState(false);
    const [showCustomize, setShowCustomize] = useState(false);
    
    // Cookie preference switches initialized from storage
    const [analyticsAllowed, setAnalyticsAllowed] = useState<boolean>(() => {
        if (typeof window !== 'undefined') {
            try {
                const stored = localStorage.getItem(STORAGE_KEY);
                if (stored) {
                    const parsed: CookiePreferences = JSON.parse(stored);
                    return parsed.analytics ?? true;
                }
            } catch {}
        }
        return true;
    });

    const [preferencesAllowed, setPreferencesAllowed] = useState<boolean>(() => {
        if (typeof window !== 'undefined') {
            try {
                const stored = localStorage.getItem(STORAGE_KEY);
                if (stored) {
                    const parsed: CookiePreferences = JSON.parse(stored);
                    return parsed.preferences ?? true;
                }
            } catch {}
        }
        return true;
    });

    useEffect(() => {
        let timer: ReturnType<typeof setTimeout> | null = null;
        try {
            const stored = localStorage.getItem(STORAGE_KEY);
            if (!stored) {
                timer = setTimeout(() => setIsVisible(true), 800);
            }
        } catch {
            timer = setTimeout(() => setIsVisible(true), 800);
        }

        // Listener to allow reopening cookie settings from Footer or links
        const handleOpenSettings = () => {
            setShowCustomize(true);
            setIsVisible(true);
        };

        window.addEventListener('open-cookie-settings', handleOpenSettings);
        return () => {
            if (timer) clearTimeout(timer);
            window.removeEventListener('open-cookie-settings', handleOpenSettings);
        };
    }, []);

    const savePreferences = (prefs: CookiePreferences) => {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
        } catch (e) {
            console.error('Erreur sauvegarde cookies consent:', e);
        }
        setIsVisible(false);
        setShowCustomize(false);
    };

    const handleAcceptAll = () => {
        savePreferences({
            essential: true,
            analytics: true,
            preferences: true,
            timestamp: Date.now(),
        });
    };

    const handleRejectNonEssential = () => {
        savePreferences({
            essential: true,
            analytics: false,
            preferences: false,
            timestamp: Date.now(),
        });
    };

    const handleSaveCustom = () => {
        savePreferences({
            essential: true,
            analytics: analyticsAllowed,
            preferences: preferencesAllowed,
            timestamp: Date.now(),
        });
    };

    if (!isVisible) return null;

    return (
        <>
            {/* Backdrop when modal customization is open */}
            {showCustomize && (
                <div 
                    className="fixed inset-0 bg-black/60 backdrop-blur-sm z-[9998] transition-opacity duration-300"
                    onClick={() => setShowCustomize(false)}
                />
            )}

            {/* Customization Modal */}
            {showCustomize ? (
                <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4">
                    <div className="bg-white rounded-3xl shadow-2xl border border-[#e6d3b2]/60 w-full max-w-xl max-h-[90vh] overflow-y-auto p-6 sm:p-8 animate-in fade-in zoom-in-95 duration-200 text-[#241b16]">
                        {/* Header */}
                        <div className="flex items-center justify-between border-b border-[#f0e7db] pb-4 mb-6">
                            <div className="flex items-center gap-3">
                                <div className="p-2.5 bg-[#ebb95e]/20 text-[#8c4308] rounded-2xl">
                                    <Settings2 className="w-6 h-6" />
                                </div>
                                <div>
                                    <h3 className="text-lg font-bold text-[#241b16]">
                                        Centre de préférences des cookies
                                    </h3>
                                    <p className="text-xs text-[#716558]">
                                        Personnalisez vos choix de navigation sur ProsArtisan
                                    </p>
                                </div>
                            </div>
                            <button
                                onClick={() => setShowCustomize(false)}
                                className="p-2 hover:bg-[#fbf9f6] text-[#716558] hover:text-[#241b16] rounded-full transition"
                                aria-label="Fermer"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        {/* Content description */}
                        <p className="text-xs sm:text-sm text-[#716558] leading-relaxed mb-6">
                            Nous utilisons différents types de cookies pour optimiser votre expérience, assurer la sécurité des transactions (Wave, Orange Money) et mesurer l&apos;audience de nos services. Vous pouvez choisir d&apos;activer ou de désactiver chaque catégorie à tout moment.
                        </p>

                        {/* Cookie Categories */}
                        <div className="space-y-4 mb-8">
                            {/* 1. Essential */}
                            <div className="p-4 rounded-2xl bg-[#fbf9f6] border border-[#f0e7db] flex items-start justify-between gap-4">
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        <span className="font-bold text-sm text-[#241b16]">Cookies Essentiels & Sécurité</span>
                                        <span className="px-2 py-0.5 text-[10px] font-bold bg-[#10b981]/15 text-[#059669] rounded-full uppercase tracking-wider">
                                            Toujours actif
                                        </span>
                                    </div>
                                    <p className="text-xs text-[#716558] leading-relaxed">
                                        Indispensables au fonctionnement du site, à l&apos;authentification sécurisée, à la prévention des fraudes et à l&apos;initiation des paiements chantiers.
                                    </p>
                                </div>
                                <div className="p-2 text-[#059669]">
                                    <Check className="w-5 h-5" />
                                </div>
                            </div>

                            {/* 2. Analytics */}
                            <div className="p-4 rounded-2xl bg-white border border-[#f0e7db] hover:border-[#ebb95e]/60 transition flex items-start justify-between gap-4">
                                <div className="space-y-1 flex-1">
                                    <div className="flex items-center gap-2">
                                        <span className="font-bold text-sm text-[#241b16]">Mesure d&apos;audience & Performance</span>
                                    </div>
                                    <p className="text-xs text-[#716558] leading-relaxed">
                                        Nous aident à comprendre comment les visiteurs naviguent sur la vitrine, à détecter d&apos;éventuelles erreurs techniques et à perfectionner la mise en relation chantiers.
                                    </p>
                                </div>
                                <label className="relative inline-flex items-center cursor-pointer shrink-0 mt-1">
                                    <input
                                        type="checkbox"
                                        checked={analyticsAllowed}
                                        onChange={(e) => setAnalyticsAllowed(e.target.checked)}
                                        className="sr-only peer"
                                    />
                                    <div className="w-11 h-6 bg-[#e6d3b2] peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-[#d97706] after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#d97706]"></div>
                                </label>
                            </div>

                            {/* 3. Preferences */}
                            <div className="p-4 rounded-2xl bg-white border border-[#f0e7db] hover:border-[#ebb95e]/60 transition flex items-start justify-between gap-4">
                                <div className="space-y-1 flex-1">
                                    <div className="flex items-center gap-2">
                                        <span className="font-bold text-sm text-[#241b16]">Personnalisation & Expérience</span>
                                    </div>
                                    <p className="text-xs text-[#716558] leading-relaxed">
                                        Permettent de conserver vos filtres de recherche (métiers, communes d&apos;Abidjan/Côte d&apos;Ivoire) et vos préférences visuelles.
                                    </p>
                                </div>
                                <label className="relative inline-flex items-center cursor-pointer shrink-0 mt-1">
                                    <input
                                        type="checkbox"
                                        checked={preferencesAllowed}
                                        onChange={(e) => setPreferencesAllowed(e.target.checked)}
                                        className="sr-only peer"
                                    />
                                    <div className="w-11 h-6 bg-[#e6d3b2] peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-[#d97706] after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#d97706]"></div>
                                </label>
                            </div>
                        </div>

                        {/* Footer Legal & Actions */}
                        <div className="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-[#f0e7db]">
                            <Link 
                                href="/politique-confidentialite" 
                                className="text-xs text-[#8c4308] hover:underline flex items-center gap-1 font-medium"
                                target="_blank"
                            >
                                <span>Politique de confidentialité</span>
                                <ExternalLink className="w-3 h-3" />
                            </Link>

                            <div className="flex items-center gap-3 w-full sm:w-auto">
                                <button
                                    type="button"
                                    onClick={handleSaveCustom}
                                    className="flex-1 sm:flex-none px-5 py-2.5 rounded-xl border border-[#d97706] text-[#8c4308] font-bold text-xs hover:bg-[#ebb95e]/10 transition"
                                >
                                    Enregistrer mes choix
                                </button>
                                <button
                                    type="button"
                                    onClick={handleAcceptAll}
                                    className="flex-1 sm:flex-none px-6 py-2.5 rounded-xl bg-gradient-to-r from-[#d97706] to-[#8c4308] text-white font-bold text-xs shadow-md hover:shadow-lg hover:brightness-105 transition"
                                >
                                    Tout accepter
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            ) : (
                /* Floating Bottom Banner */
                <div className="fixed bottom-4 left-4 right-4 sm:left-6 sm:right-6 md:left-auto md:right-8 md:max-w-xl z-[9990] animate-in slide-in-from-bottom-8 duration-500">
                    <div className="bg-[#241b16]/95 backdrop-blur-md text-white p-5 sm:p-6 rounded-3xl shadow-2xl border border-[#ebb95e]/30 flex flex-col gap-4">
                        <div className="flex items-start gap-3.5">
                            <div className="p-2.5 bg-[#ebb95e] text-[#241b16] rounded-2xl shrink-0 mt-0.5 shadow-md">
                                <Cookie className="w-6 h-6" />
                            </div>
                            <div className="space-y-1">
                                <h4 className="text-sm sm:text-base font-bold text-white flex items-center gap-2">
                                    <span>Gestion de vos cookies & vie privée</span>
                                </h4>
                                <p className="text-xs sm:text-sm text-[#efe6da]/85 leading-relaxed">
                                    Nous utilisons des cookies essentiels et analytiques pour assurer le bon fonctionnement de ProsArtisan, sécuriser vos transactions et améliorer la mise en relation avec nos artisans et quincailleries partenaires.
                                </p>
                            </div>
                        </div>

                        {/* Actions */}
                        <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 pt-2 border-t border-white/10">
                            <div className="flex items-center gap-3 text-xs text-[#efe6da]/70">
                                <button
                                    type="button"
                                    onClick={() => setShowCustomize(true)}
                                    className="underline hover:text-[#ebb95e] transition text-left flex items-center gap-1 font-medium"
                                >
                                    <Settings2 className="w-3.5 h-3.5" />
                                    <span>Personnaliser</span>
                                </button>
                                <span>•</span>
                                <Link
                                    href="/politique-confidentialite"
                                    className="hover:text-white underline transition"
                                >
                                    En savoir plus
                                </Link>
                            </div>

                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    onClick={handleRejectNonEssential}
                                    className="flex-1 sm:flex-none px-4 py-2 rounded-xl border border-white/20 text-white hover:bg-white/10 text-xs font-semibold transition"
                                >
                                    Refuser non-essentiels
                                </button>
                                <button
                                    type="button"
                                    onClick={handleAcceptAll}
                                    className="flex-1 sm:flex-none px-5 py-2 rounded-xl bg-gradient-to-r from-[#ebb95e] to-[#d97706] text-[#241b16] hover:brightness-110 text-xs font-bold shadow-md transition"
                                >
                                    Tout accepter
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
