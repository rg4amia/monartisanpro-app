'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { 
  ArrowRight, ShieldCheck, Award, Users, 
  MapPin, Star, Play, Calendar, Landmark, CheckCircle2, ChevronLeft, ChevronRight 
} from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';
import { api, Slide, Artisan, ArtisanDuMois, Article, Video, Formation } from '@/lib/api';

function formatEmbedUrl(rawUrl: string): string {
  if (!rawUrl) return '';
  const ytMatch = rawUrl.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|v\/|shorts\/))([\w-]{11})/);
  if (ytMatch && ytMatch[1]) {
    return `https://www.youtube.com/embed/${ytMatch[1]}?autoplay=1&rel=0`;
  }
  const vimeoMatch = rawUrl.match(/vimeo\.com\/(\d+)/);
  if (vimeoMatch && vimeoMatch[1]) {
    return `https://player.vimeo.com/video/${vimeoMatch[1]}?autoplay=1`;
  }
  return rawUrl;
}

export default function HomePage() {
  const [slides, setSlides] = useState<Slide[]>([]);
  const [currentSlide, setCurrentSlide] = useState(0);
  const [artisanDuMois, setArtisanDuMois] = useState<ArtisanDuMois | null>(null);
  const [topArtisans, setTopArtisans] = useState<Artisan[]>([]);
  const [videos, setVideos] = useState<Video[]>([]);
  const [formations, setFormations] = useState<Formation[]>([]);
  const [articles, setArticles] = useState<Article[]>([]);
  const [settings, setSettings] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(true);

  // Video modal player
  const [activeVideoUrl, setActiveVideoUrl] = useState<string | null>(null);

  useEffect(() => {
    async function loadData() {
      try {
        const [
          fetchedSlides,
          fetchedAdm,
          fetchedStars,
          fetchedVideos,
          fetchedFormations,
          fetchedArticles,
          fetchedSettings
        ] = await Promise.all([
          api.getSlides(),
          api.getArtisanDuMois(),
          api.getArtisansStars(),
          api.getVideos(),
          api.getFormations(),
          api.getArticles(),
          api.getSettings()
        ]);

        setSlides(fetchedSlides);
        setArtisanDuMois(fetchedAdm);
        setTopArtisans(fetchedStars.slice(0, 4));
        setVideos(fetchedVideos.slice(0, 3));
        setFormations(fetchedFormations.slice(0, 2));
        setArticles(fetchedArticles.slice(0, 3));
        setSettings(fetchedSettings);
      } catch (e) {
        console.error("Error loading homepage data:", e);
      } finally {
        setLoading(false);
      }
    }
    loadData();
  }, []);

  // Slide auto-play
  useEffect(() => {
    if (slides.length <= 1) return;
    const interval = setInterval(() => {
      setCurrentSlide((prev) => (prev + 1) % slides.length);
    }, 8000);
    return () => clearInterval(interval);
  }, [slides]);

  if (loading) {
    return (
      <div className="min-h-screen bg-[#fbf9f6] flex items-center justify-center">
        <div className="flex flex-col items-center gap-4">
          <Image
            src="/img/prosartisan-logo.png"
            alt="ProsArtisan — Professionnel de l'Artisanat"
            width={160}
            height={56}
            className="h-14 w-auto object-contain animate-pulse"
            priority
          />
          <p className="text-sm font-bold text-[#746251] animate-pulse">Chargement de la vitrine...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="bg-[#fbf9f6] min-h-screen">
      {/* 1. Hero Section (Slideshow) */}
      <section className="relative h-[85vh] md:h-[90vh] overflow-hidden bg-[#241b16]">
        <AnimatePresence mode="wait">
          {slides.length > 0 && (
            <motion.div
              key={currentSlide}
              initial={{ opacity: 0, scale: 1.05 }}
              animate={{ opacity: 1, scale: 1 }}
              exit={{ opacity: 0 }}
              transition={{ duration: 1 }}
              className="absolute inset-0"
            >
              {/* Slide image */}
              <div 
                className="absolute inset-0 bg-cover bg-center"
                style={{ backgroundImage: `url(${slides[currentSlide].image_url})` }}
              />
              {/* Premium dark gradient overlay */}
              <div className="absolute inset-0 bg-gradient-to-t from-[#241b16] via-[#241b16]/75 to-transparent" />
              
              {/* Slide text */}
              <div className="absolute inset-0 flex items-center">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
                  <div className="max-w-3xl space-y-6">
                    <motion.span 
                      initial={{ opacity: 0, y: 15 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ delay: 0.2 }}
                      className="inline-flex items-center gap-1.5 px-3 py-1 bg-[#ebb95e]/20 border border-[#ebb95e]/30 rounded-full text-xs font-bold uppercase tracking-wider text-[#ebb95e]"
                    >
                      <ShieldCheck className="h-4 w-4" />
                      <span>{settings.vitrine_hero_subtitle ? "Label de Confiance" : "Marketplace Sécurisée"}</span>
                    </motion.span>
                    
                    <motion.h1 
                      initial={{ opacity: 0, y: 20 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ delay: 0.3 }}
                      className="text-4xl sm:text-5xl md:text-6xl font-extrabold text-white tracking-tight leading-tight"
                    >
                      {slides[currentSlide].titre}
                    </motion.h1>
                    
                    <motion.p 
                      initial={{ opacity: 0, y: 20 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ delay: 0.4 }}
                      className="text-lg text-[#efe6da]/90 font-medium leading-relaxed max-w-2xl"
                    >
                      {slides[currentSlide].sous_titre}
                    </motion.p>
                    
                    <motion.div 
                      initial={{ opacity: 0, y: 20 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ delay: 0.5 }}
                      className="pt-4 flex flex-wrap gap-4"
                    >
                      {slides[currentSlide].cta_lien && (
                        <Link
                          href={slides[currentSlide].cta_lien || '#'}
                          className="bg-[#ebb95e] hover:bg-[#8a5d16] hover:text-white text-[#241b16] text-sm font-extrabold px-8 py-4 rounded-full flex items-center gap-2 transition-all shadow-lg hover:shadow-[#ebb95e]/20 transform hover:-translate-y-0.5 active:translate-y-0"
                        >
                          <span>{slides[currentSlide].cta_texte || 'Démarrer'}</span>
                          <ArrowRight className="h-4 w-4" />
                        </Link>
                      )}
                      <Link
                        href="/services"
                        className="bg-white/10 hover:bg-white/20 border border-white/20 text-white text-sm font-extrabold px-8 py-4 rounded-full transition-all"
                      >
                        En savoir plus
                      </Link>
                    </motion.div>
                  </div>
                </div>
              </div>
            </motion.div>
          )}
        </AnimatePresence>

        {/* Slide navigation controls */}
        {slides.length > 1 && (
          <div className="absolute bottom-10 right-10 flex items-center gap-3 z-10">
            <button
              onClick={() => setCurrentSlide((prev) => (prev - 1 + slides.length) % slides.length)}
              className="p-3 rounded-full bg-white/10 hover:bg-white/20 text-white border border-white/15 transition-all active:scale-95"
            >
              <ChevronLeft className="h-5 w-5" />
            </button>
            <button
              onClick={() => setCurrentSlide((prev) => (prev + 1) % slides.length)}
              className="p-3 rounded-full bg-white/10 hover:bg-white/20 text-white border border-white/15 transition-all active:scale-95"
            >
              <ChevronRight className="h-5 w-5" />
            </button>
          </div>
        )}
      </section>

      {/* 2. Key Metrics Stats Section */}
      <section className="relative z-10 -mt-16 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-6 p-8 rounded-[36px] bg-white border border-[#e6d3b2] shadow-xl">
          {[
            {
              label: settings.stat_artisans_label || settings.chiffres_cles_artisans_label || "Artisans agréés",
              value: settings.stat_artisans_valeur || settings.chiffres_cles_artisans || "2 500+",
              icon: Users,
              color: "text-[#8a5d16]"
            },
            {
              label: settings.stat_missions_label || settings.chiffres_cles_missions_label || "Missions terminées",
              value: settings.stat_missions_valeur || settings.chiffres_cles_missions || "14 800+",
              icon: CheckCircle2,
              color: "text-emerald-700"
            },
            {
              label: settings.stat_communes_label || settings.chiffres_cles_communes_label || "Communes desservies",
              value: settings.stat_communes_valeur || settings.chiffres_cles_communes || "10 Abidjan",
              icon: MapPin,
              color: "text-blue-700"
            },
            {
              label: settings.stat_satisfaction_label || settings.chiffres_cles_satisfaction_label || "Satisfaction client",
              value: settings.stat_satisfaction_valeur || settings.chiffres_cles_satisfaction || "4.8 / 5",
              icon: Star,
              color: "text-amber-500"
            }
          ].map((stat, i) => (
            <div key={i} className="text-center space-y-2 border-r last:border-0 border-[#e6d3b2]/40">
              <div className="inline-flex p-3 bg-[#f7efe2]/60 rounded-2xl">
                <stat.icon className={`h-6 w-6 ${stat.color}`} />
              </div>
              <h3 className="text-2xl font-extrabold text-[#241b16]">{stat.value}</h3>
              <p className="text-xs font-semibold text-[#746251] uppercase tracking-wider">{stat.label}</p>
            </div>
          ))}
        </div>
      </section>

      {/* 3. Artisan of the Month Spotlight */}
      {artisanDuMois && (
        <section className="py-24 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="relative rounded-[48px] bg-gradient-to-tr from-[#241b16] to-[#45362e] border border-[#e6d3b2]/20 overflow-hidden shadow-2xl p-8 sm:p-12 lg:p-16 flex flex-col lg:flex-row items-center gap-12">
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(235,185,94,0.08),transparent)] pointer-events-none" />
            
            {/* Image spotlight */}
            <div className="w-full lg:w-1/3 flex justify-center shrink-0">
              <div className="relative">
                <div className="absolute -inset-4 bg-gradient-to-tr from-[#ebb95e] to-[#8a5d16] rounded-[36px] blur-lg opacity-40 animate-pulse" />
                <div className="relative h-80 w-80 rounded-[32px] overflow-hidden border-4 border-[#ebb95e] bg-[#241b16]">
                  <Image
                    src={artisanDuMois.photo_url}
                    alt={artisanDuMois.artisan.name}
                    fill
                    className="object-cover"
                    unoptimized
                  />
                  <div className="absolute bottom-4 left-4 right-4 bg-white/95 backdrop-blur-md rounded-2xl p-3 border border-[#e6d3b2]/40 flex items-center justify-between shadow-lg">
                    <div>
                      <p className="font-extrabold text-[#241b16] text-sm">{artisanDuMois.artisan.name}</p>
                      <p className="text-[10px] font-bold text-[#8a5d16] uppercase">{artisanDuMois.artisan.trade}</p>
                    </div>
                    <div className="flex items-center gap-1 px-2.5 py-1 bg-amber-500/10 border border-amber-500/20 rounded-lg text-amber-700 font-black text-xs">
                      <Star className="h-3.5 w-3.5 fill-current" />
                      <span>{artisanDuMois.artisan.note_moyenne ? artisanDuMois.artisan.note_moyenne.toFixed(1) : (artisanDuMois.artisan.score_prosartisan / 200).toFixed(1)}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {/* Editorial Content */}
            <div className="w-full lg:w-2/3 space-y-6 text-[#efe6da]">
              <span className="inline-flex items-center gap-1.5 px-3 py-1 bg-[#ebb95e]/15 border border-[#ebb95e]/30 rounded-full text-xs font-bold uppercase tracking-wider text-[#ebb95e]">
                <Award className="h-4 w-4" />
                <span>Artisan du mois</span>
              </span>

              <h2 className="text-3xl sm:text-4xl font-extrabold tracking-tight text-white leading-tight">
                Mise à l&apos;honneur de notre artisan vedette
              </h2>

              <p className="text-base text-[#efe6da]/80 leading-relaxed font-medium">
                &quot;{artisanDuMois.texte_editorial}&quot;
              </p>

              <div className="pt-4 border-t border-[#e6d3b2]/10 flex flex-wrap items-center gap-6">
                <div>
                  <p className="text-[10px] uppercase font-bold tracking-wider text-[#ebb95e]">Score de Confiance</p>
                  <p className="text-2xl font-black text-white">{artisanDuMois.artisan.score_prosartisan} <span className="text-xs text-[#efe6da]/60">/ 1000</span></p>
                </div>
                <div>
                  <p className="text-[10px] uppercase font-bold tracking-wider text-[#ebb95e]">Taux de succès</p>
                  <p className="text-2xl font-black text-white">{artisanDuMois.artisan.taux_succes !== undefined ? `${artisanDuMois.artisan.taux_succes}%` : '100%'}</p>
                </div>
                <div>
                  <p className="text-[10px] uppercase font-bold tracking-wider text-[#ebb95e]">Zone principale</p>
                  <p className="text-lg font-bold text-white">{artisanDuMois.artisan.zone || 'Abidjan, Côte d\'Ivoire'}</p>
                </div>
              </div>
            </div>
          </div>
        </section>
      )}

      {/* Artisans Stars Grid */}
      {topArtisans.length > 0 && (
        <section className="py-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-12">
            <div className="space-y-3">
              <span className="text-[11px] font-extrabold uppercase tracking-[0.24em] text-[#8a5d16]">talents labellisés</span>
              <h2 className="text-3xl font-extrabold text-[#241b16] tracking-tight">
                Nos artisans les mieux notés
              </h2>
            </div>
            <Link 
              href="/artisans" 
              className="group inline-flex items-center gap-1.5 text-sm font-bold text-[#8a5d16] hover:text-[#241b16] transition"
            >
              <span>Voir l&apos;annuaire complet</span>
              <ArrowRight className="h-4 w-4 group-hover:translate-x-1 transition" />
            </Link>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            {topArtisans.map((artisan) => (
              <div
                key={artisan.id}
                className="bg-white border border-[#e6d3b2]/40 rounded-[28px] overflow-hidden shadow-sm hover:shadow-md transition flex flex-col justify-between"
              >
                <div>
                  <div className="aspect-square bg-zinc-950 overflow-hidden relative">
                    <Image
                      src={artisan.kyc_selfie_path || 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=300&q=80'}
                      alt={artisan.name}
                      fill
                      className="object-cover"
                      unoptimized
                    />
                    <div className="absolute top-4 left-4 right-4 flex justify-between items-center">
                      <span className="px-2.5 py-0.5 bg-black/60 rounded-md text-[9px] font-bold uppercase tracking-wider text-white">
                        Vérifié
                      </span>
                      <div className="flex items-center gap-1 px-2 py-0.5 bg-amber-500/90 rounded-md text-white font-bold text-[10px]">
                        <Star className="h-3 w-3 fill-current" />
                        <span>{(artisan.score_prosartisan / 200).toFixed(1)}</span>
                      </div>
                    </div>
                  </div>
                  <div className="p-5 space-y-2">
                    <h3 className="font-extrabold text-[#241b16] text-sm">{artisan.name}</h3>
                    <p className="text-[10px] font-bold text-[#8a5d16] uppercase">{artisan.trade || 'Artisan'}</p>
                    <p className="text-xs text-[#746251]">{artisan.city || 'Abidjan'}</p>
                  </div>
                </div>
                <div className="p-5 pt-0">
                  <div className="border-t border-[#e6d3b2]/10 pt-4 flex items-center justify-between text-xs text-[#746251]">
                    <span>Score : <strong>{artisan.score_prosartisan}</strong></span>
                    <Link
                      href={`/contact?artisan_id=${artisan.id}`}
                      className="px-3.5 py-1.5 bg-[#f7efe2] hover:bg-[#8a5d16] hover:text-white border border-[#e6d3b2]/50 rounded-xl text-[10px] font-bold text-[#8a5d16] transition"
                    >
                      Contacter
                    </Link>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </section>
      )}

      {/* 4. Nos Services & Mission Section */}
      <section className="py-24 bg-[#efe6da]/30 border-y border-[#e6d3b2]/30">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center max-w-3xl mx-auto space-y-4 mb-16">
            <span className="text-[11px] font-extrabold uppercase tracking-[0.24em] text-[#8a5d16]">nos activités</span>
            <h2 className="text-3xl sm:text-4xl font-extrabold text-[#241b16] tracking-tight">
              Un écosystème sécurisé pour tous vos travaux
            </h2>
            <p className="text-sm text-[#746251] leading-relaxed">
              ProsArtisan professionnalise les relations de chantiers en Côte d&apos;Ivoire en protégeant les fonds des clients tout en assurant l&apos;approvisionnement matériel et le micro-crédit des artisans.
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            {[
              {
                title: "Mise en relation & Diagnostic IA",
                desc: "Des diagnostics précis alimentés par Gemini IA. Nous trouvons instantanément les meilleurs artisans dans un rayon de 2 km autour de vous, tout en floutant leur position à 50 m pour garantir leur sécurité.",
                icon: ShieldCheck,
                color: "from-amber-500/10 via-amber-500/5 to-transparent",
                border: "border-amber-200"
              },
              {
                title: "Formations & Labellisation",
                desc: "Nous certifions nos artisans partenaires aux normes de sécurité techniques et aux outils digitaux ProsArtisan, afin de garantir un travail de qualité supérieure sur tous vos chantiers.",
                icon: Award,
                color: "from-emerald-500/10 via-emerald-500/5 to-transparent",
                border: "border-emerald-200"
              },
              {
                title: "Financement & Micro-crédit",
                desc: "Accès à des portefeuilles segmentés J-Codes (matériaux) bloqués et main d'œuvre libérée par OTP. Micro-crédit d'urgence disponible sous 2h pour les artisans justifiant d'un excellent score.",
                icon: Landmark,
                color: "from-blue-500/10 via-blue-500/5 to-transparent",
                border: "border-blue-200"
              }
            ].map((service, index) => (
              <div 
                key={index}
                className={`bg-white border ${service.border} bg-gradient-to-br ${service.color} rounded-[32px] p-8 space-y-5 shadow-sm hover:shadow-md transition`}
              >
                <div className="inline-flex p-4 bg-white border border-[#e6d3b2]/30 rounded-2xl shadow-sm text-[#8a5d16]">
                  <service.icon className="h-6 w-6" />
                </div>
                <h3 className="text-lg font-extrabold text-[#241b16]">{service.title}</h3>
                <p className="text-xs text-[#746251] leading-relaxed">{service.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* 5. Capsule Vidéos Section */}
      {videos.length > 0 && (
        <section className="py-24 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-16">
            <div className="space-y-4">
              <span className="text-[11px] font-extrabold uppercase tracking-[0.24em] text-[#8a5d16]">médiathèque & capsules</span>
              <h2 className="text-3xl sm:text-4xl font-extrabold text-[#241b16] tracking-tight">
                Vidéos & capsules d&apos;activités
              </h2>
            </div>
            <Link 
              href="/videos" 
              className="group inline-flex items-center gap-1.5 text-sm font-bold text-[#8a5d16] hover:text-[#241b16] transition"
            >
              <span>Voir toute la médiathèque</span>
              <ArrowRight className="h-4 w-4 group-hover:translate-x-1 transition" />
            </Link>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            {videos.map((video) => {
              const getCatBadge = (cat: string) => {
                switch(cat) {
                  case 'capsule': return 'Capsule Pédagogique';
                  case 'formation': return 'Formation';
                  case 'temoignage': return 'Témoignage';
                  case 'evenement': return 'Événement';
                  default: return cat;
                }
              };

              return (
                <div 
                  key={video.id}
                  className="bg-white border border-[#e6d3b2]/40 rounded-[28px] overflow-hidden shadow-sm hover:shadow-xl transition group flex flex-col justify-between"
                >
                  <div>
                    {/* Thumbnail space */}
                    <div className="relative aspect-video bg-zinc-950 overflow-hidden cursor-pointer" onClick={() => setActiveVideoUrl(video.video_url)}>
                      <Image
                        src={video.thumbnail_url || 'https://images.unsplash.com/photo-1541534741688-6078c6bfb5c5?auto=format&fit=crop&w=600&q=80'}
                        alt={video.titre}
                        fill
                        className="object-cover group-hover:scale-105 transition duration-300"
                        unoptimized
                      />
                      <div className="absolute inset-0 bg-black/35 group-hover:bg-black/20 flex items-center justify-center transition">
                        <div className="h-14 w-14 rounded-full bg-white/90 backdrop-blur-md flex items-center justify-center text-[#8a5d16] shadow-lg group-hover:scale-110 transition duration-300">
                          <Play className="h-6 w-6 fill-current translate-x-0.5" />
                        </div>
                      </div>
                      <span className="absolute top-4 left-4 px-3 py-1 bg-black/70 backdrop-blur-md rounded-full text-[10px] font-bold uppercase tracking-wider text-white">
                        {getCatBadge(video.categorie)}
                      </span>
                    </div>
                    
                    {/* Info block */}
                    <div className="p-6 space-y-2">
                      <h3 className="font-extrabold text-[#241b16] text-base group-hover:text-[#8a5d16] transition line-clamp-1">
                        {video.titre}
                      </h3>
                      {video.description && (
                        <p className="text-xs text-[#746251] leading-relaxed line-clamp-2">
                          {video.description}
                        </p>
                      )}
                    </div>
                  </div>

                  <div className="p-6 pt-0">
                    <button
                      onClick={() => setActiveVideoUrl(video.video_url)}
                      className="w-full py-2.5 px-4 rounded-xl bg-[#f7efe2] hover:bg-[#ebb95e] text-[#241b16] text-xs font-extrabold flex items-center justify-center gap-2 transition"
                    >
                      <Play className="h-3.5 w-3.5 fill-current" />
                      <span>Visionner</span>
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        </section>
      )}

      {/* 6. Sessions de Formation Section */}
      {formations.length > 0 && (
        <section className="py-24 bg-[#efe6da]/20 border-t border-[#e6d3b2]/20">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-16">
              <div className="space-y-4">
                <span className="text-[11px] font-extrabold uppercase tracking-[0.24em] text-[#8a5d16]">renforcement de capacités</span>
                <h2 className="text-3xl sm:text-4xl font-extrabold text-[#241b16] tracking-tight">
                  Prochaines sessions de formation
                </h2>
              </div>
              <Link 
                href="/formations" 
                className="group inline-flex items-center gap-1.5 text-sm font-bold text-[#8a5d16] hover:text-[#241b16] transition"
              >
                <span>Consulter le calendrier</span>
                <ArrowRight className="h-4 w-4 group-hover:translate-x-1 transition animate-bounce-x" />
              </Link>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
              {formations.map((formation) => (
                <div 
                  key={formation.id}
                  className="bg-white border border-[#e6d3b2]/40 rounded-[32px] overflow-hidden shadow-sm hover:shadow-md transition flex flex-col sm:flex-row gap-6 p-6"
                >
                  {/* Formation image */}
                  <div className="w-full sm:w-1/3 aspect-square sm:aspect-auto sm:h-auto rounded-2xl overflow-hidden bg-zinc-200 shrink-0 relative min-h-[140px]">
                    <Image
                      src={formation.image_url || 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=300&q=80'}
                      alt={formation.titre}
                      fill
                      className="object-cover"
                      unoptimized
                    />
                  </div>

                  {/* Formation description */}
                  <div className="flex-grow flex flex-col justify-between space-y-4">
                    <div className="space-y-2">
                      <span className="inline-flex items-center gap-1 px-2.5 py-0.5 bg-[#eef8f0] border border-green-300/40 text-green-700 font-extrabold text-[9px] uppercase tracking-wider rounded-md">
                        {formation.tarif === 0 ? 'Gratuite' : `${formation.tarif.toLocaleString('fr-FR')} FCFA`}
                      </span>
                      <h3 className="font-extrabold text-[#241b16] text-base leading-snug">
                        {formation.titre}
                      </h3>
                      <p className="text-xs text-[#746251] leading-relaxed line-clamp-3">
                        {formation.description}
                      </p>
                    </div>

                    <div className="space-y-3 pt-2 border-t border-[#e6d3b2]/20 text-[11px] text-[#746251]">
                      <div className="flex items-center gap-2">
                        <Calendar className="h-3.5 w-3.5 text-[#ebb95e] shrink-0" />
                        <span>Date : <strong>{new Date(formation.date_debut).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })}</strong></span>
                      </div>
                      <div className="flex items-center gap-2">
                        <MapPin className="h-3.5 w-3.5 text-[#ebb95e] shrink-0" />
                        <span className="truncate">Lieu : <strong>{formation.lieu}</strong></span>
                      </div>
                      {formation.places_restantes !== null && (
                        <div className="flex items-center gap-2">
                          <Users className="h-3.5 w-3.5 text-[#ebb95e] shrink-0" />
                          <span>Places : <strong className="text-emerald-700">{formation.places_restantes} restantes</strong> / {formation.places_total}</span>
                        </div>
                      )}
                    </div>

                    {formation.lien_inscription && (
                      <Link
                        href={formation.lien_inscription}
                        className="w-full text-center bg-[#241b16] hover:bg-[#8a5d16] text-white text-xs font-bold py-2.5 rounded-full transition shadow-sm"
                      >
                        S&apos;inscrire à la session
                      </Link>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </div>
        </section>
      )}

      {/* 7. Actualités & Événements Section */}
      {articles.length > 0 && (
        <section className="py-24 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-16">
            <div className="space-y-4">
              <span className="text-[11px] font-extrabold uppercase tracking-[0.24em] text-[#8a5d16]">actualités</span>
              <h2 className="text-3xl sm:text-4xl font-extrabold text-[#241b16] tracking-tight">
                Dernières nouvelles & articles
              </h2>
            </div>
            <Link 
              href="/actualites" 
              className="group inline-flex items-center gap-1.5 text-sm font-bold text-[#8a5d16] hover:text-[#241b16] transition"
            >
              <span>Consulter l&apos;actualité</span>
              <ArrowRight className="h-4 w-4 group-hover:translate-x-1 transition" />
            </Link>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            {articles.map((article) => (
              <Link 
                href={`/actualites/${article.slug}`} 
                key={article.id}
                className="bg-white border border-[#e6d3b2]/40 rounded-[32px] overflow-hidden shadow-sm hover:shadow-md transition flex flex-col h-full group"
              >
                {/* Image */}
                <div className="aspect-video bg-zinc-100 overflow-hidden relative">
                  <Image
                    src={article.image_url || 'https://images.unsplash.com/photo-1531403009284-440f080d1e12?auto=format&fit=crop&w=500&q=80'}
                    alt={article.titre}
                    fill
                    className="object-cover group-hover:scale-103 transition duration-300"
                    unoptimized
                  />
                  <span className="absolute bottom-4 left-4 px-2.5 py-0.5 bg-white/95 backdrop-blur-md rounded-md text-[9px] font-bold uppercase tracking-wider text-[#8a5d16] border border-[#e6d3b2]/30">
                    {article.categorie}
                  </span>
                </div>

                {/* Content */}
                <div className="p-6 flex-grow flex flex-col justify-between space-y-4">
                  <div className="space-y-2">
                    <p className="text-[10px] font-bold text-[#746251] uppercase tracking-wider">
                      {new Date(article.publie_at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })}
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
                    <span>Lire la suite</span>
                    <ArrowRight className="h-3.5 w-3.5 group-hover:translate-x-1 transition" />
                  </span>
                </div>
              </Link>
            ))}
          </div>
        </section>
      )}

      {/* 8. Call to Action Banner */}
      <section className="py-24 bg-[#241b16] text-[#efe6da] relative overflow-hidden border-t border-[#e6d3b2]/10">
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_bottom_left,rgba(235,185,94,0.06),transparent)] pointer-events-none" />
        <div className="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 space-y-8">
          <span className="inline-flex items-center gap-1.5 px-3 py-1 bg-[#ebb95e]/10 border border-[#ebb95e]/20 rounded-full text-xs font-bold uppercase tracking-wider text-[#ebb95e]">
            Rejoignez-nous
          </span>
          <h2 className="text-3xl sm:text-5xl font-black text-white tracking-tight leading-tight">
            Prêt à bâtir l&apos;artisanat de demain en Côte d&apos;Ivoire ?
          </h2>
          <p className="text-sm text-[#efe6da]/70 max-w-2xl mx-auto leading-relaxed">
            Que vous soyez client cherchant un pro labellisé, artisan qualifié à la recherche de chantiers ou quincaillerie souhaitant s&apos;affilier, ProsArtisan est fait pour vous.
          </p>
          <div className="pt-2 flex flex-col sm:flex-row justify-center gap-4">
            <Link
              href="/contact"
              className="bg-[#ebb95e] hover:bg-[#8a5d16] hover:text-white text-[#241b16] text-sm font-extrabold px-8 py-4 rounded-full transition shadow-lg"
            >
              Faire une demande de travaux
            </Link>
            <Link
              href="/contact?role=artisan"
              className="bg-white/10 hover:bg-white/20 border border-white/20 text-white text-sm font-extrabold px-8 py-4 rounded-full transition"
            >
              Devenir Artisan Partenaire
            </Link>
          </div>
        </div>
      </section>

      {/* Video Modal Player Component */}
      <AnimatePresence>
        {activeVideoUrl && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <motion.div 
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              onClick={() => setActiveVideoUrl(null)}
              className="fixed inset-0 bg-black/85 backdrop-blur-sm"
            />
            <motion.div 
              initial={{ opacity: 0, scale: 0.95 }}
              animate={{ opacity: 1, scale: 1 }}
              exit={{ opacity: 0, scale: 0.95 }}
              className="relative w-full max-w-[850px] aspect-video bg-black rounded-3xl overflow-hidden shadow-2xl border border-white/10 z-10"
            >
              <button 
                onClick={() => setActiveVideoUrl(null)}
                className="absolute top-4 right-4 z-20 bg-black/60 backdrop-blur-md text-white p-2 rounded-full hover:bg-white/15 transition"
              >
                <XClose className="h-5 w-5" />
              </button>
              <iframe
                src={formatEmbedUrl(activeVideoUrl)}
                title="Lecture vidéo"
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
