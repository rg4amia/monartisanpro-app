'use client';

import Link from 'next/link';
import { Phone, Mail, MapPin, ShieldCheck, HeartHandshake, Award } from 'lucide-react';

export default function Footer() {
    const currentYear = new Date().getFullYear();

    return (
        <footer className="bg-[#241b16] text-[#efe6da] pt-16 pb-8 border-t border-[#e6d3b2]/10">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12">
                    {/* Brand Section */}
                    <div className="space-y-4">
                        <div className="flex items-center gap-2.5">
                            <img
                                src="/img/prosartisan-logo.png"
                                alt="ProsArtisan"
                                className="h-9 w-9 object-contain"
                            />
                            <span className="font-extrabold text-lg tracking-tight text-white">
                                ProsArtisan
                            </span>
                        </div>
                        <p className="text-xs text-[#efe6da]/70 leading-relaxed">
                            Première plateforme de confiance en Côte d'Ivoire connectant clients, artisans et quincailleries agréées via un système de séquestre innovant et sécurisé.
                        </p>
                        <div className="flex items-center gap-2 text-[10px] uppercase font-bold tracking-wider text-[#ebb95e]">
                            <Award className="h-4 w-4 shrink-0" />
                            <span>Label Qualité & Confiance Ivoirien</span>
                        </div>
                    </div>

                    {/* Quick Services */}
                    <div>
                        <h4 className="text-white font-bold text-sm uppercase tracking-wider mb-5">
                            Nos Services
                        </h4>
                        <ul className="space-y-3 text-xs">
                            <li>
                                <Link href="/services" className="hover:text-[#ebb95e] transition">
                                    Mise en relation sécurisée
                                </Link>
                            </li>
                            <li>
                                <Link href="/services" className="hover:text-[#ebb95e] transition">
                                    Estimation des coûts par Gemini IA
                                </Link>
                            </li>
                            <li>
                                <Link href="/formations" className="hover:text-[#ebb95e] transition">
                                    Formations & Labellisation
                                </Link>
                            </li>
                            <li>
                                <Link href="/services" className="hover:text-[#ebb95e] transition">
                                    Micro-crédit d'urgence artisans
                                </Link>
                            </li>
                        </ul>
                    </div>

                    {/* Useful links */}
                    <div>
                        <h4 className="text-white font-bold text-sm uppercase tracking-wider mb-5">
                            Plan du site
                        </h4>
                        <ul className="space-y-3 text-xs">
                            <li>
                                <Link href="/" className="hover:text-[#ebb95e] transition">
                                    Accueil
                                </Link>
                            </li>
                            <li>
                                <Link href="/artisans" className="hover:text-[#ebb95e] transition">
                                    Trouver un Artisan
                                </Link>
                            </li>
                            <li>
                                <Link href="/videos" className="hover:text-[#ebb95e] transition">
                                    Médiathèque & Vidéos
                                </Link>
                            </li>
                            <li>
                                <Link href="/supplier/login" className="hover:text-[#ebb95e] font-semibold text-[#ebb95e]/90 transition flex items-center gap-1.5">
                                    <span>🏪 Espace Fournisseur (Quincaillerie)</span>
                                </Link>
                            </li>
                            <li>
                                <Link href="/actualites" className="hover:text-[#ebb95e] transition">
                                    Actualités & Événements
                                </Link>
                            </li>
                            <li>
                                <Link href="/recrutement" className="hover:text-[#ebb95e] transition">
                                    Recrutement & Carrières
                                </Link>
                            </li>
                        </ul>
                    </div>

                    {/* Contact Info */}
                    <div className="space-y-4 text-xs">
                        <h4 className="text-white font-bold text-sm uppercase tracking-wider mb-5">
                            Contact & Support
                        </h4>
                        <div className="flex items-center gap-3">
                            <Phone className="h-4 w-4 text-[#ebb95e] shrink-0" />
                            <a href="tel:+2250700000000" className="hover:text-[#ebb95e] transition">
                                +225 07 00 00 00 00
                            </a>
                        </div>
                        <div className="flex items-center gap-3">
                            <Mail className="h-4 w-4 text-[#ebb95e] shrink-0" />
                            <a href="mailto:contact@prosartisan.ci" className="hover:text-[#ebb95e] transition">
                                contact@prosartisan.ci
                            </a>
                        </div>
                        <div className="flex items-start gap-3">
                            <MapPin className="h-4 w-4 text-[#ebb95e] shrink-0 mt-0.5" />
                            <span>
                                Plateau, Boulevard de la République,<br />
                                Abidjan, Côte d'Ivoire
                            </span>
                        </div>
                    </div>
                </div>

                <div className="border-t border-[#e6d3b2]/10 mt-12 pt-8 flex flex-col sm:flex-row items-center justify-between text-xs text-[#efe6da]/60">
                    <p>© {currentYear} ProsArtisan. Tous droits réservés.</p>
                    <div className="flex items-center gap-4 mt-4 sm:mt-0">
                        <Link href="/cgu" className="hover:text-white transition">CGU & Mentions Légales</Link>
                        <span>•</span>
                        <span>Propulsé par Mobile Money (Wave & OM)</span>
                    </div>
                </div>
            </div>
        </footer>
    );
}
