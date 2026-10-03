# Plan — Chantier 24 : formule du Score ProsArtisan (plancher des notes, plafond d'excellence)

| Champ | Valeur |
| --- | --- |
| Statut | livré (contrôle manuel à faire) |
| Créé le | 2026-10-03 |
| Mis à jour le | 2026-10-03 |
| Auteur | Claude Code |
| Analyses liées | `2026-10-03-analyse-module-evaluations-scores.md` (constats A4 et A5) |
| Commits | — |

## Objectif

Appliquer deux des huit décisions proposées par l'analyse du module « Évaluations & Scores ».

## Décisions prises (03/10/2026)

- **Point 3 — Plancher des notes** : une étoile vaut 0 point. Auparavant, la moyenne était convertie par `moyenne / 5` : dix évaluations à 1/5 donnaient 200 points sur 1000.
- **Point 5 — Plafond d'excellence** : il s'applique au score total, bonus compris. Auparavant, il ne portait que sur la part « évaluations » et les bonus du ledger permettaient de le franchir.

Les autres points (anti-collusion, poids de la crédibilité, formule logistique, dégradation d'inactivité, ponctualité, modération) et les corrections sans décision restent ouverts.

## Étapes

- [x] `ScoreService::pillarPoints` : `(moyenne − 1) / 4 × poids du pilier`, 0 pour une moyenne de 1 ou moins ; poids lus dans `config('prosartisan.score_prosartisan.weights')`.
- [x] `ScoreService::recalculateFromLedger` : sans trois critères à 4,8 au moins, le score total (évaluations × maturité + ledger) est plafonné à `excellence_threshold` (800), lu dans la configuration.
- [x] `getScoreDetail` : `breakdown_points` et `max_points` selon la même conversion ; `excellence_threshold` exposé.
- [x] `ScoreService::recalculateAll` et migration `2026_10_03_150000_recalculate_scores_after_formula_change` : les scores stockés sont recalculés au déploiement ; les scores gelés ne bougent pas.
- [x] Mobile : conversion partagée `pillarPointsFromRating` (`lib/core/utils/score_conversion.dart`), utilisée par la fiche de l'artisan et l'écran du score.
- [x] Tests : `Chantier24ScoreFormulaTest.php`, `score_conversion_test.dart` ; `EvaluationComplianceTest` et `Sprint4ComplianceTest` alignés.

## Règles d'or concernées

14, 15, 36, 64 ; nouvelle règle 95.

## Effet sur les scores existants

| Notes reçues (10 évaluations) | Avant | Après |
| --- | --- | --- |
| 1/5 partout | 200 | 0 |
| 2/5 partout | 400 | 250 |
| 3/5 partout | 600 | 500 |
| 4/5 partout | 800 | 750 |
| 5/5 partout | 1000 | 1000 |

Tout score formé de notes inférieures à 5/5 baisse. Un artisan noté 4/5 partout passe de 800 à 750 ; l'accès au micro-crédit (700) lui reste ouvert.

## Écarts

- **Aucun décompte des comptes touchés en production** : la base de production n'a pas été consultée. Le journal du déploiement indique le nombre de scores modifiés.
- **Comptes sans évaluation** : un fournisseur ou un livreur qui n'a que des bonus est désormais plafonné à 800.
- **Parrainage** : il exige un score supérieur à 800, donc trois critères d'excellence.
- **Versions installées de l'application** : elles affichent encore les points par pilier selon l'ancienne conversion ; le score total, lui, vient du serveur.
