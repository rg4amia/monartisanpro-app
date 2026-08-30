'use client';

import React from 'react';
import Link from 'next/link';
import Navbar from '@/components/Navbar';
import Footer from '@/components/Footer';
import { ShieldCheck, Lock, FileText, Scale, CheckCircle2, ChevronRight, AlertTriangle } from 'lucide-react';

export default function CguPage() {
    return (
        <div className="min-h-screen bg-[#faf8f5] text-[#2c221e] flex flex-col font-sans">
            <Navbar />

            {/* Hero Banner */}
            <div className="bg-gradient-to-r from-[#201815] via-[#2c221e] to-[#201815] text-white pt-36 pb-20 px-4 sm:px-6 lg:px-8 border-b border-[#ebb95e]/20">
                <div className="max-w-4xl mx-auto text-center space-y-4">
                    <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-[#ebb95e]/15 border border-[#ebb95e]/30 text-[#ebb95e] text-xs font-bold uppercase tracking-wider">
                        <Scale className="w-3.5 h-3.5" />
                        Cadre Juridique & Réglementaire
                    </div>
                    <h1 className="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight">
                        Conditions Générales d'Utilisation
                    </h1>
                    <p className="text-[#efe6da]/80 text-sm sm:text-base max-w-2xl mx-auto">
                        ProsArtisan Côte d'Ivoire &bull; Plateforme technologique d’intermédiation et de sécurisation financière des prestations artisanales.
                    </p>
                    <p className="text-xs text-[#ebb95e]/90 font-medium">
                        Dernière mise à jour : 30 Août 2026
                    </p>
                </div>
            </div>

            {/* Quick Navigation / Summary Cards */}
            <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8 w-full">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="bg-white p-4 rounded-2xl shadow-lg border border-[#e6d3b2]/40 flex items-center gap-3.5">
                        <div className="w-10 h-10 rounded-xl bg-[#ebb95e]/15 flex items-center justify-center text-[#9b6818] shrink-0">
                            <Lock className="w-5 h-5" />
                        </div>
                        <div>
                            <p className="text-xs font-bold text-[#8a7766] uppercase">Sécurité</p>
                            <p className="text-sm font-bold text-[#201712]">Paiement Séquestre 100%</p>
                        </div>
                    </div>
                    <div className="bg-white p-4 rounded-2xl shadow-lg border border-[#e6d3b2]/40 flex items-center gap-3.5">
                        <div className="w-10 h-10 rounded-xl bg-emerald-500/15 flex items-center justify-center text-emerald-700 shrink-0">
                            <ShieldCheck className="w-5 h-5" />
                        </div>
                        <div>
                            <p className="text-xs font-bold text-[#8a7766] uppercase">Vérification</p>
                            <p className="text-sm font-bold text-[#201712]">Artisans Agréés & KYC</p>
                        </div>
                    </div>
                    <div className="bg-white p-4 rounded-2xl shadow-lg border border-[#e6d3b2]/40 flex items-center gap-3.5">
                        <div className="w-10 h-10 rounded-xl bg-amber-500/15 flex items-center justify-center text-amber-700 shrink-0">
                            <Scale className="w-5 h-5" />
                        </div>
                        <div>
                            <p className="text-xs font-bold text-[#8a7766] uppercase">Garantie</p>
                            <p className="text-sm font-bold text-[#201712]">Médiation & Arbitrage</p>
                        </div>
                    </div>
                </div>
            </div>

            {/* Main Content */}
            <main className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16 w-full flex-grow space-y-10">
                
                {/* Article 1 */}
                <section className="bg-white rounded-3xl p-6 sm:p-10 border border-[#e6d3b2]/40 shadow-sm space-y-4">
                    <div className="flex items-center gap-3 pb-3 border-b border-[#f0e4d0]">
                        <span className="w-8 h-8 rounded-full bg-[#ebb95e]/20 text-[#9b6818] font-black text-sm flex items-center justify-center">1</span>
                        <h2 className="text-xl sm:text-2xl font-bold text-[#201712]">
                            Objet et Définition des Rôles
                        </h2>
                    </div>
                    <div className="space-y-3 text-sm sm:text-base text-[#5c4a3e] leading-relaxed">
                        <p>
                            <strong className="text-[#201712]">Nature du Service :</strong> ProsArtisan est exclusivement une plateforme technologique d’intermédiation et de sécurisation financière en Côte d'Ivoire.
                        </p>
                        <p>
                            <strong className="text-[#201712]">Indépendance :</strong> Aucun lien de subordination n'existe entre ProsArtisan et les artisans inscrits. ProsArtisan n’est ni employeur, ni maître d’œuvre, ni sous-traitant.
                        </p>
                        <p>
                            <strong className="text-[#201712]">Éligibilité :</strong> L’utilisation des services est strictement réservée aux personnes majeures capables de contracter selon le droit ivoirien.
                        </p>
                    </div>
                </section>

                {/* Article 2 */}
                <section className="bg-white rounded-3xl p-6 sm:p-10 border border-[#e6d3b2]/40 shadow-sm space-y-4">
                    <div className="flex items-center gap-3 pb-3 border-b border-[#f0e4d0]">
                        <span className="w-8 h-8 rounded-full bg-[#ebb95e]/20 text-[#9b6818] font-black text-sm flex items-center justify-center">2</span>
                        <h2 className="text-xl sm:text-2xl font-bold text-[#201712]">
                            Enrôlement et Identité (KYC)
                        </h2>
                    </div>
                    <div className="space-y-3 text-sm sm:text-base text-[#5c4a3e] leading-relaxed">
                        <p>
                            <strong className="text-[#201712]">Vérification Stricte :</strong> Tout compte Artisan ou Prestataire nécessite obligatoirement la soumission d’une pièce d'identité valide (CNI, Passeport, Attestation d'Identité) et d’un numéro de téléphone actif (Mobile Money vérifié par code OTP).
                        </p>
                        <p>
                            <strong className="text-[#201712]">Exactitude des Données :</strong> L’utilisateur s’engage à fournir des informations réelles et vérifiables. L'usurpation d'identité ou la falsification de qualifications entraîne une suspension immédiate et irrévocable du compte, avec signalement potentiel auprès des autorités compétentes.
                        </p>
                    </div>
                </section>

                {/* Article 3 */}
                <section className="bg-white rounded-3xl p-6 sm:p-10 border border-[#e6d3b2]/40 shadow-sm space-y-4">
                    <div className="flex items-center gap-3 pb-3 border-b border-[#f0e4d0]">
                        <span className="w-8 h-8 rounded-full bg-[#ebb95e]/20 text-[#9b6818] font-black text-sm flex items-center justify-center">3</span>
                        <h2 className="text-xl sm:text-2xl font-bold text-[#201712]">
                            Fonctionnement du Paiement Séquestre
                        </h2>
                    </div>
                    <div className="space-y-4 text-sm sm:text-base text-[#5c4a3e] leading-relaxed">
                        <p>
                            <strong className="text-[#201712]">Sécurisation des Fonds :</strong> Pour valider une prestation, le Client s'acquitte du montant total via la plateforme (Wave CI, Orange Money CI ou Carte Bancaire). Ces fonds sont placés sur un compte de cantonnement (séquestre) géré par ProsArtisan.
                        </p>
                        <div className="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 flex gap-3 items-start">
                            <AlertTriangle className="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
                            <div className="text-xs sm:text-sm">
                                <strong className="font-bold">Interdiction formelle du Contournement :</strong>
                                <p className="mt-1">
                                    Toute transaction financière de main à main ou transfert direct (pour transport, achat de matériel imprévu, ou acompte informel) en dehors du système ProsArtisan est strictement interdite. En cas de violation, ProsArtisan décline toute responsabilité et se réserve le droit de bannir définitivement les utilisateurs impliqués.
                                </p>
                            </div>
                        </div>
                        <p>
                            <strong className="text-[#201712]">Libération des Fonds :</strong> Les fonds sont transférés à l’Artisan (déduction faite de la commission de service) uniquement après confirmation de l’achèvement des travaux ou validation des jalons par code OTP sécurisé par le Client via l'application.
                        </p>
                    </div>
                </section>

                {/* Article 4 */}
                <section className="bg-white rounded-3xl p-6 sm:p-10 border border-[#e6d3b2]/40 shadow-sm space-y-4">
                    <div className="flex items-center gap-3 pb-3 border-b border-[#f0e4d0]">
                        <span className="w-8 h-8 rounded-full bg-[#ebb95e]/20 text-[#9b6818] font-black text-sm flex items-center justify-center">4</span>
                        <h2 className="text-xl sm:text-2xl font-bold text-[#201712]">
                            Engagements et Exécution des Prestations
                        </h2>
                    </div>
                    <div className="space-y-3 text-sm sm:text-base text-[#5c4a3e] leading-relaxed">
                        <p>
                            <strong className="text-[#201712]">Obligations de l'Artisan :</strong> Ponctualité, conformité au devis validé, respect du domicile et des biens du client, et nettoyage complet du site après intervention. Tout retard abusif non justifié ou comportement inapproprié affectera immédiatement son score de fiabilité.
                        </p>
                        <p>
                            <strong className="text-[#201712]">Obligations du Client :</strong> Fournir des spécifications claires, garantir l'accès au site d'intervention dans les créneaux convenus, et valider la fin des travaux de bonne foi dès leur achèvement effectif.
                        </p>
                    </div>
                </section>

                {/* Article 5 */}
                <section className="bg-white rounded-3xl p-6 sm:p-10 border border-[#e6d3b2]/40 shadow-sm space-y-4">
                    <div className="flex items-center gap-3 pb-3 border-b border-[#f0e4d0]">
                        <span className="w-8 h-8 rounded-full bg-[#ebb95e]/20 text-[#9b6818] font-black text-sm flex items-center justify-center">5</span>
                        <h2 className="text-xl sm:text-2xl font-bold text-[#201712]">
                            Gestion des Litiges et Arbitrage
                        </h2>
                    </div>
                    <div className="space-y-3 text-sm sm:text-base text-[#5c4a3e] leading-relaxed">
                        <p>
                            <strong className="text-[#201712]">Gel des Fonds :</strong> En cas de désaccord sur la qualité ou l'achèvement de la prestation, le Client ou l'Artisan doit déclencher une procédure de litige dans l'application sous 24 heures. Les fonds restent alors bloqués sur le compte séquestre.
                        </p>
                        <p>
                            <strong className="text-[#201712]">Preuves Numériques :</strong> Les deux parties ont l'obligation de fournir des preuves via l'application (photos avant/après géolocalisées, fiches de suivi, historique des messages internes).
                        </p>
                        <p>
                            <strong className="text-[#201712]">Arbitrage ProsArtisan :</strong> Le support technique de ProsArtisan intervient comme médiateur de premier niveau pour trancher le litige sur la base objective des éléments numériques fournis.
                        </p>
                    </div>
                </section>

                {/* Article 6 */}
                <section className="bg-white rounded-3xl p-6 sm:p-10 border border-[#e6d3b2]/40 shadow-sm space-y-4">
                    <div className="flex items-center gap-3 pb-3 border-b border-[#f0e4d0]">
                        <span className="w-8 h-8 rounded-full bg-[#ebb95e]/20 text-[#9b6818] font-black text-sm flex items-center justify-center">6</span>
                        <h2 className="text-xl sm:text-2xl font-bold text-[#201712]">
                            Propriété Intellectuelle et Données
                        </h2>
                    </div>
                    <div className="space-y-3 text-sm sm:text-base text-[#5c4a3e] leading-relaxed">
                        <p>
                            <strong className="text-[#201712]">Cession de Droits :</strong> Les Artisans autorisent ProsArtisan à utiliser les photographies de leurs réalisations téléchargées sur l'application à des fins de promotion, d'audit de qualité et de communication institutionnelle.
                        </p>
                        <p>
                            <strong className="text-[#201712]">Confidentialité :</strong> Les utilisateurs s'interdisent formellement de réutiliser les données personnelles (numéro de téléphone, adresse physique) obtenues via la plateforme à d'autres fins que l'exécution de la prestation convenue.
                        </p>
                    </div>
                </section>

                {/* Article 7 */}
                <section className="bg-white rounded-3xl p-6 sm:p-10 border border-[#e6d3b2]/40 shadow-sm space-y-4">
                    <div className="flex items-center gap-3 pb-3 border-b border-[#f0e4d0]">
                        <span className="w-8 h-8 rounded-full bg-[#ebb95e]/20 text-[#9b6818] font-black text-sm flex items-center justify-center">7</span>
                        <h2 className="text-xl sm:text-2xl font-bold text-[#201712]">
                            Limitation de Responsabilité
                        </h2>
                    </div>
                    <div className="space-y-3 text-sm sm:text-base text-[#5c4a3e] leading-relaxed">
                        <p>
                            ProsArtisan garantit le fonctionnement optimal de l'infrastructure technologique de mise en relation et la traçabilité intégrale des transactions financières sous séquestre.
                        </p>
                        <p>
                            ProsArtisan ne peut être tenu responsable des malfaçons, des dommages matériels ou corporels survenant lors de l'exécution physique de la prestation sur le chantier, ni des interruptions temporaires de service dues à des pannes des réseaux d'opérateurs télécoms tiers.
                        </p>
                    </div>
                </section>

                {/* Bottom link to Privacy Policy */}
                <div className="p-6 rounded-3xl bg-[#f0e4d0]/60 border border-[#e6d3b2] flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div>
                        <h3 className="font-bold text-[#201712] text-base">Consultez également notre Politique de Confidentialité</h3>
                        <p className="text-xs text-[#6f5d50] mt-1">Découvrez comment nous protégeons vos données personnelles conformément à la Loi n° 2013-450 / ARTCI.</p>
                    </div>
                    <Link
                        href="/politique-confidentialite"
                        className="px-5 py-2.5 rounded-xl bg-[#201712] hover:bg-[#3d312a] text-white font-bold text-xs shrink-0 flex items-center gap-2 transition"
                    >
                        Politique de Confidentialité
                        <ChevronRight className="w-4 h-4 text-[#ebb95e]" />
                    </Link>
                </div>

            </main>

            <Footer />
        </div>
    );
}
