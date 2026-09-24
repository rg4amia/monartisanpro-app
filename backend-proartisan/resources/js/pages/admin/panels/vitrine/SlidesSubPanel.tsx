import { router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import { cn } from '@/lib/utils';
import { useConfirm } from '../../shared';
import { sanitizeUploadedFile } from './sanitizeUploadedFile';

// =============================================================================
// SUB-PANEL 1: SLIDES HERO
// =============================================================================
export function SlidesSubPanel({ slides }: { slides: any[] }) {
    const { confirm: askConfirm, dialog: confirmDialog } = useConfirm();
    const [isOpen, setIsOpen] = useState(false);
    const [editingSlide, setEditingSlide] = useState<any>(null);

    const { data, setData, post, reset, errors, processing } = useForm({
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

    const handleDelete = async (id: number) => {
        const ok = await askConfirm({
            title: 'Supprimer le slide',
            message: 'Supprimer définitivement ce slide ?',
            confirmLabel: 'Supprimer',
            tone: 'danger',
        });
        if (!ok) return;
        router.delete(`/admin/vitrine/slides/${id}`, { preserveScroll: true });
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
                        <div key={slide.id} className="border border-[var(--admin-border)] bg-[var(--admin-panel)] rounded-2xl overflow-hidden shadow-sm flex flex-col">
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
                    <div className="bg-[var(--admin-panel-strong)] rounded-[32px] border border-[var(--admin-border)] p-6 w-full max-w-lg shadow-xl relative">
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
            {confirmDialog}
        </div>
    );
}
