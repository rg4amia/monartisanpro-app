# Analyse — Module mission

| Champ | Valeur |
| --- | --- |
| Créée le | 2026-10-03 |
| Auteur | Claude (à la demande d'Inza Bamba) |
| Plans issus de cette analyse | `../plans/2026-10-03-chantier-19-cycle-de-vie-mission.md` |

## Question posée

Dans quel état est le module mission — cycle de vie, règles métier, contrat avec l'application mobile, tests — et que faut-il corriger en priorité ?

## Méthode et sources

Lecture du code au commit `80e736e4`, sans modification :

- **Serveur** : `app/Models/Mission.php`, `app/Services/MissionService.php`, `app/Http/Controllers/Api/V1/MissionController.php`, `app/Http/Resources/MissionResource.php`, `app/Http/Requests/Mission/CreateMissionRequest.php`, `app/States/Mission/` (états et transitions), `app/Listeners/RecordMissionStateTransition.php`, et tous les endroits qui changent le statut d'une mission (`WalletService`, `LitigeService`, `DevisService`, `JalonService`), `routes/api.php`, `routes/console.php`.
- **Mobile** : `lib/modules/missions/`, `lib/data/models/mission_model.dart`, `lib/data/repositories/mission_repository.dart`.
- **Plan de référence** : `plans/2026-09-26-chantier-13-machine-etats-guards-missions.md`.
- **Sonde d'exécution** : un test jetable, lancé puis supprimé, a rejoué huit situations pour confirmer les constats marqués « vérifié par exécution ».

Non examiné : les données de production (répartition réelle des missions par état), le détail des modules devis, jalons et litiges au-delà de leurs changements de statut de mission, l'onglet Missions du backoffice.

## Constats

### Ce qui tient

- **Propriété vérifiée sur chaque route** : `show`, `siteMap`, `stateHistory`, `acceptRequest`, `rejectRequest`, `assignArtisan` comparent `client_id` / `artisan_id` à l'appelant (Règle d'or 36).
- **Position du client masquée à l'artisan avant financement** (`MissionResource::shouldRevealClientDetails`, `MissionController::siteMap`), conforme à la Règle d'or 6.
- **Forçage de statut réservé à l'administrateur**, avec motif obligatoire et ligne d'audit (`MissionController::updateStatus`, lignes 365-425).
- **Liste sans requêtes en cascade** : compteurs de messages non lus et de devis agrégés (`Mission::scopeWithDevisFlags`).
- **Couverture serveur large** : 23 fichiers de test touchent le module, dont trois dédiés à la machine à états.

### A. Machine à états : les gardes et l'historique sont contournés

**A1. Les changements de statut les plus importants n'utilisent pas la machine à états.** Ils écrivent la colonne directement, ce qui saute les gardes et n'écrit rien dans l'historique.

| Changement | Emplacement |
| --- | --- |
| Financement (`funded_locked`) | `WalletService.php:226` |
| Clôture hors `pending_approval` | `WalletService.php:577` |
| Ouverture de litige (`disputed`) | `LitigeService.php:91` |
| Annulation après litige (`cancelled`) | `LitigeService.php:414` |
| Clôture après litige (`completed`) | `LitigeService.php:421` et `:434` |

Vérifié par exécution : après une écriture directe `draft` → `funded_locked`, `mission_state_transitions` ne contient aucune ligne ; une mission de 3 000 000 FCFA sans validation du Référent passe à `completed` par écriture directe.

Conséquences :

- L'historique servi par `GET /missions/{id}/state-history`, présenté comme « inaltérable », ne contient ni le financement, ni le litige, ni la clôture, ni l'annulation. Seules y figurent l'acceptation ou le refus de la demande et le démarrage du chantier.
- Les gardes du Chantier 13 (jalons tous payés, seuil Référent, gel des fonds) ne s'exécutent sur aucun de ces chemins. Le seuil Référent reste appliqué ailleurs, au paiement de chaque étape (`JalonService.php:160`, `:218`, `:253`, `WalletService.php:474`). Je n'ai pas vérifié s'il l'est aussi lors d'un arbitrage de litige en faveur de l'artisan (`LitigeService.php:417-423`).

**A2. Deux des neuf états ne sont jamais atteints.** Aucun code ne fait passer une mission en `pending_funding` ni en `pending_approval` (recherche de tous les `transitionTo` et de toutes les écritures de statut). En découle :

