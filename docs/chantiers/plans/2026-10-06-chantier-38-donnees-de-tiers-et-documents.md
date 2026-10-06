# Plan — Chantier 38 : données d'un tiers et documents nominatifs

| Champ | Valeur |
| --- | --- |
| Statut | livré (rapport et reçu à vérifier sur appareil) |
| Créé le | 2026-10-06 |
| Mis à jour le | 2026-10-06 |
| Auteur | Claude Code (Opus 5.5) |
| Analyses liées | `../audits/2026-10-06-audit-securite-api-et-deploiement.md` (constats 5, 6, 7 et 9) |
| Commits | `54560140` |

## Objectif

Fermer les constats de l'audit du 06/10/2026 où une donnée d'un tiers était remise à tout compte du bon rôle, sans lien vérifié avec la ressource, et retirer du disque public les documents PDF nominatifs.

Le contrôle du serveur fait le même jour a confirmé l'exposition : deux rapports de solvabilité et trois reçus de paiement répondaient 200 sans authentification, sous un nom qui se devine (identifiant et date à la seconde).

## Périmètre

- Inclus : constats 5 (coffre de preuves), 6 (rapport de solvabilité), 7 (recrutement), 9 (suggestion de devis), et les trois autres PDF de `PdfService` (reçu de paiement, facture de décaissement, bordereau de cash-out), que l'audit n'avait pas relevés.
- Exclu : constat 8 (téléversement générique, photos de retrait et de livraison, reçus de bons matériels sur le disque public) et constat 10 (capacités fines des routes de l'API ouvertes aux administrateurs), à traiter dans un chantier suivant ; constats 11 à 19 ; l'application mobile (aucun changement nécessaire).

## Étapes

1. **PDF sur le disque privé** — `PdfService::store` écrit les quatre documents sous `documents/<type>/` du disque privé. `GET /artisans/{user}/report` et `GET /micro-credit/report` suppriment le fichier après l'envoi. `GeneratedDocumentService::getPdfPath` garde sa copie de travail, désormais privée, et ne ressert jamais un chemin resté public (`PdfService::isPublicPath`).
2. **Copies publiques retirées** — `PdfService::purgePublicCopies` supprime les PDF de `reports/`, `receipts/`, `invoices/` et `cashouts/` du disque public ; la migration `2026_10_06_100000_purge_public_generated_pdfs` l'appelle au déploiement. Chaque document se régénère à la demande.
3. **Rapport de solvabilité** — `ArtisanController::downloadReport` : le titulaire, ou un administrateur porteur de `admin.users.view` ; 403 sinon.
4. **Coffre de preuves** — `EvidenceVaultService::accessLevel` : parties à la mission, déposant, administrateur porteur de `admin.litiges.view` (accès complet) ; juré du litige (accès anonymisé). Tout autre compte reçoit la même réponse 404 qu'une preuve inexistante. Le certificat ne porte plus de téléphone ; un juré ne reçoit ni l'identité du déposant ni la position.
5. **Recrutement** — `RecruitmentEngagementService::createEngagement` refuse (422) un engagement sur une offre dont l'accès aux candidatures n'est pas payé. `present` et `listFor` ne transmettent le téléphone de l'autre partie qu'à partir d'un engagement `active` ou `completed` ; la position du compte n'est plus jointe. Le contrôleur n'interroge plus la base.
6. **Suggestion de devis** — `DevisController::suggest` : artisan de la mission, quota IA (429), limiteur `throttle:ai` sur la route.

## Règles d'or concernées

- 36 : propriété de la ressource vérifiée, pas seulement le rôle.
- 22 et 76 : ni téléphone ni identité d'une partie remis à un juré ou à un tiers.
- 33 : le numéro de l'artisan n'est pas transmis au recruteur avant paiement.
- 40 : un document nominatif ne vit pas à une adresse publique permanente.
- 25 : toute fonction d'IA passe par le quota.
- 49 : lecture des engagements déplacée dans le service.
- 55 : migration sans effet en test, et dont l'échec ne bloque pas le déploiement.

## Décision produit du 06/10/2026

Le constat 7 laissait le choix : téléphone transmis à partir d'un engagement accepté et financé, ou jamais. Le premier est retenu — les deux parties doivent pouvoir se joindre une fois le séquestre payé (`CONTACT_STATUSES`).

## Vérification

- `Chantier38OwnershipFixesTest.php` (15 tests) : 12 échouaient avant correctif — un client téléchargeait le rapport d'un artisan, tout compte lisait le certificat d'une preuve avec le téléphone du déposant, un recruteur créait un engagement sans rien avoir payé et lisait le téléphone de l'artisan, un artisan obtenait une suggestion sur la mission d'un autre.
- `RecruitmentEngagementTest.php` : les offres du jeu d'essai sont désormais ouvertes à la consultation avant tout engagement.
- Suites voisines relancées : recrutement, devis, micro-crédit, jury, documents, cash-out, litiges.
- **Sur le serveur, après déploiement** :
  - `curl -I https://prosartisan.net/storage/reports/solvability_report_23_20260928181003.pdf` et `…/storage/receipts/recu_paiement_31_20260918214109.pdf` répondent 404 ;
  - les dossiers `reports/` et `receipts/` de `storage/app/public` sont vides.
- **Sur appareil** : télécharger son rapport de solvabilité depuis l'écran du score ; ouvrir un reçu de paiement.

## Écarts

- Le test « aucun PDF sur le disque public » passait avant correctif : l'ancien code écrivait par `storage_path()`, hors du disque simulé. Il contrôle maintenant aussi le dossier réel.
- Les reçus consultés par `GeneratedDocumentService` restent conservés sur le disque privé (copie de travail du backoffice) ; seuls les rapports remis par l'API sont supprimés après l'envoi.
- Une facture de décaissement d'un litige arbitré avant ce chantier, dont le chemin enregistré pointait vers le disque public, n'est plus téléchargeable après la purge (aucune n'existe sur le serveur : le dossier `invoices/` est absent).
- Contrôle du 06/10/2026 après déploiement (`54560140`) : les cinq PDF qui répondaient 200 répondent 404 ; les dossiers `reports/` et `receipts/` de `storage/app/public` sont vides, constaté sur le serveur.
