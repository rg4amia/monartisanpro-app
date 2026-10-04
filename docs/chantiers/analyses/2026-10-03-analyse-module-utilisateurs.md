# Analyse — Module « Utilisateurs » : backoffice, application mobile, revue KYC, rôles et actions

- **Date** : 2026-10-03
- **Auteur** : Claude (Opus 5.5), à la demande d'Inza Bamba
- **Statut** : analyse seule, aucun code modifié
- **Suite** : `plans/2026-10-03-chantier-27-securisation-comptes-utilisateurs.md` — lot A livré (F1, F3, F4, G2, G3) ; lot B livré (A1 à A4, B1 à B5, B7, C2, C3, C7, E1, E2) ; lot C livré (H1, H2 pour les comptes anonymisés et supprimés, H3, H4, H5) ; lot D livré (I1, I3, I4 pour les rôles sans effet, I5)

## Question posée

La vie d'un compte est-elle sûre et cohérente, de l'inscription dans l'application à sa gestion dans le backoffice (création, modification, statut, suppression, données personnelles, usurpation), en passant par la revue KYC et les droits de l'onglet « Rôles & Actions » ? Que faut-il corriger ?

## Méthode et sources

- **Serveur** : `app/Services/Admin/AdminUserService.php`, `AdminGdprService.php`, `AdminPermissionService.php`, `app/Http/Requests/Admin/{StoreUser,UpdateUser,ToggleUserStatus,BulkUserStatus}Request.php`, `app/Http/Controllers/Admin/{BackofficeController,ImpersonationController,AuthenticatedSessionController}.php`, `app/Http/Middleware/{AdminOnly,AccountActive}.php`, `app/Models/User.php`, `app/Services/AdminService.php` (`listUsers`), `app/Services/Admin/AdminPanelData.php` (`users`), `routes/web.php`.
- **Application mobile (API)** : `app/Http/Controllers/Api/V1/{AuthController,UserController,KycController}.php`, `app/Services/{AuthService,OtpService,KycService}.php`, `app/Http/Requests/Auth/RegisterRequest.php`, `routes/api.php`.
- **Revue KYC** : `AdminService::reviewKyc`, `bulkReviewKyc`, `pendingKyc`, `ReviewKycRequest`, catalogue des notifications.
- **Rôles & Actions** : `AdminPermissionService::sync`, `RolePermissionService`, `AdminRolePermissionController`, `app/Traits/HasPermissions.php`.
- **Backoffice** : `resources/js/pages/admin/panels/UsersPanel.tsx`, `panels/FormModals.tsx` (`UserFormModal`), `console.tsx`.
- **Tests existants** : `AdminUserManagementTest`, `UserControllerAuthorizationTest`, `UsersPanel.test.tsx`.
- **Sonde d'exécution** : dix-huit tests jetables sur SQLite, lancés puis supprimés. Les constats marqués « vérifié » en sont issus ; les autres reposent sur la lecture du code.
- **Hors périmètre** : les écrans Flutter eux-mêmes (seules les routes qu'ils appellent sont analysées), l'analyse KYC par l'IA (Règle d'or 69), la connexion au backoffice.

## Constats

### Ce qui tient

