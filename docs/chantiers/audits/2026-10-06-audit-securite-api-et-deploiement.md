# Audit — Sécurité de l'API mobile, des téléversements et du déploiement

| Champ | Valeur |
| --- | --- |
| Réalisé le | 2026-10-06 |
| Auteur | Claude Code (Opus 5.5) |
| Plan vérifié | aucun — audit transversal du dépôt |
| Commit audité | `a9237da5` |

## Périmètre

**Vérifié, par lecture du code et de la table des routes (`php artisan route:list`)** :

- les règles d'or contrôlables mécaniquement sur le backend (44, 49, 93, bannissement des montants décimaux, syntaxes spatiales, confirmations du backoffice) ;
- les contrôles de rôle et de propriété (Règle d'or 36) de `routes/api.php`, contrôleur par contrôleur, sur les modules qui n'avaient pas fait l'objet d'une analyse dédiée : SMS, codes promo, téléversements, coffre de preuves, messagerie de chantier, bons matériels, paiements, litiges, recrutement, suivi de livraison, passeport de solvabilité ;
- les capacités fines des routes `/admin/*` de `routes/web.php` ;
- le script de déploiement (`.github/workflows/backend-ci.yml`) ;
- les fichiers sensibles suivis par git, le manifeste Android, les secrets écrits dans le code ;
- le linter Pint.

**Non vérifié** :

- **la production elle-même** : aucune requête n'a été envoyée au serveur. Les constats 1 et 2 décrivent ce que le dépôt et le script de déploiement produisent ; leur effet réel se confirme sur le serveur (commandes données plus bas) ;
- la suite Pest, Vitest et `flutter test` n'ont pas été relancées (la CI les exécute à chaque push) ;
- `composer audit` et `npm audit` (Composer absent du shell utilisé) ;
- les modules déjà analysés du 03 au 05/10 (missions, évaluations, utilisateurs, rôles, Administration LLM, commandes) n'ont pas été rejoués ;
- le code de l'application mobile et de la vitrine n'a fait l'objet que d'un balayage (secrets, trafic en clair, HTML injecté), pas d'une revue.

Aucune des anomalies ci-dessous n'a été exploitée : elles sont établies par lecture. Chacune reste à reproduire par un test Pest qui échoue avant correctif (Règle d'or 36).

## Résultats

