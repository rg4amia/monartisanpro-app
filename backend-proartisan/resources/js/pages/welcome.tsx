import { Head, Link } from '@inertiajs/react';
import type { CSSProperties, ReactNode } from 'react';

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

const heroSignals = [
    {
        eyebrow: 'Intelligence Artificielle',
        title: 'Diagnostic et estimation assistes par Gemini',
        text: "Analyse automatique des descriptions et photos du client pour determiner la categorie, l'urgence et le budget estimatif.",
        tone: 'night' as const,
    },
    {
        eyebrow: 'Matching',
        title: 'Artisans trouves a moins de 2 km',
        text: 'Le client trouve vite, mais la position exacte reste protegee cote public.',
        tone: 'gold' as const,
    },
    {
        eyebrow: 'Paiement',
        title: 'Sequestre fragmente des le devis',
        text: "Materiaux et main d'oeuvre sont separes, puis verrouilles sur tout le cycle.",
        tone: 'green' as const,
    },
    {
        eyebrow: 'Execution',
        title: 'J-Code, GPS et preuves terrain',
        text: 'Le fournisseur doit etre sur site boutique, puis la mission garde ses preuves.',
        tone: 'clay' as const,
    },
    {
        eyebrow: 'Validation',
        title: 'OTP client avant tout decaissement',
        text: 'Aucun jalon ne libere de fonds sans confirmation SMS ou validation prevue.',
        tone: 'night' as const,
    },
];

const keyStats = [
    { label: 'Intelligence Artificielle', value: 'API Gemini (Diagnostic, Chat, RAG)' },
    { label: 'Acteurs relies', value: 'Clients, artisans, fournisseurs, referents' },
    { label: 'Paiements CI', value: 'Wave CI et Orange Money CI' },
    { label: 'Preuves mission', value: 'OTP, photos geo, logs et litiges' },
    { label: 'Pilotage admin', value: 'KYC, paiements, fraudes et arbitrages' },
];

const audienceCards = [
    {
        badge: 'Client',
        title: 'Commander un chantier sans payer a l aveugle',
        text: "Le client decrit son besoin, obtient un parcours lisible et garde la main sur chaque validation de mission.",
        points: ['Diagnostic assiste et estimation initiale', 'Devis lisible avec jalons et FCFA entiers', 'OTP obligatoire avant liberation de fonds'],
        icon: 'client' as const,
        tone: 'gold' as const,
    },
    {
        badge: 'Artisan',
        title: 'Recevoir des missions, etre paye proprement et monter en confiance',
        text: "L artisan ne depend plus seulement du bouche-a-oreille. Il gere devis, execution, jalons et capital reputation.",
        points: ['Matching local base sur la proximite', 'J-Code materiaux pour les achats chantier', "Score ProsArtisan archive pour la solvabilite"],
        icon: 'artisan' as const,
        tone: 'green' as const,
    },
    {
        badge: 'Fournisseur',
        title: 'Livrer les materiaux avec une verification claire et un reglement cadree',
        text: "La quincaillerie agreee valide les jetons chantier, prouve sa presence boutique et securise sa livraison.",
        points: ['Scan J-Code ou code USSD', 'Blocage automatique si GPS hors zone', 'Paiement fournisseur garanti selon le flux'],
        icon: 'store' as const,
        tone: 'clay' as const,
    },
    {
        badge: 'Livreur',
        title: 'Sécuriser le transport de matériaux avec double code',
        text: "Le livreur reçoit les opportunités de courses locales et garantit sa rémunération à la livraison.",
        points: ['Calcul dynamique du prix de course', 'Code de retrait quincaillerie (pickup)', 'Code de réception client (reception)'],
        icon: 'gps' as const,
        tone: 'gold' as const,
    },
];

const flowSteps = [
    {
        phase: 'Phase 0',
        title: 'Onboarding OTP et KYC',
        text: "Inscription par telephone, OTP, choix du role et verification KYC avant toute transaction.",
        points: ['Photo CNI et selfie', 'Activation admin requise', 'Aucun contournement du KYC'],
        tone: 'gold' as const,
    },
    {
        phase: 'Phase 1',
        title: 'Diagnostic et matching local',
        text: "L'IA analyse le besoin (texte et photos) pour estimer le budget FCFA, puis les artisans actifs sont proposes dans un rayon court.",
        points: ['Analyse IA par Gemini', 'Estimation FCFA automatique', 'Position artisan floutee'],
        tone: 'blue' as const,
    },
    {
        phase: 'Phase 2',
        title: 'Devis et sequestre intelligent',
        text: "Le devis separe clairement materiaux et main d'oeuvre, puis le ratio est fige des acceptation.",
        points: ['Lignes de cout distinctes', 'Wallet materiaux / wallet MO', 'Ratio immuable apres accord'],
        tone: 'green' as const,
    },
    {
        phase: 'Phase 3',
        title: 'J-Code materiaux',
        text: "L artisan cree le jeton chantier. Le fournisseur doit etre physiquement proche de sa boutique pour valider.",
        points: ['Code PA-XXXX ou QR', 'Controle GPS < 100 m', 'Alerte admin si anomalie'],
        tone: 'clay' as const,
    },
    {
        phase: 'Phase 4',
        title: 'Jalons, preuves et OTP',
        text: "Chaque jalon embarque checklist, photos geolocalisees et validation client avant liberation de fonds.",
        points: ['OTP 4 chiffres par SMS', 'Photos chantier', 'Referent obligatoire au-dessus de 2 000 000 FCFA'],
        tone: 'night' as const,
    },
    {
        phase: 'Phase 5',
        title: "Cloture, note et score ProsArtisan",
        text: "La mission se ferme avec signature, evaluation, archivage du score et lecture solvabilite.",
        points: ['Notation client', "Score ProsArtisan 40 / 30 / 20 / 10", "Base utile pour micro-credit d'urgence"],
        tone: 'gold' as const,
    },
];

