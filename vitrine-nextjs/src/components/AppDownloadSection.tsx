'use client';

import { useEffect, useState } from 'react';
import {
    BadgeCheck, Bell, Droplets, Hammer, Home, MapPin, PaintRoller, Search,
    ShieldCheck, Wrench, Zap, ClipboardList, Store, User,
} from 'lucide-react';
import { api, AppStoreLink } from '@/lib/api';

function GooglePlayLogo(props: React.SVGProps<SVGSVGElement>) {
    return (
        <svg viewBox="0 0 24 24" aria-hidden="true" {...props}>
            <path fill="#00D7FE" d="M3.6 1.8 13.3 12l-9.7 10.2c-.4-.2-.6-.7-.6-1.2V3c0-.5.2-1 .6-1.2z" />
            <path fill="#FFCE00" d="m16.6 15.3-3.3-3.3 3.3-3.3 3.8 2.2c1 .6 1 1.6 0 2.2z" />
            <path fill="#FF3A44" d="M16.6 15.3 13.3 12 3.6 22.2c.4.2.9.2 1.4-.1z" />
            <path fill="#00F076" d="M16.6 8.7 5 1.9c-.5-.3-1-.3-1.4-.1L13.3 12z" />
        </svg>
    );
}

function AppleLogo(props: React.SVGProps<SVGSVGElement>) {
    return (
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" {...props}>
            <path d="M12.152 6.896c-.948 0-2.415-1.078-3.96-1.04-2.04.027-3.91 1.183-4.961 3.014-2.117 3.675-.546 9.103 1.519 12.09 1.013 1.454 2.208 3.09 3.792 3.039 1.52-.065 2.09-.987 3.935-.987 1.831 0 2.35.987 3.96.948 1.637-.026 2.676-1.48 3.676-2.948 1.156-1.688 1.636-3.325 1.662-3.415-.039-.013-3.182-1.221-3.22-4.857-.026-3.04 2.48-4.494 2.597-4.559-1.429-2.09-3.623-2.324-4.39-2.376-2-.156-3.675 1.09-4.61 1.09zM15.53 3.83c.843-1.012 1.4-2.427 1.245-3.83-1.207.052-2.662.805-3.532 1.818-.78.896-1.454 2.338-1.273 3.714 1.338.104 2.715-.688 3.559-1.701" />
        </svg>
    );
}

/** Badge officiel du magasin, redessiné pour rester net à toute taille. */
function StoreBadge({ link }: { link: AppStoreLink }) {
    const android = link.platform === 'android';
    return (
        <a
            href={link.url}
            target="_blank"
            rel="noopener noreferrer"
            aria-label={android ? "Télécharger l'application sur Google Play" : "Télécharger l'application dans l'App Store"}
            className="group inline-flex h-14 min-w-[180px] items-center gap-3 rounded-2xl border border-white/10 bg-black px-4 text-white shadow-lg shadow-black/20 transition hover:-translate-y-0.5 hover:bg-[#1a1a1a] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#ebb95e]"
        >
            {android ? <GooglePlayLogo className="h-7 w-7 shrink-0" /> : <AppleLogo className="h-7 w-7 shrink-0" />}
            <span className="flex flex-col text-left leading-tight">
                <span className="text-[10px] font-medium uppercase tracking-wider text-white/75">
                    {android ? 'Disponible sur' : 'Télécharger dans'}
                </span>
                <span className="text-lg font-bold tracking-tight">{android ? 'Google Play' : "l'App Store"}</span>
            </span>
        </a>
    );
}

const PHONE_TRADES = [
    { label: 'Plomberie', Icon: Droplets, color: 'bg-sky-100 text-sky-700' },
    { label: 'Électricité', Icon: Zap, color: 'bg-amber-100 text-amber-700' },
    { label: 'Peinture', Icon: PaintRoller, color: 'bg-rose-100 text-rose-700' },
    { label: 'Maçonnerie', Icon: Hammer, color: 'bg-orange-100 text-orange-700' },
    { label: 'Dépannage', Icon: Wrench, color: 'bg-indigo-100 text-indigo-700' },
    { label: 'Matériaux', Icon: Store, color: 'bg-emerald-100 text-emerald-700' },
];

