import React, { useState, useMemo } from 'react';
import { useForm, router } from '@inertiajs/react';
import { cn } from '@/lib/utils';

function sanitizeUploadedFile(file: File | null): File | null {
    if (!file) return null;
    const cleanName = file.name
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-zA-Z0-9._-]/g, '_');
    return new File([file], cleanName, { type: file.type });
}

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
                        { id: 'contacts', label: 'Demandes de Contact', badge: newContactsCount },
                        { id: 'slides', label: 'Slides Hero' },
                        { id: 'artisan_du_mois', label: 'Artisan du Mois' },
                        { id: 'articles', label: 'Actualités & Blog' },
                        { id: 'videos', label: 'Capsules Vidéo' },
                        { id: 'formations', label: 'Sessions Formations' },
                        { id: 'recrutements', label: 'Espace Recrutement' },
                        { id: 'popups', label: 'Pop-ups & Banner' },
                        { id: 'settings', label: 'Paramètres Généraux' },
                    ] as const
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
            <div className="bg-white/50 border border-[var(--admin-border)] rounded-[32px] p-6 shadow-sm">
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

// =============================================================================
// SUB-PANEL 1: SLIDES HERO
// =============================================================================
function SlidesSubPanel({ slides }: { slides: any[] }) {
    const [isOpen, setIsOpen] = useState(false);
    const [editingSlide, setEditingSlide] = useState<any>(null);

    const { data, setData, post, put, delete: destroy, reset, errors, processing } = useForm({
        titre: '',
        sous_titre: '',
        image: null as File | null,
        image_url: '',
        cta_texte: '',
        cta_lien: '',
        ordre: 0,
        actif: true,
    });

    const openCreate = () => {
        reset();
        setEditingSlide(null);
        setIsOpen(true);
    };

    const openEdit = (slide: any) => {
        setEditingSlide(slide);
        setData({
            titre: slide.titre,
            sous_titre: slide.sous_titre || '',
            image: null,
            image_url: slide.image_url,
            cta_texte: slide.cta_texte || '',
            cta_lien: slide.cta_lien || '',
            ordre: slide.ordre,
            actif: Boolean(slide.actif),
        });
        setIsOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const url = editingSlide
            ? `/admin/vitrine/slides/${editingSlide.id}`
            : '/admin/vitrine/slides';

        post(url, {
            preserveScroll: true,
            onSuccess: () => {
                setIsOpen(false);
                reset();
            },
            onError: (errs) => {
                console.error('Erreur soumission slide:', errs);
            }
        });
    };

    const handleDelete = (id: number) => {
        if (window.confirm('Supprimer définitivement ce slide ?')) {
            router.delete(`/admin/vitrine/slides/${id}`, { preserveScroll: true });
        }
    };

    return (
        <div className="space-y-6">
            <div className="flex justify-between items-center">
                <h3 className="text-lg font-bold text-[var(--admin-text)]">Slides du Carrousel d'Accueil</h3>
                <button
                    onClick={openCreate}
                    className="rounded-2xl px-4 py-2 text-sm font-semibold transition bg-[#ebb95e] text-[#241b16] hover:bg-[#e0ab4b]"
                >
                    Ajouter un Slide
                </button>
            </div>

            {/* List */}
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {slides.length === 0 ? (
                    <div className="col-span-full py-8 text-center text-[var(--admin-text-soft)]">
                        Aucun slide configuré.
                    </div>
                ) : (
                    slides.map((slide) => (
                        <div key={slide.id} className="border border-[var(--admin-border)] bg-white/40 rounded-2xl overflow-hidden shadow-sm flex flex-col">
                            <div className="h-40 bg-stone-200 relative">
                                <img src={slide.image_url} alt={slide.titre} className="w-full h-full object-cover" />
                                <span className={cn(
                                    "absolute top-2 right-2 px-2 py-0.5 rounded-full text-xs font-bold border",
                                    slide.actif ? "bg-green-100 text-green-700 border-green-300" : "bg-red-100 text-red-700 border-red-300"
                                )}>
                                    {slide.actif ? 'Actif' : 'Inactif'}
                                </span>
                            </div>
                            <div className="p-4 flex-1 flex flex-col justify-between">
                                <div>
                                    <h4 className="font-bold text-[var(--admin-text)] truncate">{slide.titre}</h4>
                                    <p className="text-xs text-[var(--admin-text-soft)] mt-1 line-clamp-2">{slide.sous_titre}</p>
                                    <p className="text-xs text-[#b77918] font-bold mt-2">Ordre : {slide.ordre}</p>
                                </div>
                                <div className="mt-4 flex gap-2 border-t border-[var(--admin-border)] pt-3">
                                    <button
                                        onClick={() => openEdit(slide)}
                                        className="text-xs font-semibold text-[#b77918] bg-yellow-100 border border-yellow-300 hover:bg-yellow-200 px-3 py-1.5 rounded-xl transition"
                                    >
                                        Modifier
                                    </button>
                                    <button
                                        onClick={() => handleDelete(slide.id)}
                                        className="text-xs font-semibold text-red-600 bg-red-100 border border-red-300 hover:bg-red-200 px-3 py-1.5 rounded-xl transition"
                                    >
                                        Supprimer
                                    </button>
                                </div>
                            </div>
                        </div>
                    ))
                )}
            </div>

            {/* Modal */}
            {isOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                    <div className="bg-white rounded-[32px] border border-[var(--admin-border)] p-6 w-full max-w-lg shadow-xl relative">
                        <h4 className="text-lg font-bold text-[var(--admin-text)] mb-4">
                            {editingSlide ? 'Modifier Slide' : 'Créer Slide'}
                        </h4>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            {Object.keys(errors).length > 0 && (
                                <div className="p-3 bg-red-100 border border-red-300 text-red-700 rounded-xl text-xs space-y-1">
                                    {Object.values(errors).map((err, i) => (
                                        <p key={i}>• {err}</p>
                                    ))}
                                </div>
                            )}

                            <div>
                                <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Titre *</label>
                                <input
                                    type="text"
                                    value={data.titre}
                                    onChange={e => setData('titre', e.target.value)}
                                    className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    required
                                />
                                {errors.titre && <p className="text-red-500 text-xs mt-1">{errors.titre}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Sous-titre</label>
                                <textarea
                                    value={data.sous_titre}
                                    onChange={e => setData('sous_titre', e.target.value)}
                                    className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm h-16"
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Uploader une image</label>
                                    <input
                                        type="file"
                                        onChange={e => setData('image', sanitizeUploadedFile(e.target.files ? e.target.files[0] : null))}
                                        className="w-full text-xs"
                                    />
                                    {errors.image && <p className="text-red-500 text-xs mt-1">{errors.image}</p>}
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Ou URL Image existante</label>
                                    <input
                                        type="text"
                                        value={data.image_url}
                                        onChange={e => setData('image_url', e.target.value)}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Texte CTA</label>
                                    <input
                                        type="text"
                                        value={data.cta_texte}
                                        onChange={e => setData('cta_texte', e.target.value)}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                        placeholder="Ex: En savoir plus"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Lien CTA</label>
                                    <input
                                        type="text"
                                        value={data.cta_lien}
                                        onChange={e => setData('cta_lien', e.target.value)}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                        placeholder="Ex: /contact"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Ordre d'affichage</label>
                                    <input
                                        type="number"
                                        value={data.ordre}
                                        onChange={e => setData('ordre', Number(e.target.value))}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Statut</label>
                                    <select
                                        value={data.actif ? '1' : '0'}
                                        onChange={e => setData('actif', e.target.value === '1')}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    >
                                        <option value="1">Actif</option>
                                        <option value="0">Inactif</option>
                                    </select>
                                </div>
                            </div>

                            <div className="flex justify-end gap-2 pt-4 border-t border-[var(--admin-border)]">
                                <button
                                    type="button"
                                    onClick={() => setIsOpen(false)}
                                    className="rounded-xl px-4 py-2 text-sm font-semibold border border-[var(--admin-border)] text-[var(--admin-text)] hover:bg-stone-50"
                                >
                                    Annuler
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-xl px-4 py-2 text-sm font-semibold bg-[#ebb95e] text-[#241b16] hover:bg-[#e0ab4b] disabled:opacity-50"
                                >
                                    {processing ? 'Enregistrement...' : 'Sauvegarder'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}

// =============================================================================
// SUB-PANEL 2: ARTISAN DU MOIS
// =============================================================================
function ArtisanDuMoisSubPanel({ admList, artisans }: { admList: any[]; artisans: any[] }) {
    const [isOpen, setIsOpen] = useState(false);

    const { data, setData, post, reset, errors, processing } = useForm({
        user_id: '',
        mois: new Date().toISOString().slice(0, 7), // YYYY-MM
        photo: null as File | null,
        photo_override_url: '',
        texte_editorial: '',
        actif: true,
    });

    const openCreate = () => {
        reset();
        setIsOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/vitrine/artisan-du-mois', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setIsOpen(false);
                reset();
            },
            onError: (err) => {
                console.error('Erreur soumission artisan du mois:', err);
            }
        });
    };

    const handleDelete = (id: number) => {
        if (window.confirm('Supprimer cette mise en avant d\'artisan ?')) {
            router.delete(`/admin/vitrine/artisan-du-mois/${id}`, { preserveScroll: true });
        }
    };

    return (
        <div className="space-y-6">
            <div className="flex justify-between items-center">
                <h3 className="text-lg font-bold text-[var(--admin-text)]">Sélection de l'Artisan du Mois</h3>
                <button
                    onClick={openCreate}
                    className="rounded-2xl px-4 py-2 text-sm font-semibold transition bg-[#ebb95e] text-[#241b16] hover:bg-[#e0ab4b]"
                >
                    Mettre en avant un Artisan
                </button>
            </div>

            {/* List */}
            <div className="space-y-4">
                {admList.length === 0 ? (
                    <div className="py-8 text-center text-[var(--admin-text-soft)]">
                        Aucun artisan mis en avant pour l'instant.
                    </div>
                ) : (
                    admList.map((adm) => {
                        const dateFormatted = new Date(adm.mois).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
                        return (
                            <div key={adm.id} className="border border-[var(--admin-border)] bg-white/40 rounded-2xl p-5 flex flex-col md:flex-row gap-5 items-start">
                                <div className="h-28 w-28 shrink-0 bg-stone-100 rounded-2xl border overflow-hidden shadow-inner">
                                    <img 
                                        src={adm.photo_override_url || adm.user?.kyc_selfie_path || '/img/default-avatar.png'} 
                                        alt={adm.user?.name} 
                                        className="w-full h-full object-cover" 
                                    />
                                </div>
                                <div className="flex-1 space-y-2">
                                    <div className="flex flex-wrap items-center gap-3">
                                        <h4 className="text-lg font-bold text-[var(--admin-text)]">{adm.user?.name ?? 'Artisan Inconnu'}</h4>
                                        <span className="bg-amber-100 text-amber-700 px-3 py-1 rounded-full text-xs font-bold uppercase border border-amber-300">
                                            {dateFormatted}
                                        </span>
                                    </div>
                                    <p className="text-xs text-[var(--admin-muted)]">
                                        Métier : {adm.user?.trade ?? 'Non défini'} • Score ProsArtisan : {adm.user?.score_prosartisan}/1000
                                    </p>
                                    <p className="text-sm text-[var(--admin-text-soft)] line-clamp-3">
                                        "{adm.texte_editorial}"
                                    </p>
                                    <div className="pt-2 flex gap-2">
                                        <button
                                            onClick={() => handleDelete(adm.id)}
                                            className="text-xs font-semibold text-red-600 bg-red-100 border border-red-300 hover:bg-red-200 px-3 py-1.5 rounded-xl transition"
                                        >
                                            Retirer la mise en avant
                                        </button>
                                    </div>
                                </div>
                            </div>
                        );
                    })
                )}
            </div>

            {/* Modal */}
            {isOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                    <div className="bg-white rounded-[32px] border border-[var(--admin-border)] p-6 w-full max-w-lg shadow-xl relative">
                        <h4 className="text-lg font-bold text-[var(--admin-text)] mb-4">
                            Mettre en avant un Artisan
                        </h4>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            {Object.keys(errors).length > 0 && (
                                <div className="p-3 bg-red-100 border border-red-300 text-red-700 rounded-xl text-xs space-y-1">
                                    {Object.values(errors).map((err, i) => (
                                        <p key={i}>• {err}</p>
                                    ))}
                                </div>
                            )}

                            <div>
                                <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Choisir l'Artisan (Actif avec KYC validé) *</label>
                                <select
                                    value={data.user_id}
                                    onChange={e => setData('user_id', e.target.value)}
                                    className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    required
                                >
                                    <option value="">-- Sélectionner un artisan --</option>
                                    {artisans.map(art => (
                                        <option key={art.id} value={art.id}>
                                            {art.name} ({art.trade ?? 'Métier non défini'}) - Score: {art.score_prosartisan}/1000
                                        </option>
                                    ))}
                                </select>
                                {errors.user_id && <p className="text-red-500 text-xs mt-1">{errors.user_id}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Période (Mois concerné) *</label>
                                    <input
                                        type="month"
                                        value={data.mois}
                                        onChange={e => setData('mois', e.target.value)}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                        required
                                    />
                                    {errors.mois && <p className="text-red-500 text-xs mt-1">{errors.mois}</p>}
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Statut</label>
                                    <select
                                        value={data.actif ? '1' : '0'}
                                        onChange={e => setData('actif', e.target.value === '1')}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    >
                                        <option value="1">Actif</option>
                                        <option value="0">Inactif</option>
                                    </select>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Photo Override (Optionnel)</label>
                                    <input
                                        type="file"
                                        onChange={e => setData('photo', sanitizeUploadedFile(e.target.files ? e.target.files[0] : null))}
                                        className="w-full text-xs"
                                    />
                                    <p className="text-[10px] text-[var(--admin-muted)] mt-1">Si vide, utilise la photo KYC selfie de l'artisan.</p>
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Ou URL Photo Override</label>
                                    <input
                                        type="text"
                                        value={data.photo_override_url}
                                        onChange={e => setData('photo_override_url', e.target.value)}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Témoignage / Texte Éditorial *</label>
                                <textarea
                                    value={data.texte_editorial}
                                    onChange={e => setData('texte_editorial', e.target.value)}
                                    className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm h-28"
                                    placeholder="Racontez le parcours de l'artisan, ses forces, ses réalisations ce mois-ci..."
                                    required
                                />
                                {errors.texte_editorial && <p className="text-red-500 text-xs mt-1">{errors.texte_editorial}</p>}
                            </div>

                            <div className="flex justify-end gap-2 pt-4 border-t border-[var(--admin-border)]">
                                <button
                                    type="button"
                                    onClick={() => setIsOpen(false)}
                                    className="rounded-xl px-4 py-2 text-sm font-semibold border border-[var(--admin-border)] text-[var(--admin-text)] hover:bg-stone-50"
                                >
                                    Annuler
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-xl px-4 py-2 text-sm font-semibold bg-[#ebb95e] text-[#241b16] hover:bg-[#e0ab4b] disabled:opacity-50"
                                >
                                    {processing ? 'Enregistrement...' : 'Sauvegarder'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}

// =============================================================================
// SUB-PANEL 3: ARTICLES & ACTUALITÉS
// =============================================================================
function ArticlesSubPanel({ articles }: { articles: any[] }) {
    const [isOpen, setIsOpen] = useState(false);
    const [editingArticle, setEditingArticle] = useState<any>(null);

    const { data, setData, post, reset, errors, processing } = useForm({
        titre: '',
        contenu: '',
        image: null as File | null,
        image_url: '',
        categorie: 'actualite',
        publie: true,
    });

    const openCreate = () => {
        reset();
        setEditingArticle(null);
        setIsOpen(true);
    };

    const openEdit = (art: any) => {
        setEditingArticle(art);
        setData({
            titre: art.titre,
            contenu: art.contenu,
            image: null,
            image_url: art.image_url || '',
            categorie: art.categorie,
            publie: Boolean(art.publie),
        });
        setIsOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const url = editingArticle
            ? `/admin/vitrine/articles/${editingArticle.id}`
            : '/admin/vitrine/articles';

        post(url, {
            preserveScroll: true,
            onSuccess: () => {
                setIsOpen(false);
                reset();
            },
            onError: (errs) => {
                console.error('Erreur soumission article:', errs);
            }
        });
    };

    const handleDelete = (id: number) => {
        if (window.confirm('Supprimer cet article ?')) {
            router.delete(`/admin/vitrine/articles/${id}`, { preserveScroll: true });
        }
    };

    return (
        <div className="space-y-6">
            <div className="flex justify-between items-center">
                <h3 className="text-lg font-bold text-[var(--admin-text)]">Actualités & Rubriques d'Articles</h3>
                <button
                    onClick={openCreate}
                    className="rounded-2xl px-4 py-2 text-sm font-semibold transition bg-[#ebb95e] text-[#241b16] hover:bg-[#e0ab4b]"
                >
                    Créer un Article
                </button>
            </div>

            {/* List */}
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {articles.length === 0 ? (
                    <div className="col-span-full py-8 text-center text-[var(--admin-text-soft)]">
                        Aucun article rédigé.
                    </div>
                ) : (
                    articles.map((art) => (
                        <div key={art.id} className="border border-[var(--admin-border)] bg-white/40 rounded-2xl overflow-hidden shadow-sm flex flex-col justify-between">
                            <div>
                                <div className="h-40 bg-stone-200 relative">
                                    <img src={art.image_url || '/img/default-news.png'} alt={art.titre} className="w-full h-full object-cover" />
                                    <span className="absolute top-2 left-2 bg-stone-900/70 text-white px-2 py-0.5 rounded-full text-2xs uppercase">
                                        {art.categorie}
                                    </span>
                                    <span className={cn(
                                        "absolute top-2 right-2 px-2 py-0.5 rounded-full text-xs font-bold border",
                                        art.publie ? "bg-green-100 text-green-700 border-green-300" : "bg-yellow-100 text-yellow-700 border-yellow-300"
                                    )}>
                                        {art.publie ? 'Publié' : 'Brouillon'}
                                    </span>
                                </div>
                                <div className="p-4 space-y-2">
                                    <h4 className="font-bold text-[var(--admin-text)] line-clamp-2">{art.titre}</h4>
                                    <p className="text-xs text-[var(--admin-muted)]">
                                        Par {art.auteur?.name || 'Système'} • {new Date(art.created_at).toLocaleDateString('fr-FR')}
                                    </p>
                                    <p className="text-xs text-[var(--admin-text-soft)] line-clamp-3">{art.contenu}</p>
                                </div>
                            </div>
                            <div className="p-4 border-t border-[var(--admin-border)] flex gap-2">
                                <button
                                    onClick={() => openEdit(art)}
                                    className="text-xs font-semibold text-[#b77918] bg-yellow-100 border border-yellow-300 hover:bg-yellow-200 px-3 py-1.5 rounded-xl transition"
                                >
                                    Modifier
                                </button>
                                <button
                                    onClick={() => handleDelete(art.id)}
                                    className="text-xs font-semibold text-red-600 bg-red-100 border border-red-300 hover:bg-red-200 px-3 py-1.5 rounded-xl transition"
                                >
                                    Supprimer
                                </button>
                            </div>
                        </div>
                    ))
                )}
            </div>

            {/* Modal */}
            {isOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                    <div className="bg-white rounded-[32px] border border-[var(--admin-border)] p-6 w-full max-w-2xl shadow-xl relative">
                        <h4 className="text-lg font-bold text-[var(--admin-text)] mb-4">
                            {editingArticle ? 'Modifier l\'Article' : 'Rédiger un Article'}
                        </h4>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            {Object.keys(errors).length > 0 && (
                                <div className="p-3 bg-red-100 border border-red-300 text-red-700 rounded-xl text-xs space-y-1">
                                    {Object.values(errors).map((err, i) => (
                                        <p key={i}>• {err}</p>
                                    ))}
                                </div>
                            )}

                            <div>
                                <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Titre de l'article *</label>
                                <input
                                    type="text"
                                    value={data.titre}
                                    onChange={e => setData('titre', e.target.value)}
                                    className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    required
                                />
                                {errors.titre && <p className="text-red-500 text-xs mt-1">{errors.titre}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Catégorie *</label>
                                    <select
                                        value={data.categorie}
                                        onChange={e => setData('categorie', e.target.value as any)}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                        required
                                    >
                                        <option value="actualite">Actualité</option>
                                        <option value="evenement">Événement</option>
                                        <option value="temoignage">Témoignage</option>
                                        <option value="partenariat">Partenariat</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Statut *</label>
                                    <select
                                        value={data.publie ? '1' : '0'}
                                        onChange={e => setData('publie', e.target.value === '1')}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                        required
                                    >
                                        <option value="1">Publier immédiatement</option>
                                        <option value="0">Enregistrer en Brouillon</option>
                                    </select>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Image de couverture</label>
                                    <input
                                        type="file"
                                        onChange={e => setData('image', sanitizeUploadedFile(e.target.files ? e.target.files[0] : null))}
                                        className="w-full text-xs"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Ou URL de l'image</label>
                                    <input
                                        type="text"
                                        value={data.image_url}
                                        onChange={e => setData('image_url', e.target.value)}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Contenu de l'article *</label>
                                <textarea
                                    value={data.contenu}
                                    onChange={e => setData('contenu', e.target.value)}
                                    className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm h-48"
                                    placeholder="Rédigez le texte complet de votre article..."
                                    required
                                />
                                {errors.contenu && <p className="text-red-500 text-xs mt-1">{errors.contenu}</p>}
                            </div>

                            <div className="flex justify-end gap-2 pt-4 border-t border-[var(--admin-border)]">
                                <button
                                    type="button"
                                    onClick={() => setIsOpen(false)}
                                    className="rounded-xl px-4 py-2 text-sm font-semibold border border-[var(--admin-border)] text-[var(--admin-text)] hover:bg-stone-50"
                                >
                                    Annuler
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-xl px-4 py-2 text-sm font-semibold bg-[#ebb95e] text-[#241b16] hover:bg-[#e0ab4b] disabled:opacity-50"
                                >
                                    {processing ? 'Enregistrement...' : 'Sauvegarder'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}

// =============================================================================
// SUB-PANEL 4: CAPSULES VIDÉO
// =============================================================================
function VideosSubPanel({ videos }: { videos: any[] }) {
    const [isOpen, setIsOpen] = useState(false);
    const [editingVideo, setEditingVideo] = useState<any>(null);

    const { data, setData, post, reset, errors, processing } = useForm({
        titre: '',
        description: '',
        video_url: '',
        thumbnail: null as File | null,
        thumbnail_url: '',
        categorie: 'capsule' as const,
        ordre: 0,
        actif: true,
    });

    const openCreate = () => {
        reset();
        setEditingVideo(null);
        setIsOpen(true);
    };

    const openEdit = (vid: any) => {
        setEditingVideo(vid);
        setData({
            titre: vid.titre,
            description: vid.description || '',
            video_url: vid.video_url,
            thumbnail: null,
            thumbnail_url: vid.thumbnail_url || '',
            categorie: vid.categorie,
            ordre: vid.ordre,
            actif: Boolean(vid.actif),
        });
        setIsOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const url = editingVideo
            ? `/admin/vitrine/videos/${editingVideo.id}`
            : '/admin/vitrine/videos';

        post(url, {
            preserveScroll: true,
            onSuccess: () => {
                setIsOpen(false);
                reset();
            },
            onError: (errs) => {
                console.error('Erreur soumission vidéo:', errs);
            }
        });
    };

    const handleDelete = (id: number) => {
        if (window.confirm('Supprimer cette vidéo ?')) {
            router.delete(`/admin/vitrine/videos/${id}`, { preserveScroll: true });
        }
    };

    return (
        <div className="space-y-6">
            <div className="flex justify-between items-center">
                <h3 className="text-lg font-bold text-[var(--admin-text)]">Capsules Vidéo & Témoignages</h3>
                <button
                    onClick={openCreate}
                    className="rounded-2xl px-4 py-2 text-sm font-semibold transition bg-[#ebb95e] text-[#241b16] hover:bg-[#e0ab4b]"
                >
                    Ajouter une Vidéo
                </button>
            </div>

            {/* List */}
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {videos.length === 0 ? (
                    <div className="col-span-full py-8 text-center text-[var(--admin-text-soft)]">
                        Aucune vidéo ajoutée.
                    </div>
                ) : (
                    videos.map((vid) => (
                        <div key={vid.id} className="border border-[var(--admin-border)] bg-white/40 rounded-2xl overflow-hidden shadow-sm flex flex-col justify-between">
                            <div>
                                <div className="h-40 bg-stone-200 relative">
                                    <img src={vid.thumbnail_url || '/img/default-video.png'} alt={vid.titre} className="w-full h-full object-cover" />
                                    <div className="absolute inset-0 bg-black/30 flex items-center justify-center">
                                        <span className="h-12 w-12 rounded-full bg-white/90 flex items-center justify-center text-[#b77918] shadow-md font-bold text-xl">▶</span>
                                    </div>
                                    <span className="absolute top-2 left-2 bg-stone-900/70 text-white px-2 py-0.5 rounded-full text-2xs uppercase">
                                        {vid.categorie}
                                    </span>
                                </div>
                                <div className="p-4 space-y-1">
                                    <h4 className="font-bold text-[var(--admin-text)] line-clamp-1">{vid.titre}</h4>
                                    <p className="text-xs text-[var(--admin-text-soft)] line-clamp-2">{vid.description}</p>
                                    <p className="text-2xs text-[var(--admin-muted)] truncate">{vid.video_url}</p>
                                </div>
                            </div>
                            <div className="p-4 border-t border-[var(--admin-border)] flex gap-2">
                                <button
                                    onClick={() => openEdit(vid)}
                                    className="text-xs font-semibold text-[#b77918] bg-yellow-100 border border-yellow-300 hover:bg-yellow-200 px-3 py-1.5 rounded-xl transition"
                                >
                                    Modifier
                                </button>
                                <button
                                    onClick={() => handleDelete(vid.id)}
                                    className="text-xs font-semibold text-red-600 bg-red-100 border border-red-300 hover:bg-red-200 px-3 py-1.5 rounded-xl transition"
                                >
                                    Supprimer
                                </button>
                            </div>
                        </div>
                    ))
                )}
            </div>

            {/* Modal */}
            {isOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                    <div className="bg-white rounded-[32px] border border-[var(--admin-border)] p-6 w-full max-w-lg shadow-xl relative">
                        <h4 className="text-lg font-bold text-[var(--admin-text)] mb-4">
                            {editingVideo ? 'Modifier la Vidéo' : 'Ajouter une Vidéo'}
                        </h4>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            {Object.keys(errors).length > 0 && (
                                <div className="p-3 bg-red-100 border border-red-300 text-red-700 rounded-xl text-xs space-y-1">
                                    {Object.values(errors).map((err, i) => (
                                        <p key={i}>• {err}</p>
                                    ))}
                                </div>
                            )}

                            <div>
                                <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Titre de la vidéo *</label>
                                <input
                                    type="text"
                                    value={data.titre}
                                    onChange={e => setData('titre', e.target.value)}
                                    className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    required
                                />
                                {errors.titre && <p className="text-red-500 text-xs mt-1">{errors.titre}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Description</label>
                                <textarea
                                    value={data.description}
                                    onChange={e => setData('description', e.target.value)}
                                    className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm h-16"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Lien de la Vidéo (YouTube Embed ou URL) *</label>
                                <input
                                    type="text"
                                    value={data.video_url}
                                    onChange={e => setData('video_url', e.target.value)}
                                    className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    placeholder="Ex: https://www.youtube.com/watch?v=..."
                                    required
                                />
                                {errors.video_url && <p className="text-red-500 text-xs mt-1">{errors.video_url}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Image miniature (Thumbnail)</label>
                                    <input
                                        type="file"
                                        onChange={e => setData('thumbnail', sanitizeUploadedFile(e.target.files ? e.target.files[0] : null))}
                                        className="w-full text-xs"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Ou URL miniature</label>
                                    <input
                                        type="text"
                                        value={data.thumbnail_url}
                                        onChange={e => setData('thumbnail_url', e.target.value)}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-3 gap-4">
                                <div className="col-span-2">
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Catégorie *</label>
                                    <select
                                        value={data.categorie}
                                        onChange={e => setData('categorie', e.target.value as any)}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    >
                                        <option value="capsule">Capsule Pédagogique</option>
                                        <option value="formation">Formation</option>
                                        <option value="temoignage">Témoignage Client/Artisan</option>
                                        <option value="evenement">Événement & Conférences</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Ordre *</label>
                                    <input
                                        type="number"
                                        value={data.ordre}
                                        onChange={e => setData('ordre', Number(e.target.value))}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Statut</label>
                                <select
                                    value={data.actif ? '1' : '0'}
                                    onChange={e => setData('actif', e.target.value === '1')}
                                    className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                >
                                    <option value="1">Actif</option>
                                    <option value="0">Masqué</option>
                                </select>
                            </div>

                            <div className="flex justify-end gap-2 pt-4 border-t border-[var(--admin-border)]">
                                <button
                                    type="button"
                                    onClick={() => setIsOpen(false)}
                                    className="rounded-xl px-4 py-2 text-sm font-semibold border border-[var(--admin-border)] text-[var(--admin-text)] hover:bg-stone-50"
                                >
                                    Annuler
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-xl px-4 py-2 text-sm font-semibold bg-[#ebb95e] text-[#241b16] hover:bg-[#e0ab4b] disabled:opacity-50"
                                >
                                    {processing ? 'Enregistrement...' : 'Sauvegarder'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}

// =============================================================================
// SUB-PANEL 5: SESSIONS FORMATION
// =============================================================================
function FormationsSubPanel({ formations, money }: { formations: any[]; money: (v: number) => string }) {
    const [isOpen, setIsOpen] = useState(false);
    const [editingFormation, setEditingFormation] = useState<any>(null);

    const { data, setData, post, reset, errors, processing } = useForm({
        titre: '',
        description: '',
        image: null as File | null,
        image_url: '',
        date_debut: '',
        date_fin: '',
        lieu: '',
        formateur: '',
        places_total: 20,
        tarif: 0,
        lien_inscription: '',
        actif: true,
    });

    const openCreate = () => {
        reset();
        setEditingFormation(null);
        setIsOpen(true);
    };

    const openEdit = (form: any) => {
        setEditingFormation(form);
        setData({
            titre: form.titre,
            description: form.description,
            image: null,
            image_url: form.image_url || '',
            date_debut: form.date_debut ? form.date_debut.slice(0, 10) : '',
            date_fin: form.date_fin ? form.date_fin.slice(0, 10) : '',
            lieu: form.lieu,
            formateur: form.formateur || '',
            places_total: form.places_total || 20,
            tarif: form.tarif || 0,
            lien_inscription: form.lien_inscription || '',
            actif: Boolean(form.actif),
        });
        setIsOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const url = editingFormation
            ? `/admin/vitrine/formations/${editingFormation.id}`
            : '/admin/vitrine/formations';

        post(url, {
            preserveScroll: true,
            onSuccess: () => {
                setIsOpen(false);
                reset();
            },
            onError: (errs) => {
                console.error('Erreur soumission formation:', errs);
            }
        });
    };

    const handleDelete = (id: number) => {
        if (window.confirm('Supprimer cette formation ?')) {
            router.delete(`/admin/vitrine/formations/${id}`, { preserveScroll: true });
        }
    };

    return (
        <div className="space-y-6">
            <div className="flex justify-between items-center">
                <h3 className="text-lg font-bold text-[var(--admin-text)]">Sessions de Formations pour Artisans</h3>
                <button
                    onClick={openCreate}
                    className="rounded-2xl px-4 py-2 text-sm font-semibold transition bg-[#ebb95e] text-[#241b16] hover:bg-[#e0ab4b]"
                >
                    Créer une Session
                </button>
            </div>

            {/* List */}
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {formations.length === 0 ? (
                    <div className="col-span-full py-8 text-center text-[var(--admin-text-soft)]">
                        Aucune formation planifiée.
                    </div>
                ) : (
                    formations.map((form) => (
                        <div key={form.id} className="border border-[var(--admin-border)] bg-white/40 rounded-2xl overflow-hidden shadow-sm flex flex-col justify-between">
                            <div>
                                <div className="h-40 bg-stone-200 relative">
                                    <img src={form.image_url || '/img/default-training.png'} alt={form.titre} className="w-full h-full object-cover" />
                                    <span className={cn(
                                        "absolute top-2 right-2 px-2 py-0.5 rounded-full text-xs font-bold border",
                                        form.actif ? "bg-green-100 text-green-700 border-green-300" : "bg-red-100 text-red-700 border-red-300"
                                    )}>
                                        {form.actif ? 'Actif' : 'Masqué'}
                                    </span>
                                </div>
                                <div className="p-4 space-y-2">
                                    <h4 className="font-bold text-[var(--admin-text)] line-clamp-1">{form.titre}</h4>
                                    <div className="text-xs text-[var(--admin-text-soft)] space-y-1 bg-stone-50 border rounded-xl p-2.5">
                                        <p>📅 <b>Début</b> : {new Date(form.date_debut).toLocaleDateString('fr-FR')}</p>
                                        <p>📍 <b>Lieu</b> : {form.lieu}</p>
                                        <p>👨‍🏫 <b>Formateur</b> : {form.formateur || 'Non renseigné'}</p>
                                        <p>👥 <b>Places</b> : {form.places_restantes} / {form.places_total}</p>
                                        <p>💰 <b>Tarif</b> : <span className="text-[#b77918] font-bold">{money(form.tarif)}</span></p>
                                    </div>
                                    <p className="text-xs text-[var(--admin-text-soft)] line-clamp-3">{form.description}</p>
                                </div>
                            </div>
                            <div className="p-4 border-t border-[var(--admin-border)] flex gap-2">
                                <button
                                    onClick={() => openEdit(form)}
                                    className="text-xs font-semibold text-[#b77918] bg-yellow-100 border border-yellow-300 hover:bg-yellow-200 px-3 py-1.5 rounded-xl transition"
                                >
                                    Modifier
                                </button>
                                <button
                                    onClick={() => handleDelete(form.id)}
                                    className="text-xs font-semibold text-red-600 bg-red-100 border border-red-300 hover:bg-red-200 px-3 py-1.5 rounded-xl transition"
                                >
                                    Supprimer
                                </button>
                            </div>
                        </div>
                    ))
                )}
            </div>

            {/* Modal */}
            {isOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                    <div className="bg-white rounded-[32px] border border-[var(--admin-border)] p-6 w-full max-w-xl shadow-xl relative">
                        <h4 className="text-lg font-bold text-[var(--admin-text)] mb-4">
                            {editingFormation ? 'Modifier Session Formation' : 'Créer Session Formation'}
                        </h4>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            {Object.keys(errors).length > 0 && (
                                <div className="p-3 bg-red-100 border border-red-300 text-red-700 rounded-xl text-xs space-y-1">
                                    {Object.values(errors).map((err, i) => (
                                        <p key={i}>• {err}</p>
                                    ))}
                                </div>
                            )}

                            <div>
                                <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Titre de la formation *</label>
                                <input
                                    type="text"
                                    value={data.titre}
                                    onChange={e => setData('titre', e.target.value)}
                                    className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    required
                                />
                                {errors.titre && <p className="text-red-500 text-xs mt-1">{errors.titre}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Date début *</label>
                                    <input
                                        type="date"
                                        value={data.date_debut}
                                        onChange={e => setData('date_debut', e.target.value)}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                        required
                                    />
                                    {errors.date_debut && <p className="text-red-500 text-xs mt-1">{errors.date_debut}</p>}
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Date fin</label>
                                    <input
                                        type="date"
                                        value={data.date_fin}
                                        onChange={e => setData('date_fin', e.target.value)}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Lieu *</label>
                                    <input
                                        type="text"
                                        value={data.lieu}
                                        onChange={e => setData('lieu', e.target.value)}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                        required
                                    />
                                    {errors.lieu && <p className="text-red-500 text-xs mt-1">{errors.lieu}</p>}
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Formateur</label>
                                    <input
                                        type="text"
                                        value={data.formateur}
                                        onChange={e => setData('formateur', e.target.value)}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-3 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Places max</label>
                                    <input
                                        type="number"
                                        value={data.places_total}
                                        onChange={e => setData('places_total', Number(e.target.value))}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Tarif (FCFA) *</label>
                                    <input
                                        type="number"
                                        value={data.tarif}
                                        onChange={e => setData('tarif', Number(e.target.value))}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                        required
                                    />
                                    {errors.tarif && <p className="text-red-500 text-xs mt-1">{errors.tarif}</p>}
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Actif</label>
                                    <select
                                        value={data.actif ? '1' : '0'}
                                        onChange={e => setData('actif', e.target.value === '1')}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    >
                                        <option value="1">Oui</option>
                                        <option value="0">Non</option>
                                    </select>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Image couverture</label>
                                    <input
                                        type="file"
                                        onChange={e => setData('image', sanitizeUploadedFile(e.target.files ? e.target.files[0] : null))}
                                        className="w-full text-xs"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Lien d'inscription externe</label>
                                    <input
                                        type="text"
                                        value={data.lien_inscription}
                                        onChange={e => setData('lien_inscription', e.target.value)}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Description / Contenu formation *</label>
                                <textarea
                                    value={data.description}
                                    onChange={e => setData('description', e.target.value)}
                                    className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm h-28"
                                    required
                                />
                                {errors.description && <p className="text-red-500 text-xs mt-1">{errors.description}</p>}
                            </div>

                            <div className="flex justify-end gap-2 pt-4 border-t border-[var(--admin-border)]">
                                <button
                                    type="button"
                                    onClick={() => setIsOpen(false)}
                                    className="rounded-xl px-4 py-2 text-sm font-semibold border border-[var(--admin-border)] text-[var(--admin-text)] hover:bg-stone-50"
                                >
                                    Annuler
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-xl px-4 py-2 text-sm font-semibold bg-[#ebb95e] text-[#241b16] hover:bg-[#e0ab4b] disabled:opacity-50"
                                >
                                    {processing ? 'Enregistrement...' : 'Sauvegarder'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}

// =============================================================================
// SUB-PANEL 6: RECRUTEMENTS
// =============================================================================
function RecrutementsSubPanel({ recrutements }: { recrutements: any[] }) {
    const [isOpen, setIsOpen] = useState(false);
    const [editingRecrutement, setEditingRecrutement] = useState<any>(null);

    const { data, setData, post, reset, errors, processing } = useForm({
        titre: '',
        description: '',
        metier: '',
        lieu: '',
        type_contrat: 'cdi' as const,
        date_limite: '',
        contact_email: '',
        actif: true,
    });

    const openCreate = () => {
        reset();
        setEditingRecrutement(null);
        setIsOpen(true);
    };

    const openEdit = (job: any) => {
        setEditingRecrutement(job);
        setData({
            titre: job.titre,
            description: job.description,
            metier: job.metier,
            lieu: job.lieu,
            type_contrat: job.type_contrat,
            date_limite: job.date_limite ? job.date_limite.slice(0, 10) : '',
            contact_email: job.contact_email || '',
            actif: Boolean(job.actif),
        });
        setIsOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const url = editingRecrutement
            ? `/admin/vitrine/recrutements/${editingRecrutement.id}`
            : '/admin/vitrine/recrutements';

        post(url, {
            preserveScroll: true,
            onSuccess: () => {
                setIsOpen(false);
                reset();
            },
            onError: (errs) => {
                console.error('Erreur soumission recrutement:', errs);
            }
        });
    };

    const handleDelete = (id: number) => {
        if (window.confirm('Supprimer cette offre d\'emploi ?')) {
            router.delete(`/admin/vitrine/recrutements/${id}`, { preserveScroll: true });
        }
    };

    return (
        <div className="space-y-6">
            <div className="flex justify-between items-center">
                <h3 className="text-lg font-bold text-[var(--admin-text)]">Espace Recrutement & Métiers de l'Artisanat</h3>
                <button
                    onClick={openCreate}
                    className="rounded-2xl px-4 py-2 text-sm font-semibold transition bg-[#ebb95e] text-[#241b16] hover:bg-[#e0ab4b]"
                >
                    Publier une Offre
                </button>
            </div>

            {/* List */}
            <div className="space-y-4">
                {recrutements.length === 0 ? (
                    <div className="py-8 text-center text-[var(--admin-text-soft)]">
                        Aucune offre d'emploi en cours.
                    </div>
                ) : (
                    recrutements.map((job) => {
                        const isExpired = job.date_limite && new Date(job.date_limite + 'T23:59:59').getTime() < Date.now();
                        return (
                            <div key={job.id} className="border border-[var(--admin-border)] bg-white/40 rounded-2xl p-5 flex flex-col md:flex-row justify-between items-start gap-4">
                                <div className="space-y-2">
                                    <div className="flex items-center flex-wrap gap-2">
                                        <h4 className="text-lg font-bold text-[var(--admin-text)]">{job.titre}</h4>
                                        <span className="bg-blue-100 text-blue-700 px-3 py-0.5 rounded-full text-xs font-bold uppercase border border-blue-300">
                                            {job.type_contrat.toUpperCase()}
                                        </span>
                                        {!job.actif ? (
                                            <span className="bg-stone-100 text-stone-700 px-2.5 py-0.5 rounded-full text-xs font-semibold border border-stone-300">
                                                ⚪ Masqué manuellement
                                            </span>
                                        ) : isExpired ? (
                                            <span className="bg-rose-100 text-rose-800 px-2.5 py-0.5 rounded-full text-xs font-bold border border-rose-300 flex items-center gap-1">
                                                <span className="h-1.5 w-1.5 rounded-full bg-rose-600"></span>
                                                🔴 Expirée (Désactivée automatiquement du front office)
                                            </span>
                                        ) : (
                                            <span className="bg-emerald-100 text-emerald-800 px-2.5 py-0.5 rounded-full text-xs font-bold border border-emerald-300 flex items-center gap-1">
                                                <span className="h-1.5 w-1.5 rounded-full bg-emerald-600"></span>
                                                🟢 En ligne sur le front
                                            </span>
                                        )}
                                    </div>
                                    <p className="text-xs text-[var(--admin-muted)]">
                                        Métier ciblé : <b>{job.metier}</b> • Lieu : {job.lieu} • Date limite : {job.date_limite ? new Date(job.date_limite).toLocaleDateString('fr-FR') : 'Aucune (Toujours active)'}
                                    </p>
                                    <p className="text-sm text-[var(--admin-text-soft)] line-clamp-3">{job.description}</p>
                                </div>
                                <div className="flex gap-2 shrink-0 self-end md:self-start">
                                    <button
                                        onClick={() => openEdit(job)}
                                        className="text-xs font-semibold text-[#b77918] bg-yellow-100 border border-yellow-300 hover:bg-yellow-200 px-3 py-1.5 rounded-xl transition"
                                    >
                                        Modifier
                                    </button>
                                    <button
                                        onClick={() => handleDelete(job.id)}
                                        className="text-xs font-semibold text-red-600 bg-red-100 border border-red-300 hover:bg-red-200 px-3 py-1.5 rounded-xl transition"
                                    >
                                        Supprimer
                                    </button>
                                </div>
                            </div>
                        );
                    })
                )}
            </div>

            {/* Modal */}
            {isOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                    <div className="bg-white rounded-[32px] border border-[var(--admin-border)] p-6 w-full max-w-lg shadow-xl relative">
                        <h4 className="text-lg font-bold text-[var(--admin-text)] mb-4">
                            {editingRecrutement ? 'Modifier l\'offre' : 'Publier une offre d\'emploi'}
                        </h4>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            {Object.keys(errors).length > 0 && (
                                <div className="p-3 bg-red-100 border border-red-300 text-red-700 rounded-xl text-xs space-y-1">
                                    {Object.values(errors).map((err, i) => (
                                        <p key={i}>• {err}</p>
                                    ))}
                                </div>
                            )}

                            <div>
                                <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Titre du poste *</label>
                                <input
                                    type="text"
                                    value={data.titre}
                                    onChange={e => setData('titre', e.target.value)}
                                    className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    required
                                />
                                {errors.titre && <p className="text-red-500 text-xs mt-1">{errors.titre}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Métier cible *</label>
                                    <input
                                        type="text"
                                        value={data.metier}
                                        onChange={e => setData('metier', e.target.value)}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                        placeholder="Ex: Électricien, Maçon"
                                        required
                                    />
                                    {errors.metier && <p className="text-red-500 text-xs mt-1">{errors.metier}</p>}
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Lieu *</label>
                                    <input
                                        type="text"
                                        value={data.lieu}
                                        onChange={e => setData('lieu', e.target.value)}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                        placeholder="Ex: Abidjan - Cocody"
                                        required
                                    />
                                    {errors.lieu && <p className="text-red-500 text-xs mt-1">{errors.lieu}</p>}
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Type de Contrat *</label>
                                    <select
                                        value={data.type_contrat}
                                        onChange={e => setData('type_contrat', e.target.value as any)}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                        required
                                    >
                                        <option value="cdi">CDI</option>
                                        <option value="cdd">CDD</option>
                                        <option value="stage">Stage</option>
                                        <option value="freelance">Freelance / Mission</option>
                                        <option value="apprentissage">Apprentissage</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Date limite de candidature</label>
                                    <input
                                        type="date"
                                        value={data.date_limite}
                                        onChange={e => setData('date_limite', e.target.value)}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    />
                                    <p className="text-[10px] text-amber-700 font-medium mt-1">
                                        ⏱️ L'offre se désactivera automatiquement du Front Office dès que cette date sera dépassée.
                                    </p>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Email de contact pour postuler</label>
                                    <input
                                        type="email"
                                        value={data.contact_email}
                                        onChange={e => setData('contact_email', e.target.value)}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                        placeholder="Ex: recrutement@prosartisan.ci"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Statut d'affichage</label>
                                    <select
                                        value={data.actif ? '1' : '0'}
                                        onChange={e => setData('actif', e.target.value === '1')}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    >
                                        <option value="1">Actif / En ligne</option>
                                        <option value="0">Masqué / Clôturé</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Description du poste & Profil recherché *</label>
                                <textarea
                                    value={data.description}
                                    onChange={e => setData('description', e.target.value)}
                                    className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm h-32"
                                    required
                                />
                                {errors.description && <p className="text-red-500 text-xs mt-1">{errors.description}</p>}
                            </div>

                            <div className="flex justify-end gap-2 pt-4 border-t border-[var(--admin-border)]">
                                <button
                                    type="button"
                                    onClick={() => setIsOpen(false)}
                                    className="rounded-xl px-4 py-2 text-sm font-semibold border border-[var(--admin-border)] text-[var(--admin-text)] hover:bg-stone-50"
                                >
                                    Annuler
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-xl px-4 py-2 text-sm font-semibold bg-[#ebb95e] text-[#241b16] hover:bg-[#e0ab4b] disabled:opacity-50"
                                >
                                    {processing ? 'Enregistrement...' : 'Sauvegarder'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}

// =============================================================================
// SUB-PANEL 7: POPUPS PROMOTIONNELS
// =============================================================================
function PopupsSubPanel({ popups }: { popups: any[] }) {
    const [isOpen, setIsOpen] = useState(false);
    const [editingPopup, setEditingPopup] = useState<any>(null);

    const { data, setData, post, reset, errors, processing } = useForm({
        titre: '',
        contenu: '',
        image: null as File | null,
        image_url: '',
        lien_cta: '',
        texte_cta: '',
        date_debut: '',
        date_fin: '',
        actif: true,
    });

    const openCreate = () => {
        reset();
        setEditingPopup(null);
        setIsOpen(true);
    };

    const openEdit = (pop: any) => {
        setEditingPopup(pop);
        setData({
            titre: pop.titre,
            contenu: pop.contenu || '',
            image: null,
            image_url: pop.image_url || '',
            lien_cta: pop.lien_cta || '',
            texte_cta: pop.texte_cta || '',
            date_debut: pop.date_debut ? pop.date_debut.slice(0, 16) : '',
            date_fin: pop.date_fin ? pop.date_fin.slice(0, 16) : '',
            actif: Boolean(pop.actif),
        });
        setIsOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const url = editingPopup
            ? `/admin/vitrine/popups/${editingPopup.id}`
            : '/admin/vitrine/popups';

        post(url, {
            preserveScroll: true,
            onSuccess: () => {
                setIsOpen(false);
                reset();
            },
            onError: (errs) => {
                console.error('Erreur soumission popup:', errs);
            }
        });
    };

    const handleDelete = (id: number) => {
        if (window.confirm('Supprimer ce popup ?')) {
            router.delete(`/admin/vitrine/popups/${id}`, { preserveScroll: true });
        }
    };

    return (
        <div className="space-y-6">
            <div className="flex justify-between items-center">
                <h3 className="text-lg font-bold text-[var(--admin-text)]">Bannière & Pop-ups Promotionnels</h3>
                <button
                    onClick={openCreate}
                    className="rounded-2xl px-4 py-2 text-sm font-semibold transition bg-[#ebb95e] text-[#241b16] hover:bg-[#e0ab4b]"
                >
                    Créer une Pop-up
                </button>
            </div>

            {/* List */}
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {popups.length === 0 ? (
                    <div className="col-span-full py-8 text-center text-[var(--admin-text-soft)]">
                        Aucune pop-up ou bannière configurée.
                    </div>
                ) : (
                    popups.map((pop) => {
                        const isNow = new Date() >= new Date(pop.date_debut) && new Date() <= new Date(pop.date_fin);
                        return (
                            <div key={pop.id} className="border border-[var(--admin-border)] bg-white/40 rounded-2xl overflow-hidden shadow-sm flex flex-col justify-between">
                                <div>
                                    <div className="h-40 bg-stone-200 relative">
                                        <img src={pop.image_url || '/img/default-popup.png'} alt={pop.titre} className="w-full h-full object-cover" />
                                        <span className={cn(
                                            "absolute top-2 right-2 px-2 py-0.5 rounded-full text-xs font-bold border",
                                            pop.actif && isNow ? "bg-green-100 text-green-700 border-green-300" : "bg-stone-100 text-stone-700 border-stone-300"
                                        )}>
                                            {pop.actif && isNow ? 'Actif maintenant' : 'Planifié / Inactif'}
                                        </span>
                                    </div>
                                    <div className="p-4 space-y-2">
                                        <h4 className="font-bold text-[var(--admin-text)] line-clamp-1">{pop.titre}</h4>
                                        <p className="text-xs text-[var(--admin-text-soft)] line-clamp-3">{pop.contenu}</p>
                                        <div className="text-[10px] text-[var(--admin-muted)] bg-stone-100/50 p-2 rounded-lg">
                                            <p>Début : {new Date(pop.date_debut).toLocaleString('fr-FR')}</p>
                                            <p>Fin : {new Date(pop.date_fin).toLocaleString('fr-FR')}</p>
                                        </div>
                                    </div>
                                </div>
                                <div className="p-4 border-t border-[var(--admin-border)] flex gap-2">
                                    <button
                                        onClick={() => openEdit(pop)}
                                        className="text-xs font-semibold text-[#b77918] bg-yellow-100 border border-yellow-300 hover:bg-yellow-200 px-3 py-1.5 rounded-xl transition"
                                    >
                                        Modifier
                                    </button>
                                    <button
                                        onClick={() => handleDelete(pop.id)}
                                        className="text-xs font-semibold text-red-600 bg-red-100 border border-red-300 hover:bg-red-200 px-3 py-1.5 rounded-xl transition"
                                    >
                                        Supprimer
                                    </button>
                                </div>
                            </div>
                        );
                    })
                )}
            </div>

            {/* Modal */}
            {isOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                    <div className="bg-white rounded-[32px] border border-[var(--admin-border)] p-6 w-full max-w-lg shadow-xl relative">
                        <h4 className="text-lg font-bold text-[var(--admin-text)] mb-4">
                            {editingPopup ? 'Modifier Pop-up' : 'Créer Pop-up'}
                        </h4>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            {Object.keys(errors).length > 0 && (
                                <div className="p-3 bg-red-100 border border-red-300 text-red-700 rounded-xl text-xs space-y-1">
                                    {Object.values(errors).map((err, i) => (
                                        <p key={i}>• {err}</p>
                                    ))}
                                </div>
                            )}

                            <div>
                                <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Titre *</label>
                                <input
                                    type="text"
                                    value={data.titre}
                                    onChange={e => setData('titre', e.target.value)}
                                    className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    required
                                />
                                {errors.titre && <p className="text-red-500 text-xs mt-1">{errors.titre}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Date début de diffusion *</label>
                                    <input
                                        type="datetime-local"
                                        value={data.date_debut}
                                        onChange={e => setData('date_debut', e.target.value)}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                        required
                                    />
                                    {errors.date_debut && <p className="text-red-500 text-xs mt-1">{errors.date_debut}</p>}
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Date fin de diffusion *</label>
                                    <input
                                        type="datetime-local"
                                        value={data.date_fin}
                                        onChange={e => setData('date_fin', e.target.value)}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                        required
                                    />
                                    {errors.date_fin && <p className="text-red-500 text-xs mt-1">{errors.date_fin}</p>}
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Image promotionnelle</label>
                                    <input
                                        type="file"
                                        onChange={e => setData('image', sanitizeUploadedFile(e.target.files ? e.target.files[0] : null))}
                                        className="w-full text-xs"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Ou URL Image</label>
                                    <input
                                        type="text"
                                        value={data.image_url}
                                        onChange={e => setData('image_url', e.target.value)}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Texte CTA</label>
                                    <input
                                        type="text"
                                        value={data.texte_cta}
                                        onChange={e => setData('texte_cta', e.target.value)}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                        placeholder="Ex: Réclamer mon code"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Lien de redirection CTA</label>
                                    <input
                                        type="text"
                                        value={data.lien_cta}
                                        onChange={e => setData('lien_cta', e.target.value)}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                        placeholder="Ex: /promo-codes"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Texte promotionnel</label>
                                <textarea
                                    value={data.contenu}
                                    onChange={e => setData('contenu', e.target.value)}
                                    className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm h-20"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Activer le popup</label>
                                <select
                                    value={data.actif ? '1' : '0'}
                                    onChange={e => setData('actif', e.target.value === '1')}
                                    className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                >
                                    <option value="1">Oui</option>
                                    <option value="0">Non</option>
                                </select>
                            </div>

                            <div className="flex justify-end gap-2 pt-4 border-t border-[var(--admin-border)]">
                                <button
                                    type="button"
                                    onClick={() => setIsOpen(false)}
                                    className="rounded-xl px-4 py-2 text-sm font-semibold border border-[var(--admin-border)] text-[var(--admin-text)] hover:bg-stone-50"
                                >
                                    Annuler
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-xl px-4 py-2 text-sm font-semibold bg-[#ebb95e] text-[#241b16] hover:bg-[#e0ab4b] disabled:opacity-50"
                                >
                                    {processing ? 'Enregistrement...' : 'Sauvegarder'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}

// =============================================================================
// SUB-PANEL 8: PARAMÈTRES GÉNÉRAUX
// =============================================================================
function SettingsSubPanel({ settings }: { settings: any[] }) {
    // Map list of settings to helper object
    const settingsMap = useMemo(() => {
        const map: Record<string, string> = {
            chiffres_cles_artisans: '800',
            chiffres_cles_utilisateurs: '3000',
            chiffres_cles_missions: '5000',
            chiffres_cles_metiers: '29',
            
            // Coordonnées & Contact
            contact_phone: '+225 07 00 00 00 00',
            contact_email: 'contact@prosartisan.ci',
            footer_address: "Plateau, Boulevard de la République, Abidjan, Côte d'Ivoire",
            
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

            {/* 1. CHIFFRES CLÉS */}
            <div className="space-y-4 bg-white/40 border border-[var(--admin-border)] p-6 rounded-[24px]">
                <h4 className="text-sm font-bold text-[#b77918] uppercase tracking-wider flex items-center gap-2">
                    <span>📊</span> Chiffres Clés (Hero Vitrine)
                </h4>
                <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-4">
                    <div>
                        <label className="block text-xs font-semibold text-[var(--admin-text)] mb-1">Nombre d'artisans</label>
                        <input
                            type="text"
                            value={data.chiffres_cles_artisans}
                            onChange={e => setData('chiffres_cles_artisans', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm font-semibold"
                            placeholder="Ex: 800"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-[var(--admin-text)] mb-1">Nombre d'utilisateurs</label>
                        <input
                            type="text"
                            value={data.chiffres_cles_utilisateurs}
                            onChange={e => setData('chiffres_cles_utilisateurs', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm font-semibold"
                            placeholder="Ex: 3 000"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-[var(--admin-text)] mb-1">Missions complétées</label>
                        <input
                            type="text"
                            value={data.chiffres_cles_missions}
                            onChange={e => setData('chiffres_cles_missions', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm font-semibold"
                            placeholder="Ex: 5 000"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-[var(--admin-text)] mb-1">Métiers répertoriés</label>
                        <input
                            type="text"
                            value={data.chiffres_cles_metiers}
                            onChange={e => setData('chiffres_cles_metiers', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm font-semibold"
                            placeholder="Ex: 29"
                        />
                    </div>
                </div>
            </div>

            {/* 2. FOOTER - IDENTITÉ & MARQUE */}
            <div className="space-y-4 bg-white/40 border border-[var(--admin-border)] p-6 rounded-[24px]">
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
            <div className="space-y-4 bg-white/40 border border-[var(--admin-border)] p-6 rounded-[24px]">
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
                            placeholder="+225 07 00 00 00 00"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-[var(--admin-text)] mb-1">Email officiel de support</label>
                        <input
                            type="email"
                            value={data.contact_email}
                            onChange={e => setData('contact_email', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm font-semibold"
                            placeholder="contact@prosartisan.ci"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-[var(--admin-text)] mb-1">Adresse géographique / Siège</label>
                        <input
                            type="text"
                            value={data.footer_address}
                            onChange={e => setData('footer_address', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                            placeholder="Plateau, Boulevard de la République, Abidjan"
                        />
                    </div>
                </div>
            </div>

            {/* 4. RÉSEAUX SOCIAUX */}
            <div className="space-y-4 bg-white/40 border border-[var(--admin-border)] p-6 rounded-[24px]">
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
            <div className="space-y-4 bg-white/40 border border-[var(--admin-border)] p-6 rounded-[24px]">
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
            <div className="space-y-4 bg-white/40 border border-[var(--admin-border)] p-6 rounded-[24px]">
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
            <div className="space-y-4 bg-white/40 border border-[var(--admin-border)] p-6 rounded-[24px]">
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

// =============================================================================
// SUB-PANEL 9: DEMANDES DE CONTACT & GESTION DES REQUÊTES
// =============================================================================
function ContactsSubPanel({ messages = [] }: { messages: any[] }) {
    const [selectedMessage, setSelectedMessage] = useState<any | null>(null);
    const [statusFilter, setStatusFilter] = useState<'all' | 'nouveau' | 'en_cours' | 'traite' | 'archive'>('all');
    const [searchQuery, setSearchQuery] = useState('');

    const { data, setData, post, processing, errors } = useForm({
        statut: 'nouveau' as 'nouveau' | 'en_cours' | 'traite' | 'archive',
        priorite: 'normale' as 'basse' | 'normale' | 'urgente',
        notes_admin: '',
        reponse_envoyee: '',
    });

    const openManage = (msg: any) => {
        setSelectedMessage(msg);
        setData({
            statut: msg.statut || 'nouveau',
            priorite: msg.priorite || 'normale',
            notes_admin: msg.notes_admin || '',
            reponse_envoyee: msg.reponse_envoyee || '',
        });
    };

    const handleUpdate = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedMessage) return;

        post(`/admin/vitrine/contacts/${selectedMessage.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setSelectedMessage(null);
            },
            onError: (errs) => {
                console.error('Erreur mise à jour contact:', errs);
            }
        });
    };

    const handleSendReply = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedMessage) return;

        post(`/admin/vitrine/contacts/${selectedMessage.id}/reply`, {
            preserveScroll: true,
            onSuccess: () => {
                setSelectedMessage(null);
            },
            onError: (errs) => {
                console.error('Erreur réponse contact:', errs);
            }
        });
    };

    const handleQuickStatus = (msg: any, newStatus: string) => {
        router.post(`/admin/vitrine/contacts/${msg.id}`, {
            statut: newStatus,
            priorite: msg.priorite || 'normale',
            notes_admin: msg.notes_admin || '',
        }, { preserveScroll: true });
    };

    const handleDelete = (id: number) => {
        if (window.confirm('Supprimer définitivement cette demande de contact ?')) {
            router.delete(`/admin/vitrine/contacts/${id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    if (selectedMessage?.id === id) {
                        setSelectedMessage(null);
                    }
                }
            });
        }
    };

    // KPIs
    const stats = useMemo(() => {
        return {
            total: messages.length,
            nouveau: messages.filter(m => m.statut === 'nouveau').length,
            en_cours: messages.filter(m => m.statut === 'en_cours').length,
            traite: messages.filter(m => m.statut === 'traite').length,
            archive: messages.filter(m => m.statut === 'archive').length,
        };
    }, [messages]);

    // Filtering
    const filteredMessages = useMemo(() => {
        return messages.filter(msg => {
            const matchesStatus = statusFilter === 'all' || msg.statut === statusFilter;
            const q = searchQuery.toLowerCase().trim();
            const matchesSearch = !q || (
                (msg.nom || '').toLowerCase().includes(q) ||
                (msg.email || '').toLowerCase().includes(q) ||
                (msg.telephone || '').toLowerCase().includes(q) ||
                (msg.sujet || '').toLowerCase().includes(q) ||
                (msg.message || '').toLowerCase().includes(q) ||
                (msg.artisan?.name || '').toLowerCase().includes(q)
            );
            return matchesStatus && matchesSearch;
        });
    }, [messages, statusFilter, searchQuery]);

    const getStatusBadge = (statut: string) => {
        switch (statut) {
            case 'nouveau':
                return (
                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-2xs font-extrabold bg-rose-100 text-rose-800 border border-rose-300 animate-pulse">
                        <span className="h-1.5 w-1.5 rounded-full bg-rose-600"></span>
                        Nouveau
                    </span>
                );
            case 'en_cours':
                return (
                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-2xs font-extrabold bg-amber-100 text-amber-800 border border-amber-300">
                        <span className="h-1.5 w-1.5 rounded-full bg-amber-600"></span>
                        En cours
                    </span>
                );
            case 'traite':
                return (
                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-2xs font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300">
                        <span className="h-1.5 w-1.5 rounded-full bg-emerald-600"></span>
                        Traité
                    </span>
                );
            case 'archive':
                return (
                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-2xs font-medium bg-stone-100 text-stone-600 border border-stone-300">
                        Archivé
                    </span>
                );
            default:
                return null;
        }
    };

    const getPriorityBadge = (p: string) => {
        switch (p) {
            case 'urgente':
                return <span className="text-2xs font-bold px-2 py-0.5 rounded bg-red-100 text-red-700 border border-red-300">🔥 Urgente</span>;
            case 'basse':
                return <span className="text-2xs font-medium px-2 py-0.5 rounded bg-stone-100 text-stone-600">Basse</span>;
            default:
                return <span className="text-2xs font-medium px-2 py-0.5 rounded bg-blue-50 text-blue-700">Normale</span>;
        }
    };

    return (
        <div className="space-y-6">
            {/* Header & Stats Cards */}
            <div>
                <div className="flex items-center justify-between flex-wrap gap-2 mb-4">
                    <div>
                        <h3 className="text-lg font-bold text-[var(--admin-text)]">
                            Suivi & Gestion des Requêtes de Contact
                        </h3>
                        <p className="text-xs text-[var(--admin-text-soft)]">
                            Toutes les prises de contact et demandes d'estimation soumises depuis le formulaire du site web.
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-2 sm:grid-cols-5 gap-3">
                    <button
                        type="button"
                        onClick={() => setStatusFilter('all')}
                        className={cn(
                            "p-4 rounded-2xl border text-left transition",
                            statusFilter === 'all'
                                ? "bg-[#ebb95e]/15 border-[#ebb95e] shadow-sm"
                                : "bg-white/60 border-[var(--admin-border)] hover:bg-white"
                        )}
                    >
                        <p className="text-2xs uppercase tracking-wider text-[var(--admin-text-soft)] font-bold">Total Requêtes</p>
                        <p className="text-2xl font-extrabold text-[var(--admin-text)] mt-1">{stats.total}</p>
                    </button>

                    <button
                        type="button"
                        onClick={() => setStatusFilter('nouveau')}
                        className={cn(
                            "p-4 rounded-2xl border text-left transition",
                            statusFilter === 'nouveau'
                                ? "bg-rose-100/60 border-rose-400 shadow-sm"
                                : "bg-white/60 border-[var(--admin-border)] hover:bg-white"
                        )}
                    >
                        <p className="text-2xs uppercase tracking-wider text-rose-700 font-bold flex items-center justify-between">
                            <span>Nouveaux</span>
                            {stats.nouveau > 0 && <span className="h-2 w-2 rounded-full bg-rose-600 animate-ping"></span>}
                        </p>
                        <p className="text-2xl font-extrabold text-rose-800 mt-1">{stats.nouveau}</p>
                    </button>

                    <button
                        type="button"
                        onClick={() => setStatusFilter('en_cours')}
                        className={cn(
                            "p-4 rounded-2xl border text-left transition",
                            statusFilter === 'en_cours'
                                ? "bg-amber-100/60 border-amber-400 shadow-sm"
                                : "bg-white/60 border-[var(--admin-border)] hover:bg-white"
                        )}
                    >
                        <p className="text-2xs uppercase tracking-wider text-amber-700 font-bold">En cours</p>
                        <p className="text-2xl font-extrabold text-amber-800 mt-1">{stats.en_cours}</p>
                    </button>

                    <button
                        type="button"
                        onClick={() => setStatusFilter('traite')}
                        className={cn(
                            "p-4 rounded-2xl border text-left transition",
                            statusFilter === 'traite'
                                ? "bg-emerald-100/60 border-emerald-400 shadow-sm"
                                : "bg-white/60 border-[var(--admin-border)] hover:bg-white"
                        )}
                    >
                        <p className="text-2xs uppercase tracking-wider text-emerald-700 font-bold">Traités / Résolus</p>
                        <p className="text-2xl font-extrabold text-emerald-800 mt-1">{stats.traite}</p>
                    </button>

                    <button
                        type="button"
                        onClick={() => setStatusFilter('archive')}
                        className={cn(
                            "p-4 rounded-2xl border text-left transition",
                            statusFilter === 'archive'
                                ? "bg-stone-200/60 border-stone-400 shadow-sm"
                                : "bg-white/60 border-[var(--admin-border)] hover:bg-white"
                        )}
                    >
                        <p className="text-2xs uppercase tracking-wider text-stone-600 font-bold">Archivés</p>
                        <p className="text-2xl font-extrabold text-stone-700 mt-1">{stats.archive}</p>
                    </button>
                </div>
            </div>

            {/* Filter / Search Bar */}
            <div className="flex flex-wrap items-center justify-between gap-3 bg-white/70 p-3 rounded-2xl border border-[var(--admin-border)]">
                <div className="flex-1 min-w-[240px]">
                    <input
                        type="text"
                        value={searchQuery}
                        onChange={e => setSearchQuery(e.target.value)}
                        placeholder="Rechercher par nom, email, téléphone, sujet ou mot-clé..."
                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-xs bg-white focus:outline-none focus:border-[#ebb95e]"
                    />
                </div>
                <div className="flex items-center gap-2">
                    <span className="text-2xs font-bold text-[var(--admin-text-soft)] uppercase">Affichage :</span>
                    <select
                        value={statusFilter}
                        onChange={e => setStatusFilter(e.target.value as any)}
                        className="rounded-xl border border-[var(--admin-border)] px-3 py-2 text-xs bg-white focus:outline-none"
                    >
                        <option value="all">Toutes les requêtes ({stats.total})</option>
                        <option value="nouveau">Nouveaux seulement ({stats.nouveau})</option>
                        <option value="en_cours">En cours ({stats.en_cours})</option>
                        <option value="traite">Traités ({stats.traite})</option>
                        <option value="archive">Archivés ({stats.archive})</option>
                    </select>
                </div>
            </div>

            {/* List / Table */}
            <div className="border border-[var(--admin-border)] bg-white/40 rounded-2xl overflow-hidden shadow-sm">
                {filteredMessages.length === 0 ? (
                    <div className="py-12 text-center text-[var(--admin-text-soft)] space-y-2">
                        <p className="text-sm font-semibold">Aucune demande de contact ne correspond aux critères.</p>
                        <p className="text-xs">Les messages soumis depuis le formulaire du site web apparaîtront ici en temps réel.</p>
                    </div>
                ) : (
                    <div className="divide-y divide-[var(--admin-border)]">
                        {filteredMessages.map((msg) => (
                            <div
                                key={msg.id}
                                className={cn(
                                    "p-4 transition hover:bg-white flex flex-col md:flex-row md:items-center justify-between gap-4",
                                    msg.statut === 'nouveau' && "bg-rose-50/40 font-medium"
                                )}
                            >
                                <div className="space-y-1.5 flex-1 min-w-0">
                                    <div className="flex items-center flex-wrap gap-2">
                                        {getStatusBadge(msg.statut)}
                                        {getPriorityBadge(msg.priorite)}
                                        <span className="text-xs text-[var(--admin-text-soft)]">
                                            {new Date(msg.created_at).toLocaleDateString('fr-FR', {
                                                day: '2-digit',
                                                month: 'short',
                                                year: 'numeric',
                                                hour: '2-digit',
                                                minute: '2-digit',
                                            })}
                                        </span>
                                        {msg.artisan && (
                                            <span className="text-2xs font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-900 border border-amber-200">
                                                🎯 Artisan ciblé : {msg.artisan.name}
                                            </span>
                                        )}
                                    </div>

                                    <div className="flex items-baseline gap-2">
                                        <h4 className="text-sm font-bold text-[var(--admin-text)] truncate">{msg.sujet}</h4>
                                    </div>

                                    <p className="text-xs text-[var(--admin-text)] line-clamp-2 bg-white/50 p-2 rounded-xl border border-[var(--admin-border)]/50">
                                        "{msg.message}"
                                    </p>

                                    <div className="flex items-center flex-wrap gap-4 text-xs text-[var(--admin-text-soft)] pt-1">
                                        <span className="font-semibold text-[var(--admin-text)]">👤 {msg.nom}</span>
                                        <a href={`mailto:${msg.email}`} className="text-blue-600 hover:underline flex items-center gap-1">
                                            ✉️ {msg.email}
                                        </a>
                                        {msg.telephone && (
                                            <a href={`tel:${msg.telephone}`} className="text-emerald-700 font-semibold hover:underline flex items-center gap-1">
                                                📞 {msg.telephone}
                                            </a>
                                        )}
                                        {msg.traite_par && (
                                            <span className="text-2xs text-stone-500">
                                                ✓ Traité par {msg.traite_par.name}
                                            </span>
                                        )}
                                    </div>
                                </div>

                                <div className="flex items-center gap-2 shrink-0 self-end md:self-center">
                                    <button
                                        type="button"
                                        onClick={() => openManage(msg)}
                                        className="rounded-xl px-4 py-2 text-xs font-bold bg-[#ebb95e] text-[#241b16] hover:bg-[#e0ab4b] transition shadow-sm"
                                    >
                                        Gérer & Suivre
                                    </button>

                                    {msg.statut !== 'traite' ? (
                                        <button
                                            type="button"
                                            title="Marquer comme traité"
                                            onClick={() => handleQuickStatus(msg, 'traite')}
                                            className="rounded-xl p-2 text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-300 hover:bg-emerald-200 transition"
                                        >
                                            ✓
                                        </button>
                                    ) : (
                                        <button
                                            type="button"
                                            title="Archiver"
                                            onClick={() => handleQuickStatus(msg, 'archive')}
                                            className="rounded-xl p-2 text-xs font-semibold bg-stone-100 text-stone-700 border border-stone-300 hover:bg-stone-200 transition"
                                        >
                                            📁
                                        </button>
                                    )}

                                    <button
                                        type="button"
                                        title="Supprimer"
                                        onClick={() => handleDelete(msg.id)}
                                        className="rounded-xl p-2 text-xs font-semibold bg-red-100 text-red-700 border border-red-300 hover:bg-red-200 transition"
                                    >
                                        🗑️
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Modal de Suivi & Traitement de la Requête */}
            {selectedMessage && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 overflow-y-auto">
                    <div className="bg-white rounded-[32px] border border-[var(--admin-border)] p-6 w-full max-w-2xl shadow-2xl relative my-8">
                        <div className="flex justify-between items-start pb-4 border-b border-[var(--admin-border)]">
                            <div>
                                <span className="text-2xs uppercase tracking-wider font-extrabold text-[#b77918]">
                                    Requête de Contact #{selectedMessage.id}
                                </span>
                                <h4 className="text-lg font-bold text-[var(--admin-text)]">
                                    {selectedMessage.sujet}
                                </h4>
                            </div>
                            <button
                                type="button"
                                onClick={() => setSelectedMessage(null)}
                                className="h-8 w-8 rounded-full bg-stone-100 hover:bg-stone-200 text-stone-700 flex items-center justify-center font-bold"
                            >
                                ✕
                            </button>
                        </div>

                        {/* Coordonnées Expéditeur */}
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 my-4 p-4 bg-stone-50 rounded-2xl border border-stone-200 text-xs">
                            <div>
                                <p className="text-stone-500 font-medium">Expéditeur</p>
                                <p className="font-bold text-stone-900 mt-0.5">{selectedMessage.nom}</p>
                            </div>
                            <div>
                                <p className="text-stone-500 font-medium">Email</p>
                                <a href={`mailto:${selectedMessage.email}`} className="font-semibold text-blue-600 hover:underline block mt-0.5">
                                    {selectedMessage.email}
                                </a>
                            </div>
                            <div>
                                <p className="text-stone-500 font-medium">Téléphone</p>
                                <p className="font-bold text-emerald-800 mt-0.5">
                                    {selectedMessage.telephone ? (
                                        <a href={`tel:${selectedMessage.telephone}`} className="hover:underline">
                                            {selectedMessage.telephone}
                                        </a>
                                    ) : (
                                        <span className="text-stone-400 font-normal">Non renseigné</span>
                                    )}
                                </p>
                            </div>
                            <div className="sm:col-span-3 flex items-center justify-between text-2xs text-stone-500 pt-2 border-t border-stone-200">
                                <span>Reçu le : {new Date(selectedMessage.created_at).toLocaleString('fr-FR')}</span>
                                {selectedMessage.ip_address && <span>IP : {selectedMessage.ip_address}</span>}
                            </div>
                        </div>

                        {/* Artisan ciblé si applicable */}
                        {selectedMessage.artisan && (
                            <div className="mb-4 p-3 bg-amber-50 rounded-2xl border border-amber-200 text-xs flex items-center justify-between">
                                <div>
                                    <p className="text-amber-800 font-bold">🎯 Demande associée à l'artisan :</p>
                                    <p className="text-amber-950 font-semibold">{selectedMessage.artisan.name} ({selectedMessage.artisan.phone})</p>
                                </div>
                            </div>
                        )}

                        {/* Message original */}
                        <div className="mb-6 space-y-1.5">
                            <label className="block text-xs font-bold text-[var(--admin-text)] uppercase tracking-wider">
                                Message de l'utilisateur :
                            </label>
                            <div className="p-4 bg-amber-50/40 rounded-2xl border border-amber-200/80 text-xs text-[#241b16] whitespace-pre-wrap leading-relaxed">
                                {selectedMessage.message}
                            </div>
                        </div>

                        {/* Formulaire de Suivi */}
                        <form onSubmit={handleUpdate} className="space-y-4 pt-4 border-t border-[var(--admin-border)]">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">
                                        Statut de la Requête *
                                    </label>
                                    <select
                                        value={data.statut}
                                        onChange={e => setData('statut', e.target.value as any)}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-xs font-semibold focus:outline-none"
                                    >
                                        <option value="nouveau">🔴 Nouveau (À traiter)</option>
                                        <option value="en_cours">🟡 En cours de traitement</option>
                                        <option value="traite">🟢 Traité / Résolu</option>
                                        <option value="archive">📁 Archivé</option>
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">
                                        Niveau de Priorité
                                    </label>
                                    <select
                                        value={data.priorite}
                                        onChange={e => setData('priorite', e.target.value as any)}
                                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-xs focus:outline-none"
                                    >
                                        <option value="normale">Normale</option>
                                        <option value="urgente">🔥 Urgente</option>
                                        <option value="basse">Basse</option>
                                    </select>
                                </div>
                            </div>

                            {/* Notes internes de suivi */}
                            <div>
                                <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">
                                    Notes internes de suivi & compte-rendu (Visible uniquement par l'équipe admin)
                                </label>
                                <textarea
                                    value={data.notes_admin}
                                    onChange={e => setData('notes_admin', e.target.value)}
                                    placeholder="Ex: Client rappelé par téléphone le 28/08 à 14h. Rendez-vous fixé avec l'artisan Kouamé..."
                                    className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-xs h-20 focus:outline-none"
                                />
                            </div>

                            {/* Réponse envoyée */}
                            <div>
                                <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">
                                    Réponse officielle enregistrée / apportée
                                </label>
                                <textarea
                                    value={data.reponse_envoyee}
                                    onChange={e => setData('reponse_envoyee', e.target.value)}
                                    placeholder="Copie du message ou email de réponse adressé au client..."
                                    className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-xs h-20 focus:outline-none"
                                />
                            </div>

                            {selectedMessage.traite_at && (
                                <div className="p-3 bg-emerald-50 rounded-xl border border-emerald-200 text-2xs text-emerald-800 flex items-center justify-between">
                                    <span>Traité le : {new Date(selectedMessage.traite_at).toLocaleString('fr-FR')}</span>
                                    {selectedMessage.traite_par && <span>Par : {selectedMessage.traite_par.name}</span>}
                                </div>
                            )}

                            <div className="flex justify-between items-center pt-4 border-t border-[var(--admin-border)]">
                                <button
                                    type="button"
                                    onClick={() => handleDelete(selectedMessage.id)}
                                    className="rounded-xl px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50 transition"
                                >
                                    Supprimer la requête
                                </button>

                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setSelectedMessage(null)}
                                        className="rounded-xl px-4 py-2 text-xs font-semibold border border-[var(--admin-border)] text-stone-700 hover:bg-stone-50"
                                    >
                                        Fermer
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="rounded-xl px-5 py-2 text-xs font-bold bg-[#ebb95e] text-[#241b16] hover:bg-[#e0ab4b] shadow-sm disabled:opacity-50"
                                    >
                                        {processing ? 'Enregistrement...' : 'Enregistrer le Suivi'}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
