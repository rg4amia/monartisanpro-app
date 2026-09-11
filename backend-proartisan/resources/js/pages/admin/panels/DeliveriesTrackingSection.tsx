import { router } from '@inertiajs/react';
import { useState } from 'react';
import { cn } from '@/lib/utils';
import {
    DeliveryStatusBadge,
    money,
    shortDate,
} from '../shared';
import type { AdminOrder } from '../shared';

interface DeliveriesTrackingSectionProps {
    orders: AdminOrder[];
    onSelectOrder?: (order: AdminOrder) => void;
}

export function DeliveriesTrackingSection({
    orders,
    onSelectOrder,
}: DeliveriesTrackingSectionProps) {
    const [selectedOrder, setSelectedOrder] = useState<AdminOrder | null>(null);
    const [trackingData, setTrackingData] = useState<any>(null);
    const [isLoadingTracking, setIsLoadingTracking] = useState(false);
    const [reassigningId, setReassigningId] = useState<number | null>(null);

    // Active deliveries in transit or assigned
    const activeDeliveries = orders.filter((o) =>
        ['driver_assigned', 'shipping', 'driver_picked_up', 'searching_driver'].includes(o.status)
    );

    const loadLiveTracking = async (order: AdminOrder) => {
        setSelectedOrder(order);
        setIsLoadingTracking(true);
        try {
            const res = await fetch(`/api/v1/orders/${order.id}/tracking`, {
                headers: {
                    Accept: 'application/json',
                },
            });
            if (res.ok) {
                const data = await res.json();
                setTrackingData(data.tracking);
            }
        } catch (e) {
            console.error('Erreur chargement tracking:', e);
        } finally {
            setIsLoadingTracking(false);
        }
    };

    const handleReassign = (orderId: number) => {
        if (!confirm('Êtes-vous sûr de vouloir réaffecter cette course à un nouveau livreur ? Le livreur actuel sera notifié et sanctionné pour retard.')) {
            return;
        }

        setReassigningId(orderId);
        router.post(`/api/v1/orders/${orderId}/reassign`, {
            reason: 'Inactivité détectée par la supervision admin',
        }, {
            preserveScroll: true,
            onFinish: () => {
                setReassigningId(null);
                setSelectedOrder(null);
            },
        });
    };

    return (
        <div className="space-y-6">
            {/* RADAR DES COURSES ACTIVES EN DIRECT */}
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-sm font-extrabold uppercase tracking-wider text-[var(--admin-text)] flex items-center gap-2">
                        <span className="relative flex h-2.5 w-2.5">
                            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                        </span>
                        <span>Radar Télémétrique & Watchdog Livreurs ({activeDeliveries.length} active(s))</span>
                    </h3>
                    <p className="text-xs text-[var(--admin-text-soft)]">
                        Suivi en direct des tournées matériaux, vitesse GPS OSRM, horodatages d'enlèvement et surveillance anti-retard.
                    </p>
                </div>
            </div>

            {/* GRILLE DES COURSES EN MOUVEMENT */}
            {activeDeliveries.length === 0 ? (
                <div className="p-6 rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 text-center text-xs text-slate-500">
                    🛵 Aucune course de matériaux active en transit pour le moment.
                </div>
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    {activeDeliveries.map((order) => {
                        const isSearching = order.status === 'searching_driver';

                        return (
                            <div
                                key={order.id}
                                className={cn(
                                    'p-4 rounded-2xl border bg-white shadow-sm transition hover:shadow-md space-y-3 relative overflow-hidden',
                                    isSearching ? 'border-amber-200 bg-amber-50/30' : 'border-slate-200'
                                )}
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="font-bold text-xs text-slate-900">Course #{order.id}</span>
                                            <DeliveryStatusBadge status={order.status} />
                                        </div>
                                        <div className="text-[10px] text-slate-400 mt-0.5 font-mono">
                                            {shortDate(order.created_at)}
                                        </div>
                                    </div>
                                    <span className="font-bold text-xs text-[#8a6b3d] font-mono">
                                        {money(order.delivery_cost || 1500)}
                                    </span>
                                </div>

                                {/* ACTEURS DE LA COURSE */}
                                <div className="space-y-1 text-xs border-y border-slate-100 py-2">
                                    <div className="flex items-center justify-between text-[11px]">
                                        <span className="text-slate-500">🏬 Quincaillerie :</span>
                                        <span className="font-semibold text-slate-800 truncate max-w-[140px]">
                                            {order.supplier?.name ?? 'Fournisseur'}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between text-[11px]">
                                        <span className="text-slate-500">👷 Chantier :</span>
                                        <span className="font-semibold text-slate-800 truncate max-w-[140px]">
                                            {order.client?.name ?? 'Client'}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between text-[11px]">
                                        <span className="text-slate-500">🛵 Coursier :</span>
                                        {order.driver ? (
                                            <span className="font-bold text-emerald-800 flex items-center gap-1">
                                                <span>{order.driver.name}</span>
                                                <a
                                                    href={`tel:${order.driver.phone}`}
                                                    className="text-[10px] text-blue-600 hover:underline"
                                                    title="Appeler"
                                                >
                                                    📞
                                                </a>
                                            </span>
                                        ) : (
                                            <span className="text-amber-700 italic font-semibold animate-pulse">
                                                Radar en cours...
                                            </span>
                                        )}
                                    </div>
                                </div>

                                {/* CODES & STATUT */}
                                <div className="flex items-center justify-between text-[10px] font-mono text-slate-600">
                                    <span>Code Retrait : <strong className="text-slate-900">{order.pickup_code}</strong></span>
                                    <span>Code Réception : <strong className="text-slate-900">{order.reception_code}</strong></span>
                                </div>

                                {/* ACTIONS RADAR */}
                                <div className="flex items-center justify-between gap-2 pt-1">
                                    <button
                                        type="button"
                                        onClick={() => loadLiveTracking(order)}
                                        className="w-full py-1.5 px-3 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-[11px] transition inline-flex items-center justify-center gap-1.5 shadow-sm"
                                    >
                                        <span>📍</span>
                                        <span>Télémétrie & OSRM</span>
                                    </button>

                                    {onSelectOrder && (
                                        <button
                                            type="button"
                                            onClick={() => onSelectOrder(order)}
                                            className="py-1.5 px-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-100 text-slate-700 font-bold text-[11px] transition"
                                            title="Consulter le dossier complet"
                                        >
                                            👁️
                                        </button>
                                    )}

                                    {order.driver && !isSearching && (
                                        <button
                                            type="button"
                                            onClick={() => handleReassign(order.id)}
                                            disabled={reassigningId === order.id}
                                            className="py-1.5 px-2.5 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-[10px] transition whitespace-nowrap"
                                            title="Réassigner un autre livreur"
                                        >
                                            {reassigningId === order.id ? '...' : '🚨 Réaffecter'}
                                        </button>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}

            {/* MODAL TÉLÉMÉTRIE DÉTAILLÉE & OSRM */}
            {selectedOrder && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
                    <div className="relative w-full max-w-xl bg-white rounded-3xl shadow-2xl border border-[var(--admin-border)] overflow-hidden">
                        {/* Header */}
                        <div className="flex items-center justify-between p-5 border-b border-[var(--admin-border)] bg-gradient-to-r from-slate-900 to-slate-800 text-white">
                            <div>
                                <div className="flex items-center gap-2">
                                    <h3 className="text-base font-black">
                                        Suivi Télémétrique Course #{selectedOrder.id}
                                    </h3>
                                    <DeliveryStatusBadge status={selectedOrder.status} />
                                </div>
                                <p className="text-xs text-slate-300 mt-0.5">
                                    Itinéraire routier réel OSRM et position GPS du coursier.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setSelectedOrder(null)}
                                className="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white font-bold flex items-center justify-center text-xs transition"
                            >
                                ✕
                            </button>
                        </div>

                        {/* Contenu */}
                        <div className="p-5 space-y-4 text-xs max-h-[75vh] overflow-y-auto">
                            {isLoadingTracking ? (
                                <div className="py-12 text-center text-slate-500 animate-pulse">
                                    🛰️ Interrogation des satellites et du moteur OSRM...
                                </div>
                            ) : (
                                <>
                                    {/* Données OSRM */}
                                    {trackingData?.route ? (
                                        <div className="p-4 rounded-2xl border border-blue-200 bg-blue-50/50 space-y-2">
                                            <div className="flex items-center justify-between">
                                                <span className="font-bold text-blue-900 text-xs flex items-center gap-1.5">
                                                    <span>🗺️ Itinéraire Routier OSRM</span>
                                                    {trackingData.route.is_fallback && (
                                                        <span className="text-[9px] px-1.5 py-0.5 rounded bg-amber-200 text-amber-900 font-bold">
                                                            Repli Haversine
                                                        </span>
                                                    )}
                                                </span>
                                                <span className="font-mono font-bold text-blue-800 text-sm">
                                                    {trackingData.route.distance_km} km
                                                </span>
                                            </div>
                                            <div className="grid grid-cols-2 gap-3 text-xs pt-1 border-t border-blue-200/60">
                                                <div>
                                                    <span className="text-[10px] text-blue-600 uppercase font-bold block">Durée Estimée</span>
                                                    <span className="font-bold text-slate-800">
                                                        ~ {trackingData.route.duration_min} minutes
                                                    </span>
                                                </div>
                                                <div>
                                                    <span className="text-[10px] text-blue-600 uppercase font-bold block">Points de Trajet</span>
                                                    <span className="font-bold text-slate-800 font-mono">
                                                        {trackingData.route.geometry?.length ?? 0} waypoints
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="p-3 rounded-xl bg-slate-50 border text-slate-500 text-center">
                                            Coordonnées GPS boutique/chantier en cours d'acquisition pour le tracé.
                                        </div>
                                    )}

                                    {/* Position actuelle du livreur */}
                                    <div className="p-4 rounded-2xl border border-emerald-200 bg-emerald-50/40 space-y-2">
                                        <div className="flex items-center justify-between">
                                            <span className="font-bold text-emerald-900 text-xs flex items-center gap-1.5">
                                                <span>🛵 Position Livreur en Direct</span>
                                            </span>
                                            {trackingData?.driver?.position ? (
                                                <span className="text-[10px] font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">
                                                    En mouvement
                                                </span>
                                            ) : (
                                                <span className="text-[10px] text-slate-400 italic">Non émise</span>
                                            )}
                                        </div>
                                        {trackingData?.driver?.position ? (
                                            <div className="grid grid-cols-3 gap-2 text-xs pt-1 font-mono">
                                                <div>
                                                    <span className="text-[10px] text-slate-500 block">Latitude</span>
                                                    <span className="font-bold text-slate-800">{trackingData.driver.position.lat.toFixed(5)}</span>
                                                </div>
                                                <div>
                                                    <span className="text-[10px] text-slate-500 block">Longitude</span>
                                                    <span className="font-bold text-slate-800">{trackingData.driver.position.lng.toFixed(5)}</span>
                                                </div>
                                                <div>
                                                    <span className="text-[10px] text-slate-500 block">Vitesse</span>
                                                    <span className="font-bold text-slate-800">
                                                        {trackingData.driver.position.speed ? `${trackingData.driver.position.speed} km/h` : '—'}
                                                    </span>
                                                </div>
                                            </div>
                                        ) : (
                                            <p className="text-[11px] text-slate-500 italic">
                                                Le livreur n'a pas encore émis de coordonnées récentes via l'application mobile.
                                            </p>
                                        )}
                                    </div>

                                    {/* Photos de double-validation */}
                                    <div className="space-y-2">
                                        <span className="font-bold text-slate-800 text-xs block">
                                            Preuves Photographiques Double-Validation (Règle 13)
                                        </span>
                                        <div className="grid grid-cols-2 gap-3">
                                            <div className="p-3 rounded-2xl border bg-slate-50 text-center space-y-1">
                                                <span className="text-[10px] font-bold text-slate-600 block uppercase">
                                                    📸 Chargement Boutique
                                                </span>
                                                {selectedOrder.pickup_photo_url ? (
                                                    <a href={selectedOrder.pickup_photo_url} target="_blank" rel="noreferrer">
                                                        <img
                                                            src={selectedOrder.pickup_photo_url}
                                                            alt="Pickup"
                                                            className="w-full h-28 object-cover rounded-xl border mt-1 hover:opacity-90"
                                                        />
                                                    </a>
                                                ) : (
                                                    <div className="h-28 flex items-center justify-center text-[10px] text-slate-400 italic bg-white rounded-xl border">
                                                        En attente de chargement
                                                    </div>
                                                )}
                                            </div>

                                            <div className="p-3 rounded-2xl border bg-slate-50 text-center space-y-1">
                                                <span className="text-[10px] font-bold text-slate-600 block uppercase">
                                                    📸 Déchargement Chantier
                                                </span>
                                                {selectedOrder.delivery_photo_url ? (
                                                    <a href={selectedOrder.delivery_photo_url} target="_blank" rel="noreferrer">
                                                        <img
                                                            src={selectedOrder.delivery_photo_url}
                                                            alt="Delivery"
                                                            className="w-full h-28 object-cover rounded-xl border mt-1 hover:opacity-90"
                                                        />
                                                    </a>
                                                ) : (
                                                    <div className="h-28 flex items-center justify-center text-[10px] text-slate-400 italic bg-white rounded-xl border">
                                                        En attente de déchargement
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </>
                            )}
                        </div>

                        {/* Footer */}
                        <div className="flex items-center justify-between p-4 border-t border-[var(--admin-border)] bg-slate-50">
                            <span className="text-[11px] text-slate-500 font-mono">
                                Canal SSE : order.{selectedOrder.id}
                            </span>
                            <button
                                type="button"
                                onClick={() => setSelectedOrder(null)}
                                className="px-4 py-2 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-slate-800 transition"
                            >
                                Fermer
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