const safeguardCards = [
    {
        title: 'KYC actif obligatoire',
        text: 'Client et artisan doivent etre actifs avant mission, devis finance ou paiement.',
        icon: 'shield' as const,
    },
    {
        title: 'GPS artisan protege',
        text: 'Le client ne recoit jamais la position exacte de l artisan. La localisation est volontairement brouillee.',
        icon: 'gps' as const,
    },
    {
        title: 'GPS fournisseur verrouille',
        text: 'Si le scan J-Code se fait hors rayon boutique, la transaction est bloquee automatiquement.',
        icon: 'pin' as const,
    },
    {
        title: 'OTP avant decaissement',
        text: 'Le wallet MO ne se debloque pas sans validation jalon cote client.',
        icon: 'otp' as const,
    },
    {
        title: 'Seuil referent',
        text: 'Au-dessus de 2 000 000 FCFA, une visite physique est requise avant liberation.',
        icon: 'alert' as const,
    },
    {
        title: 'Litige trace et arbitrable',
        text: 'Photos, chat, journaux et decision admin restent centralises dans le dossier.',
        icon: 'scale' as const,
    },
];

const adminModules = [
    {
        title: 'Validation KYC',
        text: 'Activer ou rejeter les dossiers, filtrer les comptes et prioriser les validations en attente.',
        icon: 'shield' as const,
    },
    {
        title: 'Suivi des missions',
        text: 'Visualiser les missions financees, les jalons, les preuves et les projets a superviser.',
        icon: 'dashboard' as const,
    },
    {
        title: 'Paiements et wallets',
        text: 'Suivre sequestres, decaissements, collectes et anomalies de paiement mobile.',
        icon: 'wallet' as const,
    },
    {
        title: 'Litiges et fraude',
        text: 'Ouvrir un dossier, arbitrer, geler une operation ou demander une verification terrain.',
        icon: 'scale' as const,
    },
    {
        title: 'Administration LLM (IA)',
        text: 'Piloter le moteur de diagnostic Gemini, configurer les contextes, importer des donnees et chatter avec les bases documentaires.',
        icon: 'robot' as const,
    },
];

const terrainCards = [
    {
        title: 'Wave CI et Orange Money CI',
        text: 'Le parcours reste pense pour les usages mobile money dominants sur le terrain.',
        icon: 'wallet' as const,
    },
    {
        title: 'Android prioritaire',
        text: 'La premiere experience produit est concue pour les telephones les plus utilises localement.',
        icon: 'mobile' as const,
    },
    {
        title: 'Faible connectivite et USSD',
        text: 'Le flux anticipe les zones ou la data est instable et les usages restent tres pratiques.',
        icon: 'signal' as const,
    },
    {
        title: 'FCFA entiers',
        text: 'Les montants restent lisibles, sans decimales ni confusion sur les sommes financieres.',
        icon: 'score' as const,
    },
];

const scoreParts = [
    { label: 'Fiabilite', value: 40 },
    { label: 'Integrite', value: 30 },
    { label: 'Qualite', value: 20 },
    { label: 'Reactivite', value: 10 },
];

const adminHighlights = [
    { label: 'Vue admin', value: 'KYC, missions, paiements, litiges' },
    { label: 'Dossiers critiques', value: 'Fraude, blocage GPS, referent terrain' },
    { label: 'Collecte de preuves', value: 'OTP, checklist, photos geolocalisees' },
];

