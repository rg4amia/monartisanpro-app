import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import { useConfirm } from './shared/ConfirmDialog';

interface Permission {
    id: number;
    name: string;
    description: string | null;
    category: string | null;
}

interface AdminAccount {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    capabilities: string[];
    /** Super administrateur protégé : accès total permanent, non modifiable. */
    protected: boolean;
    /** Le compte de l'administrateur connecté : il garde toujours la gestion des rôles. */
    is_self?: boolean;
}

/** Capacités qu'un administrateur ne se retire jamais à lui-même (anti-verrouillage). */
const SELF_KEPT_CAPABILITIES = ['admin.roles.manage', 'admin.users.view'];

const EMPTY_SELECTION_MESSAGE = "Cochez au moins une capacité, ou l'accès total.";

/** Premier message d'erreur renvoyé par le serveur, sinon le message par défaut. */
function firstError(errors: Record<string, string> | undefined, fallback: string): string {
    const first = errors ? Object.values(errors)[0] : undefined;
    return typeof first === 'string' && first !== '' ? first : fallback;
}

interface RolesPermissionsPanelProps {
    allPermissions: Permission[];
    rolesPermissions: Record<string, string[]>;
    /** Catalogue des capacités fines du backoffice : groupe => { nom: description } (Chantier C6 / P2-10). */
    adminCapabilityCatalog: Record<string, Record<string, string>>;
    admins: AdminAccount[];
    /** Actions indispensables à un rôle : elles ne se retirent pas. */
    protectedRolePermissions?: Record<string, string[]>;
    /** Actions réservées : nom de l'action => rôles auxquels elle s'attribue. */
    reservedRolePermissions?: Record<string, string[]>;
    /** Rôles dont les droits s'écartent des droits d'origine. */
    customizedRoles?: Record<string, boolean>;
    /** Profils types d'administrateur : un jeu de capacités appliqué d'un geste. */
    adminProfiles?: Record<string, AdminProfile>;
}

interface AdminProfile {
    label: string;
    description: string;
    capabilities: string[];
}

const roleLabels: Record<string, string> = {
    client: 'Client',
    artisan: 'Artisan',
    fournisseur: 'Fournisseur',
    referent: 'Référent',
    livreur: 'Livreur',
};

const categoryLabels: Record<string, string> = {
    missions: 'Missions & Affectations',
    devis: 'Devis & Acomptes',
    jalons: 'Suivi Jalons & OTP',
    jcodes: 'J-Codes & Matériaux',
    orders: 'Commandes E-Commerce',
    litiges: 'Arbitrages & Litiges',
    kyc: 'Dossiers KYC',
    evaluations: 'Évaluations',
    parrainages: 'Parrainages',
    'micro-credit': 'Micro-crédit',
    transactions: 'Transactions Financières',
    sms: 'SMS & OTP',
    supplier: 'Espace Fournisseur',
};

const adminGroupLabels: Record<string, string> = {
    kyc: 'KYC & Vérifications',
    missions: 'Missions',
    litiges: 'Litiges',
    users: 'Utilisateurs',
    finance: 'Finance & Exports',
    qualite: 'Qualité & Fournisseurs',
    plateforme: 'Plateforme & Sécurité',
    communication: 'Communication',
    marketing: 'Marketing',
    intelligence: 'Intelligence Artificielle',
};

