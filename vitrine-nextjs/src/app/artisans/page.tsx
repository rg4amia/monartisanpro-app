'use client';

import { useCallback, useEffect, useState } from 'react';
import { ShieldAlert, WifiOff } from 'lucide-react';

import { ArtisanCard } from '@/components/ArtisanCard';
import { api, Artisan } from '@/lib/api';

const TRADES = [
    'Électricien bâtiment',
    'Plombier sanitaire',
    'Maçon coffreur',
    'Menuisier ébéniste',
    'Peintre décorateur',
    'Carreleur',
    'Charpentier',
];

type Filters = { metier: string; ville: string; noteMin: number; disponible: boolean };

export default function ArtisansDirectoryPage() {
    const [artisans, setArtisans] = useState<Artisan[]>([]);
    const [loading, setLoading] = useState(true);
    const [failed, setFailed] = useState(false);
    const [filters, setFilters] = useState<Filters>({ metier: '', ville: '', noteMin: 0, disponible: false });
    const [reload, setReload] = useState(0);

    useEffect(() => {
        let active = true;
        api.getArtisans({
            metier: filters.metier || undefined,
            ville: filters.ville || undefined,
            note_min: filters.noteMin || undefined,
            disponible: filters.disponible || undefined,
        })
            .then((data) => {
                if (!active) return;
                setArtisans(data);
                setFailed(false);
            })
            .catch((e) => {
                console.error('Annuaire des artisans indisponible :', e);
                if (!active) return;
                // Jamais d'artisans fictifs à la place : l'erreur est annoncée (Règle d'or 29).
                setArtisans([]);
                setFailed(true);
            })
            .finally(() => {
                if (active) setLoading(false);
            });
        return () => {
            active = false;
        };
    }, [filters, reload]);

    const update = useCallback((patch: Partial<Filters>) => {
        setLoading(true);
        setFilters((current) => ({ ...current, ...patch }));
    }, []);

    const refresh = () => {
        setLoading(true);
        setReload((value) => value + 1);
    };

    const fieldClass =
        'w-full rounded-xl border border-[#e6d3b2]/60 px-3 py-2 text-xs text-[#241b16] focus:border-[#8a5d16] focus:outline-none bg-transparent';

    return (
        <div className="bg-[#fbf9f6] py-16 min-h-screen">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="text-center max-w-3xl mx-auto space-y-4 mb-16">
                    <span className="text-[11px] font-extrabold uppercase tracking-[0.24em] text-[#8a5d16]">annuaire artisans</span>
                    <h1 className="text-4xl sm:text-5xl font-extrabold text-[#241b16] tracking-tight leading-tight">Trouvez un artisan labellisé à Abidjan</h1>
                    <p className="text-sm text-[#746251] leading-relaxed">
                        Recherchez parmi nos artisans certifiés. Tous les artisans présentés justifient d&apos;une validation de pièce d&apos;identité (KYC active) et
                        d&apos;un score de réputation ProsArtisan. Les disponibilités affichées sont vérifiées par nos équipes.
                    </p>
                </div>

                <div className="bg-white border border-[#e6d3b2] rounded-[28px] p-6 shadow-sm mb-12 grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                    <div>
                        <label htmlFor="filtre-metier" className="block text-[10px] font-bold uppercase tracking-wider text-[#746251] mb-2">
                            Métier / Spécialité
                        </label>
                        <select id="filtre-metier" value={filters.metier} onChange={(e) => update({ metier: e.target.value })} className={fieldClass}>
                            <option value="">Tous les métiers</option>
                            {TRADES.map((trade) => (
                                <option key={trade} value={trade}>
                                    {trade}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label htmlFor="filtre-commune" className="block text-[10px] font-bold uppercase tracking-wider text-[#746251] mb-2">
                            Commune / Zone
                        </label>
                        <input
                            id="filtre-commune"
                            type="text"
                            placeholder="Ex: Yopougon, Cocody..."
                            value={filters.ville}
                            onChange={(e) => update({ ville: e.target.value })}
                            className={fieldClass}
                        />
                    </div>

                    <div>
                        <label htmlFor="filtre-score" className="block text-[10px] font-bold uppercase tracking-wider text-[#746251] mb-2">
                            Score ProsArtisan min
                        </label>
                        <select id="filtre-score" value={filters.noteMin} onChange={(e) => update({ noteMin: Number(e.target.value) })} className={fieldClass}>
                            <option value="0">Tous les scores</option>
                            <option value="500">≥ 500 (Moyen)</option>
                            <option value="700">≥ 700 (Excellent)</option>
                            <option value="900">≥ 900 (Stars de la zone)</option>
                        </select>
                    </div>

                    <label className="flex items-center gap-2 text-xs font-bold text-[#241b16] pb-2">
                        <input type="checkbox" checked={filters.disponible} onChange={(e) => update({ disponible: e.target.checked })} className="h-4 w-4 accent-[#8a5d16]" />
                        Disponibles maintenant
                    </label>

                    <button onClick={refresh} className="bg-[#241b16] hover:bg-[#8a5d16] text-[#fbf9f6] text-xs font-bold rounded-xl py-2.5 shadow-sm transition-all">
                        Actualiser la recherche
                    </button>
                </div>

                {loading ? (
                    <div className="flex flex-col items-center justify-center py-24 gap-3">
                        <div className="h-10 w-10 border-4 border-[#ebb95e] border-t-transparent rounded-full animate-spin" />
                        <p className="text-xs text-[#746251] font-semibold animate-pulse">Recherche des artisans...</p>
                    </div>
                ) : failed ? (
                    <div role="alert" className="text-center py-20 bg-white border border-[#e6d3b2]/50 rounded-[32px] p-8 max-w-xl mx-auto space-y-4">
                        <div className="inline-flex p-3 bg-amber-100/60 text-amber-700 rounded-2xl">
                            <WifiOff className="h-6 w-6" />
                        </div>
                        <h3 className="text-lg font-bold text-[#241b16]">Annuaire momentanément indisponible</h3>
                        <p className="text-xs text-[#746251] leading-relaxed">Nous n&apos;avons pas pu charger la liste des artisans. Réessayez dans quelques instants.</p>
                        <button onClick={refresh} className="bg-[#241b16] text-[#fbf9f6] text-xs font-bold rounded-xl px-4 py-2">
                            Réessayer
                        </button>
                    </div>
                ) : artisans.length === 0 ? (
                    <div className="text-center py-20 bg-white border border-[#e6d3b2]/50 rounded-[32px] p-8 max-w-xl mx-auto space-y-4">
                        <div className="inline-flex p-3 bg-rose-100/50 text-rose-700 rounded-2xl">
                            <ShieldAlert className="h-6 w-6" />
                        </div>
                        <h3 className="text-lg font-bold text-[#241b16]">Aucun artisan trouvé</h3>
                        <p className="text-xs text-[#746251] leading-relaxed">
                            Nous n&apos;avons trouvé aucun artisan correspondant exactement à vos filtres. Essayez d&apos;élargir votre recherche.
                        </p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                        {artisans.map((artisan) => (
                            <ArtisanCard key={artisan.id} artisan={artisan} />
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
