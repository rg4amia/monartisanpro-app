# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**ProsArtisan** — marketplace artisanal pour la Côte d'Ivoire. Deux sous-projets :

- `backend-proartisan/` — Laravel 12 API (PHP 8.2+, MySQL 5.7.39)
- `frontend_flutter/` — Flutter 3.x mobile app (Android-first)

Déploiement production : Hostinger shared hosting via GitHub Actions (`.github/workflows/backend-ci.yml`).

---

## Backend (`backend-proartisan/`)

### Commandes essentielles Backend

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

# Tests de composants front du backoffice (Vitest + Testing Library)
npm test                                # resources/js/**/*.test.tsx
npm run test:watch

# Linter (Laravel Pint)
./vendor/bin/pint

# Démarrage serveur seul
php artisan serve --port=8000

# Backoffice — filets de sécurité opérationnels
php artisan admin:full-access [email]   # restaure l'accès total d'un/tous les admins
php artisan admin:health-check [--force] # contrôle santé + alerte Telegram
```

### Architecture Backend

**Service Layer** : toute la logique métier est dans `app/Services/` — jamais dans les controllers. Les controllers font uniquement la validation + dispatch + réponse HTTP.

**Couches** :

```text
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
- `ScoreService` — calcul du Score ProsArtisan (échelle **0–1000**, 4 piliers pondérés : Fiabilité 400 / Intégrité 300 / Qualité 200 / Réactivité 100 + ledger `score_ledger_entries`)
- `MicroCreditService` — éligibilité (`score_prosartisan >= credit_threshold`) et calcul du plafond de crédit

**Services backoffice** (`app/Services/Admin/`) :

