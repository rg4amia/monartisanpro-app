# Politique et Guide de Sécurité - ProsArtisan

Ce document définit les mesures de sécurité implémentées sur le projet **ProsArtisan** (Backend Laravel et Mobile Flutter) et propose une charte proactive pour les futurs développements.

---

## 1. Sécurisation de l'API & de la Base de Données (Backend Laravel)

### 1.1 Prévention des Injections SQL

* **Principe proactif** : Toute requête SQL doit passer par l'ORM Eloquent ou le Query Builder de Laravel en utilisant des requêtes paramétrées (bindings).
* **Règle absolue** : Interdiction d'utiliser des variables directement concaténées dans des clauses brutes comme `DB::raw()`, `whereRaw()`, ou `selectRaw()`.
* **Exemple sécurisé** :

  ```php
  // CORRECT (Utilisation de placeholders et bindings)
  $artisans = DB::select("SELECT * FROM users WHERE role = ? AND status = ?", ['artisan', $status]);

  // INCORRECT (Faille potentielle d'injection SQL)
  $artisans = DB::select("SELECT * FROM users WHERE role = 'artisan' AND status = '$status'");
  ```

* **Destructive Commands** : En production, les commandes destructrices de base de données sont interdites via `DB::prohibitDestructiveCommands(app()->isProduction())` dans `AppServiceProvider`.

### 1.2 Protection DDoS et Limitation de Débit (Rate Limiting)

Des limites de taux strictes ont été implémentées dans `routes/api.php` pour ralentir ou bloquer les attaques par déni de service et le brute-force :

* **API Standard (`throttle:api`)** : `100` requêtes par minute et par utilisateur authentifié ou adresse IP.
* **Processus d'Authentification (`throttle:auth`)** : `5` requêtes par minute et par IP (limite l'envoi d'OTP, l'authentification et l'inscription pour éviter le spamming SMS).
* **Webhooks de Paiement (`throttle:webhook`)** : `60` requêtes par minute par IP pour éviter les congestions sur les callbacks financiers.
* **Passerelles hors-ligne (`throttle:gateway`)** : `30` requêtes par minute par IP — le trafic légitime provient d'un petit nombre d'IP opérateur, un pic signale un abus.
* **Endpoints publics (`throttle:public`)** : `60` requêtes par minute par IP sur la vitrine, la taxonomie et la vérification de code promo — sans plafond, ce dernier était énumérable par force brute.
* **Assistant IA (`throttle:ai`)** : `10`/minute et `150`/jour par utilisateur authentifié (`40`/jour pour un invité), le quota Gemini étant facturé.

> **Le rate limiting applicatif n'est pas une protection DDoS.** Lorsque les requêtes atteignent ces limiteurs, PHP-FPM et MySQL ont déjà travaillé. Sur hébergement mutualisé, la seule réponse efficace est un filtrage en amont (proxy type Cloudflare devant le domaine) : c'est une décision d'infrastructure, pas de code.

### 1.3 Authentification des Appelants Externes

Trois familles d'endpoints sont joignables sans session utilisateur et déclenchent pourtant des mouvements de fonds. Chacune doit prouver son identité, et le contrôle est systématiquement **fail closed** : un secret non configuré ferme l'endpoint (HTTP 503) au lieu de l'ouvrir.

* **Passerelles USSD / SMS entrant** (`VerifyGatewayRequest`) : l'identité du livreur y est déduite d'un numéro de téléphone posté dans la requête, donnée que l'appelant choisit librement. Par ordre de force — signature HMAC-SHA256 de l'opérateur sur le corps brut (`X-Webhook-Signature: sha256=…`), secret partagé en en-tête `X-Gateway-Secret`, ou paramètre `?gateway_secret=` pour les opérateurs n'acceptant pas d'en-tête personnalisé. **Une signature présente mais invalide est refusée sans repli** sur le secret partagé : accepter ce repli offrirait un contournement trivial du contrôle le plus fort.
* **Webhooks de paiement** : Wave est vérifié en HMAC-SHA256 avec `hash_equals`, et **refusé si le secret est vide** — un HMAC à clé vide est reproductible par n'importe qui, donc un secret oublié en production rendrait les confirmations de paiement forgeables. Orange Money ne fait pas confiance au payload et revérifie le statut auprès de l'API opérateur avant de confirmer.
* **Webhook DLR SMSpro** : signature obligatoire, sans repli. Traitement idempotent (unicité sur `uid`) et insensible à l'ordre, l'opérateur relançant jusqu'à trois fois.