- `ToFundedLockedTransition` ne s'exécute jamais, et elle est vide : la garde annoncée au plan du Chantier 13 (« pas de `funded_locked` sans devis accepté ni acompte confirmé ») n'existe pas.
- `ToCompletedTransition` n'est déclenchée que depuis `pending_approval` ou `disputed` par `transitionTo`, ce qui n'arrive pas : ses deux gardes sont du code mort en pratique.

**A3. Une mission ne peut pas être annulée hors litige.**

- Aucune route ne permet au client d'annuler une demande, même sans artisan ni paiement.
- `cancelled` n'est accessible que depuis `pending_artisan_acceptance` et `disputed`.
- Aucune commande planifiée ne traite les missions restées en `draft` ou en `pending_artisan_acceptance` : `routes/console.php` ne planifie que la libération des jalons. Un artisan qui ne répond jamais laisse la demande en attente indéfiniment.

**A4. Le forçage administrateur renvoie une erreur 500 sur une transition non prévue.** Vérifié par exécution : `draft` → `completed` et `draft` → `cancelled` répondent 500 avec le message interne de la bibliothèque (« Transition from `draft` to `cancelled`… was not found »). `bootstrap/app.php` ne traite ni cette exception ni la `DomainException` des gardes. La liste des valeurs acceptées contient encore les libellés français hérités et omet `pending_artisan_acceptance` (`MissionController.php:389`).

**A5. Le seuil Référent est écrit en dur à deux endroits**, contre la règle de lire la configuration : `ToCompletedTransition.php:30` et `ReferentController.php:43`. Le comparateur varie : `>` partout, sauf `AdminService.php:217` qui utilise `>=`. Une mission d'exactement 2 000 000 FCFA n'est pas traitée de la même façon selon l'écran.

**A6. Code mort avec un statut invalide.** `WalletService::refundClient` écrit `'status' => 'annulee'` (ligne 597) : vérifié par exécution, cette valeur lève `UnknownState`. Ni `refundClient` ni `payArtisan` n'ont d'appelant.

**A7. « Mission financée » a au moins cinq définitions** qui ne coïncident pas : `Mission::isFunded()` (exclut `pending_approval` et `disputed`), `MissionResource::shouldRevealClientDetails`, `MissionController::siteMap` (liste recopiée, lignes 211-217), `MissionResource::mapPaymentStatus`, `AntiCircumventionService.php:136`, plus `isFunded` côté mobile.

### B. Logique métier dans le contrôleur

**B1. Le contrôleur porte la logique que l'architecture réserve aux services.** `MissionController` compte 657 lignes, `MissionService` 146. Acceptation, refus, assignation d'artisan, forçage de statut, carte du chantier et historique sont écrits dans le contrôleur. Aucune de ces actions n'est dans une transaction : `assignArtisan` enregistre l'artisan (ligne 584) puis change l'état (ligne 590) ; un échec entre les deux laisse un artisan assigné sur une mission en `draft`.

**B2. La création expose et journalise trop.**

- En cas d'erreur, la réponse contient le message brut de l'exception (`MissionController.php:152`).
- Le journal reçoit toute la requête (`:147`) : description, adresse, numéro de paiement.
- La création modifie le compte du client : numéro de paiement et opérateur, « wave » par défaut (`:120-134`), sans que le client l'ait choisi.

