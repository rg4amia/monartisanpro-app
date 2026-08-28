'use client';

import { useState, useEffect } from 'react';
import { api, Article } from '@/lib/api';
import { Calendar, ArrowRight, BookOpen } from 'lucide-react';
import Link from 'next/link';

export default function ActualitesPage() {
    const [articles, setArticles] = useState<Article[]>([]);
    const [loading, setLoading] = useState(true);
    const [categoryFilter, setCategoryFilter] = useState('all');

    useEffect(() => {
        const loadArticles = async () => {
            setLoading(true);
            try {
                const data = await api.getArticles(categoryFilter !== 'all' ? categoryFilter : undefined);
                setArticles(data);
            } catch (e) {
                console.error('Error loading articles:', e);
            } finally {
                setLoading(false);
            }
        };
        loadArticles();
    }, [categoryFilter]);

    return (
        <div className="bg-[#fbf9f6] py-16 min-h-screen">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                {/* Header */}
                <div className="text-center max-w-3xl mx-auto space-y-4 mb-16">
                    <span className="text-[11px] font-extrabold uppercase tracking-[0.24em] text-[#8a5d16]">centre d&apos;actualités</span>
                    <h1 className="text-4xl sm:text-5xl font-extrabold text-[#241b16] tracking-tight leading-tight">
                        Actualités & Événements ProsArtisan
                    </h1>
                    <p className="text-sm text-[#746251] leading-relaxed">
                        Suivez le développement et la vie de notre écosystème : partenariats avec les quincailleries, lancements de nouveaux services techniques et bilans de chantiers solidaires.
                    </p>
                </div>

                {/* Filter buttons */}
                <div className="flex flex-wrap justify-center gap-3 mb-12">
                    {[
                        { label: 'Tous les articles', value: 'all' },
                        { label: 'Actualités', value: 'actualite' },
                        { label: 'Partenariats', value: 'partenariat' },
                        { label: 'Événements', value: 'evenement' },
                        { label: 'Témoignages', value: 'temoignage' }
                    ].map(f => (
                        <button
                            key={f.value}
                            onClick={() => setCategoryFilter(f.value)}
                            className={`rounded-full px-5 py-2 text-xs font-bold transition border ${
                                categoryFilter === f.value
                                    ? 'bg-[#ebb95e] border-[#ebb95e] text-[#241b16]'
                                    : 'bg-white border-[#e6d3b2]/60 text-[#746251] hover:bg-[#efe6da]/20'
                            }`}
                        >
                            {f.label}
                        </button>
                    ))}
                </div>

                {/* Articles grid */}
                {loading ? (
                    <div className="flex flex-col items-center justify-center py-24 gap-3">
                        <div className="h-10 w-10 border-4 border-[#ebb95e] border-t-transparent rounded-full animate-spin" />
                        <p className="text-xs text-[#746251] font-semibold animate-pulse">Chargement des actualités...</p>
                    </div>
                ) : articles.length === 0 ? (
                    <div className="text-center py-20 bg-white border border-[#e6d3b2]/50 rounded-[32px] p-8 max-w-xl mx-auto space-y-4">
                        <div className="inline-flex p-3 bg-amber-100/50 text-[#8a5d16] rounded-2xl">
                            <BookOpen className="h-6 w-6" />
                        </div>
                        <h3 className="text-lg font-bold text-[#241b16]">Aucun article publié</h3>
                        <p className="text-xs text-[#746251] leading-relaxed">
                            Nous n&apos;avons trouvé aucun article publié dans cette catégorie pour le moment.
                        </p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
                        {articles.map((article) => (
                            <Link
                                href={`/actualites/${article.slug}`}
                                key={article.id}
                                className="bg-white border border-[#e6d3b2]/40 rounded-[32px] overflow-hidden shadow-sm hover:shadow-md transition flex flex-col h-full group"
                            >
                                {/* Cover image */}
                                <div className="aspect-video bg-zinc-100 overflow-hidden relative">
                                    <img
                                        src={article.image_url || 'https://images.unsplash.com/photo-1531403009284-440f080d1e12?auto=format&fit=crop&w=500&q=80'}
                                        alt={article.titre}
                                        className="w-full h-full object-cover group-hover:scale-102 transition duration-300"
                                    />
                                    <span className="absolute bottom-4 left-4 px-2.5 py-0.5 bg-white/95 backdrop-blur-md rounded-md text-[9px] font-bold uppercase tracking-wider text-[#8a5d16] border border-[#e6d3b2]/30">
                                        {article.categorie}
                                    </span>
                                </div>

                                {/* Content description */}
                                <div className="p-6 flex-grow flex flex-col justify-between space-y-4">
                                    <div className="space-y-2">
                                        <p className="text-[10px] font-bold text-[#746251] uppercase tracking-wider flex items-center gap-1.5">
                                            <Calendar className="h-3 w-3 text-[#ebb95e]" />
                                            <span>{new Date(article.publie_at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })}</span>
                                        </p>
                                        <h3 className="font-extrabold text-[#241b16] text-sm group-hover:text-[#8a5d16] transition leading-snug line-clamp-2">
                                            {article.titre}
                                        </h3>
                                        <div
                                            className="text-xs text-[#746251] leading-relaxed line-clamp-3"
                                            dangerouslySetInnerHTML={{ __html: article.contenu }}
                                        />
                                    </div>

                                    <span className="inline-flex items-center gap-1.5 text-xs font-bold text-[#8a5d16] group-hover:text-[#241b16] transition pt-2">
                                        <span>Lire l&apos;article</span>
                                        <ArrowRight className="h-3.5 w-3.5 group-hover:translate-x-1 transition" />
                                    </span>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