/** Illustration de l'application : écran d'accueil client stylisé. */
function PhoneMockup() {
    return (
        <div className="relative mx-auto w-[240px] sm:w-[270px]" aria-hidden="true">
            <div className="absolute -inset-10 rounded-full bg-[#ebb95e]/25 blur-3xl" />
            <div className="relative rounded-[44px] bg-[#241b16] p-2.5 shadow-2xl shadow-[#241b16]/40 ring-1 ring-black/40 [transform:rotate(-4deg)]">
                <div className="absolute left-1/2 top-2.5 z-10 h-5 w-24 -translate-x-1/2 rounded-b-2xl bg-[#241b16]" />
                <div className="overflow-hidden rounded-[36px] bg-[#f5f6fa]">
                    <div className="bg-[#1a2c5b] px-4 pb-5 pt-8 text-white">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-[9px] text-white/70">Bonjour 👋</p>
                                <p className="text-[13px] font-bold">Que faut-il réparer ?</p>
                            </div>
                            <Bell className="h-4 w-4 text-white/80" />
                        </div>
                        <div className="mt-3 flex items-center gap-2 rounded-xl bg-white/95 px-3 py-2 text-[9px] text-slate-400">
                            <Search className="h-3 w-3" /> Rechercher un artisan…
                        </div>
                    </div>
                    <div className="px-3 py-3">
                        <p className="mb-2 text-[10px] font-bold text-[#1a2c5b]">Nos métiers</p>
                        <div className="grid grid-cols-3 gap-2">
                            {PHONE_TRADES.map(({ label, Icon, color }) => (
                                <div key={label} className="flex flex-col items-center gap-1 rounded-xl bg-white p-2 shadow-sm">
                                    <span className={`flex h-7 w-7 items-center justify-center rounded-lg ${color}`}>
                                        <Icon className="h-3.5 w-3.5" />
                                    </span>
                                    <span className="text-[8px] font-semibold text-slate-600">{label}</span>
                                </div>
                            ))}
                        </div>
                        <div className="mt-3 flex items-center gap-2 rounded-xl bg-emerald-50 p-2.5">
                            <ShieldCheck className="h-4 w-4 shrink-0 text-emerald-600" />
                            <p className="text-[8px] leading-snug text-emerald-800">
                                <span className="font-bold">Coffre de sécurité</span> : vous payez, l&apos;artisan est réglé étape par étape.
                            </p>
                        </div>
                    </div>
                    <div className="flex justify-around border-t border-slate-200 bg-white px-2 py-2 text-[7px] text-slate-400">
                        {[
                            { label: 'Accueil', Icon: Home, active: true },
                            { label: 'Artisans', Icon: MapPin },
                            { label: 'Boutiques', Icon: Store },
                            { label: 'Missions', Icon: ClipboardList },
                            { label: 'Profil', Icon: User },
                        ].map(({ label, Icon, active }) => (
                            <span key={label} className={`flex flex-col items-center gap-0.5 ${active ? 'font-bold text-[#2f6fed]' : ''}`}>
                                <Icon className="h-3.5 w-3.5" />
                                {label}
                            </span>
                        ))}
                    </div>
                </div>
            </div>

            <div className="absolute -left-10 top-24 hidden items-center gap-2 rounded-2xl bg-white px-3 py-2 shadow-xl sm:flex">
                <BadgeCheck className="h-5 w-5 text-emerald-600" />
                <span className="text-xs font-bold text-[#241b16]">Artisans vérifiés</span>
            </div>
            <div className="absolute -right-8 bottom-24 hidden items-center gap-2 rounded-2xl bg-white px-3 py-2 shadow-xl sm:flex">
                <ShieldCheck className="h-5 w-5 text-[#8a5d16]" />
                <span className="text-xs font-bold text-[#241b16]">Paiement protégé</span>
            </div>
        </div>
    );
}

const BENEFITS = [
    { Icon: BadgeCheck, text: 'Des artisans à l’identité vérifiée, près de chez vous.' },
    { Icon: ShieldCheck, text: 'Un paiement protégé, versé à l’artisan étape par étape.' },
    { Icon: ClipboardList, text: 'Le suivi de vos chantiers et de vos commandes de matériaux.' },
];

/**
 * Section « Téléchargez l'application » de la page d'accueil (Chantier 16).
 * Affichée uniquement quand un lien Google Play ou App Store a été validé
 * dans le backoffice ; seuls les badges des magasins publiés apparaissent.
 */
export default function AppDownloadSection() {
    const [links, setLinks] = useState<AppStoreLink[]>([]);

    useEffect(() => {
        let mounted = true;
        api.getAppLinks()
            .then((data) => {
                if (mounted) setLinks(data);
            })
            .catch(() => {});
        return () => {
            mounted = false;
        };
    }, []);

    if (links.length === 0) {
        return null;
    }

    return (
        <section
            aria-labelledby="app-download-title"
            className="relative overflow-hidden border-t border-[#e6d3b2]/40 bg-gradient-to-br from-[#fbf9f6] via-[#f7efe2] to-[#efe0c2] py-20 sm:py-24"
        >
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_20%_30%,rgba(235,185,94,0.18),transparent_45%),radial-gradient(circle_at_85%_80%,rgba(138,93,22,0.12),transparent_40%)]" />
            <div className="relative mx-auto grid max-w-7xl items-center gap-14 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
                <div className="order-2 lg:order-1">
                    <PhoneMockup />
                </div>

                <div className="order-1 space-y-6 text-center lg:order-2 lg:text-left">
                    <span className="inline-flex items-center gap-1.5 rounded-full border border-[#ebb95e]/40 bg-[#ebb95e]/15 px-3 py-1 text-xs font-bold uppercase tracking-wider text-[#8a5d16]">
                        Application mobile
                    </span>
                    <h2 id="app-download-title" className="text-3xl font-black leading-tight tracking-tight text-[#241b16] sm:text-5xl">
                        Téléchargez l&apos;application <span className="text-[#8a5d16]">ProsArtisan</span>
                    </h2>
                    <p className="mx-auto max-w-xl text-sm leading-relaxed text-[#746251] sm:text-base lg:mx-0">
                        Pour une meilleure expérience, gérez tout depuis votre téléphone : demande de travaux, devis, paiement et suivi du chantier.
                    </p>
                    <ul className="mx-auto max-w-md space-y-3 text-left lg:mx-0">
                        {BENEFITS.map(({ Icon, text }) => (
                            <li key={text} className="flex items-start gap-3 text-sm text-[#45362e]">
                                <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white shadow-sm">
                                    <Icon className="h-4 w-4 text-[#8a5d16]" />
                                </span>
                                <span className="pt-1">{text}</span>
                            </li>
                        ))}
                    </ul>
                    <div className="flex flex-wrap justify-center gap-3 pt-2 lg:justify-start">
                        {links.map((link) => (
                            <StoreBadge key={link.platform} link={link} />
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}
