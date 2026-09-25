import { router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { cn } from '@/lib/utils';
import {
    DeliveryStatusBadge,
    money,
    shortDate,
    useConfirm,
} from '../shared';
import type { AdminOrder } from '../shared';
import { ABIDJAN_COMMUNES_GEODATA } from './ivoryCoastGeoData';

export interface FleetSummary {
    total_drivers: number;
    online_drivers: number;
    in_transit: number;
    available: number;
    stalled_alerts: number;
    active_orders_count: number;
    updated_at: string;
}

export interface FleetDriver {
    id: number;
    name: string;
    phone: string;
    avatar_url?: string | null;
    status: 'delivering' | 'en_route_pickup' | 'available' | 'offline';
    is_online: boolean;
    is_stalled: boolean;
    stalled_reason?: string | null;
    position?: { lat: number; lng: number } | null;
    speed_kmh?: number | null;
    heading?: number | null;
    battery_level?: number | null;
    last_ping_at?: string | null;
    minutes_since_ping?: number | null;
    estimated_commune?: string | null;
    active_order?: {
        id: number;
        status: string;
        order_group_id?: string | null;
        delivery_cost: number;
        total_amount: number;
        supplier_name?: string | null;
        supplier_position?: { lat: number; lng: number } | null;
        client_name?: string | null;
        client_position?: { lat: number; lng: number } | null;
        pickup_code?: string;
        reception_code?: string;
    } | null;
}

export interface FleetOverview {
    summary: FleetSummary;
    drivers: FleetDriver[];
}

export interface DeliveriesTrackingSectionProps {
    orders: AdminOrder[];
    onSelectOrder?: (order: AdminOrder) => void;
    fleetOverview?: FleetOverview;
}

function projectGpsToSvg(lat?: number | null, lng?: number | null, communeId?: string | null): [number, number] {
    if (lat != null && lng != null && lat > 5.15 && lat < 5.60 && lng > -4.40 && lng < -3.80) {
        const lngMin = -4.25;
        const lngMax = -3.85;
        const latMin = 5.23;
        const latMax = 5.50;

        const x = Math.max(35, Math.min(465, ((lng - lngMin) / (lngMax - lngMin)) * 420 + 35));
        const y = Math.max(35, Math.min(415, ((latMax - lat) / (latMax - latMin)) * 370 + 35));
        return [Math.round(x), Math.round(y)];
    }

    if (communeId) {
        const found = ABIDJAN_COMMUNES_GEODATA.find(
            (c) => c.id.toLowerCase() === communeId.toLowerCase() || c.name.toLowerCase() === communeId.toLowerCase()
        );
        if (found) {
            return found.center;
        }
    }

    return [240, 220];
}

export function DeliveriesTrackingSection({
    orders,
    onSelectOrder,
    fleetOverview,
}: DeliveriesTrackingSectionProps) {
    const [viewMode, setViewMode] = useState<'radar' | 'fleet_map'>('radar');
    const [selectedOrder, setSelectedOrder] = useState<AdminOrder | null>(null);
    const [trackingData, setTrackingData] = useState<any>(null);
    const [isLoadingTracking, setIsLoadingTracking] = useState(false);
    const [reassigningId, setReassigningId] = useState<number | null>(null);
    const { confirm: askConfirm, dialog: confirmDialog } = useConfirm();

    // Gestion de la Flotte en direct
    const [fleetData, setFleetData] = useState<FleetOverview | null>(fleetOverview || null);
    const [isLoadingFleet, setIsLoadingFleet] = useState(false);
    const [selectedDriver, setSelectedDriver] = useState<FleetDriver | null>(null);
    const [statusFilter, setStatusFilter] = useState<'all' | 'delivering' | 'available' | 'stalled'>('all');
    const [communeFilter, setCommuneFilter] = useState<string>('all');
    const [lastUpdated, setLastUpdated] = useState<string>(
        fleetOverview?.summary?.updated_at || new Date().toISOString()
    );

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

    const handleReassign = async (orderId: number) => {
        const confirmed = await askConfirm({
            title: 'Réaffecter la course',
            message: 'Réaffecter cette course à un nouveau livreur ? Le livreur actuel sera notifié et sanctionné pour retard.',
            confirmLabel: 'Réaffecter',
            tone: 'danger',
        });
        if (!confirmed) return;

        setReassigningId(orderId);
        router.post(
            `/api/v1/orders/${orderId}/reassign`,
            {
                reason: 'Inactivité détectée par la supervision admin',
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setReassigningId(null);
                    setSelectedOrder(null);
                    if (selectedDriver) {
                        fetchFleet(communeFilter);
                        setSelectedDriver(null);
                    }
                },
            }
        );
    };

    const fetchFleet = async (commune = 'all') => {
        setIsLoadingFleet(true);
        try {
            const query = commune !== 'all' ? `?commune=${encodeURIComponent(commune)}` : '';
            const res = await fetch(`/api/v1/deliveries/fleet-map${query}`, {
                headers: { Accept: 'application/json' },
            });
            if (res.ok) {
                const json = await res.json();
                if (json.data) {
                    setFleetData(json.data);
                    setLastUpdated(json.data.summary?.updated_at || new Date().toISOString());
                }
            }
        } catch (e) {
            console.error('Erreur actualisation flotte:', e);
        } finally {
            setIsLoadingFleet(false);
        }
    };

    const handleCommuneFilterChange = (commune: string) => {
        setCommuneFilter(commune);
        fetchFleet(commune);
    };

    // Filtrage des livreurs de la carte
    const filteredDrivers = useMemo(() => {
        if (!fleetData?.drivers) return [];
        return fleetData.drivers.filter((d) => {
            if (statusFilter === 'delivering') {
                return d.status === 'delivering' || d.status === 'en_route_pickup';
            }
            if (statusFilter === 'available') {
                return d.status === 'available';
            }
            if (statusFilter === 'stalled') {
                return d.is_stalled;
            }
            return true;
        });
    }, [fleetData, statusFilter]);

    return (
        <div className="space-y-6">
            {/* EN-TÊTE & COMMUTATEUR DE VUE */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-[var(--admin-border)] pb-4">
                <div>
                    <h3 className="text-sm font-extrabold uppercase tracking-wider text-[var(--admin-text)] flex items-center gap-2">
                        <span className="relative flex h-2.5 w-2.5">
                            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                        </span>
                        <span>Supervision & Télémétrie Flotte Livreur</span>
                    </h3>
                    <p className="text-xs text-[var(--admin-text-soft)]">
                        Radar en direct, carte interactive Grand Abidjan, vitesse GPS OSRM et surveillance anti-retard.
                    </p>
                </div>

                {/* Commutateur Vue Radar vs Carte Flotte */}
                <div className="inline-flex rounded-2xl bg-[var(--admin-border)]/40 p-1 border border-[var(--admin-border)]">
                    <button
                        type="button"
                        onClick={() => setViewMode('radar')}
                        className={cn(
                            'px-3.5 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5',
                            viewMode === 'radar'
                                ? 'bg-[var(--admin-panel-strong)] text-[var(--admin-text)] shadow-sm'
                                : 'text-[var(--admin-text-soft)] hover:text-[var(--admin-text)]'
                        )}
                    >
                        <span>📡</span>
                        <span>Radar & Courses ({activeDeliveries.length})</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => {
                            setViewMode('fleet_map');
                            if (!fleetData) {
                                fetchFleet();
                            }
                        }}
                        className={cn(
                            'px-3.5 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5',
                            viewMode === 'fleet_map'
                                ? 'bg-slate-900 text-white shadow-sm'
                                : 'text-[var(--admin-text-soft)] hover:text-[var(--admin-text)]'
                        )}
                    >
                        <span>🗺️</span>
                        <span>Carte Flotte en Direct</span>
                        {fleetData?.summary?.stalled_alerts ? (
                            <span className="h-2 w-2 rounded-full bg-rose-500 animate-pulse"></span>
                        ) : null}
                    </button>
                </div>
            </div>

            {/* ══════════════════════════════════════════════════════════════════════
                VUE 1 : RADAR & GRILLE DES COURSES EN MOUVEMENT
            ══════════════════════════════════════════════════════════════════════ */}
            {viewMode === 'radar' && (
                <>
                    {activeDeliveries.length === 0 ? (
                        <div className="p-6 rounded-2xl border border-dashed border-[var(--admin-border)] bg-[var(--admin-panel)] text-center text-xs text-[var(--admin-text-soft)]">
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
                                            'p-4 rounded-2xl border bg-[var(--admin-panel-strong)] shadow-sm transition hover:shadow-md space-y-3 relative overflow-hidden',
                                            isSearching ? 'border-amber-200 bg-amber-50/30' : 'border-[var(--admin-border)]'
                                        )}
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <div>
                                                <div className="flex items-center gap-2 flex-wrap">
                                                    <span className="font-bold text-xs text-[var(--admin-text)]">Course #{order.id}</span>
                                                    <DeliveryStatusBadge status={order.status} />
                                                    {order.order_group_id && (
                                                        <span
                                                            className="inline-flex items-center gap-0.5 text-[9px] font-mono bg-purple-50 text-purple-700 px-1.5 py-0.5 rounded border border-purple-200 font-bold"
                                                            title="Commande issue d'un panier multi-fournisseurs"
                                                        >
                                                            📦 {order.order_group_id}
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="text-[10px] text-[var(--admin-muted)] mt-0.5 font-mono">
                                                    {shortDate(order.created_at)}
                                                </div>
                                            </div>
                                            <span className="font-bold text-xs text-[#8a6b3d] font-mono">
                                                {money(order.delivery_cost || 1500)}
                                            </span>
                                        </div>

                                        {/* ACTEURS DE LA COURSE */}
                                        <div className="space-y-1 text-xs border-y border-[var(--admin-border)] py-2">
                                            <div className="flex items-center justify-between text-[11px]">
                                                <span className="text-[var(--admin-muted)]">🏬 Quincaillerie :</span>
                                                <span className="font-semibold text-[var(--admin-text)] truncate max-w-[140px]">
                                                    {order.supplier?.name ?? 'Fournisseur'}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between text-[11px]">
                                                <span className="text-[var(--admin-muted)]">👷 Chantier :</span>
                                                <span className="font-semibold text-[var(--admin-text)] truncate max-w-[140px]">
                                                    {order.client?.name ?? 'Client'}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between text-[11px]">
                                                <span className="text-[var(--admin-muted)]">🛵 Coursier :</span>
                                                {order.driver ? (
                                                    <span className="font-bold text-emerald-700 flex items-center gap-1">
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
                                        <div className="flex items-center justify-between text-[10px] font-mono text-[var(--admin-muted)]">
                                            <span>
                                                Code Retrait : <strong className="text-[var(--admin-text)]">{order.pickup_code}</strong>
                                            </span>
                                            <span>
                                                Code Réception : <strong className="text-[var(--admin-text)]">{order.reception_code}</strong>
                                            </span>
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
                                                    className="py-1.5 px-2 rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel)] hover:bg-[var(--admin-panel-strong)] text-[var(--admin-text)] font-bold text-[11px] transition"
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
                </>
            )}

            {/* ══════════════════════════════════════════════════════════════════════
                VUE 2 : CARTE FLOTTE EN DIRECT (GRAND ABIDJAN)
            ══════════════════════════════════════════════════════════════════════ */}
            {viewMode === 'fleet_map' && (
                <div className="space-y-4 animate-fade-in">
                    {/* BARRE DE KPI FLOTTE */}
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div className="p-3.5 rounded-2xl border border-slate-200 bg-[var(--admin-panel-strong)] shadow-sm">
                            <span className="text-[10px] uppercase font-bold text-[var(--admin-muted)] block">
                                🛵 Flotte Connectée
                            </span>
                            <div className="flex items-baseline gap-1 mt-0.5">
                                <span className="text-xl font-black text-[var(--admin-text)]">
                                    {fleetData?.summary?.online_drivers ?? 0}
                                </span>
                                <span className="text-xs text-[var(--admin-muted)]">
                                    / {fleetData?.summary?.total_drivers ?? 0}
                                </span>
                            </div>
                        </div>

                        <div className="p-3.5 rounded-2xl border border-emerald-200 bg-emerald-50/40 shadow-sm">
                            <span className="text-[10px] uppercase font-bold text-emerald-800 block">
                                📦 En Livraison (Transit)
                            </span>
                            <span className="text-xl font-black text-emerald-950 mt-0.5 block">
                                {fleetData?.summary?.in_transit ?? 0}
                            </span>
                        </div>

                        <div className="p-3.5 rounded-2xl border border-blue-200 bg-blue-50/40 shadow-sm">
                            <span className="text-[10px] uppercase font-bold text-blue-800 block">
                                🟢 Disponibles
                            </span>
                            <span className="text-xl font-black text-blue-950 mt-0.5 block">
                                {fleetData?.summary?.available ?? 0}
                            </span>
                        </div>

                        <div className="p-3.5 rounded-2xl border border-rose-200 bg-rose-50/40 shadow-sm">
                            <span className="text-[10px] uppercase font-bold text-rose-800 flex items-center justify-between">
                                <span>🚨 Alertes Inactivité</span>
                                {(fleetData?.summary?.stalled_alerts ?? 0) > 0 && (
                                    <span className="h-2 w-2 rounded-full bg-rose-500 animate-ping"></span>
                                )}
                            </span>
                            <span className="text-xl font-black text-rose-700 mt-0.5 block">
                                {fleetData?.summary?.stalled_alerts ?? 0}
                            </span>
                        </div>
                    </div>

                    {/* BARRE DE FILTRES ET RAFRAÎCHISSEMENT */}
                    <div className="flex flex-wrap items-center justify-between gap-3 p-3 rounded-2xl bg-[var(--admin-panel)] border border-[var(--admin-border)]">
                        {/* Filtres de statut */}
                        <div className="flex items-center gap-1.5 flex-wrap">
                            <button
                                type="button"
                                onClick={() => setStatusFilter('all')}
                                className={cn(
                                    'px-2.5 py-1 rounded-xl text-xs font-bold transition border',
                                    statusFilter === 'all'
                                        ? 'bg-slate-900 text-white border-slate-900'
                                        : 'bg-[var(--admin-panel-strong)] text-[var(--admin-text-soft)] border-[var(--admin-border)] hover:bg-black/5'
                                )}
                            >
                                Tous ({fleetData?.drivers?.length ?? 0})
                            </button>
                            <button
                                type="button"
                                onClick={() => setStatusFilter('delivering')}
                                className={cn(
                                    'px-2.5 py-1 rounded-xl text-xs font-bold transition border',
                                    statusFilter === 'delivering'
                                        ? 'bg-emerald-700 text-white border-emerald-700'
                                        : 'bg-[var(--admin-panel-strong)] text-emerald-800 border-[var(--admin-border)] hover:bg-emerald-50'
                                )}
                            >
                                En Transit ({fleetData?.summary?.in_transit ?? 0})
                            </button>
                            <button
                                type="button"
                                onClick={() => setStatusFilter('available')}
                                className={cn(
                                    'px-2.5 py-1 rounded-xl text-xs font-bold transition border',
                                    statusFilter === 'available'
                                        ? 'bg-blue-700 text-white border-blue-700'
                                        : 'bg-[var(--admin-panel-strong)] text-blue-800 border-[var(--admin-border)] hover:bg-blue-50'
                                )}
                            >
                                Disponibles ({fleetData?.summary?.available ?? 0})
                            </button>
                            <button
                                type="button"
                                onClick={() => setStatusFilter('stalled')}
                                className={cn(
                                    'px-2.5 py-1 rounded-xl text-xs font-bold transition border',
                                    statusFilter === 'stalled'
                                        ? 'bg-rose-600 text-white border-rose-600'
                                        : 'bg-[var(--admin-panel-strong)] text-rose-700 border-[var(--admin-border)] hover:bg-rose-50'
                                )}
                            >
                                En Alerte ({fleetData?.summary?.stalled_alerts ?? 0})
                            </button>
                        </div>

                        {/* Filtre par commune & Bouton Actualiser */}
                        <div className="flex items-center gap-2">
                            <select
                                value={communeFilter}
                                onChange={(e) => handleCommuneFilterChange(e.target.value)}
                                className="text-xs font-medium rounded-xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] px-2.5 py-1 text-[var(--admin-text)]"
                            >
                                <option value="all">Toutes les communes</option>
                                {ABIDJAN_COMMUNES_GEODATA.map((c) => (
                                    <option key={c.id} value={c.name}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>

                            <button
                                type="button"
                                onClick={() => fetchFleet(communeFilter)}
                                disabled={isLoadingFleet}
                                className="px-3 py-1 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition inline-flex items-center gap-1.5 shadow-sm disabled:opacity-50"
                            >
                                <span className={cn(isLoadingFleet && 'animate-spin')}>🔄</span>
                                <span>{isLoadingFleet ? 'Actualisation...' : 'Actualiser'}</span>
                            </button>
                        </div>
                    </div>

                    {/* CARTE SVG INTERACTIVE GRAND ABIDJAN & PANNEAU DÉTAIL */}
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                        {/* 🗺️ CARTE SVG DU GRAND ABIDJAN (2 COLONNES) */}
                        <div className="lg:col-span-2 rounded-3xl border border-[var(--admin-border)] bg-gradient-to-b from-slate-900 via-slate-900 to-slate-950 p-4 relative overflow-hidden shadow-inner">
                            <div className="flex items-center justify-between text-slate-300 text-xs mb-2">
                                <span className="font-bold flex items-center gap-1.5">
                                    <span>📍 Métropole du Grand Abidjan</span>
                                    <span className="text-[10px] text-slate-400 font-mono">
                                        (Lagune Ébrié & 13 Communes)
                                    </span>
                                </span>
                                <span className="text-[10px] text-slate-400 font-mono">
                                    Mis à jour : {new Date(lastUpdated).toLocaleTimeString()}
                                </span>
                            </div>

                            {/* SVG CANVAS */}
                            <div className="relative w-full aspect-[500/420] select-none">
                                <svg
                                    viewBox="0 0 500 450"
                                    className="w-full h-full drop-shadow-lg"
                                    xmlns="http://www.w3.org/2000/svg"
                                >
                                    <defs>
                                        <linearGradient id="lagoonGradient" x1="0" y1="0" x2="1" y2="0">
                                            <stop offset="0%" stopColor="#0369a1" stopOpacity="0.3" />
                                            <stop offset="50%" stopColor="#0284c7" stopOpacity="0.45" />
                                            <stop offset="100%" stopColor="#0369a1" stopOpacity="0.3" />
                                        </linearGradient>
                                        <radialGradient id="alertGlow" cx="50%" cy="50%" r="50%">
                                            <stop offset="0%" stopColor="#ef4444" stopOpacity="0.8" />
                                            <stop offset="100%" stopColor="#ef4444" stopOpacity="0" />
                                        </radialGradient>
                                    </defs>

                                    {/* LAGUNE ÉBRIÉ (Tracé stylisé séparateur Nord / Sud) */}
                                    <path
                                        d="M30 240 C120 220 180 235 240 230 C300 225 360 250 480 260 C480 300 370 290 270 265 C200 280 120 270 30 255 Z"
                                        fill="url(#lagoonGradient)"
                                        className="pointer-events-none"
                                    />
                                    <text x="70" y="248" fill="#38bdf8" opacity="0.4" fontSize="9" fontWeight="bold" fontFamily="monospace">
                                        LAGUNE ÉBRIÉ
                                    </text>

                                    {/* COMMUNES EN ARRIÈRE-PLAN */}
                                    {ABIDJAN_COMMUNES_GEODATA.map((commune) => {
                                        const isSelected = selectedDriver?.estimated_commune?.toLowerCase() === commune.name.toLowerCase();
                                        return (
                                            <g key={commune.id}>
                                                <path
                                                    d={commune.path}
                                                    fill={isSelected ? '#1e293b' : '#0f172a'}
                                                    stroke={isSelected ? '#38bdf8' : '#334155'}
                                                    strokeWidth={isSelected ? '2' : '1'}
                                                    strokeDasharray={isSelected ? 'none' : '3 2'}
                                                    className="transition-colors duration-200"
                                                />
                                                <text
                                                    x={commune.center[0]}
                                                    y={commune.center[1]}
                                                    textAnchor="middle"
                                                    fill="#94a3b8"
                                                    fontSize="9"
                                                    fontWeight="bold"
                                                    className="pointer-events-none select-none"
                                                >
                                                    {commune.name}
                                                </text>
                                            </g>
                                        );
                                    })}

                                    {/* LIAISON QUINCAILLERIE -> LIVREUR -> CHANTIER SI CHAUFFEUR SÉLECTIONNÉ */}
                                    {selectedDriver?.active_order && (
                                        <g className="animate-fade-in pointer-events-none">
                                            {(() => {
                                                const driverPos = projectGpsToSvg(
                                                    selectedDriver.position?.lat,
                                                    selectedDriver.position?.lng,
                                                    selectedDriver.estimated_commune
                                                );
                                                const supPos = projectGpsToSvg(
                                                    selectedDriver.active_order.supplier_position?.lat,
                                                    selectedDriver.active_order.supplier_position?.lng,
                                                    'Plateau'
                                                );
                                                const cliPos = projectGpsToSvg(
                                                    selectedDriver.active_order.client_position?.lat,
                                                    selectedDriver.active_order.client_position?.lng,
                                                    'Cocody'
                                                );

                                                return (
                                                    <>
                                                        {/* Ligne Boutique -> Livreur */}
                                                        <line
                                                            x1={supPos[0]}
                                                            y1={supPos[1]}
                                                            x2={driverPos[0]}
                                                            y2={driverPos[1]}
                                                            stroke="#f59e0b"
                                                            strokeWidth="2"
                                                            strokeDasharray="4 3"
                                                        />
                                                        {/* Ligne Livreur -> Chantier */}
                                                        <line
                                                            x1={driverPos[0]}
                                                            y1={driverPos[1]}
                                                            x2={cliPos[0]}
                                                            y2={cliPos[1]}
                                                            stroke="#10b981"
                                                            strokeWidth="2"
                                                            strokeDasharray="4 3"
                                                        />
                                                        {/* Marqueur Boutique */}
                                                        <circle cx={supPos[0]} cy={supPos[1]} r="6" fill="#f59e0b" />
                                                        <text x={supPos[0]} y={supPos[1] - 8} fill="#fde68a" fontSize="8" fontWeight="bold" textAnchor="middle">
                                                            🏬 Magasin
                                                        </text>
                                                        {/* Marqueur Chantier */}
                                                        <circle cx={cliPos[0]} cy={cliPos[1]} r="6" fill="#10b981" />
                                                        <text x={cliPos[0]} y={cliPos[1] - 8} fill="#a7f3d0" fontSize="8" fontWeight="bold" textAnchor="middle">
                                                            👷 Chantier
                                                        </text>
                                                    </>
                                                );
                                            })()}
                                        </g>
                                    )}

                                    {/* 🛵 MARQUEURS DES LIVREURS SUR LA CARTE */}
                                    {filteredDrivers.map((driver) => {
                                        const [cx, cy] = projectGpsToSvg(
                                            driver.position?.lat,
                                            driver.position?.lng,
                                            driver.estimated_commune
                                        );
                                        const isSelected = selectedDriver?.id === driver.id;

                                        let pinFill = '#3b82f6'; // available (blue)
                                        if (driver.is_stalled) {
                                            pinFill = '#ef4444'; // stalled (red)
                                        } else if (driver.status === 'delivering') {
                                            pinFill = '#10b981'; // delivering (emerald)
                                        } else if (driver.status === 'en_route_pickup') {
                                            pinFill = '#f59e0b'; // pickup (amber)
                                        } else if (driver.status === 'offline') {
                                            pinFill = '#64748b'; // offline (slate)
                                        }

                                        return (
                                            <g
                                                key={driver.id}
                                                onClick={() => setSelectedDriver(driver)}
                                                className="cursor-pointer group"
                                            >
                                                {/* Halo d'alerte rouge pulsant si bloqué */}
                                                {driver.is_stalled && (
                                                    <circle
                                                        cx={cx}
                                                        cy={cy}
                                                        r="18"
                                                        fill="url(#alertGlow)"
                                                        className="animate-ping"
                                                    />
                                                )}

                                                {/* Anneau de sélection active */}
                                                {isSelected && (
                                                    <circle
                                                        cx={cx}
                                                        cy={cy}
                                                        r="14"
                                                        fill="none"
                                                        stroke="#38bdf8"
                                                        strokeWidth="2.5"
                                                    />
                                                )}

                                                {/* Disque principal du livreur */}
                                                <circle
                                                    cx={cx}
                                                    cy={cy}
                                                    r="9"
                                                    fill={pinFill}
                                                    stroke="#ffffff"
                                                    strokeWidth="1.5"
                                                    className="transition-transform group-hover:scale-125 origin-center"
                                                />

                                                {/* Symbole du coursier */}
                                                <text
                                                    x={cx}
                                                    y={cy + 3}
                                                    textAnchor="middle"
                                                    fill="#ffffff"
                                                    fontSize="8"
                                                    fontWeight="black"
                                                    className="pointer-events-none select-none"
                                                >
                                                    {driver.is_stalled ? '!' : '🛵'}
                                                </text>

                                                {/* Étiquette nom & vitesse */}
                                                <text
                                                    x={cx}
                                                    y={cy + 18}
                                                    textAnchor="middle"
                                                    fill="#ffffff"
                                                    fontSize="8"
                                                    fontWeight="bold"
                                                    className="pointer-events-none select-none drop-shadow"
                                                >
                                                    {driver.name.split(' ')[0]}
                                                    {driver.speed_kmh ? ` (${Math.round(driver.speed_kmh)}k)` : ''}
                                                </text>
                                            </g>
                                        );
                                    })}
                                </svg>
                            </div>

                            {/* LÉGENDE DE LA CARTE */}
                            <div className="flex flex-wrap items-center justify-between text-[10px] text-slate-300 pt-2 border-t border-slate-800 gap-2">
                                <div className="flex items-center gap-3">
                                    <span className="flex items-center gap-1">
                                        <span className="h-2 w-2 rounded-full bg-emerald-500"></span> En livraison
                                    </span>
                                    <span className="flex items-center gap-1">
                                        <span className="h-2 w-2 rounded-full bg-amber-500"></span> En route magasin
                                    </span>
                                    <span className="flex items-center gap-1">
                                        <span className="h-2 w-2 rounded-full bg-blue-500"></span> Disponible
                                    </span>
                                    <span className="flex items-center gap-1">
                                        <span className="h-2 w-2 rounded-full bg-rose-500 animate-pulse"></span> Alerte inactivité
                                    </span>
                                </div>
                                <span className="font-mono text-[9px] text-slate-400">
                                    {filteredDrivers.length} livreur(s) affiché(s)
                                </span>
                            </div>
                        </div>

                        {/* 📋 PANNEAU LATÉRAL : DÉTAIL DU COURSIER SÉLECTIONNÉ */}
                        <div className="rounded-3xl border border-[var(--admin-border)] bg-[var(--admin-panel-strong)] p-4 shadow-sm flex flex-col justify-between">
                            {selectedDriver ? (
                                <div className="space-y-4">
                                    {/* Profil du livreur */}
                                    <div className="flex items-start justify-between gap-2 border-b border-[var(--admin-border)] pb-3">
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <h4 className="font-extrabold text-sm text-[var(--admin-text)]">
                                                    {selectedDriver.name}
                                                </h4>
                                                <span
                                                    className={cn(
                                                        'text-[9px] font-bold px-2 py-0.5 rounded-full',
                                                        selectedDriver.is_stalled
                                                            ? 'bg-rose-100 text-rose-800'
                                                            : selectedDriver.status === 'delivering'
                                                            ? 'bg-emerald-100 text-emerald-800'
                                                            : selectedDriver.status === 'en_route_pickup'
                                                            ? 'bg-amber-100 text-amber-800'
                                                            : 'bg-blue-100 text-blue-800'
                                                    )}
                                                >
                                                    {selectedDriver.is_stalled
                                                        ? '🚨 Bloqué / Inactif'
                                                        : selectedDriver.status === 'delivering'
                                                        ? '📦 En livraison'
                                                        : selectedDriver.status === 'en_route_pickup'
                                                        ? '🏬 En route magasin'
                                                        : '🟢 Disponible'}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-2 mt-1 text-xs text-[var(--admin-muted)]">
                                                <span>📞 {selectedDriver.phone}</span>
                                                <a
                                                    href={`tel:${selectedDriver.phone}`}
                                                    className="text-blue-600 font-bold hover:underline"
                                                >
                                                    Appeler
                                                </a>
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => setSelectedDriver(null)}
                                            className="h-6 w-6 rounded-full bg-[var(--admin-panel)] text-[var(--admin-text-soft)] hover:bg-black/10 flex items-center justify-center text-xs"
                                        >
                                            ✕
                                        </button>
                                    </div>

                                    {/* Alerte watchdog si stalled */}
                                    {selectedDriver.is_stalled && (
                                        <div className="p-3 rounded-2xl border border-rose-200 bg-rose-50 space-y-1.5 animate-pulse">
                                            <span className="text-[11px] font-black text-rose-800 flex items-center gap-1">
                                                <span>⚠️ Watchdog Livreur Déclenché</span>
                                            </span>
                                            <p className="text-[11px] text-rose-700 leading-snug">
                                                {selectedDriver.stalled_reason || 'Inactivité anormale détectée.'}
                                            </p>
                                            {selectedDriver.active_order && (
                                                <button
                                                    type="button"
                                                    onClick={() => handleReassign(selectedDriver.active_order!.id)}
                                                    className="w-full mt-1 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs transition"
                                                >
                                                    🚨 Réaffecter cette course
                                                </button>
                                            )}
                                        </div>
                                    )}

                                    {/* Télémétrie en direct */}
                                    <div className="grid grid-cols-3 gap-2 text-xs">
                                        <div className="p-2.5 rounded-xl bg-[var(--admin-panel)] border border-[var(--admin-border)]">
                                            <span className="text-[10px] text-[var(--admin-muted)] block uppercase font-bold">
                                                Vitesse
                                            </span>
                                            <span className="font-bold text-[var(--admin-text)]">
                                                {selectedDriver.speed_kmh != null ? `${selectedDriver.speed_kmh} km/h` : '0 km/h'}
                                            </span>
                                        </div>
                                        <div className="p-2.5 rounded-xl bg-[var(--admin-panel)] border border-[var(--admin-border)]">
                                            <span className="text-[10px] text-[var(--admin-muted)] block uppercase font-bold">
                                                Batterie
                                            </span>
                                            <span className="font-bold text-[var(--admin-text)]">
                                                {selectedDriver.battery_level != null ? `🔋 ${selectedDriver.battery_level}%` : '—'}
                                            </span>
                                        </div>
                                        <div className="p-2.5 rounded-xl bg-[var(--admin-panel)] border border-[var(--admin-border)]">
                                            <span className="text-[10px] text-[var(--admin-muted)] block uppercase font-bold">
                                                Zone
                                            </span>
                                            <span className="font-bold text-[var(--admin-text)] truncate block">
                                                {selectedDriver.estimated_commune || 'Abidjan'}
                                            </span>
                                        </div>
                                    </div>

                                    {/* Course rattachée */}
                                    {selectedDriver.active_order ? (
                                        <div className="p-3 rounded-2xl border border-[var(--admin-border)] bg-[var(--admin-panel)] space-y-2 text-xs">
                                            <div className="flex items-center justify-between">
                                                <span className="font-bold text-[var(--admin-text)]">
                                                    Course #{selectedDriver.active_order.id}
                                                </span>
                                                <DeliveryStatusBadge status={selectedDriver.active_order.status} />
                                            </div>
                                            <div className="space-y-1 text-[11px] text-[var(--admin-text-soft)]">
                                                <div>
                                                    <span className="font-bold">🏬 Magasin : </span>
                                                    <span>{selectedDriver.active_order.supplier_name}</span>
                                                </div>
                                                <div>
                                                    <span className="font-bold">👷 Client : </span>
                                                    <span>{selectedDriver.active_order.client_name}</span>
                                                </div>
                                                <div>
                                                    <span className="font-bold">💰 Rémunération : </span>
                                                    <span className="font-mono font-bold text-[#8a6b3d]">
                                                        {money(selectedDriver.active_order.delivery_cost)}
                                                    </span>
                                                </div>
                                            </div>
                                            <div className="flex items-center justify-between text-[10px] font-mono pt-1 border-t border-[var(--admin-border)]">
                                                <span>Retrait : <strong>{selectedDriver.active_order.pickup_code}</strong></span>
                                                <span>Réception : <strong>{selectedDriver.active_order.reception_code}</strong></span>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="p-4 rounded-2xl border border-dashed border-[var(--admin-border)] text-center text-xs text-[var(--admin-muted)]">
                                            🛵 Ce coursier est libre et en attente d'une nouvelle course.
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    <div className="flex items-center justify-between border-b border-[var(--admin-border)] pb-2">
                                        <span className="text-xs font-bold text-[var(--admin-text)]">
                                            🛵 Flotte ({filteredDrivers.length})
                                        </span>
                                        <span className="text-[10px] text-[var(--admin-muted)]">
                                            Cliquez pour inspecter
                                        </span>
                                    </div>
                                    <div className="space-y-1.5 max-h-[360px] overflow-y-auto pr-1">
                                        {filteredDrivers.map((driver) => (
                                            <button
                                                key={driver.id}
                                                type="button"
                                                onClick={() => setSelectedDriver(driver)}
                                                className="w-full text-left p-2.5 rounded-2xl border border-[var(--admin-border)] bg-[var(--admin-panel)] hover:bg-black/5 dark:hover:bg-white/5 transition flex items-center justify-between gap-2"
                                            >
                                                <div className="min-w-0">
                                                    <div className="flex items-center gap-1.5">
                                                        <span className="text-xs font-bold text-[var(--admin-text)] truncate">
                                                            {driver.name}
                                                        </span>
                                                        {driver.is_stalled && (
                                                            <span className="text-[9px] px-1.5 py-0.5 rounded-full bg-rose-100 text-rose-800 font-black">
                                                                🚨
                                                            </span>
                                                        )}
                                                    </div>
                                                    <span className="text-[10px] text-[var(--admin-muted)] block truncate">
                                                        {driver.estimated_commune || 'Grand Abidjan'} • {driver.phone}
                                                    </span>
                                                </div>
                                                <div className="text-right shrink-0">
                                                    <span className={cn(
                                                        'text-[9px] font-bold px-1.5 py-0.5 rounded-full block',
                                                        driver.is_stalled
                                                            ? 'bg-rose-100 text-rose-800'
                                                            : driver.status === 'delivering'
                                                            ? 'bg-emerald-100 text-emerald-800'
                                                            : driver.status === 'en_route_pickup'
                                                            ? 'bg-amber-100 text-amber-800'
                                                            : 'bg-blue-100 text-blue-800'
                                                    )}>
                                                        {driver.is_stalled ? 'Alerte' : driver.status === 'delivering' ? 'Transit' : driver.status === 'en_route_pickup' ? 'Magasin' : 'Libre'}
                                                    </span>
                                                    {driver.speed_kmh != null && driver.speed_kmh > 0 && (
                                                        <span className="text-[9px] font-mono text-[var(--admin-text-soft)]">
                                                            {Math.round(driver.speed_kmh)} km/h
                                                        </span>
                                                    )}
                                                </div>
                                            </button>
                                        ))}
                                        {filteredDrivers.length === 0 && (
                                            <p className="text-center py-6 text-xs text-[var(--admin-muted)]">
                                                Aucun coursier pour ce filtre.
                                            </p>
                                        )}
                                    </div>
                                </div>
                            )}

                            <div className="pt-3 border-t border-[var(--admin-border)] text-[10px] text-[var(--admin-muted)] text-center">
                                Télémétrie OSRM & Watchdog temps réel
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* MODAL TÉLÉMÉTRIE DÉTAILLÉE & OSRM (VUE RADAR) */}
            {selectedOrder && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
                    <div className="relative w-full max-w-xl bg-[var(--admin-panel-strong)] rounded-3xl shadow-2xl border border-[var(--admin-border)] overflow-hidden">
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
                                <div className="py-12 text-center text-[var(--admin-muted)] animate-pulse">
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
                                                    <span className="text-[10px] text-blue-600 uppercase font-bold block">
                                                        Durée Estimée
                                                    </span>
                                                    <span className="font-bold text-[var(--admin-text)]">
                                                        ~ {trackingData.route.duration_min} minutes
                                                    </span>
                                                </div>
                                                <div>
                                                    <span className="text-[10px] text-blue-600 uppercase font-bold block">
                                                        Points de Trajet
                                                    </span>
                                                    <span className="font-bold text-[var(--admin-text)] font-mono">
                                                        {trackingData.route.geometry?.length ?? 0} waypoints
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="p-3 rounded-xl bg-[var(--admin-panel)] border border-[var(--admin-border)] text-[var(--admin-muted)] text-center">
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
                                                <span className="text-[10px] text-[var(--admin-muted)] italic">
                                                    Non émise
                                                </span>
                                            )}
                                        </div>
                                        {trackingData?.driver?.position ? (
                                            <div className="grid grid-cols-3 gap-2 text-xs pt-1 font-mono">
                                                <div>
                                                    <span className="text-[10px] text-[var(--admin-muted)] block">
                                                        Latitude
                                                    </span>
                                                    <span className="font-bold text-[var(--admin-text)]">
                                                        {trackingData.driver.position.lat.toFixed(5)}
                                                    </span>
                                                </div>
                                                <div>
                                                    <span className="text-[10px] text-[var(--admin-muted)] block">
                                                        Longitude
                                                    </span>
                                                    <span className="font-bold text-[var(--admin-text)]">
                                                        {trackingData.driver.position.lng.toFixed(5)}
                                                    </span>
                                                </div>
                                                <div>
                                                    <span className="text-[10px] text-[var(--admin-muted)] block">
                                                        Vitesse
                                                    </span>
                                                    <span className="font-bold text-[var(--admin-text)]">
                                                        {trackingData.driver.position.speed
                                                            ? `${trackingData.driver.position.speed} km/h`
                                                            : '—'}
                                                    </span>
                                                </div>
                                            </div>
                                        ) : (
                                            <p className="text-[11px] text-[var(--admin-muted)] italic">
                                                Le livreur n'a pas encore émis de coordonnées récentes via l'application mobile.
                                            </p>
                                        )}
                                    </div>

                                    {/* Photos de double-validation */}
                                    <div className="space-y-2">
                                        <span className="font-bold text-[var(--admin-text)] text-xs block">
                                            Preuves Photographiques Double-Validation (Règle 13)
                                        </span>
                                        <div className="grid grid-cols-2 gap-3">
                                            <div className="p-3 rounded-2xl border border-[var(--admin-border)] bg-[var(--admin-panel)] text-center space-y-1">
                                                <span className="text-[10px] font-bold text-[var(--admin-muted)] block uppercase">
                                                    📸 Chargement Boutique
                                                </span>
                                                {selectedOrder.pickup_photo_url ? (
                                                    <a
                                                        href={selectedOrder.pickup_photo_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        <img
                                                            src={selectedOrder.pickup_photo_url}
                                                            alt="Pickup"
                                                            className="w-full h-28 object-cover rounded-xl border border-[var(--admin-border)] mt-1 hover:opacity-90"
                                                        />
                                                    </a>
                                                ) : (
                                                    <div className="h-28 flex items-center justify-center text-[10px] text-[var(--admin-muted)] italic bg-[var(--admin-panel-strong)] rounded-xl border border-[var(--admin-border)]">
                                                        En attente de chargement
                                                    </div>
                                                )}
                                            </div>

                                            <div className="p-3 rounded-2xl border border-[var(--admin-border)] bg-[var(--admin-panel)] text-center space-y-1">
                                                <span className="text-[10px] font-bold text-[var(--admin-muted)] block uppercase">
                                                    📸 Déchargement Chantier
                                                </span>
                                                {selectedOrder.delivery_photo_url ? (
                                                    <a
                                                        href={selectedOrder.delivery_photo_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        <img
                                                            src={selectedOrder.delivery_photo_url}
                                                            alt="Delivery"
                                                            className="w-full h-28 object-cover rounded-xl border border-[var(--admin-border)] mt-1 hover:opacity-90"
                                                        />
                                                    </a>
                                                ) : (
                                                    <div className="h-28 flex items-center justify-center text-[10px] text-[var(--admin-muted)] italic bg-[var(--admin-panel-strong)] rounded-xl border border-[var(--admin-border)]">
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
                        <div className="flex items-center justify-between p-4 border-t border-[var(--admin-border)] bg-[var(--admin-panel)]">
                            <span className="text-[11px] text-[var(--admin-muted)] font-mono">
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
            {confirmDialog}
        </div>
    );
}
