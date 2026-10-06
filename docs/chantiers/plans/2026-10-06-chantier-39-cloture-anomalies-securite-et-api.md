# Plan — Chantier 39 : clôture des anomalies résiduelles de sécurité et d'API

| Champ | Valeur |
| --- | --- |
| Statut | en cours (Lots A et B validés) |
| Créé le | 2026-10-06 |
| Mis à jour le | 2026-10-06 |
| Auteur | Antigravity |
| Analyses liées | `../audits/2026-10-06-audit-securite-api-et-deploiement.md` (constats 8, 10, 12, 13, 16, 18, 19) |
| Commits | `b8aa8b91` (Lot A), `858129f5` (Lot B) |

## Objectif

Fermer l'ensemble des constats de sécurité et d'intégrité de l'API restant ouverts après les chantiers 37 (failles critiques) et 38 (données tiers et documents nominatifs), afin de parachever l'assainissement de l'écosystème ProsArtisan.

## Périmètre

- **Inclus** :
  - **Constat 19** : Déclarations redondantes des commandes et livraisons dans `routes/api.php` sans filtres de sécurité initiaux.
  - **Constat 13** : Absence de contrôle d'appartenance sur `MissionChatController::markAsRead`.
  - **Constat 10** : Bypass de capacité fine pour le rôle administrateur sur `DeliveryTrackingController::getFleetOverview` et routes associées.
  - **Constat 12** : Secret de secours en dur dans `SolvencyPassportService` et routes publiques sans rate-limiting ni expiration de jeton.
  - **Constat 16** : Fichier public `opcache_clear.php` non authentifié et wildcard CORS dans `.htaccess`.
  - **Constat 8** : Téléversement générique sur le disque public dans `UploadController`.
  - **Constat 18** : Injection HTML non assainie (`dangerouslySetInnerHTML`) dans la vitrine Next.js.
  - **Constat 14** : Masquage des messages d'exception bruts renvoyés aux utilisateurs mobiles.
- **Exclu** :
  - Modification de la structure des bases de données de production.
  - Révision des modules déjà clos et audités (chantiers 1 à 38).

---

## Lots d'implémentation

### Lot A — Intégrité des routes API & Contrôles d'accès (Backend)

1. **Suppression des routes redondantes (Constat 19)** :
   - Nettoyer le premier bloc `orders` et `deliveries` (lignes 179-201 de `backend-proartisan/routes/api.php`).
   - S'assurer que seul le bloc enrichi (lignes 386-414) avec `kyc.verified`, `payment.unrestricted`, et `dispute.debt_free` est actif.
   - Conserver les routes spécifiques non dupliquées (`/orders/disputes`, `/orders/estimate-delivery`, `/orders/multi-store`).
2. **Contrôle d'accès sur l'état de lecture du chat (Constat 13)** :
   - Dans `MissionChatController::markAsRead`, valider que l'utilisateur connecté est soit le `client_id`, soit l'`artisan_id` de la mission (403 sinon).
3. **Contrôle de capacité fine sur la flotte (Constat 10)** :
   - Dans `DeliveryTrackingController::getFleetOverview`, remplacer la condition permissive `($user->role !== 'admin' && ! $user->can('admin.missions.view'))` par une vérification stricte : `$user->can('admin.missions.view')` (ou capacité dédiée logistique).
   - Sécuriser les endpoints de tracking pour restreindre la consultation aux livreurs affectés, aux clients/artisans de la commande, ou aux administrateurs habilités.

### Lot B — Secrets, Hygiène publique & Protection Réseau (Backend)

1. **Secret et jeton du passeport de solvabilité (Constat 12)** :
   - Dans `SolvencyPassportService::verifyPassportToken`, supprimer le repli `'prosartisan-secret'`. Lever une exception de configuration si `config('app.key')` est vide.
   - Ajouter un timestamp d'émission et une durée de validité (TTL ex: 24h ou 7j) au jeton signé du passeport.
   - Poser le middleware `throttle:api` sur les routes publiques `/solvency-passports/verify` et `/insurance-quote`.
2. **Nettoyage public et politique CORS (Constat 16)** :
   - Supprimer le fichier `backend-proartisan/public/opcache_clear.php` du dépôt.
   - Supprimer la directive `Header set Access-Control-Allow-Origin "*"` dans `backend-proartisan/public/.htaccess` pour que les restrictions de `config/cors.php` s'appliquent fidèlement.
3. **Sécurisation de l'upload générique (Constat 8)** :
   - Dans `UploadController::upload`, exiger une authentification explicite, stocker sur le disque privé sécurisé, et renvoyer une URL temporaire signée si nécessaire.
4. **Masquage des erreurs d'exception (Constat 14)** :
   - Normaliser les blocs `catch (\Throwable $e)` dans `PaymentController`, `DeliveryTrackingController`, etc., pour retourner un message d'erreur utilisateur clair et générique en français (Règle d'or 35), en journalisant l'exception technique dans `Log::error`.

### Lot C — Sécurisation du Rendu Vitrine (Next.js)

1. **Assainissement des contenus riches (Constat 18)** :
   - Dans `vitrine-nextjs/src/app/page.tsx`, `actualites/page.tsx` et `actualites/[slug]/ArticleDetailClient.tsx` :
   - Installer ou intégrer un filtre de nettoyage HTML (tel que `isomorphic-dompurify`) avant le passage à `dangerouslySetInnerHTML`.
   - Garantir qu'aucune balise `<script>`, iframe malveillante ou gestionnaire d'événement inline (`onload`, `onerror`) ne puisse s'exécuter dans le navigateur des visiteurs.

---

## Règles d'or concernées

- **Règle 36** : Propriété de la ressource et capacités fines vérifiées (jamais un simple contrôle de rôle `admin`).
- **Règle 40** : Aucun document ni média sensible hébergé sous une URL publique permanente non protégée.
- **Règle 35** : Erreurs retournées en français lisible, sans message technique brut ni stack trace.
- **Règle 105** : Capacité fine sur toutes les actions sensibles d'administration.
- **Règle 108** : Aucune clé de chiffrement ou secret de secours en dur dans le code source.

---

## Méthodologie TDD & Vérification

1. **Tests Pest Backend (`Chantier39SecurityAndApiFixesTest.php`)** :
   - Écriture préalable de tests échouant sur :
     - Appel de `markAsRead` par un utilisateur non membre de la mission (attendu : 403).
     - Appel de `getFleetOverview` par un compte admin sans permission `admin.missions.view` (attendu : 403).
     - Vérification de jeton de solvabilité avec une fausse signature ou signature sous l'ancien secret par défaut (attendu : rejet).
     - Création de commande directe vérifiant la bonne application des middlewares `kyc.verified` et `payment.unrestricted`.
     - Non-présence de `opcache_clear.php` dans le répertoire public.
2. **Tests Vitest Vitrine (`actualites.test.tsx`)** :
   - Test vérifiant que le contenu d'un article contenant du code HTML malveillant est assaini sans balises `<script>`.
3. **Validation globale** :
   - Exécution complète de `php artisan test --parallel` (doit conserver 100% de succès).
   - Exécution de `npm test` dans `backend-proartisan` et `vitrine-nextjs`.
   - Vérification de la liste des routes via `php artisan route:list`.
