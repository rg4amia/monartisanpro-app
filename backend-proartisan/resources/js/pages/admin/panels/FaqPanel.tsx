// Onglet « FAQ Aide & Support » du backoffice — questions/réponses affichées
// dans l'écran Aide et support de l'app mobile, ventilées par rôle.

import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import { EmptyState, PlusIcon, RoleBadge, Surface, useConfirm } from '../shared';
import type { FaqItem, FaqRole } from '../shared';

const ROLES: { value: FaqRole; label: string }[] = [
    { value: 'client', label: 'Client' },
    { value: 'artisan', label: 'Artisan' },
    { value: 'livreur', label: 'Livreur' },
    { value: 'fournisseur', label: 'Fournisseur' },
];

interface FaqFormData {
    question: string;
    reponse: string;
    categorie: string;
    roles: FaqRole[];
    ordre: string;
    actif: boolean;
}

const emptyForm: FaqFormData = {
    question: '',
    reponse: '',
    categorie: '',
    roles: [],
    ordre: '0',
    actif: true,
};

function RolesCheckboxes({
    value,
    onChange,
    disabled,
}: {
    value: FaqRole[];
    onChange: (roles: FaqRole[]) => void;
    disabled?: boolean;
}) {
    const toggle = (role: FaqRole) => {
        onChange(value.includes(role) ? value.filter((r) => r !== role) : [...value, role]);
    };

    return (
        <div className="flex flex-wrap gap-2">
            {ROLES.map((r) => (
                <label
                    key={r.value}
                    className="flex items-center gap-1.5 rounded-full border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-1.5 text-xs font-semibold text-[var(--admin-text)] cursor-pointer has-[:checked]:border-[#ebb95e] has-[:checked]:bg-[#ebb95e]/15"
                >
                    <input
                        type="checkbox"
                        checked={value.includes(r.value)}
                        onChange={() => toggle(r.value)}
                        disabled={disabled}
                        className="h-3.5 w-3.5"
                    />
                    {r.label}
                </label>
            ))}
        </div>
    );
}

