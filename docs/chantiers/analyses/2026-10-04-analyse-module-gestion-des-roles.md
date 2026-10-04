# Analyse — Module « Gestion des rôles » : droits des rôles de l'application et capacités des administrateurs

| Champ | Valeur |
| --- | --- |
| Créée le | 2026-10-04 |
| Auteur | Claude Code |
| Plans issus de cette analyse | `../plans/2026-10-04-chantier-28-gestion-des-roles.md` |

## Question posée

Le module « Rôles & Actions » fait-il ce que son écran annonce ? Trois points sont examinés :

1. ce que change réellement un interrupteur de l'écran, pour un rôle de l'application comme pour un administrateur ;
2. ce qu'un administrateur restreint peut encore voir ou faire ;
3. la solidité du dispositif (catalogue, cache, déploiement).

Cette analyse prolonge la section I de l'analyse du module « Utilisateurs » (03/10/2026), dont les constats I1 à I5 ont été traités par les lots D et E du Chantier 27. Elle ne les reprend pas.

## Méthode et sources

- **Lecture du code** : `RolePermissionService`, `Traits/HasPermissions`, `Admin/AdminPermissionService`, `AppServiceProvider` (portes d'autorisation), `PermissionSeeder`, `AdminRolePermissionController`, `AdminPanelData`, `routes/api.php`, `routes/web.php`, `roles-permissions-panel.tsx`, `shared/permissions.ts`.
- **Table des routes** (`php artisan route:list --json`) : pour chaque route, les actions et capacités réellement exigées.
- **Base migrée en mémoire** : comparaison du catalogue du code et des lignes inscrites par les migrations.
- **Sonde exécutée puis supprimée** (test Pest temporaire) : un administrateur limité à la seule capacité « FAQ ».

Chaque constat indique s'il est **vérifié** (mesuré ou exécuté) ou issu de la **lecture du code**.

## Le module en bref

Deux dispositifs distincts partagent le même écran et la même table `permissions` :

| | Droits des rôles de l'application | Capacités des administrateurs |
| --- | --- | --- |
| Cible | un rôle entier (client, artisan, fournisseur, référent, livreur) | un compte administrateur |
| Catalogue | 40 actions (`mission.create`, `devis.accept`…), écrites dans `PermissionSeeder` | 39 capacités `admin.*` et `admin.full-access`, écrites dans `AdminPermissionService::catalog()` |
| Stockage | `permission_role` | `admin_permission_user` |
| Application | `can:<action>` sur les routes de l'API mobile | `can:admin.<x>` sur les routes du backoffice |
| Cache | une heure par rôle | cinq minutes par compte |

## Constats

### A. Droits des rôles de l'application

**A1. 25 des 40 actions de l'écran n'ont aucun effet** (vérifié sur la table des routes).

Seules 15 actions sont exigées par une route : `mission.create`, `mission.estimate`, `mission.referent-validate`, `devis.create`, `devis.update`, `devis.accept`, `devis.refuse`, `jalon.submit`, `jalon.upload-photos`, `jalon.request-otp`, `jalon.validate-otp`, `jcode.create`, `jcode.scan`, `jcode.upload-photo-materials`, `supplier-products.manage`.

Les 25 autres ne figurent sur aucune route ni dans aucun contrôle du code : toutes les actions de consultation (`mission.view`, `devis.view`, `jalon.view`, `jcode.view`, `orders.view`, `litige.view`, `transactions.view`…), et aussi `orders.create`, `orders.manage`, `deliveries.manage`, `litige.create`, `litige.arbitrate`, `litige.vote`, `kyc.upload`, `kyc.view`, `kyc.review`, `evaluation.create`, `parrainage.*`, `micro-credit.*`, `sms.*`, `supplier.dashboard`, `mission.update-status`. L'écran les présente pourtant avec le même interrupteur que les autres ; l'administrateur qui retire « Passer commande » au rôle client croit avoir coupé la fonction, et rien ne change.

**A2. L'API mobile est gardée par des contrôles de rôle écrits dans le code, pas par ces droits** (vérifié).

- 20 routes sur les 193 routes authentifiées de l'API mobile exigent une action du catalogue.
- En regard, 135 contrôles de rôle sont écrits en dur (`$user->role === '…'`) dans les contrôleurs, les services et les middlewares, dont 26 contrôleurs.

Les commandes, les livraisons, les litiges, le portefeuille, les retraits, le recrutement, le jury et les évaluations ne dépendent donc d'aucun droit réglable.

**A3. Le rôle livreur n'a aucun droit réglable, le Référent un seul, le fournisseur deux** (vérifié). Les sept actions du livreur sont toutes sans effet ; sa colonne dans l'écran est décorative. Pour le Référent, seule « valider physiquement une mission » agit ; pour le fournisseur, « scanner un bon » et « gérer son catalogue ».

**A4. Les garde-fous du lot E du Chantier 27 portent en partie sur des actions sans effet** (vérifié).

- Actions « indispensables » (`RolePermissionService::PROTECTED`) : 6 des 11 du client, 6 des 9 de l'artisan, 5 des 7 du fournisseur, 2 des 3 du Référent et les 4 du livreur sont sans effet. Le verrou affiché protège donc surtout des interrupteurs inertes.
- À l'inverse, des actions **qui agissent** restent retirables : `jalon.upload-photos`, `jalon.request-otp`, `devis.update` et `jcode.upload-photo-materials` pour l'artisan, `mission.estimate` pour le client. Retirer « envoyer les photos d'une étape » à l'artisan bloque aussitôt tous les chantiers en cours.
- Actions « réservées » (`RESERVED`) : `mission.create` et `mission.estimate` n'y figurent pas. « Créer une mission » peut être attribuée à un artisan, un fournisseur ou un livreur ; `MissionController::store` ne vérifie pas le rôle (lecture du code), si bien que ce compte créerait des missions en tant que client.

**A5. Un administrateur a tous les droits de l'application, quelles que soient ses capacités** (vérifié par la sonde). La porte d'autorisation répond « oui » à tout administrateur pour toute action qui ne commence pas par `admin.`, y compris une action qui n'existe pas (`AppServiceProvider`, `Gate::before`). Un administrateur limité à la FAQ obtient « oui » pour `devis.accept`. Les contrôles de propriété des contrôleurs limitent la portée réelle, mais le principe contredit la restriction affichée.

**A6. Le rôle `driver` survit en base et dans le code** (vérifié). La base migrée contient 7 lignes `permission_role` pour `driver`, rôle qui n'existe pas (Règle d'or 63). `PermissionSeeder` et le repli de `HasPermissions` le portent encore. L'écran ne le montre plus : ces lignes sont invisibles et sans effet.

