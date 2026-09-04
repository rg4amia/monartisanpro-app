import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

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
}

interface RolesPermissionsPanelProps {
    allPermissions: Permission[];
    rolesPermissions: Record<string, string[]>;
    /** Catalogue des capacités fines du backoffice : groupe => { nom: description } (Chantier C6 / P2-10). */
    adminCapabilityCatalog: Record<string, Record<string, string>>;
    admins: AdminAccount[];
}

const roleLabels: Record<string, string> = {
    client: 'Client',
    artisan: 'Artisan',
    fournisseur: 'Fournisseur',
    referent: 'Référent',
    admin: 'Administrateur (Accès Total)',
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
}: RolesPermissionsPanelProps) {
    const [selectedRole, setSelectedRole] = useState<string>('client');
    const [toggling, setToggling] = useState<string | null>(null);

    const [selectedAdminId, setSelectedAdminId] = useState<number | null>(admins[0]?.id ?? null);
    const [savingAdmin, setSavingAdmin] = useState(false);

    const selectedAdmin = useMemo(
        () => admins.find((a) => a.id === selectedAdminId) ?? null,
        [admins, selectedAdminId],
    );
    const adminHasFullAccess = selectedAdmin?.capabilities.includes('*') ?? false;
    const adminIsProtected = selectedAdmin?.protected ?? false;
    const adminLocked = savingAdmin || adminIsProtected;
    const allCatalogCapabilities = Object.values(adminCapabilityCatalog).flatMap((g) => Object.keys(g));

    // Group permissions by category
    const groupedPermissions = allPermissions.reduce((acc, perm) => {
        const cat = perm.category || 'other';
        if (!acc[cat]) acc[cat] = [];
        acc[cat].push(perm);
        return acc;
    }, {} as Record<string, Permission[]>);

    const handleTogglePermission = (permissionName: string, hasPermission: boolean) => {
        if (selectedRole === 'admin') {
            alert("Le rôle Administrateur possède toutes les permissions par défaut et ne peut être modifié.");
            return;
        }

        const action = hasPermission ? 'revoke' : 'assign';
        const url = `/api/v1/admin/roles-permissions/${action}`;

        setToggling(permissionName);

        router.post(url, {
            role: selectedRole,
            permission: permissionName,
        }, {
            preserveScroll: true,
            onFinish: () => {
                setToggling(null);
            },
            onError: () => {
                alert("Une erreur est survenue lors de la mise à jour des droits.");
            }
        });
    };

    const submitAdminCapabilities = (capabilities: string[]) => {
        if (!selectedAdmin || adminIsProtected) return;
        setSavingAdmin(true);
        router.post(`/admin/admins/${selectedAdmin.id}/permissions`, { capabilities }, {
            preserveScroll: true,
            onFinish: () => setSavingAdmin(false),
            onError: () => alert('Une erreur est survenue lors de la mise à jour des droits admin.'),
        });
    };

    const toggleAdminCapability = (capability: string) => {
        if (!selectedAdmin) return;
        // Point de départ : le catalogue complet si l'admin a l'accès total, sinon
        // ses capacités explicites (en écartant tout marqueur `*`).
        const current = adminHasFullAccess
            ? allCatalogCapabilities
            : selectedAdmin.capabilities.filter((c) => c !== '*');
        const next = current.includes(capability)
            ? current.filter((c) => c !== capability)
            : [...current, capability];
        // Un périmètre vide serait réinterprété comme « accès total » : on garde au
        // minimum la lecture des comptes pour rester cohérent avec l'affichage.
        submitAdminCapabilities(next.length > 0 ? next : ['admin.users.view']);
    };

    const setFullAccess = (full: boolean) => {
        if (full) {
            submitAdminCapabilities(['admin.full-access']);
        } else {
            // Retirer l'accès total → périmètre minimal explicite (lecture des comptes).
            submitAdminCapabilities(['admin.users.view']);
        }
    };

    const roles = ['client', 'artisan', 'fournisseur', 'referent', 'livreur', 'admin'];

    const adminHas = (capability: string) =>
        adminHasFullAccess || (selectedAdmin?.capabilities.includes(capability) ?? false);

    return (
        <div className="space-y-8">
            {/* ── Droits fins des administrateurs (Chantier C6 / P2-10) ── */}
            <div className="rounded-[28px] border border-[var(--admin-border)] bg-white/60 p-6">
                <div className="border-b border-[var(--admin-border)] pb-4 mb-6">
                    <h3 className="text-xl font-bold text-[var(--admin-text)]">Droits des administrateurs</h3>
                    <p className="text-xs text-[var(--admin-text-soft)] mt-1">
                        Chaque compte admin peut être restreint à un périmètre précis. Un admin sans aucune capacité
                        cochée — ou avec « Accès total » — dispose de l'ensemble des droits du backoffice.
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

                                    <label className="flex items-center justify-between gap-4 p-4 rounded-2xl border border-[var(--admin-border)] bg-white/50">
                                        <div className="min-w-0">
                                            <span className="font-semibold text-sm text-[var(--admin-text)]">Accès total</span>
                                            <p className="text-xs text-[var(--admin-text-soft)] mt-1">
                                                Super administrateur : toutes les capacités, présentes et futures.
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
                                                            adminHas(name) ? 'bg-[#eef8f0]/40' : 'bg-white/40'
                                                        } ${adminHasFullAccess ? 'opacity-60' : ''}`}
                                                    >
                                                        <div className="min-w-0">
                                                            <span className="font-mono text-xs font-semibold text-[var(--admin-text)]">{name}</span>
                                                            <p className="text-xs text-[var(--admin-text-soft)] mt-1">{description}</p>
                                                        </div>
                                                        <input
                                                            type="checkbox"
                                                            className="h-5 w-5 shrink-0 mt-0.5"
                                                            checked={adminHas(name)}
                                                            disabled={adminLocked || adminHasFullAccess}
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
                    <div className="rounded-[28px] border border-[var(--admin-border)] bg-white/60 p-4">
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
                    <div className="rounded-[28px] border border-[var(--admin-border)] bg-white/60 p-6">
                        <div className="flex items-center justify-between border-b border-[var(--admin-border)] pb-4 mb-6">
                            <div>
                                <h3 className="text-xl font-bold text-[var(--admin-text)]">
                                    Droits & Actions du rôle : <span className="text-[#b77918]">{roleLabels[selectedRole]}</span>
                                </h3>
                                <p className="text-xs text-[var(--admin-text-soft)] mt-1">
                                    {selectedRole === 'admin'
                                        ? "L'administrateur a un accès total ; ses droits fins se règlent dans « Droits des administrateurs » ci-dessus."
                                        : "Activez ou désactivez les permissions individuelles ci-dessous."
                                    }
                                </p>
                            </div>
                        </div>

                        <div className="space-y-8">
                            {Object.keys(groupedPermissions).map((category) => (
                                <div key={category} className="space-y-3">
                                    <h4 className="text-xs font-bold uppercase tracking-widest text-[#b77918] border-b border-[var(--admin-border)] pb-1.5">
                                        {categoryLabels[category] || category}
                                    </h4>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        {groupedPermissions[category].map((perm) => {
                                            const hasPermission = selectedRole === 'admin' ||
                                                (rolesPermissions[selectedRole] && rolesPermissions[selectedRole].includes(perm.name));

                                            return (
                                                <div
                                                    key={perm.id}
                                                    className={`flex items-start justify-between gap-4 p-4 rounded-2xl border border-[var(--admin-border)] transition ${
                                                        hasPermission ? 'bg-[#eef8f0]/40' : 'bg-white/40'
                                                    }`}
                                                >
                                                    <div className="min-w-0">
                                                        <span className="font-mono text-xs font-semibold text-[var(--admin-text)]">{perm.name}</span>
                                                        <p className="text-xs text-[var(--admin-text-soft)] mt-1">{perm.description || 'Aucune description fournie.'}</p>
                                                    </div>

                                                    <label className="relative inline-flex items-center cursor-pointer shrink-0 mt-0.5">
                                                        <input
                                                            type="checkbox"
                                                            disabled={selectedRole === 'admin' || toggling === perm.name}
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
