'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import { api } from '@/lib/api';
import { Phone, Mail, MapPin, Award, MessageCircle } from 'lucide-react';

function FacebookIcon(props: React.SVGProps<SVGSVGElement>) {
    return (
        <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" {...props}>
            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
        </svg>
    );
}

function InstagramIcon(props: React.SVGProps<SVGSVGElement>) {
    return (
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}>
            <rect width="20" height="20" x="2" y="2" rx="5" ry="5" />
            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z" />
            <line x1="17.5" x2="17.51" y1="6.5" y2="6.5" />
        </svg>
    );
}

function LinkedinIcon(props: React.SVGProps<SVGSVGElement>) {
    return (
        <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" {...props}>
            <path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.28 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.75M6.46 10.9v8.37H9.2V10.9H6.46M7.83 6.7a1.5 1.5 0 1 0 1.5 1.5 1.5 1.5 0 0 0-1.5-1.5z" />
        </svg>
    );
}

function YoutubeIcon(props: React.SVGProps<SVGSVGElement>) {
    return (
        <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" {...props}>
            <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z" />
        </svg>
    );
}

function TiktokIcon(props: React.SVGProps<SVGSVGElement>) {
    return (
        <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" {...props}>
            <path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z" />
        </svg>
    );
}

export default function Footer() {
    const currentYear = new Date().getFullYear();
    const [settings, setSettings] = useState<Record<string, string>>({});

    useEffect(() => {
        const loadSettings = async () => {
            try {
                const data = await api.getSettings();
                if (data && typeof data === 'object') {
                    setSettings(data);
                }
            } catch (e) {
                console.error('Erreur chargement settings footer:', e);
            }
        };
        loadSettings();
    }, []);

    // Helper to get setting with default fallback
    const get = (key: string, fallback: string) => {
        return settings[key] && settings[key].trim() !== '' ? settings[key] : fallback;
    };

    // Social Links
    const facebookUrl = settings['lien_facebook'];
    const instagramUrl = settings['lien_instagram'];
    const linkedinUrl = settings['lien_linkedin'];
    const whatsappUrl = settings['lien_whatsapp'];
    const youtubeUrl = settings['lien_youtube'];
    const tiktokUrl = settings['lien_tiktok'];

    const hasSocials = facebookUrl || instagramUrl || linkedinUrl || whatsappUrl || youtubeUrl || tiktokUrl;

    // Services list
    const services = [
        {
            text: get('footer_service_1_text', 'Mise en relation sécurisée'),
            url: get('footer_service_1_url', '/services')
        },
        {
            text: get('footer_service_2_text', 'Estimation des coûts par Gemini IA'),
            url: get('footer_service_2_url', '/services')
        },
        {
            text: get('footer_service_3_text', 'Formations & Labellisation'),
            url: get('footer_service_3_url', '/formations')
        },
        {
            text: get('footer_service_4_text', "Micro-crédit d'urgence artisans"),
            url: get('footer_service_4_url', '/services')
        }
    ].filter(s => s.text.trim() !== '');

    const phone = get('contact_phone', '+225 07 00 00 00 00');
    const email = get('contact_email', 'contact@prosartisan.ci');
    const address = get('footer_address', 'Plateau, Boulevard de la République, Abidjan, Côte d\'Ivoire');
    const description = get('footer_description', "Première plateforme de confiance en Côte d'Ivoire connectant clients, artisans et quincailleries agréées via un système de séquestre innovant et sécurisé.");
    const badgeText = get('footer_badge_text', 'Label Qualité & Confiance Ivoirien');
    const servicesTitle = get('footer_services_title', 'Nos Services');
    const sitemapTitle = get('footer_sitemap_title', 'Plan du site');
    const contactTitle = get('footer_contact_title', 'Contact & Support');
    const copyright = get('footer_copyright', `© ${currentYear} ProsArtisan. Tous droits réservés.`).replace('{YEAR}', currentYear.toString());
    const cguLabel = get('footer_cgu_label', 'CGU & Mentions Légales');
    const slogan = get('footer_slogan', 'Propulsé par Mobile Money (Wave & OM)');

    return (
        <footer className="bg-[#241b16] text-[#efe6da] pt-16 pb-8 border-t border-[#e6d3b2]/10">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12">
                    {/* Brand Section */}
                    <div className="space-y-4">
                        <Link href="/" className="inline-block bg-white/95 p-2 rounded-xl shadow-md transition hover:scale-105">
                            {/* eslint-disable-next-line @next/next/no-img-element */}
                            <img
                                src="/img/prosartisan-logo.png"
                                alt="ProsArtisan — Professionnel de l'Artisanat"
                                className="h-9 w-auto object-contain"
                            />
                        </Link>
                        <p className="text-xs text-[#efe6da]/70 leading-relaxed">
                            {description}
                        </p>
                        <div className="flex items-center gap-2 text-[10px] uppercase font-bold tracking-wider text-[#ebb95e]">
                            <Award className="h-4 w-4 shrink-0" />
                            <span>{badgeText}</span>
                        </div>

                        {/* Social Links */}
                        {hasSocials && (
                            <div className="flex items-center gap-2.5 pt-2">
                                {facebookUrl && (
                                    <a
                                        href={facebookUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="p-2 bg-white/5 hover:bg-[#ebb95e] hover:text-[#241b16] rounded-xl transition text-stone-300 flex items-center justify-center"
                                        aria-label="Facebook"
                                    >
                                        <FacebookIcon className="h-3.5 w-3.5" />
                                    </a>
                                )}
                                {instagramUrl && (
                                    <a
                                        href={instagramUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="p-2 bg-white/5 hover:bg-[#ebb95e] hover:text-[#241b16] rounded-xl transition text-stone-300 flex items-center justify-center"
                                        aria-label="Instagram"
                                    >
                                        <InstagramIcon className="h-3.5 w-3.5" />
                                    </a>
                                )}
                                {linkedinUrl && (
                                    <a
                                        href={linkedinUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="p-2 bg-white/5 hover:bg-[#ebb95e] hover:text-[#241b16] rounded-xl transition text-stone-300 flex items-center justify-center"
                                        aria-label="LinkedIn"
                                    >
                                        <LinkedinIcon className="h-3.5 w-3.5" />
                                    </a>
                                )}
                                {whatsappUrl && (
                                    <a
                                        href={whatsappUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="p-2 bg-white/5 hover:bg-[#ebb95e] hover:text-[#241b16] rounded-xl transition text-stone-300 flex items-center justify-center"
                                        aria-label="WhatsApp"
                                    >
                                        <MessageCircle className="h-3.5 w-3.5" />
                                    </a>
                                )}
                                {youtubeUrl && (
                                    <a
                                        href={youtubeUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="p-2 bg-white/5 hover:bg-[#ebb95e] hover:text-[#241b16] rounded-xl transition text-stone-300 flex items-center justify-center"
                                        aria-label="YouTube"
                                    >
                                        <YoutubeIcon className="h-3.5 w-3.5" />
                                    </a>
                                )}
                                {tiktokUrl && (
                                    <a
                                        href={tiktokUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="p-2 bg-white/5 hover:bg-[#ebb95e] hover:text-[#241b16] rounded-xl transition text-stone-300 flex items-center justify-center"
                                        aria-label="TikTok"
                                    >
                                        <TiktokIcon className="h-3.5 w-3.5" />
                                    </a>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Quick Services */}
                    <div>
                        <h4 className="text-white font-bold text-sm uppercase tracking-wider mb-5">
                            {servicesTitle}
                        </h4>
                        <ul className="space-y-3 text-xs">
                            {services.map((srv, idx) => (
                                <li key={idx}>
                                    <Link href={srv.url} className="hover:text-[#ebb95e] transition">
                                        {srv.text}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>

                    {/* Useful links */}
                    <div>
                        <h4 className="text-white font-bold text-sm uppercase tracking-wider mb-5">
                            {sitemapTitle}
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
                            <li>
                                <Link href="/cgu" className="hover:text-[#ebb95e] transition">
                                    {cguLabel}
                                </Link>
                            </li>
                            <li>
                                <Link href="/politique-confidentialite" className="hover:text-[#ebb95e] transition">
                                    Politique de Confidentialité
                                </Link>
                            </li>
                            <li>
                                <button
                                    type="button"
                                    onClick={() => window.dispatchEvent(new CustomEvent('open-cookie-settings'))}
                                    className="hover:text-[#ebb95e] transition text-left flex items-center gap-1.5"
                                >
                                    <span>🍪 Gestion des cookies</span>
                                </button>
                            </li>
                            <li>
                                <Link href="/contact" className="hover:text-[#ebb95e] transition">
                                    Contact & Devis
                                </Link>
                            </li>
                        </ul>
                    </div>

                    {/* Contact Info */}
                    <div className="space-y-4 text-xs">
                        <h4 className="text-white font-bold text-sm uppercase tracking-wider mb-5">
                            {contactTitle}
                        </h4>
                        <div className="flex items-center gap-3">
                            <Phone className="h-4 w-4 text-[#ebb95e] shrink-0" />
                            <a href={`tel:${phone.replace(/\s+/g, '')}`} className="hover:text-[#ebb95e] transition">
                                {phone}
                            </a>
                        </div>
                        <div className="flex items-center gap-3">
                            <Mail className="h-4 w-4 text-[#ebb95e] shrink-0" />
                            <a href={`mailto:${email}`} className="hover:text-[#ebb95e] transition">
                                {email}
                            </a>
                        </div>
                        <div className="flex items-start gap-3">
                            <MapPin className="h-4 w-4 text-[#ebb95e] shrink-0 mt-0.5" />
                            <span className="leading-relaxed">
                                {address}
                            </span>
                        </div>
                    </div>
                </div>

                <div className="border-t border-[#e6d3b2]/10 mt-12 pt-8 flex flex-col sm:flex-row items-center justify-between text-xs text-[#efe6da]/60 gap-4">
                    <p>{copyright}</p>
                    <div className="flex flex-wrap items-center gap-3 sm:gap-4">
                        <Link href="/cgu" className="hover:text-white transition">
                            {cguLabel}
                        </Link>
                        <span>•</span>
                        <Link href="/politique-confidentialite" className="hover:text-white transition">
                            Politique de Confidentialité
                        </Link>
                        <span>•</span>
                        <button
                            type="button"
                            onClick={() => window.dispatchEvent(new CustomEvent('open-cookie-settings'))}
                            className="hover:text-white transition"
                        >
                            Gestion des cookies
                        </button>
                        <span>•</span>
                        <span>{slogan}</span>
                    </div>
                </div>
            </div>
        </footer>
    );
}
