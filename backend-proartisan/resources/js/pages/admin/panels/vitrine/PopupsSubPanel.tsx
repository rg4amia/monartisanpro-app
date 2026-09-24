import { router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import { cn } from '@/lib/utils';
import { useConfirm } from '../../shared';
import { sanitizeUploadedFile } from './sanitizeUploadedFile';

// =============================================================================
// SUB-PANEL 7: POPUPS PROMOTIONNELS
// =============================================================================
export function PopupsSubPanel({ popups }: { popups: any[] }) {
    const { confirm: askConfirm, dialog: confirmDialog } = useConfirm();
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

    const handleDelete = async (id: number) => {
        const ok = await askConfirm({
            title: 'Supprimer le popup',
            message: 'Supprimer ce popup ?',
            confirmLabel: 'Supprimer',
            tone: 'danger',
        });
        if (!ok) return;
        router.delete(`/admin/vitrine/popups/${id}`, { preserveScroll: true });
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
                            <div key={pop.id} className="border border-[var(--admin-border)] bg-[var(--admin-panel)] rounded-2xl overflow-hidden shadow-sm flex flex-col justify-between">
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
                    <div className="bg-[var(--admin-panel-strong)] rounded-[32px] border border-[var(--admin-border)] p-6 w-full max-w-lg shadow-xl relative">
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
            {confirmDialog}
        </div>
    );
}
