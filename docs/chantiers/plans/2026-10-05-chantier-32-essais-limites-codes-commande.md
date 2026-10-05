# Plan — Chantier 32 : essais limités sur les codes de retrait et de réception

| Champ | Valeur |
| --- | --- |
| Statut | livré (contrôle manuel à faire) |
| Créé le | 2026-10-05 |
| Mis à jour le | 2026-10-05 |
| Auteur | Claude Code |
| Analyses liées | — (suite relevée au Chantier 31, à la relecture d'un rapport d'audit global du 05/10/2026) |
| Commits | `48d24bf1` |

## Objectif

Empêcher qu'un code de retrait ou de réception soit trouvé par essais successifs.

Le code fait 4 chiffres, soit 10 000 valeurs. La seule protection était le plafond général de 100 requêtes par minute et par utilisateur : un livreur pouvait trouver le code de réception d'une commande en moins de deux heures et se faire régler une course non livrée. Par USSD et SMS, le contrôleur vérifiait le rôle livreur mais pas que le livreur était celui de la commande : tout livreur pouvait tenter le code de n'importe quelle commande.

## Décisions retenues

Décisions d'Inza Bamba du 05/10/2026 :

- traiter ce point en premier parmi les suites du Chantier 31 ;
- durées de suspension : 5 minutes, puis 30 minutes, puis 1 heure pour chaque série suivante (proposition initiale : 15 minutes, 1 heure, 24 heures).

## Périmètre

- Inclus : `OrderService` (`verifyPickup`, `verifyDelivery`), `Order`, `UssdController`, `NotificationCatalog`, `config/prosartisan.php`.
- Exclu : application mobile (elle affiche le message renvoyé par le serveur) ; déblocage manuel par un administrateur ; longueur du code.

## Étapes

1. **Compteur par commande et par code** — migration `2026_10_05_110000` : `pickup_code_attempts`, `pickup_code_locked_until`, `reception_code_attempts`, `reception_code_locked_until`, masquées dans la sérialisation de la commande.
2. **Suspension** — `OrderService::assertCodeAccepted` : après 5 codes faux, tout essai est refusé sans être comparé, le bon code compris. Un essai pendant la suspension ne la prolonge pas. Un bon code remet le compteur à zéro.
3. **Compte durable** — le compteur s'enregistre dans sa propre transaction, close avant le refus, sur une ligne verrouillée : dans la transaction de la validation, l'exception aurait annulé le compte, et des essais simultanés se seraient comptés pour un.
4. **USSD et SMS** — `UssdController::orderFor` : seul le livreur de la commande ou un administrateur présente un code. Un autre livreur reçoit la réponse d'une commande inconnue.
5. **Alerte** — événement `commande.code_suspendu.admin` (push seul, sans le code) à chaque suspension.

## Règles d'or concernées

8 (validation hors ligne), 36 (ressource désignée à un acteur), 38 (codes secrets), 80 (catalogue des notifications), 54, 70, 77. Nouvelle : 111 de `CLAUDE.md` (147 d'`AGENTS.md`).

## Vérification

- **Pest** : `Chantier32OrderCodeAttemptLimitTest.php` (11 tests, écrits avant le correctif : 10 échouaient).
- **Suite complète** : SQLite et MariaDB 11.8 locale.
- **Manuel** : sur appareil, saisir cinq codes faux puis le bon ; vérifier le message, la reprise après 5 minutes et l'alerte reçue par un administrateur.

## Écarts

- **Probabilité résiduelle** : avec ces durées, un livreur qui s'acharne peut tenter environ 130 codes par jour sur une commande, soit un peu plus de 1 % de chances de trouver le bon en 24 heures. Chaque suspension alerte les administrateurs.
- **Suspension partagée** : le compteur est par commande ; un client ou un fournisseur qui se trompe cinq fois est suspendu comme le livreur.
- **Code de réponse** : la suspension répond 400, comme les autres refus de ces routes.

## Suites

- **File d'attente hors ligne du mobile** : il n'a pas été vérifié si une validation refusée est rejouée automatiquement ; un code faux mis en file consommerait alors plusieurs essais.
- **Déblocage manuel** : aucun écran du backoffice ne lève une suspension.
- Suites du Chantier 31 non traitées : adresse du carnet sans position, tarif dépendant du serveur public OSRM, `is_fallback` trompeur, appels OSRM des tests à simuler.

## Écarts constatés après livraison

- **Code de réponse de la suspension** (Chantier 33, 05/10/2026) : la suspension répond désormais 429 avec `Retry-After`, et non plus 400, pour que l'application garde en file un bon code rejoué pendant une suspension.
- **File d'attente hors ligne** (Chantier 33) : vérifiée — un code mis en file ne consomme qu'un essai au rejeu. Les refus n'étaient en revanche jamais montrés à l'utilisateur ; corrigé au Chantier 33.
