# Plan — Chantier 26 : maturité par clients distincts et pilotage de la dégradation d'inactivité

| Champ | Valeur |
| --- | --- |
| Statut | livré (contrôle manuel à faire) |
| Créé le | 2026-10-03 |
| Mis à jour le | 2026-10-03 |
| Auteur | Claude Code |
| Analyses liées | `2026-10-03-analyse-module-evaluations-scores.md` (constat A3, décisions 1 et 6) |
| Commits | — |

## Objectif

1. Empêcher qu'un seul client complice porte un artisan au score maximal (constat A3).
2. Donner à l'administrateur le moyen d'activer et de désactiver la dégradation d'inactivité depuis le backoffice, sans toucher à la configuration du serveur.

## Étapes

- [x] **Maturité par clients distincts** : `ScoreService::maturityFactor` compte les clients distincts ayant noté le compte, plus les évaluations. Cible lue dans `prosartisan.score_prosartisan.maturity_clients_target` (10).
- [x] **Détail du score** : `distinct_clients` ; `maturity_missions_count` et `maturity_percentage` portent sur les clients distincts.
- [x] **Classement du backoffice** : nombre de clients distincts sous le nombre d'évaluations.
- [x] **Réglage de la dégradation** : `settings.score_inactivity_decay_enabled` ; tant qu'il n'existe pas, la configuration du serveur fait foi.
- [x] **Service** `Admin\InactivityDecayAdminService` : état, artisans visés, pénalités et points retirés sur 30 jours, activation et désactivation auditées.
- [x] **Route** `PUT /admin/evaluations/inactivity-decay` (`admin.settings.manage`).
- [x] **Écran** : carte « Dégradation d'inactivité » du sous-onglet « Scores ProsArtisan Artisans », confirmation par `useConfirm`.
- [x] **Éditeur générique des réglages** : ce réglage n'y figure pas et y est refusé.

## Règles d'or concernées

14, 15, 16, 17, 29, 49, 64, 95, 96 ; nouvelle règle 97.

## Vérification

- `tests/Feature/Chantier26AntiCollusionAndDecayToggleTest.php` (15 tests) ; `InactivityDecayCard.test.tsx` (6 tests).
- Tests alignés sur dix clients distincts : `Chantier24ScoreFormulaTest`, `Chantier25EvaluationFixesTest`, `MicroCreditWorkflowTest`, `Sprint4ComplianceTest`.
- Suites complètes Pest (SQLite et MariaDB), Vitest.

## Écarts

- **Effet sur les scores existants** : au déploiement, la migration de recalcul du Chantier 24 applique la nouvelle maturité. Un artisan noté par peu de clients différents voit son score baisser, et peut perdre le micro-crédit, le marqueur doré ou un siège de juré.
- **Moyennes inchangées** : les évaluations répétées d'un même client pèsent toujours dans les moyennes des critères et rapportent chacune leur bonus de 5 points. Seule la maturité est protégée.
- **Fournisseurs et livreurs** : même règle, leur score passant par le même calcul.
- **Dégradation toujours éteinte par défaut** : son activation reste une décision, désormais prise à l'écran. Les points retirés ne sont pas rendus à la désactivation.
- **Seuil de 60 jours et 5 points** : non réglables depuis le backoffice.