**A7. Relancer le `PermissionSeeder` efface les réglages faits dans l'écran** (lecture du code). Pour chaque rôle, le seeder supprime toutes les lignes puis réinscrit les droits d'origine. Trois migrations passées l'appellent ; une migration future qui ferait de même, ou un `db:seed` lancé en production, annulerait sans trace les retraits et attributions des administrateurs.

**A8. Trois copies de la liste des droits d'origine** (lecture du code) : `PermissionSeeder`, le repli de `HasPermissions::getDefaultRolePermissions` (utilisé quand `permission_role` est vide) et, en partie, `RolePermissionService::PROTECTED`. Rien ne garantit qu'elles restent identiques.

**A9. Aucun retour aux droits d'origine, aucun historique dans l'écran** (lecture du code). Depuis le lot D, chaque changement est audité, mais l'écran ne dit pas qu'un rôle s'écarte de ses droits d'origine et ne permet pas d'y revenir.

### B. Capacités des administrateurs

**B1. Le tableau de bord est servi entier à tout administrateur** (vérifié par la sonde). `/admin/dashboard` n'exige aucune capacité. Un administrateur limité à la FAQ, à qui `/admin/users` et `/admin/transactions` répondent 403, reçoit sur le tableau de bord : les comptes récents avec leur téléphone, les transactions, les missions, les litiges, les dossiers KYC, les évaluations et les indicateurs financiers (solde général, commissions par fournisseur et par livreur, retraits récents). L'écran masque les onglets ; les données, elles, sont envoyées au navigateur.

**B2. Le centre de notifications montre les notifications de tous les utilisateurs à tout administrateur** (vérifié par la sonde). `/admin/notifications` n'exige aucune capacité. La sonde y lit le texte d'une notification d'un client — « Code de retrait 4821 » — et son téléphone. Or le catalogue envoie par notification des codes de retrait, de prise en charge et de bon matériel. Un administrateur restreint à la FAQ peut donc lire des codes que la Règle d'or 38 tient pour secrets.

**B3. Deux capacités du catalogue ne sont appliquées nulle part** (vérifié) : `admin.notifications.view` — prévue pour B2 — et `admin.fraud.view`. Les cocher ou les décocher ne change rien. Les 37 autres sont exigées par au moins une route.

**B4. Le reste du backoffice est bien couvert** (vérifié). Sur 192 routes `/admin/*`, 176 exigent une capacité. Les 16 autres sont la connexion, la déconnexion, la session, le manuel, la fin d'usurpation, le tableau de bord (B1) et le centre de notifications (B2).

**B5. Toutes les capacités du catalogue existent en base** (vérifié sur une base migrée). Le risque demeure pour l'avenir : une capacité ajoutée au catalogue sans migration n'a pas de ligne, `sync` l'ignore en silence, et si c'était la seule cochée, le compte se retrouve sans ligne — donc avec l'accès total (filet de secours de la Règle d'or 67).

**B6. « Gérer les rôles » vaut l'accès total** (déjà relevé, I2). Son porteur peut s'accorder toute capacité, et l'accorder à d'autres. Aucune règle ne limite ce qu'il attribue à ce qu'il détient lui-même.

