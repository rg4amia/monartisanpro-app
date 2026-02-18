<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ProsArtisan - Trouvez votre artisan de confiance en Côte d'Ivoire</title>
    <meta name="description"
        content="Plateforme de mise en relation sécurisée entre artisans qualifiés et clients en Côte d'Ivoire. Paiement sécurisé, géolocalisation, score de réputation N'Zassa.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            :root {
                --bg-primary: #F8F9FA;
                --bg-secondary: #FFFFFF;
                --bg-card: #FFFFFF;
                --text-primary: #1A1A1A;
                --text-secondary: #6B7280;
                --text-tertiary: #9CA3AF;
                --accent-primary: #4F46E5;
                --accent-secondary: #10B981;
                --accent-highlight: #F59E0B;
                --radius-card: 16px;
                --radius-button: 12px;
                --spacing-base: 16px;
                --spacing-lg: 24px;
                --spacing-xl: 32px;
                --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.07);
                --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
            }

            @media (prefers-color-scheme: dark) {
                :root {
                    --bg-primary: #1A1F3A;
                    --bg-secondary: #232B4A;
                    --bg-card: #2A3354;
                    --text-primary: #FFFFFF;
                    --text-secondary: #A8B2D1;
                    --text-tertiary: #7A8AA8;
                    --accent-primary: #5B7FFF;
                    --accent-secondary: #4ADE80;
                    --accent-highlight: #FBBF24;
                    --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.4);
                    --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.5);
                }
            }

            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
                line-height: 1.5;
                color: var(--text-primary);
                background: var(--bg-primary);
            }

            .container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 0 20px;
            }

            nav {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                background: var(--bg-secondary);
                box-shadow: var(--shadow-md);
                z-index: 1000;
                backdrop-filter: blur(10px);
            }

            nav .container {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 16px 20px;
            }

            .logo {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 24px;
                font-weight: 700;
                color: var(--text-primary);
                text-decoration: none;
            }

            .logo-icon {
                width: 40px;
                height: 40px;
                background: linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-secondary) 100%);
                border-radius: var(--radius-button);
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
            }

            .logo-text {
                color: var(--accent-primary);
            }

            .nav-links {
                display: flex;
                gap: 24px;
                align-items: center;
            }

            .nav-links a {
                text-decoration: none;
                color: var(--text-secondary);
                font-weight: 500;
                transition: color 0.3s;
            }

            .nav-links a:hover {
                color: var(--accent-primary);
            }

            .btn {
                padding: 16px 24px;
                border-radius: var(--radius-button);
                text-decoration: none;
                font-weight: 600;
                font-size: 16px;
                transition: all 0.3s;
                display: inline-block;
            }

            .btn-primary {
                background: linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-secondary) 100%);
                color: white;
                box-shadow: var(--shadow-md);
            }

            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: var(--shadow-lg);
            }

            .btn-secondary {
                background: transparent;
                color: var(--accent-primary);
                border: 2px solid var(--accent-primary);
            }

            .btn-secondary:hover {
                background: var(--accent-primary);
                color: white;
            }

            .hero {
                padding: 120px 0 80px;
                background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
            }

            .hero .container {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 48px;
                align-items: center;
            }

            .hero h1 {
                font-size: 48px;
                font-weight: 700;
                line-height: 1.2;
                margin-bottom: 24px;
            }

            .hero h1 .highlight {
                color: var(--accent-primary);
            }

            .hero p {
                font-size: 20px;
                color: var(--text-secondary);
                margin-bottom: 32px;
                line-height: 1.75;
            }

            .hero-buttons {
                display: flex;
                gap: 16px;
                flex-wrap: wrap;
            }

            .section {
                padding: 80px 0;
            }

            .section-title {
                text-align: center;
                font-size: 40px;
                font-weight: 700;
                margin-bottom: 16px;
            }

            .section-subtitle {
                text-align: center;
                color: var(--text-secondary);
                font-size: 18px;
                margin-bottom: 48px;
            }

            .card-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 24px;
                margin-top: 48px;
            }

            .card {
                background: var(--bg-card);
                padding: 32px;
                border-radius: var(--radius-card);
                box-shadow: var(--shadow-md);
                transition: all 0.3s;
            }

            .card:hover {
                transform: translateY(-8px);
                box-shadow: var(--shadow-lg);
            }

            .card-icon {
                width: 64px;
                height: 64px;
                border-radius: var(--radius-button);
                display: flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 24px;
            }

            .card-icon svg {
                width: 32px;
                height: 32px;
            }

            .card-icon.primary {
                background: linear-gradient(135deg, var(--accent-primary) 0%, #6366F1 100%);
                color: white;
            }

            .card-icon.secondary {
                background: linear-gradient(135deg, var(--accent-secondary) 0%, #059669 100%);
                color: white;
            }

            .card-icon.highlight {
                background: linear-gradient(135deg, var(--accent-highlight) 0%, #F97316 100%);
                color: white;
            }

            .card h3 {
                font-size: 20px;
                font-weight: 600;
                margin-bottom: 12px;
            }

            .card p {
                color: var(--text-secondary);
                line-height: 1.75;
            }

            .score-section {
                background: var(--bg-secondary);
            }

            .score-content {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 64px;
                align-items: center;
            }

            .score-circle {
                width: 300px;
                height: 300px;
                margin: 0 auto;
                position: relative;
            }

            .score-number {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                text-align: center;
            }

            .score-number .value {
                font-size: 64px;
                font-weight: 700;
                color: var(--accent-secondary);
            }

            .score-number .label {
                font-size: 16px;
                color: var(--text-secondary);
                margin-top: 8px;
            }

            .badge {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 8px 16px;
                background: var(--accent-highlight);
                color: #78350F;
                border-radius: 20px;
                font-weight: 600;
                margin-top: 16px;
            }

            .criteria-list {
                list-style: none;
            }

            .criteria-item {
                display: flex;
                gap: 12px;
                margin-bottom: 20px;
            }

            .criteria-item svg {
                flex-shrink: 0;
                width: 24px;
                height: 24px;
                color: var(--accent-primary);
            }

            .criteria-item strong {
                color: var(--accent-primary);
                display: block;
                margin-bottom: 4px;
            }

            .trade-card {
                text-align: center;
                padding: 40px 32px;
                border-radius: var(--radius-card);
                border: 2px solid rgba(79, 70, 229, 0.1);
                transition: all 0.3s;
            }

            .trade-card:hover {
                border-color: var(--accent-primary);
                transform: translateY(-8px);
                box-shadow: 0 10px 30px rgba(79, 70, 229, 0.15);
            }

            .trade-icon {
                width: 80px;
                height: 80px;
                margin: 0 auto 24px;
                background: linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-secondary) 100%);
                border-radius: var(--radius-card);
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .trade-icon svg {
                width: 40px;
                height: 40px;
                color: white;
            }

            .trade-card h3 {
                font-size: 24px;
                font-weight: 600;
                margin-bottom: 8px;
            }

            .cta {
                padding: 80px 0;
                background: linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-secondary) 100%);
                color: white;
                text-align: center;
            }

            .cta h2 {
                font-size: 40px;
                font-weight: 700;
                margin-bottom: 16px;
            }

            .cta p {
                font-size: 20px;
                margin-bottom: 32px;
                opacity: 0.95;
            }

            .btn-white {
                background: white;
                color: var(--accent-primary);
            }

            .btn-white:hover {
                background: rgba(255, 255, 255, 0.95);
            }

            footer {
                padding: 48px 0;
                background: var(--bg-card);
            }

            footer .container {
                display: grid;
                grid-template-columns: 2fr 1fr 1fr 1fr;
                gap: 48px;
            }

            footer h4 {
                font-size: 18px;
                font-weight: 600;
                margin-bottom: 16px;
            }

            footer ul {
                list-style: none;
            }

            footer ul li {
                margin-bottom: 8px;
            }

            footer a {
                color: var(--text-secondary);
                text-decoration: none;
                transition: color 0.3s;
            }

            footer a:hover {
                color: var(--accent-primary);
            }

            .footer-bottom {
                margin-top: 48px;
                padding-top: 32px;
                border-top: 1px solid rgba(107, 114, 128, 0.2);
                text-align: center;
                color: var(--text-tertiary);
            }

            @media (max-width: 768px) {

                .hero .container,
                .score-content,
                footer .container {
                    grid-template-columns: 1fr;
                }

                .card-grid {
                    grid-template-columns: 1fr;
                }

                .hero h1 {
                    font-size: 32px;
                }

                .nav-links {
                    display: none;
                }
            }
        </style>
    @endif
