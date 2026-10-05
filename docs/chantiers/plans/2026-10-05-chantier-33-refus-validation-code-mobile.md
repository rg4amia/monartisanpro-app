# Plan — Chantier 33 : refus d'une validation de code, dit à l'utilisateur et jamais perdu

| Champ | Valeur |
| --- | --- |
| Statut | livré (nouvelle version de l'application à publier ; contrôle sur appareil à faire) |
| Créé le | 2026-10-05 |
| Mis à jour le | 2026-10-05 |
| Auteur | Claude Code |
| Analyses liées | — (suite du Chantier 32 : comportement de la file d'attente hors ligne face à la limite d'essais) |
| Commits | — |

## Objectif

Vérifier que la file d'attente hors ligne de l'application ne consomme pas plusieurs essais avec un même code, puis corriger ce que cette vérification a révélé.

La crainte de départ était infondée : une validation mise en file n'est rejouée qu'une fois, et sort de la file sur un refus. Trois défauts sont apparus à la place :

1. un refus du serveur (code faux, saisie suspendue) s'affichait « Vérifiez votre connexion et réessayez » — le livreur réessayait jusqu'à la suspension sans savoir pourquoi ;
2. une validation enregistrée hors connexion puis refusée au rejeu disparaissait sans un mot, alors que l'utilisateur avait vu « Enregistré hors connexion » ; la liste prévue pour ces abandons n'était affichée nulle part ;
3. un bon code rejoué pendant une suspension recevait 400, que l'application traite comme un refus définitif : la preuve de présence était perdue.

## Décisions retenues

Demande d'Inza Bamba du 05/10/2026 : traiter ce point en premier parmi les suites du Chantier 32.

## Périmètre

- Inclus — serveur : `OrderController`, `DeliveryTrackingController`, `OrderCodeLockedException`. Mobile : `SyncService`, `OrderRepository`, `OfflineBanner`, titre du refus dans `HomeController`.
- Exclu : USSD et SMS (réponse en texte, sans code HTTP) ; les autres actions mises en file (photos d'étape), qui profitent du bandeau sans autre changement.

## Étapes

1. **Suspension en 429** — les quatre routes de validation répondent 429 avec `Retry-After` et `retry_after` quand la saisie est suspendue ; un code faux reste en 400.
2. **Message du serveur** — `OrderRepository._serverRefusal` rend un refus 4xx sous la forme `{success: false, message}` ; les écrans existants l'affichent. Le titre « Code invalide » devient « Validation refusée ».
3. **Actions non abouties** — `SyncService.failures` remplace `abandoned` : refus définitif au rejeu et délai de 3 jours dépassé y sont consignés avec un libellé et un motif, conservés localement jusqu'à lecture.
4. **Bandeau** — `OfflineBanner` annonce la première action non aboutie, le nombre des autres, et la retire sur « Compris ».
5. **Rejeu après suspension** — sur un 429 portant un délai, la file se relance d'elle-même à son terme.

## Règles d'or concernées

28 (lecteurs défensifs), 29 (une panne n'est pas une absence), 57 (file d'attente hors ligne), 111 (limite d'essais), 54, 70, 77. Nouvelle : 112 de `CLAUDE.md` (148 d'`AGENTS.md`).

## Vérification

- **Flutter** : `sync_failures_test.dart` (11 tests), `order_validation_offline_test.dart` ; `flutter analyze` sans remarque ; suite complète.
- **Pest** : `Chantier32OrderCodeAttemptLimitTest.php` (12 tests, dont le 429 sur les deux familles de routes) ; suite complète sur SQLite et MariaDB 11.8 locale.
- **Manuel, sur appareil** : en mode avion, valider avec un code faux, rétablir le réseau, vérifier le bandeau ; saisir cinq codes faux, vérifier le message de suspension.

## Écarts

- **Rejeu réel non couvert par un test automatique** : il dépend du stockage local et du réseau ; seules ses règles de décision sont testées.
- **Versions installées** : elles continuent d'afficher « Vérifiez votre connexion » sur un refus jusqu'à la mise à jour de l'application. La limite d'essais, elle, est déjà active sur le serveur.
- **État local du livreur** : après une validation de retrait mise en file, l'écran affiche la course « en route » ; si la validation est refusée au rejeu, le bandeau l'annonce mais l'écran ne revient à l'état du serveur qu'au rafraîchissement suivant.

## Suites

- Suites des Chantiers 31 et 32 non traitées : adresse du carnet sans position, tarif dépendant du serveur public OSRM, `is_fallback` trompeur, appels OSRM des tests à simuler, déblocage manuel d'une suspension.
