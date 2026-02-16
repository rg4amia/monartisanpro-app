# Plan d'Implémentation ProsArtisan

## Stack Technique Proposée (Adaptation du Cahier des Charges)

- **Backend** : Laravel 11 (PHP 8.2+)  
  - API RESTful (pour mobile Flutter) via Laravel Sanctum  
  - Panel admin web (back-office) avec Filament PHP (ou Laravel Nova) pour une mise en œuvre rapide et moderne  
  - Base de données : PostgreSQL (avec extension PostGIS pour la géolocalisation avancée – clustering, proximité, floutage)  
  - Queue : Laravel Horizon + Redis pour traitements asynchrones (notifications, scoring, etc.)

- **Frontend Mobile** : Flutter 3.19+ (Android & iOS)  
  - State management : Riverpod ou Provider  
  - Local storage offline : Hive ou SQLite + Isar  
  - Maps : google_maps_flutter  
  - Notifications push : firebase_messaging  
  - WhatsApp : url_launcher + intégration API WhatsApp Business (si besoin via webhook)  
  - Paiements Mobile Money : Intégration SDK Wave/Orange Money/MTN ou via agrégateurs (ex. : Flutterwave, CinetPay en Côte d’Ivoire)

- **Infrastructure**  
  - Hébergement : Laravel Vapor ou Forge + serveur VPS (DigitalOcean/AWS)  
  - CI/CD : GitHub Actions  
  - Monitoring : Laravel Telescope + Sentry

## Phases d'Implémentation (Estimation : 4-6 mois pour MVP Phase 1)

### Phase 0 : Préparation (1-2 semaines)
- Setup repository Git (monorepo ou repo séparés backend/frontend)
- Configuration Laravel (Sanctum, Queue, Broadcast)
- Setup Flutter project (flavors : dev/staging/prod)
- Définition des contrats API (OpenAPI/Swagger)
- Choix des packages clés :
  - Backend : spatie/laravel-permission (rôles), laravel/sanctum, filamentphp/filament, grimzy/laravel-mysql-spatial ou PostGIS direct
  - Frontend : dio + retrofit pattern, geolocator, google_maps_flutter, firebase_messaging, photo upload avec image_picker + exif pour géolocalisation

### Phase 1 : Authentification & Gestion Utilisateurs/KYC (3 semaines)
**Backend**
- Models : User (polymorphic roles : Client, Artisan, Fournisseur, Référent, Admin)
- Auth : Sanctum + Socialite (si login Google/Facebook)
- KYC : Upload documents + workflow validation admin (Filament resource)
- Endpoints API : register, login, profile, upload KYC, status KYC

**Frontend**
- Écrans : Onboarding, Register/Login (différents flows par rôle), Profil, Upload pièces (CNI + selfie avec liveness simple via camera)
- Offline : Stockage token local

**Back-office**
- Module KYC Filament : Liste artisans candidats → validation/rejet avec notification push

### Phase 2 : Recherche & Cartographie (2-3 semaines)
**Backend**
- Models : Category, Zone, ArtisanLocation (Point PostGIS)
- Endpoints : search artisans (filtres catégorie/zone/score), nearby artisans (rayon 2km), floutage position (offset aléatoire 50m tant que devis non accepté)
- Clustering : Calcul côté serveur ou renvoyer raw data + clustering Flutter

**Frontend**
- Écran carte avec clustering (google_maps_flutter + flutter_map)
- Marqueurs dorés pour <2km
- Liste avec tri proximité + score
- Confidentialité : Afficher position floutée jusqu'acceptation devis

### Phase 3 : Gestion Projets, Devis & Séquestre (4 semaines)
**Backend**
- Models : Project, Quote (lignes matériel/main-d'œuvre), EscrowWallet, MaterialToken
- Workflow projet : création → devis → acceptation → paiement → séquestre → fragmentation automatique (ratio configurable)
- Intégration paiement Mobile Money (via agrégateur SycaPay/Flutterwave → webhook confirmation)
- Génération Jeton Matériel (QR code avec laravel-snappy ou QR package)

**Frontend**
- Flow client : poster mission → voir devis → payer acompte
- Flow artisan : voir missions proches → créer devis → générer jeton après paiement
- Écran suivi projet avec étapes

### Phase 4 : Jeton Matériel & Validation Anti-Fraude (3 semaines)
**Backend**
- Validation jeton : endpoint scan (QR ou code) → vérif GPS proximity (<100m configurable) → validation partielle possible → paiement fournisseur J+1
- Photo preuve : upload géolocalisée (exif check) → milestone trigger
- Mode offline fournisseur : OTP SMS via Twilio/AfricasTalking

**Frontend**
- Artisan : Générer QR/USSD, prendre photo géolocalisée matériel
- Fournisseur : Écran simple web (PWA Laravel) ou app Flutter légère pour scanner/valider
- Notifications WhatsApp pour alertes critiques

### Phase 5 : Scoring N'Zassa & Libération Paiements (2-3 semaines)
**Backend**
- Model SocialScore + jobs cron pour calcul (fiabilité 40%, intégrité 30%, etc.)
- Historisation variations
- Libération tranches main-d'œuvre sur validation milestone (OTP client)
- Détection fraude (ex. : trop de validations rapprochées)

**Frontend**
- Dashboard artisan : Graphique évolution score (charts_flutter), conseils personnalisés
- Client : Valider étapes → OTP → libération fonds

### Phase 6 : Back-Office Admin Complet (3 semaines)
**Filament Modules**
1. KYC & Utilisateurs (déjà partiel)
2. Tour de contrôle séquestre (monitoring transactions, déblocage manuel)
3. Gestion litiges (chat logs, photos, arbitrage financier)
4. Pilotage scoring (paramétrage poids)
5. Catalogue & Zones (heatmap avec Laravel Charts + Leaflet, indice prix)

### Phase 7 : Tests, Sécurité & Déploiement (2-3 semaines)
- Tests unit/feature backend (PHPUnit)
- Tests UI Flutter (integration_test)
- Sécurité : Rate limiting, validation GPS anti-fraude, 2FA admin, logs audit
- Mode offline/sync Flutter
- Déploiement progressif : beta test avec artisans pilotes (3 métiers)

## Planning Estimé (Équipe 4-6 devs)

| Phase              | Durée       | Livrables Principaux                          |
|-------------------|-------------|-----------------------------------------------|
| 0 Préparation     | 1-2 sem    | Repo, contrats API, setup                     |
| 1 Auth/KYC        | 3 sem      | Inscription, rôles, validation admin          |
| 2 Recherche       | 2-3 sem    | Carte, recherche, proximité                   |
| 3 Projets/Séquestre | 4 sem    | Devis, paiement, séquestre                    |
| 4 Jeton & Anti-fraude | 3 sem  | QR, GPS check, photo preuve                   |
| 5 Scoring         | 2-3 sem    | Calcul score, libération tranches             |
| 6 Back-office     | 3 sem      | Tous modules Filament                         |
| 7 Tests/Déploiement | 2-3 sem  | Beta, corrections, lancement                  |

**Total MVP Phase 1** : ~20-24 semaines (5-6 mois)

## Risques & Recommandations
- Intégrations Mobile Money : Prioriser un agrégateur fiable en CI (CinetPay recommandé)
- Géolocalisation précise : Tester PostGIS + Flutter geolocator en conditions réelles
- Performance maps : Limiter marqueurs + clustering serveur
- Fournisseurs : Développer PWA simple Laravel pour validation jetons (pas d’app dédiée)

Ce plan respecte fidèlement le cahier des charges tout en adaptant la stack à Laravel (backend web + API) et Flutter (mobile).