export default function Welcome() {
    const currentYear = new Date().getFullYear();
    const [visibleRoles, setVisibleRoles] = useState<string[]>(['Client', 'Artisan', 'Fournisseur', 'Livreur']);

    return (
        <>
            <Head title="ProsArtisan - Travaux securises en Cote d'Ivoire">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800|sora:500,600,700,800" rel="stylesheet" />
            </Head>

            <div className="min-h-screen bg-[var(--landing-bg)] text-[var(--landing-ink)] antialiased" style={landingTheme}>
                <div className="relative overflow-hidden">
                    <div className="pointer-events-none absolute inset-0">
                        <div className="absolute inset-x-0 top-0 h-[520px] bg-[radial-gradient(circle_at_top,rgba(216,168,78,0.18),rgba(246,239,229,0)_58%)]" />
                        <div className="absolute left-[-8rem] top-[-5rem] h-72 w-72 rounded-full bg-[var(--landing-gold)]/18 blur-3xl" />
                        <div className="absolute right-[-10rem] top-20 h-[26rem] w-[26rem] rounded-full bg-[var(--landing-clay)]/16 blur-3xl" />
                        <div className="absolute bottom-20 left-1/3 h-72 w-72 rounded-full bg-[var(--landing-green)]/10 blur-3xl" />
                    </div>

                    <header className="sticky top-0 z-20 border-b border-[var(--landing-border)] bg-[rgba(246,239,229,0.84)] backdrop-blur-xl">
                        <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                            <div className="flex items-center gap-3">
                                <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-[var(--landing-gold)] shadow-[0_16px_30px_rgba(202,154,72,0.28)]">
                                    <img src="/img/prosartisan-logo.png" alt="ProsArtisan" className="h-8 w-8 object-contain" />
                                </div>
                                <div>
                                    <p className="text-[11px] font-semibold uppercase tracking-[0.28em] text-[var(--landing-copy)]">Cote d'Ivoire</p>
                                    <p className="text-xl font-semibold" style={{ fontFamily: '"Sora", sans-serif' }}>
                                        ProsArtisan
                                    </p>
                                </div>
                            </div>

                            <nav className="hidden items-center gap-7 text-sm font-medium text-[var(--landing-copy)] lg:flex">
                                <a href="#roles" className="transition hover:text-[var(--landing-ink)]">
                                    Acteurs
                                </a>
                                <a href="#flux" className="transition hover:text-[var(--landing-ink)]">
                                    Flux
                                </a>
                                <a href="#backoffice" className="transition hover:text-[var(--landing-ink)]">
                                    Backoffice
                                </a>
                                <a href="#terrain" className="transition hover:text-[var(--landing-ink)]">
                                    Terrain
                                </a>
                            </nav>

                            <div className="flex items-center gap-3">
                                <a
                                    href="mailto:contact@prosartisan.ci"
                                    className="hidden rounded-full border border-[var(--landing-border)] bg-white/70 px-4 py-2 text-sm font-semibold text-[var(--landing-ink)] transition hover:bg-white sm:inline-flex"
                                >
                                    Nous contacter
                                </a>
                                <Link
                                    href="/admin/login"
                                    className="inline-flex items-center rounded-full bg-[var(--landing-ink)] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[var(--landing-night)]"
                                >
                                    Acceder au backoffice
                                </Link>
                            </div>
                        </div>
                    </header>

                    <main>
                        <section className="relative">
                            <div className="mx-auto grid max-w-7xl gap-12 px-4 pb-16 pt-14 sm:px-6 lg:grid-cols-[1.02fr_0.98fr] lg:px-8 lg:pb-24 lg:pt-20">
                                <div className="max-w-3xl">
                                    <div className="inline-flex items-center gap-2 rounded-full border border-[var(--landing-border)] bg-white/72 px-4 py-2 text-sm font-semibold text-[var(--landing-gold-deep)]">
                                        <span className="h-2.5 w-2.5 rounded-full bg-[var(--landing-green)]" />
                                        Marketplace travaux securisee pour la realite ivoirienne
                                    </div>

                                    <h1
                                        className="mt-7 text-5xl font-semibold leading-[1.02] tracking-[-0.04em] sm:text-6xl lg:text-7xl"
                                        style={{ fontFamily: '"Sora", sans-serif' }}
                                    >
                                        Le chantier reste visible, finance et controle du debut a la fin.
                                    </h1>

                                    <p className="mt-6 max-w-2xl text-lg leading-8 text-[var(--landing-copy)]">
                                        ProsArtisan connecte clients, artisans, fournisseurs et administrateurs dans un seul flux:
                                        onboarding OTP, KYC, matching local, devis, sequestre, J-Code, validation jalon et arbitrage.
                                    </p>

                                    <div className="mt-8 flex flex-col gap-4 sm:flex-row">
                                        <a
                                            href="#flux"
                                            className="inline-flex items-center justify-center rounded-full bg-[var(--landing-gold)] px-7 py-4 text-base font-semibold text-[var(--landing-ink)] shadow-[0_18px_36px_rgba(213,160,73,0.28)] transition hover:-translate-y-0.5 hover:bg-[#e2b35b]"
                                        >
                                            Comprendre le parcours
                                        </a>
                                        <Link
                                            href="/admin/login"
                                            className="inline-flex items-center justify-center rounded-full border border-[var(--landing-border)] bg-white/72 px-7 py-4 text-base font-semibold text-[var(--landing-ink)] transition hover:bg-white"
                                        >
                                            Ouvrir le backoffice admin
                                        </Link>
                                    </div>

                                    <div className="mt-10 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                        {keyStats.map((stat) => (
                                            <StatChip key={stat.label} label={stat.label} value={stat.value} />
                                        ))}
                                    </div>
                                </div>

                                <div className="relative">
                                    <div className="absolute -left-8 top-12 hidden h-28 w-28 rounded-full border border-[var(--landing-border)] bg-white/30 blur-2xl lg:block" />
                                    <LandingPanel className="relative rounded-[36px] p-5 shadow-[0_30px_76px_rgba(128,95,58,0.14)]">
                                        <div className="rounded-[30px] bg-[linear-gradient(145deg,#fff8ee_0%,#f3e3cf_100%)] p-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.9)]">
                                            <div className="flex items-center justify-between gap-4">
                                                <div>
                                                    <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-[var(--landing-copy)]">
                                                        Orchestration mission
                                                    </p>
                                                    <h2 className="mt-3 text-3xl font-semibold leading-tight text-[var(--landing-ink)]" style={{ fontFamily: '"Sora", sans-serif' }}>
                                                        Un produit lisible pour le terrain et pour l admin
                                                    </h2>
                                                </div>
                                                <span className="rounded-full bg-white/80 px-3 py-1 text-xs font-semibold text-[var(--landing-gold-deep)]">
                                                    Cocody • 480 000 FCFA
                                                </span>
                                            </div>

                                            <div className="mt-6 grid gap-3 sm:grid-cols-2">
                                                {heroSignals.map((signal) => (
                                                    <SignalCard key={signal.title} {...signal} />
                                                ))}
                                            </div>

                                            <div className="mt-5 rounded-[28px] bg-[var(--landing-night)] p-5 text-white">
                                                <div className="flex items-start justify-between gap-4">
                                                    <div>
                                                        <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-white/55">Flux de confiance</p>
                                                        <p className="mt-2 text-2xl font-semibold" style={{ fontFamily: '"Sora", sans-serif' }}>
                                                            Quatre verrous critiques
                                                        </p>
                                                    </div>
                                                    <span className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-white/70">
                                                        Backoffice connecte
                                                    </span>
                                                </div>

                                                <div className="mt-6 space-y-3">
                                                    <DarkRow label="Avant financement" text="KYC actif, role valide et matching local controle." />
                                                    <DarkRow
                                                        label="Pendant execution"
                                                        text="Sequestre separe, J-Code materiaux, preuves terrain et supervision admin."
                                                    />
                                                    <DarkRow
                                                        label="Avant paiement"
                                                        text="OTP jalon, visite referent si seuil critique et historique exploitable."
                                                    />
                                                </div>

                                                <div className="mt-6 grid gap-3 sm:grid-cols-3">
                                                    {adminHighlights.map((item) => (
                                                        <MiniDarkTile key={item.label} label={item.label} value={item.value} />
                                                    ))}
                                                </div>
                                            </div>
                                        </div>
                                    </LandingPanel>
                                </div>
                            </div>
                        </section>

                        <section id="roles" className="border-y border-[var(--landing-border)] bg-white/42 py-20">
                            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                                <SectionHeading
                                    eyebrow="Acteurs relies"
                                    title="Une experience differente pour chaque role, mais un seul cadre de confiance"
                                    text="ProsArtisan ne vend pas seulement de la mise en relation. La plateforme structure les droits, les preuves et les paiements pour tous les intervenants."
                                />

                                {/* Interactive Visibility Controls */}
                                <div className="mt-8 flex flex-wrap items-center justify-center gap-6 p-5 rounded-[24px] border border-[var(--landing-border)] bg-white/60 backdrop-blur-md max-w-2xl mx-auto">
                                    <span className="text-sm font-bold text-[var(--landing-ink)]">Afficher / Masquer :</span>
                                    {['Client', 'Artisan', 'Fournisseur', 'Livreur'].map(role => (
                                        <label key={role} className="flex items-center gap-2.5 cursor-pointer text-sm font-semibold text-[var(--landing-copy)] hover:text-[var(--landing-ink)] transition select-none">
                                            <input
                                                type="checkbox"
                                                checked={visibleRoles.includes(role)}
                                                onChange={(e) => {
                                                    if (e.target.checked) {
                                                        setVisibleRoles([...visibleRoles, role]);
                                                    } else {
                                                        setVisibleRoles(visibleRoles.filter(r => r !== role));
                                                    }
                                                }}
                                                className="h-4 w-4 rounded border-[var(--landing-border)] text-[#b77918] focus:ring-[#b77918] transition cursor-pointer"
                                            />
                                            {role}
                                        </label>
                                    ))}
                                </div>

                                {/* Autolayout dynamic flex grid */}
                                <div className="mt-10 flex flex-wrap justify-center gap-6">
                                    {audienceCards
                                        .filter(card => visibleRoles.includes(card.badge))
                                        .map((card) => (
                                            <div 
                                                key={card.badge} 
                                                className="w-full sm:w-[calc(50%-12px)] lg:w-[calc(33.33%-16px)] xl:w-[calc(25%-18px)] max-w-[350px] flex shrink-0 grow justify-center transition-all duration-300"
                                            >
                                                <AudienceCard {...card} />
                                            </div>
                                        ))}
                                </div>
                            </div>
                        </section>

                        <section id="flux" className="py-20">
                            <div className="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-[0.88fr_1.12fr] lg:px-8">
                                <div>
                                    <SectionHeading
                                        eyebrow="Flux metier"
                                        title="Le parcours complet est enfin raconte comme un produit, pas comme une documentation"
                                        text="De l inscription jusqu au score final, chaque etape a une fonction precise et des regles non negociables."
                                    />

                                    <LandingPanel className="mt-8 rounded-[32px] p-6">
                                        <p className="text-xs font-semibold uppercase tracking-[0.24em] text-[var(--landing-copy)]">Ce que le systeme verrouille</p>
                                        <div className="mt-5 space-y-3">
                                            <LockLine title="Ratio materiaux / MO immuable" text="Le sequestre est fige a l acceptation du devis et ne change plus ensuite." />
                                            <LockLine title="Blocage GPS fournisseur" text="Une livraison hors zone boutique declenche une alerte et stoppe le flux." />
                                            <LockLine title="OTP obligatoire sur jalon" text="Le paiement artisan ne part pas tant que le client ne valide pas." />
                                        </div>
                                    </LandingPanel>

                                    <LandingPanel className="mt-5 rounded-[32px] p-6">
                                        <p className="text-xs font-semibold uppercase tracking-[0.24em] text-[var(--landing-copy)]">Score ProsArtisan</p>
                                        <h3 className="mt-3 text-2xl font-semibold text-[var(--landing-ink)]" style={{ fontFamily: '"Sora", sans-serif' }}>
                                            Un score qui sert l execution et la confiance
                                        </h3>
                                        <div className="mt-6 space-y-4">
                                            {scoreParts.map((item) => (
                                                <ScoreBar key={item.label} label={item.label} value={item.value} />
                                            ))}
                                        </div>
                                    </LandingPanel>
                                </div>

                                <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                                    {flowSteps.map((step, index) => (
                                        <FlowCard key={step.title} index={index} {...step} />
                                    ))}
                                </div>
                            </div>
                        </section>

                        <section className="bg-[linear-gradient(180deg,#fcf5e9_0%,#f3e8d7_100%)] py-20">
                            <div className="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-[1.04fr_0.96fr] lg:px-8">
                                <div>
                                    <SectionHeading
                                        eyebrow="Confiance et controle"
                                        title="Les regles critiques sont visibles tout de suite, pas cachees dans le code"
                                        text="La landing assume ce qui fait la difference: des preuves, des seuils, des blocages automatiques et un vrai arbitrage."
                                    />

                                    <div className="mt-8 grid gap-4 sm:grid-cols-2">
                                        {safeguardCards.map((card) => (
                                            <SafeguardCard key={card.title} {...card} />
                                        ))}
                                    </div>
                                </div>

                                <div className="rounded-[36px] bg-[linear-gradient(145deg,#201b18_0%,#2c221c_100%)] p-6 text-white shadow-[0_34px_80px_rgba(43,31,24,0.22)]">
                                    <p className="text-xs font-semibold uppercase tracking-[0.24em] text-white/55">Lecture backoffice</p>
                                    <h3 className="mt-4 text-3xl font-semibold leading-tight" style={{ fontFamily: '"Sora", sans-serif' }}>
                                        Un poste d operation pour piloter KYC, missions, paiements et litiges
                                    </h3>

                                    <div className="mt-8 space-y-4">
                                        <DarkRow
                                            label="Validation quotidienne"
                                            text="Comptes en attente, demandes KYC, activation des partenaires et dossiers sensibles."
                                        />
                                        <DarkRow
                                            label="Surveillance mission"
                                            text="Jalons soumis, alertes GPS, projets au-dessus du seuil referent et suivis terrains."
                                        />
                                        <DarkRow
                                            label="Arbitrage et paiement"
                                            text="Litiges ouverts, gel des flux, paiements fournisseurs et historique de decaissement."
                                        />
                                    </div>

                                    <div className="mt-8 rounded-[28px] border border-white/10 bg-white/5 p-5">
                                        <p className="text-xs font-semibold uppercase tracking-[0.22em] text-white/50">Vue synthese admin</p>
                                        <div className="mt-5 grid gap-3 sm:grid-cols-2">
                                            <MiniDarkTile label="Comptes a activer" value="KYC et roles a valider" />
                                            <MiniDarkTile label="Missions a suivre" value="Financees, en cours, litiges" />
                                            <MiniDarkTile label="Paiements mobiles" value="Collecte, decaissement, anomalies" />
                                            <MiniDarkTile label="Referents terrain" value="Visites et missions critiques" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section id="backoffice" className="py-20">
                            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                                <SectionHeading
                                    eyebrow="Backoffice"
                                    title="Le poste admin est pense pour travailler, pas pour exposer des details techniques"
                                    text="La plateforme doit aider a prendre des decisions vite: verifier, filtrer, arbitrer, payer, tracer et relancer."
                                />

                                <div className="mt-10 grid gap-6 lg:grid-cols-[0.96fr_1.04fr]">
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        {adminModules.map((module) => (
                                            <AdminModuleCard key={module.title} {...module} />
                                        ))}
                                    </div>

                                    <LandingPanel className="rounded-[34px] p-5">
                                        <div className="rounded-[28px] bg-[linear-gradient(160deg,#fff8ef_0%,#f2e1c7_100%)] p-5">
                                            <div className="flex flex-wrap items-center gap-3">
                                                <span className="rounded-full bg-white/80 px-4 py-2 text-sm font-semibold text-[var(--landing-ink)]">Vue d'ensemble</span>
                                                <span className="rounded-full border border-[var(--landing-border)] bg-white/55 px-4 py-2 text-sm font-semibold text-[var(--landing-copy)]">
                                                    Utilisateurs
                                                </span>
                                                <span className="rounded-full border border-[var(--landing-border)] bg-white/55 px-4 py-2 text-sm font-semibold text-[var(--landing-copy)]">
                                                    Paiements
                                                </span>
                                                <span className="rounded-full border border-[var(--landing-border)] bg-white/55 px-4 py-2 text-sm font-semibold text-[var(--landing-copy)]">
                                                    Parametres
                                                </span>
                                            </div>

                                            <div className="mt-6 grid gap-4 md:grid-cols-2">
                                                <BackofficeMetric title="Comptes en attente" value="12" detail="KYC, activation et pieces manquantes" />
                                                <BackofficeMetric title="Missions en cours" value="28" detail="Suivi jalons, OTP et preuves terrain" />
                                                <BackofficeMetric title="Litiges ouverts" value="3" detail="Arbitrages clients / artisans en attente" />
                                                <BackofficeMetric title="Collecte mobile money" value="14,2M FCFA" detail="Wave CI et Orange Money CI consolides" />
                                            </div>

                                            <div className="mt-5 rounded-[26px] border border-[var(--landing-border)] bg-white/78 p-4">
                                                <div className="grid gap-3 md:grid-cols-[1.05fr_0.95fr]">
                                                    <div>
                                                        <p className="text-xs font-semibold uppercase tracking-[0.22em] text-[var(--landing-copy)]">Liste operable</p>
                                                        <div className="mt-4 space-y-3">
                                                            <ListRow label="Admin Baobab" status="KYC valide" detail="Role: admin • Activite recente" />
                                                            <ListRow label="Mission plomberie Riviera" status="OTP requis" detail="Phase 4 • 850 000 FCFA" />
                                                            <ListRow label="Fournisseur agrees" status="GPS controle" detail="Boutiques, scans et anomalies" />
                                                        </div>
                                                    </div>

                                                    <div className="rounded-[22px] bg-[var(--landing-night)] p-4 text-white">
                                                        <p className="text-xs font-semibold uppercase tracking-[0.22em] text-white/55">Focus du jour</p>
                                                        <div className="mt-4 space-y-3">
                                                            <DarkRow label="A traiter" text="Valider les comptes critiques et purger les faux positifs KYC." />
                                                            <DarkRow label="A surveiller" text="Alertes GPS fournisseurs et missions proches du seuil referent." />
                                                            <DarkRow label="A conclure" text="Arbitrages ouverts, remboursements ou paiements artisan." />
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </LandingPanel>
                                </div>
                            </div>
                        </section>

                        <section id="terrain" className="py-20">
                            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                                <SectionHeading
                                    eyebrow="Concu pour le terrain"
                                    title="Pas une vitrine abstraite: une plateforme faite pour les conditions reelles"
                                    text="Connectivite limitee, paiements mobiles, preuves chantier, coordination locale et parcours clairs depuis le telephone."
                                />

                                <div className="mt-10 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                                    {terrainCards.map((card) => (
                                        <TerrainCard key={card.title} {...card} />
                                    ))}
                                </div>
                            </div>
                        </section>

                        <section className="pb-20">
                            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                                <div className="rounded-[40px] border border-[var(--landing-border)] bg-[linear-gradient(145deg,#fff6e7_0%,#f2dfc2_100%)] p-8 shadow-[0_28px_70px_rgba(141,109,68,0.14)] lg:p-10">
                                    <div className="grid gap-8 lg:grid-cols-[1fr_auto] lg:items-center">
                                        <div>
                                            <p className="text-xs font-semibold uppercase tracking-[0.24em] text-[var(--landing-copy)]">Pret a operer</p>
                                            <h2 className="mt-4 text-4xl font-semibold leading-tight text-[var(--landing-ink)]" style={{ fontFamily: '"Sora", sans-serif' }}>
                                                Une page d accueil qui explique enfin le vrai produit ProsArtisan.
                                            </h2>
                                            <p className="mt-4 max-w-3xl text-base leading-7 text-[var(--landing-copy)]">
                                                Le public comprend les roles, les verrous et les flux. L equipe admin retrouve un acces propre vers son backoffice, sans console technique ni faux ecrans publics.
                                            </p>
                                        </div>

                                        <div className="flex flex-col gap-3 sm:flex-row lg:flex-col">
                                            <Link
                                                href="/admin/login"
                                                className="inline-flex items-center justify-center rounded-full bg-[var(--landing-ink)] px-6 py-3.5 text-sm font-semibold text-white transition hover:bg-[var(--landing-night)]"
                                            >
                                                Acceder au backoffice
                                            </Link>
                                            <a
                                                href="mailto:contact@prosartisan.ci"
                                                className="inline-flex items-center justify-center rounded-full border border-[var(--landing-border)] bg-white/70 px-6 py-3.5 text-sm font-semibold text-[var(--landing-ink)] transition hover:bg-white"
                                            >
                                                Parler a l equipe
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </main>

                    <footer id="contact" className="bg-[var(--landing-night)] text-white">
                        <div className="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
                            <div className="grid gap-10 lg:grid-cols-[1.1fr_0.9fr_0.8fr_0.8fr]">
                                <div>
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-[var(--landing-gold)] text-[var(--landing-ink)]">
                                            <img src="/img/prosartisan-logo.png" alt="ProsArtisan" className="h-8 w-8 object-contain" />
                                        </div>
                                        <div>
                                            <p className="text-lg font-semibold" style={{ fontFamily: '"Sora", sans-serif' }}>
                                                ProsArtisan
                                            </p>
                                            <p className="text-sm text-white/55">Marketplace travaux securisee</p>
                                        </div>
                                    </div>
                                    <p className="mt-5 max-w-md text-sm leading-7 text-white/68">
                                        ProsArtisan connecte les bons acteurs autour d un cadre clair: verification, execution, paiement, preuves et arbitrage.
                                    </p>
                                </div>

                                <FooterGroup title="Produit">
                                    <a href="#roles" className="transition hover:text-white">
                                        Acteurs
                                    </a>
                                    <a href="#flux" className="transition hover:text-white">
                                        Flux metier
                                    </a>
                                    <a href="/cgu" className="transition hover:text-white">
                                        Conditions d'utilisation
                                    </a>
                                    <a href="#backoffice" className="transition hover:text-white">
                                        Backoffice
                                    </a>
                                    <a href="#terrain" className="transition hover:text-white">
                                        Terrain
                                    </a>
                                </FooterGroup>

                                <FooterGroup title="Acces">
                                    <Link href="/admin/login" className="transition hover:text-white">
                                        Backoffice admin
                                    </Link>
                                    <a href="mailto:info@prosartisan.net" className="transition hover:text-white">
                                        info@prosartisan.net
                                    </a>
                                    <a href="tel:+2250160606183" className="transition hover:text-white">
                                        +225 01 60 60 61 83
                                    </a>
                                </FooterGroup>

                                <FooterGroup title="Terrain">
                                    <span>Cote d'Ivoire</span>
                                    <span>Wave CI</span>
                                    <span>Orange Money CI</span>
                                    <span>Android prioritaire</span>
                                </FooterGroup>
                            </div>

                            <div className="mt-10 border-t border-white/10 pt-6 text-sm text-white/55">
                                © {currentYear} ProsArtisan. Tous droits reserves.
                            </div>
                        </div>
                    </footer>
                </div>
            </div>
        </>
    );
}

