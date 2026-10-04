# Plan — Chantier 27 : sécurisation des comptes utilisateurs

| Champ | Valeur |
| --- | --- |
| Statut | en cours (lots A et B livrés, contrôle manuel à faire) |
| Créé le | 2026-10-03 |
| Mis à jour le | 2026-10-04 |
| Auteur | Claude Code |
| Analyses liées | `../analyses/2026-10-03-analyse-module-utilisateurs.md` |
| Commits | — |

## Objectif

Fermer les failles relevées par l'analyse du module « Utilisateurs » : ouverture et prise de compte dans l'application, élévation de privilèges dans le backoffice, revue KYC incohérente, droits modifiés sans trace. Les lots A à D ne demandent aucune décision. Le lot E attend les choix d'Inza Bamba ; chaque point y porte une recommandation.

## Périmètre

- Inclus : routes d'inscription, de récupération et de profil de l'application ; onglets « Utilisateurs », « KYC & Vérifications » et « Rôles & Actions » du backoffice ; tests, Règle d'or, PRD et manuel.
- Exclu : les écrans Flutter (sauf l'adaptation du lot E si elle est décidée), l'analyse KYC par l'IA, la connexion au backoffice, la refonte de la liste des utilisateurs au-delà des constats E1 et E2.

## Ordre de livraison

Les lots sont indépendants et se livrent dans cet ordre, chacun avec ses tests. Le lot A passe en premier : il ferme les failles exploitables sans compte.

| Lot | Sujet | Constats | Décision requise |
| --- | --- | --- | --- |
| A | Inscription et profil dans l'application | F1, F3, F4, G2, G3 | non |
| B | Droits et statuts dans le backoffice | A1 à A4, B1 à B5, B7, C2, C3, E1, E2 | non |
| C | Revue KYC | H1 à H5 | non |
| D | Rôles & Actions | I1, I3, I4 (rôles sans effet), I5 | non |
| E | Règles à décider | F2, G1, G4, D1 à D3, C1, C4 à C6, B6, I4 | oui |

## Étapes

### Lot A — Inscription et profil dans l'application (livré)

1. **Inscription seulement après un code validé (F1)** — `OtpService`, `AuthController::verifyOtp` et `register`, nouveau `AuthService::assertPhoneVerified`.
   - À la validation du code, le serveur enregistre une preuve pour ce numéro, valable 30 minutes (`prosartisan.otp.registration_window_minutes`).
   - `register` refuse (422, « Validez d'abord le code reçu par SMS. ») sans preuve valide, et la consomme à la réussite.
   - La preuve est côté serveur : les versions installées de l'application, qui appellent déjà `verify-otp` avant `register`, continuent de fonctionner sans mise à jour.
   - La logique sort du contrôleur vers `AuthService` (Règle d'or 49).
2. **Récupération par un livreur (F3)** — la route accepte `livreur` et `driver`, ramenés à `livreur`. Sans objet si la récupération est fermée au lot E.
3. **Compte supprimé qui revient (F4)** — `AuthService::findOrCreateByPhone` détecte une ligne supprimée portant ce numéro et répond 422 avec un message en français qui renvoie au support. Plus d'erreur 500 ni de message de la base. La restauration elle-même relève du lot E.
4. **Profil modifié par un administrateur (G2)** — `UserController::update` et `updateCnmci` : un administrateur doit porter `admin.users.manage` ; la modification est auditée (`user.profile.updated_by_admin`, avant et après). Le numéro de paiement n'est plus modifiable par cette voie pour un tiers.
5. **Carte CNMCI sur disque privé (G3)** — stockage `local`, lien signé de 15 minutes sur le modèle des pièces KYC ; commande `cnmci:migrate-to-private` pour les cartes existantes ; `UserResource` et le backoffice lisent le lien signé.

### Lot B — Droits et statuts dans le backoffice (livré)

6. **Comptes administrateurs réservés (A1, A3)** — `AdminUserService::create` et `update` : créer un compte `admin`, donner ou retirer le rôle `admin` exige `admin.roles.manage`. Un administrateur créé reçoit une capacité explicite (aucune ligne = accès total, voir étape 16). Le formulaire ne propose le rôle « Administrateur » qu'aux porteurs de la capacité.
7. **Super administrateurs intouchables (A2, A4)** — garde unique `AdminUserService::guardProtected` appliquée à la modification, au changement de statut unitaire et groupé, à la suppression et à l'anonymisation : seul le super administrateur lui-même modifie son compte, et jamais son rôle ni son adresse e-mail. Modifier un autre administrateur (mot de passe compris) exige `admin.roles.manage`.
8. **Administrateur suspendu (B1)** — `AuthenticatedSessionController` refuse la connexion d'un compte non actif ; `AdminOnly` ferme la session d'un administrateur suspendu ou anonymisé (audit `admin.session.closed_suspended`).
9. **Compte anonymisé figé (B2)** — changement de statut, modification, revue KYC et changement de rôle refusés sur un compte anonymisé (422 unitaire, ignoré dans un lot avec compte rendu).
10. **Statut par une seule voie (B3, B4, B5)** — le formulaire de modification n'écrit plus `account_status` : le statut passe par `toggleStatus`, qui met à jour le motif et la date, et audite. Un compte banni redevient modifiable. La garde « pas soi-même » couvre la modification du rôle et du statut.
11. **Audit du changement groupé (B7)** — une ligne par compte (`user.status_changed`) en plus de la ligne récapitulative ; passage par `toggleStatus` compte par compte, un échec n'interrompant pas le lot (Règle d'or 21).
12. **Téléphone et rôle livreur (C2, C3)** — téléphone validé par `^\+225[0-9]{10}$` à la création et à la modification (un numéro existant hors format reste accepté tant qu'il n'est pas modifié) ; rôle `livreur` ajouté à la validation et au formulaire.
13. **Liste allégée (E1, E2)** — `AdminPanelData::users` transmet une forme explicite par compte (champs affichés seulement : ni jeton de notification, ni empreinte, ni position) ; soldes calculés en une requête groupée pour la page. Objectif : nombre de requêtes indépendant du nombre de lignes, vérifié par test.

### Lot C — Revue KYC

14. **Revue cohérente (H1 à H4)** — `AdminService::reviewKyc` passe dans un service dédié `Admin\KycReviewService` :
    - approbation refusée sans carte d'identité **et** selfie (422, « Ce dossier ne contient pas les deux pièces. ») ;
    - compte anonymisé ou supprimé refusé ;
    - à l'approbation, les deux pièces courantes passent « approuvé », quel que soit leur statut précédent ;
    - `KycService::uploadDocument` remet un compte « rejeté » en « en attente » dès qu'il renvoie une pièce : le dossier revient dans la liste et l'analyse automatique s'applique.
15. **Motif du rejet transmis (H5)** — variable `{motif}` ajoutée à l'événement `kyc.rejete.utilisateur` (texte par défaut mis à jour, variable obligatoire) ; `GET /kyc/status` renvoie `rejection_reason`. L'écran KYC de l'application l'affiche s'il est présent.

### Lot D — Rôles & Actions

16. **« Aucune capacité » distinct d'« accès total » (I1)** — `AdminPermissionService::sync` refuse une liste vide (422, « Cochez au moins une capacité, ou l'accès total. »). L'accès total s'accorde par sa case explicite. Migration de données : les administrateurs sans ligne reçoivent `admin.full-access`, pour que l'état actuel soit conservé et lisible. Le repli « aucune ligne = accès total » de `capabilitiesFor` est conservé comme filet de secours (Règle d'or 67).
17. **Audit complet (I3, I5)** — `RolePermissionService` audite `role_permission.assigned` et `.revoked` (rôle, action) ; `admin.permissions_updated` enregistre les capacités avant et après.
18. **Rôles sans effet retirés (I4, partie sans décision)** — `admin` et `driver` sortent de la validation et de l'écran.

