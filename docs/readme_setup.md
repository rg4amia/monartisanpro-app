# 🚀 PROSARTISAN - GUIDE D'INSTALLATION

## 📋 Table des Matières
1. [Prérequis](#prérequis)
2. [Installation Backend (Laravel)](#installation-backend)
3. [Installation Frontend Mobile (Flutter)](#installation-frontend-mobile)
4. [Configuration des Services](#configuration-des-services)
5. [Lancement des Projets](#lancement-des-projets)
6. [Architecture et Structure](#architecture-et-structure)

---

## 🔧 Prérequis

### Backend
- PHP >= 8.2
- Composer
- PostgreSQL >= 14 (avec extension PostGIS)
- Redis
- Node.js >= 18
- NPM ou Yarn

### Frontend Mobile
- Flutter SDK >= 3.16.0
- Android Studio (pour Android)
- Xcode (pour iOS - Mac uniquement)
- VS Code (recommandé)

### Services Externes
- Compte Firebase (Cloud Messaging)
- Compte Google Cloud (Maps API)
- Accès API Mobile Money (Wave, Orange Money, MTN)

---

## 🔨 Installation Backend (Laravel)

### 1. Cloner et Installer les Dépendances

```bash
cd prosartisan_backend

# Installer les dépendances PHP
composer install

# Installer les dépendances Node.js
npm install

# Copier le fichier d'environnement
cp .env.example .env

# Générer la clé d'application
php artisan key:generate
```

### 2. Configuration de la Base de Données

```bash
# Éditer .env
nano .env
```

```env
APP_NAME=ProsArtisan
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=prosartisan
DB_USERNAME=postgres
DB_PASSWORD=your_password

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mobile Money (à configurer selon vos partenaires)
WAVE_API_KEY=your_wave_key
ORANGE_MONEY_API_KEY=your_orange_key
MTN_MOBILE_MONEY_API_KEY=your_mtn_key

# Firebase
FIREBASE_CREDENTIALS=path/to/firebase-credentials.json

# Google Maps
GOOGLE_MAPS_API_KEY=your_google_maps_key

# WhatsApp Business
WHATSAPP_API_TOKEN=your_whatsapp_token
WHATSAPP_PHONE_NUMBER_ID=your_phone_id
```

### 3. Installation de PostGIS (pour la géolocalisation)

```bash
# Sur Ubuntu/Debian
sudo apt-get install postgresql-14-postgis-3

# Sur macOS avec Homebrew
brew install postgis

# Activer PostGIS dans PostgreSQL
psql -U postgres -d prosartisan -c "CREATE EXTENSION postgis;"
```

### 4. Migrations et Seeds

```bash
# Créer la base de données
php artisan migrate

# Seeder les données de base
php artisan db:seed --class=CategoriesSeeder
php artisan db:seed --class=AdminUserSeeder

# Créer les liens symboliques pour le storage
php artisan storage:link
```

### 5. Configuration des Permissions

```bash
# Installer Spatie Permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate

# Créer les rôles de base
php artisan db:seed --class=RolesAndPermissionsSeeder
```

### 6. Configuration Inertia.js

```bash
# Publier la configuration Inertia
php artisan vendor:publish --provider="Inertia\ServiceProvider"

# Installer les dépendances React
npm install @inertiajs/react react react-dom

# Compiler les assets
npm run dev
```

---

## 📱 Installation Frontend Mobile (Flutter)

### 1. Configuration de Flutter

```bash
cd prosartisan_mobile

# Vérifier l'installation de Flutter
flutter doctor

# Récupérer les dépendances
flutter pub get

# Générer les fichiers Hive (si nécessaire)
flutter packages pub run build_runner build --delete-conflicting-outputs
```

### 2. Configuration Firebase

```bash
# Installer FlutterFire CLI
dart pub global activate flutterfire_cli

# Configurer Firebase pour votre projet
flutterfire configure

# Cela créera automatiquement :
# - android/app/google-services.json
# - ios/Runner/GoogleService-Info.plist
# - lib/firebase_options.dart
```

### 3. Configuration des APIs

Créer le fichier `lib/core/config/api_keys.dart` :

```dart
class ApiKeys {
  static const String googleMapsApiKey = 'YOUR_GOOGLE_MAPS_API_KEY';
  static const String backendBaseUrl = 'http://10.0.2.2:8000'; // Android Emulator
  // static const String backendBaseUrl = 'http://localhost:8000'; // iOS Simulator
  // static const String backendBaseUrl = 'https://api.prosartisan.ci'; // Production
}
```

### 4. Configuration Android

Éditer `android/app/src/main/AndroidManifest.xml` :

```xml
<manifest>
    <uses-permission android:name="android.permission.INTERNET" />
    <uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
    <uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />
    <uses-permission android:name="android.permission.CAMERA" />
    
    <application>
        <!-- Google Maps API Key -->
        <meta-data
            android:name="com.google.android.geo.API_KEY"
            android:value="YOUR_GOOGLE_MAPS_API_KEY"/>
    </application>
</manifest>
```

### 5. Configuration iOS

Éditer `ios/Runner/Info.plist` :

```xml
<key>NSLocationWhenInUseUsageDescription</key>
<string>ProsArtisan a besoin de votre localisation pour trouver des artisans près de chez vous</string>

<key>NSCameraUsageDescription</key>
<string>ProsArtisan a besoin d'accéder à votre caméra pour prendre des photos de preuve</string>

<key>io.flutter.embedded_views_preview</key>
<true/>
```

---

## ⚙️ Configuration des Services

### 1. Redis (Queue & Cache)

```bash
# Démarrer Redis
redis-server

# Configurer les queues dans Laravel
php artisan queue:table
php artisan migrate

# Lancer le worker (dans un terminal séparé)
php artisan queue:work --tries=3
```

### 2. Scheduler (Tâches Cron)

Ajouter dans votre crontab :

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

Ou lancer manuellement en développement :

```bash
php artisan schedule:work
```

### 3. Configuration Mobile Money

Créer les fichiers de configuration pour chaque opérateur :

**config/mobile_money.php**

```php
<?php

return [
    'wave' => [
        'api_key' => env('WAVE_API_KEY'),
        'api_secret' => env('WAVE_API_SECRET'),
        'base_url' => env('WAVE_BASE_URL', 'https://api.wave.com'),
    ],
    
    'orange' => [
        'api_key' => env('ORANGE_MONEY_API_KEY'),
        'merchant_key' => env('ORANGE_MONEY_MERCHANT_KEY'),
        'base_url' => env('ORANGE_BASE_URL', 'https://api.orange.ci'),
    ],
    
    'mtn' => [
        'api_key' => env('MTN_MOBILE_MONEY_API_KEY'),
        'subscription_key' => env('MTN_SUBSCRIPTION_KEY'),
        'base_url' => env('MTN_BASE_URL', 'https://api.mtn.ci'),
    ],
];
```

---

## 🚀 Lancement des Projets

### Backend Laravel

```bash
# Terminal 1 : Serveur Laravel
cd prosartisan_backend
php artisan serve

# Terminal 2 : Vite (Hot reload pour React)
npm run dev

# Terminal 3 : Queue Worker
php artisan queue:work

# Terminal 4 : Scheduler
php artisan schedule:work

# Terminal 5 : Redis
redis-server
```

### Frontend Mobile Flutter

```bash
cd prosartisan_mobile

# Lancer sur émulateur Android
flutter run

# Lancer sur simulateur iOS
flutter run -d ios

# Lancer sur un appareil physique
flutter devices
flutter run -d <device-id>

# Build pour production
flutter build apk --release  # Android
flutter build ios --release  # iOS
```

### Accès aux Applications

- **API Backend** : http://localhost:8000/api/v1
- **Back-office Web** : http://localhost:8000/backoffice
- **Documentation API** : http://localhost:8000/api/documentation
- **Mobile App** : Émulateur/Simulateur

---

## 📂 Architecture et Structure

### Structure Backend (DDD)

```
prosartisan_backend/
├── app/
│   ├── Domain/                 # Couche Domaine (Logique Métier)
│   │   ├── Identity/           # Bounded Context Identités
│   │   ├── Marketplace/        # BC Marketplace
│   │   ├── Financial/          # BC Transactions
│   │   ├── Worksite/           # BC Chantiers
│   │   ├── Reputation/         # BC Score N'Zassa
│   │   └── Dispute/            # BC Litiges
│   │
│   ├── Application/            # Couche Application (Use Cases)
│   │   ├── UseCases/
│   │   ├── DTOs/
│   │   └── Handlers/
│   │
│   ├── Infrastructure/         # Couche Infrastructure
│   │   ├── Repositories/
│   │   ├── Services/
│   │   └── Providers/
│   │
│   └── Http/                   # Couche Présentation
│       ├── Controllers/
│       │   ├── Api/V1/        # API Mobile
│       │   └── Backoffice/    # Back-office
│       ├── Resources/
│       └── Requests/
│
├── resources/
│   └── js/                     # React + Inertia
│       ├── Pages/
│       ├── Components/
│       ├── Layouts/
│       └── Utils/
│
└── database/
    ├── migrations/
    ├── seeders/
    └── factories/
```

### Structure Frontend Mobile (Clean Architecture)

```
prosartisan_mobile/
├── lib/
│   ├── core/                   # Configuration & Services
│   │   ├── config/
│   │   ├── routes/
│   │   ├── services/
│   │   └── utils/
│   │
│   ├── features/               # Features par Bounded Context
│   │   ├── auth/
│   │   │   ├── data/          # Models, Repositories, DataSources
│   │   │   ├── domain/        # Entities, Repositories, UseCases
│   │   │   └── presentation/  # Controllers, Pages, Widgets
│   │   │
│   │   ├── marketplace/
│   │   ├── mission/
│   │   ├── payment/
│   │   ├── worksite/
│   │   └── reputation/
│   │
│   └── shared/                 # Code partagé
│       ├── widgets/
│       ├── models/
│       └── controllers/
│
├── assets/
│   ├── images/
│   ├── icons/
│   └── fonts/
│
└── test/
    ├── unit/
    ├── widget/
    └── integration/
```

---

## 🔐 Sécurité & Bonnes Pratiques

### Backend

```bash
# Générer des clés JWT
php artisan jwt:secret

# Configurer les CORS
php artisan vendor:publish --tag="cors"

# Activer le rate limiting
php artisan route:cache
```

### Mobile

```dart
// Ne JAMAIS commit les clés API dans le code
// Utiliser des variables d'environnement ou des fichiers ignorés par git

// .gitignore
lib/core/config/api_keys.dart
android/app/google-services.json
ios/Runner/GoogleService-Info.plist
```

---

## 🧪 Tests

### Backend

```bash
# Tests unitaires
php artisan test --testsuite=Unit

# Tests d'intégration
php artisan test --testsuite=Feature

# Avec couverture
php artisan test --coverage
```

### Mobile

```bash
# Tests unitaires
flutter test

# Tests de widgets
flutter test test/widget/

# Tests d'intégration
flutter test test/integration/

# Tests avec couverture
flutter test --coverage
```

---

## 📚 Documentation Complémentaire

- **API Documentation** : Voir `/docs/api/`
- **Architecture DDD** : Voir `/docs/architecture/ddd.md`
- **Guide Utilisateur** : Voir `/docs/user_guides/`
- **Déploiement** : Voir `/docs/deployment/`

---

## 🆘 Dépannage

### Erreur "Class not found"
```bash
composer dump-autoload
php artisan config:clear
php artisan cache:clear
```

### Erreur PostGIS
```bash
# Vérifier l'installation
psql -U postgres -c "SELECT PostGIS_version();"
```

### Erreur Flutter pub get
```bash
flutter clean
flutter pub get
```

### Erreur Google Maps (Mobile)
```bash
# Vérifier que la clé API est bien configurée
# Activer les APIs nécessaires dans Google Cloud Console :
# - Maps SDK for Android
# - Maps SDK for iOS
# - Places API
# - Geocoding API
```

---

## 👥 Contributeurs

- **Équipe Backend** : [Noms]
- **Équipe Mobile** : [Noms]
- **Équipe DevOps** : [Noms]

---

## 📄 Licence

Ce projet est la propriété de ProsArtisan. Tous droits réservés.

---

**Version** : 1.0.0  
**Dernière mise à jour** : Janvier 2026