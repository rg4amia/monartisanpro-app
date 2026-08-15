import { Head, Link } from '@inertiajs/react';
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

export default function Cgu() {
    return (
        <div style={landingTheme} className="min-h-screen bg-[var(--landing-bg)] text-[var(--landing-ink)] font-sans antialiased selection:bg-[var(--landing-gold)] selection:text-white pb-24">
            <Head title="Conditions Générales d'Utilisation - ProsArtisan" />

            {/* Header */}
            <header className="sticky top-0 z-50 border-b border-[var(--landing-border)] bg-[var(--landing-panel)] backdrop-blur-xl">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-20 items-center justify-between">
                        <div className="flex items-center gap-2">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-[var(--landing-gold)] to-[var(--landing-gold-deep)] shadow-sm">
                                <svg className="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <span className="text-xl font-black tracking-tight text-[var(--landing-night)]">
                                Pros<span className="text-[var(--landing-gold-deep)]">Artisan</span>
                            </span>
                        </div>
                        <nav className="hidden md:flex gap-8">
                            <Link href="/" className="text-sm font-semibold text-[var(--landing-copy)] hover:text-[var(--landing-gold-deep)] transition-colors">
                                Retour à l'accueil
                            </Link>
                        </nav>
                    </div>
                </div>
            </header>

            {/* Main Content */}
            <main className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 pt-16">
                <div className="text-center mb-16">
                    <h1 className="text-4xl md:text-5xl font-black tracking-tight text-[var(--landing-night)] mb-6">
                        Conditions Générales d'Utilisation
                    </h1>
                    <p className="text-lg text-[var(--landing-copy)]">
                        Dernière mise à jour : {new Date().toLocaleDateString('fr-FR')}
                    </p>
                </div>

                <div className="bg-[var(--landing-panel-strong)] border border-[var(--landing-border)] rounded-3xl p-8 md:p-12 shadow-sm space-y-12">
                    
                    {/* Section 1 */}
                    <section>
                        <h2 className="text-2xl font-bold text-[var(--landing-night)] mb-4 flex items-center gap-3">
                            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--landing-cream)] text-[var(--landing-gold-deep)] text-sm font-black">1</span>
                            Préambule et Objet
                        </h2>
                        <div className="space-y-4 text-[var(--landing-copy)] leading-relaxed">
                            <p>
                                Les présentes Conditions Générales d'Utilisation (ci-après « CGU ») encadrent juridiquement l’utilisation de la plateforme <strong>ProsArtisan</strong>, opérée en Côte d'Ivoire. Elles définissent les droits et obligations des utilisateurs (Clients, Artisans, Fournisseurs et Référents) dans le cadre de leur mise en relation et de la réalisation de prestations artisanales.
                            </p>
                            <p>
                                L'utilisation de l'application mobile et du site web implique l'acceptation sans réserve des présentes CGU.
                            </p>
                        </div>
                    </section>

                    {/* Section 2 */}
                    <section>
                        <h2 className="text-2xl font-bold text-[var(--landing-night)] mb-4 flex items-center gap-3">
                            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--landing-cream)] text-[var(--landing-gold-deep)] text-sm font-black">2</span>
                            Accès au Service et Inscription (KYC)
                        </h2>
                        <div className="space-y-4 text-[var(--landing-copy)] leading-relaxed">
                            <p>
                                L’accès aux services de mise en relation de ProsArtisan est conditionné à la validation d'une procédure d'identification <strong>(KYC - Know Your Customer)</strong> stricte pour tous les utilisateurs.
                            </p>
                            <ul className="list-disc pl-6 space-y-2 marker:text-[var(--landing-gold)]">
                                <li>L’inscription s'effectue obligatoirement via un numéro de téléphone ivoirien valide et la saisie d'un code OTP.</li>
                                <li>Chaque profil (Client, Artisan, Fournisseur) doit soumettre une pièce d'identité valide (CNI, Passeport) et un selfie de vérification (Liveness).</li>
                                <li><strong>Aucune transaction financière ni mise en relation</strong> n'est autorisée pour les profils dont le statut KYC est "en attente" ou "rejeté".</li>
                            </ul>
                        </div>
                    </section>

                    {/* Section 3 */}
                    <section>
                        <h2 className="text-2xl font-bold text-[var(--landing-night)] mb-4 flex items-center gap-3">
                            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--landing-cream)] text-[var(--landing-gold-deep)] text-sm font-black">3</span>
                            Séquestre et Modèle Financier
                        </h2>
                        <div className="space-y-4 text-[var(--landing-copy)] leading-relaxed">
                            <p>
                                ProsArtisan agit comme un tiers de confiance financier pour sécuriser les transactions entre Clients, Artisans et Fournisseurs via les opérateurs Wave CI et Orange Money CI.
                            </p>
                            <div className="bg-[var(--landing-bg)] p-6 rounded-2xl border border-[var(--landing-border)]">
                                <ul className="space-y-4">
                                    <li className="flex gap-3">
                                        <svg className="h-6 w-6 shrink-0 text-[var(--landing-green)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span><strong>Fragmentation immuable :</strong> À l'acceptation d'un devis, les fonds sont bloqués sur un compte séquestre et scindés en deux portefeuilles : <em>Matériaux</em> et <em>Main d'Œuvre</em>. Ce ratio devient définitif.</span>
                                    </li>
                                    <li className="flex gap-3">
                                        <svg className="h-6 w-6 shrink-0 text-[var(--landing-clay)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span><strong>J-Codes et Fournisseurs :</strong> Les matériaux sont récupérés en quincaillerie grâce à un jeton sécurisé (J-Code). Le scan exige la géolocalisation stricte du fournisseur (moins de 100m de sa boutique) pour débloquer le virement direct.</span>
                                    </li>
                                    <li className="flex gap-3">
                                        <svg className="h-6 w-6 shrink-0 text-[var(--landing-gold-deep)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span><strong>Validation par Jalons :</strong> L'artisan est payé progressivement pour sa main d'œuvre. La libération des fonds requiert la saisie d'un code <strong>OTP unique</strong> par le Client confirmant la bonne exécution de chaque jalon.</span>
                                    </li>
                                </ul>
                            </div>
                            <p className="text-sm italic">
                                Note : Pour toute mission dépassant 2 000 000 FCFA, la visite physique et la validation d'un Référent ProsArtisan sont obligatoires.
                            </p>
                        </div>
                    </section>

                    {/* Section 4 */}
                    <section>
                        <h2 className="text-2xl font-bold text-[var(--landing-night)] mb-4 flex items-center gap-3">
                            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--landing-cream)] text-[var(--landing-gold-deep)] text-sm font-black">4</span>
                            Protection des Données Personnelles
                        </h2>
                        <div className="space-y-4 text-[var(--landing-copy)] leading-relaxed">
                            <p>
                                Conformément à la <strong>Loi n° 2013-450 du 19 juin 2013 relative à la protection des données à caractère personnel</strong> en Côte d'Ivoire, ProsArtisan s'engage à protéger la vie privée de ses utilisateurs. Les traitements de données font l'objet d'une déclaration auprès de l’Autorité de Régulation des Télécommunications/TIC de Côte d'Ivoire (ARTCI).
                            </p>
                            <h3 className="font-bold text-[var(--landing-night)] mt-6">4.1. Nature des données collectées</h3>
                            <p>
                                Nous collectons : vos numéros de téléphone, nom, prénom, photos (CNI/Selfie pour le KYC), coordonnées GPS (artisans, fournisseurs, chantiers), données de paiement (transactions Mobile Money) et logs de communication. 
                            </p>
                            <h3 className="font-bold text-[var(--landing-night)] mt-6">4.2. Floutage GPS et sécurité</h3>
                            <p>
                                Afin de protéger la sécurité des artisans, la géolocalisation exacte de l'artisan n'est jamais transmise au client en phase de recherche. Un offset aléatoire (décalage) d'environ 50 mètres est appliqué mathématiquement pour préserver l'anonymat géographique précis.
                            </p>
                            <h3 className="font-bold text-[var(--landing-night)] mt-6">4.3. Droits des utilisateurs</h3>
                            <p>
                                Vous disposez d’un droit d’accès, de rectification, de suppression et d’opposition sur vos données personnelles. Vous pouvez exercer ces droits en contactant notre Délégué à la Protection des Données (DPO) à l'adresse <em>privacy@prosartisan.ci</em>. Les données liées aux facturations et aux litiges sont conservées pour une durée légale de 10 ans selon le droit comptable OHADA.
                            </p>
                        </div>
                    </section>

                    {/* Section 5 */}
                    <section>
                        <h2 className="text-2xl font-bold text-[var(--landing-night)] mb-4 flex items-center gap-3">
                            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--landing-cream)] text-[var(--landing-gold-deep)] text-sm font-black">5</span>
                            Responsabilités, Litiges et Score ProsArtisan
                        </h2>
                        <div className="space-y-4 text-[var(--landing-copy)] leading-relaxed">
                            <p>
                                <strong>Score ProsArtisan :</strong> Chaque artisan est évalué à la fin de sa mission pour calculer son Score ProsArtisan (0 à 1000), basé sur sa fiabilité, son intégrité, la qualité et sa réactivité. Ce score conditionne la visibilité de l'artisan et son accès aux micro-crédits d'urgence. Un score trop bas peut entraîner une suspension.
                            </p>
                            <p>
                                <strong>Litiges :</strong> En cas de désaccord sur un chantier ou un paiement, un bouton "Signalement" permet de bloquer le séquestre et de faire intervenir un modérateur. Les décisions d'arbitrage de ProsArtisan (remboursement, gel, paiement) s'imposent aux parties dans un délai raisonnable.
                            </p>
                            <p>
                                <strong>Juridiction compétente :</strong> Les présentes CGU sont soumises au droit ivoirien. À défaut de résolution à l'amiable via le processus de médiation interne, tout différend sera porté devant le <strong>Tribunal de Commerce d'Abidjan</strong>.
                            </p>
                        </div>
                    </section>

                </div>

                <div className="mt-12 mb-12 text-center text-sm text-[var(--landing-copy-soft)]">
                    &copy; {new Date().getFullYear()} ProsArtisan Côte d'Ivoire. Tous droits réservés. <br />
                    Plateforme agréée et conforme aux directives de l'ARTCI.
                </div>
            </main>
        </div>
    );
}
