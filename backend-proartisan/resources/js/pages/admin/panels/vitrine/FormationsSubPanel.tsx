import { router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import { cn } from '@/lib/utils';
import { useConfirm } from '../../shared';
import { sanitizeUploadedFile } from './sanitizeUploadedFile';

// =============================================================================
// SUB-PANEL 5: SESSIONS FORMATION
// =============================================================================
export function FormationsSubPanel({ formations, money }: { formations: any[]; money: (v: number) => string }) {
    const { confirm: askConfirm, dialog: confirmDialog } = useConfirm();
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

    const handleDelete = async (id: number) => {
        const ok = await askConfirm({
            title: 'Supprimer la formation',
            message: 'Supprimer cette formation ?',
            confirmLabel: 'Supprimer',
            tone: 'danger',
        });
        if (!ok) return;
        router.delete(`/admin/vitrine/formations/${id}`, { preserveScroll: true });
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
                        <div key={form.id} className="border border-[var(--admin-border)] bg-[var(--admin-panel)] rounded-2xl overflow-hidden shadow-sm flex flex-col justify-between">
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
                    <div className="bg-[var(--admin-panel-strong)] rounded-[32px] border border-[var(--admin-border)] p-6 w-full max-w-xl shadow-xl relative">
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
            {confirmDialog}
        </div>
    );
}
