import React, { useState, useMemo } from 'react';
import { useForm, router } from '@inertiajs/react';
import { cn } from '@/lib/utils';

interface VitrinePanelProps {
    vitrineSlides: any[];
    vitrineArtisanDuMois: any[];
    vitrineArticles: any[];
    vitrineVideos: any[];
    vitrineFormations: any[];
    vitrineRecrutements: any[];
    vitrinePopups: any[];
    vitrineSettings: any[];
    users: any[];
}

type SubTab = 'slides' | 'artisan_du_mois' | 'articles' | 'videos' | 'formations' | 'recrutements' | 'popups' | 'settings';

export default function VitrinePanel({
    vitrineSlides = [],
    vitrineArtisanDuMois = [],
    vitrineArticles = [],
    vitrineVideos = [],
    vitrineFormations = [],
    vitrineRecrutements = [],
    vitrinePopups = [],
    vitrineSettings = [],
    users = [],
}: VitrinePanelProps) {
    const [activeSubTab, setActiveSubTab] = useState<SubTab>('slides');

    // Filter artisans to pick for "Artisan du Mois"
    const artisans = useMemo(() => {
        return users.filter(u => u.role === 'artisan' && u.kyc_status === 'actif');
    }, [users]);

    // Format money helper
    const money = (amount: number) => {
        return new Intl.NumberFormat('fr-FR').format(amount) + ' FCFA';
    };

    return (
        <div className="space-y-6">
            {/* Header info */}
            <div className="flex flex-col gap-2">
                <h2 className="text-2xl font-bold text-[var(--admin-text)]">Gestion de la Vitrine Publique</h2>
                <p className="text-sm text-[var(--admin-text-soft)]">
                    Pilotez le contenu éditorial, les slides, les formations, les offres d'emploi et les actualités du site web ProsArtisan.
                </p>
            </div>

            {/* Sub Tabs */}
            <div className="flex flex-wrap gap-2 border-b border-[var(--admin-border)] pb-px">
                {(
                    [
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
                            onClick={() => setActiveSubTab(tab.id)}
                            className={cn(
                                "border-b-2 px-4 py-3 text-sm font-semibold transition-colors focus:outline-none",
                                isActive
                                    ? "border-[#ebb95e] text-[#b77918]"
                                    : "border-transparent text-[var(--admin-text-soft)] hover:text-[var(--admin-text)]"
                            )}
                        >
                            {tab.label}
                        </button>
                    );
                })}
            </div>

            {/* Sub Tab Panel rendering */}
            <div className="bg-white/50 border border-[var(--admin-border)] rounded-[32px] p-6 shadow-sm">
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
        if (editingSlide) {
            // Laravel requires _method=PUT to handle multipart files in PUT requests, or we can use POST with _method=PUT
            post(`/admin/vitrine/slides/${editingSlide.id}`, {
                data: {
                    ...data,
                    _method: 'PUT'
                },
                preserveScroll: true,
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                }
            });
        } else {
            post('/admin/vitrine/slides', {
                preserveScroll: true,
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                }
            });
        }
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
                                        onChange={e => setData('image', e.target.files ? e.target.files[0] : null)}
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
                                    Sauvegarder
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
            preserveScroll: true,
            onSuccess: () => {
                setIsOpen(false);
                reset();
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
                                        onChange={e => setData('photo', e.target.files ? e.target.files[0] : null)}
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
                                    Sauvegarder
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
        if (editingArticle) {
            post(`/admin/vitrine/articles/${editingArticle.id}`, {
                data: {
                    ...data,
                    _method: 'PUT'
                },
                preserveScroll: true,
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                }
            });
        } else {
            post('/admin/vitrine/articles', {
                preserveScroll: true,
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                }
            });
        }
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
                                        onChange={e => setData('image', e.target.files ? e.target.files[0] : null)}
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
                                    Sauvegarder
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
        if (editingVideo) {
            post(`/admin/vitrine/videos/${editingVideo.id}`, {
                data: {
                    ...data,
                    _method: 'PUT'
                },
                preserveScroll: true,
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                }
            });
        } else {
            post('/admin/vitrine/videos', {
                preserveScroll: true,
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                }
            });
        }
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
                            <div>
                                <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Titre de la vidéo *</label>
                                <input
                                    type="text"
                                    value={data.titre}
                                    onChange={e => setData('titre', e.target.value)}
                                    className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    required
                                />
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
                                <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Lien de la Vidéo (YouTube Embed ou URL brute) *</label>
                                <input
                                    type="text"
                                    value={data.video_url}
                                    onChange={e => setData('video_url', e.target.value)}
                                    className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    placeholder="Ex: https://www.youtube.com/watch?v=..."
                                    required
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Image miniature (Thumbnail)</label>
                                    <input
                                        type="file"
                                        onChange={e => setData('thumbnail', e.target.files ? e.target.files[0] : null)}
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
                                    Sauvegarder
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
        if (editingFormation) {
            post(`/admin/vitrine/formations/${editingFormation.id}`, {
                data: {
                    ...data,
                    _method: 'PUT'
                },
                preserveScroll: true,
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                }
            });
        } else {
            post('/admin/vitrine/formations', {
                preserveScroll: true,
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                }
            });
        }
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
                            <div>
                                <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Titre de la formation *</label>
                                <input
                                    type="text"
                                    value={data.titre}
                                    onChange={e => setData('titre', e.target.value)}
                                    className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    required
                                />
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
                                        onChange={e => setData('image', e.target.files ? e.target.files[0] : null)}
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
                                    Sauvegarder
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
        if (editingRecrutement) {
            post(`/admin/vitrine/recrutements/${editingRecrutement.id}`, {
                data: {
                    ...data,
                    _method: 'PUT'
                },
                preserveScroll: true,
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                }
            });
        } else {
            post('/admin/vitrine/recrutements', {
                preserveScroll: true,
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                }
            });
        }
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
                    recrutements.map((job) => (
                        <div key={job.id} className="border border-[var(--admin-border)] bg-white/40 rounded-2xl p-5 flex flex-col md:flex-row justify-between items-start gap-4">
                            <div className="space-y-2">
                                <div className="flex items-center gap-3">
                                    <h4 className="text-lg font-bold text-[var(--admin-text)]">{job.titre}</h4>
                                    <span className="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-bold uppercase border border-blue-300">
                                        {job.type_contrat.toUpperCase()}
                                    </span>
                                    {!job.actif && (
                                        <span className="bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs font-bold border border-red-300">
                                            Masqué
                                        </span>
                                    )}
                                </div>
                                <p className="text-xs text-[var(--admin-muted)]">
                                    Métier ciblé : <b>{job.metier}</b> • Lieu : {job.lieu} • Limite : {job.date_limite ? new Date(job.date_limite).toLocaleDateString('fr-FR') : 'Aucune'}
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
                    ))
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
                            <div>
                                <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Titre du poste *</label>
                                <input
                                    type="text"
                                    value={data.titre}
                                    onChange={e => setData('titre', e.target.value)}
                                    className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    required
                                />
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
                                    Sauvegarder
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
        if (editingPopup) {
            post(`/admin/vitrine/popups/${editingPopup.id}`, {
                data: {
                    ...data,
                    _method: 'PUT'
                },
                preserveScroll: true,
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                }
            });
        } else {
            post('/admin/vitrine/popups', {
                preserveScroll: true,
                onSuccess: () => {
                    setIsOpen(false);
                    reset();
                }
            });
        }
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
                            <div>
                                <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Titre *</label>
                                <input
                                    type="text"
                                    value={data.titre}
                                    onChange={e => setData('titre', e.target.value)}
                                    className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                                    required
                                />
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
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-[var(--admin-text)] mb-1">Image promotionnelle</label>
                                    <input
                                        type="file"
                                        onChange={e => setData('image', e.target.files ? e.target.files[0] : null)}
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
                                    Sauvegarder
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
            lien_facebook: '',
            lien_instagram: '',
            lien_linkedin: '',
            contact_phone: '+225 07 00 00 00 00',
            contact_email: 'contact@prosartisan.ci',
            presentation_mission: 'ProsArtisan connecte les particuliers aux meilleurs artisans qualifiés et quincailleries de Côte d’Ivoire...',
        };
        settings.forEach((s) => {
            map[s.cle] = s.valeur;
        });
        return map;
    }, [settings]);

    const { data, setData, post, processing } = useForm({
        chiffres_cles_artisans: settingsMap.chiffres_cles_artisans,
        chiffres_cles_utilisateurs: settingsMap.chiffres_cles_utilisateurs,
        chiffres_cles_missions: settingsMap.chiffres_cles_missions,
        chiffres_cles_metiers: settingsMap.chiffres_cles_metiers,
        lien_facebook: settingsMap.lien_facebook,
        lien_instagram: settingsMap.lien_instagram,
        lien_linkedin: settingsMap.lien_linkedin,
        contact_phone: settingsMap.contact_phone,
        contact_email: settingsMap.contact_email,
        presentation_mission: settingsMap.presentation_mission,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/vitrine/settings', { preserveScroll: true });
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <h3 className="text-lg font-bold text-[var(--admin-text)] border-b pb-2">Paramètres de la Vitrine</h3>

            {/* Chiffres Cles */}
            <div className="space-y-4">
                <h4 className="text-sm font-bold text-[#b77918] uppercase tracking-wider">Chiffres Clés (Hero Page d'accueil)</h4>
                <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-4">
                    <div>
                        <label className="block text-xs font-medium text-[var(--admin-text)] mb-1">Nombre d'artisans</label>
                        <input
                            type="text"
                            value={data.chiffres_cles_artisans}
                            onChange={e => setData('chiffres_cles_artisans', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm font-semibold"
                            placeholder="Ex: 800"
                            required
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-[var(--admin-text)] mb-1">Nombre d'utilisateurs</label>
                        <input
                            type="text"
                            value={data.chiffres_cles_utilisateurs}
                            onChange={e => setData('chiffres_cles_utilisateurs', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm font-semibold"
                            placeholder="Ex: 3 000"
                            required
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-[var(--admin-text)] mb-1">Missions complétées</label>
                        <input
                            type="text"
                            value={data.chiffres_cles_missions}
                            onChange={e => setData('chiffres_cles_missions', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm font-semibold"
                            placeholder="Ex: 5 000"
                            required
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-[var(--admin-text)] mb-1">Métiers répertoriés</label>
                        <input
                            type="text"
                            value={data.chiffres_cles_metiers}
                            onChange={e => setData('chiffres_cles_metiers', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm font-semibold"
                            placeholder="Ex: 29"
                            required
                        />
                    </div>
                </div>
            </div>

            {/* Reseaux & Contact */}
            <div className="space-y-4 pt-4 border-t border-[var(--admin-border)]">
                <h4 className="text-sm font-bold text-[#b77918] uppercase tracking-wider">Contact & Réseaux Sociaux</h4>
                <div className="grid gap-4 md:grid-cols-2">
                    <div>
                        <label className="block text-xs font-medium text-[var(--admin-text)] mb-1">Téléphone de contact</label>
                        <input
                            type="text"
                            value={data.contact_phone}
                            onChange={e => setData('contact_phone', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                            placeholder="+225 07 ..."
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-[var(--admin-text)] mb-1">Email de contact officiel</label>
                        <input
                            type="email"
                            value={data.contact_email}
                            onChange={e => setData('contact_email', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                            placeholder="contact@prosartisan.ci"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-[var(--admin-text)] mb-1">Lien Facebook</label>
                        <input
                            type="text"
                            value={data.lien_facebook}
                            onChange={e => setData('lien_facebook', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                            placeholder="https://facebook.com/..."
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-[var(--admin-text)] mb-1">Lien Instagram</label>
                        <input
                            type="text"
                            value={data.lien_instagram}
                            onChange={e => setData('lien_instagram', e.target.value)}
                            className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm"
                            placeholder="https://instagram.com/..."
                        />
                    </div>
                </div>
            </div>

            {/* Mission Presentation */}
            <div className="space-y-4 pt-4 border-t border-[var(--admin-border)]">
                <h4 className="text-sm font-bold text-[#b77918] uppercase tracking-wider">Mission & Présentation de la Plateforme</h4>
                <div>
                    <label className="block text-xs font-medium text-[var(--admin-text)] mb-1">Texte de Présentation (Page d'accueil & Accueil menu)</label>
                    <textarea
                        value={data.presentation_mission}
                        onChange={e => setData('presentation_mission', e.target.value)}
                        className="w-full rounded-xl border border-[var(--admin-border)] px-3 py-2 text-sm h-32"
                        required
                    />
                </div>
            </div>

            <div className="flex justify-end pt-4 border-t border-[var(--admin-border)]">
                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-2xl px-6 py-2.5 text-sm font-semibold transition bg-[#ebb95e] text-[#241b16] hover:bg-[#e0ab4b] disabled:opacity-50"
                >
                    Enregistrer les Paramètres
                </button>
            </div>
        </form>
    );
}
