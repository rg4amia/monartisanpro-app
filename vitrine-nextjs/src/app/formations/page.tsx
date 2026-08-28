'use client';

import { useState, useEffect } from 'react';
import { api, Formation } from '@/lib/api';
import { Calendar, MapPin, ChevronRight } from 'lucide-react';

export default function FormationsPage() {
    const [formations, setFormations] = useState<Formation[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const loadFormations = async () => {
            try {
                const data = await api.getFormations();
                setFormations(data);
            } catch (e) {
                console.error('Error loading formations:', e);
            } finally {
                setLoading(false);
            }
        };
        loadFormations();
    }, []);

    return (
        <div className="bg-[#fbf9f6] py-16 min-h-screen">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                {/* Header */}
                <div className="text-center max-w-3xl mx-auto space-y-4 mb-20">
                    <span className="text-[11px] font-extrabold uppercase tracking-[0.24em] text-[#8a5d16]">renforcement de capacités</span>
                    <h1 className="text-4xl sm:text-5xl font-extrabold text-[#241b16] tracking-tight leading-tight">
                        Calendrier des sessions de formation ProsArtisan
                    </h1>
                    <p className="text-sm text-[#746251] leading-relaxed">
                        ProsArtisan accompagne ses artisans affiliés dans le développement de leurs compétences techniques, administratives et sécuritaires. Découvrez nos prochaines sessions de formation et inscrivez-vous.
                    </p>
                </div>

                {/* Formations list */}
                {loading ? (
                    <div className="flex flex-col items-center justify-center py-24 gap-3">
                        <div className="h-10 w-10 border-4 border-[#ebb95e] border-t-transparent rounded-full animate-spin" />
                        <p className="text-xs text-[#746251] font-semibold animate-pulse">Chargement du calendrier...</p>
                    </div>
                ) : formations.length === 0 ? (
                    <div className="text-center py-20 bg-white border border-[#e6d3b2]/50 rounded-[32px] p-8 max-w-xl mx-auto space-y-4">
                        <div className="inline-flex p-3 bg-amber-100/50 text-[#8a5d16] rounded-2xl">
                            <Calendar className="h-6 w-6" />
                        </div>
                        <h3 className="text-lg font-bold text-[#241b16]">Aucune session programmée</h3>
                        <p className="text-xs text-[#746251] leading-relaxed">
                            Il n&apos;y a pas de session de formation programmée pour le moment. Veuillez repasser plus tard ou nous contacter pour plus d&apos;infos.
                        </p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                        {formations.map((formation) => (
                            <div
                                key={formation.id}
                                className="bg-white border border-[#e6d3b2]/40 rounded-[32px] overflow-hidden shadow-sm hover:shadow-md transition flex flex-col justify-between p-6 sm:p-8"
                            >
                                <div className="space-y-6">
                                    {/* Cover image */}
                                    <div className="aspect-[2/1] rounded-2xl overflow-hidden bg-zinc-200">
                                        <img
                                            src={formation.image_url || 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=800&q=80'}
                                            alt={formation.titre}
                                            className="w-full h-full object-cover"
                                        />
                                    </div>

                                    {/* Content details */}
                                    <div className="space-y-3">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="inline-flex items-center px-2.5 py-0.5 bg-[#eef8f0] border border-green-300/40 text-green-700 font-extrabold text-[9px] uppercase tracking-wider rounded-md">
                                                {formation.tarif === 0 ? 'Gratuite' : `${formation.tarif.toLocaleString('fr-FR')} FCFA`}
                                            </span>
                                            {formation.formateur && (
                                                <span className="inline-flex items-center px-2.5 py-0.5 bg-[#efe6da]/60 border border-[#e6d3b2]/30 text-[#746251] font-bold text-[9px] uppercase tracking-wider rounded-md">
                                                    Formateur : {formation.formateur}
                                                </span>
                                            )}
                                        </div>

                                        <h3 className="font-extrabold text-[#241b16] text-lg sm:text-xl leading-tight">
                                            {formation.titre}
                                        </h3>
                                        <p className="text-xs text-[#746251] leading-relaxed">
                                            {formation.description}
                                        </p>
                                    </div>
                                </div>

                                <div className="mt-8 pt-6 border-t border-[#e6d3b2]/10 space-y-4">
                                    {/* Meta row */}
                                    <div className="grid grid-cols-2 gap-4 text-xs text-[#746251]">
                                        <div className="space-y-1">
                                            <p className="text-[10px] uppercase font-bold text-[#8a5d16]">Date & Période</p>
                                            <div className="flex items-center gap-2 font-semibold text-[#241b16]">
                                                <Calendar className="h-4 w-4 text-[#ebb95e]" />
                                                <span>{new Date(formation.date_debut).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })}</span>
                                            </div>
                                        </div>
                                        <div className="space-y-1">
                                            <p className="text-[10px] uppercase font-bold text-[#8a5d16]">Lieu</p>
                                            <div className="flex items-center gap-2 font-semibold text-[#241b16] truncate">
                                                <MapPin className="h-4 w-4 text-[#ebb95e]" />
                                                <span className="truncate" title={formation.lieu}>{formation.lieu}</span>
                                            </div>
                                        </div>
                                    </div>

                                    {formation.places_restantes !== null && (
                                        <div className="flex items-center justify-between text-xs pt-2">
                                            <span className="text-[#746251]">Places restantes : <strong className="text-emerald-700">{formation.places_restantes}</strong> / {formation.places_total}</span>
                                            <div className="w-1/2 h-2 bg-[#efe6da] rounded-full overflow-hidden">
                                                <div 
                                                    className="h-full bg-emerald-600 rounded-full" 
                                                    style={{ width: `${((formation.places_restantes ?? 0) / (formation.places_total ?? 1)) * 100}%` }}
                                                />
                                            </div>
                                        </div>
                                    )}

                                    {formation.lien_inscription && (
                                        <a
                                            href={formation.lien_inscription}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="w-full text-center bg-[#241b16] hover:bg-[#8a5d16] text-white text-xs font-bold py-3.5 rounded-full flex items-center justify-center gap-2 transition"
                                        >
                                            <span>S&apos;inscrire à cette session</span>
                                            <ChevronRight className="h-4 w-4" />
                                        </a>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
