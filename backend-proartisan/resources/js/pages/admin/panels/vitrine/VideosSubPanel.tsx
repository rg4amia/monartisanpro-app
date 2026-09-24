import { router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import { useConfirm } from '../../shared';
import { sanitizeUploadedFile } from './sanitizeUploadedFile';

// =============================================================================
// SUB-PANEL 4: CAPSULES VIDÉO
// =============================================================================
export function VideosSubPanel({ videos }: { videos: any[] }) {
    const { confirm: askConfirm, dialog: confirmDialog } = useConfirm();
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

    const handleDelete = async (id: number) => {
        const ok = await askConfirm({
            title: 'Supprimer la vidéo',
            message: 'Supprimer cette vidéo ?',
            confirmLabel: 'Supprimer',
            tone: 'danger',
        });
        if (!ok) return;
        router.delete(`/admin/vitrine/videos/${id}`, { preserveScroll: true });
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
                        <div key={vid.id} className="border border-[var(--admin-border)] bg-[var(--admin-panel)] rounded-2xl overflow-hidden shadow-sm flex flex-col justify-between">
                            <div>
                                <div className="h-40 bg-stone-200 relative">
                                    <img src={vid.thumbnail_url || '/img/default-video.png'} alt={vid.titre} className="w-full h-full object-cover" />
                                    <div className="absolute inset-0 bg-black/30 flex items-center justify-center">
                                        <span className="h-12 w-12 rounded-full bg-[var(--admin-panel-strong)] flex items-center justify-center text-[#b77918] shadow-md font-bold text-xl">▶</span>
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
                    <div className="bg-[var(--admin-panel-strong)] rounded-[32px] border border-[var(--admin-border)] p-6 w-full max-w-lg shadow-xl relative">
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
            {confirmDialog}
        </div>
    );
}
