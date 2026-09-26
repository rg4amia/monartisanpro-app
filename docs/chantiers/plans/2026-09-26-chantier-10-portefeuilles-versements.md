# Plan — Chantier 10 : Portefeuilles, versements Mobile Money et course livreur « à la Yango »

| Champ | Valeur |
| --- | --- |
| Statut | livré |
| Créé le | 2026-09-26 |
| Mis à jour le | 2026-09-26 |
| Auteur | Claude Code (session du 26/09/2026) |
| Analyses liées | — |
| Commits | `e06db5d9` |

## Objectif

Un audit du parcours portefeuille livreur (solde fictif de 25 000 FCFA affiché sur l'accueil) a mis au jour quatre défauts structurels. Ce chantier les corrige :

1. **Le livreur ne peut pas retirer ses gains** : ils sont crédités sur son `wallet_mo`, sans aucune voie de retrait.
2. **Un virement Mobile Money échoué vers l'artisan est perdu** : le portefeuille est débité avant le virement ; en cas d'échec, la transaction reste « en attente » sans relance possible, et les fonds ne sont plus visibles nulle part.
3. **Le sens des montants de l'historique est faux** : l'acompte versé par un client s'affiche en « + », comme une rentrée d'argent.
4. **La course livreur est encaissée fictivement** : l'acceptation crée une transaction Wave « confirmée » sans que le client soit débité.

## Décisions du porteur de projet (26/09/2026)

- Retrait livreur **sur le modèle du cash-out quincaillerie**, frais **0 % par défaut**, réglables (`commission_cashout_livreur`).
- Course **payée à la livraison** : rien n'est prépayé pour la course ; à la livraison, le montant final (course + bonus d'attente) est révélé au livreur et une demande de paiement Wave / Orange Money est adressée au client. Le livreur est crédité à la confirmation du paiement.
- Virement échoué : **aucun débit du portefeuille** tant que le virement n'a pas abouti, **relance** possible et **historique** de toutes les actions.

## Périmètre

- Inclus : backend (services, migrations, routes API et backoffice, commande planifiée), backoffice React (sous-onglet de l'onglet Transactions), application mobile (retrait livreur, paiement de la course par le client, révélation du montant au livreur, virements échoués de l'artisan, historique signé).
- Exclu : encaissement réel des commandes de matériaux (toujours simulé à la création de la commande — hors demande), reçu PDF des retraits livreur, remboursements client par Mobile Money.

## Étapes

1. **Module de versements Mobile Money** — tables `mobile_money_payouts` et `mobile_money_payout_events` (journal append-only), `MobileMoneyPayoutService` : tentative, relance (manuelle ou automatique avec délai croissant), versement manuel hors plateforme avec référence, annulation. Le portefeuille n'est débité qu'au virement réussi ; le séquestre d'une mission réserve les versements non aboutis (`getMissionEscrowBalance`). Branché sur `releaseJalon`, `releaseLaborEscrowToArtisan`, `releaseMaterialEscrowToArtisan`. Commande `prosartisan:retry-failed-payouts` (toutes les 15 min).
2. **Retrait des gains livreur** — table `driver_cashouts`, `DriverCashoutService` (demande, approbation, versement via le module 1, rejet), API mobile `GET/POST /driver/cashouts`, actions backoffice auditées.
3. **Course à la Yango** — l'acceptation ne crée plus de paiement (estimation seulement), le bonus d'attente s'accumule dans `orders.waiting_fee`, la livraison révèle le montant final et ouvre une demande de paiement (`POST /orders/{order}/delivery-fare/pay`) ; `PaymentService::applyConfirmedPayment` règle la course et crédite le livreur, de façon idempotente.
4. **Historique signé** — `TransactionResource` expose `direction` (`entrant` / `sortant` / `sequestre`), un libellé et un statut en français, calculés pour l'utilisateur qui consulte.
5. **Backoffice** — sous-onglet « Versements & retraits livreurs » : versements en échec avec historique, relance, versement manuel ; demandes de retrait livreur.
6. **Mobile** — écran de retrait livreur, dialogue de montant de course, paiement de la course côté client, virements en échec côté artisan, historique signé.

## Règles d'or concernées

9 (ledger), 29 (aucune donnée inventée), 35 (français), 36 (propriété des ressources, idempotence des confirmations, toute libération tracée), 17 (audit admin), 49 (couche service), 55 (migrations idempotentes), 47 (portabilité MariaDB / SQLite).

## Vérification

- Pest : versement réussi / échoué / relancé / versé manuellement, séquestre réservé, retrait livreur complet, course révélée et payée (idempotence), historique signé, contrôles de propriété (403).
- Vitest : sous-onglet backoffice.
- Flutter : lecture des nouveaux champs, écran de retrait, dialogue de course.

## Écarts

Livré le 26/09/2026 (commit `e06db5d9`). Vérifications : Pest 816 tests (SQLite), Vitest 314, Flutter 370 + `flutter analyze` sans remarque. **La suite MariaDB n'a pas été rejouée localement** : le job CI `tests-mariadb` fera foi.

Écarts et reliquats par rapport au plan :

- **Correctifs hors plan, trouvés pendant l'audit** : solde livreur fictif de 25 000 FCFA stocké sur le téléphone et substitué au ledger ; carte de notations livreur codée en dur ; écran Gains qui ignorait le rôle `livreur` ; tarifs de course inventés côté mobile (1 500 FCFA, 15 % du panier) remplacés par l'estimation serveur ; statut « Score Fluidité » aligné sur l'échelle 0–1000.
- **Montant final de la course** : recalculé à la livraison par le moteur tarifaire (itinéraire et majoration du moment), et non d'après la trace GPS réelle du trajet.
- **Course livrée non réglée par le client** : elle reste `a_payer` et apparaît dans les gains « en attente » du livreur ; aucune relance automatique du client ni signal d'observabilité dédié pour l'instant.
- **Non traités** : reçu PDF des retraits livreur ; remboursement client en litige (`refundClientFromDispute`, toujours sur l'ancien virement direct) ; encaissement réel des commandes de matériaux, toujours simulé à la création.
- **Déploiement du 26/09/2026 bloqué par le job Flutter** : sous Flutter 3.47 (CI), l'`ExpansionTile` du bloc « Virements en attente » posé sur le fond d'un `Container` déclenche l'assertion « ListTile background color or ink splashes may be invisible », que Flutter 3.41 (poste local) ne lève pas. Le fond est désormais peint par un `Material` (forme arrondie, bordure) ; les suites Pest SQLite et MariaDB étaient vertes.
