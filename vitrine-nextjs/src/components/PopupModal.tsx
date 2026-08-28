'use client';

import { useEffect, useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { X, ArrowRight } from 'lucide-react';
import { api, Popup } from '@/lib/api';
import Link from 'next/link';

export default function PopupModal() {
    const [popup, setPopup] = useState<Popup | null>(null);
    const [isVisible, setIsVisible] = useState(false);

    useEffect(() => {
        const loadPopup = async () => {
            const activePopup = await api.getPopup();
            if (activePopup) {
                // Check if already dismissed in this session
                const dismissed = sessionStorage.getItem(`popup_dismissed_${activePopup.id}`);
                if (!dismissed) {
                    setPopup(activePopup);
                    // Show popup after a slight delay
                    setTimeout(() => {
                        setIsVisible(true);
                    }, 1500);
                }
            }
        };

        loadPopup();
    }, []);

    const dismissPopup = () => {
        if (popup) {
            sessionStorage.setItem(`popup_dismissed_${popup.id}`, 'true');
        }
        setIsVisible(false);
    };

    if (!popup) return null;

    return (
        <AnimatePresence>
            {isVisible && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    {/* Backdrop */}
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        onClick={dismissPopup}
                        className="fixed inset-0 bg-black/60 backdrop-blur-sm"
                    />

                    {/* Modal Content */}
                    <motion.div
                        initial={{ opacity: 0, scale: 0.9, y: 20 }}
                        animate={{ opacity: 1, scale: 1, y: 0 }}
                        exit={{ opacity: 0, scale: 0.9, y: 20 }}
                        transition={{ type: 'spring', damping: 25, stiffness: 350 }}
                        className="relative bg-white rounded-[32px] border border-[#e6d3b2] w-full max-w-[500px] overflow-hidden shadow-2xl z-10 p-6 sm:p-8"
                    >
                        {/* Close button */}
                        <button
                            onClick={dismissPopup}
                            className="absolute top-4 right-4 p-2 rounded-full hover:bg-[#efe6da]/40 text-[#241b16] transition"
                            aria-label="Fermer"
                        >
                            <X className="h-5 w-5" />
                        </button>

                        <div className="space-y-5 text-center mt-3">
                            <span className="inline-block px-3 py-1 bg-[#f7efe2] border border-[#e6d3b2]/50 text-[#8a5d16] text-[10px] font-extrabold uppercase tracking-wider rounded-full">
                                offre exclusive
                            </span>

                            <h3 className="text-xl sm:text-2xl font-extrabold text-[#241b16] leading-tight">
                                {popup.titre}
                            </h3>

                            {popup.image_url && (
                                <div className="rounded-2xl overflow-hidden border border-[#e6d3b2]/30 max-h-48">
                                    <img
                                        src={popup.image_url}
                                        alt={popup.titre}
                                        className="w-full object-cover"
                                    />
                                </div>
                            )}

                            {popup.contenu && (
                                <p className="text-sm text-[#746251] leading-relaxed">
                                    {popup.contenu}
                                </p>
                            )}

                            <div className="pt-2 flex flex-col sm:flex-row gap-3">
                                <button
                                    onClick={dismissPopup}
                                    className="flex-1 px-5 py-3 rounded-full border border-[#e6d3b2] text-sm font-bold text-[#746251] hover:bg-[#efe6da]/20 transition"
                                >
                                    Fermer
                                </button>
                                {popup.lien_cta && (
                                    <Link
                                        href={popup.lien_cta}
                                        onClick={dismissPopup}
                                        className="flex-1 bg-[#241b16] hover:bg-[#8a5d16] text-[#fbf9f6] text-sm font-bold px-5 py-3 rounded-full flex items-center justify-center gap-1.5 transition shadow-md"
                                    >
                                        <span>{popup.texte_cta || 'Profiter'}</span>
                                        <ArrowRight className="h-4 w-4" />
                                    </Link>
                                )}
                            </div>
                        </div>
                    </motion.div>
                </div>
            )}
        </AnimatePresence>
    );
}