Règle de conception associée : la vérification HMAC porte toujours sur le **corps brut** de la requête (`$request->getContent()`). Re-sérialiser le JSON décodé modifierait un espace ou l'ordre des clés et invaliderait la signature.

### 1.4 Secrets de Validation & Preuve de Présence

Les codes de retrait et de réception conditionnent une libération de fonds ; ils sont la seule preuve qu'une remise a physiquement eu lieu.

* **Ne jamais accepter une forme dérivable d'un identifiant public.** Accepter `REC-<id_commande>` revient à n'exiger aucun secret : le livreur connaît l'identifiant dès qu'il accepte la course.
* **Aucune constante de test dans la logique de validation.** Deux codes universels avaient survécu en production et validaient n'importe quelle commande.
* **Masquer les secrets à la sérialisation du modèle** (`$hidden`) plutôt qu'endpoint par endpoint : la protection couvre alors les endpoints à venir. La réexposition est explicite et par acteur.
* **Ne pas transporter un secret dans une notification destinée à celui qui doit le demander.** Le livreur recevait par notification le code de réception qu'il était censé réclamer au client.
* **Contrôler l'acteur, pas seulement le code.** Un endpoint de validation sans vérification d'acteur laisse tout compte authentifié connaissant le code déclencher un versement.

### 1.5 Anti-Force Brute sur l'OTP

L'OTP est le mécanisme de connexion. Un throttle par IP ne le protège pas : un attaquant disposant de proxys couvre les 10 000 combinaisons d'un code à 4 chiffres avant son expiration. Le compteur de tentatives doit être **porté par le code lui-même** (`otps.attempts`), afin d'être indépendant de l'origine de la requête ; au-delà du plafond le code est brûlé. La comparaison se fait en temps constant, et la sélection du code actif par identifiant décroissant plutôt que par date, pour lever l'ambiguïté de deux envois dans la même seconde.

### 1.6 Assainissement des Données (XSS et Injections)

* Le middleware `SanitizeRequests` intercepte toutes les requêtes entrantes pour nettoyer récursivement les entrées de type chaîne de caractères (retrait automatique des balises HTML et JavaScript suspectes via `strip_tags` et suppression des espaces superflus).

### 1.7 Headers de Sécurité HTTP

Le middleware `SecurityHeadersMiddleware` injecte systématiquement les en-têtes recommandés par l'OWASP pour protéger les navigateurs ou webviews :