- `AdminPanelData` — props Inertia par onglet (remplace l'ancien god-method `renderPage`)
- `AdminActivityLogger` — écriture append-only du journal d'audit `admin_activity_logs` (best-effort)
- `AdminLoginThrottle` — limitation des tentatives de connexion admin (5 / 60 s par identifiant + IP)
- `AdminPermissionService` — capacités fines `admin.*` (table `admin_permission_user`), super admins protégés
- `AdminExportService` — exports CSV en streaming (BOM UTF-8, `sep=;`, `->lazy()`)
- `AdminGdprService` — vue des données personnelles + anonymisation tracée (RGPD)
- `AdminObservabilityService` — instantané de santé (jobs KO, webhooks paiement, fraude GPS, seuil Référent)
- `AdminDashboardCache` / `TelegramAlertService` — cache des KPI + alertes d'observabilité

**Constantes métier** : `config/prosartisan.php` — seuils GPS, TTL OTP, `score_prosartisan` (poids des piliers, `credit_threshold` = 700, `excellence_threshold` = 800, `golden_marker_threshold` = 700), seuil Référent, `super_admins` (emails à accès total permanent, env `SUPER_ADMIN_EMAILS`). Toute logique de seuil de score doit lire la config, jamais une valeur en dur.

**Auth** : Sanctum Bearer tokens (API). Middleware `account.active` vérifie `kyc_status = 'actif'`. Routes publiques : `send-otp`, `verify-otp`, `register`, webhooks. Le **backoffice** (`/admin/*`, Inertia + session web) est gardé par `admin.only` (`role='admin'`) puis, route par route, par le middleware `can:admin.<capacité>` adossé aux Gates d'`AdminPermissionService`.

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

### Commandes essentielles Frontend

```bash
cd frontend_flutter

flutter pub get          # installer les dépendances
flutter run              # lancer sur émulateur/device
flutter test             # tests unitaires
flutter build apk        # build Android release
```

### Architecture Frontend

Pattern **GetX + Clean Architecture** :

```text
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

## Backoffice admin (Inertia 2 + React 19 + TS)

`backend-proartisan/resources/js/pages/admin/` — console unique en onglets (`console.tsx` + `shared/AdminShell.tsx`), un panneau par onglet dans `panels/`, hooks dans `hooks/` (`useServerTable`, `useRowSelection`), primitives partagées dans `shared/` (`ConfirmDialog`, `permissions`, `loading`, `ExportButton`, `BulkActionBar`).

- **Permissions front** : la prop partagée `auth.permissions` (`['*']` = accès total) pilote l'affichage des onglets et des actions ; le backend reste seul juge (`can:` middleware).
- **Listes** : chargées page par page via `useServerTable` (rechargement partiel Inertia `only`), filtres persistés en `localStorage`.
- **Confirmations destructives** : toujours via `useConfirm()` (modale accessible : `role="dialog"`, focus, Échap), jamais `window.confirm`/`prompt`.
- **Tests** : Vitest + Testing Library, fichiers `*.test.tsx` co-localisés (exclus du glob Inertia dans `app.tsx`/`ssr.tsx`).

---

## Règles d'or — ne jamais contourner

1. **KYC** : `kyc_status = 'actif'` obligatoire avant toute transaction
2. **Ratio séquestre** : fixé à l'acceptation du devis, **immuable**
3. **GPS J-Code** : > 100 m → blocage automatique, aucune exception dans le code
4. **OTP jalons** : libération de fonds impossible sans OTP validé
5. **Seuil Référent** : missions > 2 000 000 FCFA → validation physique obligatoire
6. **Floutage GPS** : ne jamais retourner la position exacte d'un artisan au client
7. **Avenants de devis** : l'acceptation et le paiement d'un avenant réajustent le séquestre de façon incrémentale, créditent les portefeuilles de l'artisan et créent les jalons additionnels sans réinitialiser ou perturber le statut de la mission.
8. **Validation hors-ligne (USSD/SMS)** : Les requêtes hors-ligne de prise en charge et de livraison livreur doivent valider de façon identique les codes de retrait (`RET-ID`) et de réception (`REC-ID`) et rejeter tout appelant n'ayant pas le rôle `livreur` ou `admin`.
9. **Ledger Financier Immuable** : Le solde des portefeuilles utilisateurs (`wallet_materiaux` et `wallet_mo`) doit être calculé dynamiquement par la somme des crédits et débits de la table `wallet_transactions`. Toute affectation directe en base de données doit être supplantée par cette somme dynamique.
10. **Évaluation Multi-Acteurs** : Les clients évaluent distinctement l'artisan, le livreur et le fournisseur pour chaque mission terminée ou commande livrée, avec journalisation dans `score_ledger_entries`.
11. **Gestion du Consentement des Cookies Web** : Le front office web propose un bandeau et une modale de gestion des cookies (Essentiels, Analytiques, Préférences) persistés localement avec lien permanent au footer.
12. **Acceptation des CGU & Confidentialité** : Validation obligatoire dès l'inscription avec horodatage en base de données et accès permanent depuis chaque espace et le footer web.
13. **Robustesse et Navigation de Notation** : Le bouton de notation d'un artisan est directement accessible sur la carte de mission terminée (`Routes.rating`). Le backend et l'application mobile traitent les critères d'évaluation de manière résiliente avec conversion de types sécurisée et fallback automatique.
14. **Maturité et Excellence du Score ProsArtisan** : Le Score ProsArtisan (0-1000) applique un facteur de maturité progressive basé sur 10 missions minimum ($F_{\text{volume}} = \min(1.0, n/10)$) et exige au moins 3 critères avec 5 étoiles ($\ge 4.8/5$) pour dépasser 800 points et avoisiner 1000 points.
15. **Seuils du Score ProsArtisan (échelle 0-1000)** : L'accès au micro-crédit d'urgence exige `score_prosartisan >= 700` (`config('prosartisan.score_prosartisan.credit_threshold')`) ; le « marqueur doré » (artisan prioritaire) s'applique à partir de 700 ; les scores d'excellence commencent à 800. Le plafond de micro-crédit vaut `50 000 + (score - 700) × 1 500` FCFA (500 000 FCFA à 1000). Ces seuils sont lus depuis `config/prosartisan.php` côté backend et depuis `kMicroCreditScoreThreshold` côté mobile — jamais l'ancienne échelle 0-100 ni le seuil `70`.
16. **Permissions fines du backoffice** : `/admin/*` requiert `role='admin'` (`admin.only`) **puis** la capacité fine `admin.<x>` de la route (`can:` middleware + Gates d'`AdminPermissionService`, table pivot `admin_permission_user`). Un admin sans capacité affectée — ou porteur de `admin.full-access` — dispose de l'accès total. Les **super admins protégés** (`config('prosartisan.super_admins')`, défaut `admin@prosartisan.ci`) ont un accès total **inconditionnel** qui court-circuite la table pivot et ne peut **jamais** être restreint depuis l'UI ou l'endpoint `POST /admin/admins/{user}/permissions`. Secours : `php artisan admin:full-access [email]`.
17. **Journal d'audit admin** : toute action sensible du backoffice (revue KYC uni/groupée, arbitrage de litige, revue fournisseur/CNMCI, gel de score, création/modification/suppression/suspension de compte, changement de droits admin, modification de paramètres/IA/taxonomie/code promo, export CSV, anonymisation RGPD, usurpation de session, connexions/déconnexions admin) est journalisée en **append-only** dans `admin_activity_logs` (acteur, IP, user-agent, type/id/libellé du sujet, contexte JSON, horodatage). L'écriture est *best-effort* : son échec ne bloque jamais l'action métier.
18. **Throttle de connexion admin** : `/admin/login` et `/admin/login/verify-2fa` sont limités à **5 tentatives / 60 s** par (identifiant + IP) → HTTP 429. Chaque échec (mot de passe, rôle refusé, 2FA invalide) est audité ; le compteur est remis à zéro à la connexion réussie.
19. **Listes backoffice paginées côté serveur** : les grandes listes (`users`, `transactions`, `missions`, `litiges`, `evaluations`, `kyc`, `audit-logs`) chargent **une page à la fois** via rechargement partiel Inertia (`router.get(path, params, { only: [...] })`) ; les agrégats et KPI sont calculés **indépendamment de la page courante**. Les KPI du dashboard passent par `AdminDashboardCache` (TTL court) invalidé par des observers sur les modèles financiers.
20. **Exports CSV backoffice** : `AdminExportService` — streaming synchrone (`response()->streamDownload` + `fputcsv` + `->lazy()`), BOM UTF-8 + `sep=;` (Excel FR), ressources `users/transactions/missions/evaluations/litiges`, filtres alignés sur la liste, scopes Eloquent respectés (soft-delete), export audité (`export.generated`). Capacité `admin.exports`.
21. **Actions groupées backoffice** : revue KYC et changement de statut de compte **en lot** (max 100). L'administrateur qui agit **ne peut jamais être affecté par le lot** ; un élément en échec n'interrompt pas le traitement ; une ligne d'audit récapitulative accompagne les lignes individuelles.
22. **RGPD — droit d'accès & effacement** : `AdminGdprService` expose la **vue consolidée des données personnelles** d'un utilisateur (identité, KYC, position, consentement CGU, empreinte plateforme, traçabilité) + un **export JSON de portabilité**, et l'**anonymisation irréversible et tracée** (`anonymized_at` / `anonymized_by`) : expurge nom, e-mail, téléphone, numéro de paiement, empreinte appareil, données CNMCI et position GPS ; supprime les pièces KYC et notifications ; révoque les jetons ; passe le compte en `suspendu`. **La ligne `users` est conservée** pour l'intégrité des écritures financières et du journal d'audit. Refuse l'auto-cible et le second passage. Capacités `admin.rgpd.view` / `admin.rgpd.manage`.
23. **Observabilité & alertes** : `/admin/observability` agrège 4 signaux critiques — jobs `failed_jobs`, transactions `echoue` (webhooks paiement KO), tentatives de fraude GPS J-Code (`score_ledger_entries.event_type = 'fraude_gps_tentative'`), missions bloquées au seuil Référent. La commande planifiée `admin:health-check` (toutes les 15 min) envoie une alerte **Telegram** (`TELEGRAM_BOT_TOKEN` / `TELEGRAM_ALERT_CHAT_ID`) dès qu'un signal est non nul. Les actions `queue:retry` / `queue:flush` sont gated `admin.observability.manage` et auditées.
24. **Usurpation de session (super admin)** : `POST /admin/users/{user}/impersonate` (capacité `admin.users.impersonate`) bascule la session web sur un utilisateur **non-admin** ; `session('impersonator_id')` conserve l'identité de l'admin d'origine et un bandeau permanent (rendu Blade) permet le retour via `POST /admin/stop-impersonating` (hors `admin.only`, donc accessible au compte usurpé). Refus : sa propre cible, un autre administrateur, un compte anonymisé ou supprimé. Le début **et** la fin sont journalisés.