function FaqRow({ faq, canManage, onDelete }: { faq: FaqItem; canManage: boolean; onDelete: (faq: FaqItem) => void }) {
    const [isEditing, setIsEditing] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm<FaqFormData>({
        question: faq.question,
        reponse: faq.reponse,
        categorie: faq.categorie ?? '',
        roles: faq.roles,
        ordre: String(faq.ordre),
        actif: faq.actif,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(`/admin/faq/${faq.id}`, {
            preserveScroll: true,
            onSuccess: () => setIsEditing(false),
        });
    };

    if (isEditing) {
        return (
            <form onSubmit={handleSubmit} className="rounded-2xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] p-4 space-y-3">
                {Object.keys(errors).length > 0 && (
                    <div className="p-2.5 bg-red-100 border border-red-300 text-red-700 rounded-xl text-xs space-y-1">
                        {Object.values(errors).map((err, i) => (
                            <p key={i}>{err}</p>
                        ))}
                    </div>
                )}
                <input
                    type="text"
                    value={data.question}
                    onChange={(e) => setData('question', e.target.value)}
                    placeholder="Question"
                    className="w-full rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-2 text-sm font-semibold text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                />
                <textarea
                    rows={3}
                    value={data.reponse}
                    onChange={(e) => setData('reponse', e.target.value)}
                    placeholder="Réponse"
                    className="w-full rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                />
                <div className="grid gap-3 sm:grid-cols-2">
                    <input
                        type="text"
                        value={data.categorie}
                        onChange={(e) => setData('categorie', e.target.value)}
                        placeholder="Catégorie (ex: Paiement, J-Code...)"
                        className="w-full rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                    />
                    <input
                        type="number"
                        min={0}
                        value={data.ordre}
                        onChange={(e) => setData('ordre', e.target.value)}
                        placeholder="Ordre d'affichage"
                        className="w-full rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                    />
                </div>
                <RolesCheckboxes value={data.roles} onChange={(roles) => setData('roles', roles)} />
                <div className="flex items-center justify-between pt-1">
                    <label className="flex items-center gap-2 text-xs font-semibold text-[var(--admin-text)]">
                        <input type="checkbox" checked={data.actif} onChange={(e) => setData('actif', e.target.checked)} className="h-3.5 w-3.5" />
                        Visible dans l'app
                    </label>
                    <div className="flex gap-2">
                        <button
                            type="button"
                            onClick={() => {
                                reset();
                                setIsEditing(false);
                            }}
                            className="rounded-lg px-3 py-1.5 text-xs font-semibold border border-[var(--admin-border)] text-[var(--admin-text-soft)] hover:bg-[var(--admin-panel)]"
                        >
                            Annuler
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-lg px-3 py-1.5 text-xs font-bold bg-[#ebb95e] text-[#241b16] hover:bg-[#e0ab4b] disabled:opacity-50"
                        >
                            {processing ? 'Enregistrement...' : 'Enregistrer'}
                        </button>
                    </div>
                </div>
            </form>
        );
    }

    return (
        <div className="rounded-2xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] p-4">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <h4 className="text-sm font-bold text-[var(--admin-text)]">{faq.question}</h4>
                        {!faq.actif && (
                            <span className="rounded-full border border-[var(--admin-border)] bg-black/5 px-2 py-0.5 text-[10px] font-bold uppercase text-[var(--admin-muted)]">
                                Masquée
                            </span>
                        )}
                    </div>
                    <p className="mt-1.5 text-xs text-[var(--admin-text-soft)] whitespace-pre-line">{faq.reponse}</p>
                    <div className="mt-2.5 flex flex-wrap items-center gap-1.5">
                        {faq.categorie && (
                            <span className="rounded-full border border-[var(--admin-border)] px-2.5 py-0.5 text-[10px] font-bold uppercase text-[var(--admin-muted)]">
                                {faq.categorie}
                            </span>
                        )}
                        {faq.roles.map((r) => (
                            <RoleBadge key={r} role={r} />
                        ))}
                    </div>
                </div>
                {canManage && (
                    <div className="flex shrink-0 gap-2">
                        <button
                            type="button"
                            onClick={() => setIsEditing(true)}
                            className="rounded-lg px-2.5 py-1 text-xs font-semibold bg-black/5 hover:bg-black/10 text-[var(--admin-text)] transition"
                        >
                            Modifier
                        </button>
                        <button
                            type="button"
                            onClick={() => onDelete(faq)}
                            className="rounded-lg px-2.5 py-1 text-xs font-semibold bg-red-500/10 hover:bg-red-500/20 text-red-600 transition"
                        >
                            Supprimer
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}

interface FaqPanelProps {
    faqs: FaqItem[];
    canManage: boolean;
}

export function FaqPanel({ faqs, canManage }: FaqPanelProps) {
    const [showCreateForm, setShowCreateForm] = useState(false);
    const { confirm, dialog } = useConfirm();
    const createForm = useForm<FaqFormData>(emptyForm);

    const handleCreate = (e: FormEvent) => {
        e.preventDefault();
        createForm.post('/admin/faq', {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset();
                setShowCreateForm(false);
            },
        });
    };

    const handleDelete = async (faq: FaqItem) => {
        const ok = await confirm({
            title: `Supprimer cette question ?`,
            message: faq.question,
            tone: 'danger',
            confirmLabel: 'Supprimer',
        });
        if (!ok) return;
        router.delete(`/admin/faq/${faq.id}`, { preserveScroll: true });
    };

    return (
        <section className="mt-5 space-y-5">
            {dialog}

            <Surface className="rounded-[32px] p-5 lg:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-[var(--admin-border)] pb-5">
                    <div>
                        <h3 className="text-xl font-bold text-[var(--admin-text)] flex items-center gap-2">
                            <span>❓ FAQ Aide & Support</span>
                            <span className="rounded-full bg-[#ebb95e]/20 text-[#8a5d16] text-xs font-bold px-2.5 py-0.5">{faqs.length} question(s)</span>
                        </h3>
                        <p className="text-xs text-[var(--admin-text-soft)] mt-1">
                            Ces questions/réponses alimentent l'écran « Aide et support » des 4 espaces mobile (client, artisan, livreur, fournisseur).
                        </p>
                    </div>
                    {canManage && (
                        <button
                            type="button"
                            onClick={() => setShowCreateForm((v) => !v)}
                            className="inline-flex items-center gap-2 rounded-full bg-[#ebb95e] text-[#241b16] px-5 py-2.5 text-xs font-bold hover:opacity-90 transition shadow-sm"
                        >
                            <PlusIcon className="h-4 w-4" />
                            Nouvelle Question
                        </button>
                    )}
                </div>

                {showCreateForm && canManage && (
                    <form onSubmit={handleCreate} className="mt-5 space-y-3 rounded-2xl border border-dashed border-[var(--admin-border)] p-4">
                        {Object.keys(createForm.errors).length > 0 && (
                            <div className="p-2.5 bg-red-100 border border-red-300 text-red-700 rounded-xl text-xs space-y-1">
                                {Object.values(createForm.errors).map((err, i) => (
                                    <p key={i}>{err}</p>
                                ))}
                            </div>
                        )}
                        <input
                            type="text"
                            value={createForm.data.question}
                            onChange={(e) => createForm.setData('question', e.target.value)}
                            placeholder="Question"
                            className="w-full rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-2 text-sm font-semibold text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                        />
                        <textarea
                            rows={3}
                            value={createForm.data.reponse}
                            onChange={(e) => createForm.setData('reponse', e.target.value)}
                            placeholder="Réponse"
                            className="w-full rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                        />
                        <div className="grid gap-3 sm:grid-cols-2">
                            <input
                                type="text"
                                value={createForm.data.categorie}
                                onChange={(e) => createForm.setData('categorie', e.target.value)}
                                placeholder="Catégorie (ex: Paiement, J-Code...)"
                                className="w-full rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                            />
                            <input
                                type="number"
                                min={0}
                                value={createForm.data.ordre}
                                onChange={(e) => createForm.setData('ordre', e.target.value)}
                                placeholder="Ordre d'affichage"
                                className="w-full rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-2 text-xs text-[var(--admin-text)] focus:border-amber-500 focus:outline-none"
                            />
                        </div>
                        <RolesCheckboxes value={createForm.data.roles} onChange={(roles) => createForm.setData('roles', roles)} />
                        <div className="flex items-center justify-between pt-1">
                            <label className="flex items-center gap-2 text-xs font-semibold text-[var(--admin-text)]">
                                <input
                                    type="checkbox"
                                    checked={createForm.data.actif}
                                    onChange={(e) => createForm.setData('actif', e.target.checked)}
                                    className="h-3.5 w-3.5"
                                />
                                Visible dans l'app
                            </label>
                            <button
                                type="submit"
                                disabled={createForm.processing}
                                className="rounded-lg px-4 py-2 text-xs font-bold bg-[#ebb95e] text-[#241b16] hover:bg-[#e0ab4b] disabled:opacity-50"
                            >
                                {createForm.processing ? 'Création...' : 'Ajouter la question'}
                            </button>
                        </div>
                    </form>
                )}

                <div className="mt-5 space-y-3">
                    {faqs.length === 0 ? (
                        <EmptyState
                            title="Aucune question pour l'instant"
                            description="Ajoutez des questions/réponses pour alimenter l'écran Aide et support de l'application mobile."
                        />
                    ) : (
                        faqs.map((faq) => <FaqRow key={faq.id} faq={faq} canManage={canManage} onDelete={handleDelete} />)
                    )}
                </div>
            </Surface>
        </section>
    );
}
