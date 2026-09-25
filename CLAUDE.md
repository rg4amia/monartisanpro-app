# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**ProsArtisan** — marketplace artisanal pour la Côte d'Ivoire. Trois sous-projets :

- `backend-proartisan/` — Laravel 12 API + backoffice Inertia (PHP 8.3, MariaDB 11.8 en production)
- `frontend_flutter/` — Flutter 3.x mobile app (Android-first)
- `vitrine-nextjs/` — site vitrine public (Next.js 16, React 19, export statique)

Déploiement production : Hostinger shared hosting via GitHub Actions (`.github/workflows/backend-ci.yml`). **`new-dev-inz` est l'unique branche principale** (branche par défaut du dépôt) : elle seule déclenche les tests et la mise en production ; `new-develop` n'est conservée qu'en archive et ne déclenche plus aucun workflow. Le serveur exécute `git reset --hard` sur la branche déclenchante : le job `deploy` refuse donc toute autre branche, y compris en `workflow_dispatch`. Le job `tests` (suite Pest sur SQLite) **conditionne le déploiement** : aucun push ne part en production si un test échoue. Le job `tests-mariadb` rejoue la même suite face à MariaDB 11.8 (moteur de production) et **bloque aussi le déploiement** : SQLite masque des défauts qui ne cassent qu'en production (tailles de colonnes ignorées, ENUM `NOT NULL` rempli implicitement, identifiant de colonne inconnu lu comme chaîne littérale, fonctions spatiales absentes). Dans les tests, toute colonne `position` s'écrit via `Tests\Support\Geo::point()`. **File d'attente** : la production tourne en `QUEUE_CONNECTION=sync` (vérifié le 24/09/2026) — les jobs (`PaySupplierJob`…) s'exécutent immédiatement, aucun worker `queue:work` n'est requis ni planifié ; un traitement lent se diffère avec `dispatchAfterResponse`.

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
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=<port> DB_DATABASE=prosartisan_test DB_USERNAME=root DB_PASSWORD=<mdp> ./vendor/bin/pest --parallel  # même suite sur MariaDB (comme le job CI tests-mariadb)

# Tests de composants front du backoffice (Vitest + Testing Library)
npm test                                # resources/js/**/*.test.tsx
npm run test:watch

# Linter (Laravel Pint)
./vendor/bin/pint

# Démarrage serveur seul
php artisan serve --port=8000

# Backoffice & Watchdog — filets de sécurité opérationnels
php artisan admin:full-access [email]        # restaure l'accès total d'un/tous les admins
php artisan admin:health-check [--force]      # contrôle santé + alerte Telegram
php artisan prosartisan:driver-watchdog       # réassignation automatique des courses inactives (> 15 min)
php artisan prosartisan:reconcile-treasury    # audit en lecture seule : séquestre missions/commandes, soldes stockés vs ledger (Règle d'or 9)
php artisan prosartisan:reconcile-hybrid-jalons [--fix]  # jalons hybrides payés non financés, doubles paiements, déficits de séquestre par mission
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
- `JalonService` — cycle OTP → libération `wallet_mo`, contrôle physique Référent (> 2M FCFA)
- `JCodeService` — tokens `PA-XXXX`, QR + USSD, vérification GPS fournisseur
- `OrderService` — e-commerce matériaux, split-cart multi-quincailleries (`order_group_id`), télémétrie livreur par lot, watchdog
- `DeliveryPricingService` — calcul tarifaire dynamique (OSRM, surge pricing, classe véhicule)
- `WalletService` — gestion `wallet_materiaux` / `wallet_mo` (Event Sourcing strict) ; séquestre hybride cloisonné par mission (`releaseJalon`, `fundHybridJalon`, `getMissionEscrowBalance`)
- `ScoreService` — calcul du Score ProsArtisan (échelle **0–1000**, 4 piliers pondérés : Fiabilité 400 / Intégrité 300 / Qualité 200 / Réactivité 100 + ledger `score_ledger_entries`)
- `MicroCreditService` — éligibilité (score recalculé depuis le ledger `>= credit_threshold`) et calcul du plafond de crédit sur cette même valeur
- `PaymentService` — initiation (acompte de devis, jalon hybride) et suivi des paiements Wave / Orange Money / virement bancaire, plafond Mobile Money, simulateur local ; refus métier via `PaymentException`
- `GoogleMapsService` — distance/durée routières via **Yandex** Distance Matrix, repli Haversine (nom historique conservé, aucun appel Google)
- `BankTransferSettingsService` — coordonnées de virement bancaire (banque, titulaire, IBAN) saisies dans le backoffice ; `details()` renvoie null tant qu'elles manquent (virement alors refusé)
- `AppAccessService` — blocage d'accès à l'app mobile par espace (`none`/`new`/`old`/`all`/`hidden`) et messages de maintenance, table `settings` (`GET /settings/app-access` public, `PUT /admin/settings/app-access` gated `admin.settings.manage`)
- `AddressService` — carnet d'adresses de livraison du client (défaut automatique à la première adresse, unicité du défaut, promotion à la suppression, snapshot immuable sur `orders`)

**Services backoffice** (`app/Services/Admin/`) :

