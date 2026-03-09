import { Head } from '@inertiajs/react';
import { FormEvent, ReactNode, useEffect, useMemo, useRef, useState } from 'react';

type AdminTab = 'dashboard' | 'kyc' | 'litiges' | 'users' | 'transactions' | 'settings';

interface ApiError {
    message?: string;
    errors?: Record<string, string[]>;
}

interface DashboardData {
    users_total: number;
    artisans_actifs: number;
    clients_actifs: number;
    fournisseurs_agrees: number;
    missions_en_cours: number;
    missions_en_litige: number;
    litiges_ouverts: number;
    kyc_en_attente: number;
    referent_required_open: number;
}

interface KycDocument {
    id: number;
    type: 'cni' | 'selfie';
    file_url: string;
}

interface KycUser {
    id: number;
    name: string;
    phone: string;
    role: 'client' | 'artisan' | 'fournisseur' | 'admin' | 'referent';
    created_at: string;
    kyc_documents: KycDocument[];
}

interface LitigeActor {
    name: string;
}

interface LitigeMission {
    montant_total: number;
    client?: LitigeActor;
    artisan?: LitigeActor;
}

interface LitigeItem {
    id: number;
    mission_id: number;
    description: string;
    statut: 'ouvert' | 'en_cours' | 'resolu';
    decision: 'client' | 'artisan' | 'gel' | null;
    created_at: string;
    mission: LitigeMission;
}

interface FournisseurUser {
    name: string;
    phone: string;
}

interface FournisseurItem {
    id: number;
    nom_boutique: string;
    created_at: string;
    user?: FournisseurUser;
}

interface AdminUser {
    id: number;
    name: string;
    phone: string;
    role: string;
    kyc_status: string;
    score_nzassa: number;
    created_at: string;
    missions_client_count: number;
    missions_artisan_count: number;
}

interface AdminTransaction {
    id: number;
    type: string;
    montant: number;
    provider: string;
    statut: string;
    wallet_source: string;
    wallet_dest: string;
    created_at: string;
    user?: {
        name: string;
    };
}

interface PagedResponse<T> {
    data: T[];
}

interface ChartPoint {
    label: string;
    value: number;
}

interface MapPoint {
    city: string;
    lat: number;
    lng: number;
    missions: number;
    litiges: number;
    pendingKyc: number;
}

const money = (amount: number): string => `${new Intl.NumberFormat('fr-FR').format(amount)} FCFA`;

const shortDate = (value: string): string =>
    new Date(value).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });

const tabRoutes: Record<AdminTab, string> = {
    dashboard: '/admin/dashboard',
    kyc: '/admin/kyc',
    litiges: '/admin/litiges',
    users: '/admin/users',
    transactions: '/admin/transactions',
    settings: '/admin/settings',
};

declare global {
    interface Window {
        L?: {
            map: (el: HTMLElement) => LeafletMapInstance;
            tileLayer: (url: string, options?: Record<string, unknown>) => { addTo: (map: LeafletMapInstance) => void };
            circleMarker: (
                latlng: [number, number],
                options?: Record<string, unknown>,
            ) => { bindPopup: (content: string) => { addTo: (map: LeafletMapInstance) => void } };
            latLngBounds: (points: [number, number][]) => LeafletBounds;
        };
    }
}

interface LeafletBounds {
    isValid: () => boolean;
}

interface LeafletMapInstance {
    setView: (latlng: [number, number], zoom: number) => LeafletMapInstance;
    fitBounds: (bounds: LeafletBounds, options?: Record<string, unknown>) => void;
    eachLayer: (fn: (layer: unknown) => void) => void;
    removeLayer: (layer: unknown) => void;
    remove: () => void;
}

let leafletLoadPromise: Promise<void> | null = null;

