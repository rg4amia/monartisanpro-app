# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**ProsArtisan** — marketplace artisanal pour la Côte d'Ivoire. Deux sous-projets :

- `backend-proartisan/` — Laravel 12 API (PHP 8.2+, MySQL 5.7.39)
- `frontend_flutter/` — Flutter 3.x mobile app (Android-first)

Déploiement production : Hostinger shared hosting via GitHub Actions (`.github/workflows/backend-ci.yml`).

---

## Backend (`backend-proartisan/`)

### Commandes essentielles

```bash
cd backend-proartisan

# Développement (serveur + queue + logs + assets en parallèle)
composer dev

# Reset complet de la base de données + seeds
php artisan migrate:fresh --seed

# Tests (Pest 4)
./vendor/bin/pest                       # tous les tests
./vendor/bin/pest tests/Feature/FullMissionWorkflowTest.php  # un fichier
./vendor/bin/pest --filter="nom du test"  # filtre par nom

# Linter (Laravel Pint)
./vendor/bin/pint

# Démarrage serveur seul
php artisan serve --port=8000
```

### Architecture

**Service Layer** : toute la logique métier est dans `app/Services/` — jamais dans les controllers. Les controllers font uniquement la validation + dispatch + réponse HTTP.

**Couches** :

```
routes/api.php
  → app/Http/Controllers/V1/
      → app/Http/Requests/        (validation)
      → app/Services/             (logique métier)
          → app/Models/           (Eloquent, casts JSON natifs)
      → app/Http/Resources/       (transformation camelCase)
```

**Services clés** :

- `GeoService` — floutage GPS artisan (~50m), `ST_Distance_Sphere`, vérification J-Code
- `DevisService` — fragmentation séquestre (ratio immuable à l'acceptation)
- `JalonService` — cycle OTP → libération `wallet_mo`
- `JCodeService` — tokens `PA-XXXX`, QR + USSD, vérification GPS fournisseur
- `WalletService` — gestion `wallet_materiaux` / `wallet_mo`
- `ScoreService` — calcul ProsArtisan (4 composantes pondérées)

**Constantes métier** : `config/prosartisan.php` — seuils GPS, TTL OTP, weights ProsArtisan, seuil Référent.

**Auth** : Sanctum Bearer tokens. Middleware `account.active` vérifie `kyc_status = 'actif'`. Routes publiques : `send-otp`, `verify-otp`, `register`, webhooks.

### MySQL 5.7 — Contraintes critiques

La base tourne sur MySQL **5.7.39** (MAMP port 8889), pas 8.0+.

```php
// Colonnes POINT — ne PAS utiliser le builder Eloquent
DB::statement('ALTER TABLE t ADD COLUMN position POINT NULL');
DB::statement('ALTER TABLE t ADD COLUMN position POINT NOT NULL');
DB::statement('ALTER TABLE t ADD SPATIAL INDEX idx_pos (position)'); // NOT NULL seulement

// Insertion d'un point
DB::statement('UPDATE users SET position = POINT(?, ?) WHERE id = ?', [$lng, $lat, $id]);

// Requête spatiale (lng en premier dans POINT)
ST_Distance_Sphere(position, POINT(:lng, :lat))

// TIMESTAMP NOT NULL sans DEFAULT → erreur en strict mode
$table->dateTime('expires_at');  // ✅ pas timestamp()
```

**Jamais** :

- `POINT SRID 4326` dans les migrations (MySQL 8.0+ seulement)
- `DB::raw("ST_SRID(...)")` (inutile en 5.7)
- `FLOAT`/`DOUBLE` pour les montants (toujours `BIGINT`)

**Format téléphone** : `+225` + 10 chiffres (regex `^\+225[0-9]{10}$`).

---

## Frontend (`frontend_flutter/`)

### Commandes essentielles

```bash
cd frontend_flutter

flutter pub get          # installer les dépendances
flutter run              # lancer sur émulateur/device
flutter test             # tests unitaires
flutter build apk        # build Android release
```

### Architecture

Pattern **GetX + Clean Architecture** :

```
lib/
  core/          # réseau (Dio), thème, services GPS/notifications, storage
  data/
    models/      # JSON-serializable (json_annotation)
    repositories/# accès API/local (Hive)
  modules/       # feature modules (auth, missions, devis, jcode, litige…)
    <feature>/
      bindings/  # injection de dépendances GetX
      controllers/
      views/
  shared/widgets/
```

**Chaque module** = bindings + controller(s) + views. Navigation via `GetX` named routes dans `app/routes/`.

**Données offline** : Hive pour le cache local (faible connectivité). `flutter_secure_storage` pour le token Sanctum.

**Maps** : Yandex Maps (`yandex_maps_mapkit`) — meilleur support Afrique que Google Maps.

**Base URL API** : `http://localhost:8000/api/v1` (dev) → variable d'env pour prod.

---

## Règles d'or — ne jamais contourner

1. **KYC** : `kyc_status = 'actif'` obligatoire avant toute transaction
2. **Ratio séquestre** : fixé à l'acceptation du devis, **immuable**
3. **GPS J-Code** : > 100 m → blocage automatique, aucune exception dans le code
4. **OTP jalons** : libération de fonds impossible sans OTP validé
5. **Seuil Référent** : missions > 2 000 000 FCFA → validation physique obligatoire
6. **Floutage GPS** : ne jamais retourner la position exacte d'un artisan au client