function SectionHeading({ eyebrow, text, title }: { eyebrow: string; text: string; title: string }) {
    return (
        <div className="max-w-3xl">
            <p className="text-xs font-semibold uppercase tracking-[0.26em] text-[var(--landing-gold-deep)]">{eyebrow}</p>
            <h2 className="mt-4 text-4xl font-semibold leading-tight text-[var(--landing-ink)] sm:text-5xl" style={{ fontFamily: '"Sora", sans-serif' }}>
                {title}
            </h2>
            <p className="mt-4 text-base leading-7 text-[var(--landing-copy)]">{text}</p>
        </div>
    );
}

function LandingPanel({ children, className = '' }: { children: ReactNode; className?: string }) {
    return (
        <section
            className={cn(
                'border border-[var(--landing-border)] bg-[var(--landing-panel)] backdrop-blur-xl shadow-[0_20px_44px_rgba(135,103,65,0.08)]',
                className,
            )}
        >
            {children}
        </section>
    );
}

function StatChip({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-[26px] border border-[var(--landing-border)] bg-[var(--landing-panel)] p-4 backdrop-blur-xl">
            <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-[var(--landing-copy)]">{label}</p>
            <p className="mt-2 text-sm font-semibold leading-6 text-[var(--landing-ink)]">{value}</p>
        </div>
    );
}