### Lot E — Règles à décider

Rien n'est implémenté avant la réponse d'Inza Bamba. Recommandation entre parenthèses.

19. **Récupération « SIM perdue » (F2)** — (fermer la route ; le support change le numéro depuis le backoffice, action auditée qui ferme les sessions et prévient l'utilisateur). Variante : la conserver avec une preuve que l'attaquant n'a pas, par exemple le numéro de la pièce d'identité du dossier KYC.
20. **Numéro de paiement (G1)** — (code de confirmation envoyé au numéro du compte, notification push et SMS au titulaire, blocage des retraits pendant 24 heures après le changement).
21. **Suppression d'un compte engagé (G4, D1)** — (refuser tant que le compte a un solde, une mission ou une commande en cours, un crédit, une dette ou un versement en attente ; message listant ce qui bloque).
22. **Compte supprimé (D2, D3, F4)** — (bouton « Restaurer » dans le backoffice, capacité `admin.users.delete`, audité ; filtre « Supprimés » dans la liste).
23. **Statut KYC dans le formulaire (C1)** — (le retirer : seule la revue KYC active un compte ; exception pour le Référent, créé actif par un administrateur).
24. **Rejet d'un compte déjà actif (H2)** — (l'autoriser avec motif, fermer les sessions, signaler les missions en cours à l'administrateur avant confirmation).
25. **Suspension (B6)** — (motif obligatoire de 5 caractères, sessions fermées, message de l'application reprenant le motif réel).
26. **Mot de passe (C4)** — (12 caractères pour un administrateur, 8 pour les autres rôles).
27. **Changement de rôle (C5, G5)** — (un seul circuit, celui de l'application : KYC remis en attente, sessions fermées ; refusé si le compte a un solde ou une mission en cours).
28. **Boutique créée d'office (C6)** — (supprimer la création automatique ; la fiche fournisseur se crée à l'agrément, avec ses vraies coordonnées).
29. **Droits des rôles de l'application (I4)** — (liste d'actions protégées qu'aucun retrait ne peut toucher, et liste d'actions réservées par rôle, par exemple « arbitrer un litige »).

## Règles d'or concernées

1, 6, 16, 17, 21, 22, 24, 29, 35, 36, 39, 40, 49, 55, 67, 68, 74, 80, 81 ; Règle d'or 98 (lot A), Règle d'or 99 (lot B).

Points d'attention :

- **Anti-lockout (67, 68)** : aucune étape ne doit priver un super administrateur de son accès ; `admin:full-access` et `admin:reset-password` restent le secours.
- **Catalogue des notifications (80, 81)** : `{motif}` s'ajoute comme variable obligatoire ; un texte surchargé en base qui ne la contient pas doit rester valide (repli sur le texte d'origine).
- **Migrations (55)** : la migration des administrateurs sans capacité est idempotente et ne touche pas ceux qui ont déjà des lignes.

