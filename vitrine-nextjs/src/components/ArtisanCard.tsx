import Image from 'next/image';
import Link from 'next/link';
import { Clock, MapPin, Moon, Star } from 'lucide-react';

import type { Artisan, ArtisanAvailability } from '@/lib/api';

const AVAILABILITY_TONE: Record<ArtisanAvailability['status'], string> = {
    disponible: 'bg-emerald-600 text-white',
    occupe: 'bg-amber-500 text-white',
    conge: 'bg-zinc-500 text-white',
};

function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

/**
 * Carte d'un artisan de l'annuaire (Chantier 15) : photo professionnelle ou
 * initiales — jamais une photo de banque d'images qui ferait passer un
 * inconnu pour l'artisan —, métier, commune, score et disponibilité validée.
 */
export function ArtisanCard({ artisan }: { artisan: Artisan }) {
    const availability = artisan.availability ?? null;

    return (
        <div className="bg-white border border-[#e6d3b2]/40 rounded-[28px] overflow-hidden shadow-sm hover:shadow-md transition flex flex-col justify-between">
            <div>
                <div className="aspect-square bg-[#241b16] overflow-hidden relative">
                    {artisan.photo_url ? (
                        <Image src={artisan.photo_url} alt={artisan.name} fill sizes="(max-width: 768px) 100vw, 25vw" className="object-cover" unoptimized />
                    ) : (
                        <div className="absolute inset-0 flex items-center justify-center text-5xl font-black text-[#ebb95e]" aria-hidden="true">
                            {initials(artisan.name)}
                        </div>
                    )}
                    <div className="absolute top-4 left-4 right-4 flex justify-between items-center">
                        <span className="px-2.5 py-0.5 bg-black/60 rounded-md text-[9px] font-bold uppercase tracking-wider text-white">Vérifié CNI</span>
                        <div className="flex items-center gap-1 px-2 py-0.5 bg-amber-500/90 rounded-md text-white font-bold text-[10px]">
                            <Star className="h-3 w-3 fill-current" />
                            <span>{(artisan.score_prosartisan / 200).toFixed(1)}</span>
                        </div>
                    </div>
                    {availability ? (
                        <span
                            className={`absolute bottom-4 left-4 px-2.5 py-1 rounded-full text-[10px] font-bold ${AVAILABILITY_TONE[availability.status]}`}
                            data-testid="availability-badge"
                        >
                            {availability.label}
                        </span>
                    ) : null}
                </div>

                <div className="p-5 space-y-3">
                    <div>
                        <h3 className="font-extrabold text-[#241b16] text-sm">{artisan.name}</h3>
                        <p className="text-[10px] font-bold text-[#8a5d16] uppercase mt-0.5">{artisan.trade ?? 'Métier non renseigné'}</p>
                    </div>
                    {artisan.city ? (
                        <div className="flex items-center gap-1.5 text-xs text-[#746251]">
                            <MapPin className="h-3.5 w-3.5 text-[#ebb95e]" />
                            <span>{artisan.city}</span>
                        </div>
                    ) : null}
                    {availability?.schedule_summary ? (
                        <div className="flex items-start gap-1.5 text-xs text-[#746251]">
                            <Clock className="h-3.5 w-3.5 mt-0.5 shrink-0 text-[#ebb95e]" />
                            <span>{availability.schedule_summary}</span>
                        </div>
                    ) : null}
                    {availability?.night_work ? (
                        <div className="flex items-center gap-1.5 text-xs text-[#746251]">
                            <Moon className="h-3.5 w-3.5 text-[#ebb95e]" />
                            <span>Intervient la nuit</span>
                        </div>
                    ) : null}
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
    );
}