**B7. Pas de profils types** (lecture du code). Chaque administrateur se règle capacité par capacité, parmi 39. Rien ne permet de dire « support », « finance » ou « modération » et d'appliquer le même jeu à plusieurs comptes ; deux comptes de même fonction divergent avec le temps.

### C. Écran et exploitation

**C1. L'écran ne distingue pas ce qui agit de ce qui n'agit pas** (lecture du code). C'est la conséquence visible de A1 et B3.

**C2. Les libellés des actions viennent de la base** (`permissions.description`), ceux des capacités du code. Corriger un libellé d'action demande une migration ; les deux listes ne se maintiennent pas de la même façon.

**C3. Aucun test ne vérifie qu'une action du catalogue est appliquée** (lecture du code). Le catalogue de notifications a ce garde-fou (tout événement est émis, tout appel désigne un événement) ; celui des droits ne l'a pas, ce qui explique A1 et B3.

## Conclusion

La partie **administrateurs** tient, à deux fuites près qui sont sérieuses : le tableau de bord (B1) et le centre de notifications (B2) contournent toute restriction.

La partie **rôles de l'application** est en grande partie une façade : les deux tiers des interrupteurs n'ont aucun effet, et l'accès réel est décidé par des contrôles écrits dans le code. L'écran donne à l'administrateur un pouvoir qu'il n'a pas sur 25 actions, et un pouvoir dangereux sur quelques autres (A4).

## Recommandations

Par ordre de priorité.

1. **Fermer les deux fuites du backoffice** (B1, B2, B3).
   - Centre de notifications : exiger `admin.notifications.view`, déjà au catalogue, et ne plus afficher le texte des notifications portant un code.
   - Tableau de bord : ne servir chaque bloc qu'au porteur de la capacité correspondante (`admin.users.view`, `admin.transactions.view`, `admin.litiges.view`…). C'est aussi l'occasion d'alléger le lot de données, signalé au lot F du Chantier 27.
   - `admin.fraud.view` : l'appliquer à la consultation des alertes, ou la retirer du catalogue.
2. **Limiter les droits d'application d'un administrateur** (A5) : la porte ne répond « oui » d'office que pour les actions du catalogue, et seulement au porteur d'une capacité à définir.
3. **Dire la vérité dans l'écran des rôles** (A1, A3, C1) — voir la décision n° 1.
4. **Corriger les garde-fous** (A4) : protéger les actions qui agissent, réserver `mission.create` et `mission.estimate` au client, retirer des listes les actions sans effet.
5. **Assainir le socle** (A6, A7, A8, B5, C3) : supprimer `driver`, rendre le seeder additif (il n'ajoute que ce qui manque), une seule liste des droits d'origine, un test qui échoue quand une action ou une capacité du catalogue n'est exigée nulle part, et un test qui échoue quand une capacité n'a pas de ligne en base.
6. **Confort** (A9, B7) : retour aux droits d'origine d'un rôle, profils types d'administrateur.

## Décisions attendues

1. **Que faire des 25 actions sans effet ?**
   - *Les retirer de l'écran* (recommandé) : l'écran ne montre que les 15 actions qui agissent. Rapide, honnête, sans risque.
   - *Les brancher* : poser `can:` sur les routes concernées (commandes, litiges, portefeuille…). C'est un chantier à part : une centaine de routes, et le risque de couper une fonction à des utilisateurs dont le rôle n'aurait pas reçu l'action.
   - Un mélange est possible : retirer d'abord, brancher ensuite les seules actions qu'on veut réellement pouvoir couper (par exemple « passer commande », « demander un micro-crédit »).
2. **Un administrateur restreint doit-il voir un tableau de bord ?** Recommandation : oui, réduit aux blocs de ses capacités, et aux seuls compteurs sans donnée personnelle pour les autres.
3. **Faut-il des profils types d'administrateur** (support, finance, modération) ? Recommandation : oui si l'équipe dépasse quelques comptes ; sinon reporter.
4. **Faut-il limiter ce que le porteur de « gérer les rôles » peut accorder à ce qu'il détient** (B6) ? Recommandation : non — cela compliquerait la délégation sans protéger davantage, ce porteur pouvant déjà s'accorder l'accès total ; mieux vaut réserver cette capacité à très peu de comptes.

## Suites

- **04/10/2026 — B1 et B2 fermés** (décision d'Inza Bamba, sans attendre les décisions ci-dessus) : blocs du tableau de bord servis selon la capacité ; historique du centre de notifications réservé à `admin.notifications.view` ; texte et données des notifications portant un code masqués ; « marquer comme lue » limité à ses propres notifications. Tests : `RestrictedAdminDataLeaksTest.php`.
- **Restent ouverts** : `admin.fraud.view` toujours appliquée nulle part (B3), et l'ensemble des constats A, B5 à B7 et C, en attente des décisions.
- **04/10/2026 — Chantier 28** : recommandations 1 à 6 appliquées (constats A1 à A9, B3, B5, B7, C1, C3). Restent en l'état, par décision : B6 (portée de « gérer les rôles ») et C2 (libellés des actions en base).