function SignalCard({
    eyebrow,
    text,
    title,
    tone,
}: {
    eyebrow: string;
    text: string;
    title: string;
    tone: 'gold' | 'green' | 'clay' | 'night';
}) {
    return (
        <div className="rounded-[24px] border border-[var(--landing-border)] bg-white/78 p-4">
            <div className="flex items-center gap-3">
                <span className={cn('h-3 w-3 rounded-full', signalDotTone(tone))} />
                <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-[var(--landing-copy)]">{eyebrow}</p>
            </div>
            <p className="mt-3 text-base font-semibold text-[var(--landing-ink)]">{title}</p>
            <p className="mt-2 text-sm leading-6 text-[var(--landing-copy)]">{text}</p>
        </div>
    );
}

function AudienceCard({
    badge,
    icon,
    points,
    text,
    title,
    tone,
}: {
    badge: string;
    icon: IconName;
    points: string[];
    text: string;
    title: string;
    tone: 'gold' | 'green' | 'clay';
}) {
    return (
        <LandingPanel className="h-full rounded-[32px] p-7">
            <div className="flex items-center justify-between gap-4">
                <span className={cn('rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em]', badgeTone(tone))}>{badge}</span>
                <div className={cn('flex h-12 w-12 items-center justify-center rounded-2xl', iconPanelTone(tone))}>
                    <FeatureIcon kind={icon} className="h-5 w-5" />
                </div>
            </div>

            <h3 className="mt-5 text-2xl font-semibold leading-tight text-[var(--landing-ink)]" style={{ fontFamily: '"Sora", sans-serif' }}>
                {title}
            </h3>
            <p className="mt-4 text-base leading-7 text-[var(--landing-copy)]">{text}</p>

            <div className="mt-6 space-y-3">
                {points.map((point) => (
                    <FeatureBullet key={point}>{point}</FeatureBullet>
                ))}
            </div>
        </LandingPanel>
    );
}

