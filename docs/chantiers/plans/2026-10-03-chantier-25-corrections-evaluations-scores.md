# Plan — Chantier 25 : corrections du module « Évaluations & Scores »

| Champ | Valeur |
| --- | --- |
| Statut | livré (contrôle manuel à faire) |
| Créé le | 2026-10-03 |
| Mis à jour le | 2026-10-03 |
| Auteur | Claude Code |
| Analyses liées | `2026-10-03-analyse-module-evaluations-scores.md` |
| Commits | — |

## Objectif

Corriger les défauts relevés par l'analyse qui ne demandent aucune décision de règle : fuite de données, évalué sans lien, crédibilité, dégradation d'inactivité, doublons, backoffice, couche service.

## Étapes

- [x] **Fuite de données (C2)** : `GET /evaluations/my` ne transmet de l'autre partie que l'identifiant, le nom et le rôle.
- [x] **Livreur sans lien (C1)** : un livreur ne s'évalue plus depuis une mission, seulement depuis la commande qu'il a livrée.
- [x] **Couche service (C5)** : `EvaluationService` ; `EvaluationController` ne fait plus que valider et répondre. Le statut `terminee`, disparu, n'est plus testé.
- [x] **Message d'exception (C4)** : l'erreur interne reste dans le journal ; la réponse donne un message générique.
- [x] **Unicité (C3)** : index uniques `(mission_id, evaluateur_id, evalue_id)` et `(order_id, evaluateur_id, evalue_id)` ; la violation répond 422.
- [x] **Crédibilité (A1)** : le compte des missions terminées filtre sur `completed`.
- [x] **Évaluation concernée (A7)** : le bonus porte sur l'évaluation qui vient d'être créée, inscrit une seule fois.
- [x] **Dégradation d'inactivité (B1 à B3)** : plus de lecture de `missions.accepted_at` ; 5 points par semaine au plus, quel que soit le rythme d'appel ; aucun retrait sur un score nul ou gelé ; un artisan en erreur n'interrompt plus la commande.
- [x] **Backoffice** : recherche du classement groupée (E1) ; historique du score chargé par artisan (E2) ; « Commande # » pour une évaluation de commande (E3) ; note moyenne « Non évalué » sans évaluation (E6) ; libellés français des événements.

## Règles d'or concernées

6, 9, 10, 22, 27, 29, 36, 49 ; nouvelle règle 96.

## Vérification

- `tests/Feature/Chantier25EvaluationFixesTest.php` (17 tests) ; `DetailModals.test.tsx` ; `MultiEvaluationTest` aligné.
- Suites complètes Pest (SQLite et MariaDB), Vitest.

## Écarts

- **Dégradation d'inactivité éteinte par défaut** (`SCORE_INACTIVITY_DECAY_ENABLED=false`). Elle ne fonctionnait pas en production (la commande plantait) ; la réparer et la laisser active aurait commencé à retirer des points à des artisans réels, ce qui relève de la décision 6 de l'analyse. La commande planifiée tourne, n'échoue plus et ne modifie aucun score ; `--dry-run` liste les artisans concernés.
- **Pondération des clients institutionnels** (`client_b2b`, 1,5) : conservée telle quelle, la décision produit étant en attente ; le rôle n'existe pas en production.
- **Index unique et doublons existants** : si la production contient déjà des évaluations en double, l'index concerné n'est pas créé et le journal le signale ; les doublons ne sont pas supprimés d'office.
- **Crédibilité corrigée pour l'avenir seulement** : les lignes de bonus déjà inscrites avec l'indice 0,1 ne sont pas réécrites.
- **Non traités, faute de décision** : anti-collusion, poids de la crédibilité dans les moyennes, formule logistique, activation de la dégradation, événements de ponctualité, modération des évaluations, pénalités de litige hors de `ScoreService`, recalcul à la lecture, évaluation reçue pendant un gel.
