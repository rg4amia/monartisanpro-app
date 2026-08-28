'use client';

import { useState, useEffect } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api, Article } from '@/lib/api';
import { Calendar, ChevronLeft, ArrowRight } from 'lucide-react';
import Link from 'next/link';

export default function ArticleDetailClient() {
    const params = useParams();
    const router = useRouter();
    const slug = params.slug as string;

    const [article, setArticle] = useState<Article | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const loadArticle = async () => {
            if (!slug) return;
            setLoading(true);
            try {
                const data = await api.getArticle(slug);
                setArticle(data);
            } catch (e) {
                console.error('Error loading article:', e);
            } finally {
                setLoading(false);
            }
        };
        loadArticle();
    }, [slug]);

    if (loading) {
        return (
            <div className="min-h-screen bg-[#fbf9f6] flex items-center justify-center">
                <div className="flex flex-col items-center gap-3">
                    <div className="h-8 w-8 border-4 border-[#ebb95e] border-t-transparent rounded-full animate-spin" />
                    <p className="text-xs text-[#746251] font-semibold animate-pulse">Chargement de l&apos;article...</p>
                </div>
            </div>
        );
    }

    if (!article) {
        return (
            <div className="min-h-screen bg-[#fbf9f6] flex items-center justify-center p-4">
                <div className="text-center bg-white border border-[#e6d3b2] rounded-[32px] p-8 max-w-md w-full space-y-4 shadow-sm">
                    <h3 className="text-lg font-bold text-[#241b16]">Article introuvable</h3>
                    <p className="text-xs text-[#746251]">L&apos;actualité demandée semble inexistante ou a été retirée de notre site.</p>
                    <button
                        onClick={() => router.push('/actualites')}
                        className="bg-[#241b16] hover:bg-[#8a5d16] text-white text-xs font-bold px-6 py-3 rounded-full inline-flex items-center gap-1.5 transition"
                    >
                        <ChevronLeft className="h-4 w-4" />
                        <span>Retourner aux actualités</span>
                    </button>
                </div>
            </div>
        );
    }

    return (
        <article className="bg-[#fbf9f6] py-16 min-h-screen">
            <div className="max-w-4xl mx-auto px-4 sm:px-6">
                {/* Back button */}
                <Link
                    href="/actualites"
                    className="inline-flex items-center gap-1.5 text-xs font-bold text-[#8a5d16] hover:text-[#241b16] transition mb-8 group"
                >
                    <ChevronLeft className="h-4 w-4 group-hover:-translate-x-0.5 transition" />
                    <span>Retour aux actualités</span>
                </Link>

                {/* Article Header */}
                <div className="space-y-4 mb-10">
                    <div className="flex items-center gap-3 text-xs text-[#746251]">
                        <span className="px-2.5 py-0.5 bg-[#f7efe2] border border-[#e6d3b2]/50 text-[#8a5d16] font-bold uppercase rounded-md">
                            {article.categorie}
                        </span>
                        <div className="flex items-center gap-1">
                            <Calendar className="h-3.5 w-3.5 text-[#ebb95e]" />
                            <span>{new Date(article.publie_at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })}</span>
                        </div>
                    </div>

                    <h1 className="text-3xl sm:text-4xl font-extrabold text-[#241b16] tracking-tight leading-tight">
                        {article.titre}
                    </h1>

                    <div className="flex items-center gap-2 pt-2 border-t border-[#e6d3b2]/20">
                        <div className="h-7 w-7 rounded-full bg-[#ebb95e] flex items-center justify-center text-white font-bold text-xs">
                            PA
                        </div>
                        <span className="text-xs text-[#241b16] font-bold">Écrit par l&apos;équipe ProsArtisan</span>
                    </div>
                </div>

                {/* Featured image */}
                {article.image_url && (
                    <div className="rounded-[36px] overflow-hidden border border-[#e6d3b2]/40 shadow-sm aspect-video mb-12">
                        <img
                            src={article.image_url}
                            alt={article.titre}
                            className="w-full h-full object-cover"
                        />
                    </div>
                )}

                {/* Article Content */}
                <div 
                    className="prose max-w-none text-[#241b16] text-sm leading-relaxed space-y-6"
                    dangerouslySetInnerHTML={{ __html: article.contenu }}
                />

                {/* Call to action at the bottom */}
                <div className="mt-16 bg-[#efe6da]/30 border border-[#e6d3b2]/40 rounded-[32px] p-6 sm:p-8 flex flex-col sm:flex-row items-center justify-between gap-6 shadow-sm">
                    <div className="space-y-1 text-center sm:text-left">
                        <h4 className="font-extrabold text-sm text-[#241b16]">Des travaux à réaliser ?</h4>
                        <p className="text-xs text-[#746251]">Trouvez l&apos;artisan idéal en Côte d&apos;Ivoire en quelques clics.</p>
                    </div>
                    <Link
                        href="/contact"
                        className="bg-[#241b16] hover:bg-[#8a5d16] text-white text-xs font-bold px-6 py-3 rounded-full flex items-center gap-1.5 transition shrink-0"
                    >
                        <span>Démarrer mon projet</span>
                        <ArrowRight className="h-4 w-4" />
                    </Link>
                </div>
            </div>
        </article>
    );
}
