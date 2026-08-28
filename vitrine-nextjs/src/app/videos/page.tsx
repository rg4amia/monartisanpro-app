'use client';

import { useState, useEffect, useMemo } from 'react';
import Link from 'next/link';
import { Play, Search, Video as VideoIcon, ArrowLeft, GraduationCap, Lightbulb, MessageSquareQuote, Mic2 } from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';
import { api, Video } from '@/lib/api';

const categories = [
  { id: 'all', label: 'Toutes les vidéos', icon: VideoIcon },
  { id: 'capsule', label: 'Capsules Pédagogiques', icon: Lightbulb },
  { id: 'formation', label: 'Sessions Formations', icon: GraduationCap },
  { id: 'temoignage', label: 'Témoignages & Retours', icon: MessageSquareQuote },
  { id: 'evenement', label: 'Événements & Conférences', icon: Mic2 },
];

function formatEmbedUrl(rawUrl: string): string {
  if (!rawUrl) return '';
  // Convert YouTube youtu.be / watch?v= to embed
  const ytMatch = rawUrl.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|v\/|shorts\/))([\w-]{11})/);
  if (ytMatch && ytMatch[1]) {
    return `https://www.youtube.com/embed/${ytMatch[1]}?autoplay=1&rel=0`;
  }
  // Convert Vimeo
  const vimeoMatch = rawUrl.match(/vimeo\.com\/(\d+)/);
  if (vimeoMatch && vimeoMatch[1]) {
    return `https://player.vimeo.com/video/${vimeoMatch[1]}?autoplay=1`;
  }
  return rawUrl;
}

