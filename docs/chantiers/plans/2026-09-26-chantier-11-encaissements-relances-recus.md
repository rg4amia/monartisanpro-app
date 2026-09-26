# Plan — Chantier 11 : encaissement réel des commandes, relances de course, course au trajet GPS, reçus PDF

| Champ | Valeur |
| --- | --- |
| Statut | livré |
| Créé le | 2026-09-26 |
| Mis à jour le | 2026-09-26 |
| Auteur | Claude Code (session du 26/09/2026) |
| Analyses liées | [Chantier 10](2026-09-26-chantier-10-portefeuilles-versements.md) (reliquats) |
| Commits | `346e3ddc`, `7b87755b` |

## Objectif

Solder les reliquats du Chantier 10, à la demande du porteur de projet (26/09/2026) :

1. **Course impayée** : relancer le client (alerte backoffice, notification push, SMS). Un client qui ne règle pas sa course malgré 5 relances est présumé l'avoir payée hors plateforme — infraction aux conditions d'utilisation — et son compte est restreint.
2. **Montant final de la course** recalculé sur le trajet GPS réel, et **reçu PDF** pour toute opération de paiement (retrait livreur compris).
3. **Remboursement client après litige** : relances (livré au Chantier 10, commit `02dba1bd`) et **choix par le client du moyen de remboursement**.
4. **Encaissement réel des commandes de matériaux** : la commande était créée « payée » avec une transaction Wave confirmée que rien n'avait encaissée.

## Choix par défaut (non précisés, réglables)

| Réglage (`settings`) | Défaut | Rôle |
| --- | --- | --- |
| `delivery_fare_reminder_interval_hours` | 24 | Délai entre deux relances d'une course impayée |
| `delivery_fare_reminder_max` | 5 | Relances avant restriction du compte |
| `order_payment_timeout_minutes` | 30 | Délai de paiement d'une commande avant annulation et remise en stock |

**Restriction** : le client ne peut plus passer de commande ni publier de mission tant qu'une course reste impayée ; il peut toujours payer, consulter et suivre. Elle se lève automatiquement au paiement de la dernière course due, ou par un admin (audité). Ce n'est pas un blocage du compte (`account_status`), qui interdirait aussi de payer.

## Étapes

### Lot A — Relances et restriction
- `orders.delivery_fare_reminders_count`, `delivery_fare_last_reminder_at` ; `users.payment_restricted_at`, `payment_restriction_reason`.
- `DeliveryFareCollectionService` : relance due (push + SMS + notification en base), relance manuelle admin, restriction au-delà du plafond, levée automatique au paiement, levée admin.
- Commande planifiée `prosartisan:remind-unpaid-delivery-fares` (toutes les heures).
- Middleware `payment.unrestricted` sur la création de commande et de mission.
- Backoffice : sous-onglet « Courses impayées » (relances, relance manuelle, comptes restreints, levée).

### Lot B — Course au trajet GPS réel et reçus
- `DeliveryPricingService::fareFromTrip` (même barème que l'estimation) ; `OrderService::measureTrip` sur `delivery_trackings` entre le retrait et la livraison (points aberrants écartés). Garde-fous : trace insuffisante → estimation ; trajet supérieur à 1,5 × l'itinéraire → plafonné et signalé. Le temps d'attente déjà rémunéré par le bonus est retiré de la durée.
- Reçu PDF de toute transaction confirmée de l'utilisateur : `GET /api/v1/transactions/{transaction}/receipt-link` (propriété vérifiée) → URL signée 15 min ; libellés des nouveaux types (course, commande, retrait livreur, remboursement).
- Mobile : bouton « Reçu PDF » dans l'historique et sur les retraits livreur versés.

### Lot C — Moyen de remboursement choisi par le client
- `PUT /api/v1/payouts/{payout}/destination` (bénéficiaire d'un remboursement non abouti) : opérateur + numéro, verrouillés, consignés dans l'historique.
- `PUT /api/v1/litiges/{litige}/refund-destination` : préférence déclarée à l'avance par le client de la mission, utilisée au remboursement.
- Mobile : « Modifier le moyen de remboursement » sur la carte du virement en attente.

### Lot D — Encaissement réel des commandes
- Commande créée `pending` (stock réservé, échéance de paiement) ; paiement Wave / Orange Money / virement via `PaymentService` (type `order`) ; confirmation (`applyConfirmedPayment`, idempotente, montant et payeur revérifiés) → `paid` et notification du fournisseur.
- Commande expirée : annulée, stock et code promo restitués (`prosartisan:expire-unpaid-orders`, toutes les 5 min, après interrogation de l'opérateur). Paiement confirmé après annulation : commande réactivée si le stock le permet, sinon alerte admin pour remboursement.
- Relance du paiement d'une commande en attente : `POST /api/v1/payments/orders/{order}/checkout`.
- Mobile : choix Wave / Orange Money au paiement, ouverture de la page opérateur, suivi, bouton « Payer » sur une commande en attente.
- Correctif : `startCheckout` écrasait `metadata.order_id` par la référence Orange Money.

## Règles d'or concernées

9, 17, 28, 29, 35, 36 (propriété, montants fixés par le serveur, idempotence des confirmations), 40 (URL signée), 47, 49, 54, 55, 70.

## Vérification

Pest (tests en échec avant correctif pour chaque défaut), Vitest (sous-onglet), Flutter (modèles, contrôleurs), suite MariaDB en CI.

## Écarts constatés (26/09/2026)

Serveur, backoffice et paiement mobile des commandes livrés (`346e3ddc`). Les écrans mobiles suivants manquaient, les routes serveur existant déjà ; ils sont livrés depuis :
- bouton « Reçu PDF » sur l'historique des transactions et les retraits livreur (`ReceiptOpener`, lien signé de `GET /transactions/{id}/receipt-link` ouvert dans le navigateur) ;
- « Modifier le moyen de remboursement » sur la carte d'un remboursement non abouti (`PUT /payouts/{payout}/destination`) et choix anticipé depuis la fiche litige, réservé au client (`PUT /litiges/{litige}/refund-destination`) — dialogue partagé `RefundDestinationDialog` (Wave, Orange Money, numéro ramené au format `+225`).

Tests : `receipts_and_refund_destination_test.dart`.
