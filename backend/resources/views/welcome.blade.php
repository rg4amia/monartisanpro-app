<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>ProsArtisan - Trouvez votre artisan de confiance en Côte d'Ivoire</title>
    <meta name="description"
        content="Plateforme de mise en relation sécurisée entre artisans qualifiés et clients en Côte d'Ivoire. Paiement sécurisé, géolocalisation, score de réputation N'Zassa.">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />

    <!-- Styles / Scripts -->
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Inter', system-ui, -apple-system, sans-serif;
                line-height: 1.6;
                color: #1f2937;
            }

            .container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 0 1.5rem;
            }

            /* Navigation */
            nav {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                background: white;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                z-index: 1000;
            }

            nav .container {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 1rem 1.5rem;
            }

            .logo {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                font-size: 1.5rem;
                font-weight: 700;
                color: #1f2937;
                text-decoration: none;
            }

            .logo-icon {
                width: 40px;
                height: 40px;
                background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
            }

            .logo-text {
                color: #f97316;
            }

            .nav-links {
                display: flex;
                gap: 1.5rem;
                align-items: center;
            }

            .nav-links a {
                text-decoration: none;
                color: #4b5563;
                transition: color 0.3s;
            }

            .nav-links a:hover {
                color: #f97316;
            }

            .btn {
                padding: 0.625rem 1.5rem;
                border-radius: 0.5rem;
                text-decoration: none;
                font-weight: 600;
                transition: all 0.3s;
                display: inline-block;
            }

            .btn-primary {
                background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
                color: white;
            }

            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 20px rgba(249, 115, 22, 0.3);
            }

            /* Hero Section */
            .hero {
                padding: 8rem 0 4rem;
                background: linear-gradient(135deg, #fff7ed 0%, #ffffff 50%, #f0fdf4 100%);
            }

            .hero .container {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 3rem;
                align-items: center;
            }

            .hero h1 {
                font-size: 3rem;
                font-weight: 800;
                line-height: 1.2;
                margin-bottom: 1.5rem;
            }

            .hero h1 .highlight {
                color: #f97316;
            }

            .hero p {
                font-size: 1.25rem;
                color: #6b7280;
                margin-bottom: 2rem;
            }

            .hero-buttons {
                display: flex;
                gap: 1rem;
            }

            .btn-secondary {
                background: white;
                color: #f97316;
                border: 2px solid #f97316;
            }

            .btn-secondary:hover {
                background: #f97316;
                color: white;
            }

            .hero-image {
                position: relative;
            }

            .hero-image img {
                width: 100%;
                border-radius: 1rem;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            }

            /* Features Section */
            .features {
                padding: 5rem 0;
                background: white;
            }

            .section-title {
                text-align: center;
                font-size: 2.5rem;
                font-weight: 700;
                margin-bottom: 1rem;
            }

            .section-subtitle {
                text-align: center;
                color: #6b7280;
                font-size: 1.125rem;
                margin-bottom: 3rem;
            }

            .features-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 2rem;
                margin-top: 3rem;
            }

            .feature-card {
                padding: 2rem;
                border-radius: 1rem;
                background: #f9fafb;
                transition: all 0.3s;
            }

            .feature-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            }

            .feature-icon {
                width: 60px;
                height: 60px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 1.5rem;
                font-size: 1.75rem;
            }

            .feature-icon.orange {
                background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
                color: white;
            }

            .feature-icon.green {
                background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
                color: white;
            }

            .feature-icon.blue {
                background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
                color: white;
            }

            .feature-card h3 {
                font-size: 1.25rem;
                font-weight: 600;
                margin-bottom: 0.75rem;
            }

            .feature-card p {
                color: #6b7280;
                line-height: 1.6;
            }

            /* Score Section */
            .score-section {
                padding: 5rem 0;
                background: linear-gradient(135deg, #fff7ed 0%, #ffffff 100%);
            }

            .score-content {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 4rem;
                align-items: center;
            }

            .score-visual {
                position: relative;
            }

            .score-circle {
                width: 300px;
                height: 300px;
                margin: 0 auto;
                position: relative;
            }

            .score-circle svg {
                transform: rotate(-90deg);
            }

            .score-number {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                text-align: center;
            }

            .score-number .value {
                font-size: 4rem;
                font-weight: 800;
                color: #22c55e;
            }

            .score-number .label {
                font-size: 1rem;
                color: #6b7280;
                margin-top: 0.5rem;
            }

            .badge {
                display: inline-block;
                padding: 0.5rem 1rem;
                background: #ffd700;
                color: #92400e;
                border-radius: 2rem;
                font-weight: 600;
                margin-top: 1rem;
            }

            /* Trades Section */
            .trades {
                padding: 5rem 0;
                background: white;
            }

            .trades-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 2rem;
                margin-top: 3rem;
            }

            .trade-card {
                text-align: center;
                padding: 2.5rem 2rem;
                border-radius: 1rem;
                border: 2px solid #e5e7eb;
                transition: all 0.3s;
            }

            .trade-card:hover {
                border-color: #f97316;
                transform: translateY(-5px);
                box-shadow: 0 10px 30px rgba(249, 115, 22, 0.1);
            }

            .trade-icon {
                font-size: 3rem;
                margin-bottom: 1rem;
            }

            .trade-card h3 {
                font-size: 1.5rem;
                font-weight: 600;
                margin-bottom: 0.5rem;
            }

            .trade-card p {
                color: #6b7280;
            }

            /* CTA Section */
            .cta {
                padding: 5rem 0;
                background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
                color: white;
                text-align: center;
            }

            .cta h2 {
                font-size: 2.5rem;
                font-weight: 700;
                margin-bottom: 1rem;
            }

            .cta p {
                font-size: 1.25rem;
                margin-bottom: 2rem;
                opacity: 0.9;
            }

            .btn-white {
                background: white;
                color: #f97316;
            }

            .btn-white:hover {
                background: #f9fafb;
            }

            /* Footer */
            footer {
                padding: 3rem 0;
                background: #1f2937;
                color: white;
            }

            footer .container {
                display: grid;
                grid-template-columns: 2fr 1fr 1fr 1fr;
                gap: 3rem;
            }

            footer h4 {
                font-size: 1.125rem;
                font-weight: 600;
                margin-bottom: 1rem;
            }

            footer ul {
                list-style: none;
            }

            footer ul li {
                margin-bottom: 0.5rem;
            }

            footer a {
                color: #9ca3af;
                text-decoration: none;
                transition: color 0.3s;
            }

            footer a:hover {
                color: white;
            }

            .footer-bottom {
                margin-top: 3rem;
                padding-top: 2rem;
                border-top: 1px solid #374151;
                text-align: center;
                color: #9ca3af;
            }

            /* Responsive */
            @media (max-width: 768px) {

                .hero .container,
                .score-content,
                footer .container {
                    grid-template-columns: 1fr;
                }

                .features-grid,
                .trades-grid {
                    grid-template-columns: 1fr;
                }

                .hero h1 {
                    font-size: 2rem;
                }

                .nav-links {
                    display: none;
                }
            }
        </style>
    @endif
