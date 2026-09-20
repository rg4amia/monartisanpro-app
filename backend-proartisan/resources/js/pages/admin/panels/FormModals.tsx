// Modales-formulaires du backoffice — extraites de console.tsx (Chantier C2).
// Chaque modale reçoit l'instance `useForm` d'Inertia du parent (données + setData + errors + processing).

import type { FormEvent } from 'react';

import { CloseIcon, KycStatusBadge } from '../shared';
import type { AdminUser, CampagneParrainageItem, PromoCodeItem, SectorItem } from '../shared';

const documentLabels: Record<'cni' | 'selfie', string> = {
    cni: 'Carte Nationale d’Identité (CNI)',
    selfie: 'Selfie de vérification',
};

// L'instance useForm d'Inertia n'expose pas de type nommé stable ; on garde `any` à cette frontière.
type InertiaForm = any;

function CloseButton({ onClose }: { onClose: () => void }) {
    return (
        <button
            type="button"
            onClick={onClose}
            className="rounded-full p-2 text-[var(--admin-muted)] hover:bg-white/10 hover:text-[var(--admin-text)] transition"
            title="Fermer"
            aria-label="Fermer"
        >
            <svg className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                <path d="M6 18L18 6M6 6l12 12" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
        </button>
    );
}

export function CommunicationFormModal({
    form,
    editing,
    adminName,
    audioUploadLimit,
    onSubmit,
    onClose,
}: {
    form: InertiaForm;
    editing: unknown;
    adminName: string;
    /** Plafond reellement applicable, borne par la configuration PHP du serveur. */
    audioUploadLimit?: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    onClose: () => void;
}) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div className="admin-panel admin-surface w-full max-w-[550px] rounded-[32px] border p-6 lg:p-8 shadow-2xl relative">
                <div className="flex items-center justify-between border-b border-[var(--admin-border)] pb-4">
                    <h2 className="text-xl font-bold text-[var(--admin-text)]">
                        {editing ? 'Modifier la publication' : 'Créer une publication'}
                    </h2>
                    <CloseButton onClose={onClose} />
                </div>

                <form onSubmit={onSubmit} className="mt-6 space-y-4">
                    <label className="block space-y-1">
                        <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Type de communication</span>
                        <select
                            value={form.data.type}
                            onChange={(e) => form.setData('type', e.target.value)}
                            className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                            required
                        >
                            <option value="annonce">Communication interne</option>
                            <option value="le_saviez_vous">Le saviez-vous ?</option>
                            <option value="audio">Message vocal</option>
                            <option value="video">Vidéo</option>
                        </select>
                    </label>

                    {form.data.type === 'audio' && (
                        <label className="block space-y-1">
                            <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">
                                Fichier audio {editing ? '(laisser vide pour conserver l’actuel)' : ''}
                            </span>
                            <input
                                type="file"
                                accept="audio/mpeg,audio/mp4,audio/aac,audio/ogg,audio/wav,.mp3,.m4a,.aac,.ogg,.wav"
                                onChange={(e) => form.setData('media_file', e.target.files?.[0] ?? null)}
                                className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                            />
                            <span className="block text-[11px] text-[var(--admin-muted)]">
                                MP3, M4A, AAC, OGG ou WAV — {audioUploadLimit ?? '10 Mo'} maximum. Les destinataires voient le poids avant d’écouter.
                            </span>
                            {form.errors.media_file && (
                                <span className="block text-[11px] text-red-500">{form.errors.media_file}</span>
                            )}
                        </label>
                    )}

                    {form.data.type === 'video' && (
                        <label className="block space-y-1">
                            <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Lien de la vidéo</span>
                            <input
                                type="url"
                                value={form.data.media_external_url}
                                onChange={(e) => form.setData('media_external_url', e.target.value)}
                                placeholder="https://www.youtube.com/watch?v=..."
                                className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                            />
                            <span className="block text-[11px] text-[var(--admin-muted)]">
                                Le lien doit être en HTTPS : l’application refuse le trafic non chiffré et la vidéo ne se chargerait pas.
                            </span>
                            {form.errors.media_external_url && (
                                <span className="block text-[11px] text-red-500">{form.errors.media_external_url}</span>
                            )}
                        </label>
                    )}

                    {(form.data.type === 'audio' || form.data.type === 'video') && (
                        <label className="block space-y-1">
                            <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Durée en secondes (facultatif)</span>
                            <input
                                type="number"
                                min={1}
                                value={form.data.media_duration}
                                onChange={(e) => form.setData('media_duration', e.target.value)}
                                placeholder="90"
                                className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                            />
                            <span className="block text-[11px] text-[var(--admin-muted)]">
                                Annoncée avant lecture, pour que l’utilisateur sache ce qu’il engage.
                            </span>
                        </label>
                    )}

                    <label className="block space-y-1">
                        <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Intitulé / Titre</span>
                        <input
                            type="text"
                            value={form.data.titre}
                            onChange={(e) => form.setData('titre', e.target.value)}
                            className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                            required
                        />
                    </label>

                    <label className="block space-y-1">
                        <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Description / Contenu</span>
                        <textarea
                            value={form.data.contenu}
                            onChange={(e) => form.setData('contenu', e.target.value)}
                            className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none h-24 resize-none"
                            required
                        />
                    </label>

                    <div className="space-y-1">
                        <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Espaces cibles</span>
                        <div className="grid grid-cols-2 gap-2 mt-2">
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={form.data.cibles.includes('client') && form.data.cibles.includes('artisan') && form.data.cibles.includes('livreur') && form.data.cibles.includes('fournisseur')}
                                    onChange={(e) => {
                                        if (e.target.checked) {
                                            form.setData('cibles', ['client', 'artisan', 'livreur', 'fournisseur']);
                                        } else {
                                            form.setData('cibles', []);
                                        }
                                    }}
                                    className="rounded border-[var(--admin-border)]"
                                />
                                <span className="text-sm text-[var(--admin-text)]">TOUS</span>
                            </label>
                            {['client', 'artisan', 'livreur', 'fournisseur'].map((role) => (
                                <label key={role} className="flex items-center gap-2 cursor-pointer capitalize">
                                    <input
                                        type="checkbox"
                                        checked={form.data.cibles.includes(role)}
                                        onChange={(e) => {
                                            if (e.target.checked) {
                                                form.setData('cibles', [...form.data.cibles, role]);
                                            } else {
                                                form.setData('cibles', form.data.cibles.filter((r: string) => r !== role));
                                            }
                                        }}
                                        className="rounded border-[var(--admin-border)]"
                                    />
                                    <span className="text-sm text-[var(--admin-text)]">
                                        {role === 'client' ? 'Clients' : role === 'artisan' ? 'Artisans' : role === 'livreur' ? 'Livreur' : 'Fournisseur'}
                                    </span>
                                </label>
                            ))}
                        </div>
                    </div>

                    <div className="pt-2 border-t border-[var(--admin-border)] flex justify-between text-xs text-[var(--admin-text-soft)]">
                        <div>Auteur : <strong>{adminName || 'Admin'}</strong></div>
                        <div>Date : <strong>{new Date().toLocaleDateString('fr-FR')}</strong></div>
                    </div>

                    <div className="pt-4 flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-full border border-[var(--admin-border)] px-5 py-2.5 text-sm font-semibold hover:bg-white/10 transition"
                        >
                            Annuler
                        </button>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="rounded-full bg-[#ebb95e] text-[#241b16] px-6 py-2.5 text-sm font-semibold hover:opacity-90 transition disabled:opacity-50"
                        >
                            Enregistrer Brouillon
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export function PromoCodeFormModal({
    form,
    editing,
    onSubmit,
    onClose,
}: {
    form: InertiaForm;
    editing: PromoCodeItem | null;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    onClose: () => void;
}) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div className="admin-panel admin-surface w-full max-w-[550px] rounded-[32px] border p-6 lg:p-8 shadow-2xl relative">
                <div className="flex items-center justify-between border-b border-[var(--admin-border)] pb-4">
                    <h2 className="text-xl font-bold text-[var(--admin-text)]">
                        {editing ? `Modifier le code ${editing.code}` : 'Créer un nouveau code promo'}
                    </h2>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-full p-2 text-[var(--admin-muted)] hover:bg-white/10 hover:text-[var(--admin-text)] transition"
                        title="Fermer"
                    >
                        <CloseIcon className="h-5 w-5" />
                    </button>
                </div>

                <form onSubmit={onSubmit} className="mt-6 space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label className="block space-y-1">
                            <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Code Promo *</span>
                            <input
                                type="text"
                                value={form.data.code}
                                onChange={(e) => form.setData('code', e.target.value.toUpperCase())}
                                placeholder="ex: PROS225"
                                className="admin-input w-full rounded-2xl px-4 py-3 text-sm font-mono font-bold outline-none"
                                required
                            />
                        </label>

                        <label className="block space-y-1">
                            <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Type de réduction *</span>
                            <select
                                value={form.data.discount_type}
                                onChange={(e) => form.setData('discount_type', e.target.value as 'percent' | 'fixed')}
                                className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                            >
                                <option value="percent">Pourcentage (%)</option>
                                <option value="fixed">Montant fixe (FCFA)</option>
                            </select>
                        </label>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label className="block space-y-1">
                            <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">
                                Valeur de la réduction ({form.data.discount_type === 'percent' ? '%' : 'FCFA'}) *
                            </span>
                            <input
                                type="number"
                                min="1"
                                value={form.data.discount_value}
                                onChange={(e) => form.setData('discount_value', Number(e.target.value))}
                                className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                                required
                            />
                        </label>

                        <label className="block space-y-1">
                            <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Montant min commande (FCFA)</span>
                            <input
                                type="number"
                                min="0"
                                value={form.data.min_order_amount}
                                onChange={(e) => form.setData('min_order_amount', Number(e.target.value))}
                                placeholder="0"
                                className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                            />
                        </label>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label className="block space-y-1">
                            <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Plafond réduction (FCFA)</span>
                            <input
                                type="number"
                                min="0"
                                value={form.data.max_discount_amount}
                                onChange={(e) => form.setData('max_discount_amount', Number(e.target.value))}
                                placeholder="Optionnel"
                                className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                            />
                        </label>

                        <label className="block space-y-1">
                            <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Limite d'utilisations</span>
                            <input
                                type="number"
                                min="0"
                                value={form.data.usage_limit}
                                onChange={(e) => form.setData('usage_limit', Number(e.target.value))}
                                placeholder="Optionnel (ex: 500)"
                                className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                            />
                        </label>
                    </div>

                    <label className="block space-y-1">
                        <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Description</span>
                        <input
                            type="text"
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                            placeholder="Description de la campagne promotionnelle"
                            className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                        />
                    </label>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label className="block space-y-1">
                            <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Date d'expiration</span>
                            <input
                                type="date"
                                value={form.data.expires_at}
                                onChange={(e) => form.setData('expires_at', e.target.value)}
                                className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                            />
                        </label>

                        <label className="flex items-center gap-3 pt-6 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={form.data.is_active}
                                onChange={(e) => form.setData('is_active', e.target.checked)}
                                className="rounded border-[var(--admin-border)] h-5 w-5"
                            />
                            <span className="text-sm font-semibold text-[var(--admin-text)]">Code Promo Actif</span>
                        </label>
                    </div>

                    <div className="pt-4 flex justify-end gap-3 border-t border-[var(--admin-border)]">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-full border border-[var(--admin-border)] px-5 py-2.5 text-sm font-semibold hover:bg-white/10 transition"
                        >
                            Annuler
                        </button>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="rounded-full bg-[#ebb95e] text-[#241b16] px-6 py-2.5 text-sm font-semibold hover:opacity-90 transition disabled:opacity-50"
                        >
                            {editing ? 'Mettre à jour' : 'Créer le Code Promo'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export function CampagneParrainageFormModal({
    form,
    editing,
    onSubmit,
    onClose,
}: {
    form: InertiaForm;
    editing: CampagneParrainageItem | null;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    onClose: () => void;
}) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div className="admin-panel admin-surface w-full max-w-[550px] rounded-[32px] border p-6 lg:p-8 shadow-2xl relative">
                <div className="flex items-center justify-between border-b border-[var(--admin-border)] pb-4">
                    <h2 className="text-xl font-bold text-[var(--admin-text)]">
                        {editing ? `Modifier la campagne ${editing.libelle}` : 'Créer une nouvelle campagne de parrainage'}
                    </h2>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-full p-2 text-[var(--admin-muted)] hover:bg-white/10 hover:text-[var(--admin-text)] transition"
                        title="Fermer"
                    >
                        <CloseIcon className="h-5 w-5" />
                    </button>
                </div>

                <form onSubmit={onSubmit} className="mt-6 space-y-4">
                    <label className="block space-y-1">
                        <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Libellé *</span>
                        <input
                            type="text"
                            value={form.data.libelle}
                            onChange={(e) => form.setData('libelle', e.target.value)}
                            placeholder="ex: Parrainage Septembre 2026"
                            className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                            required
                        />
                    </label>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label className="block space-y-1">
                            <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Type de réduction *</span>
                            <select
                                value={form.data.discount_type}
                                onChange={(e) => form.setData('discount_type', e.target.value as 'percent' | 'fixed')}
                                className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                            >
                                <option value="percent">Pourcentage (%)</option>
                                <option value="fixed">Montant fixe (FCFA)</option>
                            </select>
                        </label>

                        <label className="block space-y-1">
                            <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">
                                Valeur de la réduction ({form.data.discount_type === 'percent' ? '%' : 'FCFA'}) *
                            </span>
                            <input
                                type="number"
                                min="1"
                                value={form.data.discount_value}
                                onChange={(e) => form.setData('discount_value', Number(e.target.value))}
                                className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                                required
                            />
                        </label>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label className="block space-y-1">
                            <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Montant min commande/mission (FCFA)</span>
                            <input
                                type="number"
                                min="0"
                                value={form.data.min_montant}
                                onChange={(e) => form.setData('min_montant', Number(e.target.value))}
                                placeholder="0"
                                className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                            />
                        </label>

                        <label className="block space-y-1">
                            <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Plafond réduction (FCFA)</span>
                            <input
                                type="number"
                                min="0"
                                value={form.data.max_discount_amount}
                                onChange={(e) => form.setData('max_discount_amount', Number(e.target.value))}
                                placeholder="Optionnel"
                                className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                            />
                        </label>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label className="block space-y-1">
                            <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Date de début</span>
                            <input
                                type="date"
                                value={form.data.starts_at}
                                onChange={(e) => form.setData('starts_at', e.target.value)}
                                className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                            />
                        </label>

                        <label className="block space-y-1">
                            <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Date d'expiration</span>
                            <input
                                type="date"
                                value={form.data.expires_at}
                                onChange={(e) => form.setData('expires_at', e.target.value)}
                                className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                            />
                        </label>
                    </div>

                    <label className="flex items-center gap-3 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(e) => form.setData('is_active', e.target.checked)}
                            className="rounded border-[var(--admin-border)] h-5 w-5"
                        />
                        <span className="text-sm font-semibold text-[var(--admin-text)]">Campagne Active</span>
                    </label>

                    <div className="pt-4 flex justify-end gap-3 border-t border-[var(--admin-border)]">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-full border border-[var(--admin-border)] px-5 py-2.5 text-sm font-semibold hover:bg-white/10 transition"
                        >
                            Annuler
                        </button>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="rounded-full bg-[#ebb95e] text-[#241b16] px-6 py-2.5 text-sm font-semibold hover:opacity-90 transition disabled:opacity-50"
                        >
                            {editing ? 'Mettre à jour' : 'Créer la Campagne'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export function UserFormModal({
    form,
    editing,
    sectors = [],
    onSubmit,
    onClose,
}: {
    form: InertiaForm;
    editing: AdminUser | null;
    sectors?: SectorItem[];
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    onClose: () => void;
}) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div className="admin-panel admin-surface relative flex w-full max-w-[550px] max-h-[90vh] flex-col rounded-[32px] border shadow-2xl">
                <div className="flex shrink-0 items-center justify-between border-b border-[var(--admin-border)] px-6 py-5 lg:px-8">
                    <h2 className="text-xl font-bold text-[var(--admin-text)]">
                        {editing ? 'Modifier l’utilisateur' : 'Créer un utilisateur'}
                    </h2>
                    <CloseButton onClose={onClose} />
                </div>

                <form onSubmit={onSubmit} className="flex min-h-0 flex-1 flex-col">
                    <div className="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-5 lg:px-8">
                    <label className="block space-y-1">
                        <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Nom complet</span>
                        <input
                            type="text"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                            required
                        />
                        {form.errors.name && <p className="text-xs text-[#b24f43]">{form.errors.name}</p>}
                    </label>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label className="block space-y-1">
                            <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Téléphone</span>
                            <input
                                type="text"
                                value={form.data.phone}
                                onChange={(e) => form.setData('phone', e.target.value)}
                                className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                                required
                                placeholder="+225..."
                            />
                            {form.errors.phone && <p className="text-xs text-[#b24f43]">{form.errors.phone}</p>}
                        </label>

                        <label className="block space-y-1">
                            <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">E-mail</span>
                            <input
                                type="email"
                                value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)}
                                className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                                placeholder="exemple@email.com"
                            />
                            {form.errors.email && <p className="text-xs text-[#b24f43]">{form.errors.email}</p>}
                        </label>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label className="block space-y-1">
                            <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Rôle</span>
                            <select
                                value={form.data.role}
                                onChange={(e) => form.setData('role', e.target.value)}
                                className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none bg-transparent"
                            >
                                <option value="client">Client</option>
                                <option value="artisan">Artisan</option>
                                <option value="fournisseur">Fournisseur</option>
                                <option value="referent">Référent</option>
                                <option value="admin">Administrateur</option>
                            </select>
                            {form.errors.role && <p className="text-xs text-[#b24f43]">{form.errors.role}</p>}
                        </label>

                        <label className="block space-y-1">
                            <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Mot de passe</span>
                            <input
                                type="password"
                                value={form.data.password}
                                onChange={(e) => form.setData('password', e.target.value)}
                                className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                                required={!editing}
                                placeholder={editing ? 'Laisser vide pour ne pas changer' : 'Minimum 6 caractères'}
                            />
                            {form.errors.password && <p className="text-xs text-[#b24f43]">{form.errors.password}</p>}
                        </label>
                    </div>

                    {form.data.role === 'fournisseur' && (() => {
                        const selectedSector = sectors.find((sector) => sector.id === Number(form.data.fournisseur_sector_id));
                        const trades = selectedSector?.trades ?? [];

                        return (
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <label className="block space-y-1">
                                    <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Secteur d’activité (fournisseur)</span>
                                    <select
                                        value={form.data.fournisseur_sector_id ?? ''}
                                        onChange={(e) => {
                                            const value = e.target.value ? Number(e.target.value) : '';
                                            form.setData('fournisseur_sector_id', value);
                                            // Un métier n'a de sens que rattaché à son secteur : on
                                            // efface le choix précédent s'il ne correspond plus.
                                            form.setData('fournisseur_trade_id', '');
                                        }}
                                        className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none bg-transparent"
                                    >
                                        <option value="">Non renseigné</option>
                                        {sectors.map((sector) => (
                                            <option key={sector.id} value={sector.id}>
                                                {sector.icon ? `${sector.icon} ` : ''}{sector.name}
                                            </option>
                                        ))}
                                    </select>
                                    {form.errors.fournisseur_sector_id && (
                                        <p className="text-xs text-[#b24f43]">{form.errors.fournisseur_sector_id}</p>
                                    )}
                                </label>

                                <label className="block space-y-1">
                                    <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Sous-catégorie (métier lié)</span>
                                    <select
                                        value={form.data.fournisseur_trade_id ?? ''}
                                        onChange={(e) => form.setData('fournisseur_trade_id', e.target.value ? Number(e.target.value) : '')}
                                        disabled={!selectedSector || trades.length === 0}
                                        className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none bg-transparent disabled:opacity-50"
                                    >
                                        <option value="">Non renseigné</option>
                                        {trades.map((trade) => (
                                            <option key={trade.id} value={trade.id}>{trade.name}</option>
                                        ))}
                                    </select>
                                    {!selectedSector && (
                                        <p className="text-[11px] text-[var(--admin-muted)]">Choisissez d’abord un secteur.</p>
                                    )}
                                    {selectedSector && trades.length === 0 && (
                                        <p className="text-[11px] text-[var(--admin-muted)]">Aucune sous-catégorie pour ce secteur.</p>
                                    )}
                                    {form.errors.fournisseur_trade_id && (
                                        <p className="text-xs text-[#b24f43]">{form.errors.fournisseur_trade_id}</p>
                                    )}
                                </label>
                            </div>
                        );
                    })()}

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label className="block space-y-1">
                            <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Statut KYC</span>
                            <select
                                value={form.data.kyc_status}
                                onChange={(e) => form.setData('kyc_status', e.target.value)}
                                className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none bg-transparent"
                            >
                                <option value="en_attente">En attente</option>
                                <option value="actif">Actif (Approuvé)</option>
                                <option value="rejete">Rejeté</option>
                            </select>
                            {form.errors.kyc_status && <p className="text-xs text-[#b24f43]">{form.errors.kyc_status}</p>}
                        </label>

                        <label className="block space-y-1">
                            <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Statut du compte</span>
                            <select
                                value={form.data.account_status}
                                onChange={(e) => form.setData('account_status', e.target.value)}
                                className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none bg-transparent"
                            >
                                <option value="actif">Actif</option>
                                <option value="suspendu">Suspendu</option>
                            </select>
                            {form.errors.account_status && <p className="text-xs text-[#b24f43]">{form.errors.account_status}</p>}
                        </label>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label className="block space-y-1">
                            <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Gel de Score ProsArtisan</span>
                            <select
                                value={form.data.score_frozen ? 'oui' : 'non'}
                                onChange={(e) => form.setData('score_frozen', e.target.value === 'oui')}
                                className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none bg-transparent"
                            >
                                <option value="non">Actif (Non gelé)</option>
                                <option value="oui">Gelé (Bloqué)</option>
                            </select>
                            {form.errors.score_frozen && <p className="text-xs text-[#b24f43]">{form.errors.score_frozen}</p>}
                        </label>

                        <label className="block space-y-1">
                            <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Empreinte de l'appareil (IMEI)</span>
                            <input
                                type="text"
                                value={form.data.device_fingerprint}
                                onChange={(e) => form.setData('device_fingerprint', e.target.value)}
                                className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none"
                                placeholder="Empreinte IMEI / Appareil"
                            />
                            {form.errors.device_fingerprint && <p className="text-xs text-[#b24f43]">{form.errors.device_fingerprint}</p>}
                        </label>
                    </div>

                    {editing ? (
                        <div className="space-y-4 border-t border-[var(--admin-border)] pt-4">
                            <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">
                                Photo et pièces d’identité
                            </span>

                            <div className="flex items-center gap-4 rounded-2xl border border-[var(--admin-border)] bg-[var(--admin-panel)] p-3">
                                {form.data.photo ? (
                                    <img
                                        src={URL.createObjectURL(form.data.photo)}
                                        alt="Nouvelle photo"
                                        className="h-14 w-14 shrink-0 rounded-full object-cover"
                                    />
                                ) : editing.photo_url ? (
                                    <img
                                        src={editing.photo_url}
                                        alt={`Photo de ${editing.name}`}
                                        className="h-14 w-14 shrink-0 rounded-full object-cover"
                                    />
                                ) : (
                                    <span className="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-[var(--admin-panel-strong)] text-xs text-[var(--admin-muted)]">
                                        Aucune
                                    </span>
                                )}
                                <div className="min-w-0 flex-1">
                                    <p className="text-xs font-semibold text-[var(--admin-text)]">Photo de profil</p>
                                    <input
                                        type="file"
                                        accept="image/png,image/jpeg"
                                        onChange={(e) => form.setData('photo', e.target.files?.[0] ?? null)}
                                        className="mt-1.5 w-full text-xs text-[var(--admin-text-soft)] file:mr-3 file:rounded-full file:border-0 file:bg-[#ebb95e] file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-[#241b16]"
                                    />
                                    {form.errors.photo && <p className="mt-1 text-xs text-[#b24f43]">{form.errors.photo}</p>}
                                </div>
                            </div>

                            {(['cni', 'selfie'] as const).map((type) => {
                                const current = editing.kyc_documents?.find((doc) => doc.type === type) ?? null;
                                const pendingFile = form.data.documents?.[type] ?? null;

                                return (
                                    <div key={type} className="rounded-2xl border border-[var(--admin-border)] bg-[var(--admin-panel)] p-3">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <p className="text-xs font-semibold text-[var(--admin-text)]">{documentLabels[type]}</p>
                                            {current ? <KycStatusBadge status={current.statut} /> : (
                                                <span className="text-[11px] text-[var(--admin-muted)]">Aucune pièce enregistrée</span>
                                            )}
                                        </div>
                                        {current?.file_url && (
                                            <a
                                                href={current.file_url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="mt-1 inline-block text-[11px] text-[#b77918] underline"
                                            >
                                                Consulter la pièce actuelle
                                            </a>
                                        )}
                                        <input
                                            type="file"
                                            accept="image/png,image/jpeg"
                                            onChange={(e) =>
                                                form.setData('documents', {
                                                    ...form.data.documents,
                                                    [type]: e.target.files?.[0] ?? null,
                                                })
                                            }
                                            className="mt-2 w-full text-xs text-[var(--admin-text-soft)] file:mr-3 file:rounded-full file:border-0 file:bg-[#ebb95e] file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-[#241b16]"
                                        />
                                        {pendingFile && (
                                            <p className="mt-1 text-[11px] text-[var(--admin-muted)]">
                                                Remplacera la pièce actuelle à l’enregistrement — repasse en « en attente ».
                                            </p>
                                        )}
                                        {form.errors[`documents.${type}`] && (
                                            <p className="mt-1 text-xs text-[#b24f43]">{form.errors[`documents.${type}`]}</p>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    ) : null}
                    </div>

                    <div className="flex shrink-0 justify-end gap-3 border-t border-[var(--admin-border)] px-6 py-4 lg:px-8">
                        <button type="button" onClick={onClose} className="admin-button admin-button--ghost">
                            Annuler
                        </button>
                        <button type="submit" disabled={form.processing} className="admin-button admin-button--primary">
                            {form.processing ? 'Enregistrement...' : 'Enregistrer'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export function StatusFormModal({
    form,
    targetUser,
    onSubmit,
    onClose,
}: {
    form: InertiaForm;
    targetUser: AdminUser;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    onClose: () => void;
}) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div className="admin-panel admin-surface w-full max-w-[450px] rounded-[32px] border p-6 shadow-2xl relative">
                <div className="flex items-center justify-between border-b border-[var(--admin-border)] pb-4">
                    <h2 className="text-lg font-bold text-[var(--admin-text)]">
                        Suspendre le compte de {targetUser.name}
                    </h2>
                    <CloseButton onClose={onClose} />
                </div>

                <form onSubmit={onSubmit} className="mt-5 space-y-4">
                    <p className="text-sm text-[var(--admin-text-soft)]">
                        Veuillez indiquer le motif de suspension du compte. Ce motif sera visible pour l'utilisateur.
                    </p>
                    <label className="block space-y-1">
                        <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Motif de suspension</span>
                        <textarea
                            value={form.data.account_status_reason}
                            onChange={(e) => form.setData('account_status_reason', e.target.value)}
                            className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none h-24 resize-none"
                            required
                            placeholder="Ex: Documents non conformes ou comportement abusif signalé..."
                        />
                        {form.errors.account_status_reason && (
                            <p className="text-xs text-[#b24f43]">{form.errors.account_status_reason}</p>
                        )}
                    </label>

                    <div className="flex justify-end gap-3 pt-3">
                        <button type="button" onClick={onClose} className="admin-button admin-button--ghost">
                            Annuler
                        </button>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="admin-button bg-[#f15f57] text-white hover:bg-[#dd4d45]"
                        >
                            {form.processing ? 'Suspension...' : 'Suspendre le compte'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

export function AiQuotaFormModal({
    form,
    targetName,
    globalDailyLimit,
    globalMonthlyLimit,
    onSubmit,
    onClose,
}: {
    form: InertiaForm;
    targetName: string;
    globalDailyLimit: number;
    globalMonthlyLimit: number;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    onClose: () => void;
}) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div className="admin-panel admin-surface w-full max-w-[480px] rounded-[32px] border p-6 shadow-2xl relative">
                <div className="flex items-center justify-between border-b border-[var(--admin-border)] pb-4">
                    <h2 className="text-lg font-bold text-[var(--admin-text)]">Quota IA — {targetName}</h2>
                    <CloseButton onClose={onClose} />
                </div>

                <form onSubmit={onSubmit} className="mt-5 space-y-4">
                    <p className="text-sm text-[var(--admin-text-soft)]">
                        Laisser vide pour appliquer la limite globale ({globalDailyLimit > 0 ? `${globalDailyLimit}/j` : 'illimité'},{' '}
                        {globalMonthlyLimit > 0 ? `${globalMonthlyLimit}/mois` : 'illimité/mois'}). Mettre <strong>0</strong> pour un accès illimité propre à cet utilisateur.
                    </p>

                    <div className="grid grid-cols-2 gap-3">
                        <label className="block space-y-1">
                            <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Limite / jour</span>
                            <input
                                type="number"
                                min={0}
                                value={form.data.daily_limit}
                                onChange={(e) => form.setData('daily_limit', e.target.value)}
                                className="admin-input w-full rounded-xl px-3 py-2 text-sm outline-none"
                                placeholder="défaut"
                            />
                            {form.errors.daily_limit && <p className="text-xs text-[#b24f43]">{form.errors.daily_limit}</p>}
                        </label>
                        <label className="block space-y-1">
                            <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Limite / mois</span>
                            <input
                                type="number"
                                min={0}
                                value={form.data.monthly_limit}
                                onChange={(e) => form.setData('monthly_limit', e.target.value)}
                                className="admin-input w-full rounded-xl px-3 py-2 text-sm outline-none"
                                placeholder="défaut"
                            />
                            {form.errors.monthly_limit && <p className="text-xs text-[#b24f43]">{form.errors.monthly_limit}</p>}
                        </label>
                    </div>

                    <label className="flex items-center gap-3">
                        <input
                            type="checkbox"
                            checked={form.data.blocked}
                            onChange={(e) => form.setData('blocked', e.target.checked)}
                            className="h-4 w-4 rounded border-[var(--admin-border)]"
                        />
                        <span className="text-sm font-semibold text-[var(--admin-text)]">Bloquer complètement l'accès à l'IA pour cet utilisateur</span>
                    </label>

                    <label className="block space-y-1">
                        <span className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Note interne (optionnel)</span>
                        <textarea
                            value={form.data.note}
                            onChange={(e) => form.setData('note', e.target.value)}
                            className="admin-input w-full rounded-2xl px-4 py-3 text-sm outline-none h-20 resize-none"
                            maxLength={500}
                            placeholder="Ex: abus détecté le 12/09, quota réduit temporairement."
                        />
                        {form.errors.note && <p className="text-xs text-[#b24f43]">{form.errors.note}</p>}
                    </label>

                    <div className="flex justify-end gap-3 pt-3">
                        <button type="button" onClick={onClose} className="admin-button admin-button--ghost">Annuler</button>
                        <button type="submit" disabled={form.processing} className="admin-button admin-button--primary">
                            {form.processing ? 'Enregistrement...' : 'Enregistrer'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
