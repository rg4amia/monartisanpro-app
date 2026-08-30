'use client';

import React from 'react';
import Link from 'next/link';
import Navbar from '@/components/Navbar';
import Footer from '@/components/Footer';
import { Shield, Lock, EyeOff, Server, Clock, Mail, ChevronRight, CheckCircle2 } from 'lucide-react';

export default function PolitiqueConfidentialitePage() {
    return (
        <div className="min-h-screen bg-[#faf8f5] text-[#2c221e] flex flex-col font-sans">
            <Navbar />

            {/* Hero Banner */}
            <div className="bg-gradient-to-r from-[#17261d] via-[#1f3528] to-[#17261d] text-white pt-36 pb-20 px-4 sm:px-6 lg:px-8 border-b border-emerald-500/20">
                <div className="max-w-4xl mx-auto text-center space-y-4">
                    <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-xs font-bold uppercase tracking-wider">
                        <Shield className="w-3.5 h-3.5" />
                        Protection des Données Personnelles
                    </div>
                    <h1 className="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight">
                        Politique de Confidentialité
                    </h1>
                    <p className="text-emerald-100/80 text-sm sm:text-base max-w-2xl mx-auto">
                        ProsArtisan Côte d'Ivoire &bull; Conformité stricte aux exigences de la Loi n° 2013-450 et aux directives de l'ARTCI.
                    </p>
                    <p className="text-xs text-emerald-400 font-medium">
                        Dernière mise à jour : 30 Août 2026
                    </p>
                </div>
            </div>

            {/* Quick Metrics */}
            <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8 w-full">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="bg-white p-4 rounded-2xl shadow-lg border border-[#e6d3b2]/40 flex items-center gap-3.5">
                        <div className="w-10 h-10 rounded-xl bg-emerald-500/15 flex items-center justify-center text-emerald-700 shrink-0">
                            <EyeOff className="w-5 h-5" />
                        </div>
                        <div>
                            <p className="text-xs font-bold text-[#8a7766] uppercase">Anonymat</p>
                            <p className="text-sm font-bold text-[#201712]">Floutage GPS 50m</p>
                        </div>
                    </div>
                    <div className="bg-white p-4 rounded-2xl shadow-lg border border-[#e6d3b2]/40 flex items-center gap-3.5">
                        <div className="w-10 h-10 rounded-xl bg-blue-500/15 flex items-center justify-center text-blue-700 shrink-0">
                            <Server className="w-5 h-5" />
                        </div>
                        <div>
                            <p className="text-xs font-bold text-[#8a7766] uppercase">Étanchéité</p>
                            <p className="text-sm font-bold text-[#201712]">Aucun partage tiers</p>
                        </div>
                    </div>
                    <div className="bg-white p-4 rounded-2xl shadow-lg border border-[#e6d3b2]/40 flex items-center gap-3.5">
                        <div className="w-10 h-10 rounded-xl bg-purple-500/15 flex items-center justify-center text-purple-700 shrink-0">
                            <Clock className="w-5 h-5" />
                        </div>
                        <div>
                            <p className="text-xs font-bold text-[#8a7766] uppercase">Conservation</p>
                            <p className="text-sm font-bold text-[#201712]">10 ans OHADA / Factures</p>
                        </div>
                    </div>
                </div>
            </div>

            {/* Main Content */}
            <main className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16 w-full flex-grow space-y-10">
                
                {/* Section 1 */}
                <section className="bg-white rounded-3xl p-6 sm:p-10 border border-[#e6d3b2]/40 shadow-sm space-y-4">
                    <div className="flex items-center gap-3 pb-3 border-b border-[#f0e4d0]">
                        <span className="w-8 h-8 rounded-full bg-emerald-100 text-emerald-800 font-black text-sm flex items-center justify-center">1</span>
                        <h2 className="text-xl sm:text-2xl font-bold text-[#201712]">
                            Préambule et Responsabilité du Traitement
                        </h2>
                    </div>
                    <div className="space-y-4 text-sm sm:text-base text-[#5c4a3e] leading-relaxed">
                        <p>
                            La présente politique définit la manière dont l'application <strong>ProsArtisan</strong> collecte, traite, stocke et protège les données à caractère personnel de ses utilisateurs (Clients, Artisans, Fournisseurs et Livreurs). ProsArtisan agit en tant que responsable de traitement exclusif de cet environnement.
                        </p>
                        <div className="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-950">
                            <p className="font-bold text-emerald-900 mb-1">Étanchéité institutionnelle absolue :</p>
                            <p className="text-xs sm:text-sm">
                                L'infrastructure de ProsArtisan opère dans une étanchéité absolue vis-à-vis de toute application institutionnelle tierce : aucune donnée utilisateur n'est synchronisée, partagée ou accessible par des démembrements administratifs ou des agences régionales.
                            </p>
                        </div>
                    </div>
                </section>

                {/* Section 2 */}
                <section className="bg-white rounded-3xl p-6 sm:p-10 border border-[#e6d3b2]/40 shadow-sm space-y-4">
                    <div className="flex items-center gap-3 pb-3 border-b border-[#f0e4d0]">
                        <span className="w-8 h-8 rounded-full bg-emerald-100 text-emerald-800 font-black text-sm flex items-center justify-center">2</span>
                        <h2 className="text-xl sm:text-2xl font-bold text-[#201712]">
                            Nature des Données Collectées
                        </h2>
                    </div>
                    <div className="space-y-4 text-sm sm:text-base text-[#5c4a3e] leading-relaxed">
                        <p>
                            L'architecture de collecte obéit au principe strict de <strong>minimisation</strong> (seules les données strictement nécessaires au service sont requises) :
                        </p>
                        <ul className="space-y-3 pl-2">
                            <li className="flex items-start gap-2">
                                <span className="w-2 h-2 rounded-full bg-emerald-600 mt-2 shrink-0"></span>
                                <div>
                                    <strong className="text-[#201712]">Données d’identification :</strong> Noms, prénoms, numéros de téléphone (vérifiés par code OTP), adresses e-mail. Pour les Artisans et Livreurs : copie numérisée de la pièce d'identité (CNI, Passeport, Attestation) et selfie liveness.
                                </div>
                            </li>
                            <li className="flex items-start gap-2">
                                <span className="w-2 h-2 rounded-full bg-emerald-600 mt-2 shrink-0"></span>
                                <div>
                                    <strong className="text-[#201712]">Données de localisation :</strong> Coordonnées GPS collectées uniquement lors de l'utilisation active de l'application, afin d'assurer le matching géographique entre la demande du Client et la position de l'Artisan. Un floutage systématique de 50 m est appliqué avant toute communication au client en phase de devis.
                                </div>
                            </li>
                            <li className="flex items-start gap-2">
                                <span className="w-2 h-2 rounded-full bg-emerald-600 mt-2 shrink-0"></span>
                                <div>
                                    <strong className="text-[#201712]">Données transactionnelles et financières :</strong> Historique des paiements, identifiants de transactions Mobile Money (Wave, Orange Money) ou bancaires. ProsArtisan ne stocke jamais les codes PIN ou mots de passe des portefeuilles électroniques.
                                </div>
                            </li>
                            <li className="flex items-start gap-2">
                                <span className="w-2 h-2 rounded-full bg-emerald-600 mt-2 shrink-0"></span>
                                <div>
                                    <strong className="text-[#201712]">Données d'activité et IA :</strong> Historique des recherches, photographies des réalisations sur chantier, évaluations et requêtes textuelles. Les requêtes de recherche sont transformées en vecteurs pour alimenter notre moteur de recommandation, après anonymisation des identifiants directs.
                                </div>
                            </li>
                        </ul>
                    </div>
                </section>

                {/* Section 3 */}
                <section className="bg-white rounded-3xl p-6 sm:p-10 border border-[#e6d3b2]/40 shadow-sm space-y-4">
                    <div className="flex items-center gap-3 pb-3 border-b border-[#f0e4d0]">
                        <span className="w-8 h-8 rounded-full bg-emerald-100 text-emerald-800 font-black text-sm flex items-center justify-center">3</span>
                        <h2 className="text-xl sm:text-2xl font-bold text-[#201712]">
                            Finalités du Traitement
                        </h2>
                    </div>
                    <div className="space-y-4 text-sm sm:text-base text-[#5c4a3e] leading-relaxed">
                        <p>
                            Vos données ne sont <strong>en aucun cas commercialisées ou revendues</strong> à des régies publicitaires. Elles sont exploitées par nos algorithmes et nos équipes de modération pour :
                        </p>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                            <div className="p-4 rounded-2xl bg-[#faf8f5] border border-[#e6d3b2]/60">
                                <p className="font-bold text-[#201712] text-sm">1. Authentification & Anti-Fraude</p>
                                <p className="text-xs text-[#6f5d50] mt-1">Vérifier l'authenticité des profils et lutter contre l'usurpation d'identité et le blanchiment.</p>
                            </div>
                            <div className="p-4 rounded-2xl bg-[#faf8f5] border border-[#e6d3b2]/60">
                                <p className="font-bold text-[#201712] text-sm">2. Matching Géolocalisé Intelligent</p>
                                <p className="text-xs text-[#6f5d50] mt-1">Générer des correspondances précises et ultra-locales via notre moteur de recherche sémantique.</p>
                            </div>
                            <div className="p-4 rounded-2xl bg-[#faf8f5] border border-[#e6d3b2]/60">
                                <p className="font-bold text-[#201712] text-sm">3. Sécurisation du Séquestre</p>
                                <p className="text-xs text-[#6f5d50] mt-1">Garantir la libération des fonds uniquement après validation OTP des jalons.</p>
                            </div>
                            <div className="p-4 rounded-2xl bg-[#faf8f5] border border-[#e6d3b2]/60">
                                <p className="font-bold text-[#201712] text-sm">4. Preuves Numériques & Arbitrage</p>
                                <p className="text-xs text-[#6f5d50] mt-1">Traiter équitablement les litiges grâce aux photos géolocalisées et à l'historique d'intervention.</p>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Section 4 */}
                <section className="bg-white rounded-3xl p-6 sm:p-10 border border-[#e6d3b2]/40 shadow-sm space-y-4">
                    <div className="flex items-center gap-3 pb-3 border-b border-[#f0e4d0]">
                        <span className="w-8 h-8 rounded-full bg-emerald-100 text-emerald-800 font-black text-sm flex items-center justify-center">4</span>
                        <h2 className="text-xl sm:text-2xl font-bold text-[#201712]">
                            Partage et Transfert des Données
                        </h2>
                    </div>
                    <div className="space-y-4 text-sm sm:text-base text-[#5c4a3e] leading-relaxed">
                        <p>
                            Dans le cadre exclusif de l'exécution du service, certaines données chiffrées sont transmises à des tiers de confiance :
                        </p>
                        <ul className="space-y-2 pl-4 list-disc marker:text-emerald-600">
                            <li><strong>Agrégateurs de paiement certifiés (Wave, Orange Money, MTN, Moov) :</strong> Pour l'exécution et la libération sécurisée des fonds.</li>
                            <li><strong>Fournisseurs d'infrastructure Cloud sécurisés :</strong> Pour l'hébergement chiffré des bases de données et des fichiers médias.</li>
                        </ul>
                        <p className="text-xs sm:text-sm text-[#6f5d50] italic bg-[#faf8f5] p-3.5 rounded-xl border border-[#e6d3b2]/50">
                            Tous les prestataires sont soumis à des clauses de confidentialité strictes. Aucune donnée n'est transférée en dehors de l'espace CEDEAO sans des garanties de sécurité équivalentes aux normes de l'ARTCI.
                        </p>
                    </div>
                </section>

                {/* Section 5 */}
                <section className="bg-white rounded-3xl p-6 sm:p-10 border border-[#e6d3b2]/40 shadow-sm space-y-4">
                    <div className="flex items-center gap-3 pb-3 border-b border-[#f0e4d0]">
                        <span className="w-8 h-8 rounded-full bg-emerald-100 text-emerald-800 font-black text-sm flex items-center justify-center">5</span>
                        <h2 className="text-xl sm:text-2xl font-bold text-[#201712]">
                            Sécurité et Conservation des Données
                        </h2>
                    </div>
                    <div className="space-y-4 text-sm sm:text-base text-[#5c4a3e] leading-relaxed">
                        <p>
                            Les flux de données sont sécurisés de bout en bout (protocoles TLS 1.3). Les pièces d'identité et les données sensibles sont chiffrées au repos (AES-256) dans nos bases de données.
                        </p>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
                            <div className="bg-[#faf8f5] p-4 rounded-2xl border border-[#e6d3b2]/60">
                                <p className="text-xs font-bold uppercase text-[#8a7766]">Données courantes</p>
                                <p className="text-sm font-bold text-[#201712] mt-1">Durée du compte actif</p>
                                <p className="text-xs text-[#6f5d50] mt-1">Profil, coordonnées de contact, historique de messagerie.</p>
                            </div>
                            <div className="bg-[#faf8f5] p-4 rounded-2xl border border-[#e6d3b2]/60">
                                <p className="text-xs font-bold uppercase text-[#8a7766]">Géolocalisation</p>
                                <p className="text-sm font-bold text-[#201712] mt-1">60 jours maximum</p>
                                <p className="text-xs text-[#6f5d50] mt-1">Coordonnées précises purgées ou anonymisées après travaux.</p>
                            </div>
                            <div className="bg-[#faf8f5] p-4 rounded-2xl border border-[#e6d3b2]/60">
                                <p className="text-xs font-bold uppercase text-[#8a7766]">Comptabilité</p>
                                <p className="text-sm font-bold text-[#201712] mt-1">10 ans (Droit OHADA)</p>
                                <p className="text-xs text-[#6f5d50] mt-1">Factures, séquestre et transactions financières.</p>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Section 6 */}
                <section className="bg-white rounded-3xl p-6 sm:p-10 border border-[#e6d3b2]/40 shadow-sm space-y-4">
                    <div className="flex items-center gap-3 pb-3 border-b border-[#f0e4d0]">
                        <span className="w-8 h-8 rounded-full bg-emerald-100 text-emerald-800 font-black text-sm flex items-center justify-center">6</span>
                        <h2 className="text-xl sm:text-2xl font-bold text-[#201712]">
                            Droits des Utilisateurs & Contact DPO
                        </h2>
                    </div>
                    <div className="space-y-4 text-sm sm:text-base text-[#5c4a3e] leading-relaxed">
                        <p>
                            Conformément à la législation ivoirienne en vigueur (Loi n° 2013-450), tout utilisateur dispose d’un droit d’accès, de rectification, de limitation et de suppression de ses données ("droit à l'oubli").
                        </p>
                        <p>
                            Pour exercer ces droits ou pour toute question relative au traitement de vos données, vous pouvez formuler votre requête directement depuis les paramètres de votre compte ou contacter notre <strong>Délégué à la Protection des Données (DPO)</strong> :
                        </p>
                        <div className="p-4 rounded-2xl bg-[#faf8f5] border border-[#e6d3b2] flex items-center gap-3">
                            <Mail className="w-5 h-5 text-emerald-700 shrink-0" />
                            <span className="text-sm font-bold text-[#201712]">
                                Courriel DPO : <a href="mailto:dpo@prosartisan.net" className="text-emerald-700 hover:underline">dpo@prosartisan.net</a>
                            </span>
                        </div>
                    </div>
                </section>

                {/* Bottom link to CGU */}
                <div className="p-6 rounded-3xl bg-[#f0e4d0]/60 border border-[#e6d3b2] flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div>
                        <h3 className="font-bold text-[#201712] text-base">Consultez nos Conditions Générales d'Utilisation</h3>
                        <p className="text-xs text-[#6f5d50] mt-1">Règles d'engagement, fonctionnement du séquestre financier et gestion des litiges.</p>
                    </div>
                    <Link
                        href="/cgu"
                        className="px-5 py-2.5 rounded-xl bg-[#201712] hover:bg-[#3d312a] text-white font-bold text-xs shrink-0 flex items-center gap-2 transition"
                    >
                        Consulter les CGU
                        <ChevronRight className="w-4 h-4 text-[#ebb95e]" />
                    </Link>
                </div>

            </main>

            <Footer />
        </div>
    );
}
