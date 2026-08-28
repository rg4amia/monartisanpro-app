'use client';

import { useState, useEffect, useCallback } from 'react';
import { api, Artisan } from '@/lib/api';
import { MapPin, Star, ShieldAlert } from 'lucide-react';
import Link from 'next/link';

export default function ArtisansDirectoryPage() {
    const [artisans, setArtisans] = useState<Artisan[]>([]);
    const [loading, setLoading] = useState(true);

    // Filters
    const [metier, setMetier] = useState('');
    const [ville, setVille] = useState('');
    const [noteMin, setNoteMin] = useState(0);

    const trades = [
        "Électricien bâtiment",
        "Plombier sanitaire",
        "Maçon coffreur",
        "Menuisier ébéniste",
        "Peintre décorateur",
        "Carreleur",
        "Charpentier"
    ];

    const loadArtisans = async () => {
        setLoading(true);
        try {
            const data = await api.getArtisans({
                metier: metier || undefined,
                ville: ville || undefined,
                note_min: noteMin || undefined
            });
            setArtisans(data);
        } catch (e) {
            console.error('Error searching artisans:', e);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        let active = true;
        api.getArtisans({
            metier: metier || undefined,
            ville: ville || undefined,
            note_min: noteMin || undefined
        }).then((data) => {
            if (active) {
                setArtisans(data);
                setLoading(false);
            }
        }).catch((e) => {
            console.error('Error fetching artisans on filter change:', e);
            if (active) {
                setLoading(false);
            }
        });
        return () => {
            active = false;
        };
    }, [metier, ville, noteMin]);

    const handleMetierChange = (value: string) => {
        setMetier(value);
        setLoading(true);
    };

    const handleVilleChange = (value: string) => {
        setVille(value);
        setLoading(true);
    };

    const handleNoteMinChange = (value: number) => {
        setNoteMin(value);
        setLoading(true);
    };

    const handleRefresh = () => {
        loadArtisans();
    };

    return (
        <div className="bg-[#fbf9f6] py-16 min-h-screen">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                {/* Header */}
                <div className="text-center max-w-3xl mx-auto space-y-4 mb-16">
                    <span className="text-[11px] font-extrabold uppercase tracking-[0.24em] text-[#8a5d16]">annuaire artisans</span>
                    <h1 className="text-4xl sm:text-5xl font-extrabold text-[#241b16] tracking-tight leading-tight">
                        Trouvez un artisan labellisé à Abidjan
                    </h1>
                    <p className="text-sm text-[#746251] leading-relaxed">
                        Recherchez parmi nos artisans certifiés. Tous les artisans présentés justifient d&apos;une validation de pièce d&apos;identité (KYC active) et d&apos;un score de réputation ProsArtisan.
                    </p>
                </div>

                {/* Filters Row */}
                <div className="bg-white border border-[#e6d3b2] rounded-[28px] p-6 shadow-sm mb-12 grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    {/* Trade filter */}
                    <div>
                        <label className="block text-[10px] font-bold uppercase tracking-wider text-[#746251] mb-2">Métier / Spécialité</label>
                        <select
                            value={metier}
                            onChange={(e) => handleMetierChange(e.target.value)}
                            className="w-full rounded-xl border border-[#e6d3b2]/60 px-3 py-2 text-xs text-[#241b16] focus:border-[#8a5d16] focus:outline-none bg-transparent"
                        >
                            <option value="">Tous les métiers</option>
                            {trades.map(t => (
                                <option key={t} value={t}>{t}</option>
                            ))}
                        </select>
                    </div>

                    {/* City filter */}
                    <div>
                        <label className="block text-[10px] font-bold uppercase tracking-wider text-[#746251] mb-2">Commune / Zone</label>
                        <input
                            type="text"
                            placeholder="Ex: Yopougon, Cocody..."
                            value={ville}
                            onChange={(e) => handleVilleChange(e.target.value)}
                            className="w-full rounded-xl border border-[#e6d3b2]/60 px-3 py-2 text-xs text-[#241b16] focus:border-[#8a5d16] focus:outline-none"
                        />
                    </div>

                    {/* Minimum rating filter */}
                    <div>
                        <label className="block text-[10px] font-bold uppercase tracking-wider text-[#746251] mb-2">Score ProsArtisan min</label>
                        <select
                            value={noteMin}
                            onChange={(e) => handleNoteMinChange(Number(e.target.value))}
                            className="w-full rounded-xl border border-[#e6d3b2]/60 px-3 py-2 text-xs text-[#241b16] focus:border-[#8a5d16] focus:outline-none bg-transparent"
                        >
                            <option value="0">Tous les scores</option>
                            <option value="500">≥ 500 (Moyen)</option>
                            <option value="700">≥ 700 (Excellent)</option>
                            <option value="900">≥ 900 (Stars de la zone)</option>
                        </select>
                    </div>

                    <button
                        onClick={handleRefresh}
                        className="bg-[#241b16] hover:bg-[#8a5d16] text-[#fbf9f6] text-xs font-bold rounded-xl py-2.5 shadow-sm transition-all"
                    >
                        Actualiser la recherche
                    </button>
                </div>

                {/* Artisans grid */}
                {loading ? (
                    <div className="flex flex-col items-center justify-center py-24 gap-3">
                        <div className="h-10 w-10 border-4 border-[#ebb95e] border-t-transparent rounded-full animate-spin" />
                        <p className="text-xs text-[#746251] font-semibold animate-pulse">Recherche des artisans...</p>
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
                            <div
                                key={artisan.id}
                                className="bg-white border border-[#e6d3b2]/40 rounded-[28px] overflow-hidden shadow-sm hover:shadow-md transition flex flex-col justify-between"
                            >
                                <div>
                                    {/* Image / Header */}
                                    <div className="aspect-square bg-zinc-950 overflow-hidden relative">
                                        <img
                                            src={artisan.kyc_selfie_path || 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=300&q=80'}
                                            alt={artisan.name}
                                            className="w-full h-full object-cover"
                                        />
                                        <div className="absolute top-4 left-4 right-4 flex justify-between items-center">
                                            <span className="px-2.5 py-0.5 bg-black/60 rounded-md text-[9px] font-bold uppercase tracking-wider text-white">
                                                Vérifié CNI
                                            </span>
                                            <div className="flex items-center gap-1 px-2 py-0.5 bg-amber-500/90 rounded-md text-white font-bold text-[10px]">
                                                <Star className="h-3 w-3 fill-current" />
                                                <span>{(artisan.score_prosartisan / 200).toFixed(1)}</span>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Content */}
                                    <div className="p-5 space-y-3">
                                        <div>
                                            <h3 className="font-extrabold text-[#241b16] text-sm">{artisan.name}</h3>
                                            <p className="text-[10px] font-bold text-[#8a5d16] uppercase mt-0.5">{artisan.trade || 'Artisan'}</p>
                                        </div>

                                        <div className="flex items-center gap-1.5 text-xs text-[#746251]">
                                            <MapPin className="h-3.5 w-3.5 text-[#ebb95e]" />
                                            <span>{artisan.city || 'Abidjan'}</span>
                                        </div>
                                    </div>
                                </div>

                                <div className="p-5 pt-0">
                                    <div className="border-t border-[#e6d3b2]/10 pt-4 flex items-center justify-between text-xs text-[#746251]">
                                        <div>
                                            <p className="text-[9px] uppercase tracking-wider">Score confiance</p>
                                            <p className="font-black text-[#241b16]">{artisan.score_prosartisan} / 1000</p>
                                        </div>
                                        <Link
                                            href={`/contact?artisan_id=${artisan.id}`}
                                            className="px-4 py-2 bg-[#f7efe2] hover:bg-[#8a5d16] hover:text-white border border-[#e6d3b2]/50 rounded-xl text-[10px] font-bold text-[#8a5d16] transition"
                                        >
                                            Contacter
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
