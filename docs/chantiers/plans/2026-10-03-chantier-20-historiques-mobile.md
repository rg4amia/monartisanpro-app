# Plan — Chantier 20 : historiques dans chaque espace de l'application mobile

| Champ | Valeur |
| --- | --- |
| Statut | livré (contrôle manuel sur appareil à faire) |
| Créé le | 2026-10-03 |
| Mis à jour le | 2026-10-03 |
| Auteur | Claude Code |
| Analyses liées | `../analyses/2026-10-03-analyse-module-mission.md` |
| Commits | — |

## Objectif

Chaque type d'utilisateur de l'application (client, artisan, fournisseur, livreur, Référent) retrouve au même endroit l'historique de ce qui le concerne : paiements, missions, litiges, commandes ou courses, retraits, inspections. Demande du 03/10/2026, à la suite du Chantier 19 (l'historique des états d'une mission n'est aujourd'hui visible que dans le backoffice).

## État des lieux (lecture du code, 03/10/2026)

| | Paiements | Litiges | Missions | Commandes / courses | Retraits | Inspections |
| --- | --- | --- | --- | --- | --- | --- |
| Client | existe (30 dernières lignes, sans filtre) | **manque** (aucune liste) | liste existe, sans « Annulées » ; **historique des états absent** | existe (accueil seulement) | sans objet | sans objet |
| Artisan | existe (idem) | **manque** (hors espace juré) | idem client | bons matériels seulement | **versements aboutis non listés** | sans objet |
| Fournisseur | existe | litiges en cours seulement | onglet « Commandes » = liste des missions, **vide** | existe | existe | sans objet |
| Livreur | existe | **manque** | onglet « Livraisons » = liste des missions, **vide** | **manque** (courses livrées jetées) | existe | sans objet |
| Référent | vide | onglet « Litiges » = formulaire de signalement, **inutilisable** | onglet « Missions » **vide** | sans objet | sans objet | **manque** (seules les inspections à faire) |

Défauts relevés au passage (à confirmer par un test avant correction) :

- `SupplierDashboardController::litiges` filtre les missions sur `status = 'litige'`, état qui n'existe plus (`disputed`, Règle d'or 27) : la liste serait toujours vide.
- L'onglet « Litiges » du Référent monte `LitigeScreen` sans que son contrôleur soit enregistré.
- `GET /driver/cashouts` n'accepte que le rôle `livreur`.

## Périmètre

- Inclus : écrans mobiles et points d'API listés dans les étapes ; manuel d'utilisation ; règles d'or.
- Exclu : backoffice (fait au Chantier 19) ; site vitrine ; export ou PDF des historiques (les reçus existants sont conservés) ; refonte des écrans existants au-delà de ce qui est listé.

## Étapes

### Lot A — Entrée commune « Mon historique »

1. **Écran `HistoryHubScreen`** (module `history`) ouvert depuis Profil › « Mon historique », pour les cinq rôles. Il liste les rubriques du rôle connecté :
   - client : Paiements, Missions, Litiges, Commandes de matériaux ;
   - artisan : Paiements et versements, Chantiers, Litiges, Bons matériels ;
   - fournisseur : Paiements, Commandes, Virements, Litiges ;
   - livreur : Gains, Courses, Retraits, Litiges ;
   - Référent : Inspections, Litiges.
2. L'entrée « Historique des Paiements » du Profil est conservée (même écran).

### Lot B — Paiements

3. **`GET /transactions`** : `last_page` dans `meta`, filtre `type` et `statut` validés (valeur inconnue → 422).
4. **Portefeuille** : chargement page par page (« Voir plus »), filtre par statut. Carte de solde masquée pour le client et le Référent, qui n'ont pas de portefeuille.
5. **Artisan** : section « Versements reçus » (versements aboutis de `GET /payouts`, en plus des versements en attente déjà affichés).

### Lot C — Missions

6. **Onglet « Annulées »** dans la liste des missions (client, artisan) ; pagination de la liste.
7. **Historique des états** dans le suivi d'une mission (client, artisan) : frise « Historique de la mission » alimentée par `GET /missions/{mission}/state-history` — libellés français, date, motif, mention « Reconstitué », « Date non conservée ». L'auteur est affiché par son rôle (« Client », « Artisan », « Administrateur », « Référent », « Automatique »), jamais par le nom d'un administrateur.
8. **Onglets hors sujet remplacés** : fournisseur « Commandes » → écran des commandes existant ; livreur « Livraisons » → écran des courses (lot E) ; Référent « Missions » → inspections (lot F).

### Lot D — Litiges

