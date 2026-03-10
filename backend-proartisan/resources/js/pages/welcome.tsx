import { Head, Link } from '@inertiajs/react';

export default function Welcome() {
    return (
        <>
            <Head title="ProsArtisan - Plateforme de confiance">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link
                    href="https://fonts.bunny.net/css?family=inter:400,500,600,700"
                    rel="stylesheet"
                />
            </Head>

            <div className="min-h-screen bg-gray-50 font-sans antialiased">
                {/* Header / Navigation */}
                <header className="bg-white border-b border-gray-200">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex justify-between items-center h-16">
                            {/* Logo */}
                            <div className="flex items-center space-x-2">
                                <div className="flex items-center space-x-1">
                                    <div className="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center">
                                        <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                    </div>
                                    <span className="text-xl font-bold text-gray-900">N'ZASSA</span>
                                </div>
                            </div>

                            {/* Navigation */}
                            <nav className="hidden md:flex items-center space-x-8">
                                <a href="#how-it-works" className="text-sm font-medium text-gray-700 hover:text-indigo-600 transition">
                                    Comment ça marche
                                </a>
                                <a href="#features" className="text-sm font-medium text-gray-700 hover:text-indigo-600 transition">
                                    Fonctionnalités
                                </a>
                                <a href="#artisans" className="text-sm font-medium text-gray-700 hover:text-indigo-600 transition">
                                    Pour les Artisans
                                </a>
                                <a href="#suppliers" className="text-sm font-medium text-gray-700 hover:text-indigo-600 transition">
                                    Pour les Fournisseurs
                                </a>
                            </nav>

                            {/* CTA Buttons */}
                            <div className="flex items-center space-x-4">
                                <Link
                                    href="/login"
                                    className="text-sm font-medium text-gray-700 hover:text-indigo-600 transition"
                                >
                                    Se connecter
                                </Link>
                                <Link
                                    href="/register"
                                    className="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition shadow-sm"
                                >
                                    Commencer
                                </Link>
                            </div>
                        </div>
                    </div>
                </header>

                {/* Hero Section */}
                <section className="relative bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50 overflow-hidden">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-28">
                        <div className="grid lg:grid-cols-2 gap-12 items-center">
                            {/* Left Content */}
                            <div className="space-y-8">
                                <div className="inline-flex items-center space-x-2 px-3 py-1.5 bg-indigo-100 rounded-full">
                                    <div className="w-2 h-2 bg-indigo-600 rounded-full animate-pulse" />
                                    <span className="text-sm font-semibold text-indigo-700">
                                        VÉRIFIÉ & SÉCURISÉ ÉCOSYSTÈME
                                    </span>
                                </div>

                                <h1 className="text-5xl lg:text-6xl font-bold leading-tight">
                                    <span className="text-gray-900">Construisez la Confiance,</span>
                                    <br />
                                    <span className="text-gray-900">Sécurisez le Travail,</span>
                                    <br />
                                    <span className="text-indigo-600">et Développez Votre Entreprise</span>
                                </h1>

                                <p className="text-lg text-gray-600 leading-relaxed max-w-xl">
                                    Connexion des clients, artisans et fournisseurs à travers un système de notation transparent et une protection de paiement sécurisée.
                                </p>

                                <div className="flex flex-col sm:flex-row gap-4">
                                    <Link
                                        href="/register"
                                        className="inline-flex items-center justify-center px-8 py-4 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700 transition shadow-lg hover:shadow-xl"
                                    >
                                        Commencer maintenant
                                    </Link>
                                    <a
                                        href="#how-it-works"
                                        className="inline-flex items-center justify-center px-8 py-4 bg-white text-gray-900 font-semibold rounded-xl hover:bg-gray-50 transition border border-gray-200 shadow-sm"
                                    >
                                        En savoir plus
                                    </a>
                                </div>
                            </div>

                            {/* Right Image */}
                            <div className="relative">
                                <div className="relative rounded-2xl overflow-hidden shadow-2xl aspect-[4/3] bg-gradient-to-br from-indigo-400 to-purple-600">
                                    <div className="absolute inset-0 flex items-center justify-center">
                                        <svg className="w-32 h-32 text-white opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                </div>
                                {/* Decorative elements */}
                                <div className="absolute -top-6 -right-6 w-24 h-24 bg-yellow-400 rounded-full blur-2xl opacity-50" />
                                <div className="absolute -bottom-6 -left-6 w-32 h-32 bg-indigo-400 rounded-full blur-2xl opacity-50" />
                            </div>
                        </div>
                    </div>
                </section>

                {/* Tailored for Your Growth Section */}
                <section id="features" className="py-20 bg-white">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="text-center mb-16">
                            <h2 className="text-4xl font-bold text-gray-900 mb-4">
                                Adapté à Votre Croissance
                            </h2>
                            <p className="text-lg text-gray-600 max-w-2xl mx-auto">
                                Une plateforme unique pour tous les acteurs de l'industrie de la construction et des services domestiques.
                            </p>
                        </div>

                        <div className="grid md:grid-cols-3 gap-8">
                            {/* For Clients */}
                            <div className="group bg-gray-50 rounded-2xl p-8 hover:bg-white hover:shadow-xl transition-all duration-300 border border-gray-100">
                                <div className="w-14 h-14 bg-indigo-100 rounded-xl flex items-center justify-center mb-6 group-hover:bg-indigo-600 transition">
                                    <svg className="w-7 h-7 text-indigo-600 group-hover:text-white transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                </div>
                                <h3 className="text-xl font-semibold text-gray-900 mb-3">
                                    Pour les Clients
                                </h3>
                                <p className="text-gray-600 mb-6 leading-relaxed">
                                    Accédez à des experts vérifiés et gérez vos projets de maison avec une totale tranquillité d'esprit grâce aux paiements protégés.
                                </p>
                                <a
                                    href="#clients"
                                    className="inline-flex items-center text-indigo-600 font-semibold hover:text-indigo-700 transition"
                                >
                                    Trouver un artisan
                                    <svg className="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                    </svg>
                                </a>
                            </div>

                            {/* For Artisans */}
                            <div className="group bg-gray-50 rounded-2xl p-8 hover:bg-white hover:shadow-xl transition-all duration-300 border border-gray-100">
                                <div className="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center mb-6 group-hover:bg-blue-600 transition">
                                    <svg className="w-7 h-7 text-blue-600 group-hover:text-white transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <h3 className="text-xl font-semibold text-gray-900 mb-3">
                                    Pour les Artisans
                                </h3>
                                <p className="text-gray-600 mb-6 leading-relaxed">
                                    Recevez des paiements instantanés dès l'achèvement, accédez à du matériel en crédit, et construisez une réputation professionnelle digitale.
                                </p>
                                <a
                                    href="#artisans"
                                    className="inline-flex items-center text-blue-600 font-semibold hover:text-blue-700 transition"
                                >
                                    Devenir artisan
                                    <svg className="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                    </svg>
                                </a>
                            </div>

                            {/* For Suppliers */}
                            <div className="group bg-gray-50 rounded-2xl p-8 hover:bg-white hover:shadow-xl transition-all duration-300 border border-gray-100">
                                <div className="w-14 h-14 bg-green-100 rounded-xl flex items-center justify-center mb-6 group-hover:bg-green-600 transition">
                                    <svg className="w-7 h-7 text-green-600 group-hover:text-white transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                </div>
                                <h3 className="text-xl font-semibold text-gray-900 mb-3">
                                    Pour les Fournisseurs
                                </h3>
                                <p className="text-gray-600 mb-6 leading-relaxed">
                                    Profitez de ventes garanties et d'un flux constant de commandes de professionnels de confiance dans notre réseau.
                                </p>
                                <a
                                    href="#suppliers"
                                    className="inline-flex items-center text-green-600 font-semibold hover:text-green-700 transition"
                                >
                                    Devenir partenaire
                                    <svg className="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </section>

                {/* N'Zassa Score Section */}
                <section className="py-20 bg-gradient-to-br from-indigo-600 via-indigo-700 to-purple-700 relative overflow-hidden">
                    {/* Background decorations */}
                    <div className="absolute inset-0 opacity-10">
                        <div className="absolute top-0 right-0 w-96 h-96 bg-white rounded-full blur-3xl" />
                        <div className="absolute bottom-0 left-0 w-96 h-96 bg-purple-300 rounded-full blur-3xl" />
                    </div>

                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                        <div className="grid lg:grid-cols-2 gap-12 items-center">
                            {/* Left Content */}
                            <div className="text-white space-y-6">
                                <h2 className="text-4xl lg:text-5xl font-bold leading-tight">
                                    Le Score N'Zassa
                                </h2>
                                <p className="text-lg text-indigo-100 leading-relaxed">
                                    Notre système de notation propriétaire est la fondation de la confiance. Il évalue la performance, la fiabilité et la solvabilité pour garantir des résultats de haute qualité pour tous.
                                </p>

                                <div className="space-y-4">
                                    <div className="flex items-start space-x-3">
                                        <div className="flex-shrink-0 w-6 h-6 bg-indigo-400 rounded-full flex items-center justify-center mt-1">
                                            <svg className="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 className="font-semibold mb-1">Historique vérifié de travail de qualité</h4>
                                            <p className="text-indigo-100 text-sm">Suivi de chaque projet terminé avec succès</p>
                                        </div>
                                    </div>

                                    <div className="flex items-start space-x-3">
                                        <div className="flex-shrink-0 w-6 h-6 bg-indigo-400 rounded-full flex items-center justify-center mt-1">
                                            <svg className="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 className="font-semibold mb-1">Fiabilité financière et historique de paiement</h4>
                                            <p className="text-indigo-100 text-sm">Transactions transparentes et ponctuelles</p>
                                        </div>
                                    </div>

                                    <div className="flex items-start space-x-3">
                                        <div className="flex-shrink-0 w-6 h-6 bg-indigo-400 rounded-full flex items-center justify-center mt-1">
                                            <svg className="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 className="font-semibold mb-1">Indices de satisfaction client</h4>
                                            <p className="text-indigo-100 text-sm">Évaluations et retours réels des clients</p>
                                        </div>
                                    </div>
                                </div>

                                <div className="pt-4">
                                    <a
                                        href="#score"
                                        className="inline-flex items-center px-6 py-3 bg-white text-indigo-600 font-semibold rounded-xl hover:bg-gray-50 transition shadow-lg"
                                    >
                                        Découvrez votre score
                                    </a>
                                </div>
                            </div>

                            {/* Right - Score Gauge */}
                            <div className="flex justify-center">
                                <div className="relative">
                                    {/* Score Circle */}
                                    <div className="relative w-80 h-80 flex items-center justify-center">
                                        {/* Background circle */}
                                        <svg className="absolute inset-0 w-full h-full transform -rotate-90" viewBox="0 0 200 200">
                                            <circle
                                                cx="100"
                                                cy="100"
                                                r="80"
                                                fill="none"
                                                stroke="rgba(255, 255, 255, 0.2)"
                                                strokeWidth="12"
                                            />
                                            {/* Progress circle */}
                                            <circle
                                                cx="100"
                                                cy="100"
                                                r="80"
                                                fill="none"
                                                stroke="white"
                                                strokeWidth="12"
                                                strokeLinecap="round"
                                                strokeDasharray={`${(820 / 1000) * 502} 502`}
                                                className="transition-all duration-1000"
                                            />
                                        </svg>

                                        {/* Score Number */}
                                        <div className="text-center z-10">
                                            <div className="text-7xl font-bold text-white mb-2">820</div>
                                            <div className="text-sm text-indigo-200 font-medium">SUR 1000</div>
                                        </div>
                                    </div>

                                    {/* Label */}
                                    <div className="text-center mt-6">
                                        <div className="text-white font-semibold text-lg">Excellente Fiabilité</div>
                                        <div className="text-indigo-200 text-sm mt-1">Top 5% de tous les artisans</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Secure Project Flow Section */}
                <section id="how-it-works" className="py-20 bg-gray-50">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="text-center mb-16">
                            <h2 className="text-4xl font-bold text-gray-900 mb-4">
                                Flux de Projet Sécurisé
                            </h2>
                            <p className="text-lg text-gray-600 max-w-2xl mx-auto">
                                Comment nous protégeons vos intérêts du début à la fin.
                            </p>
                        </div>

                        <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                            {/* Step 1 */}
                            <div className="relative">
                                <div className="bg-white rounded-2xl p-8 shadow-sm hover:shadow-md transition h-full">
                                    <div className="flex items-center justify-center w-16 h-16 bg-indigo-100 rounded-2xl mb-6">
                                        <svg className="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <div className="mb-3">
                                        <span className="inline-block px-3 py-1 bg-indigo-50 text-indigo-600 text-xs font-bold rounded-full mb-2">
                                            ÉTAPE 1
                                        </span>
                                        <h3 className="text-xl font-semibold text-gray-900">Accord</h3>
                                    </div>
                                    <p className="text-gray-600 text-sm leading-relaxed">
                                        Les parties s'accordent sur le scope, le budget et la timeline.
                                    </p>
                                </div>
                                {/* Connector line */}
                                <div className="hidden lg:block absolute top-12 left-full w-full h-0.5 bg-gradient-to-r from-indigo-200 to-transparent -z-10" />
                            </div>

                            {/* Step 2 */}
                            <div className="relative">
                                <div className="bg-white rounded-2xl p-8 shadow-sm hover:shadow-md transition h-full">
                                    <div className="flex items-center justify-center w-16 h-16 bg-blue-100 rounded-2xl mb-6">
                                        <svg className="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                    </div>
                                    <div className="mb-3">
                                        <span className="inline-block px-3 py-1 bg-blue-50 text-blue-600 text-xs font-bold rounded-full mb-2">
                                            ÉTAPE 2
                                        </span>
                                        <h3 className="text-xl font-semibold text-gray-900">Dépôt en Séquestre</h3>
                                    </div>
                                    <p className="text-gray-600 text-sm leading-relaxed">
                                        Le client sécurise les fonds dans le portefeuille N'Zassa protégé.
                                    </p>
                                </div>
                                <div className="hidden lg:block absolute top-12 left-full w-full h-0.5 bg-gradient-to-r from-blue-200 to-transparent -z-10" />
                            </div>

                            {/* Step 3 */}
                            <div className="relative">
                                <div className="bg-white rounded-2xl p-8 shadow-sm hover:shadow-md transition h-full">
                                    <div className="flex items-center justify-center w-16 h-16 bg-purple-100 rounded-2xl mb-6">
                                        <svg className="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                                        </svg>
                                    </div>
                                    <div className="mb-3">
                                        <span className="inline-block px-3 py-1 bg-purple-50 text-purple-600 text-xs font-bold rounded-full mb-2">
                                            ÉTAPE 3
                                        </span>
                                        <h3 className="text-xl font-semibold text-gray-900">Achèvement du Travail</h3>
                                    </div>
                                    <p className="text-gray-600 text-sm leading-relaxed">
                                        L'artisan effectue le service selon les normes convenues.
                                    </p>
                                </div>
                                <div className="hidden lg:block absolute top-12 left-full w-full h-0.5 bg-gradient-to-r from-purple-200 to-transparent -z-10" />
                            </div>

                            {/* Step 4 */}
                            <div className="relative">
                                <div className="bg-white rounded-2xl p-8 shadow-sm hover:shadow-md transition h-full border-2 border-indigo-100">
                                    <div className="flex items-center justify-center w-16 h-16 bg-indigo-600 rounded-2xl mb-6">
                                        <svg className="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                        </svg>
                                    </div>
                                    <div className="mb-3">
                                        <span className="inline-block px-3 py-1 bg-indigo-600 text-white text-xs font-bold rounded-full mb-2">
                                            ÉTAPE 4
                                        </span>
                                        <h3 className="text-xl font-semibold text-gray-900">Livraison Sécurisée</h3>
                                    </div>
                                    <p className="text-gray-600 text-sm leading-relaxed">
                                        Le paiement sécurisé est instantanément transféré à l'artisan.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Footer */}
                <footer className="bg-gray-900 text-gray-300">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                        <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-12 mb-12">
                            {/* Company Info */}
                            <div className="space-y-4">
                                <div className="flex items-center space-x-2">
                                    <div className="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center">
                                        <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                    </div>
                                    <span className="text-xl font-bold text-white">N'ZASSA</span>
                                </div>
                                <p className="text-sm text-gray-400 leading-relaxed">
                                    Construire le monde de travail moderne connecté. Bâtir la confiance par la technologie.
                                </p>
                                <div className="flex space-x-4">
                                    <a href="#" className="w-10 h-10 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-indigo-600 transition">
                                        <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                        </svg>
                                    </a>
                                    <a href="#" className="w-10 h-10 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-indigo-600 transition">
                                        <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                                        </svg>
                                    </a>
                                    <a href="#" className="w-10 h-10 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-indigo-600 transition">
                                        <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                                        </svg>
                                    </a>
                                </div>
                            </div>

                            {/* Platform */}
                            <div>
                                <h4 className="text-white font-semibold mb-4">Plateforme</h4>
                                <ul className="space-y-2">
                                    <li><a href="#how-it-works" className="text-sm hover:text-white transition">Comment ça marche</a></li>
                                    <li><a href="#security" className="text-sm hover:text-white transition">Sécurité & Confiance</a></li>
                                    <li><a href="#score" className="text-sm hover:text-white transition">Le Score N'Zassa</a></li>
                                    <li><a href="#pricing" className="text-sm hover:text-white transition">Tarification</a></li>
                                </ul>
                            </div>

                            {/* Partners */}
                            <div>
                                <h4 className="text-white font-semibold mb-4">Partenaires</h4>
                                <ul className="space-y-2">
                                    <li><Link href="/register?role=artisan" className="text-sm hover:text-white transition">Pour les Artisans</Link></li>
                                    <li><Link href="/register?role=fournisseur" className="text-sm hover:text-white transition">Pour les Fournisseurs</Link></li>
                                    <li><a href="#corporate" className="text-sm hover:text-white transition">Comptes Entreprises</a></li>
                                    <li><a href="#directory" className="text-sm hover:text-white transition">Annuaire Partenaires</a></li>
                                </ul>
                            </div>

                            {/* Contact */}
                            <div>
                                <h4 className="text-white font-semibold mb-4">Contact</h4>
                                <ul className="space-y-2">
                                    <li>
                                        <a href="mailto:hello@nzassa.com" className="text-sm hover:text-white transition flex items-center">
                                            <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                            hello@nzassa.com
                                        </a>
                                    </li>
                                    <li>
                                        <a href="tel:+22501234567" className="text-sm hover:text-white transition flex items-center">
                                            <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                            </svg>
                                            +225 01 23 45 67 89
                                        </a>
                                    </li>
                                    <li className="text-sm flex items-start">
                                        <svg className="w-4 h-4 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span>Abidjan, Côte d'Ivoire</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        {/* Bottom Bar */}
                        <div className="border-t border-gray-800 pt-8 flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                            <p className="text-sm text-gray-400">
                                © 2024 N'Zassa Ecosystem. Tous droits réservés.
                            </p>
                            <div className="flex flex-wrap justify-center gap-6 text-sm">
                                <a href="#privacy" className="hover:text-white transition">Politique de confidentialité</a>
                                <a href="#terms" className="hover:text-white transition">Conditions d'utilisation</a>
                                <a href="#cookies" className="hover:text-white transition">Politique de cookies</a>
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
