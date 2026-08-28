'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';

interface OrderLitige {
    id: number;
    client_id: number;
    subtotal: number;
    status: string;
    created_at: string;
    client?: {
        name: string;
        phone: string;
    };
}

interface MissionLitige {
    id: number;
    status: string;
    montant_total: number;
    created_at: string;
    client?: {
        name: string;
        phone: string;
    };
    artisan?: {
        name: string;
        phone: string;
    };
    litiges?: Array<{
        id: number;
        motif?: string;
        description?: string;
        statut: string;
        created_at: string;
    }>;
}

interface LitigeData {
    order_litiges: OrderLitige[];
    mission_litiges: MissionLitige[];
}

export default function SupplierLitiges() {
    const [data, setData] = useState<LitigeData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [activeSection, setActiveSection] = useState<'orders' | 'missions'>('orders');

    useEffect(() => {
        const fetchLitiges = async () => {
            try {
                const res = await api.getSupplierLitiges();
                setData(res);
            } catch (err: any) {
                console.error(err);
                setError(err.message || 'Impossible de charger les litiges');
            } finally {
                setLoading(false);
            }
        };

        fetchLitiges();
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

    return (
        <div className="space-y-6">
            {/* Header */}
            <div>
                <h1 className="text-2xl font-bold tracking-tight">Litiges & Réclamations</h1>
                <p className="text-slate-400 text-sm mt-1">
                    Consultez les litiges ouverts ou résolus associés à vos ventes de matériaux et vos chantiers.
                </p>
            </div>

            {/* Toggle tabs */}
            <div className="flex border-b border-slate-800">
                <button
                    onClick={() => setActiveSection('orders')}
                    className={`px-5 py-3 text-sm font-bold border-b-2 transition ${
                        activeSection === 'orders'
                            ? 'border-amber-500 text-amber-500'
                            : 'border-transparent text-slate-400 hover:text-white'
                    }`}
                >
                    Litiges sur commandes ({data?.order_litiges.length || 0})
                </button>
                <button
                    onClick={() => setActiveSection('missions')}
                    className={`px-5 py-3 text-sm font-bold border-b-2 transition ${
                        activeSection === 'missions'
                            ? 'border-amber-500 text-amber-500'
                            : 'border-transparent text-slate-400 hover:text-white'
                    }`}
                >
                    Litiges sur chantiers / J-Codes ({data?.mission_litiges.length || 0})
                </button>
            </div>

            {/* Content section */}
            {activeSection === 'orders' ? (
                /* Order Litiges */
                data?.order_litiges && data.order_litiges.length > 0 ? (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                        {data.order_litiges.map((order) => (
                            <div
                                key={order.id}
                                className="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-lg space-y-4"
                            >
                                <div className="flex items-center justify-between">
                                    <span className="font-extrabold text-white">Commande #{order.id}</span>
                                    <span className="bg-rose-500/10 text-rose-400 border border-rose-500/20 px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider">
                                        Commande Bloquée
                                    </span>
                                </div>

                                <div className="space-y-2">
                                    <div className="flex justify-between text-sm">
                                        <span className="text-slate-500">Client</span>
                                        <span className="font-semibold text-slate-200">{order.client?.name} ({order.client?.phone})</span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="text-slate-500">Date d'ouverture</span>
                                        <span className="text-slate-300">{shortDate(order.created_at)}</span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="text-slate-500">Valeur totale</span>
                                        <span className="font-extrabold text-amber-500">{money(order.subtotal)}</span>
                                    </div>
                                </div>

                                <div className="bg-slate-950/40 border border-slate-850 p-3 rounded-lg text-xs text-slate-400">
                                    ℹ️ Cette commande de livraison a fait l'objet d'un signalement. Les fonds logistiques et matériels sont gelés en séquestre en attente d'arbitrage administratif.
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="text-center py-16 border border-dashed border-slate-850 rounded-xl">
                        <span className="text-5xl">🛡️</span>
                        <h3 className="text-slate-300 font-bold mt-4">Aucun litige de commande</h3>
                        <p className="text-slate-500 text-xs mt-1">Excellent ! Toutes vos commandes directes se déroulent sans encombre.</p>
                    </div>
                )
            ) : (
                /* Mission Litiges */
                data?.mission_litiges && data.mission_litiges.length > 0 ? (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                        {data.mission_litiges.map((mission) => {
                            const litige = mission.litiges?.[0];
                            return (
                                <div
                                    key={mission.id}
                                    className="bg-slate-900 border border-slate-800 rounded-xl p-5 shadow-lg space-y-4"
                                >
                                    <div className="flex items-center justify-between">
                                        <span className="font-extrabold text-white">Chantier #{mission.id}</span>
                                        <span className="bg-rose-500/10 text-rose-400 border border-rose-500/20 px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider">
                                            {litige?.statut === 'ouvert' ? 'Instruction active' : 'Résolu'}
                                        </span>
                                    </div>

                                    <div className="space-y-2">
                                        <div className="flex justify-between text-sm">
                                            <span className="text-slate-500">Client</span>
                                            <span className="font-semibold text-slate-200">{mission.client?.name}</span>
                                        </div>
                                        <div className="flex justify-between text-sm">
                                            <span className="text-slate-500">Artisan</span>
                                            <span className="font-semibold text-slate-200">{mission.artisan?.name}</span>
                                        </div>
                                        {litige?.motif && (
                                            <div className="flex justify-between text-sm">
                                                <span className="text-slate-500">Motif</span>
                                                <span className="font-medium text-slate-300">{litige.motif}</span>
                                            </div>
                                        )}
                                        <div className="flex justify-between text-sm">
                                            <span className="text-slate-500">Total Chantier</span>
                                            <span className="font-extrabold text-amber-500">{money(mission.montant_total)}</span>
                                        </div>
                                    </div>

                                    {litige?.description && (
                                        <div className="bg-slate-950/40 border border-slate-850 p-3 rounded-lg text-xs text-slate-400">
                                            <div className="font-bold text-slate-300 mb-1">Description du litige :</div>
                                            "{litige.description}"
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                ) : (
                    <div className="text-center py-16 border border-dashed border-slate-850 rounded-xl">
                        <span className="text-5xl">🛡️</span>
                        <h3 className="text-slate-300 font-bold mt-4">Aucun litige de chantier</h3>
                        <p className="text-slate-500 text-xs mt-1">Excellent ! Les chantiers fournis via vos J-Codes n'ont pas de litige.</p>
                    </div>
                )
            )}
        </div>
    );
}
