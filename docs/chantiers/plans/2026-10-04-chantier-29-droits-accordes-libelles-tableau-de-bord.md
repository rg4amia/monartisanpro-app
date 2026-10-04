# Plan — Chantier 29 : droits accordés dans la limite des siens, libellés des actions, tableau de bord allégé

| Champ | Valeur |
| --- | --- |
| Statut | livré (contrôle dans le navigateur à faire) |
| Créé le | 2026-10-04 |
| Mis à jour le | 2026-10-04 |
| Auteur | Claude Code |
| Analyses liées | `../analyses/2026-10-04-analyse-module-gestion-des-roles.md` |
| Commits | — |

## Objectif

Traiter les trois points laissés ouverts après le Chantier 28 : le constat B6 (« gérer les rôles » vaut l'accès total), le constat C2 (libellés des actions en base) et les listes complètes encore transmises au tableau de bord.

## Décisions retenues

Demande d'Inza Bamba du 04/10/2026 : traiter tous les points restants. La décision n° 4 du Chantier 28 (aucune limite à ce que le porteur de « gérer les rôles » accorde) est donc levée.

## Périmètre

- Inclus : `AdminPermissionService`, `AdminUserService`, `RolePermissionService`, `PermissionSeeder`, `AdminPanelData::dashboard`, écran `roles-permissions-panel.tsx`.
- Exclu : application mobile ; nouvelles capacités ; contenu des profils types.

## Étapes

1. **Nul n'accorde ce qu'il ne détient pas (B6)** — `AdminPermissionService::sync` refuse à un administrateur restreint d'ajouter à un compte, le sien compris, une capacité qu'il ne détient pas, et l'accès total. Seul un administrateur à accès total accorde tout.
2. **Compte plus étendu hors de portée** — `AdminPermissionService::covers` : un administrateur restreint ne modifie ni les droits ni le compte (nom, mot de passe, statut, suppression, anonymisation) d'un administrateur qui détient des droits qu'il n'a pas. Changer son mot de passe suffisait à s'en emparer.
3. **Administrateur créé ou promu** — il reçoit l'accès total si son auteur l'a, sinon les capacités de son auteur (`initialCapabilitiesGrantedBy`).
4. **Écran** — les capacités hors de portée et la case « Accès total » sont grisées, un profil type qui en contient une est inactif, un compte hors de portée est annoncé (`grantableCapabilities`, `editable`).
5. **Libellés des actions dans le code (C2)** — `RolePermissionService::CATALOG` (rubrique et libellé) ; l'écran et l'API le lisent, le seeder en écrit une copie en base. Libellés réécrits dans les termes de l'application.
6. **Tableau de bord** — litiges, missions et transactions réduits aux champs lus (compteurs, courbes, activité récente) ; commandes, évaluations et classement des artisans, que le tableau de bord n'affichait pas, ne sont plus transmis.

## Règles d'or concernées

16, 67 et 99 (capacités, anti-verrouillage, comptes administrateurs), 17 (audit), 22 (données personnelles), 29, 35, 36, 49, 54, 70, 77, 105, 106.

## Vérification

- **Pest** : `Chantier29RightsFollowUpsTest.php` (11 tests) ; `RestrictedAdminDataLeaksTest`, `Chantier27LotBBackofficeAccountsTest` alignés. Suite complète sur SQLite et MariaDB 11.8.
- **Vitest** : `roles-permissions-panel.test.tsx`.
- **Manuel** : dans le navigateur, connecté avec un compte restreint porteur de « gérer les rôles » — cases grisées, compte à accès total verrouillé ; tableau de bord (courbes, activité récente).

## Écarts

- **Défaut trouvé en chemin** : la liste des administrateurs de l'écran « Rôles & Actions » était chargée sans la colonne du rôle, si bien que chaque compte non protégé s'affichait avec « 0 droit(s) » et aucune case cochée. Corrigé et couvert par un test.
- **Anti-verrouillage conservé** : sur son propre compte, un administrateur garde toujours « gérer les rôles » et « consulter les utilisateurs », même s'il ne détenait pas la seconde.
- **Retirer reste libre** : un administrateur restreint retire toute capacité à un compte qu'il couvre.
- **Rétrogradation et suppression** d'un administrateur plus étendu : refusées au même titre que la modification.
- **Libellés en base** : conservés en copie (colonnes `description` et `category`), mis à jour quand le seeder est relancé ; plus rien ne les lit pour l'affichage tant que l'action figure au catalogue du code.
