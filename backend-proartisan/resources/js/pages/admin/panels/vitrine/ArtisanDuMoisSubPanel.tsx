import { router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import { useConfirm } from '../../shared';
import { sanitizeUploadedFile } from './sanitizeUploadedFile';

// =============================================================================
// SUB-PANEL 2: ARTISAN DU MOIS
// =============================================================================
export function ArtisanDuMoisSubPanel({ admList, artisans }: { admList: any[]; artisans: any[] }) {
    const { confirm: askConfirm, dialog: confirmDialog } = useConfirm();
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

    const handleDelete = async (id: number) => {
        const ok = await askConfirm({
            title: "Retirer la mise en avant",
            message: "Supprimer cette mise en avant d'artisan ?",
            confirmLabel: 'Supprimer',
            tone: 'danger',
        });
        if (!ok) return;
        router.delete(`/admin/vitrine/artisan-du-mois/${id}`, { preserveScroll: true });
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
                            <div key={adm.id} className="border border-[var(--admin-border)] bg-[var(--admin-panel)] rounded-2xl p-5 flex flex-col md:flex-row gap-5 items-start">
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
                    <div className="bg-[var(--admin-panel-strong)] rounded-[32px] border border-[var(--admin-border)] p-6 w-full max-w-lg shadow-xl relative">
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
            {confirmDialog}
        </div>
    );
}
