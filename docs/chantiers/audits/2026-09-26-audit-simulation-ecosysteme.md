# Audit — Simulation de bout en bout de l'écosystème ProsArtisan

| Champ | Valeur |
| --- | --- |
| Réalisé le | 2026-09-26 |
| Auteur | Claude Code (Opus 5.5), à la demande d'Inza Bamba |
| Plan vérifié | aucun (campagne de test transverse) |
| Commit audité | `3c87fd6d` |

## Périmètre

Une journée type de la plateforme, rejouée **uniquement par l'API**, du point de vue de chaque acteur : client, deux artisans, quincaillerie, livreur, administrateur. Le scénario est automatisé dans `backend-proartisan/tests/Feature/EcosystemJourneyTest.php` et affiche son récit étape par étape. Il tourne sur la base de test (SQLite en CI, MariaDB 11.8 avec le job `tests-mariadb`) : ni vrai SMS, ni vrai paiement, ni donnée de production.

Parcours couverts :

1. Demande de devis : refus par l'artisan A, réaffectation à l'artisan B, tentative d'un artisan non désigné.
2. Devis : refus par le client, nouveau devis, acceptation, paiement Wave simulé.
3. Achat des matériaux par J-Code, puis paiement des deux étapes par OTP et évaluation de l'artisan.
4. Seconde mission : litige, preuves géolocalisées des deux parties, arbitrage administrateur « mixte ».
5. Commande de matériaux avec adresse de livraison, préparation, course acceptée par le livreur, retrait, livraison, règlement de la course, évaluations.
6. Décaissements : retrait des gains du livreur, cash-out de la quincaillerie, validés depuis le backoffice.
7. Audits : jalons hybrides (`prosartisan:reconcile-hybrid-jalons`), grand livre en partie double (`ledger:verify-integrity`), trésorerie (`prosartisan:reconcile-treasury`).

Non couverts : application mobile et backoffice côté interface (seule l'API est exercée), passerelles USSD/SMS, micro-crédit, recrutement, jury de pairs, vrais opérateurs Mobile Money.

## Résultats

| Point contrôlé | État | Détail |
| --- | --- | --- |
| Demande de devis réservée à l'artisan désigné | ✅ conforme | Artisan non désigné : 403. |
| Refus puis réaffectation de la demande | ✅ conforme | Mission remise en `draft`, puis `pending_artisan_acceptance` pour le nouvel artisan. |
| Devis avant acceptation de la demande (Règle d'or 43) | ❌ non conforme → corrigé | Le serveur acceptait le devis (201). Voir Anomalies. |
| Acceptation du devis réservée au client (Règle d'or 37) | ✅ conforme | Artisan : 403. |
| Montant du séquestre fixé par le serveur (Règle d'or 36) | ✅ conforme | 100 FCFA postés, 86 500 FCFA encaissés. |
| Scan J-Code hors zone (Règle d'or 3) | ✅ conforme | Scan à 2 km : 422, transaction bloquée. |
| Libération sans OTP valide (Règle d'or 4) | ✅ conforme | OTP erroné : 422, étape non payée. |
| Litige : gel des fonds et confidentialité de la fiche | ✅ conforme | Fonds gelés ; fiche refusée à un tiers (403). |
| Arbitrage réservé à l'administrateur | ✅ conforme | Client : refus (422). Arbitrage mixte : 60 000 FCFA remboursés au client. |
| Commande vers l'adresse d'un autre client (Règle d'or 34) | ⚠️ écart documentaire → corrigé | Refus effectif, en 422 et non en 403 comme l'annonçait la règle. |
| Préparation d'une commande impayée | ✅ conforme | 400. |
| Codes logistiques (Règle d'or 38) | ✅ conforme | Aucun code transmis au livreur ; « RET-{n° de commande} » refusé. |
| Course « à la Yango » | ✅ conforme | Course révélée à la livraison, réglée par le client, livreur crédité. |
| Retrait livreur : plafond et absence de débit anticipé (Règle d'or 74) | ✅ conforme | Demande au-delà du solde : 422 ; rien n'est débité avant le versement. |
| Cash-out quincaillerie | ✅ conforme | Approuvé puis versé depuis le backoffice. |
| Grand livre en partie double | ✅ conforme | Débits = crédits. |
| Jalons hybrides | ✅ conforme | Aucune anomalie. |
| Audit de trésorerie des commandes | ❌ faux positif → corrigé | Voir Anomalies. |

## Anomalies

1. **Faux positif de `prosartisan:reconcile-treasury` (gravité moyenne).** L'audit ne comptait que les acomptes versés au séquestre d'une commande. Depuis le Chantier 10, la course est payée à part (`paiement_livraison`) vers `escrow_order_{id}`, et `revealDeliveryFare` l'ajoute au `total_amount`. Chaque commande livrée et réglée était donc signalée. Dans la simulation : « attendu 22 765 FCFA, déposé 20 600 FCFA », alors que le séquestre avait bien reçu 20 600 + 2 165 FCFA. Une commande livrée dont la course restait à régler l'aurait été aussi. Aucune perte d'argent, mais les vraies anomalies étaient noyées. **Correctif :** l'audit compte l'acompte et le paiement de course, y compris dans un panier groupé, et n'attend pas une course révélée non encore réglée (`Order::deliveryFareDue()`). Quatre tests ont été ajoutés dans `ReconcileTreasuryCommandTest` ; ils échouaient avant le correctif, et l'un d'eux garantit qu'une commande réellement sous-payée reste signalée.
2. **Règle d'or 43 appliquée par le seul masquage du bouton mobile (gravité faible).** `DevisService::create` vérifiait que l'artisan était le bon, mais pas que la demande avait été acceptée : un appel direct à l'API sautait l'étape. **Correctif :** refus en 422 tant que la mission est en `pending_artisan_acceptance`. Un test a été ajouté dans `DevisGestionRulesTest` ; il échouait avant le correctif. `MobileMoneyValidationTest` profitait du contournement : sa mission est désormais placée après acceptation.
3. **Texte de la Règle d'or 34 inexact.** Il annonçait un 403 pour l'adresse d'un tiers. Le serveur répond 422 « Adresse de livraison invalide. », la même réponse qu'une adresse inexistante. Le code a été conservé et le texte corrigé dans `CLAUDE.md` et `PRD.md`.

## Suites

- Base locale alignée sur la production : conteneur Docker `prosartisan-mariadb`, image `mariadb:11.8` (11.8.9, identique à Hostinger), `127.0.0.1:3308`, volume `prosartisan-mariadb-data`, bases `prosartisan` (développement, migrée et alimentée par les seeders) et `prosartisan_test`. Le `.env` local pointe dessus ; l'ancienne configuration MySQL 8.4 (WAMP) y est conservée en commentaire.
- Suite Pest complète après correctifs : SQLite 889 réussis, 2 ignorés, 0 échec ; MariaDB 11.8.9 local (même moteur que la production et que le job CI `tests-mariadb`) 890 réussis, 1 ignoré, 0 échec.
- Le scénario `EcosystemJourneyTest` rejoint la suite bloquante de la CI : toute régression d'un module qui casse l'enchaînement de la journée type bloque désormais le déploiement.
