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

### 1.3 Assainissement des Données (XSS et Injections)
* Le middleware `SanitizeRequests` intercepte toutes les requêtes entrantes pour nettoyer récursivement les entrées de type chaîne de caractères (retrait automatique des balises HTML et JavaScript suspectes via `strip_tags` et suppression des espaces superflus).

### 1.4 Headers de Sécurité HTTP
Le middleware `SecurityHeadersMiddleware` injecte systématiquement les en-têtes recommandés par l'OWASP pour protéger les navigateurs ou webviews :
* `X-Frame-Options: DENY` (anti-clickjacking)
* `X-Content-Type-Options: nosniff` (bloque le MIME sniffing)
* `X-XSS-Protection: 1; mode=block` (protection XSS active)
* `Content-Security-Policy` (limite l'origine de chargement des scripts/styles)
* `Strict-Transport-Security` (force le protocole HTTPS en production)

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
