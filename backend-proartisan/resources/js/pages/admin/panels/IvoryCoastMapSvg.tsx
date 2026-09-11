import React, { useState } from 'react';
import type { DistrictHeatmapItem, CommuneHeatmapItem } from '../shared/types';

interface IvoryCoastMapSvgProps {
    viewMode: 'national' | 'abidjan';
    selectedDistrict: string | null;
    selectedCommune: string | null;
    districtsHeatmap: Record<string, DistrictHeatmapItem>;
    communesHeatmap: Record<string, CommuneHeatmapItem>;
    onSelectDistrict: (slug: string) => void;
    onSelectCommune: (slug: string) => void;
    onSwitchViewMode: (mode: 'national' | 'abidjan') => void;
}

// Données géométriques des 14 Districts / Régions de Côte d'Ivoire (Vue Nationale viewBox 0 0 650 600)
interface RegionPolygon {
    slug: string;
    name: string;
    labelPos: [number, number];
    path: string;
}

const DISTRICT_POLYGONS: RegionPolygon[] = [
    {
        // Denguélé (Nord-Ouest : Odienné)
        slug: 'denguele',
        name: 'Denguélé',
        labelPos: [110, 110],
        path: 'M 40,80 L 160,50 L 195,110 L 150,180 L 80,170 L 40,80 Z',
    },
    {
        // Savanes / Poro (Grand Nord : Korhogo)
        slug: 'poro',
        name: 'Poro / Savanes',
        labelPos: [290, 85],
        path: 'M 160,50 L 390,40 L 420,120 L 330,165 L 195,110 Z',
    },
    {
        // Zanzan / Gontougo (Nord-Est : Bondoukou)
        slug: 'gontougo',
        name: 'Gontougo / Zanzan',
        labelPos: [505, 175],
        path: 'M 390,40 L 590,110 L 580,240 L 460,250 L 420,120 Z',
    },
    {
        // Woroba / Worodougou (Centre-Nord Ouest : Séguéla, Mankono)
        slug: 'worodougou',
        name: 'Worodougou',
        labelPos: [155, 220],
        path: 'M 80,170 L 150,180 L 220,185 L 230,270 L 130,285 L 80,170 Z',
    },
    {
        // Vallée du Bandama / Gbêkê (Bouaké)
        slug: 'gbeke',
        name: 'Gbêkê / V. Bandama',
        labelPos: [325, 225],
        path: 'M 220,185 L 330,165 L 420,120 L 460,250 L 370,290 L 230,270 Z',
    },
    {
        // Comoé / Indénié-Djuablin (Est : Abengourou)
        slug: 'indenie_djuablin',
        name: 'Indénié-Djuablin',
        labelPos: [515, 335],
        path: 'M 460,250 L 580,240 L 610,400 L 485,390 L 440,320 Z',
    },
    {
        // Montagnes / Tonkpi (Ouest : Man)
        slug: 'tonkpi',
        name: 'Tonkpi / Montagnes',
        labelPos: [65, 310],
        path: 'M 40,240 L 130,285 L 125,380 L 30,360 L 40,240 Z',
    },
    {
        // Haut-Sassandra (Centre-Ouest : Daloa)
        slug: 'haut_sassandra',
        name: 'Haut-Sassandra',
        labelPos: [195, 335],
        path: 'M 130,285 L 230,270 L 270,350 L 205,400 L 125,380 Z',
    },
    {
        // Lacs / Yamoussoukro (District autonome & Capitale)
        slug: 'yamoussoukro',
        name: 'Yamoussoukro',
        labelPos: [290, 310],
        path: 'M 255,285 L 325,280 L 330,335 L 265,340 Z',
    },
    {
        // Bélier / Lacs (Toumodi, Dimbokro)
        slug: 'belier',
        name: 'Bélier',
        labelPos: [365, 340],
        path: 'M 330,280 L 370,290 L 440,320 L 415,380 L 320,380 L 330,335 Z',
    },
    {
        // Gôh-Djiboua (Gagnoa, Divo)
        slug: 'goh_djiboua',
        name: 'Gôh-Djiboua',
        labelPos: [230, 440],
        path: 'M 125,380 L 205,400 L 270,350 L 320,380 L 320,460 L 205,490 L 125,380 Z',
    },
    {
        // Bas-Sassandra / San-Pédro (Sud-Ouest : Port & Littoral)
        slug: 'san_pedro',
        name: 'San-Pédro',
        labelPos: [110, 485],
        path: 'M 30,360 L 125,380 L 205,490 L 180,560 L 60,540 L 30,360 Z',
    },
    {
        // Lagunes / Agnéby-Tiassa (Dabou, Tiassalé, Agboville)
        slug: 'agneby_tiassa',
        name: 'Agnéby-Tiassa',
        labelPos: [390, 440],
        path: 'M 320,380 L 415,380 L 485,390 L 470,470 L 320,460 Z',
    },
    {
        // District Autonome d'Abidjan (Pôle Économique Métropolitain)
        slug: 'abidjan',
        name: 'District d’Abidjan',
        labelPos: [435, 515],
        path: 'M 360,460 L 510,465 L 530,540 L 380,545 L 360,460 Z',
    },
];