**B3. Valeurs par défaut inventées** (Règle d'or 29) :

- Sans type d'intervention, la mission reçoit le premier type de la table (`MissionService.php:70`) — « Maintenance », vérifié par exécution.
- Sans retour exploitable de l'IA, la mission reçoit la catégorie « Travaux généraux » et une estimation de 25 000 à 100 000 FCFA (`MissionService.php:107-110`), affichées comme une estimation réelle.

**B4. L'appel à l'IA est synchrone dans la création** (`MissionService.php:95`) : le temps de réponse de la création dépend de Gemini. Un échec est toléré.

**B5. Assignation d'un artisan** (`MissionController.php:542-609`) :

- L'artisan remplacé pendant `pending_artisan_acceptance` n'est pas prévenu que la demande lui est retirée.
- Seul le KYC de l'artisan est contrôlé, pas l'état de son compte (`User::isAccountActive()` existe).

**B6. Filtre de liste mal groupé pour les rôles autres que client et artisan** (`MissionController.php:51`) : le `orWhere` n'est pas entre parenthèses, le filtre de statut ne s'applique qu'au côté artisan. Sans fuite : vérifié par exécution, un livreur n'obtient aucune mission d'autrui.

### C. Contrat avec l'application mobile

**C1. Le bouton « Demarrer » de l'artisan appelle une route réservée à l'administrateur.** `bottom_actions.dart:106` déclenche `updateMissionStatus(mission.id, 'en_cours')` (`mission_tracking_screen.dart:249`), soit `PUT /missions/{id}/status`. Cette route refuse tout autre rôle qu'administrateur et exige un motif : l'appel échoue toujours. Hors connexion, la requête est mise en file et rejouée (`mission_repository.dart:312`). Le démarrage réel se fait à la soumission de la première étape (`JalonService.php:55`).

**C2. Le mobile ramène les neuf états à six libellés hérités** (`mission_model.dart:380-413`). `draft`, `pending_artisan_acceptance` et `pending_funding` deviennent tous « en_attente » : le client ne distingue pas une demande sans artisan, une demande en attente de réponse et un devis à payer.

**C3. `MissionResource` renvoie des valeurs trompeuses ou factices :**

- `financials.platformFeesBreakdown` vaut toujours 0 et `tokenCode` toujours `null` ;
- `paymentStatus` vaut « refunded » pour toute mission annulée, y compris jamais payée ;
- `statusGemini` vaut « sent » pour `draft` et `pending_approval`, « work_done » pour `in_progress` ;
- plusieurs clés existent en double (`client_id` / `clientId`, `address_id` / `addressId`).

**C4. Libellés sans accents** dans l'écran de suivi : « Demarrer », « Mission mise a jour », « Le statut de la mission a ete actualise » (Règle d'or 35).

### D. Tests

- **Serveur** : les tests de la machine à états portent sur `transitionTo`, donc sur un chemin que le code de production n'emprunte pas pour le financement, le litige et la clôture. Aucun test ne vérifie que l'historique est complet au terme d'un parcours réel, ni la réponse du forçage administrateur sur une transition non prévue.
- **Mobile** : `MissionsController` (821 lignes) n'a pas de test ; le dossier `test/modules/missions/` ne contient que des tests de composants et de devis vocal.

## Recommandations

Par ordre de priorité. Chaque lot peut devenir un plan.

1. **Fiabiliser la machine à états** (constats A1, A2, A5, A6, A7).
   - Un point d'entrée unique, dans `MissionService`, pour tout changement d'état : il applique les gardes et écrit l'historique avec l'acteur et le motif passés explicitement.
   - Remplacer les six écritures directes par ce point d'entrée.
   - Trancher le sort de `pending_funding` et `pending_approval` : les utiliser réellement ou les retirer.
   - Lire le seuil Référent dans la configuration, avec un seul comparateur.
   - Une seule définition de « mission financée », sur le modèle.
   - Un test de garde qui relit les sources et refuse toute écriture directe du statut d'une mission, sur le modèle de `MariaDbSpatialSyntaxGuardTest`.
   - Supprimer `refundClient` et `payArtisan`.
2. **Corriger ce que l'utilisateur voit casser** (C1, C4, A4, B2).
   - Retirer le bouton « Demarrer » ou le brancher sur une action réelle.
   - Répondre 422 avec un message en français quand un forçage administrateur est refusé.
   - Ne plus renvoyer le message d'exception à la création, ni journaliser la requête entière.
3. **Annulation et demandes sans réponse** (A3).
   - Annulation par le client tant qu'aucun paiement n'existe.
   - Relance puis retour en recherche d'artisan quand celui-ci ne répond pas dans un délai à fixer, par une commande planifiée.
4. **Ramener la logique dans le service** (B1, B5, B6) : acceptation, refus, assignation et forçage dans `MissionService`, en transaction ; prévenir l'artisan remplacé ; contrôler l'état du compte.
5. **Assainir le contrat avec le mobile** (C2, C3, B3).
   - Exposer le libellé français de l'état (`MissionState::labelFor`) et afficher les neuf états.
   - Retirer les valeurs factices ; une estimation absente s'annonce comme telle.
   - Tests du contrôleur mobile.

### Décisions à prendre avant tout plan

- **`pending_approval`** : voulez-vous une validation finale du chantier par le client, distincte de la validation de la dernière étape ? Sinon l'état est à retirer.
- **Annulation après financement** : est-elle permise hors litige, et avec quelle pénalité ?
- **Délai de réponse de l'artisan** avant de remettre la demande en recherche.