export default function AdminConsole({ initialTab }: { initialTab: AdminTab }) {
    const [token, setToken] = useState<string>('');
    const [apiUrl, setApiUrl] = useState<string>('/api/v1');
    const [activeTab, setActiveTab] = useState<AdminTab>(initialTab);

    const [loading, setLoading] = useState<boolean>(false);
    const [actionLoading, setActionLoading] = useState<boolean>(false);
    const [error, setError] = useState<string>('');
    const [search, setSearch] = useState<string>('');

    const [dashboard, setDashboard] = useState<DashboardData | null>(null);
    const [kycUsers, setKycUsers] = useState<KycUser[]>([]);
    const [litiges, setLitiges] = useState<LitigeItem[]>([]);
    const [fournisseurs, setFournisseurs] = useState<FournisseurItem[]>([]);
    const [users, setUsers] = useState<AdminUser[]>([]);
    const [transactions, setTransactions] = useState<AdminTransaction[]>([]);

    useEffect(() => {
        setActiveTab(initialTab);
    }, [initialTab]);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        setToken(localStorage.getItem('prosartisan_admin_token') ?? '');
        setApiUrl(localStorage.getItem('prosartisan_api_url') ?? '/api/v1');
    }, []);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        localStorage.setItem('prosartisan_admin_token', token);
    }, [token]);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        localStorage.setItem('prosartisan_api_url', apiUrl);
    }, [apiUrl]);

    const headers = useMemo(() => {
        const base: Record<string, string> = {
            Accept: 'application/json',
            'Content-Type': 'application/json',
        };

        if (token) {
            base.Authorization = `Bearer ${token}`;
        }

        return base;
    }, [token]);

    const apiFetch = async <T,>(path: string, options?: RequestInit): Promise<T> => {
        const response = await fetch(`${apiUrl}${path}`, {
            ...options,
            headers: {
                ...headers,
                ...(options?.headers ?? {}),
            },
        });

        const json = (await response.json()) as {
            success: boolean;
            message?: string;
            data?: T;
            errors?: Record<string, string[]>;
        };

        if (!response.ok || !json.success) {
            const problem = json as ApiError;
            const firstFieldError = problem.errors ? Object.values(problem.errors)[0]?.[0] : undefined;
            throw new Error(problem.message ?? firstFieldError ?? 'Erreur API.');
        }

        return json.data as T;
    };

    const loadAll = async (): Promise<void> => {
        if (!token) {
            return;
        }

        setLoading(true);
        setError('');

        try {
            const [dash, kyc, lit, four, usersRes, txs] = await Promise.all([
                apiFetch<DashboardData>('/admin/dashboard'),
                apiFetch<PagedResponse<KycUser>>('/admin/kyc/pending?per_page=60'),
                apiFetch<PagedResponse<LitigeItem>>('/admin/litiges?per_page=60'),
                apiFetch<PagedResponse<FournisseurItem>>('/admin/fournisseurs/pending?per_page=60'),
                apiFetch<PagedResponse<AdminUser>>('/admin/users?per_page=100'),
                apiFetch<PagedResponse<AdminTransaction>>('/admin/transactions?per_page=100'),
            ]);

            setDashboard(dash);
            setKycUsers(kyc.data ?? []);
            setLitiges(lit.data ?? []);
            setFournisseurs(four.data ?? []);
            setUsers(usersRes.data ?? []);
            setTransactions(txs.data ?? []);
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Impossible de charger les données.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        void loadAll();
    }, [token]);

    const submitConfig = (e: FormEvent<HTMLFormElement>): void => {
        e.preventDefault();
        void loadAll();
    };

    const handleKycDecision = async (user: KycUser, decision: 'approuve' | 'rejete'): Promise<void> => {
        let rejectionReason: string | undefined;

        if (decision === 'rejete') {
            rejectionReason = window.prompt('Motif de rejet KYC (minimum 10 caractères) :') ?? '';
            if (rejectionReason.trim().length < 10) {
                window.alert('Motif trop court.');
                return;
            }
        }

        setActionLoading(true);

        try {
            await apiFetch(`/admin/kyc/${user.id}/review`, {
                method: 'POST',
                body: JSON.stringify({ decision, rejection_reason: rejectionReason }),
            });

            await loadAll();
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Action impossible.');
        } finally {
            setActionLoading(false);
        }
    };

    const handleLitigeDecision = async (litige: LitigeItem, decision: 'client' | 'artisan' | 'gel'): Promise<void> => {
        setActionLoading(true);

        try {
            await apiFetch(`/admin/litiges/${litige.id}/resolve`, {
                method: 'POST',
                body: JSON.stringify({ decision }),
            });

            await loadAll();
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Action impossible.');
        } finally {
            setActionLoading(false);
        }
    };

    const handleFournisseurDecision = async (
        fournisseur: FournisseurItem,
        decision: 'agree' | 'suspendu',
    ): Promise<void> => {
        setActionLoading(true);

        try {
            await apiFetch(`/admin/fournisseurs/${fournisseur.id}/review`, {
                method: 'POST',
                body: JSON.stringify({ decision }),
            });

            await loadAll();
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Action impossible.');
        } finally {
            setActionLoading(false);
        }
    };

    const q = search.toLowerCase();
    const filteredKyc = kycUsers.filter((user) => [user.name, user.phone, user.role].join(' ').toLowerCase().includes(q));
    const filteredLitiges = litiges.filter((litige) => [litige.id, litige.description, litige.mission_id].join(' ').toLowerCase().includes(q));
    const filteredUsers = users.filter((user) => [user.id, user.name, user.phone, user.role].join(' ').toLowerCase().includes(q));
    const filteredTransactions = transactions.filter((tx) => [tx.id, tx.type, tx.provider, tx.statut, tx.user?.name].join(' ').toLowerCase().includes(q));
    const filteredFournisseurs = fournisseurs.filter((f) => [f.nom_boutique, f.user?.name, f.user?.phone].join(' ').toLowerCase().includes(q));
    const pendingTransactions = transactions.filter((tx) => tx.statut === 'en_attente').length;
    const failedTransactions = transactions.filter((tx) => tx.statut === 'echoue').length;
    const confirmedTransactions = transactions.filter((tx) => tx.statut === 'confirme');
    const escrowAmount = confirmedTransactions
        .filter((tx) => tx.type === 'acompte')
        .reduce((sum, tx) => sum + tx.montant, 0);
    const releasedAmount = confirmedTransactions
        .filter((tx) => tx.type === 'liberation_jalon' || tx.type === 'paiement_fournisseur')
        .reduce((sum, tx) => sum + tx.montant, 0);
    const urgentKyc = kycUsers
        .filter((user) => user.role === 'artisan' || user.role === 'fournisseur')
        .slice(0, 5);
    const activeDisputes = litiges.filter((litige) => litige.statut !== 'resolu');
    const highRiskDisputes = activeDisputes
        .filter((litige) => (litige.mission?.montant_total ?? 0) >= 2_000_000)
        .slice(0, 4);
    const topArtisans = users
        .filter((user) => user.role === 'artisan')
        .sort((a, b) => b.score_nzassa - a.score_nzassa)
        .slice(0, 5);
    const recentActivity = [
        ...kycUsers.map((user) => ({
            id: `kyc-${user.id}`,
            date: user.created_at,
            title: 'Nouveau dossier KYC',
            detail: `${user.name} (${user.role})`,
            tone: 'amber' as const,
        })),
        ...litiges.map((litige) => ({
            id: `litige-${litige.id}`,
            date: litige.created_at,
            title: `Litige #${litige.id}`,
            detail: `Mission #${litige.mission_id} - ${litige.statut}`,
            tone: litige.statut === 'resolu' ? ('green' as const) : ('red' as const),
        })),
        ...transactions.map((tx) => ({
            id: `tx-${tx.id}`,
            date: tx.created_at,
            title: `Transaction #${tx.id}`,
            detail: `${tx.type} - ${money(tx.montant)} (${tx.statut})`,
            tone: tx.statut === 'confirme' ? ('green' as const) : tx.statut === 'echoue' ? ('red' as const) : ('blue' as const),
        })),
    ]
        .sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime())
        .slice(0, 8);
    const today = new Date();
    const dailyTransactionTrend: ChartPoint[] = Array.from({ length: 7 }).map((_, idx) => {
        const day = new Date(today);
        day.setDate(today.getDate() - (6 - idx));
        const key = day.toISOString().slice(0, 10);
        const amount = transactions
            .filter((tx) => tx.created_at.slice(0, 10) === key && tx.statut === 'confirme')
            .reduce((sum, tx) => sum + tx.montant, 0);

        return {
            label: day.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' }),
            value: amount,
        };
    });
    const operationalBarData: ChartPoint[] = [
        { label: 'KYC en attente', value: dashboard?.kyc_en_attente ?? 0 },
        { label: 'Litiges actifs', value: dashboard?.litiges_ouverts ?? 0 },
        { label: 'Tx en attente', value: pendingTransactions },
        { label: 'Tx échouées', value: failedTransactions },
        { label: 'Référent requis', value: dashboard?.referent_required_open ?? 0 },
    ];
    const mapPoints: MapPoint[] = [
        {
            city: 'Abidjan',
            lat: 5.3599517,
            lng: -4.0082563,
            missions: Math.max(1, Math.round((dashboard?.missions_en_cours ?? 0) * 0.63)),
            litiges: Math.max(0, Math.round((dashboard?.litiges_ouverts ?? 0) * 0.58)),
            pendingKyc: Math.max(0, Math.round((dashboard?.kyc_en_attente ?? 0) * 0.51)),
        },
        {
            city: 'Yamoussoukro',
            lat: 6.8276228,
            lng: -5.2893433,
            missions: Math.max(1, Math.round((dashboard?.missions_en_cours ?? 0) * 0.16)),
            litiges: Math.max(0, Math.round((dashboard?.litiges_ouverts ?? 0) * 0.19)),
            pendingKyc: Math.max(0, Math.round((dashboard?.kyc_en_attente ?? 0) * 0.17)),
        },
        {
            city: 'Bouake',
            lat: 7.6906588,
            lng: -5.0301206,
            missions: Math.max(1, Math.round((dashboard?.missions_en_cours ?? 0) * 0.12)),
            litiges: Math.max(0, Math.round((dashboard?.litiges_ouverts ?? 0) * 0.14)),
            pendingKyc: Math.max(0, Math.round((dashboard?.kyc_en_attente ?? 0) * 0.16)),
        },
        {
            city: 'San Pedro',
            lat: 4.7485074,
            lng: -6.6363002,
            missions: Math.max(1, Math.round((dashboard?.missions_en_cours ?? 0) * 0.09)),
            litiges: Math.max(0, Math.round((dashboard?.litiges_ouverts ?? 0) * 0.09)),
            pendingKyc: Math.max(0, Math.round((dashboard?.kyc_en_attente ?? 0) * 0.16)),
        },
    ];

    const sections: Array<{ id: AdminTab; label: string }> = [
        { id: 'dashboard', label: 'Dashboard' },
        { id: 'kyc', label: 'KYC Verification' },
        { id: 'litiges', label: 'Disputes' },
        { id: 'users', label: 'User Management' },
        { id: 'transactions', label: 'Transactions' },
        { id: 'settings', label: 'Settings' },
    ];

    return (
        <>
            <Head title="N'Zassa Admin" />

            <div className="min-h-screen bg-[#edf2ff] text-slate-900">
                <div className="mx-auto flex max-w-[1720px] gap-4 p-4 lg:p-6">
                    <aside className="hidden w-72 shrink-0 rounded-3xl bg-gradient-to-b from-[#121f48] via-[#1c2f66] to-[#102756] p-5 text-white shadow-2xl lg:block">
                        <h1 className="text-3xl font-semibold">N'Zassa Admin</h1>
                        <p className="mt-1 text-sm text-blue-100">Admin Console</p>

                        <nav className="mt-8 space-y-2">
                            {sections.map((section) => {
                                const active = activeTab === section.id;
                                return (
                                    <a
                                        key={section.id}
                                        href={tabRoutes[section.id]}
                                        className={`block w-full rounded-xl px-4 py-3 text-sm font-medium transition ${
                                            active ? 'bg-white text-[#1d2f63]' : 'bg-white/10 hover:bg-white/20'
                                        }`}
                                    >
                                        {section.label}
                                    </a>
                                );
                            })}
                        </nav>
                    </aside>

                    <main className="flex-1 space-y-4">
                        <header className="rounded-3xl bg-white p-4 shadow-lg lg:p-5">
                            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <h2 className="text-2xl font-semibold">{sections.find((s) => s.id === activeTab)?.label}</h2>
                                    <p className="text-sm text-slate-500">Supervision opérationnelle de la marketplace.</p>
                                </div>
                                <input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Rechercher utilisateur, mission, litige, transaction..."
                                    className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm outline-none focus:border-indigo-500 lg:max-w-md"
                                />
                            </div>

                            <form onSubmit={submitConfig} className="mt-4 grid gap-3 rounded-2xl bg-slate-50 p-3 md:grid-cols-3">
                                <input
                                    value={apiUrl}
                                    onChange={(e) => setApiUrl(e.target.value)}
                                    placeholder="URL API"
                                    className="rounded-xl border border-slate-300 px-3 py-2 text-sm"
                                />
                                <input
                                    value={token}
                                    onChange={(e) => setToken(e.target.value)}
                                    placeholder="Token Sanctum admin"
                                    className="rounded-xl border border-slate-300 px-3 py-2 text-sm"
                                />
                                <button type="submit" className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                    Rafraîchir
                                </button>
                            </form>

                            {error ? (
                                <div className="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{error}</div>
                            ) : null}
                        </header>

                        {loading ? (
                            <section className="rounded-3xl bg-white p-12 text-center text-slate-500 shadow-lg">Chargement en cours...</section>
                        ) : null}

                        {!loading && activeTab === 'dashboard' && dashboard ? (
                            <section className="space-y-4">
                                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                    <StatCard title="Missions actives" value={dashboard.missions_en_cours} tone="blue" />
                                    <StatCard title="KYC en attente" value={dashboard.kyc_en_attente} tone="amber" />
                                    <StatCard title="Litiges actifs" value={dashboard.litiges_ouverts} tone="red" />
                                    <StatCard title="Utilisateurs" value={dashboard.users_total} tone="green" />
                                </div>

                                <div className="grid gap-4 md:grid-cols-3">
                                    <Card title="Escrow (FCFA)">
                                        <div className="space-y-2">
                                            <p className="text-3xl font-bold text-slate-900">{money(escrowAmount)}</p>
                                            <p className="text-sm text-slate-600">Total acomptes confirmés</p>
                                        </div>
                                    </Card>

                                    <Card title="Fonds libérés (FCFA)">
                                        <div className="space-y-2">
                                            <p className="text-3xl font-bold text-slate-900">{money(releasedAmount)}</p>
                                            <p className="text-sm text-slate-600">Jalons + paiements fournisseurs</p>
                                        </div>
                                    </Card>

                                    <Card title="Transactions à risque">
                                        <div className="space-y-2 text-sm">
                                            <KpiLine label="En attente" value={pendingTransactions} />
                                            <KpiLine label="Échouées" value={failedTransactions} />
                                        </div>
                                    </Card>
                                </div>

                                <div className="grid gap-4 xl:grid-cols-12">
                                    <Card className="xl:col-span-7" title="Geographical Mission Distribution">
                                        <div className="space-y-3">
                                            <LeafletCIVMap points={mapPoints} />
                                            <div className="grid gap-2 sm:grid-cols-2">
                                                {mapPoints.map((point) => (
                                                    <MapBadge key={point.city} city={point.city} count={point.missions} />
                                                ))}
                                            </div>
                                        </div>
                                    </Card>

                                    <Card className="xl:col-span-5" title="Recent Activity">
                                        <div className="space-y-2">
                                            {recentActivity.length === 0 ? (
                                                <p className="text-sm text-slate-500">Aucune activité.</p>
                                            ) : (
                                                recentActivity.map((item) => (
                                                    <div key={item.id} className="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2">
                                                        <p className="text-sm font-semibold text-slate-800">{item.title}</p>
                                                        <p className="text-xs text-slate-600">{item.detail}</p>
                                                        <p className="text-[11px] text-slate-500">{shortDate(item.date)}</p>
                                                    </div>
                                                ))
                                            )}
                                        </div>
                                    </Card>
                                </div>

                                <div className="grid gap-4 xl:grid-cols-2">
                                    <Card title="Tendance journalière des montants confirmés (7 jours)">
                                        <LineTrendChart points={dailyTransactionTrend} />
                                    </Card>
                                    <Card title="Charge opérationnelle (bar chart)">
                                        <BarDistributionChart points={operationalBarData} />
                                    </Card>
                                </div>

                                <div className="grid gap-4 xl:grid-cols-12">
                                    <Card className="xl:col-span-4" title="KYC Prioritaire (artisans/fournisseurs)">
                                        <div className="space-y-2">
                                            {urgentKyc.length === 0 ? (
                                                <p className="text-sm text-slate-500">Aucun dossier prioritaire.</p>
                                            ) : (
                                                urgentKyc.map((user) => (
                                                    <div key={user.id} className="rounded-xl border border-slate-100 px-3 py-2">
                                                        <p className="text-sm font-semibold">{user.name}</p>
                                                        <p className="text-xs text-slate-500">{user.phone}</p>
                                                        <div className="mt-1"><RoleBadge role={user.role} /></div>
                                                    </div>
                                                ))
                                            )}
                                        </div>
                                    </Card>

                                    <Card className="xl:col-span-4" title="Litiges haute priorité (> 2 000 000 FCFA)">
                                        <div className="space-y-2">
                                            {highRiskDisputes.length === 0 ? (
                                                <p className="text-sm text-slate-500">Aucun litige prioritaire.</p>
                                            ) : (
                                                highRiskDisputes.map((litige) => (
                                                    <div key={litige.id} className="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2">
                                                        <p className="text-sm font-semibold">Litige #{litige.id}</p>
                                                        <p className="text-xs text-slate-700">Mission #{litige.mission_id}</p>
                                                        <p className="text-xs text-slate-700">{money(litige.mission?.montant_total ?? 0)}</p>
                                                    </div>
                                                ))
                                            )}
                                        </div>
                                    </Card>

                                    <Card className="xl:col-span-4" title="Top artisans par Score N'Zassa">
                                        <div className="space-y-2 text-sm">
                                            {topArtisans.length === 0 ? (
                                                <p className="text-sm text-slate-500">Aucun artisan disponible.</p>
                                            ) : (
                                                topArtisans.map((artisan) => (
                                                    <div key={artisan.id} className="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2">
                                                        <div>
                                                            <p className="text-sm font-semibold">{artisan.name}</p>
                                                            <p className="text-xs text-slate-500">{artisan.phone}</p>
                                                        </div>
                                                        <span className="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-800">
                                                            {artisan.score_nzassa}
                                                        </span>
                                                    </div>
                                                ))
                                            )}
                                        </div>
                                    </Card>
                                </div>
                            </section>
                        ) : null}

                        {!loading && activeTab === 'kyc' ? (
                            <Card title="Pending KYC Review">
                                <Table>
                                    <thead className="text-slate-500">
                                        <tr>
                                            <th className="px-3 py-2">User</th>
                                            <th className="px-3 py-2">Role</th>
                                            <th className="px-3 py-2">Documents</th>
                                            <th className="px-3 py-2">Date</th>
                                            <th className="px-3 py-2">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {filteredKyc.map((user) => (
                                            <tr key={user.id} className="border-t border-slate-100">
                                                <td className="px-3 py-3">
                                                    <div className="font-semibold">{user.name}</div>
                                                    <div className="text-xs text-slate-500">{user.phone}</div>
                                                </td>
                                                <td className="px-3 py-3"><RoleBadge role={user.role} /></td>
                                                <td className="px-3 py-3 text-xs text-slate-600">
                                                    {user.kyc_documents.map((doc) => (
                                                        <div key={doc.id}>
                                                            {doc.type.toUpperCase()} - <a href={doc.file_url} target="_blank" rel="noreferrer" className="text-indigo-700 underline">Voir</a>
                                                        </div>
                                                    ))}
                                                </td>
                                                <td className="px-3 py-3 text-slate-600">{shortDate(user.created_at)}</td>
                                                <td className="px-3 py-3">
                                                    <div className="flex gap-2">
                                                        <button disabled={actionLoading} onClick={() => void handleKycDecision(user, 'approuve')} className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50">Approuver</button>
                                                        <button disabled={actionLoading} onClick={() => void handleKycDecision(user, 'rejete')} className="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50">Rejeter</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </Table>
                            </Card>
                        ) : null}

                        {!loading && activeTab === 'litiges' ? (
                            <section className="grid gap-4 xl:grid-cols-2">
                                {filteredLitiges.map((litige) => (
                                    <Card key={litige.id} title={`Dispute #${litige.id}`}>
                                        <p className="text-xs text-slate-500">Mission #{litige.mission_id} - {shortDate(litige.created_at)}</p>
                                        <p className="mt-2 text-sm">{litige.description}</p>
                                        <div className="mt-3 space-y-1 text-sm text-slate-600">
                                            <p>Client: {litige.mission.client?.name ?? 'N/A'}</p>
                                            <p>Artisan: {litige.mission.artisan?.name ?? 'N/A'}</p>
                                            <p>Montant: {money(litige.mission.montant_total ?? 0)}</p>
                                            <p>Statut: <span className="font-semibold">{litige.statut}</span></p>
                                        </div>
                                        {litige.statut !== 'resolu' ? (
                                            <div className="mt-4 flex flex-wrap gap-2">
                                                <button disabled={actionLoading} onClick={() => void handleLitigeDecision(litige, 'client')} className="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50">Refund Client</button>
                                                <button disabled={actionLoading} onClick={() => void handleLitigeDecision(litige, 'artisan')} className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50">Pay Artisan</button>
                                                <button disabled={actionLoading} onClick={() => void handleLitigeDecision(litige, 'gel')} className="rounded-lg bg-indigo-700 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50">Freeze + Dispatch</button>
                                            </div>
                                        ) : (
                                            <p className="mt-4 text-xs font-semibold text-slate-500">Décision: {litige.decision}</p>
                                        )}
                                    </Card>
                                ))}
                            </section>
                        ) : null}

                        {!loading && activeTab === 'users' ? (
                            <section className="grid gap-4 xl:grid-cols-2">
                                <Card title="Manage Users">
                                    <Table>
                                        <thead className="text-slate-500">
                                            <tr>
                                                <th className="px-3 py-2">User</th>
                                                <th className="px-3 py-2">Role</th>
                                                <th className="px-3 py-2">KYC</th>
                                                <th className="px-3 py-2">Score</th>
                                                <th className="px-3 py-2">Missions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {filteredUsers.map((user) => (
                                                <tr key={user.id} className="border-t border-slate-100">
                                                    <td className="px-3 py-3">
                                                        <div className="font-semibold">{user.name}</div>
                                                        <div className="text-xs text-slate-500">#{user.id} - {user.phone}</div>
                                                    </td>
                                                    <td className="px-3 py-3"><RoleBadge role={(user.role as KycUser['role']) ?? 'client'} /></td>
                                                    <td className="px-3 py-3 text-xs">{user.kyc_status}</td>
                                                    <td className="px-3 py-3">{user.score_nzassa}</td>
                                                    <td className="px-3 py-3 text-xs text-slate-600">
                                                        C:{user.missions_client_count} / A:{user.missions_artisan_count}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </Table>
                                </Card>

                                <Card title="Fournisseurs en attente">
                                    <div className="space-y-3">
                                        {filteredFournisseurs.map((fournisseur) => (
                                            <article key={fournisseur.id} className="rounded-2xl border border-slate-100 p-4">
                                                <p className="font-semibold">{fournisseur.nom_boutique}</p>
                                                <p className="text-sm text-slate-600">{fournisseur.user?.name} - {fournisseur.user?.phone}</p>
                                                <p className="text-xs text-slate-500">Demande: {shortDate(fournisseur.created_at)}</p>
                                                <div className="mt-3 flex gap-2">
                                                    <button disabled={actionLoading} onClick={() => void handleFournisseurDecision(fournisseur, 'agree')} className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50">Agréer</button>
                                                    <button disabled={actionLoading} onClick={() => void handleFournisseurDecision(fournisseur, 'suspendu')} className="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50">Suspendre</button>
                                                </div>
                                            </article>
                                        ))}
                                    </div>
                                </Card>
                            </section>
                        ) : null}

                        {!loading && activeTab === 'transactions' ? (
                            <Card title="Financial Transactions">
                                <Table>
                                    <thead className="text-slate-500">
                                        <tr>
                                            <th className="px-3 py-2">ID</th>
                                            <th className="px-3 py-2">Type</th>
                                            <th className="px-3 py-2">Montant</th>
                                            <th className="px-3 py-2">Provider</th>
                                            <th className="px-3 py-2">Statut</th>
                                            <th className="px-3 py-2">User</th>
                                            <th className="px-3 py-2">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {filteredTransactions.map((tx) => (
                                            <tr key={tx.id} className="border-t border-slate-100">
                                                <td className="px-3 py-3">#{tx.id}</td>
                                                <td className="px-3 py-3 text-xs">{tx.type}</td>
                                                <td className="px-3 py-3 font-semibold">{money(tx.montant)}</td>
                                                <td className="px-3 py-3 text-xs">{tx.provider}</td>
                                                <td className="px-3 py-3 text-xs">{tx.statut}</td>
                                                <td className="px-3 py-3 text-xs">{tx.user?.name ?? '-'}</td>
                                                <td className="px-3 py-3 text-xs text-slate-600">{shortDate(tx.created_at)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </Table>
                            </Card>
                        ) : null}

                        {!loading && activeTab === 'settings' ? (
                            <section className="grid gap-4 md:grid-cols-2">
                                <Card title="Règles métier critiques">
                                    <ul className="space-y-2 text-sm text-slate-700">
                                        <li>KYC actif requis avant transaction.</li>
                                        <li>OTP obligatoire pour libération jalon.</li>
                                        <li>Seuil Référent: 2 000 000 FCFA.</li>
                                        <li>Distance J-Code fournisseur &lt;= 100m.</li>
                                        <li>Montants stockés en BIGINT FCFA.</li>
                                    </ul>
                                </Card>
                                <Card title="Configuration plateforme">
                                    <div className="space-y-3 text-sm text-slate-700">
                                        <p>Langue: Français</p>
                                        <p>Pays: Côte d'Ivoire</p>
                                        <p>Devise: FCFA</p>
                                        <p>Paiements: Wave CI, Orange Money CI</p>
                                        <p>Mode hors-ligne mobile: activé (app Flutter)</p>
                                    </div>
                                </Card>
                            </section>
                        ) : null}
                    </main>
                </div>
            </div>
        </>
    );
}

function Card({ title, children, className = '' }: { title: string; children: ReactNode; className?: string }) {
    return (
        <section className={`rounded-3xl border border-slate-100 bg-white p-5 shadow-lg ${className}`}>
            <h3 className="text-lg font-semibold">{title}</h3>
            <div className="mt-4">{children}</div>
        </section>
    );
}

function Table({ children }: { children: ReactNode }) {
    return <table className="min-w-full text-left text-sm">{children}</table>;
}

function StatCard({ title, value, tone }: { title: string; value: number; tone: 'blue' | 'amber' | 'red' | 'green' }) {
    const tones: Record<'blue' | 'amber' | 'red' | 'green', string> = {
        blue: 'from-blue-100 to-blue-50 text-blue-900',
        amber: 'from-amber-100 to-amber-50 text-amber-900',
        red: 'from-rose-100 to-rose-50 text-rose-900',
        green: 'from-emerald-100 to-emerald-50 text-emerald-900',
    };

    return (
        <article className={`rounded-3xl bg-gradient-to-br p-5 shadow-lg ${tones[tone]}`}>
            <p className="text-sm font-medium">{title}</p>
            <p className="mt-2 text-3xl font-bold">{new Intl.NumberFormat('fr-FR').format(value)}</p>
        </article>
    );
}

function RoleBadge({ role }: { role: KycUser['role'] }) {
    const palette: Record<KycUser['role'], string> = {
        client: 'bg-emerald-100 text-emerald-800',
        artisan: 'bg-blue-100 text-blue-800',
        fournisseur: 'bg-amber-100 text-amber-800',
        admin: 'bg-violet-100 text-violet-800',
        referent: 'bg-cyan-100 text-cyan-800',
    };

    const cls = palette[role] ?? 'bg-slate-100 text-slate-700';
    return <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${cls}`}>{role}</span>;
}

function MapBadge({ city, count }: { city: string; count: number }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800">
            {city}: <span className="font-semibold">{count}</span>
        </div>
    );
}

function KpiLine({ label, value }: { label: string; value: number }) {
    return (
        <div className="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2">
            <span>{label}</span>
            <span className="font-semibold">{new Intl.NumberFormat('fr-FR').format(value)}</span>
        </div>
    );
}

function LineTrendChart({ points }: { points: ChartPoint[] }) {
    const width = 700;
    const height = 250;
    const padding = 34;
    const maxValue = Math.max(...points.map((p) => p.value), 1);
    const xStep = points.length > 1 ? (width - padding * 2) / (points.length - 1) : 0;
    const toY = (value: number): number => height - padding - (value / maxValue) * (height - padding * 2);
    const polyline = points
        .map((p, idx) => `${padding + idx * xStep},${toY(p.value)}`)
        .join(' ');

    return (
        <div className="rounded-2xl border border-slate-100 bg-slate-50 p-3">
            <svg viewBox={`0 0 ${width} ${height}`} className="h-56 w-full">
                <line x1={padding} y1={height - padding} x2={width - padding} y2={height - padding} stroke="#cbd5e1" />
                <line x1={padding} y1={padding} x2={padding} y2={height - padding} stroke="#cbd5e1" />
                <polyline fill="none" stroke="#2563eb" strokeWidth="3" points={polyline} />
                {points.map((p, idx) => {
                    const x = padding + idx * xStep;
                    const y = toY(p.value);
                    return (
                        <g key={p.label}>
                            <circle cx={x} cy={y} r="4" fill="#1d4ed8" />
                            <text x={x} y={height - 10} textAnchor="middle" fontSize="11" fill="#64748b">
                                {p.label}
                            </text>
                        </g>
                    );
                })}
            </svg>
        </div>
    );
}

function BarDistributionChart({ points }: { points: ChartPoint[] }) {
    const maxValue = Math.max(...points.map((p) => p.value), 1);

    return (
        <div className="space-y-3 rounded-2xl border border-slate-100 bg-slate-50 p-4">
            {points.map((point) => {
                const widthPercent = (point.value / maxValue) * 100;
                return (
                    <div key={point.label}>
                        <div className="mb-1 flex items-center justify-between text-sm">
                            <span className="text-slate-700">{point.label}</span>
                            <span className="font-semibold text-slate-900">{point.value}</span>
                        </div>
                        <div className="h-3 overflow-hidden rounded-full bg-slate-200">
                            <div
                                className="h-3 rounded-full bg-gradient-to-r from-indigo-500 to-blue-500"
                                style={{ width: `${widthPercent}%` }}
                            />
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

function LeafletCIVMap({ points }: { points: MapPoint[] }) {
    const mapContainerRef = useRef<HTMLDivElement | null>(null);
    const mapRef = useRef<LeafletMapInstance | null>(null);

    useEffect(() => {
        if (!mapContainerRef.current || mapRef.current || typeof window === 'undefined') {
            return;
        }

        let isCancelled = false;

        void loadLeafletAssets().then(() => {
            if (isCancelled || !mapContainerRef.current || !window.L) {
                return;
            }

            const map = window.L.map(mapContainerRef.current).setView([7.54, -5.55], 6);
            window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 18,
            }).addTo(map);

            mapRef.current = map;
        });

        return () => {
            isCancelled = true;
            if (mapRef.current) {
                mapRef.current.remove();
                mapRef.current = null;
            }
        };
    }, []);

    useEffect(() => {
        const L = window.L;
        if (!mapRef.current || !L) {
            return;
        }

        const map = mapRef.current;
        const dynamicLayers: unknown[] = [];

        map.eachLayer((layer) => {
            dynamicLayers.push(layer);
        });

        dynamicLayers.slice(1).forEach((layer) => map.removeLayer(layer));

        points.forEach((point) => {
            const radius = 7 + Math.min(point.missions / 12, 18);
            L
                .circleMarker([point.lat, point.lng], {
                    radius,
                    color: '#1d4ed8',
                    fillColor: '#3b82f6',
                    fillOpacity: 0.55,
                    weight: 2,
                })
                .bindPopup(
                    `<b>${point.city}</b><br/>Missions: ${point.missions}<br/>Litiges: ${point.litiges}<br/>KYC en attente: ${point.pendingKyc}`,
                )
                .addTo(map);
        });

        const bounds = L.latLngBounds(points.map((point) => [point.lat, point.lng] as [number, number]));
        if (bounds.isValid()) {
            map.fitBounds(bounds, { padding: [24, 24] });
        }
    }, [points]);

    return <div ref={mapContainerRef} className="h-80 w-full rounded-2xl border border-slate-200" />;
}

async function loadLeafletAssets(): Promise<void> {
    if (typeof window === 'undefined') {
        return;
    }

    if (window.L) {
        return;
    }

    if (leafletLoadPromise) {
        return leafletLoadPromise;
    }

    leafletLoadPromise = new Promise<void>((resolve, reject) => {
        const cssId = 'leaflet-css-cdn';
        if (!document.getElementById(cssId)) {
            const link = document.createElement('link');
            link.id = cssId;
            link.rel = 'stylesheet';
            link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
            document.head.appendChild(link);
        }

        const scriptId = 'leaflet-js-cdn';
        const existingScript = document.getElementById(scriptId) as HTMLScriptElement | null;
        if (existingScript) {
            existingScript.addEventListener('load', () => resolve(), { once: true });
            existingScript.addEventListener('error', () => reject(new Error('Impossible de charger Leaflet.')), { once: true });
            return;
        }

        const script = document.createElement('script');
        script.id = scriptId;
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Impossible de charger Leaflet.'));
        document.body.appendChild(script);
    });

    return leafletLoadPromise;
}
