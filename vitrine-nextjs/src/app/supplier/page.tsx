'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api } from '@/lib/api';

interface DashboardData {
    stats: {
        total_orders: number;
        pending_orders: number;
        total_revenue: number;
        catalog_count: number;
    };
    recent_orders: any[];
}

export default function SupplierDashboard() {
    const [data, setData] = useState<DashboardData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const fetchDashboard = async () => {
            try {
                const res = await api.getSupplierDashboard<DashboardData>();
                setData(res);
            } catch (err: any) {
                console.error(err);
                setError(err.message || 'Impossible de charger le tableau de bord');
            } finally {
                setLoading(false);
            }
        };

        fetchDashboard();
    }, []);

    const money = (amount: number): string => `${new Intl.NumberFormat('fr-FR').format(amount)} FCFA`;
    const shortDate = (value: string): string =>
        new Date(value).toLocaleDateString('fr-FR', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });

    if (loading) {
        return (
            <div className="flex items-center justify-center min-h-[400px]">
                <div className="w-8 h-8 border-2 border-amber-500 border-t-transparent rounded-full animate-spin" />
            </div>
        );
    }

    if (error) {
        return (
            <div className="bg-rose-950/40 border border-rose-800 text-rose-300 px-6 py-4 rounded-xl text-sm max-w-2xl mx-auto mt-8 flex flex-col gap-3">
                <div className="flex items-center gap-2 font-bold text-base">
                    <span>⚠️</span>
                    <span>Erreur de chargement</span>
                </div>
                <p>{error}</p>
                <button
                    onClick={() => {
                        setLoading(true);
                        setError(null);
                        api.getSupplierDashboard<DashboardData>().then(setData).catch(err => setError(err.message)).finally(() => setLoading(false));
                    }}
                    className="bg-rose-900/60 hover:bg-rose-800 text-white font-bold px-4 py-2 rounded-lg text-xs self-start transition"
                >
                    Réessayer
                </button>
            </div>
        );
    }

    const statsItems = [
        { label: 'Revenus validés', value: money(data?.stats.total_revenue || 0), icon: '💰', color: 'from-emerald-500/10 to-teal-500/10 border-emerald-500/20 text-emerald-400' },
        { label: 'Total Commandes', value: data?.stats.total_orders || 0, icon: '🛒', color: 'from-blue-500/10 to-indigo-500/10 border-blue-500/20 text-blue-400' },
        { label: 'Commandes à préparer', value: data?.stats.pending_orders || 0, icon: '⏳', color: 'from-amber-500/10 to-orange-500/10 border-amber-500/20 text-amber-400' },
        { label: 'Articles au catalogue', value: data?.stats.catalog_count || 0, icon: '📦', color: 'from-purple-500/10 to-pink-500/10 border-purple-500/20 text-purple-400' },
    ];

    const getStatusBadge = (status: string) => {
        const badges: Record<string, { text: string; css: string }> = {
            paid: { text: 'Payée (À préparer)', css: 'bg-blue-500/10 text-blue-400 border-blue-500/20' },
            prepared: { text: 'Préparée', css: 'bg-purple-500/10 text-purple-400 border-purple-500/20' },
            searching_driver: { text: 'Recherche Livreur', css: 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20' },
            driver_assigned: { text: 'Livreur Assigné', css: 'bg-orange-500/10 text-orange-400 border-orange-500/20' },
            driver_picked_up: { text: 'En Cours de Livraison', css: 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20' },
            shipping: { text: 'En Transit', css: 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20' },
            delivered: { text: 'Livrée & Validée', css: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' },
            disputed: { text: 'En Litige', css: 'bg-rose-500/10 text-rose-400 border-rose-500/20' },
        };
        const config = badges[status] || { text: status, css: 'bg-slate-800 text-slate-400 border-slate-700' };
        return (
            <span className={`px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wider ${config.css}`}>
                {config.text}
            </span>
        );
    };

    return (
        <div className="space-y-8">
            {/* Header Title */}
            <div>
                <h1 className="text-2xl font-bold tracking-tight">Tableau de Bord</h1>
                <p className="text-slate-400 text-sm mt-1">
                    Vue d'ensemble de vos ventes, revenus et performances d'activité quincaillerie.
                </p>
            </div>

            {/* Statistics Grid */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                {statsItems.map((stat, idx) => (
                    <div
                        key={idx}
                        className={`bg-gradient-to-br ${stat.color} border p-6 rounded-xl flex items-center justify-between shadow-lg`}
                    >
                        <div className="space-y-1.5">
                            <span className="text-slate-400 text-xs font-semibold uppercase tracking-wider">{stat.label}</span>
                            <div className="text-xl sm:text-2xl font-extrabold text-white">{stat.value}</div>
                        </div>
                        <span className="text-3xl">{stat.icon}</span>
                    </div>
                ))}
            </div>

            {/* Recent Orders Section */}
            <div className="bg-slate-900/40 border border-slate-800 rounded-xl p-6 shadow-xl space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-lg font-bold">Dernières Commandes</h2>
                        <p className="text-slate-400 text-xs mt-0.5">Vos 5 commandes les plus récentes reçues sur la plateforme.</p>
                    </div>
                    <Link
                        href="/supplier/orders"
                        className="text-amber-500 hover:text-amber-400 text-xs font-bold transition flex items-center gap-1"
                    >
                        <span>Voir tout</span>
                        <span>→</span>
                    </Link>
                </div>

                {data?.recent_orders && data.recent_orders.length > 0 ? (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="border-b border-slate-800 text-slate-400 text-[10px] uppercase font-bold tracking-wider">
                                    <th className="py-3 px-4">Commande</th>
                                    <th className="py-3 px-4">Client</th>
                                    <th className="py-3 px-4">Date</th>
                                    <th className="py-3 px-4">Livraison</th>
                                    <th className="py-3 px-4">Statut</th>
                                    <th className="py-3 px-4 text-right">Montant</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-800/60 text-sm">
                                {data.recent_orders.map((order) => (
                                    <tr key={order.id} className="hover:bg-slate-800/20 transition">
                                        <td className="py-3.5 px-4 font-semibold text-white">
                                            #{order.id}
                                        </td>
                                        <td className="py-3.5 px-4">
                                            <div className="font-semibold text-slate-200">{order.client?.name}</div>
                                            <div className="text-xs text-slate-500">{order.client?.phone}</div>
                                        </td>
                                        <td className="py-3.5 px-4 text-slate-300">
                                            {shortDate(order.created_at)}
                                        </td>
                                        <td className="py-3.5 px-4">
                                            <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${
                                                order.delivery_mode === 'delivery'
                                                    ? 'bg-amber-500/10 text-amber-400'
                                                    : 'bg-blue-500/10 text-blue-400'
                                            }`}>
                                                {order.delivery_mode === 'delivery' ? 'LIVRAISON CHANTIER' : 'RETRAIT SUR PLACE'}
                                            </span>
                                        </td>
                                        <td className="py-3.5 px-4">
                                            {getStatusBadge(order.status)}
                                        </td>
                                        <td className="py-3.5 px-4 text-right font-bold text-amber-500">
                                            {money(order.subtotal)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="text-center py-12 border border-dashed border-slate-800 rounded-lg">
                        <span className="text-4xl">📭</span>
                        <h3 className="text-slate-300 font-bold mt-3">Aucune commande reçue</h3>
                        <p className="text-slate-500 text-xs mt-1">Dès qu'un artisan passera commande de matériaux, elle apparaîtra ici.</p>
                    </div>
                )}
            </div>
        </div>
    );
}