// Données géométriques des 13 Communes du Grand Abidjan (viewBox 0 0 750 520)
interface CommunePolygon {
    slug: string;
    name: string;
    labelPos: [number, number];
    path: string;
}

const COMMUNE_POLYGONS: CommunePolygon[] = [
    {
        // Songon (Périurbain Ouest)
        slug: 'songon',
        name: 'Songon',
        labelPos: [70, 240],
        path: 'M 20,160 L 140,150 L 150,320 L 30,330 Z',
    },
    {
        // Anyama (Nord)
        slug: 'anyama',
        name: 'Anyama',
        labelPos: [320, 60],
        path: 'M 240,20 L 420,20 L 410,120 L 250,115 Z',
    },
    {
        // Abobo (Grand Nord Métropolitain)
        slug: 'abobo',
        name: 'Abobo',
        labelPos: [320, 155],
        path: 'M 230,120 L 430,120 L 420,210 L 220,200 Z',
    },
    {
        // Yopougon (Grand Ouest)
        slug: 'yopougon',
        name: 'Yopougon',
        labelPos: [155, 235],
        path: 'M 145,150 L 230,120 L 220,200 L 235,320 L 145,310 Z',
    },
    {
        // Attécoubé (Zone Lagunaire / Baie du Banco)
        slug: 'attecoube',
        name: 'Attécoubé',
        labelPos: [260, 240],
        path: 'M 220,200 L 300,205 L 290,290 L 230,285 Z',
    },
    {
        // Adjamé (Carrefour & Marché Central)
        slug: 'adjame',
        name: 'Adjamé',
        labelPos: [340, 235],
        path: 'M 300,205 L 380,210 L 375,275 L 290,270 Z',
    },
    {
        // Cocody (Nord-Est Résidentiel & Affaires)
        slug: 'cocody',
        name: 'Cocody',
        labelPos: [465, 215],
        path: 'M 380,210 L 560,190 L 570,300 L 385,295 Z',
    },
    {
        // Bingerville (Est Lagunaire)
        slug: 'bingerville',
        name: 'Bingerville',
        labelPos: [650, 240],
        path: 'M 560,190 L 730,195 L 720,310 L 570,300 Z',
    },
    {
        // Le Plateau (Centre des Affaires / Cité Administrative)
        slug: 'plateau',
        name: 'Plateau',
        labelPos: [340, 310],
        path: 'M 305,280 L 375,285 L 365,340 L 315,335 Z',
    },
    {
        // Treichville (Sud Lagunaire / Port & Culture)
        slug: 'treichville',
        name: 'Treichville',
        labelPos: [335, 370],
        path: 'M 290,345 L 380,345 L 370,410 L 295,405 Z',
    },
    {
        // Marcory (Sud / Zone Résidentielle & Commerces)
        slug: 'marcory',
        name: 'Marcory',
        labelPos: [425, 370],
        path: 'M 380,345 L 470,345 L 460,420 L 375,415 Z',
    },
    {
        // Koumassi (Sud Industriel & Artisanal)
        slug: 'koumassi',
        name: 'Koumassi',
        labelPos: [515, 370],
        path: 'M 470,345 L 565,340 L 555,420 L 465,420 Z',
    },
    {
        // Port-Bouët (Sud Côtier / Aéroport FHB & Océan)
        slug: 'port_bouet',
        name: 'Port-Bouët',
        labelPos: [450, 465],
        path: 'M 270,420 L 670,410 L 680,495 L 260,500 Z',
    },
];