function FlowCard({
    index,
    phase,
    points,
    text,
    title,
    tone,
}: {
    index: number;
    phase: string;
    points: string[];
    text: string;
    title: string;
    tone: 'gold' | 'blue' | 'green' | 'clay' | 'night';
}) {
    return (
        <LandingPanel className="rounded-[30px] p-6">
            <div className="flex items-center justify-between gap-3">
                <span className={cn('flex h-11 w-11 items-center justify-center rounded-2xl text-sm font-semibold text-white', flowTone(tone))}>
                    0{index + 1}
                </span>
                <span className={cn('rounded-full px-3 py-1 text-xs font-semibold', flowPillTone(tone))}>{phase}</span>
            </div>

            <h3 className="mt-5 text-2xl font-semibold text-[var(--landing-ink)]" style={{ fontFamily: '"Sora", sans-serif' }}>
                {title}
            </h3>
            <p className="mt-3 text-base leading-7 text-[var(--landing-copy)]">{text}</p>

            <div className="mt-5 space-y-3">
                {points.map((point) => (
                    <FeatureBullet key={point}>{point}</FeatureBullet>
                ))}
            </div>
        </LandingPanel>
    );
}

function SafeguardCard({ icon, text, title }: { icon: IconName; text: string; title: string }) {
    return (
        <LandingPanel className="rounded-[28px] p-5">
            <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-[var(--landing-cream)] text-[var(--landing-gold-deep)]">
                <FeatureIcon kind={icon} className="h-5 w-5" />
            </div>
            <h3 className="mt-4 text-lg font-semibold text-[var(--landing-ink)]">{title}</h3>
            <p className="mt-2 text-sm leading-6 text-[var(--landing-copy)]">{text}</p>
        </LandingPanel>
    );
}

