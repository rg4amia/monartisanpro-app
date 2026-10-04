# Plan — Chantier 28 : Gestion des rôles — un écran qui dit vrai, des garde-fous qui portent

| Champ | Valeur |
| --- | --- |
| Statut | livré (contrôle dans le navigateur à faire) |
| Créé le | 2026-10-04 |
| Mis à jour le | 2026-10-04 |
| Auteur | Claude Code |
| Analyses liées | `../analyses/2026-10-04-analyse-module-gestion-des-roles.md` |
| Commits | — |

## Objectif

Appliquer les recommandations de l'analyse du module « Gestion des rôles » : l'écran « Rôles & Actions » ne doit proposer que ce qui a un effet, ses garde-fous doivent porter sur des actions réelles, un administrateur restreint ne doit pas exercer les actions de l'application, et le socle (catalogue, seeder) ne doit plus pouvoir défaire les réglages.

Les constats B1 et B2 (fuites du tableau de bord et du centre de notifications) ont été fermés le 04/10/2026, avant ce chantier.

## Décisions retenues

Demande d'Inza Bamba du 04/10/2026 : appliquer les recommandations de l'analyse.

1. **Actions sans effet** : retirées de l'écran. Aucune n'est branchée sur une route dans ce chantier.
2. **Tableau de bord d'un administrateur restreint** : conservé, réduit aux blocs de ses capacités (déjà fait).
3. **Profils types d'administrateur** : ajoutés, sous la forme la plus simple (un jeu de capacités appliqué d'un geste).
4. **Porteur de « gérer les rôles »** : aucune limite ajoutée à ce qu'il peut accorder.

## Périmètre

- Inclus : `RolePermissionService`, `HasPermissions`, `PermissionSeeder`, porte d'autorisation (`AppServiceProvider`), `AdminPermissionService`, `AdminPanelData`, écran `roles-permissions-panel.tsx`, section des alertes de fraude.
- Exclu : brancher de nouvelles actions sur les routes de l'API mobile ; remplacer les contrôles de rôle écrits dans le code ; application mobile (aucun changement).

## Étapes

1. **Actions réglables lues dans la table des routes** — `RolePermissionService::effectiveActions()` relève les actions exigées par `can:<action>`. L'écran, l'API et les garde-fous ne connaissent plus qu'elles ; régler une autre action est refusé (422).
2. **Garde-fous corrigés** — `PROTECTED` : les actions dont dépend un chantier en cours (photos d'étape, demande de code, bon matériel) ne se retirent plus ; `RESERVED` : chaque action qui agit est réservée aux rôles dont son contrôleur suppose l'identité (« créer une mission » au client).
3. **Porte d'autorisation** — une action que rien n'exige n'est accordée d'office à personne ; seul un administrateur à accès total exerce les actions de l'application.
4. **Socle** — une seule liste des droits d'origine (`RolePermissionService::DEFAULTS`) ; seeder additif ; rôle `driver` supprimé du code et de la base ; une capacité sans ligne en base est refusée au lieu d'être ignorée.
5. **`admin.fraud.view`** — appliquée à la liste nominative des alertes de fraude de l'écran Santé & Observabilité.
6. **Confort** — retour aux droits d'origine d'un rôle ; profils types d'administrateur.
7. **Tests de garde** — une action exigée par une route existe au catalogue ; toute capacité du catalogue est installée et appliquée ; les garde-fous ne citent que des actions qui agissent.

## Règles d'or concernées

16 et 67 (capacités fines, anti-verrouillage), 17 (audit), 29 (ne rien afficher qui trompe), 35 (français), 36 (une ressource n'est exercée que par l'acteur prévu), 49 (couche service), 55 (migration idempotente), 63 (rôle `livreur`), 54, 70, 77.

## Vérification

- **Pest** : `Chantier28RolesManagementTest.php` ; tests existants alignés (`RolePermissionTest`, `Chantier27LotDRolesAndRightsTest`, `Chantier27LotEAccountRulesTest`, `BackofficeSessionRoutesTest`). Suite complète sur SQLite et MariaDB 11.8.
- **Vitest** : `roles-permissions-panel.test.tsx`.
- **Manuel** : dans le navigateur, écran « Rôles & Actions » pour chaque rôle, retour aux droits d'origine, application d'un profil type.

## Écarts

- **Écran des rôles presque en lecture seule** : sur les 15 actions qui agissent, 13 sont indispensables à leur rôle. Il reste deux réglages réels — « demander une estimation » pour le client, « modifier un devis » pour l'artisan — et le retrait de « demander un code d'étape » au client, qui ne s'en sert pas. Le livreur n'a aucune action réglable : l'écran le dit. C'est l'état exact du produit, que l'ancien écran masquait derrière 40 interrupteurs.
- **Actions réservées masquées** : une action réservée à d'autres rôles n'est plus affichée grisée pour le rôle consulté, elle n'apparaît pas.
- **Lignes en base conservées** : les droits sans effet restent dans `permission_role` (ils reprendraient effet si une route venait à les exiger) ; seules les lignes `driver` sont supprimées.
- **Brancher une action** : poser `can:<action>` sur une route suffit à la faire apparaître dans l'écran. Il faut alors l'ajouter à `RESERVED` — le test de garde l'exige — et vérifier que les rôles prévus la détiennent.
- **Administrateur restreint et API mobile** : il n'obtient plus les actions de l'application. Aucun parcours du backoffice n'en dépend (les routes du backoffice exigent des capacités `admin.*`).
- **Profils types** : Support, Finance, Modération, Communication. Leur contenu est une proposition à relire ; aucun ne contient « gérer les rôles ». Un profil n'est pas mémorisé sur le compte : seules ses capacités le sont, et elles se modifient ensuite une à une.
- **Alertes de fraude** : sans `admin.fraud.view`, les compteurs restent visibles et la liste annonce qu'elle n'est pas accessible — jamais « aucune alerte ».
- **Non fait** : libellés des actions toujours en base (constat C2) ; aucune limite à ce que le porteur de « gérer les rôles » accorde (décision n° 4).
