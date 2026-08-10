import { router } from '@inertiajs/react';
import { useState } from 'react';

interface Permission {
    id: number;
    name: string;
    description: string | null;
    category: string | null;
}

interface RolesPermissionsPanelProps {
    allPermissions: Permission[];
    rolesPermissions: Record<string, string[]>;
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

export default function RolesPermissionsPanel({
    allPermissions = [],
    rolesPermissions = {},
}: RolesPermissionsPanelProps) {
    const [selectedRole, setSelectedRole] = useState<string>('client');
    const [toggling, setToggling] = useState<string | null>(null);

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

    const roles = ['client', 'artisan', 'fournisseur', 'referent', 'admin'];

    return (
        <div className="grid gap-6 xl:grid-cols-4">
            {/* Roles Selector Side List */}
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

            {/* Permissions Matrix Main Content */}
            <div className="xl:col-span-3 space-y-6">
                <div className="rounded-[28px] border border-[var(--admin-border)] bg-white/60 p-6">
                    <div className="flex items-center justify-between border-b border-[var(--admin-border)] pb-4 mb-6">
                        <div>
                            <h3 className="text-xl font-bold text-[var(--admin-text)]">
                                Droits & Actions du rôle : <span className="text-[#b77918]">{roleLabels[selectedRole]}</span>
                            </h3>
                            <p className="text-xs text-[var(--admin-text-soft)] mt-1">
                                {selectedRole === 'admin' 
                                    ? "L'administrateur a un accès total et permanent à toutes les fonctionnalités."
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
    );
}