| Point contrôlé | État | Détail |
| --- | --- | --- |
| `env()` hors de `config/` (Règle 44) | ✅ conforme | aucune occurrence dans `app/` ni `routes/` |
| Accès base dans les contrôleurs par `DB::` (Règle 49) | ✅ conforme | aucune occurrence |
| Montants en `FLOAT` / `DOUBLE` | ✅ conforme | les décimaux restants sont des taux, des coordonnées et des mesures |
| `POINT SRID` / `ST_SRID` à deux arguments | ✅ conforme | seulement dans des commentaires |
| `window.confirm` / `alert` dans le backoffice | ✅ conforme | tout passe par `useConfirm` |
| Backoffice appelant `/api/v1` (Règle 93) | ✅ conforme | aucune occurrence hors commentaire |
| Capacité fine sur les routes `/admin/*` (Règle 16) | ✅ conforme | 9 routes sans capacité, toutes prévues (accueil, tableau de bord, déconnexion, manuel, notifications de l'administrateur) |
| Propriété vérifiée : missions, devis, jalons, bons matériels, paiements, transactions, notifications, stock artisan, adresses | ✅ conforme | contrôle `client_id` / `artisan_id` / `user_id` présent |
| Secrets suivis par git | ✅ conforme | seul `.env.example` est suivi ; ni `env.json`, ni `key.properties`, ni keystore |
| Trafic en clair Android | ✅ conforme | `usesCleartextTraffic="false"` |
| Routes de simulation de paiement | ✅ conforme | `abort_unless(app()->environment(['local', 'testing']), 404)` |
| Dépôt déployé sous la racine web | ❌ non conforme | constat 1 |
| Téléversements sur le disque public | ❌ non conforme | constats 2 et 8 |
| Routes API d'administration sans contrôle | ❌ non conforme | constats 3 et 4 |
| Propriété vérifiée : coffre de preuves, rapport de solvabilité, suggestion de devis, lecture d'un message | ❌ non conforme | constats 5, 6, 9, 13 |
| Recrutement : numéro de l'artisan (Règle 33) | ❌ non conforme | constat 7 |
| Capacité fine sur les routes API ouvertes aux administrateurs (Règles 36 et 105) | ⚠️ partiel | constat 10 |
| Échec fermé sans clé Gemini (Règles 29 et 69) | ⚠️ partiel | constat 11 |
| Clé de secours écrite dans le code (Règle 108) | ⚠️ partiel | constat 12 |
| Linter Pint | ⚠️ partiel | 25 fichiers non conformes (constat 15) |

## Anomalies

### Critiques

**1. Le dépôt entier est cloné sous la racine web, sans règle d'interdiction.**
`backend-ci.yml` déploie dans `public_html/monartisanpro-app` (ligne 309) par `git clone` puis `git reset --hard`. Le `.htaccess` écrit à la racine (lignes 439-494) ne fait que router : il n'interdit aucun chemin, et le dépôt ne contient aucun autre `.htaccess` que celui de `backend-proartisan/public/`. Un fichier existant demandé directement est donc servi tel quel, sauf protection propre à l'hébergeur. Sont concernés :

- `monartisanpro-app/backend-proartisan/.env` (clé d'application, base de données, Wave, Orange Money, SMS, Gemini, OneSignal, Telegram) ;
- `monartisanpro-app/.git/` — le code et son historique, et `.git/config`, où le script inscrit l'adresse du dépôt **avec le jeton GitHub du déploiement** (lignes 324 et 328) ;
- `monartisanpro-app/backend-proartisan/storage/app/private/` — pièces d'identité KYC, cartes CNMCI, notes vocales, documents de l'Assistant, c'est-à-dire tout ce que les Règles d'or 40, 94 et 98 ont placé sur le disque privé ;
- `monartisanpro-app/backend-proartisan/storage/logs/laravel.log`.

À confirmer sur le serveur avec un fichier sans secret, par exemple : `curl -I https://prosartisan.net/monartisanpro-app/backend-proartisan/composer.json`. Une réponse 200 confirme l'exposition ; il faut alors tenir pour compromis tous les secrets du `.env`.

Correctif : sortir le dépôt de `public_html` (seul `backend-proartisan/public/` doit être atteignable, par lien symbolique ou copie) ; à défaut et dans l'immédiat, un `.htaccess` `Require all denied` dans `monartisanpro-app/` avec une exception pour `backend-proartisan/public/`. Ne plus inscrire le jeton dans `.git/config`. Renouveler les secrets si l'exposition est confirmée.

**2. La messagerie de chantier accepte n'importe quel fichier et le publie avec l'extension choisie par l'expéditeur.**
`MissionChatController::store` valide `'file' => ['nullable', 'file', 'max:10240']`, sans liste de types, puis enregistre le fichier sur le disque public sous `chat/{mission}/chat_<aléa>.<extension du client>` et renvoie son adresse. Un participant à une mission peut ainsi déposer un fichier `.php` et l'appeler par `/storage/chat/...` : `/storage` est routé vers `public/`, où PHP s'exécute. Selon la configuration de l'hébergeur, cela donne l'exécution de code sur le serveur ; à tout le moins, un fichier `.html` ou `.svg` y est servi sur le domaine du backoffice. Le filtre anti-contournement ne lit par ailleurs que le texte : une photo portant un numéro de téléphone passe avant le financement.

Correctif : liste fermée de types (`mimes:jpeg,jpg,png,webp,m4a,mp3,aac,wav,ogg`), extension déduite du contenu (`$file->extension()`), et un `.htaccess` dans `storage/app/public/` qui retire l'exécution de PHP. `PhotoService::uploadGeolocatedPhoto` reprend lui aussi l'extension du client : à aligner.

**3. Les routes SMS de l'API sont ouvertes à tout compte connecté.**
`POST /api/v1/sms/send`, `GET /api/v1/sms` et `GET /api/v1/sms/{uid}` (`SmsController`) ne portent que `auth:sanctum`, `account.active` et `throttle:api` — vérifié dans la table des routes. Tout client, artisan, fournisseur ou livreur peut :

- envoyer un SMS au texte libre, à n'importe quel numéro, sous le nom d'expéditeur de ProsArtisan ou un autre (`sender_id`), jusqu'à 100 par minute, aux frais de la plateforme — de quoi imiter un message de paiement ou de code ;
- lire les messages envoyés par le compte SMS Pro (`viewAll`), qui contiennent les codes de connexion et les codes des étapes de chantier (Règles d'or 4 et 39).

Aucun test ne couvre ces routes. Correctif : les supprimer si rien ne les appelle, sinon les placer sous `admin.only` et une capacité fine.

**4. La gestion des codes promo par l'API est ouverte à tout compte connecté.**
`Route::apiResource('promo-codes', …)` et `/promo-codes/{promoCode}/toggle` n'ont ni `admin.only` ni `can:admin.promo.manage`, alors que leurs équivalents du backoffice les portent (`routes/web.php`, lignes 248-252) — écart direct avec la Règle d'or 36. Tout utilisateur peut lister les codes, en créer un à 100 %, modifier, désactiver ou supprimer ceux qui existent. `OrderService::createOrder` applique la remise au sous-total de la commande. La ressource déclare aussi une route `show` que le contrôleur n'implémente pas (erreur 500). Aucun test ne couvre ces routes.

Correctif : supprimer ces routes (le backoffice a les siennes) ou leur donner `admin.only` et `can:admin.promo.manage`.

### Élevées

**5. Coffre de preuves lisible par n'importe quel compte.** `GET /evidence-vault/{id}/certificate` et `/verify` (`EvidenceVaultController`) ne vérifient ni la mission ni le litige. Le certificat renvoie le nom et le **téléphone** de la personne qui a déposé la preuve, ainsi que sa position GPS ; les identifiants sont séquentiels. Contraire aux Règles d'or 22 et 76. Correctif : parties à la mission ou au litige, jurés du dossier (sans coordonnées), administrateur.

**6. Rapport de solvabilité d'un artisan téléchargeable par tous.** `GET /artisans/{user}/report` (`ArtisanController::downloadReport`) ne contrôle que le rôle de la cible : tout compte obtient le PDF d'un artisan (score détaillé, missions terminées, gains cumulés). `PdfService::generateSolvabilityReport` écrit en outre chaque PDF dans `storage/app/public/reports/solvability_report_<id>_<date à la seconde>.pdf`, donc sous une adresse publique devinable, et ne le supprime jamais. Correctif : titulaire ou administrateur porteur de la capacité, fichier temporaire sur le disque privé, supprimé après envoi (`deleteFileAfterSend`).

**7. Recrutement : le numéro de l'artisan est remis au recruteur sans paiement.** `RecruitmentEngagementService::createEngagement` vérifie que la candidature appartient à une offre du recruteur, mais pas que l'accès aux candidatures a été payé (`applicantsUnlocked()`). `RecruitmentEngagementController::show` et `mine` chargent ensuite `artisan:id,name,phone` quel que soit l'état de l'engagement. Un recruteur peut donc créer un engagement sur les identifiants de candidature de son offre, sans avoir rien réglé, et lire le nom et le téléphone de chaque candidat — ce que la Règle d'or 33 interdit et que `RecruitmentTest` vérifie seulement sur la liste des candidatures. Correctif : exiger le séquestre d'accès avant tout engagement, et ne transmettre le téléphone qu'à partir d'un engagement accepté et financé (ou jamais, selon la décision produit).

**8. Téléversement générique sur le disque public.** `POST /upload` (`UploadController`) enregistre images, PDF et vidéos de tout compte dans `fileshare/` sur le disque public, sous une adresse permanente. Sans clé Gemini, ou sur un PDF, l'analyse des données sensibles laisse tout passer. L'adresse renvoyée est écrite en dur (`https://prosartisan.net`). Les photos de retrait et de livraison (`orders/pickup`, `orders/delivery`) et les reçus de bons matériels (`jcode_receipts`) sont aussi sur le disque public. À évaluer : ce qui relève de la preuve devrait suivre le circuit des liens signés (Règle d'or 40).

### Moyennes

**9. Suggestion de devis ouverte à tout artisan.** `DevisController::suggest` ne contrôle que le rôle : un artisan obtient une suggestion sur la mission d'un autre, et la route n'a ni `throttle:ai` ni contrôle du quota IA (Règle d'or 25), contrairement à `voice-quote`. Correctif : artisan de la mission, quota et limiteur.

**10. Rôle `admin` sans capacité fine sur des routes de l'API.** `DeliveryTrackingController::getFleetOverview` laisse passer tout administrateur (`role !== 'admin' && ! can(...)` : la capacité n'est lue que pour les autres rôles) ; `updateLocation`, `getTracking`, `LitigeJuryController::assign`, `LitigeService::arbitrate`, `LitigeController::show`, `MissionChatController` et `MissionStreamController` se contentent du rôle. Un administrateur restreint y retrouve la carte de la flotte, les fiches de litige avec téléphones, les discussions de chantier et l'arbitrage (Règles d'or 36 et 105). Le rôle `referent` lit de même toute discussion et tout flux de mission, sans lien avec le chantier.

**11. Repli sans clé Gemini.** `GeminiService::suggestDevis` renvoie une suggestion de repli (`getFallbackDevisSuggestion`) et `analyzeMediaForSensitiveData` accepte le fichier quand la clé manque ou en test. À rapprocher de l'échec fermé retenu pour le KYC et la base de connaissances (Règles d'or 29, 69 et 94).

**12. Passeport de solvabilité.** `SolvencyPassportService::verifyPassportToken` signe avec `config('app.key') ?? 'prosartisan-secret'` — une clé de secours écrite dans le code, que la Règle d'or 108 a écartée pour le défi anti-robot. Le jeton n'expire pas, et les deux routes publiques (`/solvency-passports/verify`, `/insurance-quote`) n'ont aucun limiteur, à la différence des autres routes publiques.

### Faibles

**13.** `MissionChatController::markAsRead` ne vérifie pas que l'appelant participe à la mission.

**14.** Messages d'exception renvoyés tels quels à l'utilisateur (`$e->getMessage()` dans `PaymentController`, `DeliveryTrackingController`, `JCodeController::uploadPhotoMateriaux`, `RecruitmentEngagementController`) : un message technique, possiblement en anglais, peut atteindre l'écran (Règle d'or 35).

**15.** `./vendor/bin/pint --test` échoue sur 25 fichiers (style seulement), dont `EvidenceVaultController`, `JuryController`, `MicroCreditController`, `SolvencyPassportService`, `DoubleEntryLedgerService` et trois migrations.

**16.** `public/opcache_clear.php` est suivi par git et appelable sans authentification (vide le cache d'OPcache à la demande). `public/.htaccess` pose `Access-Control-Allow-Origin: *` sur toutes les réponses, en contradiction avec la liste fermée de `config/cors.php`.

**17.** Logique et requêtes Eloquent dans des contrôleurs (`DashboardController`, `RecruitmentController`, `PromoCodeController`, `ArtisanStockController`, `TransactionController::index`) : l'esprit de la Règle d'or 49, dont la lettre (`DB::`) est respectée.

**18.** La vitrine injecte `article.contenu` par `dangerouslySetInnerHTML` (trois pages). Le contenu vient du backoffice ; à nettoyer côté serveur si ce n'est pas déjà fait.

**19.** `routes/api.php` déclare deux fois les routes de commandes et de livraisons (lignes 181-199 puis 385-413), la première fois sans `kyc.verified` ni `payment.unrestricted` sur `POST /orders`. La seconde déclaration l'emporte aujourd'hui ; l'ordre des lignes tient seul la protection.

## Suites

### Constat 1 confirmé et fermé le 06/10/2026

- 13 h 08 GMT : `curl -I` sur `monartisanpro-app/backend-proartisan/composer.json` répond **200**. L'exposition est confirmée.
- Deux règles ajoutées à la main dans `public_html/.htaccess`, après `RewriteBase /` : tout le dossier `monartisanpro-app` est refusé, sauf `backend-proartisan/public/`.
- 13 h 24 GMT : `composer.json`, `.env` et `.git/config` répondent **403** ; `GET /api/v1/settings/app-access` répond **200**.
- Les mêmes règles sont ajoutées au `.htaccess` qu'écrit `backend-ci.yml` (règle 0), sans quoi le déploiement suivant effaçait la correction manuelle.
- Reste à faire : renouveler les secrets du `.env`, qui ont pu être lus tant que le dossier était ouvert (aucun moyen de l'établir depuis le dépôt) ; sortir le dépôt de `public_html` reste la correction de fond.

### Autres constats

Aucun autre correctif n'a été appliqué.

1. **Immédiat, sur le serveur** : confirmer le constat 1 par la commande indiquée ; si elle répond 200, interdire l'accès au dossier, renouveler les secrets du `.env` et le jeton, puis sortir le dépôt de la racine web.
2. **Chantier 37 (proposé)** — fermer les constats 2, 3 et 4, chacun avec son test Pest qui échoue avant correctif.
3. **Chantier 38 (proposé)** — constats 5 à 10 : propriété et capacités fines.
4. Constats 11 à 19 : à traiter au fil de l'eau ; `./vendor/bin/pint` règle le 15 en une commande.
5. À compléter par : `composer audit`, `npm audit` (backoffice et vitrine), et une revue du code de l'application mobile, non couverts ici.
