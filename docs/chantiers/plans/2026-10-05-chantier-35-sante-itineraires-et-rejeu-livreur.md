# Plan — Chantier 35 : services d'itinéraire surveillés, écran du livreur relu après un rejeu

| Champ | Valeur |
| --- | --- |
| Statut | livré (contrôle manuel à faire) |
| Créé le | 2026-10-05 |
| Mis à jour le | 2026-10-05 |
| Auteur | Claude Code |
| Analyses liées | — (suites consignées en fin des plans des Chantiers 33 et 34) |
| Commits | — |

## Objectif

Traiter les trois améliorations laissées ouvertes après le Chantier 34 :

1. aucune alerte ne signalait que Yandex ne répondait plus — la clé de production a expiré sans que personne ne soit prévenu, et chaque course était tarifée par le serveur public de démonstration d'OSRM ;
2. après une validation enregistrée hors connexion puis refusée, l'écran du livreur gardait la course « en route » jusqu'au rafraîchissement suivant ;
3. l'exécution de toute la suite de tests en séquentiel sous Windows, que le rapport d'audit du 05/10/2026 voyait coupée à 180 secondes, n'avait été vérifiée que par un test ciblé.

## Décisions retenues

Demande d'Inza Bamba du 05/10/2026 : traiter les trois améliorations.

## Périmètre

- Inclus — serveur : `RoutingHealthService`, `GoogleMapsService`, `OsrmRoutingService`, `Admin\AdminObservabilityService`, `AdminHealthCheckCommand`. Backoffice : `ObservabilityPanel`. Mobile : `HomeController`.
- Exclu : réactivation de la clé Yandex et hébergement d'une instance OSRM (hors code) ; fréquence de l'alerte Telegram.

## Étapes

1. **Suivi des services d'itinéraire** — `RoutingHealthService` retient, pour Yandex et OSRM, la dernière réponse correcte, le dernier échec, son motif et les échecs consécutifs. `GoogleMapsService` et `OsrmRoutingService` y enregistrent l'issue de chaque appel.
2. **Sixième signal critique** — `routing_degraded` dans `criticalCounts()`, annoncé par `admin:health-check` ; `snapshot()['routing']` alimente l'écran.
3. **Écran** — section « Calcul des courses » de Santé & Observabilité.
4. **Écran du livreur** — `HomeController` écoute la file hors connexion et relit les courses sur le serveur quand elle est vide.
5. **Suite en séquentiel** — exécutée entièrement sous Windows, sans `--parallel`.

## Règles d'or concernées

23 (observabilité et alertes), 29, 44 (Yandex, fournisseur officiel), 57 (file hors connexion), 110, 112, 113, 54, 70, 77. Nouvelle : 114 de `CLAUDE.md` (150 d'`AGENTS.md`).

## Vérification

- **Pest** : `Chantier35RoutingHealthTest.php` (7 tests) ; suite complète en parallèle sur SQLite (1346 réussis) et MariaDB 11.8 locale (1347 réussis).
- **Suite en séquentiel sous Windows** : 1339 tests réussis en 794 secondes, sans coupure. Cette exécution a démarré avant l'ajout des sept tests de ce chantier.
- **Vitest** : `ObservabilityPanel.test.tsx` (deux cas ajoutés) ; suite complète (489 tests).
- **Flutter** : `driver_missions_after_replay_test.dart` (3 tests) ; `flutter analyze` sans remarque ; suite complète (525 tests).
- **Manuel** : ouvrir Santé & Observabilité après le déploiement, clé Yandex encore expirée ; vérifier le bandeau, puis sa disparition une fois la clé rétablie et une course estimée.

## Écarts

- **Alerte répétée** : tant que la clé Yandex reste expirée, l'alerte Telegram part à chaque contrôle de santé (toutes les 15 minutes), comme pour les autres signaux.
- **État tenu en cache** : un vidage du cache du serveur remet le suivi à zéro ; le signal revient après trois nouveaux échecs.
- **Signal après usage seulement** : le suivi se nourrit des estimations de course réelles. Sans aucune course estimée, une clé expirée n'est pas signalée.
- **Retour à la normale** : le signal ne s'efface qu'à la première réponse correcte de Yandex, donc à la première course estimée après le rétablissement de la clé.
- **Rejeu sur appareil** : la relecture des courses du livreur n'est testée que par ses règles de décision ; le rejeu réel se vérifie sur appareil.

## Suites

- Réactiver la clé Yandex Distance Matrix, ou brancher une instance OSRM dédiée (`OSRM_BASE_URL`).
- Publier une nouvelle version de l'application : elle porte les corrections mobiles des Chantiers 33, 34 et 35.
