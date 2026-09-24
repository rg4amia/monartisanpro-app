import { useForm } from '@inertiajs/react';
import React, { useMemo } from 'react';

// =============================================================================
// SUB-PANEL 8: PARAMÈTRES GÉNÉRAUX
// =============================================================================
export function SettingsSubPanel({ settings }: { settings: any[] }) {
    // Map list of settings to helper object
    const settingsMap = useMemo(() => {
        const map: Record<string, string> = {
            // Indicateurs Hero Vitrine
            stat_artisans_valeur: '2 500+',
            stat_artisans_label: 'Artisans agréés',
            stat_missions_valeur: '14 800+',
            stat_missions_label: 'Missions terminées',
            stat_communes_valeur: '10 Abidjan',
            stat_communes_label: 'Communes desservies',
            stat_satisfaction_valeur: '4.8 / 5',
            stat_satisfaction_label: 'Satisfaction client',

            chiffres_cles_artisans: '2 500+',
            chiffres_cles_utilisateurs: '3000',
            chiffres_cles_missions: '14 800+',
            chiffres_cles_metiers: '29',
            
            // Coordonnées & Contact
            contact_phone: '+225 01 60 60 61 83',
            contact_email: 'info@prosartisan.net',
            footer_address: "Koumassi remblais, Abidjan, Côte d'Ivoire",
            
            // Réseaux sociaux
            lien_facebook: '',
            lien_instagram: '',
            lien_linkedin: '',
            lien_whatsapp: '',
            lien_youtube: '',
            lien_tiktok: '',

            // Identité Footer
            footer_description: "Première plateforme de confiance en Côte d'Ivoire connectant clients, artisans et quincailleries agréées via un système de séquestre innovant et sécurisé.",
            footer_badge_text: "Label Qualité & Confiance Ivoirien",
            presentation_mission: "ProsArtisan connecte les particuliers aux meilleurs artisans qualifiés et quincailleries de Côte d’Ivoire...",

            // Titres de colonnes
            footer_services_title: "Nos Services",
            footer_sitemap_title: "Plan du site",
            footer_contact_title: "Contact & Support",

            // Services
            footer_service_1_text: "Mise en relation sécurisée",
            footer_service_1_url: "/services",
            footer_service_2_text: "Estimation des coûts par Gemini IA",
            footer_service_2_url: "/services",
            footer_service_3_text: "Formations & Labellisation",
            footer_service_3_url: "/formations",
            footer_service_4_text: "Micro-crédit d'urgence artisans",
            footer_service_4_url: "/services",

            // Bas de page
            footer_copyright: "© 2026 ProsArtisan. Tous droits réservés.",
            footer_cgu_label: "CGU & Mentions Légales",
            footer_slogan: "Propulsé par Mobile Money (Wave & OM)",
        };
        settings.forEach((s) => {
            map[s.cle] = s.valeur;
        });
        return map;
    }, [settings]);

    const { data, setData, post, processing, errors } = useForm({
        stat_artisans_valeur: settingsMap.stat_artisans_valeur,
        stat_artisans_label: settingsMap.stat_artisans_label,
        stat_missions_valeur: settingsMap.stat_missions_valeur,
        stat_missions_label: settingsMap.stat_missions_label,
        stat_communes_valeur: settingsMap.stat_communes_valeur,
        stat_communes_label: settingsMap.stat_communes_label,
        stat_satisfaction_valeur: settingsMap.stat_satisfaction_valeur,
        stat_satisfaction_label: settingsMap.stat_satisfaction_label,

        chiffres_cles_artisans: settingsMap.chiffres_cles_artisans,
        chiffres_cles_utilisateurs: settingsMap.chiffres_cles_utilisateurs,
        chiffres_cles_missions: settingsMap.chiffres_cles_missions,
        chiffres_cles_metiers: settingsMap.chiffres_cles_metiers,
        
        contact_phone: settingsMap.contact_phone,
        contact_email: settingsMap.contact_email,
        footer_address: settingsMap.footer_address,

        lien_facebook: settingsMap.lien_facebook,
        lien_instagram: settingsMap.lien_instagram,
        lien_linkedin: settingsMap.lien_linkedin,
        lien_whatsapp: settingsMap.lien_whatsapp,
        lien_youtube: settingsMap.lien_youtube,
        lien_tiktok: settingsMap.lien_tiktok,

        footer_description: settingsMap.footer_description,
        footer_badge_text: settingsMap.footer_badge_text,
        presentation_mission: settingsMap.presentation_mission,

        footer_services_title: settingsMap.footer_services_title,
        footer_sitemap_title: settingsMap.footer_sitemap_title,
        footer_contact_title: settingsMap.footer_contact_title,

        footer_service_1_text: settingsMap.footer_service_1_text,
        footer_service_1_url: settingsMap.footer_service_1_url,
        footer_service_2_text: settingsMap.footer_service_2_text,
        footer_service_2_url: settingsMap.footer_service_2_url,
        footer_service_3_text: settingsMap.footer_service_3_text,
        footer_service_3_url: settingsMap.footer_service_3_url,
        footer_service_4_text: settingsMap.footer_service_4_text,
        footer_service_4_url: settingsMap.footer_service_4_url,

        footer_copyright: settingsMap.footer_copyright,
        footer_cgu_label: settingsMap.footer_cgu_label,
        footer_slogan: settingsMap.footer_slogan,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/vitrine/settings', {
            preserveScroll: true,
            onError: (errs) => {
                console.error('Erreur soumission settings:', errs);
            }
        });
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-8">
            <div className="flex items-center justify-between border-b pb-4">
                <div>
                    <h3 className="text-lg font-bold text-[var(--admin-text)]">Paramètres de la Vitrine & du Footer</h3>
                    <p className="text-xs text-[var(--admin-text-soft)] mt-0.5">
                        Personnalisez l'ensemble des informations, textes, liens, coordonnées et réseaux sociaux affichés sur le Front Office.
                    </p>
                </div>
                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-xl px-5 py-2.5 text-sm font-semibold bg-[#ebb95e] text-[#241b16] hover:bg-[#e0ab4b] disabled:opacity-50 shadow-sm"
                >
                    {processing ? 'Enregistrement...' : 'Enregistrer les Paramètres'}
                </button>
            </div>

            {Object.keys(errors).length > 0 && (
                <div className="p-3 bg-red-100 border border-red-300 text-red-700 rounded-xl text-xs space-y-1">
                    {Object.values(errors).map((err, i) => (
                        <p key={i}>• {err}</p>
                    ))}
                </div>
            )}

            {/* 1. INDICATEURS CLÉS (HERO VITRINE) */}
            <div className="space-y-4 bg-[var(--admin-panel)] border border-[var(--admin-border)] p-6 rounded-[24px]">
                <div>
                    <h4 className="text-sm font-bold text-[#b77918] uppercase tracking-wider flex items-center gap-2">
                        <span>📊</span> Barre d'Indicateurs Clés (Hero Vitrine)
                    </h4>
                    <p className="text-xs text-[var(--admin-text-soft)] mt-1">
                        Configurez les 4 compteurs et indicateurs affichés sous le diaporama de la page d'accueil de la Vitrine.
                    </p>
                </div>
                
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {/* Indicateur 1 */}
                    <div className="p-4 bg-[var(--admin-panel-strong)] rounded-2xl border border-[var(--admin-border)] space-y-3 shadow-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-[10px] font-bold text-[#8a5d16] uppercase tracking-wider">Indicateur 1 (Artisans)</span>
                            <span className="text-xs">👥</span>
                        </div>
                        <div>
                            <label className="block text-[11px] font-semibold text-[var(--admin-text)] mb-1">Valeur affichée</label>
                            <input
                                type="text"
                                value={data.stat_artisans_valeur}
                                onChange={e => {
                                    setData('stat_artisans_valeur', e.target.value);
                                    setData('chiffres_cles_artisans', e.target.value);
                                }}
                                className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm font-semibold"
                                placeholder="Ex: 2 500+"
                            />
                        </div>
                        <div>
                            <label className="block text-[11px] font-semibold text-[var(--admin-text)] mb-1">Libellé</label>
                            <input
                                type="text"
                                value={data.stat_artisans_label}
                                onChange={e => setData('stat_artisans_label', e.target.value)}
                                className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-1.5 text-xs"
                                placeholder="Ex: Artisans agréés"
                            />
                        </div>
                    </div>

                    {/* Indicateur 2 */}
                    <div className="p-4 bg-[var(--admin-panel-strong)] rounded-2xl border border-[var(--admin-border)] space-y-3 shadow-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-[10px] font-bold text-emerald-700 uppercase tracking-wider">Indicateur 2 (Missions)</span>
                            <span className="text-xs">✅</span>
                        </div>
                        <div>
                            <label className="block text-[11px] font-semibold text-[var(--admin-text)] mb-1">Valeur affichée</label>
                            <input
                                type="text"
                                value={data.stat_missions_valeur}
                                onChange={e => {
                                    setData('stat_missions_valeur', e.target.value);
                                    setData('chiffres_cles_missions', e.target.value);
                                }}
                                className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm font-semibold"
                                placeholder="Ex: 14 800+"
                            />
                        </div>
                        <div>
                            <label className="block text-[11px] font-semibold text-[var(--admin-text)] mb-1">Libellé</label>
                            <input
                                type="text"
                                value={data.stat_missions_label}
                                onChange={e => setData('stat_missions_label', e.target.value)}
                                className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-1.5 text-xs"
                                placeholder="Ex: Missions terminées"
                            />
                        </div>
                    </div>

                    {/* Indicateur 3 */}
                    <div className="p-4 bg-[var(--admin-panel-strong)] rounded-2xl border border-[var(--admin-border)] space-y-3 shadow-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-[10px] font-bold text-blue-700 uppercase tracking-wider">Indicateur 3 (Communes)</span>
                            <span className="text-xs">📍</span>
                        </div>
                        <div>
                            <label className="block text-[11px] font-semibold text-[var(--admin-text)] mb-1">Valeur affichée</label>
                            <input
                                type="text"
                                value={data.stat_communes_valeur}
                                onChange={e => setData('stat_communes_valeur', e.target.value)}
                                className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm font-semibold"
                                placeholder="Ex: 10 Abidjan"
                            />
                        </div>
                        <div>
                            <label className="block text-[11px] font-semibold text-[var(--admin-text)] mb-1">Libellé</label>
                            <input
                                type="text"
                                value={data.stat_communes_label}
                                onChange={e => setData('stat_communes_label', e.target.value)}
                                className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-1.5 text-xs"
                                placeholder="Ex: Communes desservies"
                            />
                        </div>
                    </div>

                    {/* Indicateur 4 */}
                    <div className="p-4 bg-[var(--admin-panel-strong)] rounded-2xl border border-[var(--admin-border)] space-y-3 shadow-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-[10px] font-bold text-amber-600 uppercase tracking-wider">Indicateur 4 (Satisfaction)</span>
                            <span className="text-xs">⭐</span>
                        </div>
                        <div>
                            <label className="block text-[11px] font-semibold text-[var(--admin-text)] mb-1">Valeur affichée</label>
                            <input
                                type="text"
                                value={data.stat_satisfaction_valeur}
                                onChange={e => setData('stat_satisfaction_valeur', e.target.value)}
                                className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm font-semibold"
                                placeholder="Ex: 4.8 / 5"
                            />
                        </div>
                        <div>
                            <label className="block text-[11px] font-semibold text-[var(--admin-text)] mb-1">Libellé</label>
                            <input
                                type="text"
                                value={data.stat_satisfaction_label}
                                onChange={e => setData('stat_satisfaction_label', e.target.value)}
                                className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-1.5 text-xs"
                                placeholder="Ex: Satisfaction client"
                            />
                        </div>
                    </div>
                </div>
            </div>

            {/* 2. FOOTER - IDENTITÉ & MARQUE */}
            <div className="space-y-4 bg-[var(--admin-panel)] border border-[var(--admin-border)] p-6 rounded-[24px]">
                <h4 className="text-sm font-bold text-[#b77918] uppercase tracking-wider flex items-center gap-2">
                    <span>🏢</span> Footer : Identité de Marque & Badge de Confiance
                </h4>
                <div className="grid gap-4 md:grid-cols-2">
                    <div className="md:col-span-2">
                        <label className="block text-xs font-semibold text-[var(--admin-text)] mb-1">
                            Texte de présentation sous le Logo Footer
                        </label>
                        <textarea
                            rows={3}
                            value={data.footer_description}
                            onChange={e => setData('footer_description', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm resize-none"
                            placeholder="Ex: Première plateforme de confiance en Côte d'Ivoire..."
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-[var(--admin-text)] mb-1">
                            Texte du Badge de Confiance Footer
                        </label>
                        <input
                            type="text"
                            value={data.footer_badge_text}
                            onChange={e => setData('footer_badge_text', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm font-semibold"
                            placeholder="Ex: Label Qualité & Confiance Ivoirien"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-[var(--admin-text)] mb-1">
                            Titre de la colonne Contact Footer
                        </label>
                        <input
                            type="text"
                            value={data.footer_contact_title}
                            onChange={e => setData('footer_contact_title', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                            placeholder="Ex: Contact & Support"
                        />
                    </div>
                </div>
            </div>

            {/* 3. FOOTER - COORDONNÉES & CONTACT */}
            <div className="space-y-4 bg-[var(--admin-panel)] border border-[var(--admin-border)] p-6 rounded-[24px]">
                <h4 className="text-sm font-bold text-[#b77918] uppercase tracking-wider flex items-center gap-2">
                    <span>📞</span> Footer : Coordonnées de Contact & Localisation
                </h4>
                <div className="grid gap-4 md:grid-cols-3">
                    <div>
                        <label className="block text-xs font-semibold text-[var(--admin-text)] mb-1">Téléphone officiel (Footer & Contact)</label>
                        <input
                            type="text"
                            value={data.contact_phone}
                            onChange={e => setData('contact_phone', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm font-semibold"
                            placeholder="+225 01 60 60 61 83"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-[var(--admin-text)] mb-1">Email officiel de support</label>
                        <input
                            type="email"
                            value={data.contact_email}
                            onChange={e => setData('contact_email', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm font-semibold"
                            placeholder="info@prosartisan.net"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-[var(--admin-text)] mb-1">Adresse géographique / Siège</label>
                        <input
                            type="text"
                            value={data.footer_address}
                            onChange={e => setData('footer_address', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                            placeholder="Koumassi remblais, Abidjan"
                        />
                    </div>
                </div>
            </div>

            {/* 4. RÉSEAUX SOCIAUX */}
            <div className="space-y-4 bg-[var(--admin-panel)] border border-[var(--admin-border)] p-6 rounded-[24px]">
                <h4 className="text-sm font-bold text-[#b77918] uppercase tracking-wider flex items-center gap-2">
                    <span>🌐</span> Réseaux Sociaux Officiels
                </h4>
                <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-3">
                    <div>
                        <label className="block text-xs font-semibold text-[var(--admin-text)] mb-1">Lien Facebook</label>
                        <input
                            type="text"
                            value={data.lien_facebook}
                            onChange={e => setData('lien_facebook', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                            placeholder="https://facebook.com/prosartisan"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-[var(--admin-text)] mb-1">Lien Instagram</label>
                        <input
                            type="text"
                            value={data.lien_instagram}
                            onChange={e => setData('lien_instagram', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                            placeholder="https://instagram.com/prosartisan"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-[var(--admin-text)] mb-1">Lien LinkedIn</label>
                        <input
                            type="text"
                            value={data.lien_linkedin}
                            onChange={e => setData('lien_linkedin', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                            placeholder="https://linkedin.com/company/prosartisan"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-[var(--admin-text)] mb-1">Lien WhatsApp Direct</label>
                        <input
                            type="text"
                            value={data.lien_whatsapp}
                            onChange={e => setData('lien_whatsapp', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                            placeholder="https://wa.me/2250700000000"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-[var(--admin-text)] mb-1">Lien Chaîne YouTube</label>
                        <input
                            type="text"
                            value={data.lien_youtube}
                            onChange={e => setData('lien_youtube', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                            placeholder="https://youtube.com/@prosartisan"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-[var(--admin-text)] mb-1">Lien TikTok</label>
                        <input
                            type="text"
                            value={data.lien_tiktok}
                            onChange={e => setData('lien_tiktok', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                            placeholder="https://tiktok.com/@prosartisan"
                        />
                    </div>
                </div>
            </div>

            {/* 5. SERVICES PERSONNALISABLES */}
            <div className="space-y-4 bg-[var(--admin-panel)] border border-[var(--admin-border)] p-6 rounded-[24px]">
                <div className="flex items-center justify-between">
                    <h4 className="text-sm font-bold text-[#b77918] uppercase tracking-wider flex items-center gap-2">
                        <span>🛠️</span> Footer : Liens de la Colonne Services
                    </h4>
                    <div className="w-64">
                        <input
                            type="text"
                            value={data.footer_services_title}
                            onChange={e => setData('footer_services_title', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-1.5 text-xs font-bold"
                            placeholder="Titre : Nos Services"
                        />
                    </div>
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-2 p-3 bg-stone-50/60 rounded-xl border border-[var(--admin-border)]">
                        <label className="block text-[11px] font-bold text-[var(--admin-text)]">Service 1 (Texte & URL)</label>
                        <input
                            type="text"
                            value={data.footer_service_1_text}
                            onChange={e => setData('footer_service_1_text', e.target.value)}
                            className="w-full rounded-lg border border-[var(--admin-border)] px-2.5 py-1.5 text-xs"
                            placeholder="Intitulé du service"
                        />
                        <input
                            type="text"
                            value={data.footer_service_1_url}
                            onChange={e => setData('footer_service_1_url', e.target.value)}
                            className="w-full rounded-lg border border-[var(--admin-border)] px-2.5 py-1.5 text-xs"
                            placeholder="Lien / URL (/services)"
                        />
                    </div>
                    <div className="space-y-2 p-3 bg-stone-50/60 rounded-xl border border-[var(--admin-border)]">
                        <label className="block text-[11px] font-bold text-[var(--admin-text)]">Service 2 (Texte & URL)</label>
                        <input
                            type="text"
                            value={data.footer_service_2_text}
                            onChange={e => setData('footer_service_2_text', e.target.value)}
                            className="w-full rounded-lg border border-[var(--admin-border)] px-2.5 py-1.5 text-xs"
                            placeholder="Intitulé du service"
                        />
                        <input
                            type="text"
                            value={data.footer_service_2_url}
                            onChange={e => setData('footer_service_2_url', e.target.value)}
                            className="w-full rounded-lg border border-[var(--admin-border)] px-2.5 py-1.5 text-xs"
                            placeholder="Lien / URL (/services)"
                        />
                    </div>
                    <div className="space-y-2 p-3 bg-stone-50/60 rounded-xl border border-[var(--admin-border)]">
                        <label className="block text-[11px] font-bold text-[var(--admin-text)]">Service 3 (Texte & URL)</label>
                        <input
                            type="text"
                            value={data.footer_service_3_text}
                            onChange={e => setData('footer_service_3_text', e.target.value)}
                            className="w-full rounded-lg border border-[var(--admin-border)] px-2.5 py-1.5 text-xs"
                            placeholder="Intitulé du service"
                        />
                        <input
                            type="text"
                            value={data.footer_service_3_url}
                            onChange={e => setData('footer_service_3_url', e.target.value)}
                            className="w-full rounded-lg border border-[var(--admin-border)] px-2.5 py-1.5 text-xs"
                            placeholder="Lien / URL (/formations)"
                        />
                    </div>
                    <div className="space-y-2 p-3 bg-stone-50/60 rounded-xl border border-[var(--admin-border)]">
                        <label className="block text-[11px] font-bold text-[var(--admin-text)]">Service 4 (Texte & URL)</label>
                        <input
                            type="text"
                            value={data.footer_service_4_text}
                            onChange={e => setData('footer_service_4_text', e.target.value)}
                            className="w-full rounded-lg border border-[var(--admin-border)] px-2.5 py-1.5 text-xs"
                            placeholder="Intitulé du service"
                        />
                        <input
                            type="text"
                            value={data.footer_service_4_url}
                            onChange={e => setData('footer_service_4_url', e.target.value)}
                            className="w-full rounded-lg border border-[var(--admin-border)] px-2.5 py-1.5 text-xs"
                            placeholder="Lien / URL (/services)"
                        />
                    </div>
                </div>
            </div>

            {/* 6. FOOTER - BAS DE PAGE & LÉGAL */}
            <div className="space-y-4 bg-[var(--admin-panel)] border border-[var(--admin-border)] p-6 rounded-[24px]">
                <h4 className="text-sm font-bold text-[#b77918] uppercase tracking-wider flex items-center gap-2">
                    <span>⚖️</span> Footer : Bas de page, Copyright & Mentions Légales
                </h4>
                <div className="grid gap-4 md:grid-cols-3">
                    <div>
                        <label className="block text-xs font-semibold text-[var(--admin-text)] mb-1">
                            Texte Copyright
                        </label>
                        <input
                            type="text"
                            value={data.footer_copyright}
                            onChange={e => setData('footer_copyright', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                            placeholder="© 2026 ProsArtisan. Tous droits réservés."
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-[var(--admin-text)] mb-1">
                            Libellé Lien Légal / CGU
                        </label>
                        <input
                            type="text"
                            value={data.footer_cgu_label}
                            onChange={e => setData('footer_cgu_label', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                            placeholder="CGU & Mentions Légales"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-[var(--admin-text)] mb-1">
                            Slogan / Paiements Partenaires
                        </label>
                        <input
                            type="text"
                            value={data.footer_slogan}
                            onChange={e => setData('footer_slogan', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                            placeholder="Propulsé par Mobile Money (Wave & OM)"
                        />
                    </div>
                </div>
            </div>

            {/* 7. MISSION GLOBALE */}
            <div className="space-y-4 bg-[var(--admin-panel)] border border-[var(--admin-border)] p-6 rounded-[24px]">
                <h4 className="text-sm font-bold text-[#b77918] uppercase tracking-wider flex items-center gap-2">
                    <span>📝</span> Présentation & Mission Globale de la Plateforme
                </h4>
                <div>
                    <label className="block text-xs font-semibold text-[var(--admin-text)] mb-1">
                        Texte éditorial de présentation
                    </label>
                    <textarea
                        value={data.presentation_mission}
                        onChange={e => setData('presentation_mission', e.target.value)}
                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm h-28 resize-none"
                    />
                </div>
            </div>

            <div className="flex justify-end pt-4 border-t border-[var(--admin-border)]">
                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-xl px-6 py-3 text-sm font-bold bg-[#ebb95e] text-[#241b16] hover:bg-[#e0ab4b] disabled:opacity-50 shadow-md transition"
                >
                    {processing ? 'Enregistrement...' : 'Enregistrer tous les Paramètres'}
                </button>
            </div>
        </form>
    );
}