- **Couche service** : les contrôleurs valident et délèguent à `AdminUserService` (Règle d'or 49).
- **Capacités fines par route** : consultation, gestion, suppression, usurpation et RGPD ont chacune la leur (Règle d'or 16).
- **Garde « pas soi-même »** sur la suppression, le changement de statut unitaire et groupé, l'anonymisation et l'usurpation.
- **Usurpation** : refusée sur un administrateur, un compte supprimé ou anonymisé ; début et fin audités.
- **Anonymisation** : données expurgées, pièces et jetons supprimés, ligne `users` conservée (Règle d'or 22).
- **Photo et pièces KYC** sur disque privé, servies par lien signé.
- **Liste paginée côté serveur**, recherche groupée entre parenthèses.

### A. Élévation de privilèges par la capacité « gérer les utilisateurs »

Un administrateur sans capacité affectée a l'accès total (Règle d'or 16). Tout ce qui crée ou modifie un compte `admin` donne donc potentiellement l'accès total.

**A1. Un administrateur restreint crée un administrateur à accès total** (vérifié). Avec `admin.users.view` et `admin.users.manage` seulement, `POST /admin/users` accepte `role = admin`. Le compte créé n'a aucune capacité affectée : il reçoit `['*']`. Son créateur en connaît le mot de passe et enregistre lui-même sa double authentification à la première connexion.

**A2. Il prend le contrôle d'un super administrateur protégé** (vérifié). `PUT /admin/users/{id}` change le mot de passe de `admin@prosartisan.ci`. Il change aussi son adresse e-mail et son rôle : le compte cesse alors d'être protégé, puisque la protection tient à l'adresse.

**A3. Il promeut n'importe quel compte en administrateur** (vérifié) : un client passé en `admin` par le formulaire de modification obtient l'accès total.

**A4. Il supprime ou suspend un super administrateur** (vérifié) : `DELETE /admin/users/{id}` avec `admin.users.delete`, ou le changement de statut groupé avec `admin.users.manage`. Seul l'onglet « Rôles & Actions » protège les super administrateurs.

### B. Statut du compte

**B1. Un administrateur suspendu garde l'accès au backoffice** (vérifié). Ni la connexion ni `AdminOnly` ne lisent `account_status` : un administrateur suspendu ouvre la liste des utilisateurs et suspend un autre compte. La suspension d'un administrateur est sans effet.

**B2. Un compte anonymisé se réactive et se réattribue** (vérifié). Le changement de statut, unitaire ou groupé, le repasse en « actif » ; le formulaire de modification lui redonne un nom, un téléphone et un mot de passe. L'anonymisation « irréversible » ne l'est pas, et l'historique financier du compte passe à une autre personne.

**B3. Le statut modifié par le formulaire échappe à l'audit** (vérifié). La ligne `user.updated` ne compare pas `account_status`. Le motif et la date de blocage ne sont pas mis à jour : un compte réactivé par le formulaire garde son ancien motif et sa date de blocage.

**B4. Un compte banni ne se modifie plus** (vérifié). Le formulaire n'accepte que « actif » ou « suspendu » : corriger le nom d'un compte banni échoue, sauf à le débannir du même geste, sans trace (B3).

**B5. Un administrateur se suspend ou se rétrograde lui-même** (vérifié) par le formulaire de modification, qui n'a pas la garde « pas soi-même ».

**B6. La suspension ne demande aucun motif et ne ferme pas les sessions** (vérifié pour le motif et les jetons). Le motif est facultatif ; les jetons de l'application restent en base. L'application est bloquée par `account.active`, mais le message annonce toujours « litiges abusifs », quel que soit le motif réel.

**B7. Le changement groupé n'écrit qu'une ligne d'audit** (vérifié), alors que la Règle d'or 21 demande une ligne par compte en plus de la ligne récapitulative.

### C. Création et modification

**C1. Le statut KYC se fixe à la main, sans pièce ni trace de revue** (vérifié). Créer ou modifier un compte avec `kyc_status = actif` contourne la revue KYC et sa capacité `admin.kyc.review` ; l'audit n'enregistre qu'une modification de compte (Règle d'or 1).

**C2. Le téléphone n'est pas contrôlé** (vérifié) : `abc` est accepté, alors que la règle est `+225` suivi de dix chiffres. Un tel compte ne recevra jamais de code par SMS.

**C3. Un livreur ne se crée ni ne se modifie** (vérifié). Le rôle `livreur` manque à la validation et à la liste du formulaire : toute modification d'un livreur est refusée sur le champ « rôle ».

**C4. Mot de passe de six caractères**, y compris pour un administrateur.

**C5. Changer le rôle d'un compte ne vérifie rien** (lecture du code) : un artisan avec des missions en cours ou un solde peut devenir client ; ses profils, portefeuilles et missions restent attachés à un rôle qui ne les voit plus.

**C6. Un fournisseur créé au backoffice reçoit une boutique inventée** (lecture du code). `User::booted` crée « Quincaillerie de … » à des coordonnées fixes d'Abidjan (Règle d'or 29). Ce code est désactivé pendant les tests : rien ne le couvre.

**C7. L'empreinte de l'appareil est modifiable à la main**, alors qu'elle sert à la détection de fraude (Règle d'or 71).

### D. Suppression

**D1. Aucun contrôle avant suppression** (vérifié). Un artisan avec 50 000 FCFA en portefeuille et une mission en cours est supprimé sans avertissement. Ses fonds, ses missions et ses versements en attente restent attachés à un compte que plus personne ne peut ouvrir.

