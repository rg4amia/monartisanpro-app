# Plan — Chantier 21 : compléments des historiques

| Champ | Valeur |
| --- | --- |
| Statut | livré (contrôle manuel à faire) |
| Créé le | 2026-10-03 |
| Mis à jour le | 2026-10-03 |
| Auteur | Claude Code |
| Analyses liées | — |
| Commits | — |

## Objectif

Lever les six limites laissées par le Chantier 20 (demande du 03/10/2026) :

1. conserver en base l'issue des litiges de commande, pour le fournisseur et le livreur ;
2. « Voir plus » dans la liste des missions ;
3. un historique complet des versements de l'artisan ;
4. un filtre des paiements par type d'opération ;
5. la pagination des commandes du client et du fournisseur ;
6. l'historique des états d'une mission visible du Référent.

## Constat préalable

Un litige de commande ne pouvait pas être clos : ni le backoffice ni l'API ne faisaient sortir une commande de l'état « En litige ». Il n'existait donc aucune issue à conserver. Le point 1 comprend l'action de clôture.

## Périmètre

- Inclus : les six points, côté serveur, backoffice et application mobile ; documentation.
- Exclu : tout mouvement de fonds à la clôture d'un litige de commande (remboursement, retenue sur le fournisseur ou le livreur). La décision est consignée, le traitement financier reste manuel.

## Étapes

1. **Litiges de commande** — table `order_disputes` (une ligne par litige : motif, auteur, état, issue, note, administrateur, dates), reprise des litiges existants par la migration, enregistrement à l'ouverture (`OrderService::openOrderDispute`), clôture par `OrderDisputeService::resolve` (issue `reclamation_acceptee` ou `reclamation_rejetee`, motif obligatoire, commande remise à « Livrée », parties notifiées, audit). Backoffice : bloc « Litige ouvert par le client » dans la fiche de la commande, capacité `admin.litiges.arbitrate`. API `GET /orders/disputes` (client, fournisseur, livreur), sans nom ni téléphone. Mobile : écran « Litiges de commandes ».
2. **Missions** — `MissionRepository::getMissionsPage`, `MissionsController::loadMoreMissions`, bouton « Voir plus ».
3. **Versements** — `GET /payouts` accepte `statut` et `per_page` ; écran « Historique des versements » avec filtres.
4. **Types d'opération** — `GET /transactions` renvoie `meta.types` (types présents dans l'historique de l'utilisateur, libellés en français) ; liste déroulante « Type d'opération » dans le portefeuille.
5. **Commandes** — `GET /orders` et `GET /supplier/orders` paginés sur demande, commandes à suivre en tête ; « Voir plus » sur les écrans du client et du fournisseur.
6. **Référent** — `GET /missions/{mission}/state-history` ouvert au Référent pour un chantier de son ressort, sans le nom des parties ; écran ouvert depuis ses inspections et ses litiges.

## Règles d'or concernées

- 17 (audit de la clôture), 22 (aucune coordonnée dans un historique lu par un tiers), 27 (libellés français), 29 (panne annoncée), 36 (propriété vérifiée, issue fixée par l'administrateur), 49 (logique dans les services), 55 (migration défensive), 80 (événements du catalogue).

## Vérification

- Pest sur SQLite et MariaDB 11.8 : `Chantier21HistoryComplementsTest.php`.
- Vitest : `OrderDisputeBlock.test.tsx`.
- Flutter : `history_test.dart`, `order_follow_up_screens_test.dart`, `wallet_controller_test.dart`, `mission_lifecycle_test.dart`.
- Contrôle manuel : clôture d'un litige de commande dans le backoffice, puis lecture de l'issue sur l'application.

## Écarts

- **Clôture sans mouvement de fonds** : la décision ne rembourse ni ne retient rien. Ce que devient l'argent quand la réclamation est acceptée (remboursement du client, retenue sur le fournisseur ou sur le livreur) est une décision métier à prendre ; d'ici là, le traitement se fait à part et la note de clôture en garde la trace.
- **Litiges antérieurs** : un litige ouvert avant ce chantier est repris « en cours » ; une commande qui en serait déjà sortie est reprise « Issue non conservée ».
- **« Voir plus » des missions** : le serveur ne renvoyant pas le nombre de pages de cette liste, le bouton s'affiche tant qu'une page revient pleine (20 missions) ; il peut donc apparaître une fois de trop, et disparaît à la page suivante.
- **Commandes** : l'écran charge désormais ses commandes page par page sans cache de repli ; une panne s'annonce au lieu d'afficher une liste vide.
- **Référent** : il lit l'historique des chantiers à visiter, visités par lui, ou au-dessus du seuil de 2 000 000 FCFA ; les autres lui restent fermés.
- **Navigation du backoffice** (hors plan, signalé le 03/10/2026) : un filtre mémorisé sur une liste renvoyait vers elle depuis n'importe quel module. `useServerTable` ne restaure plus ses filtres que sur la page de sa liste.
- **Contrôle manuel** : non effectué.
