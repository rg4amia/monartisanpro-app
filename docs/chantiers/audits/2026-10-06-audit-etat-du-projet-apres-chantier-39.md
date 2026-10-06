# Audit — État du projet après le Chantier 39

| Champ | Valeur |
| --- | --- |
| Réalisé le | 2026-10-06 |
| Auteur | Claude Code (Opus 5.5) |
| Plan vérifié | `../plans/2026-10-06-chantier-39-cloture-anomalies-securite-et-api.md` |
| Commit audité | `361c800f` |

## Périmètre

**Vérifié** :

- l'état de la production, par des requêtes en lecture seule sur `prosartisan.net` et `www.prosartisan.net` (codes de réponse, en-têtes, adresse d'API du site publié) ;
- le dernier déploiement (`backend-ci.yml`, exécution du commit `361c800f`) ;
- ce que le Chantier 39 déclare fermé (constats 8, 10, 12, 13, 14, 16, 18 et 19 de l'audit du matin), par lecture du code livré ;
- la table des routes après le nettoyage de `routes/api.php` ;
- les tests : ceux de la CI sur `361c800f`, et la suite Vitest de la vitrine relancée localement ;
- le linter Pint, `npm audit` du backoffice et de la vitrine ;
- la tenue des fichiers de règles et du journal (Règles d'or 54, 70 et 77).

**Non vérifié** :

- `composer audit` : Composer est absent du poste utilisé ;
- la suite Pest et les tests Flutter n'ont pas été relancés localement : la CI les a exécutés sur ce commit ;
- le code de l'application mobile, hors les deux appels à `POST /upload` ;
- le contenu du `.env` de production (valeur d'`ALLOWED_ORIGINS`) et l'état des caches sur le serveur, déduits du script de déploiement ;
- aucun appareil, aucun navigateur : l'effet de l'anomalie 2 sur l'affichage du site est déduit des en-têtes, pas constaté à l'écran.

## Résultats

| Point contrôlé | État | Détail |
| --- | --- | --- |
| Tests Pest SQLite et MariaDB, tests Flutter sur `361c800f` | ✅ conforme | trois jobs de la CI au vert |
| Tests Vitest de la vitrine | ✅ conforme | 71 tests, 11 fichiers |
| Dépôt inaccessible depuis internet (Règle d'or 116) | ✅ conforme | `.env` et `.git/config` répondent 403 |
| Chantiers 37 et 38 en production | ✅ conforme | `/storage/essai.php` 403, `/api/v1/sms` et `/promo-codes` 404, PDF exposés 404 |
| Routes de commandes et de livraisons après nettoyage (constat 19) | ✅ conforme | 18 et 7 routes, aucune perdue, une seule déclaration |
| Lecture d'un message de chantier (constat 13) | ✅ conforme | participants ou administrateur habilité |
| Passeport de solvabilité (constat 12) | ✅ conforme | plus de clé de secours, jeton de 30 jours, limiteur |
| `opcache_clear.php` et en-tête CORS général (constat 16) | ✅ conforme | fichier supprimé (404 en production) ; voir anomalie 2 pour l'effet de bord |
| Déploiement du Chantier 39 | ❌ non conforme | anomalie 1 |
| Site public sur `www.prosartisan.net` | ❌ non conforme | anomalie 2 |
| Téléversement sur le disque public (constat 8) | ❌ non conforme | anomalie 4 |
| Capacités fines des routes de l'API (constat 10) | ⚠️ partiel | anomalie 5 |
| Messages d'exception renvoyés à l'utilisateur (constat 14) | ⚠️ partiel | anomalie 6 |
| Assainissement du HTML de la vitrine (constat 18) | ⚠️ partiel | anomalie 9 |
| Fichiers de règles et journal tenus à jour | ❌ non conforme | anomalie 7 |
| Dépendances JavaScript | ⚠️ partiel | anomalie 8 |
| Linter Pint | ⚠️ partiel | 23 fichiers, anomalie 10 |

## Anomalies

### Élevées

**1. Le déploiement du Chantier 39 a échoué à mi-parcours : la production est dans un état intermédiaire.**
L'exécution du commit `361c800f` (06/10, 20 h 29 GMT) a échoué à l'étape « Copy built assets via SCP » (`ssh: handshake failed`), après l'étape qui met à jour le code. Sont donc faits : `git reset` sur le serveur (confirmé : `opcache_clear.php` répond 404). Ne sont pas faits : la copie des fichiers du backoffice, la copie de la vitrine, et toute l'étape finale — `migrate`, `config:cache`, `route:cache`, `view:cache`, réécriture du `.htaccess` racine.

Conséquences :

- le cache des routes est celui du déploiement précédent : le limiteur des routes du passeport de solvabilité et la capacité `can:admin.missions.view` de la carte de la flotte, ajoutés dans `routes/api.php`, ne sont pas actifs (le contrôle écrit dans le contrôleur, lui, l'est) ;
- la vitrine publiée est l'ancienne : l'assainissement du HTML (Lot C) n'est pas en ligne ;
- aucune migration n'était en attente dans ces commits, et rien n'indique une panne : le site et l'API répondent.

Correctif : relancer le déploiement (`gh run rerun --failed`, ou le prochain push). L'échec ressemble à une coupure SSH passagère, pas à un défaut du code.

**2. Le site public servi sous `www.prosartisan.net` n'a plus le droit d'appeler l'API.**
Le Lot B a retiré de `public/.htaccess` l'en-tête `Access-Control-Allow-Origin: *`. C'est voulu, mais la liste fermée de `config/cors.php` ne contient que `https://prosartisan.net` (et `.ci`, `admin.*`) : pas `https://www.prosartisan.net`. Or :

- `www.prosartisan.net` répond 200, sans redirection vers le domaine nu ;
- le site publié appelle l'adresse absolue `https://prosartisan.net/api/v1/vitrine` (relevée dans ses fichiers JavaScript) ;
- une requête portant `Origin: https://www.prosartisan.net` ne reçoit plus aucun en-tête `Access-Control-Allow-Origin` (vérifié, y compris sur la requête préalable `OPTIONS`) ; avec `Origin: https://prosartisan.net`, l'en-tête est présent.

Un visiteur arrivé par `www.` voit donc le navigateur bloquer chaque appel. La vitrine se replie alors en silence sur son contenu par défaut, et l'espace fournisseur (connexion par code) ne peut plus fonctionner depuis cette adresse. `FRONT_URL` vaut d'ailleurs `https://www.prosartisan.net` dans `.env.example`. Cette régression est en ligne : `public/.htaccess` est lu dès la mise à jour du code.

Correctif, au choix : ajouter `https://www.prosartisan.net` (et `https://www.prosartisan.ci`) à `allowed_origins`, ou rediriger `www.` vers le domaine nu dans le `.htaccess` racine. Dans les deux cas il faut un déploiement complet (cache de configuration). À couvrir par un test sur la liste des origines.

**3. Secrets et emplacement du dépôt : inchangés.**
Le jeton GitHub du déploiement et les secrets du `.env`, lisibles tant que le dossier était ouvert, ne sont pas renouvelés à la connaissance de cet audit. Le dépôt est toujours sous `public_html` ; seule la règle 0 du `.htaccess` le protège.

### Moyennes

**4. Constat 8 déclaré fermé, non corrigé.**
Le plan prévoyait le disque privé et une adresse signée. Le commit ne change que deux lignes de `UploadController` (le calcul de l'adresse) : le fichier part toujours dans `fileshare/` sur le disque public, sous une adresse permanente, et sans clé Gemini l'analyse laisse tout passer. Aucun test du Chantier 39 ne porte sur ce point. Restent aussi sur le disque public : photos de retrait et de livraison (`orders/pickup`, `orders/delivery`) et reçus de bons matériels (`jcode_receipts`). L'application appelle `POST /upload` depuis `mission_repository.dart` et `supplier_catalog_repository.dart` : les images de catalogue ont vocation à être publiques, les photos de mission non — le tri est à faire avant de déplacer quoi que ce soit.

**5. Constat 10 traité sur deux routes seulement.**
`getFleetOverview` et `getTracking` exigent maintenant la capacité. Se contentent toujours du rôle `admin` : `LitigeJuryController::assign`, `LitigeService` (lecture et arbitrage, lignes 59 et 373), `LitigeController` (lignes 90, 121, 155), `DeliveryTrackingController::updateLocation`, `DevisController`, `JalonController`, `JCodeController`, `RecruitmentController`, `RecruitmentEngagementController::show`, `SolvencyPassportController`, `Llm\DisputeMediationService`. `MissionChatController` et `MissionStreamController` ouvrent toujours toute discussion et tout flux à tout administrateur et à tout Référent, sans lien avec le chantier. Un administrateur restreint y retrouve ce que son profil lui retire (Règles d'or 36 et 105).

**6. Constat 14 traité dans un seul contrôleur.**
`PaymentController` ne renvoie plus le message technique. `DeliveryTrackingController` renvoie encore `$e->getMessage()` d'un `\Throwable` à sept endroits, `JCodeController::uploadPhotoMateriaux` et `RecruitmentEngagementController` (deux routes de paiement) aussi. Les autres occurrences relevées renvoient le message d'une exception métier rédigée en français ; elles n'ont pas été vérifiées une à une.

**7. Le Chantier 39 n'a pas tenu les fichiers de règles ni le journal.**
- `CLAUDE.md`, `AGENTS.md` et `PRD.md` ne sont modifiés par aucun de ses cinq commits (Règle d'or 54) : aucune règle ne dit que l'en-tête CORS général est interdit, ni que `opcache_clear.php` ne doit pas revenir.
- L'audit du matin dit toujours « les constats 8 et 10 à 19 restent ouverts ».
- Le plan est au statut « livré » alors que les constats 8, 10 et 14 ne le sont qu'en partie et que le déploiement a échoué ; il n'a pas de section « Écarts » ni de contrôle sur le serveur.

**8. Dépendances JavaScript signalées par `npm audit`.**
- Vitrine : `next` 16.3.3 (critique, exécution de code dans `next/og`), `sharp` (élevée), `source-map-js` (élevée). Le site étant exporté en fichiers statiques, aucun serveur Next ne tourne en production ; la mise à jour reste à faire.
- Backoffice : `concurrently` et `shell-quote` (critiques), `axios`, `nanoid`, `source-map-js` (élevées), `qs` (moyenne). `concurrently` ne sert qu'au poste de développement (`composer dev`) mais figure dans les dépendances de production.
- `npm audit fix` corrige la plupart ; `next` demande une montée de version à tester.

### Faibles

**9. Filtre HTML de secours contournable.** Hors navigateur, `sanitizeHtml` applique des expressions régulières que `<svg/onload=…>` (pas d'espace avant l'attribut) ou `href=javascript:…` sans guillemets traversent. Les pages chargeant les articles dans le navigateur, où DOMPurify s'applique, ce chemin n'est pas atteint aujourd'hui. Le nettoyage côté serveur, à l'enregistrement de l'article, reste la protection à ajouter. `@types/dompurify` est rangé dans les dépendances de production.

**10. Pint échoue sur 23 fichiers**, dont deux livrés par le Chantier 39 (`SolvencyPassportService`, `Chantier39SecurityAndApiFixesTest`).

**11. Constats de l'audit du matin restés hors de tout chantier** : 11 (repli sans clé Gemini pour la suggestion de devis et l'analyse des fichiers), 15, 17, et le second volet du 2 (photo d'un message non lue par le filtre anti-contournement). `UssdController` cite encore le rôle `driver`, supprimé (Règle d'or 106).

**12.** L'en-tête `x-powered-by: PHP/8.3.33` annonce la version exacte de PHP.

## Suites

1. **Tout de suite** : corriger l'anomalie 2 (origine `www.`), puis pousser — ce push relance le déploiement complet et règle du même coup l'anomalie 1. Vérifier ensuite : en-tête `Access-Control-Allow-Origin` présent pour `https://www.prosartisan.net`, vitrine à jour.
2. **Hors code** : renouveler le jeton du déploiement et les secrets du `.env` (anomalie 3).
3. **Chantier 40 (proposé)** : finir les constats 8, 10 et 14 (anomalies 4, 5 et 6), chacun avec son test qui échoue avant correctif, et mettre à jour les fichiers de règles et l'audit du matin (anomalie 7).
4. **Au fil de l'eau** : `npm audit fix` et montée de `next` (anomalie 8), `./vendor/bin/pint` (anomalie 10), anomalies 9, 11 et 12.
5. À compléter : `composer audit`, et une revue du code de l'application mobile.

### Suites données le 06/10/2026 (Chantier 40)

- Anomalies 1 et 2 : fermées. Le déploiement de `842b1649` a réussi ; `Access-Control-Allow-Origin` est renvoyé pour `https://www.prosartisan.net` et `https://prosartisan.net`, pas pour une origine étrangère ; la vitrine publiée contient l'assainissement HTML.
- Anomalies 5, 6, 7, 9, 10 et 12 : fermées par le Chantier 40.
- Anomalie 8 : fermée pour les dépendances de production (`npm audit --omit=dev` : 0, backoffice et vitrine). Reste `vitest` du backoffice, outil de test, dont la correction demande une montée de version majeure.
- Anomalie 4 : partielle — voir les « Écarts » du plan du Chantier 40.
- Anomalie 11 : `driver` retiré d'`UssdController` ; le repli sans clé Gemini et les requêtes dans des contrôleurs restent ouverts.
- Anomalie 3 : inchangée, hors code.
