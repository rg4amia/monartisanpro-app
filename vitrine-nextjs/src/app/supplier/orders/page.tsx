'use client';

import { useEffect, useState, useMemo } from 'react';
import { api } from '@/lib/api';

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

export default function SupplierOrders() {
    const [orders, setOrders] = useState<Order[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState<string>('all');

    // Modal state
    const [selectedOrder, setSelectedOrder] = useState<Order | null>(null);
    const [isPickupModalOpen, setIsPickupModalOpen] = useState(false);
    const [pickupCodeInput, setPickupCodeInput] = useState('');
    const [actionLoading, setActionLoading] = useState(false);

    const loadOrders = async () => {
        try {
            const list = await api.getSupplierOrders();
            setOrders(list);
        } catch (err: any) {
            console.error(err);
            setError(err.message || 'Impossible de charger les commandes');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadOrders();
    }, []);

    const filteredOrders = useMemo(() => {
        return orders.filter((o) => {
            const matchesSearch =
                !searchQuery ||
                o.id.toString().includes(searchQuery) ||
                (o.client && o.client.name.toLowerCase().includes(searchQuery.toLowerCase())) ||
                (o.client && o.client.phone.includes(searchQuery));

            const matchesStatus =
                statusFilter === 'all' ||
                o.status === statusFilter;

            return matchesSearch && matchesStatus;
        });
    }, [orders, searchQuery, statusFilter]);

    const handleMarkPrepared = async (orderId: number) => {
        setActionLoading(true);
        try {
            await api.markOrderPrepared(orderId);
            setSelectedOrder(null);
            loadOrders();
        } catch (err: any) {
            alert(err.message || "Erreur de mise à jour du statut.");
        } finally {
            setActionLoading(false);
        }
    };

    const handleVerifyPickup = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedOrder) return;
        
        setActionLoading(true);
        try {
            await api.verifyOrderPickup(selectedOrder.id, pickupCodeInput);
            setIsPickupModalOpen(false);
            setSelectedOrder(null);
            setPickupCodeInput('');
            loadOrders();
        } catch (err: any) {
            alert(err.message || "Code de retrait invalide.");
        } finally {
            setActionLoading(false);
        }
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

    const getStatusBadge = (status: string) => {
        const badges: Record<string, { text: string; css: string }> = {
            paid: { text: 'Payée (À préparer)', css: 'bg-blue-500/10 text-blue-400 border-blue-500/20' },
            prepared: { text: 'Préparée (Retrait imminent)', css: 'bg-purple-500/10 text-purple-400 border-purple-500/20' },
            searching_driver: { text: 'Recherche Livreur', css: 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20' },
            driver_assigned: { text: 'Livreur Assigné', css: 'bg-orange-500/10 text-orange-400 border-orange-500/20' },
            driver_picked_up: { text: 'En cours de livraison', css: 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20' },
            shipping: { text: 'En Transit', css: 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20' },
            delivered: { text: 'Livrée & Réglée', css: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' },
            disputed: { text: 'En Litige', css: 'bg-rose-500/10 text-rose-400 border-rose-500/20' },
        };
        const config = badges[status] || { text: status, css: 'bg-slate-800 text-slate-400 border-slate-700' };
        return (
            <span className={`px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wider ${config.css}`}>
                {config.text}
            </span>
        );
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center min-h-[400px]">
                <div className="w-8 h-8 border-2 border-amber-500 border-t-transparent rounded-full animate-spin" />
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Header */}
            <div>
                <h1 className="text-2xl font-bold tracking-tight">Commandes Client</h1>
                <p className="text-slate-400 text-sm mt-1">
                    Suivez et préparez les commandes de matériaux reçues de vos clients.
                </p>
            </div>

            {/* Filters & Search */}
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div className="flex items-center bg-slate-900 border border-slate-800 rounded-lg px-4 py-2 w-full md:max-w-md shadow-md">
                    <span className="text-slate-500 text-sm">🔍</span>
                    <input
                        type="text"
                        placeholder="Rechercher par # ID, Client, Téléphone..."
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        className="w-full bg-transparent border-none text-white text-sm focus:outline-none pl-3 outline-none"
                    />
                </div>

                <div className="flex items-center gap-2 overflow-x-auto pb-1 md:pb-0">
                    {[
                        { label: 'Tous', value: 'all' },
                        { label: 'À préparer', value: 'paid' },
                        { label: 'Préparées', value: 'prepared' },
                        { label: 'En Transit', value: 'driver_picked_up' },
                        { label: 'Livrées', value: 'delivered' },
                        { label: 'Litiges', value: 'disputed' },
                    ].map((filter) => (
                        <button
                            key={filter.value}
                            onClick={() => setStatusFilter(filter.value)}
                            className={`px-3 py-1.5 rounded-lg text-xs font-semibold border transition whitespace-nowrap ${
                                statusFilter === filter.value
                                    ? 'bg-amber-500/10 text-amber-500 border-amber-500/35'
                                    : 'border-slate-800 text-slate-400 hover:text-white hover:bg-slate-900'
                            }`}
                        >
                            {filter.label}
                        </button>
                    ))}
                </div>
            </div>

            {/* Orders Table */}
            {filteredOrders.length > 0 ? (
                <div className="bg-slate-900/40 border border-slate-800 rounded-xl overflow-hidden shadow-xl">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="border-b border-slate-800 text-slate-400 text-[10px] uppercase font-bold tracking-wider bg-slate-900/20">
                                    <th className="py-4 px-6">Commande</th>
                                    <th className="py-4 px-6">Client</th>
                                    <th className="py-4 px-6">Date de commande</th>
                                    <th className="py-4 px-6">Mode</th>
                                    <th className="py-4 px-6">Statut</th>
                                    <th className="py-4 px-6">Montant</th>
                                    <th className="py-4 px-6 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-850 text-sm">
                                {filteredOrders.map((order) => (
                                    <tr key={order.id} className="hover:bg-slate-800/15 transition">
                                        <td className="py-4 px-6 font-bold text-white">#{order.id}</td>
                                        <td className="py-4 px-6">
                                            <div className="font-semibold text-slate-200">{order.client?.name}</div>
                                            <div className="text-xs text-slate-500">{order.client?.phone}</div>
                                        </td>
                                        <td className="py-4 px-6 text-slate-350">{shortDate(order.created_at)}</td>
                                        <td className="py-4 px-6">
                                            <span className={`px-2 py-0.5 rounded text-[9px] font-bold ${
                                                order.delivery_mode === 'delivery'
                                                    ? 'bg-amber-500/10 text-amber-400'
                                                    : 'bg-blue-500/10 text-blue-400'
                                            }`}>
                                                {order.delivery_mode === 'delivery' ? 'LIVRAISON CHANTIER' : 'RETRAIT DIRECT'}
                                            </span>
                                        </td>
                                        <td className="py-4 px-6">{getStatusBadge(order.status)}</td>
                                        <td className="py-4 px-6 font-extrabold text-amber-500">{money(order.subtotal)}</td>
                                        <td className="py-4 px-6 text-right">
                                            <button
                                                onClick={() => setSelectedOrder(order)}
                                                className="bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold px-3 py-1.5 rounded-lg text-xs transition"
                                            >
                                                Gérer
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            ) : (
                <div className="text-center py-16 border border-dashed border-slate-850 rounded-xl">
                    <span className="text-5xl">📭</span>
                    <h3 className="text-slate-300 font-bold mt-4">Aucune commande trouvée</h3>
                    <p className="text-slate-500 text-xs mt-1">Aucune commande ne correspond à vos critères actuels.</p>
                </div>
            )}

            {/* Manage Order Modal */}
            {selectedOrder && (
                <div className="fixed inset-0 bg-black/75 flex items-center justify-center p-4 z-50 backdrop-blur-sm">
                    <div className="bg-slate-900 border border-slate-800 w-full max-w-2xl rounded-2xl overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
                        {/* Header */}
                        <div className="p-6 border-b border-slate-800 flex justify-between items-center bg-slate-950/20">
                            <div>
                                <h3 className="font-extrabold text-lg text-white">Commande #{selectedOrder.id}</h3>
                                <p className="text-xs text-slate-500 mt-0.5">{shortDate(selectedOrder.created_at)}</p>
                            </div>
                            <button
                                onClick={() => setSelectedOrder(null)}
                                className="text-slate-400 hover:text-white text-lg transition outline-none"
                            >
                                ✕
                            </button>
                        </div>

                        {/* Body */}
                        <div className="p-6 overflow-y-auto space-y-6">
                            {/* Actors */}
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="bg-slate-950/50 p-4 rounded-xl border border-slate-850">
                                    <span className="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Client Destinataire</span>
                                    <div className="font-bold text-slate-200 mt-1">{selectedOrder.client?.name}</div>
                                    <div className="text-xs text-slate-400">{selectedOrder.client?.phone}</div>
                                </div>

                                {selectedOrder.driver && (
                                    <div className="bg-slate-950/50 p-4 rounded-xl border border-slate-850">
                                        <span className="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Livreur Assigné</span>
                                        <div className="font-bold text-slate-200 mt-1">{selectedOrder.driver.name}</div>
                                        <div className="text-xs text-slate-400">{selectedOrder.driver.phone}</div>
                                    </div>
                                )}
                            </div>

                            {/* Items Grid */}
                            <div>
                                <h4 className="text-xs font-bold uppercase text-slate-400 tracking-wider mb-2.5">Matériaux Commandés</h4>
                                <div className="border border-slate-850 rounded-lg overflow-hidden divide-y divide-slate-850">
                                    {selectedOrder.items?.map((item) => (
                                        <div key={item.id} className="p-3 flex items-center justify-between text-sm bg-slate-950/20">
                                            <div>
                                                <div className="font-semibold text-slate-200">{item.product?.name}</div>
                                                <div className="text-xs text-slate-500">Quantité : {item.quantity} | PU : {money(item.unit_price)}</div>
                                            </div>
                                            <div className="font-bold text-slate-300">
                                                {money(item.unit_price * item.quantity)}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            {/* Status and Action Panel */}
                            <div className="bg-slate-950/50 p-5 rounded-xl border border-slate-850/80 flex flex-col sm:flex-row items-center justify-between gap-4">
                                <div className="space-y-1 self-start sm:self-center">
                                    <div className="text-xs text-slate-500 font-semibold">Statut logistique actuel</div>
                                    <div className="flex items-center gap-2 pt-0.5">
                                        {getStatusBadge(selectedOrder.status)}
                                    </div>
                                </div>

                                <div className="flex gap-2 w-full sm:w-auto">
                                    {selectedOrder.status === 'paid' && (
                                        <button
                                            onClick={() => handleMarkPrepared(selectedOrder.id)}
                                            className="w-full sm:w-auto bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold px-5 py-2.5 rounded-lg text-xs transition active:scale-[0.98]"
                                            disabled={actionLoading}
                                        >
                                            {actionLoading ? 'Mise à jour...' : 'Marquer comme Prête'}
                                        </button>
                                    )}

                                    {['prepared', 'driver_assigned'].includes(selectedOrder.status) && (
                                        <button
                                            onClick={() => setIsPickupModalOpen(true)}
                                            className="w-full sm:w-auto bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-white font-bold px-5 py-2.5 rounded-lg text-xs transition active:scale-[0.98]"
                                            disabled={actionLoading}
                                        >
                                            Valider la récupération (Code)
                                        </button>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Footer */}
                        <div className="p-6 border-t border-slate-800 bg-slate-950/10 flex justify-end">
                            <button
                                onClick={() => setSelectedOrder(null)}
                                className="bg-slate-800 hover:bg-slate-750 text-slate-300 font-semibold px-4 py-2.5 rounded-lg text-xs transition"
                            >
                                Fermer
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Verify Pickup Code Modal */}
            {isPickupModalOpen && selectedOrder && (
                <div className="fixed inset-0 bg-black/85 flex items-center justify-center p-4 z-50 backdrop-blur-sm">
                    <div className="bg-slate-900 border border-slate-800 w-full max-w-md rounded-2xl overflow-hidden shadow-2xl relative">
                        <div className="p-6 border-b border-slate-800">
                            <h3 className="font-bold text-base text-white">Validation du Retrait</h3>
                            <p className="text-slate-500 text-xs mt-0.5">Saisissez le code de retrait fourni par le livreur ou le client.</p>
                        </div>

                        <form onSubmit={handleVerifyPickup} className="p-6 space-y-4">
                            <div>
                                <label className="block text-slate-400 text-xs font-bold mb-1.5 uppercase">Code de Retrait (Pickup Code)</label>
                                <input
                                    type="text"
                                    placeholder="Ex: RET-16"
                                    value={pickupCodeInput}
                                    onChange={(e) => setPickupCodeInput(e.target.value.toUpperCase())}
                                    className="w-full bg-slate-950 border border-slate-800 text-white rounded-lg px-4 py-3 text-center text-lg font-bold uppercase tracking-widest focus:border-amber-500 outline-none transition"
                                    required
                                    disabled={actionLoading}
                                />
                            </div>

                            <div className="flex gap-3 pt-3">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setIsPickupModalOpen(false);
                                        setPickupCodeInput('');
                                    }}
                                    className="flex-1 bg-slate-850 hover:bg-slate-800 text-slate-300 font-bold py-2.5 rounded-lg text-xs transition"
                                    disabled={actionLoading}
                                >
                                    Annuler
                                </button>
                                <button
                                    type="submit"
                                    className="flex-1 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold py-2.5 rounded-lg text-xs transition"
                                    disabled={actionLoading}
                                >
                                    {actionLoading ? 'Validation...' : 'Valider'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