</head>

<body>
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
            <div>
                <svg width="100%" height="400" viewBox="0 0 600 400" fill="none">
                    <defs>
                        <linearGradient id="hero-bg" x1="0" y1="0" x2="600" y2="400">
                            <stop offset="0%" stop-color="#4F46E5" stop-opacity="0.1" />
                            <stop offset="100%" stop-color="#10B981" stop-opacity="0.1" />
                        </linearGradient>
                        <linearGradient id="house-gradient" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#4F46E5" />
                            <stop offset="100%" stop-color="#10B981" />
                        </linearGradient>
                    </defs>
                    <rect width="600" height="400" fill="url(#hero-bg)" rx="16" />
                    <circle cx="300" cy="200" r="120" fill="white" opacity="0.9" />
                    <path d="M300 140 L340 180 L320 180 L320 240 L280 240 L280 180 L260 180 Z"
                        fill="url(#house-gradient)" />
                    <circle cx="450" cy="100" r="40" fill="#10B981" opacity="0.8" />
                    <circle cx="150" cy="300" r="30" fill="#4F46E5" opacity="0.8" />
                </svg>
            </div>
        </div>
    </section>

    <section id="features" class="section">
        <div class="container">
            <h2 class="section-title">Une plateforme complète et sécurisée</h2>
            <p class="section-subtitle">Tout ce dont vous avez besoin pour vos projets de construction et rénovation</p>
            <div class="card-grid">
                <div class="card">
                    <div class="card-icon primary">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <h3>Système de Séquestre Financier</h3>
                    <p>Vos paiements sont sécurisés dans un compte séquestre. L'artisan est payé uniquement après
                        validation des travaux.</p>
                </div>
                <div class="card">
                    <div class="card-icon secondary">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                    </div>
                    <h3>Score N'Zassa</h3>
                    <p>Système de notation transparent basé sur la fiabilité, l'intégrité, la qualité et le
                        professionnalisme des artisans.</p>
                </div>
                <div class="card">
                    <div class="card-icon highlight">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <h3>Géolocalisation</h3>
                    <p>Trouvez les artisans disponibles près de chez vous grâce à notre carte interactive avec
                        clustering intelligent.</p>
                </div>
                <div class="card">
                    <div class="card-icon primary">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </div>
                    <h3>Paiement Mobile Money</h3>
                    <p>Payez facilement avec Orange Money, MTN Money ou Wave via notre intégration CinetPay sécurisée.
                    </p>
                </div>
                <div class="card">
                    <div class="card-icon secondary">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                        </svg>
                    </div>
                    <h3>Jetons Matériaux</h3>
                    <p>Système de jetons pour l'achat de matériaux, garantissant la transparence et évitant les
                        détournements.</p>
                </div>
                <div class="card">
                    <div class="card-icon highlight">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </div>
                    <h3>Notifications en Temps Réel</h3>
                    <p>Restez informé à chaque étape avec des notifications push via Firebase Cloud Messaging.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section score-section">
        <div class="container">
            <div class="score-content">
                <div>
                    <div class="score-circle">
                        <svg width="300" height="300">
                            <circle cx="150" cy="150" r="140" stroke="rgba(107, 114, 128, 0.2)"
                                stroke-width="20" fill="none" />
                            <circle cx="150" cy="150" r="140" stroke="url(#score-gradient)"
                                stroke-width="20" fill="none" stroke-dasharray="879" stroke-dashoffset="220"
                                stroke-linecap="round" transform="rotate(-90 150 150)" />
                            <defs>
                                <linearGradient id="score-gradient" x1="0%" y1="0%" x2="100%"
                                    y2="100%">
                                    <stop offset="0%" stop-color="#10B981" />
                                    <stop offset="100%" stop-color="#059669" />
                                </linearGradient>
                            </defs>
                        </svg>
                        <div class="score-number">
                            <div class="value">85</div>
                            <div class="label">Score N'Zassa</div>
                            <div class="badge">
                                <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                                Badge Or
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <h2 class="section-title" style="text-align: left;">Le Score N'Zassa</h2>
                    <p style="font-size: 18px; color: var(--text-secondary); margin-bottom: 32px;">
                        Un système de notation transparent et équitable qui évalue les artisans sur 5 critères
                        essentiels :
                    </p>
                    <ul class="criteria-list">
                        <li class="criteria-item">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <strong>Fiabilité (40%)</strong>
                                <span style="color: var(--text-secondary);">Respect des délais et engagement</span>
                            </div>
                        </li>
                        <li class="criteria-item">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                            </svg>
                            <div>
                                <strong>Intégrité (30%)</strong>
                                <span style="color: var(--text-secondary);">Utilisation correcte des jetons
                                    matériaux</span>
                            </div>
                        </li>
                        <li class="criteria-item">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                            </svg>
                            <div>
                                <strong>Qualité (15%)</strong>
                                <span style="color: var(--text-secondary);">Évaluations clients et satisfaction</span>
                            </div>
                        </li>
                        <li class="criteria-item">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            <div>
                                <strong>Réactivité (10%)</strong>
                                <span style="color: var(--text-secondary);">Temps de réponse aux demandes</span>
                            </div>
                        </li>
                        <li class="criteria-item">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <div>
                                <strong>Professionnalisme (5%)</strong>
                                <span style="color: var(--text-secondary);">Profil complet et comportement</span>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section id="trades" class="section">
        <div class="container">
            <h2 class="section-title">Nos métiers pilotes</h2>
            <p class="section-subtitle">Trois corps de métiers pour démarrer, avec expansion prévue</p>
            <div class="card-grid">
                <div class="trade-card">
                    <div class="trade-icon">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                    </div>
                    <h3>Plomberie</h3>
                    <p style="color: var(--text-secondary);">Installation, réparation et entretien de systèmes de
                        plomberie et sanitaires</p>
                </div>
                <div class="trade-card">
                    <div class="trade-icon">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <h3>Électricité</h3>
                    <p style="color: var(--text-secondary);">Installation électrique, dépannage et mise aux normes de
                        votre installation</p>
                </div>
                <div class="trade-card">
                    <div class="trade-icon">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <h3>Maçonnerie</h3>
                    <p style="color: var(--text-secondary);">Construction, rénovation et travaux de gros œuvre pour
                        tous vos projets</p>
                </div>
            </div>
        </div>
    </section>

    <section id="download" class="cta">
        <div class="container">
            <h2>Prêt à démarrer votre projet ?</h2>
            <p>Téléchargez l'application mobile ProsArtisan disponible sur Android et iOS</p>
            <div style="display: flex; gap: 16px; justify-content: center; margin-top: 32px; flex-wrap: wrap;">
                <a href="#" class="btn btn-white" style="display: flex; align-items: center; gap: 8px;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <path
                            d="M3,20.5V3.5C3,2.91 3.34,2.39 3.84,2.15L13.69,12L3.84,21.85C3.34,21.6 3,21.09 3,20.5M16.81,15.12L6.05,21.34L14.54,12.85L16.81,15.12M20.16,10.81C20.5,11.08 20.75,11.5 20.75,12C20.75,12.5 20.53,12.9 20.18,13.18L17.89,14.5L15.39,12L17.89,9.5L20.16,10.81M6.05,2.66L16.81,8.88L14.54,11.15L6.05,2.66Z" />
                    </svg>
                    Google Play
                </a>
                <a href="#" class="btn btn-white" style="display: flex; align-items: center; gap: 8px;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <path
                            d="M18.71,19.5C17.88,20.74 17,21.95 15.66,21.97C14.32,22 13.89,21.18 12.37,21.18C10.84,21.18 10.37,21.95 9.1,22C7.79,22.05 6.8,20.68 5.96,19.47C4.25,17 2.94,12.45 4.7,9.39C5.57,7.87 7.13,6.91 8.82,6.88C10.1,6.86 11.32,7.75 12.11,7.75C12.89,7.75 14.37,6.68 15.92,6.84C16.57,6.87 18.39,7.1 19.56,8.82C19.47,8.88 17.39,10.1 17.41,12.63C17.44,15.65 20.06,16.66 20.09,16.67C20.06,16.74 19.67,18.11 18.71,19.5M13,3.5C13.73,2.67 14.94,2.04 15.94,2C16.07,3.17 15.6,4.35 14.9,5.19C14.21,6.04 13.07,6.7 11.95,6.61C11.8,5.46 12.36,4.26 13,3.5Z" />
                    </svg>
                    App Store
                </a>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <div>
                <div class="logo" style="margin-bottom: 16px;">
                    <div class="logo-icon">
                        <svg width="24" height="24" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                    </div>
                    <span>Pros<span class="logo-text">Artisan</span></span>
                </div>
                <p style="color: var(--text-secondary);">La plateforme de confiance pour vos projets de construction en
                    Côte d'Ivoire.</p>
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
