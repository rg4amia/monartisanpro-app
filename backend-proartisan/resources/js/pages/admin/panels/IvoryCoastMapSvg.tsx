import React, { useState, useMemo, useRef } from 'react';
import type { DistrictHeatmapItem, CommuneHeatmapItem, HeatmapMetricMode } from '../shared/types';
import {
    IVORY_COAST_DISTRICTS,
    IVORY_COAST_CITIES,
    ABIDJAN_COMMUNES_GEODATA,
    getChoroplethColor
    
    
    
} from './ivoryCoastGeoData';
import type {CityGeoData, DistrictGeoData, AbidjanCommuneGeoData} from './ivoryCoastGeoData';

interface IvoryCoastMapSvgProps {
    viewMode: 'national' | 'abidjan';
    selectedDistrict: string | null;
    selectedCommune: string | null;
    districtsHeatmap: Record<string, DistrictHeatmapItem>;
    communesHeatmap: Record<string, CommuneHeatmapItem>;
    onSelectDistrict: (slug: string) => void;
    onSelectCommune: (slug: string) => void;
    onSwitchViewMode: (mode: 'national' | 'abidjan') => void;
    onSelectCity?: (city: CityGeoData) => void;
}

export function IvoryCoastMapSvg({
    viewMode,
    selectedDistrict,
    selectedCommune,
    districtsHeatmap,
    communesHeatmap,
    onSelectDistrict,
    onSelectCommune,
    onSwitchViewMode,
    onSelectCity,
}: IvoryCoastMapSvgProps) {
    // État des calques et de la métrique (Style MapSVG / EditMapStudio)
    const [metricMode, setMetricMode] = useState<HeatmapMetricMode>('actors');
    const [showCities, setShowCities] = useState<boolean>(true);
    const [showLabels, setShowLabels] = useState<boolean>(true);
    const [showCoastline, setShowCoastline] = useState<boolean>(true);
    const [searchQuery, setSearchQuery] = useState<string>('');
    const [isSearchOpen, setIsSearchOpen] = useState<boolean>(false);

    // Contrôles de zoom et déplacement (Pan & Zoom)
    const [zoomLevel, setZoomLevel] = useState<number>(1);
    const [panOffset, setPanOffset] = useState<{ x: number; y: number }>({ x: 0, y: 0 });
    const [isDragging, setIsDragging] = useState<boolean>(false);
    const [dragStart, setDragStart] = useState<{ x: number; y: number }>({ x: 0, y: 0 });
    const [isFullscreen, setIsFullscreen] = useState<boolean>(false);
    const containerRef = useRef<HTMLDivElement>(null);
    const svgRef = useRef<SVGSVGElement>(null);

    // Survol d'une zone (District, Commune ou Ville)
    const [hoveredEntity, setHoveredEntity] = useState<{
        title: string;
        subtitle?: string;
        isoCode?: string;
        chefLieu?: string;
        regions?: string[];
        actors: number;
        missions: number;
        volumeFcfa?: number;
        disputes: number;
        rate: number;
        x: number;
        y: number;
        population?: string;
        isCity?: boolean;
    } | null>(null);

    // Calcul dynamique de la valeur maximale pour la normalisation de la choroplèthe
    const maxMetricValue = useMemo(() => {
        let max = 1;
        if (viewMode === 'national') {
            Object.values(districtsHeatmap).forEach((d) => {
                if (metricMode === 'actors') max = Math.max(max, d.actors_count || 0);
                else if (metricMode === 'volume') max = Math.max(max, d.volume_fcfa || 0);
                else if (metricMode === 'rate') max = 100;
            });
        } else {
            Object.values(communesHeatmap).forEach((c) => {
                if (metricMode === 'actors') max = Math.max(max, c.actors_count || 0);
                else if (metricMode === 'volume') max = Math.max(max, c.volume_fcfa || 0);
                else if (metricMode === 'rate') max = 100;
            });
        }
        return Math.max(max, 1);
    }, [districtsHeatmap, communesHeatmap, viewMode, metricMode]);

    // Calcul de la couleur d'un district
    const getDistrictFill = (district: DistrictGeoData, isSelected: boolean) => {
        if (isSelected) return '#f59e0b'; // Amber sélectionné
        const stats = districtsHeatmap[district.slug];
        if (!stats) return '#f8fafc'; // Neutre clair

        let val = 0;
        if (metricMode === 'actors') val = stats.actors_count;
        else if (metricMode === 'volume') val = stats.volume_fcfa;
        else if (metricMode === 'rate') return getChoroplethColor(stats.realization_rate, 'rate');

        const normalized = Math.min(100, (val / maxMetricValue) * 100);
        return getChoroplethColor(normalized, metricMode);
    };

    // Calcul de la couleur d'une commune (Abidjan)
    const getCommuneFill = (commune: AbidjanCommuneGeoData, isSelected: boolean) => {
        if (isSelected) return '#f59e0b';
        const stats = communesHeatmap[commune.id];
        if (!stats) return '#f1f5f9';

        let val = 0;
        if (metricMode === 'actors') val = stats.actors_count;
        else if (metricMode === 'volume') val = stats.volume_fcfa;
        else if (metricMode === 'rate') return getChoroplethColor(stats.realization_rate, 'rate');

        const normalized = Math.min(100, (val / maxMetricValue) * 100);
        return getChoroplethColor(normalized, metricMode);
    };

    // Gestion du Zoom
    const handleZoomIn = () => setZoomLevel((prev) => Math.min(prev + 0.35, 3.5));
    const handleZoomOut = () => setZoomLevel((prev) => Math.max(prev - 0.35, 0.8));
    const handleResetZoom = () => {
        setZoomLevel(1);
        setPanOffset({ x: 0, y: 0 });
    };

    // Calcul du viewBox SVG dynamique selon le zoom et le déplacement
    const baseWidth = viewMode === 'national' ? 1000 : 550;
    const baseHeight = viewMode === 'national' ? 1000 : 450;
    const currentWidth = baseWidth / zoomLevel;
    const currentHeight = baseHeight / zoomLevel;
    const minX = (baseWidth - currentWidth) / 2 + panOffset.x;
    const minY = (baseHeight - currentHeight) / 2 + panOffset.y;
    const dynamicViewBox = `${minX} ${minY} ${currentWidth} ${currentHeight}`;

    // Événements de glisser-déposer (Pan)
    const handleMouseDown = (e: React.MouseEvent) => {
        if (zoomLevel <= 1) return;
        setIsDragging(true);
        setDragStart({ x: e.clientX - panOffset.x, y: e.clientY - panOffset.y });
    };

    const handleMouseMove = (e: React.MouseEvent) => {
        if (!isDragging) return;
        setPanOffset({
            x: e.clientX - dragStart.x,
            y: e.clientY - dragStart.y,
        });
    };

    const handleMouseUp = () => setIsDragging(false);

    // Suggestions de recherche dynamique
    const searchResults = useMemo(() => {
        const q = searchQuery.trim().toLowerCase();
        if (!q) return [];

        const districts = IVORY_COAST_DISTRICTS.filter(
            (d) =>
                d.name.toLowerCase().includes(q) ||
                d.chefLieu.toLowerCase().includes(q) ||
                d.regions.some((r) => r.toLowerCase().includes(q))
        ).map((d) => ({ type: 'district', item: d }));

        const cities = IVORY_COAST_CITIES.filter(
            (c) => c.name.toLowerCase().includes(q) || c.description.toLowerCase().includes(q)
        ).map((c) => ({ type: 'city', item: c }));

        const communes = ABIDJAN_COMMUNES_GEODATA.filter((c) => c.name.toLowerCase().includes(q)).map((c) => ({
            type: 'commune',
            item: c,
        }));

        return [...districts, ...cities, ...communes].slice(0, 8);
    }, [searchQuery]);

    // Sélection d'un résultat de recherche
    const handleSelectSearchResult = (result: { type: string; item: any }) => {
        if (result.type === 'district') {
            const d = result.item as DistrictGeoData;
            onSelectDistrict(d.slug);
            if (viewMode !== 'national') onSwitchViewMode('national');
            // Auto centrage
            setPanOffset({ x: (d.center[0] - 500) * 0.4, y: (d.center[1] - 500) * 0.4 });
            setZoomLevel(1.6);
        } else if (result.type === 'city') {
            const c = result.item as CityGeoData;
            onSelectDistrict(c.districtSlug);
            if (onSelectCity) onSelectCity(c);
            if (viewMode !== 'national') onSwitchViewMode('national');
            setPanOffset({ x: (c.x - 500) * 0.5, y: (c.y - 500) * 0.5 });
            setZoomLevel(1.8);
        } else if (result.type === 'commune') {
            const c = result.item as AbidjanCommuneGeoData;
            onSelectCommune(c.id);
            if (viewMode !== 'abidjan') onSwitchViewMode('abidjan');
        }
        setSearchQuery('');
        setIsSearchOpen(false);
    };

    // Export SVG
    const handleExportSvg = () => {
        if (!svgRef.current) return;
        const svgContent = svgRef.current.outerHTML;
        const blob = new Blob([svgContent], { type: 'image/svg+xml;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `carte-cote-divoire-${viewMode}-${new Date().toISOString().slice(0, 10)}.svg`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    };

    return (
        <div
            ref={containerRef}
            className={`relative w-full rounded-3xl border border-[var(--admin-border)] bg-gradient-to-b from-[#fbf8f3] via-[#f7f2ea] to-[#f0e7d8] p-4 sm:p-6 shadow-sm overflow-hidden select-none transition-all duration-300 ${
                isFullscreen ? 'fixed inset-0 z-50 rounded-none p-8 bg-[#f7f2ea]' : ''
            }`}
        >
            {/* Header / Barre de Contrôles & Filtres Thématiques */}
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-[#e2d4c0] pb-3">
                {/* Titre & Sélecteur de Mode */}
                <div className="flex items-center gap-3">
                    <div className="flex items-center gap-2">
                        <span className="flex h-3.5 w-3.5 rounded-full bg-[#f59e0b] shadow-[0_0_8px_#f59e0b] animate-pulse" />
                        <div>
                            <h3 className="text-base font-bold text-[#241b16] flex items-center gap-2">
                                {viewMode === 'national' ? "🇨🇮 Carte Interactive de Côte d'Ivoire" : '🏙️ Zoom Grand Abidjan Métropole'}
                                <span className="rounded-full bg-[#ebd8be] px-2 py-0.5 text-[10px] font-semibold text-[#664b28]">
                                    {viewMode === 'national' ? '14 Districts & Régions' : '13 Communes'}
                                </span>
                            </h3>
                            <p className="text-[11px] text-[#785f47]">
                                {viewMode === 'national'
                                    ? 'Survolez ou cliquez sur un district ou pôle urbain pour analyser la situation des acteurs et missions'
                                    : 'Visualisation à haute résolution des communes du Grand Abidjan'}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Commutateurs de vue & Mode de Métrique Heatmap */}
                <div className="flex flex-wrap items-center gap-2">
                    {/* Switcher Vue Nationale / Abidjan */}
                    <div className="flex rounded-xl bg-white/80 p-1 border border-[#e2d4c0] shadow-sm">
                        <button
                            type="button"
                            onClick={() => onSwitchViewMode('national')}
                            className={`rounded-lg px-3 py-1 text-xs font-semibold transition ${
                                viewMode === 'national'
                                    ? 'bg-[#241b16] text-white shadow'
                                    : 'text-[#6b533f] hover:text-[#241b16]'
                            }`}
                        >
                            🇨🇮 Vue Nationale
                        </button>
                        <button
                            type="button"
                            onClick={() => onSwitchViewMode('abidjan')}
                            className={`rounded-lg px-3 py-1 text-xs font-semibold transition flex items-center gap-1.5 ${
                                viewMode === 'abidjan'
                                    ? 'bg-[#b77918] text-white shadow'
                                    : 'text-[#6b533f] hover:text-[#241b16]'
                            }`}
                        >
                            🏙️ Abidjan (13)
                        </button>
                    </div>

                    {/* Sélecteur de Métrique Heatmap */}
                    <div className="flex rounded-xl bg-white/80 p-1 border border-[#e2d4c0] shadow-sm">
                        <button
                            type="button"
                            onClick={() => setMetricMode('actors')}
                            className={`rounded-lg px-2.5 py-1 text-xs font-semibold transition ${
                                metricMode === 'actors'
                                    ? 'bg-blue-600 text-white shadow'
                                    : 'text-[#6b533f] hover:text-blue-700'
                            }`}
                            title="Densité des 4 catégories d'acteurs"
                        >
                            👥 Acteurs
                        </button>
                        <button
                            type="button"
                            onClick={() => setMetricMode('volume')}
                            className={`rounded-lg px-2.5 py-1 text-xs font-semibold transition ${
                                metricMode === 'volume'
                                    ? 'bg-amber-600 text-white shadow'
                                    : 'text-[#6b533f] hover:text-amber-700'
                            }`}
                            title="Volume d'affaires en FCFA des missions"
                        >
                            💰 Volume FCFA
                        </button>
                        <button
                            type="button"
                            onClick={() => setMetricMode('rate')}
                            className={`rounded-lg px-2.5 py-1 text-xs font-semibold transition ${
                                metricMode === 'rate'
                                    ? 'bg-emerald-600 text-white shadow'
                                    : 'text-[#6b533f] hover:text-emerald-700'
                            }`}
                            title="Taux de réalisation et achèvement des travaux"
                        >
                            📈 Réalisation
                        </button>
                    </div>
                </div>
            </div>

            {/* Barre d'outils secondaire : Calques, Recherche prédictive & Outils Zoom */}
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2.5 text-xs text-[#6b533f]">
                {/* Recherche & Autocomplétion */}
                <div className="relative flex-1 min-w-[200px] max-w-xs">
                    <input
                        type="text"
                        value={searchQuery}
                        onChange={(e) => {
                            setSearchQuery(e.target.value);
                            setIsSearchOpen(true);
                        }}
                        onFocus={() => setIsSearchOpen(true)}
                        placeholder="Rechercher région, ville, commune..."
                        className="w-full h-8 rounded-xl border border-[#d8c5ad] bg-white/90 px-3 pl-8 text-xs text-[#241b16] placeholder-[#9c826c] shadow-sm focus:outline-none focus:ring-2 focus:ring-[#f59e0b]"
                    />
                    <span className="absolute left-2.5 top-2 text-[#9c826c] pointer-events-none">🔍</span>
                    {searchQuery && (
                        <button
                            type="button"
                            onClick={() => setSearchQuery('')}
                            className="absolute right-2.5 top-2 text-[#9c826c] hover:text-[#241b16]"
                        >
                            ✕
                        </button>
                    )}

                    {/* Menu déroulant des suggestions de recherche */}
                    {isSearchOpen && searchResults.length > 0 && (
                        <div className="absolute top-9 left-0 z-30 w-full rounded-xl border border-[#d8c5ad] bg-white p-1.5 shadow-xl">
                            {searchResults.map((res, i) => (
                                <button
                                    key={i}
                                    type="button"
                                    onClick={() => handleSelectSearchResult(res)}
                                    className="w-full text-left px-2.5 py-1.5 rounded-lg text-xs hover:bg-[#fbf4eb] flex items-center justify-between transition"
                                >
                                    <span className="font-semibold text-[#241b16]">
                                        {res.type === 'district' && '📍 '}
                                        {res.type === 'city' && '🏙️ '}
                                        {res.type === 'commune' && '🏘️ '}
                                        {res.item.name}
                                    </span>
                                    <span className="text-[10px] text-[#9c826c]">
                                        {res.type === 'district' && 'District'}
                                        {res.type === 'city' && 'Pôle urbain'}
                                        {res.type === 'commune' && 'Commune'}
                                    </span>
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                {/* Calques (Layers) */}
                <div className="flex flex-wrap items-center gap-2">
                    <label className="flex items-center gap-1.5 cursor-pointer bg-white/70 px-2.5 py-1 rounded-lg border border-[#e2d4c0] hover:bg-white transition">
                        <input
                            type="checkbox"
                            checked={showCities}
                            onChange={(e) => setShowCities(e.target.checked)}
                            className="rounded text-amber-600 focus:ring-amber-500 h-3.5 w-3.5"
                        />
                        <span className="font-medium text-[11px]">📍 Villes clés (22)</span>
                    </label>

                    <label className="flex items-center gap-1.5 cursor-pointer bg-white/70 px-2.5 py-1 rounded-lg border border-[#e2d4c0] hover:bg-white transition">
                        <input
                            type="checkbox"
                            checked={showLabels}
                            onChange={(e) => setShowLabels(e.target.checked)}
                            className="rounded text-amber-600 focus:ring-amber-500 h-3.5 w-3.5"
                        />
                        <span className="font-medium text-[11px]">🏷️ Noms</span>
                    </label>

                    <label className="flex items-center gap-1.5 cursor-pointer bg-white/70 px-2.5 py-1 rounded-lg border border-[#e2d4c0] hover:bg-white transition">
                        <input
                            type="checkbox"
                            checked={showCoastline}
                            onChange={(e) => setShowCoastline(e.target.checked)}
                            className="rounded text-amber-600 focus:ring-amber-500 h-3.5 w-3.5"
                        />
                        <span className="font-medium text-[11px]">🌊 Littoral</span>
                    </label>
                </div>

                {/* Toolbar Flottante Zoom & Export (Style MapSVG) */}
                <div className="flex items-center gap-1 bg-white/80 p-1 rounded-xl border border-[#e2d4c0] shadow-sm">
                    <button
                        type="button"
                        onClick={handleZoomIn}
                        title="Zoom avant"
                        className="h-7 w-7 rounded-lg hover:bg-[#fbf4eb] text-[#241b16] font-bold flex items-center justify-center transition"
                    >
                        +
                    </button>
                    <button
                        type="button"
                        onClick={handleZoomOut}
                        title="Zoom arrière"
                        className="h-7 w-7 rounded-lg hover:bg-[#fbf4eb] text-[#241b16] font-bold flex items-center justify-center transition"
                    >
                        −
                    </button>
                    <button
                        type="button"
                        onClick={handleResetZoom}
                        title="Réinitialiser la vue"
                        className="h-7 w-7 rounded-lg hover:bg-[#fbf4eb] text-[#241b16] flex items-center justify-center transition text-xs"
                    >
                        ↺
                    </button>
                    <div className="h-4 w-px bg-[#e2d4c0] mx-0.5" />
                    <button
                        type="button"
                        onClick={handleExportSvg}
                        title="Télécharger le tracé SVG vectoriel"
                        className="h-7 px-2 rounded-lg hover:bg-[#fbf4eb] text-[#241b16] flex items-center gap-1 transition text-[11px] font-semibold"
                    >
                        📥 SVG
                    </button>
                    <button
                        type="button"
                        onClick={() => setIsFullscreen(!isFullscreen)}
                        title={isFullscreen ? 'Quitter plein écran' : 'Plein écran'}
                        className="h-7 w-7 rounded-lg hover:bg-[#fbf4eb] text-[#241b16] flex items-center justify-center transition text-xs"
                    >
                        {isFullscreen ? '↙' : '⛶'}
                    </button>
                </div>
            </div>

            {/* Zone de Rendu SVG Vectoriel */}
            <div
                className={`relative flex justify-center items-center py-2 overflow-hidden ${
                    zoomLevel > 1 ? (isDragging ? 'cursor-grabbing' : 'cursor-grab') : 'cursor-default'
                }`}
                onMouseDown={handleMouseDown}
                onMouseMove={handleMouseMove}
                onMouseUp={handleMouseUp}
                onMouseLeave={handleMouseUp}
            >
                {viewMode === 'national' ? (
                    <svg
                        ref={svgRef}
                        viewBox={dynamicViewBox}
                        className="w-full max-w-[820px] h-auto drop-shadow-md select-none transition-transform duration-150"
                        aria-label="Carte vectorielle des 14 districts et pôles urbains de Côte d'Ivoire"
                    >
                        <defs>
                            <filter id="mapShadow" x="-5%" y="-5%" width="110%" height="110%">
                                <feDropShadow dx="1.5" dy="2.5" stdDeviation="3" floodOpacity="0.12" />
                            </filter>
                            <radialGradient id="capitalPulse" cx="50%" cy="50%" r="50%">
                                <stop offset="0%" stopColor="#f59e0b" stopOpacity="0.8" />
                                <stop offset="100%" stopColor="#d97706" stopOpacity="0.1" />
                            </radialGradient>
                            <radialGradient id="metroPulse" cx="50%" cy="50%" r="50%">
                                <stop offset="0%" stopColor="#10b981" stopOpacity="0.8" />
                                <stop offset="100%" stopColor="#059669" stopOpacity="0.1" />
                            </radialGradient>
                        </defs>

                        {/* Zone Cotière & Golfe de Guinée */}
                        {showCoastline && (
                            <g className="pointer-events-none">
                                <path
                                    d="M 50,915 C 200,940 380,950 560,910 C 700,880 850,890 980,870 L 980,1000 L 50,1000 Z"
                                    fill="#e0f2fe"
                                    opacity="0.6"
                                />
                                <text
                                    x="500"
                                    y="970"
                                    textAnchor="middle"
                                    fill="#0284c7"
                                    fontSize="14"
                                    fontWeight="600"
                                    opacity="0.7"
                                    letterSpacing="2"
                                >
                                    GOLFE DE GUINÉE / OCÉAN ATLANTIQUE
                                </text>
                            </g>
                        )}

                        {/* 14 Districts Géographiques */}
                        <g filter="url(#mapShadow)">
                            {IVORY_COAST_DISTRICTS.map((district) => {
                                const isSelected = selectedDistrict === district.slug;
                                const stats = districtsHeatmap[district.slug];
                                const fillColor = getDistrictFill(district, isSelected);
                                const actorsCount = stats?.actors_count ?? 0;
                                const missionsCount = stats?.missions_count ?? 0;
                                const realizationRate = stats?.realization_rate ?? 0;
                                const disputeCount = stats?.disputes_count ?? 0;
                                const volumeFcfa = stats?.volume_fcfa ?? 0;

                                return (
                                    <g
                                        key={district.slug}
                                        className="cursor-pointer transition-all duration-200 group"
                                        onClick={() => onSelectDistrict(district.slug)}
                                        onMouseEnter={() => {
                                            setHoveredEntity({
                                                title: district.name,
                                                subtitle: `District autonome & ${district.regions.join(', ')}`,
                                                isoCode: district.isoCode,
                                                chefLieu: district.chefLieu,
                                                regions: district.regions,
                                                actors: actorsCount,
                                                missions: missionsCount,
                                                volumeFcfa: volumeFcfa,
                                                disputes: disputeCount,
                                                rate: realizationRate,
                                                x: district.center[0],
                                                y: district.center[1],
                                            });
                                        }}
                                        onMouseLeave={() => setHoveredEntity(null)}
                                    >
                                        <path
                                            d={district.path}
                                            fill={fillColor}
                                            stroke={isSelected ? '#18181b' : '#785f47'}
                                            strokeWidth={isSelected ? 3.5 : 1.2}
                                            strokeLinejoin="round"
                                            className="transition-all duration-200 group-hover:brightness-95 group-hover:stroke-[#18181b] group-hover:stroke-[2.5]"
                                        />

                                        {/* Badge / Pastille Centrale avec Nombre d'Acteurs */}
                                        {showLabels && (
                                            <g className="pointer-events-none">
                                                <circle
                                                    cx={district.center[0]}
                                                    cy={district.center[1]}
                                                    r={isSelected ? 16 : 13}
                                                    fill={isSelected ? '#18181b' : '#ffffff'}
                                                    stroke={isSelected ? '#f59e0b' : '#785f47'}
                                                    strokeWidth="1.8"
                                                    className="transition-transform group-hover:scale-110"
                                                />
                                                <text
                                                    x={district.center[0]}
                                                    y={district.center[1] + 4.5}
                                                    textAnchor="middle"
                                                    fill={isSelected ? '#ffffff' : '#18181b'}
                                                    fontSize={isSelected ? 11 : 9.5}
                                                    fontWeight="bold"
                                                >
                                                    {metricMode === 'volume'
                                                        ? volumeFcfa > 0
                                                            ? `${Math.round(volumeFcfa / 1000)}k`
                                                            : '0'
                                                        : metricMode === 'rate'
                                                        ? `${realizationRate}%`
                                                        : actorsCount}
                                                </text>

                                                <text
                                                    x={district.center[0]}
                                                    y={district.center[1] + (district.slug === 'abidjan' ? 24 : 20)}
                                                    textAnchor="middle"
                                                    fill="#18181b"
                                                    fontSize={district.slug === 'abidjan' ? 14 : 11}
                                                    fontWeight={isSelected || district.slug === 'abidjan' ? 'bold' : '600'}
                                                    className="drop-shadow-[0_1px_2px_rgba(255,255,255,0.95)]"
                                                >
                                                    {district.name}
                                                </text>
                                            </g>
                                        )}
                                    </g>
                                );
                            })}
                        </g>

                        {/* Pôles Urbains et Villes Clés (City Pins 22 Villes) */}
                        {showCities && (
                            <g className="city-pins">
                                {IVORY_COAST_CITIES.map((city) => {
                                    const isCapital = city.type === 'capitale';
                                    const isMetropole = city.type === 'metropole';
                                    const isPort = city.type === 'port';

                                    return (
                                        <g
                                            key={city.id}
                                            className="cursor-pointer group"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                onSelectDistrict(city.districtSlug);
                                                if (onSelectCity) onSelectCity(city);
                                            }}
                                            onMouseEnter={() => {
                                                const stats = districtsHeatmap[city.districtSlug];
                                                setHoveredEntity({
                                                    title: city.name,
                                                    subtitle: `${city.type.toUpperCase()} • ${city.description}`,
                                                    chefLieu: city.name,
                                                    actors: stats?.actors_count ?? 0,
                                                    missions: stats?.missions_count ?? 0,
                                                    volumeFcfa: stats?.volume_fcfa ?? 0,
                                                    disputes: stats?.disputes_count ?? 0,
                                                    rate: stats?.realization_rate ?? 0,
                                                    population: city.population,
                                                    x: city.x,
                                                    y: city.y,
                                                    isCity: true,
                                                });
                                            }}
                                            onMouseLeave={() => setHoveredEntity(null)}
                                        >
                                            {/* Halo pulsant pour Capitale et Métropoles */}
                                            {isCapital && (
                                                <circle
                                                    cx={city.x}
                                                    cy={city.y}
                                                    r="18"
                                                    fill="url(#capitalPulse)"
                                                    className="animate-ping opacity-75 origin-center"
                                                />
                                            )}
                                            {isMetropole && (
                                                <circle
                                                    cx={city.x}
                                                    cy={city.y}
                                                    r="14"
                                                    fill="url(#metroPulse)"
                                                    className="animate-pulse opacity-60"
                                                />
                                            )}

                                            {/* Marqueur City Pin */}
                                            <circle
                                                cx={city.x}
                                                cy={city.y}
                                                r={isCapital ? 7.5 : isMetropole ? 6 : isPort ? 5 : 4}
                                                fill={
                                                    isCapital
                                                        ? '#f59e0b'
                                                        : isMetropole
                                                        ? '#10b981'
                                                        : isPort
                                                        ? '#0284c7'
                                                        : '#4b5563'
                                                }
                                                stroke="#ffffff"
                                                strokeWidth="2"
                                                className="transition-transform group-hover:scale-125"
                                            />

                                            {/* Label de la Ville */}
                                            <text
                                                x={city.x + 8}
                                                y={city.y + 3.5}
                                                fill="#1e293b"
                                                fontSize={isCapital || isMetropole ? 10 : 8.5}
                                                fontWeight={isCapital || isMetropole ? 'bold' : '600'}
                                                className="pointer-events-none drop-shadow-[0_1px_2px_rgba(255,255,255,0.9)]"
                                            >
                                                {city.name}
                                            </text>
                                        </g>
                                    );
                                })}
                            </g>
                        )}
                    </svg>
                ) : (
                    /* Vue Grand Abidjan Métropole (13 Communes) */
                    <svg
                        ref={svgRef}
                        viewBox="0 0 550 450"
                        className="w-full max-w-[720px] h-auto drop-shadow-md select-none transition-all duration-300"
                        aria-label="Carte des 13 communes du Grand Abidjan"
                    >
                        {/* Lagune Ébrié & Baie de Cocody stylisées */}
                        <path
                            d="M 10,230 C 120,220 220,240 300,265 C 380,290 480,250 540,240 L 540,280 C 470,295 380,315 300,290 C 220,265 110,260 10,270 Z"
                            fill="#bae6fd"
                            opacity="0.8"
                        />
                        <text x="360" y="275" fill="#0369a1" fontSize="10" fontWeight="600" opacity="0.8">
                            Lagune Ébrié
                        </text>

                        {/* Baie du Banco / Zone Nord */}
                        <path
                            d="M 180,140 C 195,170 215,190 220,230 L 235,230 C 230,180 210,160 195,140 Z"
                            fill="#bae6fd"
                            opacity="0.7"
                        />

                        {/* 13 Communes d'Abidjan */}
                        {ABIDJAN_COMMUNES_GEODATA.map((commune) => {
                            const stats = communesHeatmap[commune.id];
                            const isSelected = selectedCommune === commune.id;
                            const fillColor = getCommuneFill(commune, isSelected);
                            const actorsCount = stats?.actors_count ?? 0;
                            const missionsCount = stats?.missions_count ?? 0;
                            const volumeFcfa = stats?.volume_fcfa ?? 0;

                            return (
                                <g
                                    key={commune.id}
                                    className="cursor-pointer transition-all duration-200 group"
                                    onClick={() => onSelectCommune(commune.id)}
                                    onMouseEnter={() => {
                                        setHoveredEntity({
                                            title: `Commune de ${commune.name}`,
                                            subtitle: `Zone : ${commune.zone} • Densité : ${commune.density}`,
                                            chefLieu: commune.name,
                                            actors: actorsCount,
                                            missions: missionsCount,
                                            volumeFcfa: volumeFcfa,
                                            disputes: stats?.disputes_count ?? 0,
                                            rate: stats?.realization_rate ?? 0,
                                            x: commune.center[0],
                                            y: commune.center[1],
                                        });
                                    }}
                                    onMouseLeave={() => setHoveredEntity(null)}
                                >
                                    <path
                                        d={commune.path}
                                        fill={fillColor}
                                        stroke={isSelected ? '#18181b' : '#785f47'}
                                        strokeWidth={isSelected ? 3 : 1.2}
                                        strokeLinejoin="round"
                                        className="transition-all duration-200 group-hover:brightness-95 group-hover:stroke-[#18181b] group-hover:stroke-[2]"
                                    />
                                    <circle
                                        cx={commune.center[0]}
                                        cy={commune.center[1] - 3}
                                        r={isSelected ? 12 : 9.5}
                                        fill={isSelected ? '#18181b' : '#ffffff'}
                                        stroke={isSelected ? '#f59e0b' : '#785f47'}
                                        strokeWidth="1.4"
                                    />
                                    <text
                                        x={commune.center[0]}
                                        y={commune.center[1]}
                                        textAnchor="middle"
                                        fill={isSelected ? '#ffffff' : '#18181b'}
                                        fontSize={isSelected ? 8.5 : 7.5}
                                        fontWeight="bold"
                                        className="pointer-events-none"
                                    >
                                        {metricMode === 'volume'
                                            ? volumeFcfa > 0
                                                ? `${Math.round(volumeFcfa / 1000)}k`
                                                : '0'
                                            : metricMode === 'rate'
                                            ? `${stats?.realization_rate ?? 0}%`
                                            : actorsCount}
                                    </text>
                                    <text
                                        x={commune.center[0]}
                                        y={commune.center[1] + 14}
                                        textAnchor="middle"
                                        fill="#18181b"
                                        fontSize="9"
                                        fontWeight={isSelected ? 'bold' : '600'}
                                        className="pointer-events-none drop-shadow-[0_1px_1px_rgba(255,255,255,0.9)]"
                                    >
                                        {commune.name}
                                    </text>
                                </g>
                            );
                        })}
                    </svg>
                )}

                {/* Popover / Infobulle Flottante Détaillée au Survol */}
                {hoveredEntity && (
                    <div className="absolute top-4 right-4 z-20 w-72 rounded-2xl border border-[#d8c5ad] bg-white/95 p-3.5 shadow-2xl backdrop-blur pointer-events-none animate-in fade-in zoom-in-95 duration-150">
                        <div className="flex items-center justify-between border-b border-[#f1e5d6] pb-2 mb-2">
                            <div>
                                <h4 className="font-bold text-sm text-[#241b16] flex items-center gap-1.5">
                                    {hoveredEntity.title}
                                    {hoveredEntity.isoCode && (
                                        <span className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-[#f3ede3] text-[#785f47]">
                                            {hoveredEntity.isoCode}
                                        </span>
                                    )}
                                </h4>
                                {hoveredEntity.subtitle && (
                                    <p className="text-[11px] text-[#785f47] truncate max-w-[210px]">
                                        {hoveredEntity.subtitle}
                                    </p>
                                )}
                            </div>
                            <span className="rounded-full bg-[#fbf5ec] px-2 py-0.5 text-[10px] font-bold text-[#b77918] border border-[#e8d5bc]">
                                Actif
                            </span>
                        </div>

                        {hoveredEntity.population && (
                            <div className="mb-2 text-[11px] text-[#6b533f] flex items-center justify-between bg-[#fbf7f2] p-1.5 rounded-lg">
                                <span>Population estimée :</span>
                                <span className="font-bold text-[#241b16]">{hoveredEntity.population}</span>
                            </div>
                        )}

                        <div className="grid grid-cols-2 gap-2 text-xs">
                            <div className="rounded-xl bg-[#fbf8f4] p-1.5 border border-[#f0e4d2]">
                                <p className="text-[10px] text-[#785f47]">Acteurs recensés</p>
                                <p className="text-sm font-bold text-blue-700">{hoveredEntity.actors}</p>
                            </div>
                            <div className="rounded-xl bg-[#fbf8f4] p-1.5 border border-[#f0e4d2]">
                                <p className="text-[10px] text-[#785f47]">Missions traitées</p>
                                <p className="text-sm font-bold text-[#241b16]">{hoveredEntity.missions}</p>
                            </div>
                            <div className="rounded-xl bg-[#fbf8f4] p-1.5 border border-[#f0e4d2]">
                                <p className="text-[10px] text-[#785f47]">Taux de succès</p>
                                <p className="text-sm font-bold text-emerald-700">{hoveredEntity.rate}%</p>
                            </div>
                            <div className="rounded-xl bg-[#fbf8f4] p-1.5 border border-[#f0e4d2]">
                                <p className="text-[10px] text-[#785f47]">Litiges signalés</p>
                                <p
                                    className={`text-sm font-bold ${
                                        hoveredEntity.disputes > 0 ? 'text-rose-600' : 'text-slate-700'
                                    }`}
                                >
                                    {hoveredEntity.disputes}
                                </p>
                            </div>
                        </div>

                        {hoveredEntity.volumeFcfa !== undefined && (
                            <div className="mt-2 text-[11px] text-[#6b533f] flex items-center justify-between bg-[#fbf7f2] p-1.5 rounded-lg border border-[#f0e4d2]">
                                <span>Volume d'affaires :</span>
                                <span className="font-bold text-[#b77918]">
                                    {Math.round(hoveredEntity.volumeFcfa).toLocaleString('fr-FR')} FCFA
                                </span>
                            </div>
                        )}
                    </div>
                )}
            </div>

            {/* Légende Dynamique & Indicateurs selon la Métrique */}
            <div className="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-[#e2d4c0] pt-3 text-[11px] text-[#785f47]">
                <div className="flex items-center gap-2">
                    <span className="font-semibold text-[#241b16]">
                        Échelle {metricMode === 'actors' ? 'Acteurs' : metricMode === 'volume' ? 'Volume FCFA' : 'Réalisation'} :
                    </span>
                    <div className="flex items-center gap-1.5">
                        <span className="h-3 w-4 rounded-sm border border-[#c4b5a2] bg-[#f8fafc]" title="Faible / Nul" />
                        <span>Faible</span>
                        <span
                            className="h-3 w-4 rounded-sm border border-[#c4b5a2]"
                            style={{ backgroundColor: getChoroplethColor(30, metricMode) }}
                        />
                        <span>Moyen</span>
                        <span
                            className="h-3 w-4 rounded-sm border border-[#c4b5a2]"
                            style={{ backgroundColor: getChoroplethColor(70, metricMode) }}
                        />
                        <span>Élevé</span>
                        <span
                            className="h-3 w-4 rounded-sm border border-[#c4b5a2]"
                            style={{ backgroundColor: getChoroplethColor(95, metricMode) }}
                        />
                        <span>Pôle majeur</span>
                    </div>
                </div>

                <div className="flex items-center gap-3">
                    <div className="flex items-center gap-1.5">
                        <span className="h-3 w-3 rounded-full border border-[#18181b] bg-[#f59e0b]" />
                        <span className="font-medium text-[#241b16]">Zone sélectionnée</span>
                    </div>
                    {showCities && (
                        <div className="flex items-center gap-1.5">
                            <span className="h-2.5 w-2.5 rounded-full bg-[#f59e0b] border border-white" />
                            <span>Capitale / Pôle</span>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
