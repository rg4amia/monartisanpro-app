import { Head, Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import type { CSSProperties } from 'react';

const landingTheme: CSSProperties = {
    '--landing-bg': '#f6efe5',
    '--landing-panel': 'rgba(255, 251, 245, 0.8)',
    '--landing-panel-strong': '#fffaf2',
    '--landing-border': 'rgba(182, 144, 92, 0.24)',
    '--landing-ink': '#201712',
    '--landing-copy': '#6f5d50',
    '--landing-copy-soft': '#8a7766',
    '--landing-gold': '#d8a84e',
    '--landing-gold-deep': '#ad6f1d',
    '--landing-clay': '#cc6a45',
    '--landing-green': '#1f7a55',
    '--landing-night': '#1f1a17',
    '--landing-cream': '#fff4e3',
    '--landing-surface': '#f0e4d0',
} as CSSProperties;

interface Props {
    defaultTab?: 'cgu' | 'privacy';
}

export default function Cgu({ defaultTab = 'cgu' }: Props) {
    const [activeTab, setActiveTab] = useState<'cgu' | 'privacy'>(() => {
        if (typeof window !== 'undefined') {
            if (window.location.pathname.includes('politique') || window.location.pathname.includes('confidentialite')) {
                return 'privacy';
            } else if (window.location.pathname.includes('cgu')) {
                return 'cgu';
            }
        }
        return defaultTab;
    });

    return (
        <div style={landingTheme} className="min-h-screen bg-[var(--landing-bg)] text-[var(--landing-ink)] font-sans antialiased selection:bg-[var(--landing-gold)] selection:text-white pb-24">
            <Head title={activeTab === 'cgu' ? "Conditions Générales d'Utilisation — ProsArtisan" : "Politique de Confidentialité — ProsArtisan"} />

            {/* Header */}
            <header className="sticky top-0 z-50 border-b border-[var(--landing-border)] bg-[var(--landing-panel)] backdrop-blur-xl">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-20 items-center justify-between">
                        <Link href="/" className="flex items-center gap-3">
                            <img
                                src="/img/prosartisan-logo.png"
                                alt="ProsArtisan"
                                className="h-10 w-auto object-contain"
                            />
                        </Link>
                        <nav className="flex items-center gap-6">
                            <Link href="/" className="text-sm font-semibold text-[var(--landing-copy)] hover:text-[var(--landing-gold-deep)] transition-colors">
                                Retour à l'accueil
                            </Link>
                        </nav>
                    </div>
                </div>
            </header>

            {/* Main Content */}
            <main className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 pt-12">
                <div className="text-center mb-10">
                    <h1 className="text-4xl md:text-5xl font-black tracking-tight text-[var(--landing-night)] mb-4">
                        {activeTab === 'cgu' ? "Conditions Générales d'Utilisation" : "Politique de Confidentialité"}
                    </h1>
                    <p className="text-sm text-[var(--landing-copy-soft)]">
                        ProsArtisan Côte d'Ivoire &bull; Dernière mise à jour : 30 Août 2026
                    </p>

                    {/* Tab Navigation */}
                    <div className="mt-8 inline-flex p-1.5 rounded-2xl bg-[var(--landing-surface)] border border-[var(--landing-border)] shadow-inner gap-1">
                        <button
                            type="button"
                            onClick={() => setActiveTab('cgu')}
                            className={`px-6 py-2.5 rounded-xl font-bold text-sm transition-all ${
                                activeTab === 'cgu'
                                    ? 'bg-white text-[var(--landing-night)] shadow-sm'
                                    : 'text-[var(--landing-copy)] hover:text-[var(--landing-night)]'
                            }`}
                        >
                            Conditions d'Utilisation (CGU)
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('privacy')}
                            className={`px-6 py-2.5 rounded-xl font-bold text-sm transition-all ${
                                activeTab === 'privacy'
                                    ? 'bg-white text-[var(--landing-night)] shadow-sm'
                                    : 'text-[var(--landing-copy)] hover:text-[var(--landing-night)]'
                            }`}
                        >
                            Politique de Confidentialité
                        </button>
                    </div>
                </div>

                <div className="bg-[var(--landing-panel-strong)] border border-[var(--landing-border)] rounded-3xl p-8 md:p-12 shadow-sm space-y-12">
                    
                    {activeTab === 'cgu' ? (
                        <>
                            {/* Section 1 */}
                            <section>
                                <h2 className="text-2xl font-bold text-[var(--landing-night)] mb-4 flex items-center gap-3">
                                    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--landing-cream)] text-[var(--landing-gold-deep)] text-sm font-black">1</span>
                                    Objet et Définition des Rôles
                                </h2>
                                <div className="space-y-4 text-[var(--landing-copy)] leading-relaxed">
                                    <p>
                                        <strong>Nature du Service :</strong> ProsArtisan est exclusivement une plateforme technologique d’intermédiation et de sécurisation financière en Côte d'Ivoire.
                                    </p>
                                    <p>
                                        <strong>Indépendance :</strong> Aucun lien de subordination n'existe entre ProsArtisan et les artisans inscrits. ProsArtisan n’est ni employeur, ni maître d’œuvre, ni sous-traitant.
                                    </p>
                                    <p>
                                        <strong>Éligibilité :</strong> L’utilisation est réservée aux personnes majeures capables de contracter selon le droit ivoirien.
                                    </p>
                                </div>
                            </section>

                            {/* Section 2 */}
                            <section>
                                <h2 className="text-2xl font-bold text-[var(--landing-night)] mb-4 flex items-center gap-3">
                                    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--landing-cream)] text-[var(--landing-gold-deep)] text-sm font-black">2</span>
                                    Enrôlement et Identité (KYC)
                                </h2>
                                <div className="space-y-4 text-[var(--landing-copy)] leading-relaxed">
                                    <p>
                                        <strong>Vérification Stricte :</strong> Tout compte Artisan ou Partenaire nécessite la soumission d’une pièce d'identité valide (CNI, Passeport, Attestation d'Identité) et d’un numéro de téléphone actif (Mobile Money).
                                    </p>
                                    <p>
                                        <strong>Exactitude des Données :</strong> L’utilisateur s’engage à fournir des informations réelles. L'usurpation d'identité ou la falsification de compétences entraîne une suspension immédiate et irrévocable, avec signalement potentiel aux autorités compétentes.
                                    </p>
                                </div>
                            </section>

                            {/* Section 3 */}
                            <section>
                                <h2 className="text-2xl font-bold text-[var(--landing-night)] mb-4 flex items-center gap-3">
                                    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--landing-cream)] text-[var(--landing-gold-deep)] text-sm font-black">3</span>
                                    Fonctionnement du Paiement Séquestre
                                </h2>
                                <div className="space-y-4 text-[var(--landing-copy)] leading-relaxed">
                                    <p>
                                        <strong>Sécurisation des Fonds :</strong> Pour valider une prestation, le Client s'acquitte du montant total via la plateforme (Mobile Money Wave / Orange Money ou carte bancaire). Ces fonds sont placés sur un compte de cantonnement (séquestre) géré par ProsArtisan.
                                    </p>
                                    <div className="bg-[var(--landing-bg)] p-5 rounded-2xl border border-[var(--landing-border)]">
                                        <p className="font-bold text-[var(--landing-clay)] mb-2">Interdiction formelle du Contournement :</p>
                                        <p className="text-sm">
                                            Toute transaction financière de main à main ou transfert direct (pour transport, achat de matériel imprévu, ou acompte) en dehors du système ProsArtisan est strictement interdite. En cas de violation, ProsArtisan décline toute responsabilité et se réserve le droit de bannir définitivement les utilisateurs impliqués.
                                        </p>
                                    </div>
                                    <p>
                                        <strong>Libération des Fonds :</strong> Les fonds sont transférés à l’Artisan (déduction faite de la commission de la plateforme) uniquement après confirmation de l’achèvement des travaux par le Client via l'application (ou validation des jalons par code OTP unique).
                                    </p>
                                </div>
                            </section>

                            {/* Section 4 */}
                            <section>
                                <h2 className="text-2xl font-bold text-[var(--landing-night)] mb-4 flex items-center gap-3">
                                    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--landing-cream)] text-[var(--landing-gold-deep)] text-sm font-black">4</span>
                                    Engagements et Exécution des Prestations
                                </h2>
                                <div className="space-y-4 text-[var(--landing-copy)] leading-relaxed">
                                    <p>
                                        <strong>Obligations de l'Artisan :</strong> Ponctualité, conformité au devis validé, respect du domicile du client, et nettoyage du site après intervention. Tout retard abusif non justifié ou comportement inapproprié affectera son score de fiabilité.
                                    </p>
                                    <p>
                                        <strong>Obligations du Client :</strong> Fournir des spécifications claires, garantir l'accès au site d'intervention, et valider la fin des travaux de bonne foi dès leur achèvement effectif.
                                    </p>
                                </div>
                            </section>

                            {/* Section 5 */}
                            <section>
                                <h2 className="text-2xl font-bold text-[var(--landing-night)] mb-4 flex items-center gap-3">
                                    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--landing-cream)] text-[var(--landing-gold-deep)] text-sm font-black">5</span>
                                    Gestion des Litiges et Arbitrage
                                </h2>
                                <div className="space-y-4 text-[var(--landing-copy)] leading-relaxed">
                                    <p>
                                        <strong>Gel des Fonds :</strong> En cas de désaccord sur la qualité ou l'achèvement de la prestation, le Client ou l'Artisan doit déclencher une procédure de litige dans l'application sous 24 heures. Les fonds restent séquestrés.
                                    </p>
                                    <p>
                                        <strong>Preuves Numériques :</strong> Les deux parties ont l'obligation de fournir des preuves via l'application (photos avant/après géolocalisées, historique des messages et fiches d'intervention).
                                    </p>
                                    <p>
                                        <strong>Arbitrage ProsArtisan :</strong> Le support technique de ProsArtisan intervient comme médiateur de premier niveau pour trancher le litige sur la base des éléments numériques fournis.
                                    </p>
                                </div>
                            </section>

                            {/* Section 6 */}
                            <section>
                                <h2 className="text-2xl font-bold text-[var(--landing-night)] mb-4 flex items-center gap-3">
                                    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--landing-cream)] text-[var(--landing-gold-deep)] text-sm font-black">6</span>
                                    Propriété Intellectuelle et Données
                                </h2>
                                <div className="space-y-4 text-[var(--landing-copy)] leading-relaxed">
                                    <p>
                                        <strong>Cession de Droits :</strong> Les Artisans autorisent ProsArtisan à utiliser les photographies de leurs réalisations téléchargées sur l'application à des fins de promotion, d'audit et de marketing.
                                    </p>
                                    <p>
                                        <strong>Confidentialité :</strong> Les utilisateurs s'interdisent formellement de réutiliser les données personnelles (numéro de téléphone, adresse physique) obtenues via la plateforme à d'autres fins que l'exécution de la prestation convenue.
                                    </p>
                                </div>
                            </section>

                            {/* Section 7 */}
                            <section>
                                <h2 className="text-2xl font-bold text-[var(--landing-night)] mb-4 flex items-center gap-3">
                                    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--landing-cream)] text-[var(--landing-gold-deep)] text-sm font-black">7</span>
                                    Limitation de Responsabilité
                                </h2>
                                <div className="space-y-4 text-[var(--landing-copy)] leading-relaxed">
                                    <p>
                                        ProsArtisan garantit le fonctionnement de l'infrastructure logicielle de mise en relation et la traçabilité intégrale des paiements sous séquestre.
                                    </p>
                                    <p>
                                        ProsArtisan ne peut être tenu responsable des malfaçons, des dommages matériels ou corporels survenant lors de l'exécution physique de la prestation sur le terrain, ni des interruptions temporaires de service dues à des pannes des réseaux d'opérateurs tiers.
                                    </p>
                                </div>
                            </section>
                        </>
                    ) : (
                        <>
                            {/* Privacy Section 1 */}
                            <section>
                                <h2 className="text-2xl font-bold text-[var(--landing-night)] mb-4 flex items-center gap-3">
                                    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--landing-cream)] text-[var(--landing-green)] text-sm font-black">1</span>
                                    Préambule et Responsabilité du Traitement
                                </h2>
                                <div className="space-y-4 text-[var(--landing-copy)] leading-relaxed">
                                    <p>
                                        La présente politique définit la manière dont l'application <strong>ProsArtisan</strong> collecte, traite, stocke et protège les données à caractère personnel de ses utilisateurs (Clients, Artisans, Fournisseurs et Livreurs). ProsArtisan agit en tant que responsable de traitement exclusif de cet environnement.
                                    </p>
                                    <div className="bg-[var(--landing-bg)] p-5 rounded-2xl border border-[var(--landing-border)]">
                                        <p className="font-bold text-[var(--landing-night)] mb-1">Étanchéité institutionnelle absolue :</p>
                                        <p className="text-sm">
                                            L'infrastructure de ProsArtisan opère dans une étanchéité absolue vis-à-vis de toute application institutionnelle tierce : aucune donnée utilisateur n'est synchronisée, partagée ou accessible par des démembrements administratifs ou des agences régionales.
                                        </p>
                                    </div>
                                </div>
                            </section>

                            {/* Privacy Section 2 */}
                            <section>
                                <h2 className="text-2xl font-bold text-[var(--landing-night)] mb-4 flex items-center gap-3">
                                    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--landing-cream)] text-[var(--landing-green)] text-sm font-black">2</span>
                                    Nature des Données Collectées
                                </h2>
                                <div className="space-y-4 text-[var(--landing-copy)] leading-relaxed">
                                    <p>
                                        L'architecture de collecte obéit au principe strict de <strong>minimisation</strong> (seules les données strictement nécessaires au service sont requises) :
                                    </p>
                                    <ul className="space-y-3">
                                        <li className="flex gap-2">
                                            <span className="font-bold text-[var(--landing-night)]">&bull; Données d’identification :</span>
                                            <span>Noms, prénoms, numéros de téléphone (vérifiés par OTP SMS/WhatsApp), adresses e-mail. Pour les Artisans et Livreurs : copie numérisée de la pièce d'identité (CNI, Passeport, Attestation) et selfie liveness.</span>
                                        </li>
                                        <li className="flex gap-2">
                                            <span className="font-bold text-[var(--landing-night)]">&bull; Données de localisation :</span>
                                            <span>Coordonnées GPS collectées uniquement lors de l'utilisation active de l'application, afin d'assurer le matching géographique entre la demande du Client et la position de l'Artisan. Floutage de 50 m appliqué avant tout partage.</span>
                                        </li>
                                        <li className="flex gap-2">
                                            <span className="font-bold text-[var(--landing-night)]">&bull; Données transactionnelles et financières :</span>
                                            <span>Historique des paiements, identifiants de transactions Mobile Money (Wave / Orange Money) ou bancaires. ProsArtisan ne stocke jamais les codes PIN ou mots de passe des portefeuilles électroniques.</span>
                                        </li>
                                        <li className="flex gap-2">
                                            <span className="font-bold text-[var(--landing-night)]">&bull; Données d'activité et IA :</span>
                                            <span>Historique des recherches, photographies des réalisations, évaluations et requêtes textuelles. Les requêtes de recherche sont transformées en vecteurs pour alimenter notre moteur de recommandation, après anonymisation des identifiants directs.</span>
                                        </li>
                                    </ul>
                                </div>
                            </section>

                            {/* Privacy Section 3 */}
                            <section>
                                <h2 className="text-2xl font-bold text-[var(--landing-night)] mb-4 flex items-center gap-3">
                                    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--landing-cream)] text-[var(--landing-green)] text-sm font-black">3</span>
                                    Finalités du Traitement
                                </h2>
                                <div className="space-y-4 text-[var(--landing-copy)] leading-relaxed">
                                    <p>
                                        Vos données ne sont <strong>jamais commercialisées</strong>. Elles sont exploitées exclusivement pour :
                                    </p>
                                    <ul className="list-disc pl-6 space-y-2 marker:text-[var(--landing-green)]">
                                        <li>Authentifier les profils et lutter contre la fraude (usurpation d'identité, blanchiment).</li>
                                        <li>Générer des correspondances précises (matching) via notre moteur de recherche sémantique.</li>
                                        <li>Garantir la sécurité du mécanisme de paiement séquestre.</li>
                                        <li>Fournir des preuves numériques (photos géolocalisées, logs de suivi, historique de chat interne) nécessaires à l'arbitrage en cas de litige sur la qualité ou l'achèvement des travaux.</li>
                                    </ul>
                                </div>
                            </section>

                            {/* Privacy Section 4 */}
                            <section>
                                <h2 className="text-2xl font-bold text-[var(--landing-night)] mb-4 flex items-center gap-3">
                                    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--landing-cream)] text-[var(--landing-green)] text-sm font-black">4</span>
                                    Partage et Transfert des Données
                                </h2>
                                <div className="space-y-4 text-[var(--landing-copy)] leading-relaxed">
                                    <p>
                                        Dans le cadre exclusif de l'exécution du service, certaines données chiffrées sont transmises à des tiers de confiance :
                                    </p>
                                    <ul className="list-disc pl-6 space-y-2 marker:text-[var(--landing-green)]">
                                        <li><strong>Agrégateurs de paiement (Wave, Orange Money, MTN, Moov) :</strong> Pour l'exécution et la libération des fonds.</li>
                                        <li><strong>Fournisseurs d'infrastructure Cloud :</strong> Pour l'hébergement sécurisé des bases de données et des fichiers médias.</li>
                                    </ul>
                                    <p className="text-sm">
                                        Tous les sous-traitants sont soumis à des clauses de confidentialité strictes. Aucune donnée n'est transférée en dehors de l'espace CEDEAO sans des garanties de sécurité équivalentes aux normes de l'ARTCI (Autorité de Régulation des Télécommunications/TIC de Côte d'Ivoire).
                                    </p>
                                </div>
                            </section>

                            {/* Privacy Section 5 */}
                            <section>
                                <h2 className="text-2xl font-bold text-[var(--landing-night)] mb-4 flex items-center gap-3">
                                    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--landing-cream)] text-[var(--landing-green)] text-sm font-black">5</span>
                                    Sécurité et Conservation
                                </h2>
                                <div className="space-y-4 text-[var(--landing-copy)] leading-relaxed">
                                    <p>
                                        Les flux de données sont sécurisés de bout en bout (protocoles TLS). Les pièces d'identité et les données sensibles sont chiffrées au repos dans nos bases de données.
                                    </p>
                                    <div className="grid gap-3 sm:grid-cols-3 mt-4">
                                        <div className="bg-[var(--landing-bg)] p-4 rounded-xl border border-[var(--landing-border)]">
                                            <p className="text-xs font-bold uppercase text-[var(--landing-copy-soft)]">Données courantes</p>
                                            <p className="text-sm font-semibold text-[var(--landing-night)] mt-1">Conservées tant que le compte est actif</p>
                                        </div>
                                        <div className="bg-[var(--landing-bg)] p-4 rounded-xl border border-[var(--landing-border)]">
                                            <p className="text-xs font-bold uppercase text-[var(--landing-copy-soft)]">Géolocalisation</p>
                                            <p className="text-sm font-semibold text-[var(--landing-night)] mt-1">Purgées/anonymisées sous 60 jours</p>
                                        </div>
                                        <div className="bg-[var(--landing-bg)] p-4 rounded-xl border border-[var(--landing-border)]">
                                            <p className="text-xs font-bold uppercase text-[var(--landing-copy-soft)]">Données comptables</p>
                                            <p className="text-sm font-semibold text-[var(--landing-night)] mt-1">Conservées 10 ans (OHADA)</p>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            {/* Privacy Section 6 */}
                            <section>
                                <h2 className="text-2xl font-bold text-[var(--landing-night)] mb-4 flex items-center gap-3">
                                    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--landing-cream)] text-[var(--landing-green)] text-sm font-black">6</span>
                                    Droits des Utilisateurs & Contact DPO
                                </h2>
                                <div className="space-y-4 text-[var(--landing-copy)] leading-relaxed">
                                    <p>
                                        Conformément à la législation en vigueur (Loi n° 2013-450), tout utilisateur dispose d'un droit d’accès, de rectification, de limitation et de suppression de ses données ("droit à l'oubli").
                                    </p>
                                    <p>
                                        Pour exercer ces droits, l'utilisateur peut formuler sa requête via l'application mobile ou contacter notre Délégué à la Protection des Données (DPO) à l'adresse officielle : <strong className="text-[var(--landing-night)]">dpo@prosartisan.net</strong>.
                                    </p>
                                </div>
                            </section>
                        </>
                    )}

                </div>

                <div className="mt-12 mb-12 text-center text-sm text-[var(--landing-copy-soft)]">
                    &copy; {new Date().getFullYear()} ProsArtisan Côte d'Ivoire. Tous droits réservés. <br />
                    Plateforme agréée et conforme aux directives de l'ARTCI.
                </div>
            </main>
        </div>
    );
}