</head>

<body>
    <!-- Navigation -->
    <nav>
        <div class="container">
            <a href="/" class="logo">
                <div class="logo-icon">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                </div>
                <span>Pros<span class="logo-text">Artisan</span></span>
            </a>

            <div class="nav-links">
                <a href="{{ url('/admin') }}">Espace Admin</a>
                <a href="#features">Fonctionnalités</a>
                <a href="#trades">Métiers</a>
                <a href="#download" class="btn btn-primary">Télécharger l'app</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div>
                <h1>Trouvez votre <span class="highlight">artisan de confiance</span> en Côte d'Ivoire</h1>
                <p>La plateforme de mise en relation sécurisée entre artisans qualifiés et clients. Paiement protégé,
                    géolocalisation et score de réputation.</p>
                <div class="hero-buttons">
                    <a href="#download" class="btn btn-primary">Télécharger l'application</a>
                    <a href="#features" class="btn btn-secondary">En savoir plus</a>
                </div>
            </div>
            <div class="hero-image">
                <svg width="100%" height="400" viewBox="0 0 600 400" fill="none">
                    <rect width="600" height="400" fill="url(#hero-gradient)" />
                    <circle cx="300" cy="200" r="120" fill="white" opacity="0.9" />
                    <path d="M300 140 L340 180 L320 180 L320 240 L280 240 L280 180 L260 180 Z" fill="#f97316" />
                    <circle cx="450" cy="100" r="40" fill="#22c55e" opacity="0.8" />
                    <circle cx="150" cy="300" r="30" fill="#3b82f6" opacity="0.8" />
                    <defs>
                        <linearGradient id="hero-gradient" x1="0" y1="0" x2="600" y2="400">
                            <stop offset="0%" stop-color="#fff7ed" />
                            <stop offset="100%" stop-color="#f0fdf4" />
                        </linearGradient>
                    </defs>
                </svg>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features">
        <div class="container">
            <h2 class="section-title">Une plateforme complète et sécurisée</h2>
            <p class="section-subtitle">Tout ce dont vous avez besoin pour vos projets de construction et rénovation</p>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon orange">🔒</div>
                    <h3>Système de Séquestre Financier</h3>
                    <p>Vos paiements sont sécurisés dans un compte séquestre. L'artisan est payé uniquement après
                        validation des travaux.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon green">⭐</div>
                    <h3>Score N'Zassa</h3>
                    <p>Système de notation transparent basé sur la fiabilité, l'intégrité, la qualité et le
                        professionnalisme des artisans.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon blue">📍</div>
                    <h3>Géolocalisation</h3>
                    <p>Trouvez les artisans disponibles près de chez vous grâce à notre carte interactive avec
                        clustering intelligent.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon orange">💳</div>
                    <h3>Paiement Mobile Money</h3>
                    <p>Payez facilement avec Orange Money, MTN Money ou Wave via notre intégration CinetPay sécurisée.
                    </p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon green">🎫</div>
                    <h3>Jetons Matériaux</h3>
                    <p>Système de jetons pour l'achat de matériaux, garantissant la transparence et évitant les
                        détournements.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon blue">📱</div>
                    <h3>Notifications en Temps Réel</h3>
                    <p>Restez informé à chaque étape avec des notifications push via Firebase Cloud Messaging.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Score N'Zassa Section -->
    <section class="score-section">
        <div class="container">
            <div class="score-content">
                <div class="score-visual">
                    <div class="score-circle">
                        <svg width="300" height="300">
                            <circle cx="150" cy="150" r="140" stroke="#e5e7eb" stroke-width="20"
                                fill="none" />
                            <circle cx="150" cy="150" r="140" stroke="url(#score-gradient)"
                                stroke-width="20" fill="none" stroke-dasharray="879" stroke-dashoffset="220"
                                stroke-linecap="round" />
                            <defs>
                                <linearGradient id="score-gradient" x1="0%" y1="0%" x2="100%"
                                    y2="100%">
                                    <stop offset="0%" stop-color="#22c55e" />
                                    <stop offset="100%" stop-color="#16a34a" />
                                </linearGradient>
                            </defs>
                        </svg>
                        <div class="score-number">
                            <div class="value">85</div>
                            <div class="label">Score N'Zassa</div>
                            <div class="badge">🥇 Badge Or</div>
                        </div>
                    </div>
                </div>

                <div>
                    <h2 class="section-title" style="text-align: left;">Le Score N'Zassa</h2>
                    <p style="font-size: 1.125rem; color: #6b7280; margin-bottom: 2rem;">
                        Un système de notation transparent et équitable qui évalue les artisans sur 5 critères
                        essentiels :
                    </p>
                    <ul style="list-style: none;">
                        <li style="margin-bottom: 1rem;">
                            <strong style="color: #f97316;">🎯 Fiabilité (40%)</strong><br>
                            <span style="color: #6b7280;">Respect des délais et engagement</span>
                        </li>
                        <li style="margin-bottom: 1rem;">
                            <strong style="color: #f97316;">🛡️ Intégrité (30%)</strong><br>
                            <span style="color: #6b7280;">Utilisation correcte des jetons matériaux</span>
                        </li>
                        <li style="margin-bottom: 1rem;">
                            <strong style="color: #f97316;">⭐ Qualité (15%)</strong><br>
                            <span style="color: #6b7280;">Évaluations clients et satisfaction</span>
                        </li>
                        <li style="margin-bottom: 1rem;">
                            <strong style="color: #f97316;">⚡ Réactivité (10%)</strong><br>
                            <span style="color: #6b7280;">Temps de réponse aux demandes</span>
                        </li>
                        <li style="margin-bottom: 1rem;">
                            <strong style="color: #f97316;">👔 Professionnalisme (5%)</strong><br>
                            <span style="color: #6b7280;">Profil complet et comportement</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Trades Section -->
    <section id="trades" class="trades">
        <div class="container">
            <h2 class="section-title">Nos métiers pilotes</h2>
            <p class="section-subtitle">Trois corps de métiers pour démarrer, avec expansion prévue</p>

            <div class="trades-grid">
                <div class="trade-card">
                    <div class="trade-icon">🔧</div>
                    <h3>Plomberie</h3>
                    <p>Installation, réparation et entretien de systèmes de plomberie et sanitaires</p>
                </div>

                <div class="trade-card">
                    <div class="trade-icon">⚡</div>
                    <h3>Électricité</h3>
                    <p>Installation électrique, dépannage et mise aux normes de votre installation</p>
                </div>

                <div class="trade-card">
                    <div class="trade-icon">🏗️</div>
                    <h3>Maçonnerie</h3>
                    <p>Construction, rénovation et travaux de gros œuvre pour tous vos projets</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Download Section -->
    <section id="download" class="cta">
        <div class="container">
            <h2>Prêt à démarrer votre projet ?</h2>
            <p>Téléchargez l'application mobile ProsArtisan disponible sur Android et iOS</p>
            <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 2rem; flex-wrap: wrap;">
                <a href="#" class="btn btn-white" style="display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <path
                            d="M3,20.5V3.5C3,2.91 3.34,2.39 3.84,2.15L13.69,12L3.84,21.85C3.34,21.6 3,21.09 3,20.5M16.81,15.12L6.05,21.34L14.54,12.85L16.81,15.12M20.16,10.81C20.5,11.08 20.75,11.5 20.75,12C20.75,12.5 20.53,12.9 20.18,13.18L17.89,14.5L15.39,12L17.89,9.5L20.16,10.81M6.05,2.66L16.81,8.88L14.54,11.15L6.05,2.66Z" />
                    </svg>
                    Google Play
                </a>
                <a href="#" class="btn btn-white" style="display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <path
                            d="M18.71,19.5C17.88,20.74 17,21.95 15.66,21.97C14.32,22 13.89,21.18 12.37,21.18C10.84,21.18 10.37,21.95 9.1,22C7.79,22.05 6.8,20.68 5.96,19.47C4.25,17 2.94,12.45 4.7,9.39C5.57,7.87 7.13,6.91 8.82,6.88C10.1,6.86 11.32,7.75 12.11,7.75C12.89,7.75 14.37,6.68 15.92,6.84C16.57,6.87 18.39,7.1 19.56,8.82C19.47,8.88 17.39,10.1 17.41,12.63C17.44,15.65 20.06,16.66 20.09,16.67C20.06,16.74 19.67,18.11 18.71,19.5M13,3.5C13.73,2.67 14.94,2.04 15.94,2C16.07,3.17 15.6,4.35 14.9,5.19C14.21,6.04 13.07,6.7 11.95,6.61C11.8,5.46 12.36,4.26 13,3.5Z" />
                    </svg>
                    App Store
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div>
                <div class="logo" style="margin-bottom: 1rem;">
                    <div class="logo-icon">
                        <svg width="24" height="24" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                    </div>
                    <span>Pros<span class="logo-text">Artisan</span></span>
                </div>
                <p style="color: #9ca3af;">La plateforme de confiance pour vos projets de construction en Côte
                    d'Ivoire.</p>
            </div>

            <div>
                <h4>Plateforme</h4>
                <ul>
                    <li><a href="#features">Fonctionnalités</a></li>
                    <li><a href="#trades">Métiers</a></li>
                    <li><a href="#download">Télécharger</a></li>
                    <li><a href="#">FAQ</a></li>
                </ul>
            </div>

            <div>
                <h4>Entreprise</h4>
                <ul>
                    <li><a href="#">À propos</a></li>
                    <li><a href="#">Blog</a></li>
                    <li><a href="#">Carrières</a></li>
                    <li><a href="#">Contact</a></li>
                </ul>
            </div>

            <div>
                <h4>Légal</h4>
                <ul>
                    <li><a href="#">Conditions d'utilisation</a></li>
                    <li><a href="#">Politique de confidentialité</a></li>
                    <li><a href="#">Mentions légales</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; {{ date('Y') }} ProsArtisan. Tous droits réservés.</p>
        </div>
    </footer>
</body>

</html>
