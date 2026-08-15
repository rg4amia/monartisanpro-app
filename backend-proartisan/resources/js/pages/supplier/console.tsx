import { Head, Link, router, usePage, useForm } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import type { ReactNode } from 'react';

type SupplierTab = 'dashboard' | 'catalog' | 'orders' | 'litiges';

interface OrderItem {
    id: number;
    supplier_product_id: number;
    quantity: number;
    unit_price: number;
    product?: {
        name: string;
        sku?: string;
        image_url?: string;
    };
}

interface Order {
    id: number;
    client_id: number;
    driver_id?: number | null;
    delivery_mode: 'pickup' | 'delivery';
    vehicle_class?: string;
    status: 'paid' | 'prepared' | 'searching_driver' | 'driver_assigned' | 'driver_picked_up' | 'shipping' | 'delivered' | 'disputed';
    subtotal: number;
    delivery_cost: number;
    platform_fee: number;
    total_amount: number;
    pickup_code: string;
    reception_code: string;
    created_at: string;
    client?: {
        name: string;
        phone: string;
    };
    driver?: {
        name: string;
        phone: string;
    };
    items?: OrderItem[];
}

interface Product {
    id: number;
    name: string;
    sku?: string;
    description?: string;
    unit_price: number;
    stock_quantity: number;
    image_url?: string;
    is_active: boolean;
}

interface Litige {
    id: number;
    mission_id: number;
    motif: string;
    description: string;
    statut: string;
    created_at: string;
    mission?: {
        id: number;
        description: string;
        client?: { name: string; phone: string };
        artisan?: { name: string; phone: string };
    };
}

interface SupplierConsoleProps {
    initialTab: SupplierTab;
    stats?: {
        total_orders: number;
        pending_orders: number;
        total_revenue: number;
        catalog_count: number;
    };
    recentOrders?: Order[];
    products?: Product[];
    orders?: Order[];
    orderLitiges?: Order[];
    missionLitiges?: any[];
}

const tabRoutes: Record<SupplierTab, string> = {
    dashboard: '/supplier/dashboard',
    catalog: '/supplier/catalog',
    orders: '/supplier/orders',
    litiges: '/supplier/litiges',
};

const tabMeta: Record<SupplierTab, { label: string; description: string }> = {
    dashboard: {
        label: "Tableau de Bord",
        description: "Vue d'ensemble de vos ventes, revenus et performances d'activité.",
    },
    catalog: {
        label: "Catalogue d'Articles",
        description: "Gérez vos produits, mettez à jour vos stocks et ajustez vos prix.",
    },
    orders: {
        label: "Commandes Client",
        description: "Suivez et préparez les commandes reçues de vos clients.",
    },
    litiges: {
        label: "Litiges & Réclamations",
        description: "Consultez les litiges relatifs à vos commandes ou missions.",
    },
};

const money = (amount: number): string => `${new Intl.NumberFormat('fr-FR').format(amount)} FCFA`;

const shortDate = (value: string): string =>
    new Date(value).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });

export default function SupplierConsole({
    initialTab = 'dashboard',
    stats = { total_orders: 0, pending_orders: 0, total_revenue: 0, catalog_count: 0 },
    recentOrders = [],
    products = [],
    orders = [],
    orderLitiges = [],
    missionLitiges = [],
}: SupplierConsoleProps) {
    const { auth } = usePage<any>().props;
    const [activeTab, setActiveTab] = useState<SupplierTab>(initialTab);
    const [searchQuery, setSearchQuery] = useState('');
    
    // Modals state
    const [isProductModalOpen, setIsProductModalOpen] = useState(false);
    const [editingProduct, setEditingProduct] = useState<Product | null>(null);
    const [selectedOrder, setSelectedOrder] = useState<Order | null>(null);
    const [isPickupModalOpen, setIsPickupModalOpen] = useState(false);
    const [pickupCodeInput, setPickupCodeInput] = useState('');
    const [uploading, setUploading] = useState(false);
    const [previewImageUrl, setPreviewImageUrl] = useState<string | null>(null);

    // Form for product
    const { data: productData, setData: setProductData, post: postProduct, put: putProduct, errors: productErrors, reset: resetProduct } = useForm({
        name: '',
        sku: '',
        description: '',
        unit_price: 0,
        stock_quantity: 0,
        image_url: '',
        is_active: true,
    });

    const handleImageUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;

        setUploading(true);

        const formData = new FormData();
        formData.append('file', file);

        fetch('/supplier/upload', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin'
        })
        .then(res => res.json())
        .then(res => {
            if (res.success && res.url) {
                setProductData('image_url', res.url);
            } else {
                alert(res.message || "Erreur lors de l'upload de l'image.");
            }
        })
        .catch(err => {
            console.error(err);
            alert("Erreur de connexion lors de l'upload de l'image.");
        })
        .finally(() => {
            setUploading(false);
        });
    };

    const openCreateProductModal = () => {
        setEditingProduct(null);
        resetProduct();
        setProductData({
            name: '',
            sku: '',
            description: '',
            unit_price: 0,
            stock_quantity: 0,
            image_url: '',
            is_active: true,
        });
        setIsProductModalOpen(true);
    };

    const openEditProductModal = (product: Product) => {
        setEditingProduct(product);
        setProductData({
            name: product.name,
            sku: product.sku || '',
            description: product.description || '',
            unit_price: product.unit_price,
            stock_quantity: product.stock_quantity,
            image_url: product.image_url || '',
            is_active: product.is_active,
        });
        setIsProductModalOpen(true);
    };

    const saveProduct = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingProduct) {
            putProduct(`/supplier/products/${editingProduct.id}`, {
                onSuccess: () => {
                    setIsProductModalOpen(false);
                    router.reload();
                }
            });
        } else {
            postProduct('/supplier/products', {
                onSuccess: () => {
                    setIsProductModalOpen(false);
                    router.reload();
                }
            });
        }
    };

    const toggleProductStatus = (product: Product) => {
        router.put(`/supplier/products/${product.id}`, {
            name: product.name,
            sku: product.sku,
            unit_price: product.unit_price,
            stock_quantity: product.stock_quantity,
            image_url: product.image_url,
            is_active: !product.is_active,
        }, {
            onSuccess: () => router.reload()
        });
    };

    const archiveProduct = (product: Product) => {
        if (confirm(`Êtes-vous sûr de vouloir archiver l'article "${product.name}" ?`)) {
            router.delete(`/supplier/products/${product.id}`, {
                onSuccess: () => router.reload()
            });
        }
    };

    const markOrderAsPrepared = (orderId: number) => {
        router.post(`/supplier/orders/${orderId}/prepared`, {}, {
            onSuccess: () => {
                setSelectedOrder(null);
                router.reload();
            }
        });
    };

    const handleVerifyPickup = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedOrder) return;
        
        router.post(`/supplier/orders/${selectedOrder.id}/verify-pickup`, {
            code: pickupCodeInput
        }, {
            onSuccess: () => {
                setIsPickupModalOpen(false);
                setSelectedOrder(null);
                setPickupCodeInput('');
                router.reload();
            },
            onError: (err: any) => {
                alert(err.message || "Code de validation incorrect.");
            }
        });
    };

    // Filter products & orders
    const filteredProducts = useMemo(() => {
        if (!searchQuery) return products;
        const q = searchQuery.toLowerCase();
        return products.filter(p => 
            p.name.toLowerCase().includes(q) || 
            (p.sku && p.sku.toLowerCase().includes(q))
        );
    }, [products, searchQuery]);

    const filteredOrders = useMemo(() => {
        if (!searchQuery) return orders;
        const q = searchQuery.toLowerCase();
        return orders.filter(o => 
            o.id.toString().includes(q) ||
            (o.client && o.client.name.toLowerCase().includes(q)) ||
            (o.client && o.client.phone.includes(q))
        );
    }, [orders, searchQuery]);

    const logout = () => {
        router.post('/supplier/logout');
    };

    return (
        <div className="min-h-screen bg-slate-900 text-slate-100 font-sans flex flex-col">
            <Head title={`Console Fournisseur - ${tabMeta[activeTab].label}`} />

            {/* Top Bar */}
            <header className="bg-slate-950 border-b border-slate-800 px-6 py-4 flex items-center justify-between sticky top-0 z-30">
                <div className="flex items-center gap-3">
                    <span className="bg-amber-500 text-slate-950 font-bold px-3 py-1 rounded text-sm tracking-wide">
                        PROSARTISAN
                    </span>
                    <span className="text-slate-400 font-semibold text-lg">
                        Espace Fournisseur
                    </span>
                </div>

                <div className="flex items-center gap-4">
                    <div className="text-right">
                        <div className="font-medium text-sm text-slate-200">{auth?.user?.name || auth?.user?.phone}</div>
                        <div className="text-xs text-amber-500 font-medium">Boutique Agréée</div>
                    </div>
                    <button 
                        onClick={logout}
                        className="bg-slate-800 hover:bg-rose-950 hover:text-rose-200 text-slate-300 font-medium px-4 py-2 rounded text-sm border border-slate-700 transition"
                    >
                        Déconnexion
                    </button>
                </div>
            </header>

            <div className="flex-1 flex">
                {/* Sidebar */}
                <aside className="w-64 bg-slate-950 border-r border-slate-800 p-4 flex flex-col gap-2">
                    <div className="text-[10px] text-slate-500 font-bold tracking-wider uppercase px-3 py-2">
                        NAVIGATION
                    </div>

                    {(['dashboard', 'catalog', 'orders', 'litiges'] as SupplierTab[]).map(tab => (
                        <Link
                            key={tab}
                            href={tabRoutes[tab]}
                            onClick={() => setActiveTab(tab)}
                            className={`flex items-center justify-between px-3 py-2.5 rounded font-medium text-sm transition ${
                                activeTab === tab
                                    ? 'bg-amber-500 text-slate-950 font-semibold'
                                    : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200'
                            }`}
                        >
                            <span>{tabMeta[tab].label}</span>
                            {tab === 'orders' && stats.pending_orders > 0 && (
                                <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${activeTab === 'orders' ? 'bg-slate-950 text-amber-500' : 'bg-amber-500/20 text-amber-400'}`}>
                                    {stats.pending_orders}
                                </span>
                            )}
                        </Link>
                    ))}
                </aside>

                {/* Main Content Area */}
                <main className="flex-1 p-8 bg-slate-900 overflow-y-auto">
                    {/* Header info */}
                    <div className="mb-6">
                        <h1 className="text-2xl font-bold text-slate-100">{tabMeta[activeTab].label}</h1>
                        <p className="text-sm text-slate-400">{tabMeta[activeTab].description}</p>
                    </div>

                    {/* Flash messages */}
                    {usePage<any>().props.flash?.success && (
                        <div className="mb-6 bg-emerald-950 border border-emerald-800 text-emerald-300 px-4 py-3 rounded text-sm">
                            {usePage<any>().props.flash.success}
                        </div>
                    )}

                    {/* Tab Views */}

                    {/* TAB: DASHBOARD */}
                    {activeTab === 'dashboard' && (
                        <div className="space-y-8">
                            {/* Stats Grid */}
                            <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                                <div className="bg-slate-950 border border-slate-800 p-6 rounded-lg shadow-sm">
                                    <div className="text-xs font-semibold uppercase text-slate-500 tracking-wider">Chiffre d'Affaires</div>
                                    <div className="text-2xl font-extrabold text-amber-400 mt-2">{money(stats.total_revenue)}</div>
                                    <div className="text-[10px] text-slate-400 mt-1">Revenu libéré cumulé</div>
                                </div>
                                <div className="bg-slate-950 border border-slate-800 p-6 rounded-lg shadow-sm">
                                    <div className="text-xs font-semibold uppercase text-slate-500 tracking-wider">Commandes Totales</div>
                                    <div className="text-2xl font-extrabold text-slate-200 mt-2">{stats.total_orders}</div>
                                    <div className="text-[10px] text-slate-400 mt-1">Commandes reçues</div>
                                </div>
                                <div className="bg-slate-950 border border-slate-800 p-6 rounded-lg shadow-sm">
                                    <div className="text-xs font-semibold uppercase text-slate-500 tracking-wider">À Préparer</div>
                                    <div className="text-2xl font-extrabold text-amber-500 mt-2">{stats.pending_orders}</div>
                                    <div className="text-[10px] text-slate-400 mt-1">Commandes non traitées</div>
                                </div>
                                <div className="bg-slate-950 border border-slate-800 p-6 rounded-lg shadow-sm">
                                    <div className="text-xs font-semibold uppercase text-slate-500 tracking-wider">Articles en Ligne</div>
                                    <div className="text-2xl font-extrabold text-slate-200 mt-2">{stats.catalog_count}</div>
                                    <div className="text-[10px] text-slate-400 mt-1">Articles actifs en catalogue</div>
                                </div>
                            </div>

                            {/* Recent Orders */}
                            <div className="bg-slate-950 border border-slate-800 rounded-lg p-6">
                                <h3 className="text-lg font-bold text-slate-100 mb-4">Commandes Récentes</h3>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-sm text-slate-300">
                                        <thead className="bg-slate-900 text-xs font-bold text-slate-400 uppercase border-b border-slate-800">
                                            <tr>
                                                <th className="px-4 py-3">Commande ID</th>
                                                <th className="px-4 py-3">Client</th>
                                                <th className="px-4 py-3">Mode</th>
                                                <th className="px-4 py-3">Montant Matériaux</th>
                                                <th className="px-4 py-3">Statut</th>
                                                <th className="px-4 py-3">Date</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-800/50">
                                            {recentOrders.length === 0 ? (
                                                <tr>
                                                    <td colSpan={6} className="px-4 py-8 text-center text-slate-500">Aucune commande récente.</td>
                                                </tr>
                                            ) : (
                                                recentOrders.map(order => (
                                                    <tr key={order.id} className="hover:bg-slate-800/30 transition">
                                                        <td className="px-4 py-3 font-semibold text-slate-200">#{order.id}</td>
                                                        <td className="px-4 py-3">
                                                            <div>{order.client?.name}</div>
                                                            <div className="text-xs text-slate-500">{order.client?.phone}</div>
                                                        </td>
                                                        <td className="px-4 py-3 capitalize">{order.delivery_mode === 'pickup' ? 'Retrait' : 'Livraison'}</td>
                                                        <td className="px-4 py-3 font-bold text-amber-500">{money(order.subtotal)}</td>
                                                        <td className="px-4 py-3">
                                                            <span className={`inline-block px-2 py-0.5 rounded text-[10px] font-bold ${
                                                                order.status === 'delivered' ? 'bg-emerald-950 text-emerald-400' :
                                                                order.status === 'paid' ? 'bg-blue-950 text-blue-400 border border-blue-800' :
                                                                order.status === 'disputed' ? 'bg-rose-950 text-rose-400' :
                                                                'bg-slate-800 text-slate-400'
                                                            }`}>
                                                                {order.status}
                                                            </span>
                                                        </td>
                                                        <td className="px-4 py-3 text-xs text-slate-400">{shortDate(order.created_at)}</td>
                                                    </tr>
                                                ))
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* TAB: CATALOG */}
                    {activeTab === 'catalog' && (
                        <div className="space-y-6">
                            <div className="flex justify-between items-center">
                                <input
                                    type="text"
                                    placeholder="Rechercher par nom ou SKU..."
                                    value={searchQuery}
                                    onChange={e => setSearchQuery(e.target.value)}
                                    className="bg-slate-950 border border-slate-800 px-4 py-2 rounded text-sm w-72 focus:outline-none focus:border-amber-500 text-slate-300"
                                />
                                <button
                                    onClick={openCreateProductModal}
                                    className="bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold px-4 py-2 rounded text-sm transition"
                                >
                                    + Ajouter un Article
                                </button>
                            </div>

                            <div className="bg-slate-950 border border-slate-800 rounded-lg overflow-hidden">
                                <table className="w-full text-left text-sm text-slate-300">
                                    <thead className="bg-slate-900 text-xs font-bold text-slate-400 uppercase border-b border-slate-800">
                                        <tr>
                                            <th className="px-6 py-3">Article</th>
                                            <th className="px-6 py-3">SKU</th>
                                            <th className="px-6 py-3">Prix unitaire</th>
                                            <th className="px-6 py-3">Stock disponible</th>
                                            <th className="px-6 py-3">Statut</th>
                                            <th className="px-6 py-3 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-800/50">
                                        {filteredProducts.length === 0 ? (
                                            <tr>
                                                <td colSpan={6} className="px-6 py-8 text-center text-slate-500">Aucun produit trouvé.</td>
                                            </tr>
                                        ) : (
                                            filteredProducts.map(product => (
                                                <tr key={product.id} className="hover:bg-slate-800/30 transition">
                                                    <td className="px-6 py-4">
                                                        <div className="flex items-center gap-3">
                                                            {product.image_url ? (
                                                                <button
                                                                    type="button"
                                                                    onClick={() => setPreviewImageUrl(product.image_url)}
                                                                    className="w-10 h-10 rounded border border-slate-800 shrink-0 overflow-hidden cursor-zoom-in hover:opacity-80 transition"
                                                                    title="Agrandir l'image"
                                                                >
                                                                    <img src={product.image_url} alt={product.name} className="w-full h-full object-cover" />
                                                                </button>
                                                            ) : (
                                                                <div className="w-10 h-10 bg-slate-900 border border-slate-850 rounded flex items-center justify-center text-[10px] text-slate-500 font-bold shrink-0">
                                                                    IMG
                                                                </div>
                                                            )}
                                                            <div className="min-w-0">
                                                                <div className="font-semibold text-slate-200">{product.name}</div>
                                                                <div className="text-xs text-slate-500 max-w-sm truncate">{product.description || 'Pas de description'}</div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-4 text-xs font-mono text-slate-400">{product.sku || 'N/A'}</td>
                                                    <td className="px-6 py-4 font-bold text-amber-500">{money(product.unit_price)}</td>
                                                    <td className="px-6 py-4 font-semibold">{product.stock_quantity}</td>
                                                    <td className="px-6 py-4">
                                                        <button
                                                            onClick={() => toggleProductStatus(product)}
                                                            className={`inline-block px-2.5 py-0.5 rounded text-xs font-bold ${
                                                                product.is_active
                                                                    ? 'bg-emerald-950 text-emerald-400 hover:bg-emerald-900/50'
                                                                    : 'bg-slate-850 text-slate-500 hover:bg-slate-800'
                                                            }`}
                                                        >
                                                            {product.is_active ? 'Actif' : 'Inactif'}
                                                        </button>
                                                    </td>
                                                    <td className="px-6 py-4 text-right space-x-2">
                                                        {product.image_url && (
                                                            <>
                                                                <button
                                                                    onClick={() => setPreviewImageUrl(product.image_url)}
                                                                    className="text-amber-500 hover:text-amber-400 text-xs font-bold"
                                                                >
                                                                    Visualiser
                                                                </button>
                                                                <span className="text-slate-700">|</span>
                                                            </>
                                                        )}
                                                        <button
                                                            onClick={() => openEditProductModal(product)}
                                                            className="text-slate-400 hover:text-amber-500 text-xs font-bold"
                                                        >
                                                            Modifier
                                                        </button>
                                                        <span className="text-slate-700">|</span>
                                                        <button
                                                            onClick={() => archiveProduct(product)}
                                                            className="text-rose-500 hover:text-rose-400 text-xs font-bold"
                                                        >
                                                            Archiver
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {/* TAB: ORDERS */}
                    {activeTab === 'orders' && (
                        <div className="space-y-6">
                            <input
                                type="text"
                                placeholder="Rechercher par ID de commande, nom client..."
                                value={searchQuery}
                                onChange={e => setSearchQuery(e.target.value)}
                                className="bg-slate-950 border border-slate-800 px-4 py-2 rounded text-sm w-72 focus:outline-none focus:border-amber-500 text-slate-300"
                            />

                            <div className="bg-slate-950 border border-slate-800 rounded-lg overflow-hidden">
                                <table className="w-full text-left text-sm text-slate-300">
                                    <thead className="bg-slate-900 text-xs font-bold text-slate-400 uppercase border-b border-slate-800">
                                        <tr>
                                            <th className="px-6 py-3">Commande ID</th>
                                            <th className="px-6 py-3">Client</th>
                                            <th className="px-6 py-3">Mode</th>
                                            <th className="px-6 py-3">Sous-Total</th>
                                            <th className="px-6 py-3">Statut</th>
                                            <th className="px-6 py-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-800/50">
                                        {filteredOrders.length === 0 ? (
                                            <tr>
                                                <td colSpan={6} className="px-6 py-8 text-center text-slate-500">Aucune commande reçue.</td>
                                            </tr>
                                        ) : (
                                            filteredOrders.map(order => (
                                                <tr key={order.id} className="hover:bg-slate-800/30 transition">
                                                    <td className="px-6 py-4 font-semibold text-slate-200">#{order.id}</td>
                                                    <td className="px-6 py-4">
                                                        <div className="font-medium">{order.client?.name}</div>
                                                        <div className="text-xs text-slate-500">{order.client?.phone}</div>
                                                    </td>
                                                    <td className="px-6 py-4 capitalize">{order.delivery_mode === 'pickup' ? 'Retrait' : 'Livraison'}</td>
                                                    <td className="px-6 py-4 font-bold text-amber-500">{money(order.subtotal)}</td>
                                                    <td className="px-6 py-4">
                                                        <span className={`inline-block px-2 py-0.5 rounded text-xs font-bold ${
                                                            order.status === 'delivered' ? 'bg-emerald-950 text-emerald-400' :
                                                            order.status === 'paid' ? 'bg-blue-950 text-blue-400 border border-blue-800' :
                                                            order.status === 'prepared' ? 'bg-amber-950 text-amber-400' :
                                                            order.status === 'disputed' ? 'bg-rose-950 text-rose-400' :
                                                            'bg-slate-800 text-slate-400'
                                                        }`}>
                                                            {order.status}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-4 space-x-3">
                                                        <button
                                                            onClick={() => setSelectedOrder(order)}
                                                            className="text-slate-400 hover:text-slate-200 text-xs font-bold"
                                                        >
                                                            Détail
                                                        </button>

                                                        {order.status === 'paid' && (
                                                            <button
                                                                onClick={() => markOrderAsPrepared(order.id)}
                                                                className="bg-amber-500 hover:bg-amber-600 text-slate-950 px-2 py-1 rounded text-xs font-bold"
                                                            >
                                                                Marquer Prête
                                                            </button>
                                                        )}

                                                        {(order.status === 'prepared' && order.delivery_mode === 'pickup') && (
                                                            <button
                                                                onClick={() => {
                                                                    setSelectedOrder(order);
                                                                    setIsPickupModalOpen(true);
                                                                }}
                                                                className="bg-emerald-600 hover:bg-emerald-700 text-white px-2 py-1 rounded text-xs font-bold"
                                                            >
                                                                Valider Retrait
                                                            </button>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {/* TAB: LITIGES */}
                    {activeTab === 'litiges' && (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                            {/* Commandes Litiges */}
                            <div className="bg-slate-950 border border-slate-800 p-6 rounded-lg">
                                <h3 className="text-lg font-bold text-slate-100 mb-4">Litiges sur vos Commandes</h3>
                                <div className="space-y-4">
                                    {orderLitiges.length === 0 ? (
                                        <p className="text-slate-500 text-sm py-4 text-center">Aucun litige de commande en cours.</p>
                                    ) : (
                                        orderLitiges.map(order => (
                                            <div key={order.id} className="bg-slate-900 border border-slate-800 rounded p-4 flex justify-between items-start">
                                                <div>
                                                    <div className="font-semibold text-slate-200">Commande #{order.id}</div>
                                                    <div className="text-xs text-slate-400 mt-1">Client: {order.client?.name} ({order.client?.phone})</div>
                                                    <div className="text-xs text-slate-400">Total: {money(order.total_amount)}</div>
                                                </div>
                                                <span className="bg-rose-950 text-rose-400 px-2 py-0.5 rounded text-[10px] font-bold">
                                                    DISPUTED
                                                </span>
                                            </div>
                                        ))
                                    )}
                                </div>
                            </div>

                            {/* Missions / Dépannages Litiges */}
                            <div className="bg-slate-950 border border-slate-800 p-6 rounded-lg">
                                <h3 className="text-lg font-bold text-slate-100 mb-4">Litiges Chantiers / Matériaux (J-Codes)</h3>
                                <div className="space-y-4">
                                    {missionLitiges.length === 0 ? (
                                        <p className="text-slate-500 text-sm py-4 text-center">Aucune mission associée en litige.</p>
                                    ) : (
                                        missionLitiges.map(mission => (
                                            <div key={mission.id} className="bg-slate-900 border border-slate-800 rounded p-4 space-y-2">
                                                <div className="flex justify-between items-start">
                                                    <div>
                                                        <div className="font-semibold text-slate-200">Mission #{mission.id}</div>
                                                        <div className="text-xs text-slate-400">Client: {mission.client?.name}</div>
                                                        <div className="text-xs text-slate-400">Artisan: {mission.artisan?.name}</div>
                                                    </div>
                                                    <span className="bg-rose-950 text-rose-400 px-2 py-0.5 rounded text-[10px] font-bold">
                                                        LITIGE
                                                    </span>
                                                </div>
                                                <p className="text-xs text-slate-500 italic max-h-12 overflow-hidden truncate">
                                                    "{mission.description}"
                                                </p>
                                            </div>
                                        ))
                                    )}
                                </div>
                            </div>
                        </div>
                    )}
                </main>
            </div>

            {/* MODAL: ADD / EDIT PRODUCT */}
            {isProductModalOpen && (
                <div className="fixed inset-0 bg-slate-950/80 flex items-center justify-center p-4 z-50">
                    <div className="bg-slate-900 border border-slate-800 rounded-lg p-6 max-w-md w-full space-y-4">
                        <div className="flex justify-between items-center border-b border-slate-800 pb-3">
                            <h3 className="text-lg font-bold text-slate-100">
                                {editingProduct ? "Modifier l'Article" : "Ajouter un Article"}
                            </h3>
                            <button onClick={() => setIsProductModalOpen(false)} className="text-slate-500 hover:text-slate-350">&times;</button>
                        </div>

                        <form onSubmit={saveProduct} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Nom de l'article</label>
                                <input
                                    type="text"
                                    required
                                    value={productData.name}
                                    onChange={e => setProductData('name', e.target.value)}
                                    className="w-full bg-slate-950 border border-slate-800 rounded p-2 text-sm focus:outline-none focus:border-amber-500 text-slate-200"
                                />
                                {productErrors.name && <div className="text-xs text-rose-500 mt-1">{productErrors.name}</div>}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Prix (FCFA)</label>
                                    <input
                                        type="number"
                                        required
                                        min="1"
                                        value={productData.unit_price}
                                        onChange={e => setProductData('unit_price', parseInt(e.target.value))}
                                        className="w-full bg-slate-950 border border-slate-800 rounded p-2 text-sm focus:outline-none focus:border-amber-500 text-slate-200"
                                    />
                                    {productErrors.unit_price && <div className="text-xs text-rose-500 mt-1">{productErrors.unit_price}</div>}
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Stock</label>
                                    <input
                                        type="number"
                                        required
                                        min="0"
                                        value={productData.stock_quantity}
                                        onChange={e => setProductData('stock_quantity', parseInt(e.target.value))}
                                        className="w-full bg-slate-950 border border-slate-800 rounded p-2 text-sm focus:outline-none focus:border-amber-500 text-slate-200"
                                    />
                                    {productErrors.stock_quantity && <div className="text-xs text-rose-500 mt-1">{productErrors.stock_quantity}</div>}
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">SKU (Code article unique)</label>
                                <input
                                    type="text"
                                    placeholder="ex: CIM-50"
                                    value={productData.sku}
                                    onChange={e => setProductData('sku', e.target.value)}
                                    className="w-full bg-slate-950 border border-slate-800 rounded p-2 text-sm focus:outline-none focus:border-amber-500 text-slate-200"
                                />
                                {productErrors.sku && <div className="text-xs text-rose-500 mt-1">{productErrors.sku}</div>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Image de l'article</label>
                                {productData.image_url ? (
                                    <div className="mb-2 relative w-24 h-24 bg-slate-950 border border-slate-800 rounded overflow-hidden group">
                                        <img src={productData.image_url} alt="Aperçu" className="w-full h-full object-cover" />
                                        <button 
                                            type="button" 
                                            onClick={() => setProductData('image_url', '')}
                                            className="absolute top-1 right-1 bg-red-600/90 text-white rounded-full p-1 text-xs hover:bg-red-700 transition"
                                            title="Supprimer l'image"
                                        >
                                            <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                ) : null}
                                <input
                                    type="file"
                                    accept="image/*"
                                    disabled={uploading}
                                    onChange={handleImageUpload}
                                    className="w-full text-xs text-slate-500 file:mr-4 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-amber-500/10 file:text-amber-400 hover:file:bg-amber-500/20 file:cursor-pointer disabled:opacity-50"
                                />
                                {uploading && <div className="text-xs text-amber-500 mt-1">Téléchargement de l'image en cours...</div>}
                                {productErrors.image_url && <div className="text-xs text-rose-500 mt-1">{productErrors.image_url}</div>}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Description</label>
                                <textarea
                                    value={productData.description}
                                    onChange={e => setProductData('description', e.target.value)}
                                    className="w-full bg-slate-950 border border-slate-800 rounded p-2 text-sm focus:outline-none focus:border-amber-500 text-slate-200 h-20"
                                />
                            </div>

                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="is_active"
                                    checked={productData.is_active}
                                    onChange={e => setProductData('is_active', e.target.checked)}
                                    className="rounded border-slate-800 text-amber-500 focus:ring-amber-500 bg-slate-950"
                                />
                                <label htmlFor="is_active" className="text-sm font-semibold text-slate-350">Rendre visible dans le catalogue</label>
                            </div>

                            <div className="flex justify-end gap-3 border-t border-slate-800 pt-4">
                                <button
                                    type="button"
                                    onClick={() => setIsProductModalOpen(false)}
                                    className="bg-slate-800 hover:bg-slate-700 font-medium px-4 py-2 rounded text-sm text-slate-300 transition"
                                >
                                    Annuler
                                </button>
                                <button
                                    type="submit"
                                    className="bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold px-4 py-2 rounded text-sm transition"
                                >
                                    Enregistrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* MODAL: ORDER DETAIL & RETRAIT */}
            {selectedOrder && !isPickupModalOpen && (
                <div className="fixed inset-0 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 z-50 overflow-y-auto">
                    <div className="bg-slate-900 border border-slate-800 rounded-xl p-6 max-w-2xl w-full space-y-5 my-8 shadow-2xl">
                        {/* Header */}
                        <div className="flex justify-between items-start border-b border-slate-800 pb-4">
                            <div>
                                <div className="flex items-center gap-3">
                                    <h3 className="text-xl font-extrabold text-slate-100">
                                        Commande #{selectedOrder.id}
                                    </h3>
                                    <span className={`inline-block px-2.5 py-0.5 rounded text-xs font-bold ${
                                        selectedOrder.status === 'delivered' ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' :
                                        selectedOrder.status === 'paid' ? 'bg-blue-950 text-blue-400 border border-blue-800' :
                                        selectedOrder.status === 'prepared' ? 'bg-amber-950 text-amber-400 border border-amber-800' :
                                        selectedOrder.status === 'searching_driver' ? 'bg-indigo-950 text-indigo-400 border border-indigo-800 animate-pulse' :
                                        selectedOrder.status === 'driver_assigned' || selectedOrder.status === 'driver_picked_up' || selectedOrder.status === 'shipping' ? 'bg-purple-950 text-purple-400 border border-purple-800' :
                                        selectedOrder.status === 'disputed' ? 'bg-rose-950 text-rose-400 border border-rose-800' :
                                        'bg-slate-800 text-slate-400'
                                    }`}>
                                        {selectedOrder.status === 'paid' ? 'Payée (En attente préparation)' :
                                         selectedOrder.status === 'prepared' ? 'Prête pour retrait' :
                                         selectedOrder.status === 'searching_driver' ? 'Recherche livreur...' :
                                         selectedOrder.status === 'driver_assigned' ? 'Livreur assigné' :
                                         selectedOrder.status === 'driver_picked_up' ? 'Colis récupéré par livreur' :
                                         selectedOrder.status === 'shipping' ? 'En cours de livraison' :
                                         selectedOrder.status === 'delivered' ? 'Livrée & Clôturée' :
                                         selectedOrder.status === 'disputed' ? 'En Litige' : selectedOrder.status}
                                    </span>
                                </div>
                                <div className="text-xs text-slate-500 mt-1">
                                    Reçue le {new Date(selectedOrder.created_at).toLocaleString('fr-FR')}
                                </div>
                            </div>
                            <button 
                                onClick={() => setSelectedOrder(null)} 
                                className="text-slate-400 hover:text-slate-200 text-2xl font-light leading-none p-1"
                            >
                                &times;
                            </button>
                        </div>

                        {/* Info Grid (Client & Delivery) */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {/* Client Info Card */}
                            <div className="bg-slate-950/80 border border-slate-800/80 rounded-lg p-4 space-y-2">
                                <div className="text-xs font-bold uppercase text-slate-500 tracking-wider">
                                    Coordonnées Client
                                </div>
                                <div className="text-sm font-semibold text-slate-200">
                                    {selectedOrder.client?.name || 'Client anonyme'}
                                </div>
                                <div className="text-xs text-slate-400 flex items-center gap-2">
                                    <span>📞 {selectedOrder.client?.phone || 'N/A'}</span>
                                    {selectedOrder.client?.phone && (
                                        <a 
                                            href={`tel:${selectedOrder.client.phone}`}
                                            className="text-amber-500 hover:text-amber-400 font-semibold underline text-[11px]"
                                        >
                                            Appeler
                                        </a>
                                    )}
                                </div>
                            </div>

                            {/* Delivery Info Card */}
                            <div className="bg-slate-950/80 border border-slate-800/80 rounded-lg p-4 space-y-2">
                                <div className="text-xs font-bold uppercase text-slate-500 tracking-wider">
                                    Mode de Récupération
                                </div>
                                <div className="text-sm font-semibold text-slate-200 flex items-center gap-2">
                                    <span>{selectedOrder.delivery_mode === 'pickup' ? '🏪 Retrait direct en boutique' : '🛵 Livraison à domicile'}</span>
                                </div>
                                {selectedOrder.delivery_mode === 'delivery' ? (
                                    <div className="text-xs text-slate-400">
                                        {selectedOrder.driver ? (
                                            <div className="space-y-1">
                                                <div>Livreur : <span className="font-semibold text-slate-300">{selectedOrder.driver.name}</span></div>
                                                <div>Contact : <span className="font-semibold text-amber-500">{selectedOrder.driver.phone}</span></div>
                                            </div>
                                        ) : (
                                            <div className="text-indigo-400 font-medium italic">
                                                En attente d'acceptation par un livreur partenaire
                                            </div>
                                        )}
                                    </div>
                                ) : (
                                    <div className="text-xs text-slate-400">
                                        Le client viendra retirer la marchandise avec son code de retrait.
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Articles Table */}
                        <div className="border border-slate-800 rounded-lg overflow-hidden">
                            <div className="bg-slate-950 px-4 py-2.5 text-xs font-bold uppercase text-slate-400 border-b border-slate-800">
                                Articles Commandés ({selectedOrder.items?.length || 0})
                            </div>
                            <div className="divide-y divide-slate-800 max-h-60 overflow-y-auto">
                                {!selectedOrder.items || selectedOrder.items.length === 0 ? (
                                    <div className="p-4 text-center text-xs text-slate-500">
                                        Aucun détail d'article disponible pour cette commande.
                                    </div>
                                ) : (
                                    selectedOrder.items.map(item => (
                                        <div key={item.id} className="p-3 bg-slate-900/50 flex items-center justify-between gap-4 hover:bg-slate-800/30 transition">
                                            <div className="flex items-center gap-3 min-w-0">
                                                {item.product?.image_url ? (
                                                    <img 
                                                        src={item.product.image_url} 
                                                        alt={item.product?.name || 'Article'} 
                                                        className="w-11 h-11 rounded-lg border border-slate-800 object-cover shrink-0" 
                                                    />
                                                ) : (
                                                    <div className="w-11 h-11 bg-slate-950 border border-slate-800 rounded-lg flex items-center justify-center text-[10px] text-slate-500 font-bold shrink-0">
                                                        ART
                                                    </div>
                                                )}
                                                <div className="min-w-0">
                                                    <div className="font-semibold text-slate-200 text-sm truncate">
                                                        {item.product?.name || `Produit #${item.supplier_product_id}`}
                                                    </div>
                                                    <div className="text-xs text-slate-400">
                                                        SKU : {item.product?.sku || 'N/A'} • {money(item.unit_price)} l'unité
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="text-right shrink-0">
                                                <div className="text-xs text-slate-400">
                                                    Qté : <span className="font-bold text-slate-200">x{item.quantity}</span>
                                                </div>
                                                <div className="font-bold text-amber-400 text-sm">
                                                    {money(item.unit_price * item.quantity)}
                                                </div>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>

                        {/* Financial Breakdown */}
                        <div className="bg-slate-950/80 border border-slate-800/80 rounded-lg p-4 space-y-2">
                            <div className="flex justify-between text-xs text-slate-400">
                                <span>Sous-total articles :</span>
                                <span className="font-semibold text-slate-200">{money(selectedOrder.subtotal)}</span>
                            </div>
                            {selectedOrder.delivery_cost > 0 && (
                                <div className="flex justify-between text-xs text-slate-400">
                                    <span>Frais de livraison :</span>
                                    <span className="font-semibold text-slate-200">{money(selectedOrder.delivery_cost)}</span>
                                </div>
                            )}
                            <div className="flex justify-between text-xs text-slate-400">
                                <span>Frais de service / Séquestre :</span>
                                <span className="font-semibold text-slate-200">{money(selectedOrder.platform_fee || 0)}</span>
                            </div>
                            <div className="border-t border-slate-800 pt-2 flex justify-between text-base font-extrabold">
                                <span className="text-slate-100">Montant Total Payé :</span>
                                <span className="text-amber-400 text-lg">{money(selectedOrder.total_amount || selectedOrder.subtotal)}</span>
                            </div>
                        </div>

                        {/* Modal Footer Actions */}
                        <div className="flex justify-end gap-3 border-t border-slate-800 pt-4">
                            <button
                                onClick={() => setSelectedOrder(null)}
                                className="bg-slate-800 hover:bg-slate-700 font-semibold px-4 py-2 rounded-lg text-sm text-slate-300 transition"
                            >
                                Fermer
                            </button>

                            {selectedOrder.status === 'paid' && (
                                <button
                                    onClick={() => markOrderAsPrepared(selectedOrder.id)}
                                    className="bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold px-4 py-2 rounded-lg text-sm transition shadow-lg shadow-amber-500/20"
                                >
                                    Marquer comme Prête
                                </button>
                            )}

                            {(selectedOrder.status === 'prepared' && selectedOrder.delivery_mode === 'pickup') && (
                                <button
                                    onClick={() => setIsPickupModalOpen(true)}
                                    className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 py-2 rounded-lg text-sm transition shadow-lg shadow-emerald-600/20"
                                >
                                    Valider Retrait Boutique
                                </button>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {/* MODAL: VERIFY PICKUP CODE */}
            {isPickupModalOpen && selectedOrder && (
                <div className="fixed inset-0 bg-slate-950/80 flex items-center justify-center p-4 z-50">
                    <div className="bg-slate-900 border border-slate-800 rounded-lg p-6 max-w-sm w-full space-y-4">
                        <div className="flex justify-between items-center border-b border-slate-800 pb-3">
                            <h3 className="text-lg font-bold text-slate-100">Vérification Code Retrait</h3>
                            <button 
                                onClick={() => {
                                    setIsPickupModalOpen(false);
                                    setPickupCodeInput('');
                                }} 
                                className="text-slate-500 hover:text-slate-350"
                            >
                                &times;
                            </button>
                        </div>

                        <form onSubmit={handleVerifyPickup} className="space-y-4">
                            <p className="text-xs text-slate-400">
                                Demandez au client de vous fournir son code de retrait (format: <span className="font-mono text-amber-500">RETRAIT-XXXX</span>) pour libérer les fonds.
                            </p>

                            <div>
                                <label className="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Code de retrait</label>
                                <input
                                    type="text"
                                    required
                                    placeholder="RETRAIT-XXXX"
                                    value={pickupCodeInput}
                                    onChange={e => setPickupCodeInput(e.target.value)}
                                    className="w-full bg-slate-950 border border-slate-800 rounded p-2.5 text-sm focus:outline-none focus:border-amber-500 text-center font-mono text-lg text-slate-100 placeholder-slate-700 uppercase"
                                />
                            </div>

                            <div className="flex justify-end gap-3 border-t border-slate-800 pt-4">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setIsPickupModalOpen(false);
                                        setPickupCodeInput('');
                                    }}
                                    className="bg-slate-800 hover:bg-slate-700 font-medium px-4 py-2 rounded text-sm text-slate-300 transition"
                                >
                                    Annuler
                                </button>
                                <button
                                    type="submit"
                                    className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 py-2 rounded text-sm transition"
                                >
                                    Valider & Libérer les fonds
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* LIGHTBOX: VIEW FULL SIZE IMAGE */}
            {previewImageUrl && (
                <div className="fixed inset-0 bg-slate-950/90 flex items-center justify-center p-4 z-[60]" onClick={() => setPreviewImageUrl(null)}>
                    <div className="relative max-w-4xl w-full max-h-[85vh] flex items-center justify-center" onClick={e => e.stopPropagation()}>
                        <button 
                            onClick={() => setPreviewImageUrl(null)}
                            className="absolute -top-10 right-0 text-slate-400 hover:text-white text-3xl font-bold transition focus:outline-none"
                            title="Fermer"
                        >
                            &times;
                        </button>
                        <img 
                            src={previewImageUrl} 
                            alt="Agrandissement de l'article" 
                            className="max-w-full max-h-[80vh] object-contain rounded-lg border border-slate-800 shadow-2xl bg-slate-950" 
                        />
                    </div>
                </div>
            )}
        </div>
    );
}