**D2. Le téléphone d'un compte supprimé reste réservé** (vérifié au backoffice). Recréer un compte avec ce numéro est refusé pour doublon ; rien ne permet de restaurer le compte supprimé depuis l'écran. Le comportement à l'inscription dans l'application reste à vérifier.

**D3. Suppression et anonymisation coexistent sans lien** : la suppression conserve toutes les données personnelles ; l'anonymisation les efface mais garde le compte dans la liste.

### E. Liste et écran

**E1. 115 requêtes pour afficher 25 comptes** (vérifié). Chaque ligne recalcule deux soldes en chargeant toutes les écritures du portefeuille, et lit la position.

**E2. Le navigateur reçoit plus que l'écran n'affiche** (vérifié) : jeton de notification, empreinte de l'appareil, numéro de paiement, position, pour chaque compte de la page.

**E3. Filtres incomplets** : pas de filtre sur le statut du compte (suspendu, banni), ni sur les comptes anonymisés ou supprimés.

### F. Inscription et connexion dans l'application

**F1. L'inscription n'exige pas le code reçu par SMS** (vérifié). `POST /auth/register` est publique : elle crée le compte du numéro fourni et renvoie un jeton de session, sans vérifier qu'un code a été validé pour ce numéro. N'importe qui ouvre donc un compte au numéro de quelqu'un d'autre, ou des comptes en série sans un seul SMS. Elle termine aussi l'inscription d'un compte dont le vrai titulaire a validé son code sans aller au bout : l'appelant reçoit le jeton de ce compte.

**F2. La récupération « SIM perdue » permet de prendre le compte d'autrui** (vérifié). `reset-phone-request` puis `reset-phone-confirm` demandent l'ancien numéro, le nom exact et le rôle, puis envoient le code au **nouveau** numéro, celui que fournit l'appelant. Qui connaît le nom et le numéro d'un artisan devient titulaire de son compte : le statut KYC reste « actif », les sessions de la victime restent ouvertes, personne n'est prévenu. Le numéro de paiement se change ensuite sans autre contrôle (G1) : les versements suivants partent vers le nouveau titulaire.

**F3. Un livreur ne peut pas récupérer son compte** (vérifié) : la route n'accepte que le rôle `driver`, que la base ne contient jamais (elle stocke `livreur`).

**F4. Une personne dont le compte a été supprimé ne peut plus se connecter, et reçoit une erreur technique** (vérifié). Son numéro reste réservé par la ligne supprimée : la validation du code échoue en erreur 500, avec le message de la base de données dans la réponse.

**F5. Un changement de téléphone ne ferme aucune session et ne prévient pas l'ancien numéro** (lecture du code).

### G. Profil dans l'application

**G1. Le numéro de paiement se change avec le seul jeton de session** (vérifié), sans code de confirmation ni notification. C'est vers lui que partent les versements.

**G2. Tout administrateur modifie le profil de n'importe quel compte par la route de l'application** (vérifié). `PUT /api/v1/users/{id}` n'exige que le rôle `admin` : un administrateur limité à la FAQ a changé le nom et le numéro de paiement d'un artisan, sans aucune ligne d'audit (Règles d'or 17 et 36).

