# Plan — Chantier 31 : destination d'une livraison figée sur la commande, et suite de tests sans réseau

| Champ | Valeur |
| --- | --- |
| Statut | livré (contrôle manuel à faire) |
| Créé le | 2026-10-05 |
| Mis à jour le | 2026-10-05 |
| Auteur | Claude Code |
| Analyses liées | — (relecture d'un rapport d'audit global fourni par Inza Bamba, le 05/10/2026) |
| Commits | `e1da17a3` |

## Objectif

Corriger trois défauts trouvés en confrontant au code un rapport d'audit global du 05/10/2026 :

1. la course d'une commande se calculait vers la position du compte du client, pas vers l'adresse de livraison ;
2. la suite de tests appelait de vrais services extérieurs ;
3. un job modifiait le délai d'exécution de tout le processus.

Le rapport ne signalait que le troisième, avec une correction sans effet (`public int $timeout`, lu seulement par un worker de file d'attente, absent en production).

## Décisions retenues

Demandes d'Inza Bamba du 05/10/2026 : corriger le job et bloquer les appels réseau des tests ; analyser les 26 appels OSRM restants ; corriger la destination de la course en commençant par un test qui échoue.

## Périmètre

- Inclus : `Order`, `OrderService` (création simple et groupée), `DeliveryPricingService`, `DeliveryTrackingService`, `DeliveryBatchService`, `GenerateKnowledgeSheetsJob`, `tests/TestCase.php`, `phpunit.xml`.
- Exclu : application mobile ; estimations demandées avant la commande (`OrderController`, coordonnées envoyées par l'application) ; les points listés en « Suites ».

## Étapes

1. **Destination de la course** — `calculateOrderDeliveryCost` prenait `$order->client->getPositionCoords()`. Cette méthode sert à l'acceptation de la course et au montant final révélé à la livraison : un client livré ailleurs que là où son compte est positionné payait une course vers le mauvais point. La commande groupée, elle, estimait déjà vers l'adresse.
2. **Coordonnées figées** — migration `2026_10_05_100000` : `orders.delivery_latitude` et `delivery_longitude`. Le suivi et les tournées lisaient déjà ces colonnes en repli, mais aucune migration ne les créait. Elles sont remplies à la création d'une commande en livraison.
3. **Une seule destination** — `Order::deliveryDestination()`, employée par la course, le suivi, la carte de la flotte et les tournées. Les tournées lisaient l'adresse actuelle du carnet : une modification du carnet déplaçait une livraison en cours.
4. **Appels réseau des tests** — `Http::preventStrayRequests()` dans `TestCase` ; clés OneSignal et Yandex vidées dans `phpunit.xml`. Relevé d'une exécution locale avant correction : 742 requêtes à OneSignal, 21 à Yandex (clés réelles du `.env`), 26 au serveur public OSRM.
5. **Délai d'exécution** — `GenerateKnowledgeSheetsJob` rétablit le délai précédent dans un `finally`.

## Règles d'or concernées

29, 34 (adresse figée sur la commande), 36 (décision financière sur la valeur établie par le serveur), 74 (course « à la Yango »), 54, 70, 77. Nouvelles : 109 et 110 de `CLAUDE.md` (145 et 146 d'`AGENTS.md`).

## Vérification

- **Pest** : `Chantier31OrderDeliveryDestinationTest.php` (7 tests, écrits avant le correctif : tous échouaient ; le test du calcul de course, rejoué seul contre l'ancien calcul, obtenait la position du compte au lieu de l'adresse). Un test ajouté à `Chantier23LlmKnowledgeTest.php` pour le délai d'exécution, en échec sans le correctif.
- **Suite complète** : SQLite (1316 réussis, 2 ignorés) et MariaDB 11.8 locale (1317 réussis, 1 ignoré).
- **Manuel** : commander en livraison vers une adresse éloignée de sa position, vérifier l'itinéraire affiché au livreur et le montant de la course.
- **Non vérifié** : exécution séquentielle de la suite sous Windows, cas exact où le rapport avait vu la coupure à 180 secondes.

## Écarts

- **Commandes en cours au déploiement** : la migration leur donne les coordonnées actuelles de leur adresse. Leur course finale se calcule désormais vers l'adresse ; son montant peut différer de l'estimation déjà annoncée au livreur.
- **Commandes closes** (livrées, annulées, en litige) : laissées sans coordonnées.
- **Adresse sans position** : la commande se rabat sur la position du compte du client, comme avant. Il n'a pas été vérifié que l'application enregistre toujours une position avec chaque adresse.
- **Écart du Chantier 30 levé** : l'estimation annoncée aux livreurs part maintenant de l'adresse de livraison.

## Suites

- **Appels OSRM des tests** : 26 appels restent bloqués puis suivis du repli à vol d'oiseau, avec une ligne d'avertissement chacun. Vingt-et-un demandent un trajet d'un point vers lui-même : ces tests créent la boutique avec `Geo::point()` sans argument, qui est aussi la position du client, si bien que la course tombe toujours au forfait plancher. À simuler, en séparant les positions.
- **Tarif dépendant du serveur public OSRM** : sans réponse de Yandex, le montant d'une course vient de `router.project-osrm.org`, sans garantie de service. À décider selon la présence de la clé Yandex en production.
- **`is_fallback` trompeur** : `DeliveryPricingService::estimateFare` renvoie `is_fallback: false` même quand la distance vient du repli à vol d'oiseau.
- **Codes de retrait et de réception** : 4 chiffres ; la limite de tentatives n'a pas été vérifiée.