- `AdminPanelData` — props Inertia par onglet (remplace l'ancien god-method `renderPage`)
- `AdminActivityLogger` — écriture append-only du journal d'audit `admin_activity_logs` (best-effort)
- `AdminLoginThrottle` — limitation des tentatives de connexion admin (5 / 60 s par identifiant + IP)
- `AdminPermissionService` — capacités fines `admin.*` (table `admin_permission_user`), super admins protégés
- `AdminExportService` — exports CSV en streaming (BOM UTF-8, `sep=;`, `->lazy()`)
- `AdminGdprService` — vue des données personnelles + anonymisation tracée (RGPD)
- `FraudAlertAdminService` — arbitrage des alertes de fraude (gel, levée avec respect du seuil Référent, confirmation avec sanction de score, classement), audité
- `AdminObservabilityService` — instantané de santé (jobs KO, webhooks paiement, fraude GPS, seuil Référent)
- `AdminDashboardCache` / `TelegramAlertService` — cache des KPI + alertes d'observabilité

**Constantes métier** : `config/prosartisan.php` — seuils GPS, TTL OTP, `score_prosartisan` (poids des piliers, `credit_threshold` = 700, `excellence_threshold` = 800, `golden_marker_threshold` = 700), seuil Référent, `super_admins` (emails à accès total permanent, env `SUPER_ADMIN_EMAILS`). Toute logique de seuil de score doit lire la config, jamais une valeur en dur.

**Auth** : Sanctum Bearer tokens (API). Middleware `account.active` vérifie `kyc_status = 'actif'`. Routes publiques : `send-otp`, `verify-otp`, `register`, webhooks. Le **backoffice** (`/admin/*`, Inertia + session web) est gardé par `admin.only` (`role='admin'`) puis, route par route, par le middleware `can:admin.<capacité>` adossé aux Gates d'`AdminPermissionService`.

### Base de données — MariaDB en production : contraintes critiques

Trois moteurs coexistent, et le code doit rester portable entre eux :

| Environnement | Moteur | Source |
| --- | --- | --- |
| **Production** (Hostinger) | **MariaDB 11.8.9** | `select version()` sur le serveur (vérifié le 23/09/2026) |
| Local (WAMP) | MySQL 8.4 | `select version()` en local |
| Tests Pest | SQLite en mémoire | `phpunit.xml` |

N'utiliser que le sous-ensemble SQL commun. SQLite n'exécute pas les fonctions spatiales : une syntaxe spatiale ou JSON propre à MySQL 8 passe les tests et le local, puis casse **uniquement en production**. Toute requête de ce type se vérifie contre MariaDB. La mention historique « MySQL 5.7.39 (MAMP) » de ce fichier était erronée pour la production.

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

- `POINT SRID 4326` dans les migrations (syntaxe MySQL 8 : MariaDB la refuse, elle utilise `REF_SYSTEM_ID`)
- `ST_SRID(POINT(...), 4326)` à deux arguments (mutateur MySQL 8, absent de MariaDB où `ST_SRID(g)` ne fait que lire) : toujours `POINT(lng, lat)` nu
- Garde de non-régression pour ces deux syntaxes : `tests/Unit/MariaDbSpatialSyntaxGuardTest.php` (relit les sources, SQLite n'exécutant pas les fonctions spatiales)
- `FLOAT`/`DOUBLE` pour les montants (toujours `BIGINT`)

**Format téléphone** : `+225` + 10 chiffres (regex `^\+225[0-9]{10}$`).

---

## Frontend (`frontend_flutter/`)

### Commandes essentielles Frontend

```bash
cd frontend_flutter

flutter pub get          # installer les dépendances
flutter run              # lancer sur émulateur/device
flutter test             # tests unitaires et widget (tests `integration` ignorés, cf. dart_test.yaml)
flutter test --tags integration --run-skipped  # tests d'intégration, backend Herd démarré
flutter build apk        # build Android release → build/app/outputs/flutter-apk/ (emplacement de référence des APK, jamais la racine du dépôt)
```

### Architecture Frontend

Pattern **GetX + Clean Architecture** :

```text
lib/
  core/          # réseau (Dio), thème, services GPS/notifications, storage
  data/
    models/      # JSON-serializable (json_annotation)
    repositories/# accès API/local (Hive)
  modules/       # feature modules (auth, missions, devis, jcode, litige, adresses…)
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

**Tests de contrôleurs GetX asynchrones** : tout contrôleur combinant un appel réseau (Dio) et/ou `Get.snackbar` doit être testé via le harnais partagé `test/helpers/getx_snackbar_harness.dart` (`runControllerAction(tester, action)`), jamais par un `await` direct sous `testWidgets` — voir Règle d'Or 62 du PRD pour le détail des deux pièges (blocage `FakeAsync`/Dio, `Timer` de snackbar fuyant vers un test ultérieur) qu'il neutralise.

---

## Backoffice admin (Inertia 2 + React 19 + TS)

`backend-proartisan/resources/js/pages/admin/` — console unique en onglets (`console.tsx` + `shared/AdminShell.tsx`), un panneau par onglet dans `panels/`, hooks dans `hooks/` (`useServerTable`, `useRowSelection`), primitives partagées dans `shared/` (`ConfirmDialog`, `permissions`, `loading`, `ExportButton`, `BulkActionBar`).

- **Permissions front** : la prop partagée `auth.permissions` (`['*']` = accès total) pilote l'affichage des onglets et des actions ; le backend reste seul juge (`can:` middleware).
- **Listes** : chargées page par page via `useServerTable` (rechargement partiel Inertia `only`), filtres persistés en `localStorage`.
- **Confirmations destructives** : toujours via `useConfirm()` (modale accessible : `role="dialog"`, focus, Échap), jamais `window.confirm`/`prompt`/`alert` — aucune exception résiduelle (`DeliveriesTrackingSection` et motif de rejet KYC de `console.tsx` migrés ; un motif obligatoire passe par `promptLabel` + `promptMinLength`).
- **Tests** : Vitest + Testing Library, fichiers `*.test.tsx` co-localisés (exclus du glob Inertia dans `app.tsx`/`ssr.tsx`) — **couverture complète** de `panels/` : tout composant appelant `router.post/put/delete` ou `useForm` depuis `@inertiajs/react` doit y être mocké (voir les fichiers `*.test.tsx` existants pour le patron), et toute modale de confirmation testée via `screen.findByRole('dialog')` plutôt qu'un mock de `window.confirm`.

---

## Vitrine (`vitrine-nextjs/`)

### Commandes essentielles Vitrine

```bash
cd vitrine-nextjs

npm run dev       # serveur de développement Next.js
npm run build     # export statique (output: "export")
npm test          # tests unitaires (Vitest + Testing Library)
npm run test:watch
```

### Architecture Vitrine

App Router (`src/app/`), composants partagés dans `src/components/`, accès API dans `src/lib/api.ts` (repli automatique sur un contenu par défaut si l'API backend est injoignable — concerne uniquement le contenu marketing statique, jamais des données financières ou personnelles réelles, cf. Règle d'Or 53 du PRD).

- **Consentement cookies (RGPD, Règle d'Or 20)** : `CookieConsent.tsx` (bandeau + modale de personnalisation, persistance `localStorage`) ; `GoogleAnalytics.tsx` ne charge `gtag.js` que si le consentement analytique est explicitement accordé et réagit en direct à l'événement `prosartisan-cookie-consent`.
- **Espace Fournisseur** : authentification par OTP (`api.supplierSendOtp`/`supplierVerifyOtp`, normalisation automatique des numéros locaux vers `+225`), session via `localStorage` (`supplier_token`), redirection automatique vers `/supplier/login` sur 401.
- **Tests** : Vitest + Testing Library (config `vitest.config.ts`, setup `src/test/setup.ts`), fichiers `*.test.ts(x)` co-localisés.

---

## Règles d'or — ne jamais contourner

1. **KYC** : `kyc_status = 'actif'` obligatoire avant toute transaction
2. **Ratio séquestre** : fixé à l'acceptation du devis, **immuable**
3. **GPS J-Code** : > 100 m → blocage automatique, aucune exception dans le code
4. **OTP jalons** : libération de fonds impossible sans OTP validé
5. **Seuil Référent** : missions > 2 000 000 FCFA → validation physique obligatoire. Toute voie de paiement d'un jalon applique ce seuil : validation OTP, libération automatique à 72 h (`forceRelease`) **et levée d'une alerte de fraude par l'admin** (`FraudAlertAdminService::release`) — un jalon suspendu (`valide_suspendu`) repasse à `valide` et reste impayé jusqu'à la visite du Référent ; la levée est refusée tant que les fonds de la mission sont gelés par un litige.
6. **Floutage GPS** : ne jamais retourner la position exacte d'un artisan au client
7. **Avenants de devis** : l'acceptation et le paiement d'un avenant réajustent le séquestre de façon incrémentale, créditent les portefeuilles de l'artisan et créent les jalons additionnels sans réinitialiser ou perturber le statut de la mission.
8. **Validation hors-ligne (USSD/SMS)** : Les requêtes hors-ligne de prise en charge et de livraison livreur doivent valider de façon identique les codes de retrait (`RET-ID`) et de réception (`REC-ID`) et rejeter tout appelant n'ayant pas le rôle `livreur` ou `admin`. **La passerelle appelante doit en outre s'authentifier** (`gateway.verified` / `VerifyGatewayRequest`) : le rôle est déduit d'un numéro de téléphone posté dans la requête, donnée que l'appelant choisit librement — sans authentification de la passerelle, n'importe qui confirmerait une livraison et libérerait les fonds. Trois voies d'authentification, par ordre de force : **signature HMAC-SHA256 de l'opérateur** (`X-Webhook-Signature: sha256=…` sur le corps brut, secret `SMSPRO_WEBHOOK_SECRET`) — la seule qui couvre le *contenu* du message ; sinon secret partagé `USSD_GATEWAY_SECRET` en en-tête `X-Gateway-Secret` ; sinon en paramètre `?gateway_secret=`. Une signature présente mais invalide est refusée **sans repli** sur le secret partagé. Le contrôle est *fail closed* : aucun secret configuré → HTTP 503, jamais d'accès libre.
9. **Ledger Financier Immuable** : Le solde des portefeuilles utilisateurs (`wallet_materiaux` et `wallet_mo`) doit être calculé dynamiquement par la somme des crédits et débits de la table `wallet_transactions`. Toute affectation directe en base de données doit être supplantée par cette somme dynamique.
10. **Évaluation Multi-Acteurs** : Les clients évaluent distinctement l'artisan, le livreur et le fournisseur pour chaque mission terminée ou commande livrée, avec journalisation dans `score_ledger_entries`.
11. **Gestion du Consentement des Cookies Web** : Le front office web propose un bandeau et une modale de gestion des cookies (Essentiels, Analytiques, Préférences) persistés localement avec lien permanent au footer.
12. **Acceptation des CGU & Confidentialité** : Validation obligatoire dès l'inscription avec horodatage en base de données et accès permanent depuis chaque espace et le footer web.
13. **Robustesse et Navigation de Notation** : Le bouton de notation d'un artisan est directement accessible sur la carte de mission terminée (`Routes.rating`). Le backend et l'application mobile traitent les critères d'évaluation de manière résiliente avec conversion de types sécurisée et fallback automatique.
14. **Maturité et Excellence du Score ProsArtisan** : Le Score ProsArtisan (0-1000) applique un facteur de maturité progressive basé sur 10 missions minimum ($F_{\text{volume}} = \min(1.0, n/10)$) et exige au moins 3 critères avec 5 étoiles ($\ge 4.8/5$) pour dépasser 800 points et avoisiner 1000 points.
15. **Seuils du Score ProsArtisan (échelle 0-1000) & Zéro Initial Absolu** : L'inscription d'un utilisateur ne confère STRICTEMENT AUCUN point de score. Le score démarre obligatoirement à **0 sur 1000**, garanti par l'attribut Eloquent (`User::$attributes['score_prosartisan'] = 0`), la contrainte MySQL (`DEFAULT 0`) et la garde `ScoreService::recalculateFromLedger` (réinitialisation forcée à 0 si aucun jalon ni ledger n'existe). Aucun point n'est jamais crédité sans évaluation ou notation réelle. L'accès au micro-crédit d'urgence exige `score_prosartisan >= 700` (`config('prosartisan.score_prosartisan.credit_threshold')`) ; le « marqueur doré » (artisan prioritaire) s'applique à partir de 700 ; les scores d'excellence commencent à 800. Le plafond de micro-crédit vaut `50 000 + (score - 700) × 1 500` FCFA (500 000 FCFA à 1000). Ces seuils sont lus depuis `config/prosartisan.php` côté backend et depuis `kMicroCreditScoreThreshold` côté mobile — jamais l'ancienne échelle 0-100 ni le seuil `70`. Éligibilité et plafond du micro-crédit se calculent sur **le même score, recalculé depuis le ledger** au moment de la demande (`MicroCreditService::checkEligibility`), jamais sur la colonne `score_prosartisan` stockée, potentiellement obsolète.
16. **Permissions fines du backoffice** : `/admin/*` requiert `role='admin'` (`admin.only`) **puis** la capacité fine `admin.<x>` de la route (`can:` middleware + Gates d'`AdminPermissionService`, table pivot `admin_permission_user`). Un admin sans capacité affectée — ou porteur de `admin.full-access` — dispose de l'accès total. Les **super admins protégés** (`config('prosartisan.super_admins')`, défaut `admin@prosartisan.ci`) ont un accès total **inconditionnel** qui court-circuite la table pivot et ne peut **jamais** être restreint depuis l'UI ou l'endpoint `POST /admin/admins/{user}/permissions`. Secours : `php artisan admin:full-access [email]`.
17. **Journal d'audit admin** : toute action sensible du backoffice (revue KYC uni/groupée, arbitrage de litige, revue fournisseur/CNMCI, gel de score, création/modification/suppression/suspension de compte, changement de droits admin, modification de paramètres/IA/taxonomie/code promo, export CSV, anonymisation RGPD, usurpation de session, arbitrage d'alerte de fraude, cash-out quincaillerie, connexions/déconnexions admin) est journalisée en **append-only** dans `admin_activity_logs` (acteur, IP, user-agent, type/id/libellé du sujet, contexte JSON, horodatage). L'écriture est *best-effort* : son échec ne bloque jamais l'action métier.
18. **Throttle de connexion admin** : `/admin/login` et `/admin/login/verify-2fa` sont limités à **5 tentatives / 60 s** par (identifiant + IP) → HTTP 429. Chaque échec (mot de passe, rôle refusé, 2FA invalide) est audité ; le compteur est remis à zéro à la connexion réussie.
19. **Listes backoffice paginées côté serveur** : les grandes listes (`users`, `transactions`, `missions`, `litiges`, `evaluations`, `kyc`, `audit-logs`) chargent **une page à la fois** via rechargement partiel Inertia (`router.get(path, params, { only: [...] })`) ; les agrégats et KPI sont calculés **indépendamment de la page courante**. Les KPI du dashboard passent par `AdminDashboardCache` (TTL court) invalidé par des observers sur les modèles financiers. Un compteur léger réutilisé à la fois par un onglet dédié et par le tableau de bord (ex. clics WhatsApp, couverture FAQ) est calculé par une **méthode privée unique** dans `AdminPanelData` — jamais dupliqué entre `dashboard()` et l'onglet concerné.
20. **Exports CSV backoffice** : `AdminExportService` — streaming synchrone (`response()->streamDownload` + `fputcsv` + `->lazy()`), BOM UTF-8 + `sep=;` (Excel FR), ressources `users/transactions/missions/evaluations/litiges`, filtres alignés sur la liste, scopes Eloquent respectés (soft-delete), export audité (`export.generated`). Capacité `admin.exports`.
21. **Actions groupées backoffice** : revue KYC et changement de statut de compte **en lot** (max 100). L'administrateur qui agit **ne peut jamais être affecté par le lot** ; un élément en échec n'interrompt pas le traitement ; une ligne d'audit récapitulative accompagne les lignes individuelles.
22. **RGPD — droit d'accès & effacement** : `AdminGdprService` expose la **vue consolidée des données personnelles** d'un utilisateur (identité, KYC, position, consentement CGU, empreinte plateforme, traçabilité) + un **export JSON de portabilité**, et l'**anonymisation irréversible et tracée** (`anonymized_at` / `anonymized_by`) : expurge nom, e-mail, téléphone, numéro de paiement, empreinte appareil, données CNMCI et position GPS ; supprime les pièces KYC et notifications ; révoque les jetons ; passe le compte en `suspendu`. **La ligne `users` est conservée** pour l'intégrité des écritures financières et du journal d'audit. Refuse l'auto-cible et le second passage. Capacités `admin.rgpd.view` / `admin.rgpd.manage`.
23. **Observabilité & alertes** : `/admin/observability` agrège 4 signaux critiques — jobs `failed_jobs`, transactions `echoue` (webhooks paiement KO), tentatives de fraude GPS J-Code (`score_ledger_entries.event_type = 'fraude_gps_tentative'`), missions bloquées au seuil Référent. La commande planifiée `admin:health-check` (toutes les 15 min) envoie une alerte **Telegram** (`TELEGRAM_BOT_TOKEN` / `TELEGRAM_ALERT_CHAT_ID`) dès qu'un signal est non nul. Les actions `queue:retry` / `queue:flush` sont gated `admin.observability.manage` et auditées.
24. **Usurpation de session (super admin)** : `POST /admin/users/{user}/impersonate` (capacité `admin.users.impersonate`) bascule la session web sur un utilisateur **non-admin** ; `session('impersonator_id')` conserve l'identité de l'admin d'origine et un bandeau permanent (rendu Blade) permet le retour via `POST /admin/stop-impersonating` (hors `admin.only`, donc accessible au compte usurpé). Refus : sa propre cible, un autre administrateur, un compte anonymisé ou supprimé. Le début **et** la fin sont journalisés.
25. **Quotas d'utilisation de l'IA (mobile)** : `POST /api/v1/chat` et `POST /api/v1/search` exigent `auth:sanctum`. L'enforcement autoritaire est `AiMonitoringService::checkUserLimit()` : `ai_settings.ai_enabled` off → `ai_user_quotas.blocked` → limite journalière effective → limite mensuelle effective, comptées sur `ai_usage_logs` (`status_code = 200`, actions `chat`/`search`). Limite effective = **surcharge par utilisateur** (`ai_user_quotas`, `NULL` = défaut / `0` = illimité) sinon **réglage global** (`ai_settings.daily_user_limit` / `monthly_user_limit`) ; dépassement ou blocage → HTTP 429. Pilotage : backoffice onglet « Suivi & Coûts IA » (`ai_dashboard`, capacité `admin.ai.manage`), `PUT /admin/ai-dashboard/quotas/{user}` audité (`ai_quota.updated` / `ai_quota.reset`). Modèle Gemini de production : **`gemini-3.6-flash`** via `config('services.gemini.model')` (jamais `env()`, jamais `gemini-1.5`/`2.0`). Le chat ne renvoie jamais l'erreur brute Gemini à l'utilisateur (`gracefulChatDegradation()`). L'Assistant IA mobile est servi par `GET /assistant` (jeton injecté via `#token=`).
26. **Appels GPS bornés (mobile)** : tout `Geolocator.getCurrentPosition` déclare un `timeLimit` — 10 s pour les preuves géolocalisées (J-Code, Référent, jalons, litiges), 5 s pour le confort d'interface. Sans borne, un terminal qui ne fixe aucun point en haute précision laisse le `Future` en attente indéfiniment : ni le `catch` de repli ni le `finally` ne s'exécutent, et l'écran reste en chargement sans jamais afficher d'erreur. Une carte se cadre sur une position de repli connue (Abidjan) **dès sa création**, avant d'interroger le GPS qui ne fait ensuite qu'affiner ; laissée sur la caméra par défaut du SDK, elle s'ouvre au large du golfe de Guinée. `requestPermission` est borné également. Garde de non-régression : `test/core/geolocation_time_limit_test.dart`, qui relit les sources.
27. **Statuts de mission — les états FSM font foi** : filtrer `missions.status` sur `draft`, `pending_artisan_acceptance`, `pending_funding`, `funded_locked`, `in_progress`, `pending_approval`, `completed`, `disputed`, `cancelled`. Les libellés français historiques (`en_attente`, `financee`, `en_cours`, `terminee`, `litige`) ont été convertis en base par la migration FSM : un filtre resté en français **ne lève aucune erreur** et retourne silencieusement un ensemble vide. Correspondance de référence : `MissionController::index()`. Tout filtre par statut doit être couvert par un test créant des missions dans les états visés. **Tout statut affiché est libellé en français** (sorties de commande, interfaces, notifications) : la base conserve la clé technique, l'affichage passe par `MissionState::labelFor()` (identique à `missionStatusLabels` du backoffice) ou `Order::statusLabel()` — jamais la clé brute.
28. **Contrat de forme API ↔ mobile** : un tableau associatif PHP vide se sérialise en `[]` (tableau JSON) et non `{}` — caster en objet toute clé que le mobile lit comme une `Map`, sans quoi tout compte neuf déclenche `type 'List<dynamic>' is not a subtype of type 'Map<dynamic, dynamic>'`. Côté mobile, jamais de transtypage direct d'une valeur JSON (`as String`, `as Map`) : lecteurs défensifs tolérant l'absence, l'entier comme le décimal (une moyenne ronde arrive en `3`, pas `3.0`), et **un bloc indépendant par champ** — une exception sur une clé emportait la lecture de toutes les suivantes. Les tests de rendu éprouvent des charges utiles incomplètes, pas seulement la charge nominale. Lecteurs partagés : `lib/core/utils/json_readers.dart` — `readString`/`readInt`/`readDouble`/`readBool`/`readMap`/`readList`, `readMapList`, `readDataList` (enveloppe `{data: [...]}` ou tableau nu ; lève une `FormatException` plutôt que de renvoyer une liste vide, qui masquerait la panne et écraserait le cache — Règle d'or 29) et `readApiMessage` (message d'un corps d'erreur). Tout objet relu depuis Hive arrive en `Map<dynamic, dynamic>` : `readMap` l'accepte, alors que le couple `is Map ? … as Map<String, dynamic>` échoue.
29. **Ni données de démonstration, ni note par défaut** : aucun contrôleur ne préremplit une vue de valeurs inventées — indiscernables des données réelles, elles masquent toute panne de chargement. Une section sans donnée affiche une mention explicite (`SectionEmptyNote`) disant ce qui la remplira, jamais un cadre muet. Une note jamais attribuée vaut `null` et s'affiche « Non évalué » : `COALESCE(AVG(note), 5.0)` décernait 5/5 à des comptes n'ayant servi personne et rendait le tri arbitraire. Le score initial vaut obligatoirement 0 (Règle d'or 15). Les acteurs évalués passent devant les non-évalués, et `ratings_count` distingue « pas encore noté » de « mal noté ».
30. **Clé Yandex MapKit — deux emplacements distincts, les deux obligatoires** : la clé doit être fournie à la fois côté manifeste natif Android (`android/local.properties` → `yandex.mapkit.apiKey`, injecté via `manifestPlaceholders`) et côté Dart (`frontend_flutter/env.json` → `YANDEX_MAPKIT_API_KEY`, lu par `EnvConfig.yandexMapKitApiKey` et passé explicitement à `mapkit_init.initMapkit()` dans `main.dart`). Un `flutter build apk --release` lancé **sans** `--dart-define-from-file=env.json` compile une clé Dart vide : MapKit s'initialise avec une identité invalide et Yandex rejette toute requête réseau (tuiles, itinéraires OSRM) en boucle avec `Forbidden : Invalid client information` dans `adb logcat`, même si le manifeste natif et la clé côté console Yandex sont correctement configurés — la carte reste néanmoins partiellement visible (marqueurs et widgets locaux toujours rendus), ce qui masque la cause réelle. Toute commande de build/run release doit inclure `--dart-define-from-file=env.json`. Côté console Yandex (developer.tech.yandex.ru), le champ « Restriction des identifiants d'application » d'une clé MapKit Mobile SDK n'accepte que le nom de package Android (`com.prosartisan.app`), jamais une empreinte de certificat SHA-1 — un ajout de SHA-1 y est rejeté comme valeur incorrecte.
31. **Bouton WhatsApp click-to-chat & suivi des clics (front office + backoffice)** : le site vitrine affiche un bouton flottant qui ouvre une conversation WhatsApp pré-remplie (`https://api.whatsapp.com/send/?phone=...&text=...`) vers un numéro et un message entièrement paramétrables depuis le backoffice (`whatsapp_widget_enabled` / `whatsapp_widget_phone` / `whatsapp_widget_message`, stockés dans `vitrine_settings` et déjà exposés publiquement par `GET /vitrine/settings` — même magasin clé-valeur que les autres réglages de la vitrine, pas de duplication). Chaque clic est journalisé **sans aucune donnée personnelle** (page d'origine, referrer HTTP, horodatage) dans `whatsapp_click_logs` via `POST /vitrine/whatsapp-click` (public, `throttle:public`). Consultation paginée côté serveur et réglages modifiables depuis l'onglet dédié du backoffice : capacité `admin.whatsapp.manage`.
32. **FAQ « Aide et support » ciblée par rôle (mobile)** : l'écran « Aide et support », partagé par les 4 espaces mobile (client, artisan, livreur, fournisseur), affiche une FAQ pilotée depuis le backoffice (table `faqs`, capacité `admin.faq.manage`) plutôt qu'un texte figé dans le binaire de l'app — une réponse erronée ou obsolète se corrige donc sans nouvelle publication sur les stores. Chaque question cible un ou plusieurs rôles via une colonne JSON (`roles`), filtrée côté serveur avec `whereJsonContains` (`GET /faqs?role=...`, public, `throttle:public`). Le mobile met la liste en cache localement (Hive, TTL 30 min) avec repli sur le cache périmé en cas de coupure réseau. Les mêmes boutons de contact (WhatsApp, appel `tel:`, e-mail `mailto:`) que la Règle d'or 31 sont proposés en complément, en lisant les mêmes réglages publics — aucune coordonnée de contact dupliquée entre le site vitrine et l'app mobile.
33. **Module Recrutement BTP & Métiers — publication ouverte au client/fournisseur** : `recruitment_offers`/`recruitment_applications` (`BIGINT`, `position POINT` sans SRID) sont distincts des annonces carrière vitrine (`vitrine_recrutements`) et réutilisent la taxonomie `sectors`/`trades` existante plutôt qu'un champ métier en texte libre. Un client ou fournisseur au KYC déjà actif voit son offre publiée immédiatement ; sinon elle passe en `pending_review` en attente de modération admin (`admin.recruitment.manage`) — les offres admin sont toujours publiées immédiatement. La publication est activable/désactivable **indépendamment par espace** (client / fournisseur) via la table clé-valeur `recruitment_settings` (même pattern que `ai_settings`), contrôlée côté service avant toute création. Le score de matching artisan↔offre (0–100) est volontairement simplifié : proximité GPS (`ST_Distance_Sphere`, mêmes paliers que `GeoService`) + Score ProsArtisan existant normalisé sur 100 — aucune composante de disponibilité ou d'équipement fictive tant qu'aucune donnée fiable de ce type n'existe (Règle d'or 29). Commande planifiée `recruitment:expire-offers` (chaque minute) : `active` → `expired` au dépassement de `deadline_at`. Candidature par note vocale (≤ 20 s, ≤ 1 Mo, facultative) : fichier sur le disque privé, transcrite par Gemini (`GeminiService::transcribeRecruitmentVoiceNote`) juste après la réponse HTTP (`TranscribeRecruitmentVoiceNote::dispatchAfterResponse`, aucun worker requis). Anti-contournement : le numéro de l'artisan n'est jamais transmis au recruteur, donc l'audio et la transcription ne sont exposés (URL signée 15 min, route `recruitment.voice-note.file`) que si `voice_status = approved` ; une note contenant des coordonnées (`contact_detected`) est supprimée et l'artisan prévenu ; sans transcription possible (`failed`), la note est retenue, jamais servie non vérifiée ni remplacée par un texte inventé (Règle d'or 29). Séquestres de recrutement (accès aux candidatures, engagement journalier) : toute confirmation de paiement (webhook, interrogation de statut, simulateur) les active via `PaymentService::applyConfirmedPayment`, de façon idempotente ; une transaction d'engagement fige les journées qu'elle couvre (`workday_ids`) et n'en débloque jamais d'autres (plafond cumulatif, Règle d'or 36). Commission ProsArtisan réglable depuis le backoffice (`settings.commission_recruitment`, 10 % par défaut).
34. **Carnet d'adresses & géolocalisation systématique** : le client gère un carnet d'adresses de livraison multiple (`addresses`, `AddressService`, `GET/POST /addresses`, `PUT/DELETE /addresses/{id}`, `POST /addresses/{id}/default`) — la première adresse créée devient automatiquement le défaut, en définir une nouvelle désactive l'ancienne, et supprimer l'adresse par défaut promeut la plus récente restante : le carnet n'est jamais laissé sans défaut tant qu'il en compte une. Toute commande en mode `delivery` (`POST /orders`, `POST /orders/multi-store`) exige un `address_id` appartenant au client (422 sinon, ownership vérifiée serveur → 403 sur une adresse d'un tiers) ; son contenu est **figé sur la commande** à la création (`orders.recipient_name`/`recipient_phone`/`delivery_address_line`/`delivery_city`) — une édition ultérieure du carnet ne réécrit jamais une commande déjà passée. La demande de mission (`MissionRequestScreen`) récupère la position GPS réelle du client dès l'ouverture de l'écran (appel borné, repli Abidjan), jamais `(0.0, 0.0)` en attendant une action manuelle — cf. Règle d'or 26.
35. **Langue française obligatoire — communication & documentation** : toute communication adressée à l'utilisateur (messages d'erreur/validation, notifications, SMS/OTP, interface mobile/backoffice/vitrine) est rédigée en français, sans exception. La documentation produit du projet (PRD, ce fichier, Règles d'or, retours d'expérience, backlog) est elle aussi rédigée intégralement en français ; toute règle ou point d'attention nouvellement ajouté doit suivre cette convention, y compris pour documenter un composant nommé en anglais dans le code.
36. **Points de contrôle sécurité obligatoires pour toute nouvelle fonctionnalité** (détail complet et justification dans `PRD.md` § 4, section « Points de Contrôle Sécurité Obligatoires ») : un audit offensif (septembre 2026) a trouvé 10 failles, dont 5 critiques, presque toutes du même schéma — rôle vérifié, propriété de la ressource non vérifiée. Avant tout merge touchant une ressource utilisateur, un montant financier ou une route admin :
    - Vérifier explicitement `$resource->user_id`/`client_id`/`artisan_id`/`fournisseur_id === $request->user()->id` sur **tout** contrôleur qui type-hinte un modèle Eloquent (route-model-binding) — jamais seulement le rôle de l'appelant.
    - Aucune route de simulation/mock/debug sans `abort_unless(app()->environment(['local', 'testing']), 404)`.
    - Toute route API mobile `/api/v1/admin/*` reproduisant une action du backoffice web porte la **même** capacité `can:admin.xxx` que son équivalent `routes/web.php`.
    - Tout champ numérique alimentant un calcul financier est plafonné, y compris **cumulativement** sur plusieurs appels (pas seulement par appel).
    - Toute libération de fonds (`wallet_mo`/`wallet_materiaux`) a une écriture `Transaction` correspondante débitant l'escrow — jamais un ajustement de colonne suivi d'un versement sans traçabilité.
    - Une ressource désignée à un acteur précis (J-Code, devis ciblé…) n'est consommable **que** par cet acteur désigné, vérifié en base — pas seulement par un acteur du bon rôle respectant ses propres contraintes.
    - Toute décision financière (plafond, seuil, éligibilité) se juge sur la valeur établie par le serveur au moment de la décision, et le calcul qui en découle porte sur **cette même valeur** — jamais sur un montant déclaré par le client ni sur une colonne dérivée potentiellement obsolète (ex. plafond Mobile Money jugé sur le montant officiel du devis, pas sur le `montant` posté ; micro-crédit sur le score recalculé, pas sur la colonne stockée).
    - Un solde partagé (le `wallet_mo` d'un artisan porte le séquestre de toutes ses missions) n'est jamais une preuve de financement : toute libération ou tout arbitrage se juge sur la part de la ressource concernée dans le ledger (`mission_id` / `jalon_id`), et toute voie de confirmation d'un paiement (webhook, interrogation de statut, simulateur) produit le même effet sur le séquestre, de façon idempotente.
    - Toute correction de ce type s'accompagne d'un test Pest qui échoue avant correctif (reproduit l'exploitation) et passe après.
37. **Flux devis en deux étapes distinctes, chacune notifiée dans les deux sens** : (1) un client sélectionne un artisan pour sa mission → l'artisan reçoit une **demande de devis** (statut FSM mission `pending_artisan_acceptance`) qu'il accepte (`POST /missions/{mission}/accept-request`, `MissionController::acceptRequest`) ou refuse (`POST /missions/{mission}/reject-request`, `MissionController::rejectRequest`) ; le refus notifie le client (artisan non retenu, à resélectionner) et l'acceptation débloque la création du devis côté artisan. (2) Une fois le devis soumis, seul le **client** peut l'accepter ou le refuser (`POST /devis/{devis}/accept` et `/refuse`, capacités `devis.accept`/`devis.refuse`, exclusivité client posée par `PRD.md`) — un refus notifie l'artisan, une acceptation déclenche le paiement et la fragmentation du séquestre (Règle d'or 2). Ces deux étapes sont distinctes et ne doivent jamais être confondues : l'artisan n'a et n'aura jamais de capacité d'acceptation/refus sur le devis lui-même, seulement sur la demande initiale qui précède sa création.
38. **Codes de validation logistique secrets** : `pickup_code` et `reception_code` (commandes matériaux) sont masqués par défaut sur le modèle `Order` (`$hidden`) et ne sont **jamais dérivables de l'identifiant de commande** — l'ancien format USSD/SMS « RET-42 » a été abandonné pour cette raison (voir historique `UssdController`), remplacé par `RET <NoCommande> <code>` / `REC <NoCommande> <code>`. Chaque acteur ne voit que le code qui le concerne via `Order::codesVisibleTo()` : fournisseur → code de retrait, client → le sien selon `delivery_mode`, admin → les deux, **livreur → aucun code, jamais transmis par notification**. La validation (`OrderService::verifyPickup`/`verifyDelivery`) est idempotente : revalider une commande déjà dans l'état visé réussit sans second versement.
39. **Robustesse cryptographique de l'OTP** : la comparaison entre le code saisi et le code stocké utilise `hash_equals` (temps constant — `OtpService::verifyOtp`), jamais une égalité directe qui fuiterait un timing side-channel. L'envoi passe par la route transactionnelle dédiée `type: otp` de SMSpro (prioritaire, moins filtrée que la voie marketing `plain`).
40. **Pièces KYC sur disque privé** : les documents KYC (`KycDocument`) sont stockés sur le disque privé (`local`), jamais public — l'accès passe par une URL signée à durée limitée (15 minutes, `KycDocument::VIEW_URL_TTL_MINUTES`, route `kyc.document.file`, servie par `KycDocumentController::show`), jamais une URL permanente qui exposerait indéfiniment une pièce d'identité. Migration du parc existant : `php artisan kyc:migrate-to-private`.
41. **Authentification des webhooks de paiement** : les webhooks Wave et Orange Money (`WebhookController`) vérifient la signature avant tout traitement — Wave refuse la requête si `WAVE_WEBHOOK_SECRET` est vide (une signature HMAC à clé vide serait triviale à calculer, donc *fail closed* comme pour la passerelle USSD/SMS, Règle d'or 8) ; Orange Money ne fait jamais confiance au seul webhook et revérifie le statut auprès de l'opérateur, refusant `INITIATED` (session de paiement ouverte, pas encore payée) — seuls les statuts terminaux `SUCCESS`/`SUCCESSFUL` confirment un paiement et créditent le séquestre.
42. **Jalons à Date du Jour pour Missions d'Urgence & Déplacement / Diagnostic** : Pour toute mission d'urgence (`gemini_urgency = 'urgent'`) ou d'intervention de type Déplacement / Diagnostic (`intervention_type_id` ciblant 'Déplacement / Diagnostic'), le devis peut comporter un jalon de paiement daté dès le jour de réception/création du devis (validation backend `after_or_equal:today` au lieu de `after:today`). L'application mobile propose et pré-remplit la date du jour avec le libellé explicite `Aujourd'hui (dd/MM/yyyy)` et une action rapide. Pour les missions standard, la date cible doit impérativement rester dans le futur.
43. **Gating de l'Élaboration du Devis & Masquage Pré-Acceptation** : Le bouton « Créer le devis » / « Faire devis » est masqué dans l'espace artisan tant que celui-ci n'a pas formellement accepté la demande de devis transmise par le client (`pending_artisan_acceptance`). Ce n'est qu'après l'acceptation via `POST /missions/{mission}/accept-request` que l'accès au formulaire de devis est autorisé.
44. **Yandex, fournisseur cartographique officiel — et jamais `env()` hors de `config/`** : Yandex est l'unique fournisseur de cartes et d'itinéraires (MapKit côté mobile, Distance Matrix côté backend via `services.yandex.distance_matrix_key`, repli Haversine sinon). Aucune intégration Google Maps (Directions, Distance Matrix, `GOOGLE_MAPS_API_KEY`) ne doit être ajoutée ni réactivée ; `GoogleMapsService` garde son nom historique uniquement parce que les tests le mockent. Le code de `app/` et `routes/` ne lit jamais `env(...)` : la production exécute `php artisan config:cache`, sous lequel `env()` renvoie `null` hors des fichiers `config/` — toute variable passe par une clé de configuration (ex. `FRONT_URL` → `config('prosartisan.front_url')`).
45. **Séquestre hybride cloisonné par mission** : en mode hybride (`missions.payment_type = 'hybrid'`), la main d'œuvre est financée jalon par jalon (crédit `escrow_mo_jalon` du `wallet_mo`, rattaché au `jalon_id` et au paiement client). Le `wallet_mo` étant commun à toutes les missions de l'artisan, `WalletService::releaseJalon` refuse un jalon hybride non couvert par le séquestre de **sa** mission (ledger filtré par `mission_id`, autres jalons payés réservés), et `getMissionEscrowBalance` ne se rabat jamais sur le solde global pour la main d'œuvre hybride — sans quoi un jalon impayé ou un arbitrage de litige puisait dans le séquestre d'autres missions. Le financement passe par `PaymentService::applyConfirmedPayment`, appelé par les webhooks Wave / Orange Money, l'interrogation de statut et le simulateur ; `fundHybridJalon` est idempotent. Un jalon déjà financé ne se paie pas deux fois (422). Régularisation de l'existant : `php artisan prosartisan:reconcile-hybrid-jalons` (rapport par défaut, `--fix` pour financer les jalons payés jamais consignés ; doubles paiements et déficits signalés pour traitement manuel). Tests : `HybridJalonFundingTest`, `ReconcileHybridJalonsCommandTest`.
46. **Branche principale unique `new-dev-inz` et tests MariaDB bloquants** : `new-dev-inz` est l'unique branche déclenchant les tests et le déploiement de production. `new-develop` est archivée. Les jobs CI `tests` (Pest SQLite) et `tests-mariadb` (Pest MariaDB 11.8) sont tous deux **bloquants** avant tout déploiement sur Hostinger. Aucun déploiement n'est exécuté depuis une pull request.
47. **Portabilité MariaDB 11.8 / SQLite & Helper `Geo::point()`** : la production MariaDB 11.8 n'accepte aucun SRID (`POINT(lng, lat)` uniquement, pas de `POINT SRID 4326`). Toute écriture spatiale dans les tests passe par `Tests\Support\Geo::point($lng, $lat)`. Les tests ne doivent jamais supposer des identifiants numériques séquentiels ou codés en dur (`id = 16`, `RET-16`).
48. **Typage strict Paginator et Authenticatable** : tout service manipulant les collections d'une pagination (`AdminPanelData`, `AdminService`) doit utiliser `Illuminate\Pagination\LengthAwarePaginator` (classe concrète) pour que les méthodes `getCollection()`, `transform()` et `load()` soient formellement déclarées. Dans les tests de Feature, typer les factories de création de modèle via helpers (`: User`) pour lever toute ambiguïté Intelephense avec le contrat `Authenticatable` lors des appels `$this->actingAs()`.
49. **Couche service stricte — aucun accès base dans les contrôleurs** : aucun contrôleur n'interroge la base directement (`DB::table`, `DB::select`, `DB::statement`) : lectures et écritures passent par un service. Les réglages clé-valeur suivent ce schéma — `AppAccessService` (table `settings`, groupe `app_access` : blocage d'accès et messages par espace mobile, valeurs par défaut centralisées), `RecruitmentService::updateSettings` (`recruitment_settings`). L'arbitrage des alertes de fraude passe par `FraudAlertAdminService` (gel, levée, confirmation, classement). Une dépendance de service s'injecte toujours en paramètre obligatoire : une injection optionnelle (`?AdminActivityLogger $audit = null`) était résolue à `null` par le conteneur, ce qui désactivait sans bruit l'audit des cash-outs.
50. **Coordonnées bancaires jamais dans le code** : les coordonnées de virement (banque, titulaire, IBAN) affichées aux payeurs se renseignent uniquement dans le backoffice (onglet Paramètres, carte « Coordonnées de virement bancaire », `PUT /admin/settings/bank-transfer`, capacité `admin.settings.manage`) et vivent dans la table `settings` (groupe `virement_bancaire`, `BankTransferSettingsService`). L'IBAN est validé (format + clé ISO 13616) et chaque modification est auditée avant/après (`settings.bank_transfer.updated`) ; l'éditeur générique des réglages refuse ces clés. Tant qu'elles manquent, `PaymentService` refuse le virement (422, aucune transaction créée) — y compris au-delà du plafond Mobile Money — plutôt que d'envoyer les fonds vers un compte inventé (Règle d'or 29).
51. **Matching Géospatial Multi-Paliers Adaptatif (`GeoService::adaptiveNearbyArtisans`)** : élargissement progressif automatique du rayon de recherche d'artisans qualifiés (2 km $\rightarrow$ 5 km $\rightarrow$ 15 km $\rightarrow$ 50 km) en cas d'absence de prestataires dans le premier palier pour éviter les écrans vides. Les artisans restent ordonnés par Score ProsArtisan décroissant puis distance réelle, et la réponse API renvoie les métadonnées de recherche (`radius_used_km`, `fallback_applied`, `tier_label`).
52. **Unification du Carnet d'Adresses pour les Travaux (`missions.address_id`)** : liaison clé étrangère vers `addresses`, vérification d'appartenance client (anti-IDOR), snapshot immuable de l'adresse (`address_snapshot`) sur la mission, et sélecteur tri-mode réactif sur l'application mobile (`AddressPickerField` : carnet client, saisie libre / géocodage, position GPS). Les coordonnées restent masquées à l'artisan avant financement (Règle 6).
53. **Watchdog Livreur Étendu en Transit & Préservation du Séquestre Matériaux (`driver_picked_up`)** : supervision distincte des courses en transit (`driver_picked_up`) par `prosartisan:driver-watchdog`. Contrairement au statut `driver_assigned` (> 15 min réaffecté automatiquement), une course où les matériaux ont été retirés en quincaillerie ne peut jamais être annulée ni réaffectée automatiquement sans confirmation physique (séquestre matériaux déjà engagé). Si le livreur n'émet plus de signal GPS depuis > 25 min, le système déclenche une relance SMS/Push et une alerte admin haute priorité `delivery_in_transit_unresponsive`.
54. **Mise à Jour Obligatoire du PRD et des Fichiers de Règles Avant Chaque Commit et Push (Règle de Gouvernance)** : [OBLIGATOIRE] Avant **chaque commit et push**, le Product Requirement Document (`PRD.md`) ainsi que l'ensemble des fichiers de gestion des règles (`AGENTS.md` et `CLAUDE.md`) doivent être **obligatoirement et systématiquement mis à jour** pour refléter l'état exact des modifications, des nouvelles fonctionnalités, des correctifs ou des règles architecturales introduites. Aucun commit (`git commit`) ni aucun push (`git push`) ne peut être effectué sans inclure au préalable la mise à jour correspondante de ces fichiers de référence.
55. **Résilience et Idempotence Stricte des Migrations de Production** : Toute migration ajoutant, modifiant ou supprimant des colonnes, index ou clés étrangères doit impérativement être défensive et idempotente via `Schema::hasColumn(...)`, `Schema::hasTable(...)` ou `hasIndex(...)`. En particulier, les clauses `->after('colonne_existante')` ne doivent JAMAIS présumer de la présence inconditionnelle de la colonne cible en base de production : chaque ajout de colonne dépendante doit vérifier l'existence de la colonne parente ou fractionner ses instructions `Schema::table` en blocs séquentiels distincts (afin que chaque instruction `ALTER TABLE` soit exécutée avant la vérification suivante). Si une colonne parente est absente, la migration doit la créer défensivement ou se rabattre sur une colonne antérieure existante garantie.
56. **Voice-to-Quote & Quota IA (`DevisController::voiceQuote`)** : Contrôle strict d'assignation artisan sur la mission (anti-IDOR), KYC actif requis, inclusion de `'voice_quote'` dans `QUOTA_ACTIONS` (`AiMonitoringService`), équilibrage arithmétique parfait $\sum \text{lignes} == \sum \text{jalons}$ garanti par `GeminiService::formatAndBalanceSuggestion`, et parsing JSON défensif côté mobile GetX (`devis_controller.dart`).
57. **Mode Chantier Offline & Sync Différée Multipart (`SyncService`)** : Support étendu de la file d'attente Hive pour les requêtes multipart (`isMultipart: true`, `filePaths`). Mise en file automatique lors des ruptures réseau/timeouts (`uploadJalonPhotos`), rejeu au retour de la connectivité avec reconstruction `FormData`, feedback utilisateur non-bloquant en snackbar d'alerte, et propagation stricte des erreurs métier du serveur (422, etc.).
58. **Protection Anti-Robot à la Connexion & Anti-Spam Cryptographique (`AntiBotService`)** : Protection robuste contre les bots et l'automatisation sans captcha invasif ni cookie tiers : Honeypot invisible (`bot_trap`, `website_url` en `display: none` + `tabIndex={-1}`), défi arithmétique stateless signé par HMAC-SHA256 (`token`, `nonce`, `action`, `ts`), rejet des soumissions instantanées (< 1.0 s en prod/staging), protection anti-rejeu par nonce unique en cache, et journalisation d'audit des tentatives bloquées (`admin.login.blocked_bot`). Intégré sur le backoffice (`AuthenticatedSessionController`), le front office vitrine (`/supplier/login`) et l'API d'authentification (`/api/v1/auth/send-otp`).
59. **Accessibilité Cognitive, Reformulation Visuelle & Anti-Robot Mobile (`frontend_flutter`)** : Traduction systématique du jargon technique en repères visuels et métaphores concrètes du quotidien ivoirien sans altérer le contrat API sous-jacent : « Bon Matériel Quincaillerie (J-Code) » avec icône coupon et statuts (« Bon disponible », « Matériel retiré », « Bon expiré »), « Étapes du chantier / Étapes de paiement » avec icône escalier et statuts (« À réaliser », « Preuves envoyées », « Étape validée », « Paiement versé »), « Coffre de Sécurité — Paiement Garanti » explicitant la protection des fonds et fragmentant visuellement la part matériaux et main d'œuvre. Intégration sur l'écran d'accueil/login Flutter d'un défi arithmétique élémentaire et accessible (`_showSecurityChallengeDialog`, `AuthController.fetchSecurityChallenge`) pour neutraliser le spamming automatisé d'OTP par SMS sans pénaliser l'utilisateur humain.
60. **Tournées groupées & multi-drop livreur (`DeliveryBatchService` & OSRM)** : regroupement intelligent des commandes en attente de livreur (`searching_driver`) par quincaillerie d'enlèvement (`supplier_id`) et proximité de livraison. L'itinéraire multi-points est calculé via OSRM (`OsrmRoutingService::calculateMultiDropRoute`) avec détails des étapes (`legs`), distance cumulée et ETAs par chantier. L'acceptation d'un lot (`POST /api/v1/deliveries/batch-accept`) est protégée par un verrou pessimiste (`lockForUpdate`), génère un identifiant de tournée groupée `TOUR-XXXXXXXX` et assigne simultanément les commandes. L'accès exige un profil livreur avec KYC actif (`kyc.verified`). Les codes de validation secrets ne sont jamais divulgués au livreur (`codesVisibleTo`). Consultation de la feuille de route active via `GET /api/v1/deliveries/active-tour`. Tests de couverture : `DeliveryBatchTest.php` (backend) et `order_repository_batch_test.dart` (mobile).
61. **Supervision flotte livreur & carte temps réel Grand Abidjan (`DeliveryTrackingService::getFleetOverview`)** : cartographie et télémétrie complète de la flotte de livraison sur le backoffice React/Inertia. Endpoint réservé aux administrateurs autorisés (`GET /api/v1/deliveries/fleet-map`, route web `admin.deliveries.fleet-map`, 403 pour tout autre acteur). Vue interactive exploitant les polygones SVG précis des 13 communes d'Abidjan (`ABIDJAN_COMMUNES_GEODATA`), le tracé de la Lagune Ébrié, et les centres géodésiques. Indicateurs agrégés en direct : livreurs connectés, en transit (enlèvement magasin vs livraison chantier), disponibles, alertes watchdog d'inactivité/retard (`stalled_alerts`) et total des courses. Pins animés avec statut couleur, vitesse instantanée en km/h, niveau de batterie, et panneau latéral d'inspection interactif avec appel direct, détails des commandes associées et réaffectation d'urgence sécurisée avec confirmation modale accessible (`useConfirm`). Tests : `AdminFleetSupervisionTest.php` (Pest) et `DeliveriesTrackingSection.test.tsx` (Vitest).


