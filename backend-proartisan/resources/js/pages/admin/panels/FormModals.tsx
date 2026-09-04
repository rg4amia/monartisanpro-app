// Modales-formulaires du backoffice — extraites de console.tsx (Chantier C2).
// Chaque modale reçoit l'instance `useForm` d'Inertia du parent (données + setData + errors + processing).

import type { FormEvent } from 'react';

import { CloseIcon } from '../shared';
import type { AdminUser, PromoCodeItem } from '../shared';

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
    onSubmit,
    onClose,
}: {
    form: InertiaForm;
    editing: unknown;
    adminName: string;
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
                        </select>
                    </label>

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

export function UserFormModal({
    form,
    editing,
    onSubmit,
    onClose,
}: {
    form: InertiaForm;
    editing: AdminUser | null;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    onClose: () => void;
}) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <div className="admin-panel admin-surface w-full max-w-[550px] rounded-[32px] border p-6 lg:p-8 shadow-2xl relative">
                <div className="flex items-center justify-between border-b border-[var(--admin-border)] pb-4">
                    <h2 className="text-xl font-bold text-[var(--admin-text)]">
                        {editing ? 'Modifier l’utilisateur' : 'Créer un utilisateur'}
                    </h2>
                    <CloseButton onClose={onClose} />
                </div>

                <form onSubmit={onSubmit} className="mt-6 space-y-4">
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

                    <div className="flex justify-end gap-3 pt-4 border-t border-[var(--admin-border)]">
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
