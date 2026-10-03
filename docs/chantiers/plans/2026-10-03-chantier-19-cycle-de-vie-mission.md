# Plan — Chantier 19 : cycle de vie des missions (machine à états fiable, validation finale, annulation, délai de réponse)

| Champ | Valeur |
| --- | --- |
| Statut | livré (contrôle manuel du parcours sur l'application à faire) |
| Créé le | 2026-10-03 |
| Mis à jour le | 2026-10-03 |
| Auteur | Claude (à la demande d'Inza Bamba) |
| Analyses liées | `../analyses/2026-10-03-analyse-module-mission.md` |
| Commits | — |

## Objectif

Rendre le cycle de vie d'une mission fiable et complet :

1. tout changement d'état passe par la machine à états, avec ses gardes et son historique ;
2. le client valide la fin du chantier ;
3. une mission peut être annulée, avec remboursement du séquestre et pénalité après financement ;
4. une demande restée 24 heures sans réponse de l'artisan revient en recherche d'artisan ;
5. les défauts visibles relevés par l'analyse sont corrigés.

## Décisions prises (03/10/2026)

- **Validation finale** : le client valide la fin du chantier, en plus de la validation de chaque étape.
- **Annulation après financement** : le montant en séquestre est rétrocédé au client, moins une pénalité de 7 %, réglable dans le backoffice.
- **Délai de réponse de l'artisan** : 24 heures.

## Décisions complémentaires (03/10/2026)

1. **La pénalité de 7 % revient à la plateforme** (compte des commissions).
2. **Annulation hors litige** : permise tant que le chantier n'a pas commencé et qu'aucun bon matériel n'a été utilisé. Au-delà, le désaccord passe par un litige.
3. **La validation finale ne retient aucun fonds** : chaque étape reste payée sur son code OTP ; la validation finale clôt la mission ; sans réponse du client sous 72 heures, la clôture est automatique.
4. **Historique reconstitué** pour les missions déjà financées ou clôturées (lot 7).

## Périmètre

- Inclus : serveur (machine à états, services, routes, commandes planifiées, réglages), application mobile (boutons et états), backoffice (réglages, libellés), notifications du catalogue.
- Exclu : refonte des modules devis, jalons et litiges au-delà de leurs changements d'état de mission ; onglet Missions du backoffice hors libellés.

## Étapes

### Lot 1 — Machine à états fiable

1. **Point d'entrée unique** : `MissionService::transition(Mission, état, acteur, motif, contexte)`. Il applique les gardes, écrit l'historique avec l'acteur et le motif reçus en paramètre (plus de lecture du champ `reason` de la requête), et s'exécute en transaction.
2. **Remplacer les six écritures directes** (`WalletService.php:226` et `:577`, `LitigeService.php:91`, `:414`, `:421`, `:434`) par ce point d'entrée.
3. **Graphe des transitions complété** pour refléter le parcours réel :
   - `draft` → `pending_funding` quand le paiement de l'acompte est initié ; `pending_funding` → `funded_locked` à la confirmation ; `pending_funding` → `draft` si le paiement échoue ou expire ;
   - `in_progress` → `pending_approval` quand la dernière étape est payée (lot 2) ;
   - transitions vers `cancelled` du lot 3.
4. **Gardes réelles** :
   - vers `funded_locked` : devis accepté et paiement confirmé ;
   - vers `completed` : toutes les étapes payées, seuil Référent lu dans `config('prosartisan.mission.referent_threshold')` avec un seul comparateur, partout ;
   - vers `disputed` : gel des fonds.
5. **Une seule définition de « mission financée »** sur le modèle, utilisée par la ressource, la carte du chantier et l'anti-contournement.
6. **Forçage administrateur** : une transition refusée répond 422 avec un message en français ; liste des états alignée sur les neuf états.
7. **Nettoyage** : suppression de `WalletService::refundClient` et `payArtisan`.
8. **Garde de non-régression** : test qui relit les sources et refuse toute écriture directe du statut d'une mission hors `MissionService`.

### Lot 2 — Validation finale du chantier

1. Quand la dernière étape est payée, la mission passe en `pending_approval` au lieu de `completed`. Le client est notifié.
2. `POST /missions/{mission}/approve-completion`, réservé au client de la mission : passage à `completed`, notification de l'artisan, notation ouverte.
3. Commande planifiée `missions:auto-approve-completion` (toutes les heures) : clôture automatique après le délai `mission_final_approval_hours` (72 par défaut), tracée dans l'historique avec le motif « sans réponse du client ».
4. Depuis `pending_approval`, le client peut ouvrir un litige au lieu de valider (transition existante).
5. Mobile : bouton « Valider la fin du chantier » dans l'écran de suivi, état « En attente de votre validation ». Les versions déjà installées affichent « en cours » jusqu'à la clôture automatique.

### Lot 3 — Annulation

1. **Avant tout paiement** (`draft`, `pending_artisan_acceptance`, `pending_funding` sans paiement confirmé) : `POST /missions/{mission}/cancel`, réservé au client, motif facultatif, sans frais. L'artisan sollicité est prévenu.
2. **Après financement** (selon la décision 2) :
   - refus si un bon matériel a été utilisé ou si une étape est soumise, avec renvoi vers le litige ;
   - bons matériels encore actifs annulés ;
   - calcul sur la part de la mission dans le séquestre (`getMissionEscrowBalance`, Règle d'or 36), jamais sur un montant envoyé par l'application ;
   - pénalité = taux réglable × séquestre de la mission, arrondie à l'entier ; le client reçoit le reste par le circuit des versements existant (débit au seul virement réussi, relance, Règle d'or 74) ;
   - écritures de ledger pour le remboursement et pour la pénalité, au profit du bénéficiaire retenu (décision 1) ;
   - opération idempotente : une seconde demande ne rembourse pas deux fois.
3. **Aperçu avant confirmation** : `GET /missions/{mission}/cancellation-preview` renvoie le séquestre, la pénalité et le montant remboursé calculés par le serveur ; le mobile les affiche dans la confirmation.
4. **Réglage** : `settings.mission_cancellation_penalty_rate` (7 par défaut, borné de 0 à 100), modifiable dans l'onglet Paramètres du backoffice, modification auditée. Le taux appliqué est figé sur la mission à l'annulation.
5. Colonnes `cancelled_at`, `cancelled_by`, `cancellation_reason`, `cancellation_penalty` (migration idempotente, Règle d'or 55).

### Lot 4 — Délai de réponse de l'artisan (24 heures)

1. Colonne `artisan_assigned_at`, renseignée à chaque assignation.
2. Commande planifiée `missions:expire-artisan-requests` (toutes les heures) : au-delà de `mission_artisan_response_hours` (24 par défaut), la mission revient en `draft` sans artisan, avec le motif « sans réponse de l'artisan » dans l'historique. Le client est invité à choisir un autre artisan ; l'artisan est prévenu que la demande lui est retirée.
3. Relance de l'artisan à mi-délai (12 heures), une seule fois.
4. Mobile : l'écran du client affiche l'échéance de réponse.

### Lot 5 — Logique dans le service et défauts visibles

1. Acceptation, refus et assignation déplacés dans `MissionService`, en transaction ; l'artisan remplacé est prévenu ; l'état du compte de l'artisan est contrôlé.
2. Création : message d'erreur générique en français, journal sans le contenu de la requête ; le numéro de paiement n'est plus modifié à la création d'une mission.
3. Filtre de liste groupé correctement pour tous les rôles.
4. Mobile : retrait du bouton « Demarrer » (le chantier démarre à la soumission de la première étape) ; libellés accentués.

### Lot 6 — Contrat avec le mobile

1. `MissionResource` expose `statusLabel` (`MissionState::labelFor`) et l'échéance utile à l'état courant ; valeurs factices retirées (`platformFeesBreakdown`, `tokenCode`) ; `paymentStatus` exact pour une mission annulée sans paiement.
2. Estimation et type d'intervention : plus de valeur par défaut inventée ; une estimation absente vaut `null` et s'affiche « Estimation indisponible ».
3. Mobile : les neuf états affichés avec leur libellé ; tests de `MissionsController`.

### Lot 7 — Reconstitution de l'historique des missions existantes

1. Service `MissionHistoryBackfillService` : pour chaque mission dont l'historique est incomplet, il reconstitue les transitions manquantes à partir des faits enregistrés, chacune à sa date réelle :
   - création de la mission ;
   - paiement d'acompte confirmé (financement) ;
   - première étape soumise (démarrage du chantier) ;
   - ouverture d'un litige, puis sa résolution ;
   - dernière étape payée (clôture) ;
   - état courant de la mission, pour terminer la chaîne.
2. Chaque ligne reconstituée est marquée comme telle (`metadata_json.reconstitue = true`, sans acteur) : l'historique distingue ce qui a été enregistré sur le moment de ce qui a été déduit. Une date inconnue n'est jamais inventée : la ligne porte alors la mention « date non connue » et la date de dernière modification de la mission.
3. Opération idempotente : une mission dont la chaîne est déjà complète n'est pas touchée ; relancer l'opération n'ajoute rien.
4. Commande `missions:backfill-state-history` (rapport par défaut, `--fix` pour écrire), et migration de données qui l'applique une fois au déploiement, hors tests.
5. L'API `state-history` expose le marqueur ; le mobile et le backoffice affichent « reconstitué » sur ces lignes.

## Règles d'or concernées

- 2, 4, 5 : ratio de séquestre inchangé, paiement d'étape toujours sur OTP, seuil Référent à la clôture.
- 9, 36, 45, 74 : toute sortie de fonds a son écriture de ledger, se juge sur la part de la mission, est idempotente et passe par le circuit des versements.
- 17 : réglage de la pénalité et forçage audités.
- 27, 35 : états stockés en clé technique, affichés en français.
- 29 : aucune valeur inventée.
- 49 : logique dans les services.
- 55 : migrations idempotentes.
- 76 : commandes planifiées dans `routes/console.php` (`ScheduledCommandsTest`).
- 80 : nouvelles notifications par le catalogue, en push seul.
- 54, 70, 77 : PRD, règles, manuel et journal dans le même commit.

## Vérification

- **Lot 1** : parcours complet (demande, devis, paiement, étapes, clôture) avec historique exhaustif ; garde de sources ; forçage refusé en 422 ; mission au-dessus du seuil non clôturable sans Référent, par tous les chemins, litige compris.
- **Lot 2** : passage en `pending_approval` à la dernière étape ; validation par le client seul ; clôture automatique à 72 heures ; litige depuis `pending_approval`.
- **Lot 3** : annulation gratuite avant paiement ; après financement, pénalité et remboursement exacts, taux modifié pris en compte, refus si matériel retiré ou étape soumise, double demande sans double remboursement, virement en échec relançable, tiers refusé ; test échouant avant correctif pour chaque règle.
- **Lot 4** : retour en `draft` à 24 heures, relance unique à 12 heures, acceptation à 23 heures conservée.
- **Lots 5 et 6** : tests du service, de la ressource, du contrôleur mobile.
- **Lot 7** : mission financée, mission clôturée, mission passée par un litige : chaîne reconstituée dans l'ordre et aux bonnes dates, lignes marquées ; second passage sans effet ; mission à l'historique déjà complet intacte.
- Suite Pest sur SQLite et MariaDB 11.8, Vitest, `flutter test`, puis contrôle manuel du parcours sur l'application.

## Écarts

- **Point d'entrée dans un service dédié** : `MissionLifecycleService`, et non `MissionService::transition`. Portefeuille, devis, étapes et litiges l'appellent tous ; le placer dans `MissionService` aurait créé une dépendance circulaire. L'annulation est dans `MissionCancellationService` pour la même raison.
- **Arbitrage de litige** : il clôture la mission sans attendre les étapes restantes (contexte `arbitrage`). **Décision du 03/10/2026** : le seuil Référent s'applique à toute décision qui verse des fonds à l'artisan (en sa faveur ou responsabilité partagée). Au-delà du seuil, la décision est refusée (422) tant que la visite du Référent n'est pas enregistrée ; le refus marque la mission `referent_required` et prévient les Référents ; la mission en litige figure dans la liste du Référent, dont la visite ne paie aucune étape et prévient les administrateurs. Les décisions en faveur du client et de gel ne sont pas concernées.
- **Litige sur une mission déjà clôturée ou annulée** : désormais refusé par la machine à états. L'écriture directe l'acceptait.
- **Arbitrage d'un litige créé sans passer par l'ouverture** : la mission est d'abord placée en litige, puis arbitrée ; l'historique garde les deux lignes.
- **Paiement d'acompte abandonné** : la mission reste « en attente de financement » ; elle revient en brouillon si le devis est refusé, et peut être annulée sans frais.
- **Numéro de paiement à la création d'une mission** : conservé tel quel (numéro du compte et opérateur « wave » par défaut). Ce comportement est couvert par `MobileMoneyValidationTest` ; le retirer aurait cassé une règle existante.
- **Type d'intervention par défaut** : conservé pour les versions déjà installées de l'application, qui n'envoient pas ce champ.
- **Bouton « Demarrer »** : remplacé par « Valider l'étape » sur une mission financée, puisque le chantier démarre à la soumission de la première étape.
- **Démarrage reconstitué** : la date de soumission d'une étape n'est pas conservée ; la reconstitution date le démarrage du chantier de la première étape validée.
- **Clôture reconstituée** : pour le passé, la ligne va de « en cours » à « terminée » sans passer par l'attente de validation, qui n'existait pas.
- **Affichage de l'historique** : **ajouté au backoffice le 03/10/2026** — frise des états dans la fiche d'une mission, contrôle des historiques incomplets et reconstitution à la demande sous la liste des missions (`AdminMissionHistoryController`, `MissionHistoryService`, `Admin\MissionHistoryAdminService`, `panels/MissionHistory.tsx`). Le mobile n'affiche toujours pas l'historique.
- **Échéances dans la liste des missions** : `artisanResponseDeadline` et `finalApprovalDeadline` lisent un réglage par mission concernée ; à regrouper si la liste ralentit.
- **Contrôle manuel** : non effectué dans cette session (parcours sur l'application avec paiement réel).