## Vérification

- **Pest**, un fichier par lot (`Chantier27…Test.php`). Chaque faille a un test qui échoue avant correctif et passe après (Règle d'or 36) : inscription sans code, création d'un administrateur par un compte restreint, prise de contrôle d'un super administrateur, administrateur suspendu, compte anonymisé réactivé, approbation sans pièce, retour d'un utilisateur rejeté, tout décocher.
- **Suites complètes** sur SQLite et sur MariaDB 11.8.
- **Vitest** : `UsersPanel.test.tsx`, `FormModals` (rôle « Administrateur » masqué, rôle « Livreur » présent), `KycPanel.test.tsx`, `roles-permissions-panel`.
- **Flutter** : uniquement si l'affichage du motif de rejet (étape 15) ou une décision du lot E touche l'application.
- **Contrôle manuel** : inscription complète sur un appareil réel avec une version déjà installée de l'application (étape 1) ; connexion d'un super administrateur après la migration de l'étape 16.

## Risques

- **Étape 1** : une application qui appellerait `register` sans `verify-otp` serait bloquée. Le parcours actuel appelle toujours `verify-otp` d'abord ; à confirmer sur appareil avant de pousser.
- **Étape 10** : un administrateur habitué à suspendre par le formulaire devra passer par le bouton de statut.
- **Étape 16** : après la migration, « aucune capacité » ne sera plus un moyen d'accorder l'accès total.

## Écarts

### Lot A (livré le 2026-10-03)

- **Preuve du code en base, pas en cache** : colonne `otps.verified_at`. `used_at` ne convenait pas, il est aussi posé quand un code est brûlé après trop d'essais.
- **La logique d'inscription n'a pas été déplacée en entier** vers `AuthService` : seuls la preuve du code, le compte supprimé et le rôle canonique y sont. Le contrôle du blocage d'accès par espace reste dans `AuthController`.
- **Numéro de paiement d'un tiers** : refusé (422) même pour un administrateur habilité ; aucune autre voie de modification par le support n'existe à ce jour (décision 20 du lot E).
- **Cartes CNMCI existantes** : déplacées au déploiement par une migration qui appelle `cnmci:migrate-to-private` ; un échec est journalisé sans interrompre le déploiement. Tant qu'une carte n'est pas déplacée, son ancienne adresse publique reste servie.
- **Format de la carte** : JPEG, PNG ou WEBP seulement (l'ancienne règle acceptait tout type d'image, SVG compris).
- **Application mobile** : aucune modification ; elle valide déjà le code avant l'inscription et affiche une adresse absolue pour la carte.
- **Non vérifié** : inscription sur un appareil réel avec une version installée ; déplacement des cartes en production.
- **Tests** : `Chantier27LotAAccountSecurityTest.php` (17 tests) ; `RegisterPhoneUniquenessTest` aligné.

### Lot B (livré le 2026-10-04)

- **Capacité d'un administrateur créé ou promu** : `admin.full-access`, par une ligne explicite. Créer un administrateur donnait déjà l'accès total ; il est désormais écrit en base et se restreint dans « Rôles & Actions ». Le créateur porte forcément `admin.roles.manage`.
- **Garde étendue aux autres administrateurs** : suspendre, supprimer ou anonymiser un autre administrateur exige aussi `admin.roles.manage`, pas seulement le modifier.
- **Anonymisation** : la garde est appelée par `AdminGdprService`, qui dépend maintenant de `AdminUserService`.
- **Création** : le statut du compte est retiré du formulaire de création aussi ; un compte se crée actif.
- **Empreinte de l'appareil (C7)** : le champ est retiré des formulaires et de la validation. La liste ne transmettant plus l'empreinte, l'enregistrement du formulaire l'aurait effacée. La liste indique seulement si un appareil est lié.
- **Soldes (E1)** : la liste ne les affiche pas ; ils ne sont plus calculés du tout, au lieu d'une requête groupée. « Fournisseurs en attente » et « Top artisans » partent aussi sous une forme explicite.
- **Super administrateur suspendu** : il garde l'accès au backoffice (anti-verrouillage) ; la garde empêche désormais de le suspendre.
- **Revue KYC d'un compte anonymisé** : refusée dès ce lot dans `AdminService::reviewKyc` (422 sur l'API mobile) ; le service dédié reste prévu au lot C.
- **Refus affichés** : dans le formulaire (sous le champ concerné) pour la création et la modification ; en message d'erreur pour le statut, la suppression et l'anonymisation.
- **Référent** : un Référent suspendu ne se connecte plus au backoffice non plus.
- **Non vérifié** : suite Pest sur MariaDB 11.8 en local (Docker arrêté ; le job CI `tests-mariadb` la rejoue) ; contrôle manuel dans le navigateur.
- **Tests** : `Chantier27LotBBackofficeAccountsTest.php` (22 tests, dont 21 échouent sans le correctif) ; `AdminUserManagementTest` aligné ; `FormModals.test.tsx`, `UsersPanel.test.tsx`.