9. **Écran « Mes litiges »** (client, artisan) : `GET /litiges` avec filtre En cours / Résolus, pagination ; ouverture de la fiche existante.
10. **Fournisseur** : `GET /supplier/litiges` inclut les litiges résolus (filtre `statut`), correction du filtre d'état ; l'écran existant gagne les deux onglets.
11. **Livreur** : liste des courses en litige ou l'ayant été (`GET /orders?status=…`, lot E).
12. **Référent** : `GET /referent/litiges` — litiges des missions à visiter ou visitées par lui : motif, description, adresse du chantier, montant, état, décision. Ni téléphone ni coordonnées des parties (Règle d'or 22). L'onglet « Litiges » ouvre cette liste.

### Lot E — Commandes et courses

13. **`GET /orders`** : filtre `status` validé et pagination (`per_page`), sans changer la réponse par défaut attendue des versions installées.
14. **Livreur — « Mes courses »** : courses livrées et annulées, avec montant de la course et date ; aucun code de retrait ou de réception (Règle d'or 38).
15. **Client** : « Commandes de matériaux » accessible depuis l'historique (écran existant).

### Lot F — Référent

16. **`GET /referent/inspections`** : missions validées par le Référent connecté (`referent_validated_by`), date de la visite, montant, état actuel.
17. **Écran « Mes inspections »** : à faire (liste actuelle) et réalisées.

### Lot G — Retraits

18. Routes nommées pour les écrans de retrait livreur et fournisseur, reliées à l'historique. `GET /driver/cashouts` accepte les deux écritures du rôle livreur.

### Lot H — Documentation

19. Manuel (un paragraphe « Mon historique » par espace), `CLAUDE.md`, `AGENTS.md`, `PRD.md`, ce plan et `CHRONOLOGIE.md`, dans le même commit.

## Règles d'or concernées

- 6 et 22 : aucune position exacte ni coordonnée personnelle dans un historique consulté par un tiers (Référent, livreur).
- 27 : statuts filtrés sur les états réels, affichés en français.
- 28, 29, 75 : lecteurs JSON défensifs ; une panne de chargement s'annonce, jamais une liste vide ; cache de repli vidé dans les tests.
- 36 : chaque liste est filtrée par le serveur sur l'utilisateur connecté ; un test tente la lecture par un tiers.
- 38 : aucun code logistique dans l'historique du livreur.
- 49 : filtres et pagination dans les services, pas dans les contrôleurs.
- 62 (PRD) : contrôleurs GetX testés par le harnais partagé.

## Vérification

- Pest (SQLite et MariaDB 11.8) : un test par point d'API nouveau ou modifié, dont les accès refusés.
- Flutter : tests de contrôleurs et de rendu par écran, charges utiles incomplètes comprises ; `flutter analyze`.
- Contrôle manuel sur un appareil, un compte par rôle.

## Décisions validées (03/10/2026)

- L'auteur d'un changement d'état est affiché par son rôle, jamais par le nom d'un administrateur ou d'un Référent.
- Le Référent voit le motif, la description, l'adresse du chantier, le montant et la décision d'un litige, sans téléphone ni coordonnées des parties.

## Écarts

- **Liste des litiges et jurés** : `GET /litiges` renvoyait aussi les dossiers où l'utilisateur est juré, avec les noms et téléphones des parties. Ces dossiers en sont retirés ; l'espace juré, anonymisé, reste la seule voie (Règle d'or 76). Défaut découvert en écrivant « Mes litiges », non prévu au plan.
- **Litiges du fournisseur** : le filtre `statut` prévu n'a pas été ajouté ; la liste renvoie tous les chantiers fournis ayant connu un litige, et l'écran affiche l'état et la décision de chacun. Les commandes en litige restent celles actuellement contestées : l'issue d'une contestation de commande n'est pas conservée.
- **Litiges du livreur** : même limite, seules les courses actuellement en litige sont listées (filtre « En litige » de « Mes courses »).
- **Onglet du Référent** : « Missions » devient « Réalisées » (inspections réalisées) ; l'onglet « Inspections » garde les inspections à faire. L'écran unique « Mes inspections » à deux volets n'a pas été créé.
- **Routes nommées des retraits** (lot G) : non créées ; les écrans de retrait du livreur et des litiges du fournisseur s'ouvrent depuis « Mon historique » sans route nommée, comme ailleurs dans l'application.
- **`GET /driver/cashouts`** : inchangé. Le rôle `driver` est converti en `livreur` à l'enregistrement du compte (Règle d'or 63), le refus redouté ne peut pas se produire.
- **Filtre `type` des paiements** : accepté par l'API, non proposé à l'écran (filtre par statut seulement).
- **Pagination de la liste des missions** : non faite ; la liste reste limitée à la première page du serveur.
- **Versements reçus** : limités aux 50 derniers versements renvoyés par `GET /payouts`, sans pagination.
- **Commandes du fournisseur et du client** : écrans existants repris tels quels, sans pagination.
- **Contrôle manuel** : non effectué (un compte par rôle sur un appareil).