export default function RolesPermissionsPanel({
    allPermissions = [],
    rolesPermissions = {},
    adminCapabilityCatalog = {},
    admins = [],
    protectedRolePermissions = {},
    reservedRolePermissions = {},
    customizedRoles = {},
    adminProfiles = {},
}: RolesPermissionsPanelProps) {
    const { confirm, dialog } = useConfirm();
    const [selectedRole, setSelectedRole] = useState<string>('client');
    const [toggling, setToggling] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const [selectedAdminId, setSelectedAdminId] = useState<number | null>(admins[0]?.id ?? null);
    const [savingAdmin, setSavingAdmin] = useState(false);

    const selectedAdmin = useMemo(
        () => admins.find((a) => a.id === selectedAdminId) ?? null,
        [admins, selectedAdminId],
    );
    const adminHasFullAccess = selectedAdmin?.capabilities.includes('*') ?? false;
    const adminIsProtected = selectedAdmin?.protected ?? false;
    const adminLocked = savingAdmin || adminIsProtected;

    // Une action réservée à d'autres rôles ne peut pas être attribuée à
    // celui-ci : l'afficher verrouillée ne ferait qu'encombrer l'écran.
    const rolePermissions = allPermissions.filter((perm) => {
        const held = rolesPermissions[selectedRole]?.includes(perm.name) ?? false;
        const reservedFor = reservedRolePermissions[perm.name];
        return held || reservedFor === undefined || reservedFor.includes(selectedRole);
    });

    // Group permissions by category
    const groupedPermissions = rolePermissions.reduce((acc, perm) => {
        const cat = perm.category || 'other';
        if (!acc[cat]) acc[cat] = [];
        acc[cat].push(perm);
        return acc;
    }, {} as Record<string, Permission[]>);

    const handleTogglePermission = (permissionName: string, hasPermission: boolean) => {
        const action = hasPermission ? 'revoke' : 'assign';
        const url = `/admin/roles-permissions/${action}`;

        setErrorMessage(null);
        setToggling(permissionName);

        router.post(url, {
            role: selectedRole,
            permission: permissionName,
        }, {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                setToggling(null);
            },
            onError: (errors) => {
                setErrorMessage(firstError(errors, 'Une erreur est survenue lors de la mise à jour des droits.'));
            },
        });
    };

    const resetRole = async () => {
        const ok = await confirm({
            title: `Rétablir les droits d'origine du rôle ${roleLabels[selectedRole] ?? selectedRole} ?`,
            message:
                "Les actions retirées ou ajoutées à ce rôle reviennent à leur réglage d'origine. Le changement s'applique aussitôt à tous les comptes de ce rôle.",
            confirmLabel: "Rétablir les droits d'origine",
            tone: 'primary',
        });
        if (!ok) return;

        setErrorMessage(null);
        router.post('/admin/roles-permissions/reset', { role: selectedRole }, {
            preserveState: true,
            preserveScroll: true,
            onError: (errors) => setErrorMessage(firstError(errors, 'Une erreur est survenue lors du retour aux droits d’origine.')),
        });
    };

    const applyProfile = async (profile: AdminProfile) => {
        if (!selectedAdmin || adminIsProtected) return;

        const ok = await confirm({
            title: `Appliquer le profil « ${profile.label} » à ${selectedAdmin.name} ?`,
            message: `Ses droits actuels sont remplacés par les ${profile.capabilities.length} capacités de ce profil. Vous pourrez ensuite les ajuster une à une.`,
            confirmLabel: 'Appliquer le profil',
            tone: 'primary',
        });
        if (!ok) return;

        const next = [...profile.capabilities];
        if (selectedAdmin.is_self) {
            for (const kept of SELF_KEPT_CAPABILITIES) {
                if (!next.includes(kept)) next.push(kept);
            }
        }
        submitAdminCapabilities(next);
    };

    const submitAdminCapabilities = (capabilities: string[]) => {
        if (!selectedAdmin || adminIsProtected) return;
        setErrorMessage(null);
        setSavingAdmin(true);
        router.post(`/admin/admins/${selectedAdmin.id}/permissions`, { capabilities }, {
            // Sans cela, Inertia remonte le composant après le POST et `selectedAdminId`
            // repart sur le premier admin de la liste — la modification paraît « ne rien faire ».
            preserveState: true,
            preserveScroll: true,
            onFinish: () => setSavingAdmin(false),
            onError: (errors) => setErrorMessage(firstError(errors, 'Une erreur est survenue lors de la mise à jour des droits admin.')),
        });
    };

    const toggleAdminCapability = (capability: string) => {
        if (!selectedAdmin || adminIsProtected) return;

        // Toutes les capacités répertoriées dans le catalogue
        const allCapabilities = Object.values(adminCapabilityCatalog).flatMap((group) => Object.keys(group));

        // Si l'administrateur possède l'accès total, décocher une capacité passe en mode
        // personnalisé en conservant l'ensemble des autres capacités.
        let next: string[];
        if (adminHasFullAccess) {
            next = allCapabilities.filter((c) => c !== capability);
        } else {
            const current = selectedAdmin.capabilities.filter((c) => c !== '*');
            next = current.includes(capability)
                ? current.filter((c) => c !== capability)
                : [...current, capability];
        }

        // Si toutes les capacités sont sélectionnées, réactiver la sentinelle d'accès total
        if (allCapabilities.length > 0 && next.length >= allCapabilities.length) {
            submitAdminCapabilities(['admin.full-access']);
            return;
        }

        // Garde-fou anti-verrouillage : seul l'administrateur connecté garde d'office
        // la gestion des rôles sur son propre compte. L'imposer aux autres comptes
        // revenait à leur donner l'accès total.
        if (selectedAdmin.is_self) {
            for (const kept of SELF_KEPT_CAPABILITIES) {
                if (!next.includes(kept)) next.push(kept);
            }
        }

        // « Aucune capacité » n'est pas un état : le serveur le refuse aussi.
        if (next.length === 0) {
            setErrorMessage(EMPTY_SELECTION_MESSAGE);
            return;
        }

        submitAdminCapabilities(next);
    };

    const setFullAccess = (full: boolean) => {
        if (full) {
            submitAdminCapabilities(['admin.full-access']);
            return;
        }

        // Retirer l'accès total garde chaque capacité actuelle, écrite une à une :
        // l'administrateur décoche ensuite celles qu'il veut retirer.
        submitAdminCapabilities(Object.values(adminCapabilityCatalog).flatMap((group) => Object.keys(group)));
    };

    const roles = ['client', 'artisan', 'fournisseur', 'referent', 'livreur'];

    const adminHas = (capability: string) =>
        adminHasFullAccess || (selectedAdmin?.capabilities.includes(capability) ?? false);

    return (
        <div className="space-y-8">
            {dialog}
            {errorMessage ? (
                <div
                    role="alert"
                    className="flex items-start justify-between gap-4 rounded-2xl border border-red-300 bg-red-50 p-4 text-sm text-red-800"
                >
                    <span>{errorMessage}</span>
                    <button
                        type="button"
                        onClick={() => setErrorMessage(null)}
                        className="shrink-0 text-xs font-semibold text-red-700 hover:underline"
                    >
                        Fermer
                    </button>
                </div>
            ) : null}

            {/* ── Droits fins des administrateurs (Chantier C6 / P2-10) ── */}
            <div className="rounded-[28px] border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] p-6">
                <div className="border-b border-[var(--admin-border)] pb-4 mb-6">
                    <h3 className="text-xl font-bold text-[var(--admin-text)]">Droits des administrateurs</h3>
                    <p className="text-xs text-[var(--admin-text-soft)] mt-1">
                        Chaque compte admin peut être restreint à un périmètre précis. L'ensemble des droits du
                        backoffice s'accorde par la case « Accès total » ; un compte garde toujours au moins une capacité.
                    </p>
                </div>

                {admins.length === 0 ? (
                    <p className="text-sm text-[var(--admin-text-soft)]">Aucun compte administrateur.</p>
                ) : (
                    <div className="grid gap-6 xl:grid-cols-4">
                        <div className="xl:col-span-1 space-y-1.5">
                            {admins.map((admin) => (
                                <button
                                    key={admin.id}
                                    type="button"
                                    onClick={() => setSelectedAdminId(admin.id)}
                                    className={`w-full text-left px-4 py-3 rounded-2xl text-sm transition ${
                                        selectedAdminId === admin.id
                                            ? 'bg-[#f4e2bf] text-[#7d571b] shadow-sm'
                                            : 'text-[var(--admin-text-soft)] hover:bg-[#f7efe2]'
                                    }`}
                                >
                                    <span className="block font-medium">{admin.name}</span>
                                    <span className="block text-[11px] opacity-70">
                                        {admin.capabilities.includes('*') ? 'Accès total' : `${admin.capabilities.length} droit(s)`}
                                    </span>
                                </button>
                            ))}
                        </div>

                        <div className="xl:col-span-3 space-y-6">
                            {selectedAdmin && (
                                <>
                                    {adminIsProtected ? (
                                        <p className="rounded-2xl border border-[#e6d3b2] bg-[#fbf1db] p-4 text-xs text-[#7d571b]">
                                            <strong>Super administrateur protégé.</strong> Ce compte dispose d'un accès total
                                            permanent (configuré via <code>SUPER_ADMIN_EMAILS</code>) et ne peut pas être restreint
                                            ici — garde-fou anti-verrouillage.
                                        </p>
                                    ) : null}

                                    <label className="flex items-center justify-between gap-4 p-4 rounded-2xl border border-[var(--admin-border)] bg-[var(--admin-panel)]">
                                        <div className="min-w-0">
                                            <span className="font-semibold text-sm text-[var(--admin-text)]">Accès total</span>
                                            <p className="text-xs text-[var(--admin-text-soft)] mt-1">
                                                Toutes les capacités, présentes et futures. Décochez cette case, puis les
                                                capacités à retirer, pour limiter ce compte à un périmètre précis.
                                            </p>
                                        </div>
                                        <input
                                            type="checkbox"
                                            className="h-5 w-5 shrink-0"
                                            checked={adminHasFullAccess}
                                            disabled={adminLocked}
                                            onChange={(e) => setFullAccess(e.target.checked)}
                                        />
                                    </label>

                                    {Object.keys(adminProfiles).length > 0 && !adminIsProtected ? (
                                        <div className="rounded-2xl border border-[var(--admin-border)] bg-[var(--admin-panel)] p-4">
                                            <span className="font-semibold text-sm text-[var(--admin-text)]">Profils types</span>
                                            <p className="text-xs text-[var(--admin-text-soft)] mt-1">
                                                Un profil remplace les droits du compte par un jeu de capacités, à ajuster ensuite.
                                                Aucun ne donne la gestion des rôles.
                                            </p>
                                            <div className="mt-3 flex flex-wrap gap-2">
                                                {Object.entries(adminProfiles).map(([key, profile]) => (
                                                    <button
                                                        key={key}
                                                        type="button"
                                                        title={profile.description}
                                                        disabled={adminLocked}
                                                        onClick={() => applyProfile(profile)}
                                                        className="rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-3 py-1.5 text-xs font-semibold text-[var(--admin-text)] transition hover:bg-[#f7efe2] disabled:opacity-50"
                                                    >
                                                        {profile.label}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    ) : null}

                                    {Object.entries(adminCapabilityCatalog).map(([group, capabilities]) => (
                                        <div key={group} className="space-y-3">
                                            <h4 className="text-xs font-bold uppercase tracking-widest text-[#b77918] border-b border-[var(--admin-border)] pb-1.5">
                                                {adminGroupLabels[group] || group}
                                            </h4>
                                            <div className="grid gap-3 sm:grid-cols-2">
                                                {Object.entries(capabilities).map(([name, description]) => (
                                                    <label
                                                        key={name}
                                                        className={`flex items-start justify-between gap-4 p-4 rounded-2xl border border-[var(--admin-border)] transition ${
                                                            adminLocked ? '' : 'cursor-pointer'
                                                        } ${
                                                            !adminHasFullAccess && adminHas(name) ? 'bg-[#eef8f0]/40' : 'bg-[var(--admin-panel)]'
                                                        } ${adminHasFullAccess ? 'opacity-70' : ''}`}
                                                    >
                                                        <div className="min-w-0">
                                                            <span className="font-mono text-xs font-semibold text-[var(--admin-text)]">{name}</span>
                                                            <p className="text-xs text-[var(--admin-text-soft)] mt-1">{description}</p>
                                                        </div>
                                                        <input
                                                            type="checkbox"
                                                            className="h-5 w-5 shrink-0 mt-0.5"
                                                            checked={adminHas(name)}
                                                            disabled={adminLocked}
                                                            onChange={() => toggleAdminCapability(name)}
                                                        />
                                                    </label>
                                                ))}
                                            </div>
                                        </div>
                                    ))}
                                </>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {/* ── Droits métier par rôle ── */}
            <div className="grid gap-6 xl:grid-cols-4">
                <div className="xl:col-span-1 space-y-2">
                    <div className="rounded-[28px] border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] p-4">
                        <p className="text-xs font-semibold uppercase tracking-wider text-[var(--admin-muted)] mb-3 px-2">
                            Rôles du Système
                        </p>
                        <div className="flex flex-col gap-1.5">
                            {roles.map((role) => (
                                <button
                                    key={role}
                                    type="button"
                                    onClick={() => setSelectedRole(role)}
                                    className={`w-full text-left px-4 py-3 rounded-2xl text-sm font-medium transition ${
                                        selectedRole === role
                                            ? 'bg-[#f4e2bf] text-[#7d571b] shadow-sm'
                                            : 'text-[var(--admin-text-soft)] hover:bg-[#f7efe2]'
                                    }`}
                                >
                                    {roleLabels[role] || role}
                                </button>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="xl:col-span-3 space-y-6">
                    <div className="rounded-[28px] border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] p-6">
                        <div className="flex items-center justify-between border-b border-[var(--admin-border)] pb-4 mb-6">
                            <div>
                                <h3 className="text-xl font-bold text-[var(--admin-text)]">
                                    Droits & Actions du rôle : <span className="text-[#b77918]">{roleLabels[selectedRole]}</span>
                                </h3>
                                <p className="text-xs text-[var(--admin-text-soft)] mt-1">
                                    Seules les actions qu'une fonction de l'application exige figurent ici : les retirer
                                    ou les attribuer change aussitôt ce que ce rôle peut faire. Le reste de l'accès d'un
                                    rôle est fixé par l'application.
                                </p>
                            </div>
                            {customizedRoles[selectedRole] ? (
                                <button
                                    type="button"
                                    onClick={resetRole}
                                    className="shrink-0 rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel)] px-3 py-2 text-xs font-semibold text-[var(--admin-text)] transition hover:bg-[#f7efe2]"
                                >
                                    Rétablir les droits d'origine
                                </button>
                            ) : null}
                        </div>

                        {customizedRoles[selectedRole] ? (
                            <p className="mb-6 rounded-2xl border border-[#e6d3b2] bg-[#fbf1db] p-3 text-xs text-[#7d571b]">
                                Les droits de ce rôle ont été modifiés par rapport à leur réglage d'origine.
                            </p>
                        ) : null}

                        {rolePermissions.length === 0 ? (
                            <p className="text-sm text-[var(--admin-text-soft)]">
                                Aucune action réglable pour ce rôle : son accès est fixé par l'application.
                            </p>
                        ) : null}

                        <div className="space-y-8">
                            {Object.keys(groupedPermissions).map((category) => (
                                <div key={category} className="space-y-3">
                                    <h4 className="text-xs font-bold uppercase tracking-widest text-[#b77918] border-b border-[var(--admin-border)] pb-1.5">
                                        {categoryLabels[category] || category}
                                    </h4>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        {groupedPermissions[category].map((perm) => {
                                            const hasPermission = rolesPermissions[selectedRole]?.includes(perm.name) ?? false;
                                            // Le serveur refuse ces deux cas ; l'écran les annonce avant le clic.
                                            const isProtected = hasPermission && (protectedRolePermissions[selectedRole]?.includes(perm.name) ?? false);
                                            // Action détenue par un rôle auquel elle est réservée ailleurs : elle
                                            // se retire, mais ne se réattribue pas.
                                            const reservedFor = reservedRolePermissions[perm.name];
                                            const isReserved = !hasPermission && reservedFor !== undefined && !reservedFor.includes(selectedRole);

                                            return (
                                                <div
                                                    key={perm.id}
                                                    className={`flex items-start justify-between gap-4 p-4 rounded-2xl border border-[var(--admin-border)] transition ${
                                                        hasPermission ? 'bg-[#eef8f0]/40' : 'bg-[var(--admin-panel)]'
                                                    }`}
                                                >
                                                    <div className="min-w-0">
                                                        <span className="font-mono text-xs font-semibold text-[var(--admin-text)]">{perm.name}</span>
                                                        <p className="text-xs text-[var(--admin-text-soft)] mt-1">{perm.description || 'Aucune description fournie.'}</p>
                                                        {isProtected ? (
                                                            <p className="mt-1 text-[11px] font-semibold text-[#7d571b]">Indispensable à ce rôle : ne se retire pas.</p>
                                                        ) : null}
                                                        {isReserved ? (
                                                            <p className="mt-1 text-[11px] font-semibold text-[#7d571b]">Action réservée : ne s’attribue pas à ce rôle.</p>
                                                        ) : null}
                                                    </div>

                                                    <label className="relative inline-flex items-center cursor-pointer shrink-0 mt-0.5">
                                                        <input
                                                            type="checkbox"
                                                            disabled={toggling === perm.name || isProtected || isReserved}
                                                            aria-label={perm.name}
                                                            checked={hasPermission}
                                                            onChange={() => handleTogglePermission(perm.name, hasPermission)}
                                                            className="sr-only peer"
                                                        />
                                                        <div className="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#2f9a65] disabled:opacity-50"></div>
                                                    </label>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