export function IvoryCoastMapSvg({
    viewMode,
    selectedDistrict,
    selectedCommune,
    districtsHeatmap,
    communesHeatmap,
    onSelectDistrict,
    onSelectCommune,
    onSwitchViewMode,
}: IvoryCoastMapSvgProps) {
    const [hoveredZone, setHoveredZone] = useState<{
        name: string;
        actors: number;
        missions: number;
        disputes: number;
        rate: number;
        x: number;
        y: number;
    } | null>(null);

    // Fonction de coloration thématique selon densité d'activité (FCFA / Acteurs)
    const getZoneFillColor = (count: number, isSelected: boolean) => {
        if (isSelected) return '#ebb95e'; // Marqueur or / amber ProsArtisan sélectionné
        if (count === 0) return '#f4ede4'; // Teinte neutre douce
        if (count <= 3) return '#e6dac8';
        if (count <= 10) return '#d8c2a3';
        if (count <= 25) return '#cba776';
        if (count <= 50) return '#b88946';
        return '#8d5d1c'; // Haute densité
    };

    const isAbidjanActive = selectedDistrict === 'abidjan';

    return (
        <div className="relative w-full rounded-3xl border border-[var(--admin-border)] bg-gradient-to-b from-[#faf6f0] to-[#f5ecdf] p-4 sm:p-6 shadow-sm overflow-hidden">
            {/* Header Contrôles & Commutateur de Zoom */}
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-[#e7dac7] pb-3">
                <div className="flex items-center gap-2">
                    <span className="flex h-3 w-3 rounded-full bg-[#ebb95e] animate-pulse" />
                    <h3 className="text-base font-bold text-[#241b16]">
                        {viewMode === 'national'
                            ? "Carte Nationale des Districts (14 Régions)"
                            : "Zoom Grand Abidjan Métropole (13 Communes)"}
                    </h3>
                </div>

                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => onSwitchViewMode('national')}
                        className={`rounded-xl px-3 py-1.5 text-xs font-semibold transition ${
                            viewMode === 'national'
                                ? 'bg-[#241b16] text-white shadow'
                                : 'bg-white/80 text-[#5a483a] hover:bg-white border border-[#e7dac7]'
                        }`}
                    >
                        Vue Nationale CI
                    </button>
                    <button
                        type="button"
                        onClick={() => onSwitchViewMode('abidjan')}
                        className={`rounded-xl px-3 py-1.5 text-xs font-semibold transition flex items-center gap-1.5 ${
                            viewMode === 'abidjan'
                                ? 'bg-[#b77918] text-white shadow'
                                : 'bg-white/80 text-[#5a483a] hover:bg-white border border-[#e7dac7]'
                        }`}
                    >
                        <span>🏙️</span> Zoom Abidjan (13)
                    </button>
                </div>
            </div>

            {/* Hint contextuel */}
            <div className="mb-2 flex items-center justify-between text-xs text-[#735843]">
                <p>
                    {viewMode === 'national'
                        ? "Cliquez sur une région pour filtrer le récapitulatif opérationnel. Cliquez sur Abidjan pour basculer en vue communale."
                        : "Cliquez sur une commune pour afficher les artisans, clients, quincailleries et livreurs locaux."}
                </p>
                {viewMode === 'national' && isAbidjanActive && (
                    <button
                        type="button"
                        onClick={() => onSwitchViewMode('abidjan')}
                        className="text-xs font-bold text-[#b77918] hover:underline flex items-center gap-1"
                    >
                        Explorer les 13 communes d'Abidjan &rarr;
                    </button>
                )}
            </div>

            {/* Carte SVG Interactive */}
            <div className="relative flex justify-center items-center py-2">
                {viewMode === 'national' ? (
                    <svg
                        viewBox="0 0 650 580"
                        className="w-full max-w-[620px] h-auto drop-shadow-md select-none transition-all duration-300"
                        aria-label="Carte des 14 districts de Côte d'Ivoire"
                    >
                        <defs>
                            <filter id="shadow" x="-5%" y="-5%" width="110%" height="110%">
                                <feDropShadow dx="1" dy="2" stdDeviation="2" floodOpacity="0.15" />
                            </filter>
                        </defs>

                        {/* Rendu des 14 Districts */}
                        {DISTRICT_POLYGONS.map((poly) => {
                            const stats = districtsHeatmap[poly.slug];
                            const isSelected = selectedDistrict === poly.slug;
                            const totalActors = stats?.actors_count ?? 0;
                            const missionsCount = stats?.missions_count ?? 0;
                            const disputeRate = stats?.dispute_rate ?? 0;
                            const realizationRate = stats?.realization_rate ?? 0;
                            const fillColor = getZoneFillColor(totalActors + missionsCount, isSelected);

                            return (
                                <g
                                    key={poly.slug}
                                    className="cursor-pointer transition-all duration-200 group"
                                    onClick={() => onSelectDistrict(poly.slug)}
                                    onMouseEnter={(e) => {
                                        const rect = e.currentTarget.getBoundingClientRect();
                                        setHoveredZone({
                                            name: poly.name,
                                            actors: totalActors,
                                            missions: missionsCount,
                                            disputes: stats?.disputes_count ?? 0,
                                            rate: realizationRate,
                                            x: poly.labelPos[0],
                                            y: poly.labelPos[1],
                                        });
                                    }}
                                    onMouseLeave={() => setHoveredZone(null)}
                                >
                                    <path
                                        d={poly.path}
                                        fill={fillColor}
                                        stroke={isSelected ? '#241b16' : '#8a6b3d'}
                                        strokeWidth={isSelected ? 3.2 : 1.2}
                                        strokeLinejoin="round"
                                        className="transition-all duration-200 group-hover:brightness-95 group-hover:stroke-[#241b16] group-hover:stroke-[2.5]"
                                    />
                                    {/* Pastille badge district */}
                                    <circle
                                        cx={poly.labelPos[0]}
                                        cy={poly.labelPos[1]}
                                        r={isSelected ? 14 : 11}
                                        fill={isSelected ? '#241b16' : '#ffffff'}
                                        stroke={isSelected ? '#ebb95e' : '#8a6b3d'}
                                        strokeWidth="1.5"
                                        className="transition-transform group-hover:scale-110"
                                    />
                                    <text
                                        x={poly.labelPos[0]}
                                        y={poly.labelPos[1] + 4}
                                        textAnchor="middle"
                                        fill={isSelected ? '#ffffff' : '#241b16'}
                                        fontSize={isSelected ? 10 : 9}
                                        fontWeight="bold"
                                        className="pointer-events-none"
                                    >
                                        {totalActors}
                                    </text>
                                    <text
                                        x={poly.labelPos[0]}
                                        y={poly.labelPos[1] + (poly.slug === 'abidjan' ? 22 : 18)}
                                        textAnchor="middle"
                                        fill="#241b16"
                                        fontSize={poly.slug === 'abidjan' ? 12 : 9.5}
                                        fontWeight={isSelected || poly.slug === 'abidjan' ? 'bold' : '600'}
                                        className="pointer-events-none drop-shadow-[0_1px_1px_rgba(255,255,255,0.8)]"
                                    >
                                        {poly.name}
                                    </text>
                                </g>
                            );
                        })}

                        {/* Océan Atlantique / Littoral Golfe de Guinée */}
                        <path
                            d="M 20,565 Q 250,575 630,550"
                            stroke="#3b82f6"
                            strokeWidth="2.5"
                            strokeDasharray="4 4"
                            fill="none"
                            opacity="0.4"
                        />
                        <text x="320" y="575" textAnchor="middle" fill="#2563eb" fontSize="10" fontWeight="600" opacity="0.6">
                            Golfe de Guinée / Océan Atlantique
                        </text>
                    </svg>
                ) : (
                    <svg
                        viewBox="0 0 750 530"
                        className="w-full max-w-[650px] h-auto drop-shadow-md select-none transition-all duration-300"
                        aria-label="Carte des 13 communes du Grand Abidjan"
                    >
                        {/* Lagune Ébrié en fond stylisé */}
                        <path
                            d="M 40,310 Q 230,300 370,335 T 570,320 T 730,300 L 730,350 Q 550,370 370,350 T 40,340 Z"
                            fill="#bfdbfe"
                            opacity="0.5"
                        />
                        <text x="500" y="335" fill="#1e40af" fontSize="11" fontWeight="600" opacity="0.65">
                            Lagune Ébrié
                        </text>

                        {/* Rendu des 13 Communes */}
                        {COMMUNE_POLYGONS.map((poly) => {
                            const stats = communesHeatmap[poly.slug];
                            const isSelected = selectedCommune === poly.slug;
                            const totalActors = stats?.actors_count ?? 0;
                            const missionsCount = stats?.missions_count ?? 0;
                            const fillColor = getZoneFillColor(totalActors + missionsCount, isSelected);

                            return (
                                <g
                                    key={poly.slug}
                                    className="cursor-pointer transition-all duration-200 group"
                                    onClick={() => onSelectCommune(poly.slug)}
                                    onMouseEnter={() => {
                                        setHoveredZone({
                                            name: poly.name,
                                            actors: totalActors,
                                            missions: missionsCount,
                                            disputes: stats?.disputes_count ?? 0,
                                            rate: stats?.realization_rate ?? 0,
                                            x: poly.labelPos[0],
                                            y: poly.labelPos[1],
                                        });
                                    }}
                                    onMouseLeave={() => setHoveredZone(null)}
                                >
                                    <path
                                        d={poly.path}
                                        fill={fillColor}
                                        stroke={isSelected ? '#241b16' : '#8a6b3d'}
                                        strokeWidth={isSelected ? 3.2 : 1.4}
                                        strokeLinejoin="round"
                                        className="transition-all duration-200 group-hover:brightness-95 group-hover:stroke-[#241b16] group-hover:stroke-[2.5]"
                                    />
                                    <circle
                                        cx={poly.labelPos[0]}
                                        cy={poly.labelPos[1] - 4}
                                        r={isSelected ? 13 : 10}
                                        fill={isSelected ? '#241b16' : '#ffffff'}
                                        stroke={isSelected ? '#ebb95e' : '#8a6b3d'}
                                        strokeWidth="1.5"
                                    />
                                    <text
                                        x={poly.labelPos[0]}
                                        y={poly.labelPos[1]}
                                        textAnchor="middle"
                                        fill={isSelected ? '#ffffff' : '#241b16'}
                                        fontSize={isSelected ? 9.5 : 8.5}
                                        fontWeight="bold"
                                        className="pointer-events-none"
                                    >
                                        {totalActors}
                                    </text>
                                    <text
                                        x={poly.labelPos[0]}
                                        y={poly.labelPos[1] + 16}
                                        textAnchor="middle"
                                        fill="#241b16"
                                        fontSize="10"
                                        fontWeight={isSelected ? 'bold' : '600'}
                                        className="pointer-events-none drop-shadow-[0_1px_1px_rgba(255,255,255,0.8)]"
                                    >
                                        {poly.name}
                                    </text>
                                </g>
                            );
                        })}
                    </svg>
                )}

                {/* Bulle d'information au survol (Tooltip) */}
                {hoveredZone && (
                    <div className="absolute top-4 right-4 z-20 w-64 rounded-2xl border border-[#d8c2a3] bg-white/95 p-3.5 shadow-xl backdrop-blur pointer-events-none animate-in fade-in zoom-in-95 duration-150">
                        <div className="flex items-center justify-between border-b border-[#f0e4d2] pb-1.5 mb-2">
                            <h4 className="font-bold text-sm text-[#241b16]">{hoveredZone.name}</h4>
                            <span className="rounded-full bg-[#fbf5ed] px-2 py-0.5 text-[10px] font-bold text-[#b77918] border border-[#e8d5bc]">
                                Actif
                            </span>
                        </div>
                        <div className="grid grid-cols-2 gap-2 text-xs">
                            <div className="rounded-xl bg-[#faf6f0] p-1.5">
                                <p className="text-[10px] text-[#735843]">Acteurs inscrits</p>
                                <p className="text-sm font-bold text-[#241b16]">{hoveredZone.actors}</p>
                            </div>
                            <div className="rounded-xl bg-[#faf6f0] p-1.5">
                                <p className="text-[10px] text-[#735843]">Missions</p>
                                <p className="text-sm font-bold text-[#241b16]">{hoveredZone.missions}</p>
                            </div>
                            <div className="rounded-xl bg-[#faf6f0] p-1.5">
                                <p className="text-[10px] text-[#735843]">Réalisation</p>
                                <p className="text-sm font-bold text-emerald-700">{hoveredZone.rate}%</p>
                            </div>
                            <div className="rounded-xl bg-[#faf6f0] p-1.5">
                                <p className="text-[10px] text-[#735843]">Litiges</p>
                                <p className={`text-sm font-bold ${hoveredZone.disputes > 0 ? 'text-rose-600' : 'text-slate-700'}`}>
                                    {hoveredZone.disputes}
                                </p>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {/* Légende de densité */}
            <div className="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-[#e7dac7] pt-3 text-[11px] text-[#735843]">
                <div className="flex items-center gap-2">
                    <span className="font-semibold text-[#241b16]">Densité d'activité :</span>
                    <div className="flex items-center gap-1.5">
                        <span className="h-3 w-4 rounded-sm border border-[#c4b5a2] bg-[#f4ede4]" title="0 acteur" />
                        <span>0</span>
                        <span className="h-3 w-4 rounded-sm border border-[#c4b5a2] bg-[#e6dac8]" title="1-3" />
                        <span>1-3</span>
                        <span className="h-3 w-4 rounded-sm border border-[#c4b5a2] bg-[#cba776]" title="4-25" />
                        <span>4-25</span>
                        <span className="h-3 w-4 rounded-sm border border-[#c4b5a2] bg-[#8d5d1c]" title="26+" />
                        <span>26+</span>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <span className="h-3 w-3 rounded-full border border-[#241b16] bg-[#ebb95e]" />
                    <span className="font-medium">Zone sélectionnée</span>
                </div>
            </div>
        </div>
    );
}
