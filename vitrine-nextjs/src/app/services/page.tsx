'use client';

import { motion } from 'framer-motion';
import { ShieldCheck, Award, Landmark, CheckCircle2, Cpu, ArrowRight } from 'lucide-react';
import Link from 'next/link';

export default function ServicesPage() {
    const services = [
        {
            title: "Mise en relation & Matching GPS",
            icon: ShieldCheck,
            badge: "Technologie",
            desc: "Notre système calcule en temps réel la distance entre les artisans et le chantier via ST_Distance_Sphere. Seuls les artisans qualifiés situés à moins de 2 km sont mis en relation. Pour protéger leur vie privée, la position de l'artisan est floutée de 50 m avant d'être partagée.",
            features: [
                "Matching ultra-local ≤ 2 km",
                "Floutage GPS de sécurité à 50 mètres",
                "Recommandation basée sur le Score ProsArtisan",
                "Revue KYC obligatoire (CNI + selfie liveness) avant mise en relation"
            ],
            color: "border-amber-200 bg-amber-500/5",
            iconBg: "bg-amber-100 text-amber-800"
        },
        {
            title: "Renforcement de capacités & Formations",
            icon: Award,
            badge: "Compétences",
            desc: "Nous accompagnons le secteur informel vers le formel grâce à nos programmes de formation continue. De la sécurité électrique aux normes de maçonnerie, nous permettons aux artisans de labelliser leur savoir-faire et d'obtenir des badges exclusifs.",
            features: [
                "Formations techniques certifiées (sécurité, normes)",
                "Accompagnement à la création d'entreprise et comptabilité",
                "Ateliers pratiques sur l'utilisation des devis digitaux",
                "Accès au statut d'Artisan Prioritaire Labellisé"
            ],
            color: "border-emerald-200 bg-emerald-500/5",
            iconBg: "bg-emerald-100 text-emerald-800"
        },
        {
            title: "Séquestre Intelligent & Micro-crédit",
            icon: Landmark,
            badge: "Finance",
            desc: "Sécurisation totale des budgets de chantiers. L'acompte client est automatiquement fragmenté : le portefeuille matériaux (ex: 65%) est bloqué au profit exclusif du fournisseur J-Code, tandis que le portefeuille main d'œuvre (ex: 35%) est libéré jalon par jalon via OTP client.",
            features: [
                "Ratio de fragmentation immuable fixé à la signature",
                "Génération de J-Codes matériaux sécurisés par scan GPS",
                "Libération de fonds instantanée par code OTP SMS client",
                "Micro-crédit d'urgence débloqué en moins de 2 heures pour le matériel"
            ],
            color: "border-blue-200 bg-blue-500/5",
            iconBg: "bg-blue-100 text-blue-800"
        },
        {
            title: "Développement de Systèmes d'Information",
            icon: Cpu,
            badge: "Ingénierie",
            desc: "ProsArtisan propose aux partenaires institutionnels (microfinances, municipalités, grands comptes du BTP) le développement et l'intégration de solutions de suivi, de rapports de solvabilité automatisés et d'audit comptable.",
            features: [
                "Export de rapports de solvabilité certifiés PDF pour banques",
                "Intégration d'API logistiques croisées (quincailleries, livreurs)",
                "Suivi 360° temps réel des chantiers et approvisionnements",
                "Support d'accès hors-ligne et menus interactifs USSD"
            ],
            color: "border-slate-200 bg-slate-500/5",
            iconBg: "bg-slate-100 text-slate-800"
        }
    ];

    return (
        <div className="bg-[#fbf9f6] py-16">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                {/* Header */}
                <div className="text-center max-w-3xl mx-auto space-y-4 mb-20">
                    <span className="text-[11px] font-extrabold uppercase tracking-[0.24em] text-[#8a5d16]">nos services</span>
                    <h1 className="text-4xl sm:text-5xl font-extrabold text-[#241b16] tracking-tight leading-tight">
                        Des solutions conçues pour sécuriser l&apos;artisanat
                    </h1>
                    <p className="text-sm text-[#746251] leading-relaxed">
                        ProsArtisan propose un ensemble d&apos;outils techniques, financiers et académiques pour éliminer les risques de chantiers et stimuler le développement professionnel de nos artisans affiliés.
                    </p>
                </div>

                {/* Services details list */}
                <div className="space-y-16">
                    {services.map((service, index) => (
                        <motion.div
                            key={index}
                            initial={{ opacity: 0, y: 30 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            viewport={{ once: true }}
                            transition={{ duration: 0.6, delay: index * 0.1 }}
                            className={`flex flex-col lg:flex-row gap-12 p-8 sm:p-12 rounded-[40px] border ${service.color} items-start lg:items-center`}
                        >
                            {/* Icon & Badge */}
                            <div className="w-full lg:w-1/3 space-y-4">
                                <span className={`inline-flex px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider ${service.iconBg}`}>
                                    {service.badge}
                                </span>
                                <h2 className="text-2xl sm:text-3xl font-extrabold text-[#241b16] leading-tight">
                                    {service.title}
                                </h2>
                                <div className={`inline-flex p-5 rounded-3xl bg-white border border-[#e6d3b2]/40 text-[#8a5d16] shadow-sm`}>
                                    <service.icon className="h-8 w-8" />
                                </div>
                            </div>

                            {/* Detailed features */}
                            <div className="w-full lg:w-2/3 space-y-6">
                                <p className="text-sm text-[#746251] leading-relaxed font-medium">
                                    {service.desc}
                                </p>
                                
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    {service.features.map((feature, i) => (
                                        <div key={i} className="flex items-start gap-2.5">
                                            <CheckCircle2 className="h-5 w-5 text-emerald-700 shrink-0 mt-0.5" />
                                            <span className="text-xs text-[#241b16] font-semibold leading-relaxed">
                                                {feature}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </motion.div>
                    ))}
                </div>

                {/* Call to action */}
                <div className="mt-24 text-center rounded-[36px] bg-[#241b16] text-[#efe6da] p-8 sm:p-12 relative overflow-hidden">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(235,185,94,0.05),transparent)] pointer-events-none" />
                    <div className="relative max-w-2xl mx-auto space-y-6">
                        <h2 className="text-2xl sm:text-3xl font-extrabold text-white">
                            Vous cherchez un expert pour vos travaux ?
                        </h2>
                        <p className="text-xs text-[#efe6da]/70 leading-relaxed">
                            Bénéficiez du diagnostic IA ProsArtisan pour catégoriser votre demande, obtenir une estimation de prix fiable et être mis en relation avec des pros près de chez vous.
                        </p>
                        <div className="pt-2 flex justify-center">
                            <Link
                                href="/contact"
                                className="bg-[#ebb95e] hover:bg-[#8a5d16] hover:text-white text-[#241b16] text-sm font-extrabold px-8 py-3.5 rounded-full flex items-center gap-2 transition shadow-lg"
                            >
                                <span>Lancer ma demande</span>
                                <ArrowRight className="h-4 w-4" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