function AdminModuleCard({ icon, text, title }: { icon: IconName; text: string; title: string }) {
    return (
        <LandingPanel className="rounded-[30px] p-6">
            <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-[var(--landing-cream)] text-[var(--landing-gold-deep)]">
                <FeatureIcon kind={icon} className="h-5 w-5" />
            </div>
            <h3 className="mt-5 text-xl font-semibold text-[var(--landing-ink)]" style={{ fontFamily: '"Sora", sans-serif' }}>
                {title}
            </h3>
            <p className="mt-3 text-sm leading-6 text-[var(--landing-copy)]">{text}</p>
        </LandingPanel>
    );
}

function TerrainCard({ icon, text, title }: { icon: IconName; text: string; title: string }) {
    return (
        <LandingPanel className="rounded-[28px] p-6">
            <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-[var(--landing-cream)] text-[var(--landing-gold-deep)]">
                <FeatureIcon kind={icon} className="h-5 w-5" />
            </div>
            <h3 className="mt-5 text-lg font-semibold text-[var(--landing-ink)]">{title}</h3>
            <p className="mt-2 text-sm leading-6 text-[var(--landing-copy)]">{text}</p>
        </LandingPanel>
    );
}

function FeatureBullet({ children }: { children: ReactNode }) {
    return (
        <div className="flex items-start gap-3">
            <span className="mt-1 flex h-5 w-5 items-center justify-center rounded-full bg-[var(--landing-surface)] text-[var(--landing-gold-deep)]">
                <CheckStroke className="h-3 w-3" />
            </span>
            <p className="text-sm leading-6 text-[var(--landing-copy)]">{children}</p>
        </div>
    );
}

function LockLine({ text, title }: { text: string; title: string }) {
    return (
        <div className="rounded-[24px] border border-[var(--landing-border)] bg-white/72 p-4">
            <p className="text-sm font-semibold text-[var(--landing-ink)]">{title}</p>
            <p className="mt-2 text-sm leading-6 text-[var(--landing-copy)]">{text}</p>
        </div>
    );
}

function ScoreBar({ label, value }: { label: string; value: number }) {
    return (
        <div>
            <div className="mb-2 flex items-center justify-between text-sm">
                <span className="font-semibold text-[var(--landing-ink)]">{label}</span>
                <span className="text-[var(--landing-copy)]">{value}%</span>
            </div>
            <div className="h-3 rounded-full bg-[#ead8bc]">
                <div className="h-3 rounded-full bg-[linear-gradient(90deg,#d8a84e_0%,#c97749_100%)]" style={{ width: `${value}%` }} />
            </div>
        </div>
    );
}

function DarkRow({ label, text }: { label: string; text: string }) {
    return (
        <div className="rounded-[24px] border border-white/10 bg-white/5 p-4">
            <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-white/50">{label}</p>
            <p className="mt-2 text-sm leading-6 text-white/82">{text}</p>
        </div>
    );
}

function MiniDarkTile({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-[20px] border border-white/10 bg-white/5 px-4 py-3">
            <p className="text-[11px] uppercase tracking-[0.2em] text-white/50">{label}</p>
            <p className="mt-2 text-sm font-semibold text-white">{value}</p>
        </div>
    );
}

function BackofficeMetric({ detail, title, value }: { detail: string; title: string; value: string }) {
    return (
        <div className="rounded-[24px] border border-[var(--landing-border)] bg-white/78 p-4">
            <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-[var(--landing-copy)]">{title}</p>
            <p className="mt-3 text-3xl font-semibold text-[var(--landing-ink)]" style={{ fontFamily: '"Sora", sans-serif' }}>
                {value}
            </p>
            <p className="mt-2 text-sm leading-6 text-[var(--landing-copy)]">{detail}</p>
        </div>
    );
}

function ListRow({ detail, label, status }: { detail: string; label: string; status: string }) {
    return (
        <div className="rounded-[18px] border border-[var(--landing-border)] bg-[var(--landing-panel)] p-3">
            <div className="flex items-center justify-between gap-3">
                <p className="text-sm font-semibold text-[var(--landing-ink)]">{label}</p>
                <span className="rounded-full bg-[var(--landing-cream)] px-3 py-1 text-[11px] font-semibold text-[var(--landing-gold-deep)]">
                    {status}
                </span>
            </div>
            <p className="mt-2 text-sm leading-6 text-[var(--landing-copy)]">{detail}</p>
        </div>
    );
}

function FooterGroup({ children, title }: { children: ReactNode; title: string }) {
    return (
        <div className="space-y-3 text-sm text-white/68">
            <p className="text-xs font-semibold uppercase tracking-[0.22em] text-white/45">{title}</p>
            <div className="flex flex-col gap-2">{children}</div>
        </div>
    );
}

type IconName = 'alert' | 'artisan' | 'client' | 'dashboard' | 'gps' | 'mobile' | 'otp' | 'pin' | 'robot' | 'scale' | 'score' | 'shield' | 'signal' | 'store' | 'wallet';