**G3. La carte CNMCI est stockée sur le disque public** (lecture du code), à une adresse permanente, alors que les pièces KYC sont sur disque privé (Règle d'or 40).

**G4. Un utilisateur supprime son compte malgré des fonds et une mission en cours** (vérifié). Un artisan avec 60 000 FCFA en portefeuille et un chantier en cours s'anonymise : le compte est suspendu, les fonds et la mission restent sans titulaire joignable. Rien ne vérifie non plus un micro-crédit ou une dette en cours.

**G5. Deux circuits de changement de rôle** : la route de l'application (réservée aux administrateurs) remet le KYC en attente et ferme les sessions ; le formulaire du backoffice ne fait ni l'un ni l'autre (C5).

### H. Revue KYC

**H1. Un dossier sans aucune pièce s'approuve** (vérifié) : le compte passe « actif » sans carte d'identité ni selfie.

**H2. La revue ne regarde pas l'état du compte** (vérifié). Un compte anonymisé repasse au KYC « actif » ; un compte déjà actif se rejette, sans que ses sessions soient fermées ni que ses missions en cours soient examinées.

**H3. Approuver après un rejet laisse les pièces « rejetées »** (vérifié) : le compte est actif, ses deux pièces portent le statut rejeté.

**H4. Un utilisateur rejeté qui renvoie ses pièces reste rejeté** (vérifié). Son statut ne revient pas « en attente » : le dossier n'apparaît plus dans la liste à traiter, et l'analyse automatique ne s'applique qu'aux dossiers en attente. L'utilisateur est dans une impasse, que seul le formulaire de modification du compte débloque (C1).

**H5. Le motif du rejet n'atteint pas l'utilisateur** (vérifié) : la notification dit seulement de « vérifier vos documents », et `GET /kyc/status` ne renvoie pas le motif.

**H6. La liste à traiter mêle des comptes sans dossier** (lecture du code) : un numéro qui a validé son code sans terminer l'inscription y figure, sans nom ni pièce. Les Référents n'y figurent jamais : leur KYC ne se règle que par le formulaire de modification.

### I. Onglet « Rôles & Actions »

**I1. Tout décocher donne l'accès total** (vérifié). Un administrateur limité à la FAQ dont on retire la dernière capacité reçoit `['*']` : « aucune capacité » et « accès total » sont le même état (Règle d'or 16). Aucun message ne le signale.

**I2. La capacité « gérer les rôles » vaut l'accès total** (vérifié) : son porteur se l'accorde lui-même. C'est cohérent, mais à savoir quand on l'attribue.

**I3. Les droits des rôles de l'application changent sans trace** (vérifié). Retirer ou attribuer une action à un rôle n'écrit aucune ligne d'audit, alors que la Règle d'or 17 l'exige pour tout changement de droits.

**I4. Aucun garde-fou sur ces droits** (vérifié). Retirer « créer une mission » au rôle client coupe la fonction pour tous les clients, aussitôt. Attribuer « arbitrer un litige » au rôle client est accepté. Les rôles `admin` et `driver` sont proposés alors qu'ils n'ont aucun effet.

**I5. La modification des droits d'un administrateur n'enregistre pas l'état précédent** (lecture du code) : l'audit donne les nouvelles capacités, pas les anciennes.

## Conclusion

Trois ensembles sont à traiter d'abord :

- **Dans l'application**, un compte s'ouvre sans prouver qu'on détient le numéro (F1) et se prend à son titulaire avec son nom et son numéro (F2), après quoi les versements se détournent (G1).
- **Dans le backoffice**, la capacité « gérer les utilisateurs » équivaut à l'accès total (A1 à A4), la suspension d'un administrateur n'a aucun effet (B1) et l'anonymisation se défait (B2).
- **À la revue KYC**, un compte s'active sans pièce (H1) et un utilisateur rejeté ne peut plus revenir (H4).

Le reste relève de la cohérence des statuts, de la validation et de l'audit.

## Pistes, par ordre de priorité

1. **Sans décision à prendre** :
   - application : F1 (inscription seulement après un code validé pour ce numéro), F3, F4, G2, G3 ;
   - backoffice : A1 à A4 (comptes administrateurs réservés à `admin.roles.manage`, super administrateurs intouchables), B1, B2, B3, B4, B5, B7, C2, C3, E1, E2 ;
   - KYC : H1, H2 (compte anonymisé), H3, H4, H5 ;
   - rôles : I1 (message clair et état explicite), I3, I4 (rôles sans effet retirés), I5.
2. **Décisions de règle** :
   - récupération « SIM perdue » (F2) : la fermer et la confier au support, ou la conserver avec une preuve supplémentaire ;
   - numéro de paiement (G1) : code de confirmation et notification ;
   - suppression d'un compte ayant des fonds, une mission, un crédit ou une dette, par l'utilisateur (G4) comme par l'administrateur (D1) ; restauration d'un compte supprimé (D2, D3, F4) ;
   - statut KYC modifiable ou non par le formulaire (C1) ; rejet d'un compte déjà actif (H2) ;
   - motif de suspension obligatoire et fermeture des sessions (B6) ; longueur du mot de passe (C4) ; changement de rôle (C5, G5) ; boutique créée d'office (C6) ;
   - droits des rôles de l'application : liste d'actions protégées, ou onglet en lecture seule (I4).
