// Onglet « Paramètres » du backoffice — extrait de console.tsx (Chantier C2).

import { router } from '@inertiajs/react';

import { cn } from '@/lib/utils';

import {
    AlertIcon,
    ClipboardIcon,
    InfoPill,
    SectionTitle,
    SettingsIcon,
    ShieldIcon,
    Surface,
    UsersIcon,
    WalletIcon,
} from '../shared';
import type { SectorItem, SettingItem } from '../shared';

interface SettingsPanelProps {
    adminName: string;
    adminContact: string;
    offlineActive: boolean;
    isOfflineSimulated: boolean;
    onToggleOfflineSimulated: () => void;
    onRefresh: () => void;
    settingsList: SettingItem[];
    sectors: SectorItem[];
    expandedSectors: Record<number, boolean>;
    onToggleSector: (sectorId: number) => void;
}

export function SettingsPanel({
    adminName,
    adminContact,
    offlineActive,
    isOfflineSimulated,
    onToggleOfflineSimulated,
    onRefresh,
    settingsList,
    sectors,
    expandedSectors,
    onToggleSector,
}: SettingsPanelProps) {
    return (
        <section className="mt-5 grid gap-5 xl:grid-cols-2">
            <Surface className="rounded-[32px] p-5 lg:p-6">
                <SectionTitle
                    description="Garde-fous fonctionnels à ne jamais contourner dans ProsArtisan."
                    title="Règles métier critiques"
                />
                <ul className="mt-5 space-y-2.5">
                    {[
                        { icon: ShieldIcon, text: 'Client et artisan doivent être KYC actifs avant toute mission ou transaction.' },
                        { icon: WalletIcon, text: "Le ratio de fragmentation matériaux / main d'œuvre reste figé après acceptation du devis." },
                        { icon: AlertIcon, text: 'Le scan J-Code doit toujours vérifier une distance fournisseur ≤ 100 m.' },
                        { icon: ClipboardIcon, text: 'Aucune libération de fonds sans OTP jalon validé côté client.' },
                        { icon: UsersIcon, text: 'Toute mission au-delà de 2 000 000 FCFA exige une validation physique Référent.' },
                        { icon: SettingsIcon, text: 'Les montants financiers restent en BIGINT FCFA, sans float ni double.' },
                    ].map((rule, index) => (
                        <li key={index} className="flex items-start gap-3 rounded-[18px] border border-[var(--admin-border)] bg-white/55 px-4 py-3">
                            <rule.icon className="mt-0.5 h-4 w-4 shrink-0 text-[var(--admin-muted)]" />
                            <span className="text-sm leading-6 text-[var(--admin-text-soft)]">{rule.text}</span>
                        </li>
                    ))}
                </ul>
            </Surface>

            <Surface className="rounded-[32px] p-5 lg:p-6">
                <SectionTitle
                    description="Configuration métier et accès du poste administrateur courant."
                    title="Session backoffice"
                />
                <div className="mt-5 grid gap-3 sm:grid-cols-2">
                    <InfoPill label="Admin" value={adminName} />
                    <InfoPill label="Contact" value={adminContact} />
                    <InfoPill label="Langue" value="Français" />
                    <InfoPill label="Paiements" value="Wave CI / Orange Money CI" />
                    <div className="flex items-center justify-between gap-3 rounded-[18px] border border-[var(--admin-border)] bg-white/55 px-4 py-3">
                        <div>
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-[var(--admin-muted)]">Connectivité</p>
                            <p className="mt-1 text-sm font-bold text-[var(--admin-text)]">
                                {offlineActive ? 'Hors-ligne (+ USSD)' : 'En ligne'}
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={onToggleOfflineSimulated}
                            className={cn(
                                'rounded-lg px-2.5 py-1.5 text-xs font-semibold border transition',
                                offlineActive
                                    ? 'bg-amber-100 border-amber-300 text-amber-800'
                                    : 'bg-green-100 border-green-300 text-green-800',
                            )}
                        >
                            {isOfflineSimulated ? 'Simulé' : 'Simuler Offline'}
                        </button>
                    </div>
                    <InfoPill label="Mobile" value="Android prioritaire" />
                </div>
                <div className="mt-5 flex flex-wrap gap-3">
                    <button type="button" className="admin-button admin-button--primary" onClick={onRefresh}>
                        Rafraîchir les données
                    </button>
                    <button type="button" className="admin-button admin-button--ghost" onClick={() => router.post('/admin/logout')}>
                        Se déconnecter
                    </button>
                </div>
            </Surface>

            <Surface className="rounded-[32px] p-5 lg:p-6 xl:col-span-2">
                <SectionTitle
                    description="Modifiez en temps réel les pourcentages de commission, frais de service et configurations métier."
                    title="Gestion des Commissions & Paramètres"
                />
                <div className="mt-6 space-y-6">
                    {settingsList && settingsList.length > 0 ? (
                        settingsList.map((setting) => (
                            <div key={setting.id} className="flex flex-col md:flex-row md:items-center justify-between gap-4 p-4 rounded-2xl border border-[var(--admin-border)] bg-white/60">
                                <div className="min-w-0 flex-1">
                                    <h4 className="font-semibold text-sm text-[var(--admin-text)]">{setting.label ?? setting.key}</h4>
                                    <p className="text-xs text-[var(--admin-muted)] mt-1">{setting.description}</p>
                                </div>
                                <div className="flex items-center gap-3 shrink-0">
                                    {setting.key === 'otp_delivery_channel' ? (
                                        <select
                                            defaultValue={setting.value}
                                            onChange={(e) => {
                                                router.put(`/admin/settings/${setting.id}`, {
                                                    value: e.target.value,
                                                }, { preserveScroll: true });
                                            }}
                                            className="admin-input w-48 rounded-xl px-3 py-2 text-sm text-center outline-none bg-white border border-[var(--admin-border)]"
                                        >
                                            <option value="sms">SMS uniquement</option>
                                            <option value="whatsapp">WhatsApp uniquement</option>
                                            <option value="both">SMS & WhatsApp</option>
                                        </select>
                                    ) : setting.key.startsWith('block_') ? (
                                        <select
                                            defaultValue={setting.value}
                                            onChange={(e) => {
                                                router.put(`/admin/settings/${setting.id}`, {
                                                    value: e.target.value,
                                                }, { preserveScroll: true });
                                            }}
                                            className="admin-input w-48 rounded-xl px-3 py-2 text-sm text-center outline-none bg-white border border-[var(--admin-border)]"
                                        >
                                            <option value="none">Accès normal</option>
                                            <option value="new">Bloquer Nouveaux</option>
                                            <option value="old">Bloquer Anciens</option>
                                            <option value="all">Bloquer Tous</option>
                                            <option value="hidden">Masquer l'icône (Mobile)</option>
                                        </select>
                                    ) : setting.key === 'app_access_disabled_message' || setting.key.startsWith('app_access_disabled_message_') ? (
                                        <textarea
                                            defaultValue={setting.value}
                                            onBlur={(e) => {
                                                if (e.target.value !== setting.value) {
                                                    router.put(`/admin/settings/${setting.id}`, {
                                                        value: e.target.value,
                                                    }, { preserveScroll: true });
                                                }
                                            }}
                                            rows={3}
                                            className="admin-input w-72 rounded-xl px-3 py-2 text-sm outline-none bg-white border border-[var(--admin-border)] resize-y"
                                        />
                                    ) : (
                                        <input
                                            type="text"
                                            defaultValue={setting.value}
                                            onBlur={(e) => {
                                                if (e.target.value !== setting.value) {
                                                    router.put(`/admin/settings/${setting.id}`, {
                                                        value: e.target.value,
                                                    }, { preserveScroll: true });
                                                }
                                            }}
                                            className="admin-input w-48 rounded-xl px-3 py-2 text-sm text-center outline-none"
                                        />
                                    )}
                                    <span className="text-xs text-[var(--admin-muted)] uppercase tracking-wider font-semibold">
                                        {setting.type}
                                    </span>
                                </div>
                            </div>
                        ))
                    ) : (
                        <p className="text-sm text-[var(--admin-muted)]">Aucun paramètre trouvé.</p>
                    )}
                </div>
            </Surface>

            <Surface className="rounded-[32px] p-5 lg:p-6 xl:col-span-2">
                <SectionTitle
                    description="Modifiez les noms des catégories (Secteurs) et sous-catégories (Métiers) de la plateforme."
                    title="Gestion des Catégories & Métiers"
                />

                {/* Nouvelles créations */}
                <div className="mt-6 grid gap-6 lg:grid-cols-2 p-5 rounded-2xl border border-[var(--admin-border)] bg-amber-50/20">
                    {/* Création Catégorie */}
                    <form onSubmit={(e) => {
                        e.preventDefault();
                        const form = e.currentTarget;
                        const input = form.querySelector('input') as HTMLInputElement;
                        if (input.value.trim() !== '') {
                            router.post('/admin/sectors', { name: input.value }, {
                                preserveScroll: true,
                                onSuccess: () => { input.value = ''; },
                            });
                        }
                    }} className="space-y-3">
                        <h4 className="font-semibold text-sm text-[var(--admin-text)]">Créer une catégorie</h4>
                        <div className="flex flex-col sm:flex-row gap-2">
                            <input
                                type="text"
                                placeholder="Nom de la catégorie (ex: Électricité)"
                                className="admin-input flex-1 rounded-xl px-3 py-2 text-sm outline-none bg-white border border-[var(--admin-border)] w-full"
                            />
                            <button type="submit" className="admin-button admin-button--primary text-xs py-2.5 px-4 shrink-0 w-full sm:w-auto">
                                Ajouter
                            </button>
                        </div>
                    </form>

                    {/* Création Sous-catégorie */}
                    <form onSubmit={(e) => {
                        e.preventDefault();
                        const form = e.currentTarget;
                        const select = form.querySelector('select') as HTMLSelectElement;
                        const input = form.querySelector('input') as HTMLInputElement;
                        if (select.value && input.value.trim() !== '') {
                            router.post('/admin/trades', {
                                sector_id: select.value,
                                name: input.value,
                            }, {
                                preserveScroll: true,
                                onSuccess: () => { input.value = ''; },
                            });
                        }
                    }} className="space-y-3">
                        <h4 className="font-semibold text-sm text-[var(--admin-text)]">Créer une sous-catégorie</h4>
                        <div className="flex flex-col sm:flex-row gap-2">
                            <select
                                className="admin-input rounded-xl px-3 py-2 text-sm outline-none bg-white border border-[var(--admin-border)] w-full sm:w-1/3"
                                defaultValue=""
                                required
                            >
                                <option value="" disabled>Sélectionner catégorie</option>
                                {sectors?.map((s) => (
                                    <option key={s.id} value={s.id}>{s.name}</option>
                                ))}
                            </select>
                            <input
                                type="text"
                                placeholder="Nom du métier (ex: Bobineur)"
                                className="admin-input flex-1 rounded-xl px-3 py-2 text-sm outline-none bg-white border border-[var(--admin-border)] w-full"
                                required
                            />
                            <button type="submit" className="admin-button admin-button--primary text-xs py-2.5 px-4 shrink-0 w-full sm:w-auto">
                                Ajouter
                            </button>
                        </div>
                    </form>
                </div>

                <div className="mt-6 space-y-6">
                    {sectors && sectors.length > 0 ? (
                        sectors.map((sector) => (
                            <div key={sector.id} className="p-5 rounded-2xl border border-[var(--admin-border)] bg-white/60 space-y-4">
                                <div className="flex items-center justify-between gap-4">
                                    <div className="flex-1">
                                        <span className="text-[10px] font-bold text-amber-600 uppercase">Catégorie (Secteur)</span>
                                        <input
                                            type="text"
                                            defaultValue={sector.name}
                                            onBlur={(e) => {
                                                if (e.target.value !== sector.name && e.target.value.trim() !== '') {
                                                    router.put(`/admin/sectors/${sector.id}`, {
                                                        name: e.target.value,
                                                    }, { preserveScroll: true });
                                                }
                                            }}
                                            className="admin-input mt-1 w-full rounded-xl px-3 py-2 text-sm font-semibold outline-none"
                                        />
                                    </div>
                                    <div className="pt-5">
                                        <button
                                            type="button"
                                            onClick={() => onToggleSector(sector.id)}
                                            className="p-2 rounded-xl border border-[var(--admin-border)] bg-white hover:bg-slate-100 transition flex items-center gap-1.5 text-xs text-[var(--admin-muted)] font-medium"
                                        >
                                            <span>{sector.trades?.length ?? 0} sous-catégories</span>
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                                className={cn(
                                                    'w-4 h-4 transition-transform duration-200',
                                                    expandedSectors[sector.id] ? 'rotate-180' : '',
                                                )}
                                            >
                                                <path fillRule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clipRule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                {expandedSectors[sector.id] && (
                                    <div className="pl-6 border-l-2 border-amber-200/50 space-y-3 pt-2">
                                        <span className="text-[10px] font-bold text-slate-500 uppercase">Sous-catégories (Métiers)</span>
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            {sector.trades && sector.trades.length > 0 ? (
                                                sector.trades.map((trade) => (
                                                    <div key={trade.id} className="flex flex-col p-2 bg-white/40 border border-slate-100 rounded-xl">
                                                        <input
                                                            type="text"
                                                            defaultValue={trade.name}
                                                            onBlur={(e) => {
                                                                if (e.target.value !== trade.name && e.target.value.trim() !== '') {
                                                                    router.put(`/admin/trades/${trade.id}`, {
                                                                        name: e.target.value,
                                                                    }, { preserveScroll: true });
                                                                }
                                                            }}
                                                            className="admin-input w-full rounded-lg px-2 py-1 text-xs outline-none bg-transparent hover:bg-white focus:bg-white"
                                                        />
                                                    </div>
                                                ))
                                            ) : (
                                                <p className="text-xs text-[var(--admin-muted)]">Aucun métier associé.</p>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>
                        ))
                    ) : (
                        <p className="text-sm text-[var(--admin-muted)]">Aucune catégorie trouvée.</p>
                    )}
                </div>
            </Surface>
        </section>
    );
}
