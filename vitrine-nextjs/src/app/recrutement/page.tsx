'use client';

import { useState, useEffect } from 'react';
import { api, Recrutement } from '@/lib/api';
import { Briefcase, MapPin, Calendar, Mail } from 'lucide-react';

export default function RecrutementPage() {
    const [jobs, setJobs] = useState<Recrutement[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const loadJobs = async () => {
            try {
                const data = await api.getRecrutements();
                const now = new Date();
                now.setHours(0, 0, 0, 0);

                // Ne conserver que les offres sans date limite OU dont la date limite >= aujourd'hui
                const activeJobs = (data || []).filter(job => {
                    if (!job.date_limite) return true;
                    const limit = new Date(job.date_limite);
                    limit.setHours(23, 59, 59, 999);
                    return limit >= now;
                });

                setJobs(activeJobs);
            } catch (e) {
                console.error('Error loading jobs:', e);
            } finally {
                setLoading(false);
            }
        };
        loadJobs();
    }, []);

    const getContractLabel = (type: string) => {
        const labels: Record<string, string> = {
            cdi: 'CDI',
            cdd: 'CDD',
            stage: 'Stage',
            freelance: 'Mission Freelance / Indépendant',
            apprentissage: 'Apprentissage / Stage qualifiant'
        };
        return labels[type] || type.toUpperCase();
    };

    return (
        <div className="bg-[#fbf9f6] py-16 min-h-screen">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                {/* Header */}
                <div className="text-center max-w-3xl mx-auto space-y-4 mb-20">
                    <span className="text-[11px] font-extrabold uppercase tracking-[0.24em] text-[#8a5d16]">carrières & opportunités</span>
                    <h1 className="text-4xl sm:text-5xl font-extrabold text-[#241b16] tracking-tight leading-tight">
                        Rejoignez les chantiers ProsArtisan
                    </h1>
                    <p className="text-sm text-[#746251] leading-relaxed">
                        ProsArtisan facilite le recrutement d&apos;artisans qualifiés (plombiers, électriciens, peintres) par des promoteurs immobiliers et des particuliers pour des projets d&apos;envergure en Côte d&apos;Ivoire.
                    </p>
                </div>

                {/* Job offers list */}
                {loading ? (
                    <div className="flex flex-col items-center justify-center py-24 gap-3">
                        <div className="h-10 w-10 border-4 border-[#ebb95e] border-t-transparent rounded-full animate-spin" />
                        <p className="text-xs text-[#746251] font-semibold animate-pulse">Chargement des opportunités...</p>
                    </div>
                ) : jobs.length === 0 ? (
                    <div className="text-center py-20 bg-white border border-[#e6d3b2]/50 rounded-[32px] p-8 max-w-xl mx-auto space-y-4 shadow-sm">
                        <div className="inline-flex p-3 bg-amber-100/50 text-[#8a5d16] rounded-2xl">
                            <Briefcase className="h-6 w-6" />
                        </div>
                        <h3 className="text-lg font-bold text-[#241b16]">Aucune offre active</h3>
                        <p className="text-xs text-[#746251] leading-relaxed">
                            Il n&apos;y a pas d&apos;offre de recrutement ou d&apos;appel d&apos;offres de chantier en cours pour le moment. Veuillez repasser plus tard.
                        </p>
                    </div>
                ) : (
                    <div className="space-y-6 max-w-4xl mx-auto">
                        {jobs.map((job) => (
                            <div
                                key={job.id}
                                className="bg-white border border-[#e6d3b2]/40 rounded-[32px] p-6 sm:p-8 shadow-sm hover:shadow-md transition flex flex-col md:flex-row md:items-center justify-between gap-6"
                            >
                                <div className="space-y-4">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="inline-flex items-center px-2.5 py-0.5 bg-[#f7efe2] border border-[#e6d3b2]/40 text-[#8a5d16] font-extrabold text-[9px] uppercase tracking-wider rounded-md">
                                            {getContractLabel(job.type_contrat)}
                                        </span>
                                        <span className="inline-flex items-center px-2.5 py-0.5 bg-slate-100 border border-slate-200 text-slate-700 font-bold text-[9px] uppercase tracking-wider rounded-md">
                                            Spécialité : {job.metier}
                                        </span>
                                    </div>

                                    <h3 className="font-extrabold text-[#241b16] text-lg sm:text-xl leading-tight">
                                        {job.titre}
                                    </h3>
                                    <p className="text-xs text-[#746251] leading-relaxed max-w-2xl">
                                        {job.description}
                                    </p>

                                    <div className="flex flex-wrap gap-4 text-[11px] text-[#746251] pt-2 border-t border-[#e6d3b2]/10">
                                        <div className="flex items-center gap-1.5">
                                            <MapPin className="h-3.5 w-3.5 text-[#ebb95e]" />
                                            <span>{job.lieu}</span>
                                        </div>
                                        {job.date_limite && (
                                            <div className="flex items-center gap-1.5">
                                                <Calendar className="h-3.5 w-3.5 text-[#ebb95e]" />
                                                <span>Postuler avant le : <strong>{new Date(job.date_limite).toLocaleDateString('fr-FR')}</strong></span>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                <div className="shrink-0 pt-4 md:pt-0 border-t md:border-0 border-[#e6d3b2]/10">
                                    {job.contact_email ? (
                                        <a
                                            href={`mailto:${job.contact_email}?subject=Candidature : ${encodeURIComponent(job.titre)}`}
                                            className="bg-[#241b16] hover:bg-[#8a5d16] text-white text-xs font-bold px-6 py-3 rounded-full flex items-center justify-center gap-2 transition"
                                        >
                                            <Mail className="h-4 w-4" />
                                            <span>Envoyer mon CV</span>
                                        </a>
                                    ) : (
                                        <div className="text-xs text-[#746251] italic">
                                            Aucun email de contact renseigné.
                                        </div>
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
