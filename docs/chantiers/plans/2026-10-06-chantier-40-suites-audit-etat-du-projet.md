# Plan — Chantier 40 : suites de l'audit de l'état du projet

| Champ | Valeur |
| --- | --- |
| Statut | partiel (anomalie 4 : décision produit attendue ; contrôle sur le serveur à faire) |
| Créé le | 2026-10-06 |
| Mis à jour le | 2026-10-06 |
| Auteur | Claude Code (Opus 5.5) |
| Analyses liées | `../audits/2026-10-06-audit-etat-du-projet-apres-chantier-39.md` |
| Commits | `842b1649` (lot A) |

## Objectif

Corriger les anomalies relevées par l'audit de l'état du projet après le Chantier 39, en commençant par les deux qui touchent la production.

## Périmètre

- Inclus : anomalies 1, 2 et 4 à 12 de l'audit.
- Exclu : anomalie 3 (renouvellement des secrets et du jeton, sortie du dépôt de `public_html`), qui ne se règle pas dans le code.

## Étapes

1. **Lot A — production** : origine `www.` autorisée dans `config/cors.php` (anomalie 2) ; le push relance le déploiement complet resté à mi-parcours (anomalie 1).
2. **Lot B — constats laissés ouverts par le Chantier 39** : téléversement sur le disque public (anomalie 4), capacités fines des routes de l'API (anomalie 5), messages d'exception (anomalie 6).
3. **Lot C — hygiène** : dépendances JavaScript (anomalie 8), filtre HTML de secours (anomalie 9), Pint (anomalie 10), restes de l'audit du matin (anomalie 11), en-tête `x-powered-by` (anomalie 12), fichiers de règles et journal (anomalie 7).

## Règles d'or concernées

- 36 et 105 : capacité fine, pas le seul rôle `admin`.
- 35 : aucun message technique renvoyé à l'utilisateur.
- 40 : aucun document sensible à une adresse publique permanente.
- 54, 70 et 77 : fichiers de règles et journal tenus dans le même commit.

## Vérification

- `Chantier40AuditFollowUpsTest.php`, chaque correctif avec un test qui échoue avant.
- Après le déploiement du lot A : `Access-Control-Allow-Origin` présent pour `https://www.prosartisan.net`, vitrine à jour.

## Écarts

- **Anomalies 1 et 2** : fermées et contrôlées en production (déploiement de `842b1649` réussi, origine `www.` autorisée, origine étrangère refusée, vitrine à jour).
- **Anomalie 4, partielle** : `POST /upload` refuse désormais le PDF, que l'analyse des données sensibles ne lisait pas. Les fichiers restent sur le disque public, sous un nom aléatoire de quarante caractères (non devinable, à la différence des PDF du Chantier 38), de même que les photos de retrait et de livraison et les reçus de bons matériels. Les passer au disque privé change le contrat avec l'application : les adresses sont enregistrées en base et affichées telles quelles par les versions installées, et un lien signé expire. Il faut décider ce qui est public par nature (images de catalogue, réalisations d'un artisan) et ce qui est une preuve, puis livrer une version mobile. Rien n'a encore été déposé dans ces dossiers en production (contrôle du 06/10/2026).
- **Anomalie 5** : `User::isAdminWith` appliqué aux litiges, missions, devis, étapes, bons matériels, discussions, flux, courses, recrutement, passeport de solvabilité et médiation IA. `RecruitmentService` garde le rôle seul là où il décide du statut d'une offre créée par un administrateur, ce qui n'ouvre aucune donnée.
- **Anomalie 6** : `App\Support\UserFacingError` appliqué à `DeliveryTrackingController` ; message fixe dans `JCodeController::uploadPhotoMateriaux` et les deux paiements de `RecruitmentEngagementController`. Les autres `$e->getMessage()` des contrôleurs renvoient le message d'une exception métier ; ils n'ont pas été repris un à un.
- **Anomalie 8** : Next 16.4.0, `npm audit fix`, `concurrently` déplacé dans `devDependencies`, `shell-quote` forcé à 1.12. Reste `vitest` du backoffice (outil de test, montée de version majeure).
- **Anomalie 9** : filtre de secours réécrit en liste fermée.
- **Anomalie 10** : `./vendor/bin/pint` passé sur tout le backend (25 fichiers reformatés, sans changement de comportement).
- **Anomalie 11** : `driver` retiré d'`UssdController`. Le repli sans clé Gemini (suggestion de devis, analyse des fichiers) et les requêtes Eloquent dans des contrôleurs restent ouverts.
- **Anomalie 12** : `X-Powered-By` retiré par `public/.htaccess` ; à contrôler sur le serveur, l'hébergeur pouvant le rétablir.
- **Vérification** : `Chantier40AuditFollowUpsTest` (7 tests) ; suite Pest complète ; Vitest du backoffice (489 tests) et de la vitrine (72 tests) ; compilation du backoffice et de la vitrine.
