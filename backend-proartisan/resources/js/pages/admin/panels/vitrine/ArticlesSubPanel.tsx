import { router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import { cn } from '@/lib/utils';
import { useConfirm } from '../../shared';
import { sanitizeUploadedFile } from './sanitizeUploadedFile';

// =============================================================================
// SUB-PANEL 3: ARTICLES & ACTUALITÉS
// =============================================================================
export function ArticlesSubPanel({ articles }: { articles: any[] }) {
    const { confirm: askConfirm, dialog: confirmDialog } = useConfirm();
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

    const handleDelete = async (id: number) => {
        const ok = await askConfirm({
            title: "Supprimer l'article",
            message: 'Supprimer cet article ?',
            confirmLabel: 'Supprimer',
            tone: 'danger',
        });
        if (!ok) return;
        router.delete(`/admin/vitrine/articles/${id}`, { preserveScroll: true });
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
                        <div key={art.id} className="border border-[var(--admin-border)] bg-[var(--admin-panel)] rounded-2xl overflow-hidden shadow-sm flex flex-col justify-between">
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
                    <div className="bg-[var(--admin-panel-strong)] rounded-[32px] border border-[var(--admin-border)] p-6 w-full max-w-2xl shadow-xl relative">
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
            {confirmDialog}
        </div>
    );
}
