import { router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import { useConfirm } from '../../shared';

// =============================================================================
// SUB-PANEL 6: RECRUTEMENTS
// =============================================================================
export function RecrutementsSubPanel({ recrutements }: { recrutements: any[] }) {
    const { confirm: askConfirm, dialog: confirmDialog } = useConfirm();
    const [isOpen, setIsOpen] = useState(false);
    const [editingRecrutement, setEditingRecrutement] = useState<any>(null);
    const [currentTime] = useState<number>(() => Date.now());

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

    const handleDelete = async (id: number) => {
        const ok = await askConfirm({
            title: "Supprimer l'offre d'emploi",
            message: "Supprimer cette offre d'emploi ?",
            confirmLabel: 'Supprimer',
            tone: 'danger',
        });
        if (!ok) return;
        router.delete(`/admin/vitrine/recrutements/${id}`, { preserveScroll: true });
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
                        const isExpired = currentTime > 0 && Boolean(job.date_limite && new Date(job.date_limite + 'T23:59:59').getTime() < currentTime);
                        return (
                            <div key={job.id} className="border border-[var(--admin-border)] bg-[var(--admin-panel)] rounded-2xl p-5 flex flex-col md:flex-row justify-between items-start gap-4">
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
                    <div className="bg-[var(--admin-panel-strong)] rounded-[32px] border border-[var(--admin-border)] p-6 w-full max-w-lg shadow-xl relative">
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
            {confirmDialog}
        </div>
    );
}