export default function VideosPage() {
  const [videos, setVideos] = useState<Video[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedCategory, setSelectedCategory] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [activeVideo, setActiveVideo] = useState<Video | null>(null);

  useEffect(() => {
    async function loadVideos() {
      try {
        const fetchedVideos = await api.getVideos();
        setVideos(fetchedVideos);
      } catch (err) {
        console.error('Erreur chargement vidéos:', err);
      } finally {
        setLoading(false);
      }
    }
    loadVideos();
  }, []);

  const filteredVideos = useMemo(() => {
    return videos.filter((vid) => {
      const matchCat = selectedCategory === 'all' || vid.categorie === selectedCategory;
      const matchSearch =
        searchQuery === '' ||
        vid.titre.toLowerCase().includes(searchQuery.toLowerCase()) ||
        (vid.description && vid.description.toLowerCase().includes(searchQuery.toLowerCase()));
      return matchCat && matchSearch;
    });
  }, [videos, selectedCategory, searchQuery]);

  const getCategoryLabel = (cat: string) => {
    switch (cat) {
      case 'capsule':
        return 'Capsule Pédagogique';
      case 'formation':
        return 'Formation';
      case 'temoignage':
        return 'Témoignage';
      case 'evenement':
        return 'Événement & Conférence';
      default:
        return cat;
    }
  };

  const getCategoryBadgeColor = (cat: string) => {
    switch (cat) {
      case 'capsule':
        return 'bg-amber-500/15 text-amber-800 border-amber-300';
      case 'formation':
        return 'bg-emerald-500/15 text-emerald-800 border-emerald-300';
      case 'temoignage':
        return 'bg-blue-500/15 text-blue-800 border-blue-300';
      case 'evenement':
        return 'bg-purple-500/15 text-purple-800 border-purple-300';
      default:
        return 'bg-stone-500/15 text-stone-800 border-stone-300';
    }
  };

  return (
    <div className="bg-[#fbf9f6] min-h-screen pb-28">
      {/* Header Banner */}
      <section className="bg-gradient-to-b from-[#241b16] to-[#3a2c24] text-white py-16 px-4 sm:px-6 lg:px-8 border-b border-[#e6d3b2]/20">
        <div className="max-w-7xl mx-auto space-y-4">
          <Link
            href="/"
            className="inline-flex items-center gap-2 text-xs font-bold text-[#ebb95e] hover:text-white transition"
          >
            <ArrowLeft className="h-4 w-4" />
            <span>Retour à l&apos;accueil</span>
          </Link>
          <div className="flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div className="space-y-2">
              <span className="text-[10px] font-extrabold uppercase tracking-[0.24em] text-[#ebb95e]">
                Médiathèque Officielle
              </span>
              <h1 className="text-3xl sm:text-5xl font-extrabold tracking-tight">
                Vidéos & Capsules ProsArtisan
              </h1>
              <p className="text-sm text-[#efe6da]/80 max-w-2xl">
                Découvrez nos tutoriels pratiques, sessions de formation, retours d&apos;expérience chantiers et reportages vidéo sur le secteur artisanal ivoirien.
              </p>
            </div>
          </div>
        </div>
      </section>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-6">
        {/* Filter bar & Search */}
        <div className="bg-white rounded-3xl p-5 border border-[#e6d3b2] shadow-xl space-y-4">
          <div className="flex flex-col lg:flex-row items-center justify-between gap-4">
            {/* Category tabs */}
            <div className="flex items-center gap-2 overflow-x-auto w-full lg:w-auto pb-2 lg:pb-0 scrollbar-none">
              {categories.map((cat) => {
                const Icon = cat.icon;
                const isSelected = selectedCategory === cat.id;
                return (
                  <button
                    key={cat.id}
                    onClick={() => setSelectedCategory(cat.id)}
                    className={`flex items-center gap-2 px-4 py-2.5 rounded-full text-xs font-bold transition-all whitespace-nowrap ${
                      isSelected
                        ? 'bg-[#ebb95e] text-[#241b16] shadow-sm'
                        : 'bg-[#fbf9f6] text-[#746251] hover:bg-[#f7efe2] hover:text-[#241b16] border border-[#e6d3b2]/40'
                    }`}
                  >
                    <Icon className="h-3.5 w-3.5" />
                    <span>{cat.label}</span>
                  </button>
                );
              })}
            </div>

            {/* Search Input */}
            <div className="relative w-full lg:w-72">
              <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-[#746251]/60" />
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Rechercher une vidéo..."
                className="w-full bg-[#fbf9f6] border border-[#e6d3b2]/60 rounded-full pl-9 pr-4 py-2 text-xs font-medium focus:outline-none focus:border-[#ebb95e] focus:bg-white transition"
              />
            </div>
          </div>
        </div>

        {/* Video Grid */}
        <div className="mt-12">
          {loading ? (
            <div className="py-24 text-center">
              <div className="inline-block h-8 w-8 animate-spin rounded-full border-4 border-[#ebb95e] border-r-transparent" />
              <p className="mt-3 text-xs font-bold text-[#746251]">Chargement des vidéos...</p>
            </div>
          ) : filteredVideos.length === 0 ? (
            <div className="bg-white rounded-3xl p-12 text-center border border-[#e6d3b2]/40 max-w-lg mx-auto space-y-3">
              <VideoIcon className="h-12 w-12 text-[#ebb95e] mx-auto opacity-70" />
              <h3 className="text-lg font-extrabold text-[#241b16]">Aucune vidéo disponible</h3>
              <p className="text-xs text-[#746251]">
                Aucune capsule ne correspond à votre filtre actuel. Essayez une autre catégorie ou réinitialisez la recherche.
              </p>
              <button
                onClick={() => {
                  setSelectedCategory('all');
                  setSearchQuery('');
                }}
                className="mt-2 text-xs font-bold text-[#8a5d16] underline"
              >
                Voir toutes les vidéos
              </button>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
              {filteredVideos.map((video) => (
                <div
                  key={video.id}
                  className="bg-white border border-[#e6d3b2]/40 rounded-[28px] overflow-hidden shadow-sm hover:shadow-xl transition group flex flex-col justify-between"
                >
                  <div>
                    {/* Thumbnail */}
                    <div
                      onClick={() => setActiveVideo(video)}
                      className="relative aspect-video bg-zinc-950 overflow-hidden cursor-pointer"
                    >
                      <img
                        src={
                          video.thumbnail_url ||
                          'https://images.unsplash.com/photo-1541534741688-6078c6bfb5c5?auto=format&fit=crop&w=600&q=80'
                        }
                        alt={video.titre}
                        className="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                      />
                      <div className="absolute inset-0 bg-black/35 group-hover:bg-black/20 flex items-center justify-center transition">
                        <div className="h-14 w-14 rounded-full bg-white/90 backdrop-blur-md flex items-center justify-center text-[#8a5d16] shadow-lg group-hover:scale-110 transition duration-300">
                          <Play className="h-6 w-6 fill-current translate-x-0.5" />
                        </div>
                      </div>
                      <span
                        className={`absolute top-4 left-4 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border ${getCategoryBadgeColor(
                          video.categorie
                        )}`}
                      >
                        {getCategoryLabel(video.categorie)}
                      </span>
                    </div>

                    {/* Content */}
                    <div className="p-6 space-y-2.5">
                      <h3 className="font-extrabold text-[#241b16] text-base group-hover:text-[#8a5d16] transition line-clamp-2">
                        {video.titre}
                      </h3>
                      {video.description && (
                        <p className="text-xs text-[#746251] leading-relaxed line-clamp-3">
                          {video.description}
                        </p>
                      )}
                    </div>
                  </div>

                  <div className="p-6 pt-0">
                    <button
                      onClick={() => setActiveVideo(video)}
                      className="w-full py-2.5 px-4 rounded-xl bg-[#f7efe2] hover:bg-[#ebb95e] text-[#241b16] text-xs font-extrabold flex items-center justify-center gap-2 transition"
                    >
                      <Play className="h-3.5 w-3.5 fill-current" />
                      <span>Visionner la vidéo</span>
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Video Modal Player */}
      <AnimatePresence>
        {activeVideo && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              onClick={() => setActiveVideo(null)}
              className="fixed inset-0 bg-black/85 backdrop-blur-sm"
            />
            <motion.div
              initial={{ opacity: 0, scale: 0.95 }}
              animate={{ opacity: 1, scale: 1 }}
              exit={{ opacity: 0, scale: 0.95 }}
              className="relative w-full max-w-[900px] aspect-video bg-black rounded-3xl overflow-hidden shadow-2xl border border-white/10 z-10"
            >
              <button
                onClick={() => setActiveVideo(null)}
                className="absolute top-4 right-4 z-20 bg-black/70 text-white p-2.5 rounded-full hover:bg-white/20 transition"
              >
                <XClose className="h-5 w-5" />
              </button>
              <iframe
                src={formatEmbedUrl(activeVideo.video_url)}
                title={activeVideo.titre}
                className="w-full h-full border-0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowFullScreen
              />
            </motion.div>
          </div>
        )}
      </AnimatePresence>
    </div>
  );
}

function XClose({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
      <path d="M6 18L18 6M6 6l12 12" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}