function FeatureIcon({ className, kind }: { className?: string; kind: IconName }) {
    switch (kind) {
        case 'client':
            return (
                <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                    <circle cx="12" cy="8" r="3.2" />
                    <path d="M5.5 19c0-3.4 2.9-5.7 6.5-5.7s6.5 2.3 6.5 5.7" strokeLinecap="round" />
                </svg>
            );
        case 'artisan':
            return (
                <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                    <path d="m8 5 11 11" strokeLinecap="round" />
                    <path d="m13.5 4.5 6 6-2.1 2.1-6-6Z" strokeLinejoin="round" />
                    <path d="m4.8 14.6 4.6 4.6-2.2 1.1a2.8 2.8 0 0 1-3.7-3.7Z" strokeLinejoin="round" />
                </svg>
            );
        case 'store':
            return (
                <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                    <path d="M4 9.5 5.3 5h13.4L20 9.5" strokeLinejoin="round" />
                    <path d="M5 9.5h14V19H5Z" strokeLinejoin="round" />
                    <path d="M9 13.5h6" strokeLinecap="round" />
                </svg>
            );
        case 'shield':
            return (
                <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                    <path d="M12 3s5 2 7 3v5c0 5-3.4 8-7 10-3.6-2-7-5-7-10V6c2-1 7-3 7-3Z" strokeLinecap="round" strokeLinejoin="round" />
                    <path d="m9.5 12 1.6 1.8 3.4-3.8" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
            );
        case 'gps':
            return (
                <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="6" />
                    <circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none" />
                    <path d="M12 3v3" strokeLinecap="round" />
                    <path d="M12 18v3" strokeLinecap="round" />
                    <path d="M3 12h3" strokeLinecap="round" />
                    <path d="M18 12h3" strokeLinecap="round" />
                </svg>
            );
        case 'pin':
            return (
                <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                    <path d="M12 20s6-5.4 6-10a6 6 0 1 0-12 0c0 4.6 6 10 6 10Z" strokeLinecap="round" strokeLinejoin="round" />
                    <circle cx="12" cy="10" r="2.2" />
                </svg>
            );
        case 'otp':
            return (
                <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                    <rect x="5" y="3.5" width="14" height="17" rx="2.5" />
                    <path d="M8.5 8.5h7" strokeLinecap="round" />
                    <path d="M8.5 12h7" strokeLinecap="round" />
                    <path d="M9 16h1.2" strokeLinecap="round" />
                    <path d="M12 16h1.2" strokeLinecap="round" />
                    <path d="M15 16h1.2" strokeLinecap="round" />
                </svg>
            );
        case 'alert':
            return (
                <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                    <path d="m12 4 8 14H4Z" strokeLinejoin="round" />
                    <path d="M12 9v4.5" strokeLinecap="round" />
                    <circle cx="12" cy="17.2" r="1" fill="currentColor" stroke="none" />
                </svg>
            );
        case 'scale':
            return (
                <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                    <path d="M12 5v14" strokeLinecap="round" />
                    <path d="M6 8h12" strokeLinecap="round" />
                    <path d="m7 8-3 5h6Z" strokeLinejoin="round" />
                    <path d="m17 8-3 5h6Z" strokeLinejoin="round" />
                    <path d="M9.5 20h5" strokeLinecap="round" />
                </svg>
            );
        case 'robot':
            return (
                <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                    <rect x="5" y="8" width="14" height="11" rx="2.5" />
                    <circle cx="9" cy="12" r="1.2" fill="currentColor" />
                    <circle cx="15" cy="12" r="1.2" fill="currentColor" />
                    <path d="M10 15.5h4" strokeLinecap="round" />
                    <path d="M12 4v4" strokeLinecap="round" />
                    <circle cx="12" cy="3" r="1" fill="currentColor" />
                </svg>
            );
        case 'dashboard':
            return (
                <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                    <rect x="4" y="4" width="6.5" height="6.5" rx="1.5" />
                    <rect x="13.5" y="4" width="6.5" height="10.5" rx="1.5" />
                    <rect x="4" y="13.5" width="6.5" height="6.5" rx="1.5" />
                    <rect x="13.5" y="17.5" width="6.5" height="2.5" rx="1.2" />
                </svg>
            );
        case 'wallet':
            return (
                <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                    <path d="M5 7.5A2.5 2.5 0 0 1 7.5 5H18a1 1 0 0 1 0 2H7.5A.5.5 0 0 0 7 7.5v.5h12a1 1 0 0 1 1 1v7.5a2.5 2.5 0 0 1-2.5 2.5h-10A2.5 2.5 0 0 1 5 16.5Z" strokeLinejoin="round" />
                    <path d="M16 13h4" strokeLinecap="round" />
                </svg>
            );
        case 'mobile':
            return (
                <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                    <rect x="7" y="3" width="10" height="18" rx="2.5" />
                    <path d="M10.5 6h3" strokeLinecap="round" />
                    <circle cx="12" cy="17" r="1" fill="currentColor" stroke="none" />
                </svg>
            );
        case 'signal':
            return (
                <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                    <path d="M4 18h16" strokeLinecap="round" />
                    <path d="M6.5 14.5a8.5 8.5 0 0 1 11 0" strokeLinecap="round" />
                    <path d="M9.5 11.5a4.5 4.5 0 0 1 5 0" strokeLinecap="round" />
                    <circle cx="12" cy="18" r="1.4" fill="currentColor" stroke="none" />
                </svg>
            );
        case 'score':
            return (
                <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                    <path d="M5 18h14" strokeLinecap="round" />
                    <path d="M8 18v-5" strokeLinecap="round" />
                    <path d="M12 18V8" strokeLinecap="round" />
                    <path d="M16 18v-8" strokeLinecap="round" />
                </svg>
            );
    }
}

function CheckStroke({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="2.2" viewBox="0 0 24 24">
            <path d="m5 12.5 4.2 4L19 7.5" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

function badgeTone(tone: 'gold' | 'green' | 'clay') {
    const classes = {
        gold: 'bg-[#f7e7c9] text-[var(--landing-gold-deep)]',
        green: 'bg-[#e6f5ee] text-[var(--landing-green)]',
        clay: 'bg-[#fbe7e0] text-[var(--landing-clay)]',
    };

    return classes[tone];
}

function iconPanelTone(tone: 'gold' | 'green' | 'clay') {
    const classes = {
        gold: 'bg-[#fff0d9] text-[var(--landing-gold-deep)]',
        green: 'bg-[#e9f6f0] text-[var(--landing-green)]',
        clay: 'bg-[#fde9e3] text-[var(--landing-clay)]',
    };

    return classes[tone];
}

function signalDotTone(tone: 'gold' | 'green' | 'clay' | 'night') {
    const classes = {
        gold: 'bg-[var(--landing-gold)]',
        green: 'bg-[var(--landing-green)]',
        clay: 'bg-[var(--landing-clay)]',
        night: 'bg-[var(--landing-night)]',
    };

    return classes[tone];
}

function flowTone(tone: 'gold' | 'blue' | 'green' | 'clay' | 'night') {
    const classes = {
        gold: 'bg-[var(--landing-gold)]',
        blue: 'bg-[#397eb5]',
        green: 'bg-[var(--landing-green)]',
        clay: 'bg-[var(--landing-clay)]',
        night: 'bg-[var(--landing-night)]',
    };

    return classes[tone];
}

function flowPillTone(tone: 'gold' | 'blue' | 'green' | 'clay' | 'night') {
    const classes = {
        gold: 'bg-[#f7e8cb] text-[var(--landing-gold-deep)]',
        blue: 'bg-[#e8f2fb] text-[#397eb5]',
        green: 'bg-[#e8f5ef] text-[var(--landing-green)]',
        clay: 'bg-[#fde8e2] text-[var(--landing-clay)]',
        night: 'bg-[#ede8e2] text-[var(--landing-night)]',
    };

    return classes[tone];
}

function cn(...values: Array<string | false | null | undefined>) {
    return values.filter(Boolean).join(' ');
}
