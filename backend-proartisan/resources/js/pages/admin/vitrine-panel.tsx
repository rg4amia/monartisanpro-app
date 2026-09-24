import { useMemo, useState } from 'react';
import { cn } from '@/lib/utils';
import { ArticlesSubPanel } from './panels/vitrine/ArticlesSubPanel';
import { ArtisanDuMoisSubPanel } from './panels/vitrine/ArtisanDuMoisSubPanel';
import { ContactsSubPanel } from './panels/vitrine/ContactsSubPanel';
import { FormationsSubPanel } from './panels/vitrine/FormationsSubPanel';
import { PopupsSubPanel } from './panels/vitrine/PopupsSubPanel';
import { RecrutementsSubPanel } from './panels/vitrine/RecrutementsSubPanel';
import { SettingsSubPanel } from './panels/vitrine/SettingsSubPanel';
import { SlidesSubPanel } from './panels/vitrine/SlidesSubPanel';
import { VideosSubPanel } from './panels/vitrine/VideosSubPanel';

interface VitrinePanelProps {
    vitrineSlides: any[];
    vitrineArtisanDuMois: any[];
    vitrineArticles: any[];
    vitrineVideos: any[];
    vitrineFormations: any[];
    vitrineRecrutements: any[];
    vitrinePopups: any[];
    vitrineSettings: any[];
    contactMessages?: any[];
    users: any[];
}

type SubTab = 'contacts' | 'slides' | 'artisan_du_mois' | 'articles' | 'videos' | 'formations' | 'recrutements' | 'popups' | 'settings';

export default function VitrinePanel({
    vitrineSlides = [],
    vitrineArtisanDuMois = [],
    vitrineArticles = [],
    vitrineVideos = [],
    vitrineFormations = [],
    vitrineRecrutements = [],
    vitrinePopups = [],
    vitrineSettings = [],
    contactMessages = [],
    users = [],
}: VitrinePanelProps) {
    const [activeSubTab, setActiveSubTab] = useState<SubTab>('contacts');

    // Nouveaux contacts non traités pour le badge
    const newContactsCount = useMemo(() => {
        return (contactMessages || []).filter(c => c.statut === 'nouveau').length;
    }, [contactMessages]);

    // Filter artisans to pick for "Artisan du Mois"
    const artisans = useMemo(() => {
        return (users || []).filter(u => u.role === 'artisan');
    }, [users]);

    // Format money helper
    const money = (amount: number) => {
        return new Intl.NumberFormat('fr-FR').format(amount) + ' FCFA';
    };

    return (
        <div className="space-y-6">
            {/* Header info */}
            <div className="flex flex-col gap-2">
                <div className="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <h2 className="text-2xl font-bold text-[var(--admin-text)]">Gestion de la Vitrine & Support</h2>
                        <p className="text-sm text-[var(--admin-text-soft)]">
                            Pilotez les demandes de contact, le contenu éditorial, les formations, les vidéos et les actualités du portail ProsArtisan.
                        </p>
                    </div>
                    {newContactsCount > 0 && (
                        <div className="flex items-center gap-2 px-3 py-1.5 bg-rose-100 border border-rose-300 text-rose-800 rounded-2xl text-xs font-bold animate-pulse">
                            <span className="h-2 w-2 rounded-full bg-rose-600"></span>
                            {newContactsCount} nouvelle{newContactsCount > 1 ? 's' : ''} demande{newContactsCount > 1 ? 's' : ''} de contact à traiter
                        </div>
                    )}
                </div>
            </div>

            {/* Sub Tabs */}
            <div className="flex flex-wrap gap-2 border-b border-[var(--admin-border)] pb-px">
                {(
                    [
                        { id: 'contacts' as const, label: 'Demandes de Contact', badge: newContactsCount },
                        { id: 'slides' as const, label: 'Slides Hero', badge: undefined },
                        { id: 'artisan_du_mois' as const, label: 'Artisan du Mois', badge: undefined },
                        { id: 'articles' as const, label: 'Actualités & Blog', badge: undefined },
                        { id: 'videos' as const, label: 'Capsules Vidéo', badge: undefined },
                        { id: 'formations' as const, label: 'Sessions Formations', badge: undefined },
                        { id: 'recrutements' as const, label: 'Espace Recrutement', badge: undefined },
                        { id: 'popups' as const, label: 'Pop-ups & Banner', badge: undefined },
                        { id: 'settings' as const, label: 'Paramètres Généraux', badge: undefined },
                    ]
                ).map((tab) => {
                    const isActive = activeSubTab === tab.id;
                    return (
                        <button
                            key={tab.id}
                            type="button"
                            onClick={() => setActiveSubTab(tab.id as SubTab)}
                            className={cn(
                                "border-b-2 px-4 py-3 text-sm font-semibold transition-colors focus:outline-none flex items-center gap-2",
                                isActive
                                    ? "border-[#ebb95e] text-[#b77918]"
                                    : "border-transparent text-[var(--admin-text-soft)] hover:text-[var(--admin-text)]"
                            )}
                        >
                            <span>{tab.label}</span>
                            {tab.badge && tab.badge > 0 ? (
                                <span className="bg-rose-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">
                                    {tab.badge}
                                </span>
                            ) : null}
                        </button>
                    );
                })}
            </div>

            {/* Sub Tab Panel rendering */}
            <div className="bg-[var(--admin-panel)] border border-[var(--admin-border)] rounded-[32px] p-6 shadow-sm">
                {activeSubTab === 'contacts' && (
                    <ContactsSubPanel messages={contactMessages} />
                )}
                {activeSubTab === 'slides' && (
                    <SlidesSubPanel slides={vitrineSlides} />
                )}
                {activeSubTab === 'artisan_du_mois' && (
                    <ArtisanDuMoisSubPanel admList={vitrineArtisanDuMois} artisans={artisans} />
                )}
                {activeSubTab === 'articles' && (
                    <ArticlesSubPanel articles={vitrineArticles} />
                )}
                {activeSubTab === 'videos' && (
                    <VideosSubPanel videos={vitrineVideos} />
                )}
                {activeSubTab === 'formations' && (
                    <FormationsSubPanel formations={vitrineFormations} money={money} />
                )}
                {activeSubTab === 'recrutements' && (
                    <RecrutementsSubPanel recrutements={vitrineRecrutements} />
                )}
                {activeSubTab === 'popups' && (
                    <PopupsSubPanel popups={vitrinePopups} />
                )}
                {activeSubTab === 'settings' && (
                    <SettingsSubPanel settings={vitrineSettings} />
                )}
            </div>
        </div>
    );
}