* `X-Frame-Options: DENY` (anti-clickjacking)
* `X-Content-Type-Options: nosniff` (bloque le MIME sniffing)
* `X-XSS-Protection: 1; mode=block` (protection XSS active)
* `Content-Security-Policy` (limite l'origine de chargement des scripts/styles)
* `Strict-Transport-Security` (force le protocole HTTPS en production)

### 1.8 Stockage des Pièces d'Identité (KYC)

Les pièces KYC (CNI, selfie) ne doivent **jamais** résider sur le disque public. Une URL permanente et non authentifiée expose définitivement une pièce d'identité dès qu'un lien fuit — journal, capture d'écran, historique de navigation, en-tête `Referer`. Elles sont écrites sur le disque privé et servies par une **URL signée expirant en 15 minutes**. La migration du parc existant est fournie (`php artisan kyc:migrate-to-private`, avec `--dry-run`).

---

## 2. Sécurisation de l'Application Mobile (Flutter)

### 2.1 Chiffrement de la Base de Données Locale (Hive)

Pour éviter la fuite de données personnelles ou de détails de missions en cas de vol du terminal mobile ou d'accès root au système de fichiers :

* Les boxes Hive (`missions_cache`, `jalons_cache`, `cache_metadata`) sont entièrement chiffrées en **AES-256** (`HiveAesCipher`).
* La clé AES est générée de manière sécurisée lors du premier démarrage et stockée dans l'espace de stockage sécurisé du système d'exploitation via `FlutterSecureStorage` (Keychain sur iOS, Keystore avec `encryptedSharedPreferences` activé sur Android).
* **Résilience (Self-Healing)** : Si la base de données locale est corrompue ou qu'une ancienne version non chiffrée empêche la lecture, l'application efface proprement la boîte locale obsolète et la recrée de manière chiffrée sans planter.

### 2.2 Stockage Sécurisé des Identifiants

* Le jeton d'authentification (`auth_token` Sanctum) ne doit **jamais** être stocké dans les préférences partagées standards (`SharedPreferences` ou `GetStorage` en clair). Il est écrit et lu exclusivement depuis `FlutterSecureStorage`.

### 2.3 Sécurité Réseau (SSL Validation & Pinning)

* **Trafic en clair interdit en release.** Le manifeste de production déclare `usesCleartextTraffic="false"`. En release l'application ne parle qu'en HTTPS — `NetworkDiscoveryService` force la production dès que `dart.vm.product` est vrai, et n'effectue aucune découverte réseau locale. Autoriser le clair ne servirait qu'à laisser passer images, WebViews et redirections en HTTP, interceptables sur un WiFi hostile. Le variant `debug` le réactive via `tools:replace` pour le développement local.
* **Aucun secret de validation dans le cache local.** Les codes de retrait et de réception ne transitent pas par les listes de commandes, persistées dans Hive : ils sont récupérés à la demande, au moment de leur affichage, et restent en mémoire.
* L'application valide de manière stricte la chaîne de certification TLS du serveur de production. Overrider `badCertificateCallback` pour accepter tous les certificats est interdit en release.
* **SSL Pinning (Option recommandée pour production)** : Pour bloquer les attaques de l'homme du milieu (MitM) via proxy (ex: Charles, Burp Suite), configurez le client Dio pour valider le hash SHA-256 du certificat public de l'API :

  ```dart
  // Dans ApiClient, configurez le IOHttpClientAdapter pour comparer les empreintes
  (dio.httpClientAdapter as IOHttpClientAdapter).createHttpClient = () {
    final client = HttpClient();
    client.badCertificateCallback = (cert, host, port) => false; // Rejeter les certificats invalides
    return client;
  };
  ```

### 2.4 Durcissement de l'Application lors du Build (Obfuscation)

Lors de la génération des paquets d'installation pour les stores (Google Play, App Store), appliquez obligatoirement l'obfuscation du code Dart pour complexifier la rétro-ingénierie :

```bash
# Compilation Android (AAB) sécurisée et obfusquée
flutter build appbundle --obfuscate --split-debug-info=build/app/outputs/symbols

# Compilation iOS (IPA) sécurisée et obfusquée
flutter build ipa --obfuscate --split-debug-info=build/ios/archive/symbols
```

---

## 3. Gestion des Secrets & Intégrité

* **Variables d'environnement** : Aucune clé d'API, mot de passe de base de données ou secret de webhook ne doit être écrit en dur dans le code source. Utilisez exclusivement les fichiers `.env` et assurez-vous qu'ils soient listés dans le `.gitignore`.
* **Scan de vulnérabilité** : Il est recommandé de configurer un workflow GitHub Actions avec `dependabot` ou Snyk pour vérifier périodiquement les failles de sécurité dans les packages npm, composer et pubspec.
