# Plan — Chantier 30 : corrections des commandes de matériaux et du défi anti-robot

| Champ | Valeur |
| --- | --- |
| Statut | livré (contrôle manuel à faire) |
| Créé le | 2026-10-04 |
| Mis à jour le | 2026-10-04 |
| Auteur | Claude Code |
| Analyses liées | — (relecture d'un rapport d'audit fourni par Inza Bamba, le 04/10/2026) |
| Commits | — |

## Objectif

Corriger quatre défauts relevés en confrontant au code un rapport d'audit portant sur `OrderService`, `MissionController` et `AntiBotService`. Le rapport ne les signalait pas.

## Décisions retenues

Demande d'Inza Bamba du 04/10/2026 : traiter les quatre défauts.

## Périmètre

- Inclus : `OrderService` (annonce d'une course aux livreurs, commande groupée), `NotificationCatalog`, `AntiBotService`.
- Exclu : la recommandation du rapport d'afficher une estimation de course à la commande simple (question d'affichage, à décider) ; application mobile.

## Étapes

1. **Prix de course inventé** — `notifyDriversInArea` annonçait « 1 500 FCFA » quand le coût de course valait 0, ce qui est toujours le cas à la commande simple. Le prix annoncé est désormais l'estimation du serveur (le calcul qu'applique l'acceptation de la course) ; sans estimation possible, un message distinct n'annonce aucun montant (`course.disponible_sans_estimation.livreur`).
2. **Livreurs prévenus** — seuls les livreurs qui peuvent accepter une course : KYC actif, compte actif, non anonymisé. Le rôle `driver`, qui n'existe pas en base, n'est plus recherché.
3. **Commande groupée chez un fournisseur sans boutique** — refusée en livraison, comme la commande simple. Elle se rabattait sur la position du compte du fournisseur pour estimer la course.
4. **Clé de signature du défi anti-robot** — plus de clé de secours écrite dans le code : sans clé d'application, aucun défi n'est émis ni accepté.
5. **Anti-rejeu** — le jeton est réservé en une seule opération (`Cache::add`) ; tester puis écrire laissait passer deux requêtes simultanées.

## Règles d'or concernées

29 (aucune valeur inventée), 36, 58 (anti-robot), 63 (rôle `livreur`), 80 (catalogue des notifications), 84 et 102 (boutique du fournisseur), 54, 70, 77.

## Vérification

- **Pest** : `Chantier30OrderAndAntiBotFixesTest.php` (7 tests, écrits avant les correctifs : 6 échouaient). Suite complète sur SQLite et MariaDB 11.8.
- **Manuel** : sur appareil, notification reçue par un livreur à la mise à disposition d'une course.

## Écarts

- **Estimation annoncée** : elle part de la position du compte du client, comme le calcul appliqué à l'acceptation de la course, et non de l'adresse de livraison figée sur la commande. Le montant final reste celui révélé à la livraison.
- **Écart d'affichage conservé** : à la commande simple, `delivery_cost` vaut 0 jusqu'à l'acceptation de la course ; à la commande groupée, il porte une estimation. La course n'entre dans le montant encaissé dans aucun des deux cas.
- **Zone de couverture** : tous les livreurs en état d'accepter sont prévenus, sans filtre de distance.
- **Code de réponse** : la commande groupée refusée répond 400, comme les autres refus de cette route.
