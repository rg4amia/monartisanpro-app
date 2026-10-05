# Plan — Chantier 34 : livraison à un point connu, estimation de course honnête, suspension levée par un administrateur

| Champ | Valeur |
| --- | --- |
| Statut | livré (contrôle manuel à faire ; clé Yandex à réactiver) |
| Créé le | 2026-10-05 |
| Mis à jour le | 2026-10-05 |
| Auteur | Claude Code |
| Analyses liées | — (suites consignées en fin des plans des Chantiers 31, 32 et 33) |
| Commits | — |

## Objectif

Solder les cinq suites laissées ouvertes par les Chantiers 31 à 33 :

1. une adresse du carnet sans position faisait encore calculer la course vers la position du compte du client ;
2. le tarif d'une course dépend du serveur public de démonstration d'OSRM quand Yandex ne répond pas ;
3. une estimation à vol d'oiseau se déclarait fiable (`is_fallback: false`) ;
4. 26 appels au serveur OSRM restaient bloqués puis avalés à chaque exécution de la suite de tests ;
5. aucun écran ne permettait de lever la suspension d'un code de commande.

## Décisions retenues

Demandes d'Inza Bamba du 05/10/2026 : traiter les points restants. Sur le point 2, information donnée le même jour : **la clé Yandex Distance Matrix a expiré** ; jusqu'à sa réactivation, chaque course est tarifée par le serveur de démonstration d'OSRM. Il faut renseigner la clé ou héberger une instance OSRM.

Choix faits pendant le chantier, à confirmer :

- l'adresse sans position est **refusée à la commande**, mais reste enregistrable dans le carnet (elle sert aussi aux demandes de travaux) ;
- en dernier recours, la course s'estime sur la **distance routière estimée** déjà calculée par `OsrmRoutingService`, et non plus en ligne droite.

## Périmètre

- Inclus — serveur : `OrderService`, `Order`, `DeliveryPricingService`, `Admin\OrderCodeSuspensionAdminService`, `AdminOrderCodeController`, `routes/web.php`, `tests/TestCase.php`, `tests/Support/Routing.php`. Backoffice : `OrderCodeSuspensionBlock`, fiche commande. Mobile : formulaire d'adresse.
- Exclu : hébergement d'une instance OSRM et réactivation de la clé Yandex (hors code) ; signal d'observabilité sur la tarification dégradée ; position obligatoire à l'enregistrement d'une adresse.

## Étapes

1. **Adresse sans position** — `OrderService::assertAddressLocated` refuse la livraison, à la commande simple et groupée, avant toute réservation de stock. L'application affiche une mention dans le formulaire d'adresse tant qu'aucune position n'est choisie.
2. **Dernier recours de la tarification** — quand ni Yandex ni OSRM ne répondent, `estimateFare` retient l'estimation routière d'`OsrmRoutingService` (vol d'oiseau × 1,3 à 25 km/h) au lieu de la ligne droite à 40 km/h.
3. **Estimation déclarée** — `is_fallback` dit vrai et `distance_source` nomme la source de la distance.
4. **Tests** — `Tests\Support\Routing::fakeOsrm()` dans les sept fichiers concernés ; `TestCase` fait échouer un test dont un appel extérieur bloqué a été avalé par un repli. Dix tests créaient une adresse de livraison sans position : ils lui en donnent une.
5. **Levée d'une suspension** — route `POST /admin/orders/{order}/codes/unlock` (`admin.missions.manage`), motif obligatoire, audit ; bloc « Saisie de code suspendue » dans la fiche commande ; la commande expose `code_suspensions`.

## Pertinence de la situation actuelle (clé Yandex expirée)

Tant que la clé est expirée, voici ce qui se passe à chaque estimation de course :

- l'appel à Yandex échoue, puis le serveur public OSRM est interrogé (3 secondes d'attente au plus) ;
- **s'il répond**, la course est tarifée sur un itinéraire routier réel : le résultat est correct, mais il repose sur un serveur limité en débit, sans garantie de service, que son exploitant ne destine pas à la production ;
- **s'il ne répond pas**, la course se rabattait sur la ligne droite à 40 km/h, soit environ un tiers de moins qu'un trajet routier. Ce montant sert d'estimation annoncée, de tarif quand la trace GPS est insuffisante, et de plafond du tarif relevé au GPS (× 1,5) : un livreur au trajet honnête pouvait donc être payé moins que sa course réelle. C'est ce que l'étape 2 corrige.

Le montant final reste d'abord celui du trajet GPS réel du livreur ; l'itinéraire calculé n'en est que le repli et le plafond.

Ce que le code ne règle pas :

- **Réactiver la clé Yandex** (`YANDEX_DISTANCE_MATRIX_API_KEY` dans le `.env` de production) reste la voie la plus simple.
- **Une instance OSRM dédiée** se branche par `OSRM_BASE_URL`, sans changement de code, mais ne peut pas tourner sur l'hébergement mutualisé : il faut un serveur à part, avec les données routières de la Côte d'Ivoire.

## Règles d'or concernées

29, 34, 36, 38, 44 (Yandex, fournisseur officiel), 74, 109, 110, 111, 17, 93, 54, 70, 77. Nouvelle : 113 de `CLAUDE.md` (149 d'`AGENTS.md`).

## Vérification

- **Pest** : `Chantier34OrderFollowUpsTest.php` (11 tests) ; suite complète sur SQLite et MariaDB 11.8 locale ; zéro appel extérieur bloqué relevé dans le journal.
- **Vitest** : `OrderCodeSuspensionBlock.test.tsx` (6 tests) ; suite complète et build du backoffice.
- **Flutter** : `address_form_screen_test.dart` ; `flutter analyze` sans remarque ; suite complète.
- **Manuel** : commander en livraison vers une adresse sans position ; dans le backoffice, lever une suspension depuis la fiche d'une commande.

## Écarts

- **Clients refusés dès le déploiement** : tout client dont l'adresse a été enregistrée sans position ne peut plus commander en livraison avant de l'avoir complétée. Ces adresses n'ont pas été comptées en production.
- **Tarif en mode dégradé modifié** : une course estimée sans aucun service d'itinéraire coûte désormais environ un tiers de plus qu'avant, pour se rapprocher d'un trajet routier.
- **Aucune alerte** ne signale que la tarification tourne sans Yandex : l'expiration de la clé n'a été connue que par l'utilisateur.

## Suites

- Réactiver la clé Yandex ou brancher une instance OSRM dédiée.
- Signal d'observabilité quand Yandex ne répond plus (Règle d'or 23).
- État local du livreur après une validation hors connexion refusée (Chantier 33).
