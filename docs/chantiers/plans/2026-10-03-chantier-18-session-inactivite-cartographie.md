# Plan — Chantier 18 : fermeture de session du backoffice après inactivité, et Cartographie & Territoires

| Champ | Valeur |
| --- | --- |
| Statut | livré (contrôle dans le navigateur à faire) |
| Créé le | 2026-10-03 |
| Mis à jour le | 2026-10-03 |
| Auteur | Claude (à la demande d'Inza Bamba) |
| Analyses liées | — |
| Commits | — |

## Objectif

1. **Lot A** — fermer la session d'un administrateur resté 15 minutes sans activité : il doit se reconnecter (mot de passe et 2FA) pour revenir au backoffice.
2. **Lots B1 à B3** — faire de l'onglet « Cartographie & Territoires » une vue exhaustive de l'écosystème : carte filtrable par type (clients, artisans, livreurs, missions, quincailleries) et tableau récapitulatif par zone.

Les deux sujets sont indépendants et se livrent séparément, le lot A en premier.

## État des lieux

### Session

- La session web dure 120 minutes (`SESSION_LIFETIME`), pour tout le site. Rien ne ferme une session admin inactive.
- Le backoffice est gardé par `auth` + `admin.only` (`routes/web.php`). L'usurpation de session garde l'identité de l'admin dans `session('impersonator_id')`.
- Aucun rafraîchissement automatique en arrière-plan n'existe dans les pages admin : une requête correspond aujourd'hui toujours à une action de l'utilisateur.

### Cartographie & Territoires

Fichiers : `AdminTerritoryService`, `AdminTerritoryController`, `AdminPanelData::cartography`, `CartographyPanel.tsx`, `IvoryCoastMapSvg.tsx`, `ivoryCoastGeoData.ts`.

Défauts constatés à la lecture du code (à reproduire par un test avant correction) :

1. **Les filtres par type du tableau ne filtrent pas.** L'écran envoie `artisan`, `client`, `fournisseur`, `livreur`, `mission`, `litige` ; le service attend `artisans`, `clients`, `fournisseurs`, `livreurs`, `missions`, `litiges`. Chaque onglet affiche donc tous les utilisateurs, y compris « Missions » et « Litiges ».
2. **La recherche de missions vise une colonne absente** (`location_address`, la colonne réelle est `client_address`) : erreur SQL sur MariaDB, et la localisation d'une mission s'affiche toujours « Côte d'Ivoire ».
3. **Des indicateurs affichent des valeurs jamais calculées** : « 0 avec mission active », « KYC validés (0 %) », « 0 courses en transit », « boutiques certifiées J-Code » (qui répète le nombre de comptes fournisseur). Contraire à la Règle d'or 29.
4. **Statuts affichés en clé technique** (`in_progress`, `en_attente`), contraire à la Règle d'or 27.
5. **Un utilisateur sans commune est affiché « Abidjan »**, et n'entre dans aucune zone : la somme des zones ne retrouve pas le total national.
6. **La carte ne distingue pas les types** : elle colore les zones sur le total des acteurs, le volume ou le taux de réalisation. Aucune couche par type, aucun point.
7. **Coût** : chaque rafraîchissement de la carte calcule une synthèse complète par zone (27 zones, une dizaine de requêtes chacune), mise en cache 60 secondes.

## Décisions à valider

1. **Avertissement avant fermeture** — recommandé : une fenêtre s'affiche à 14 minutes (« Votre session va se fermer dans 60 secondes ») avec un bouton « Rester connecté ». Sans elle, un administrateur perd une saisie en cours sans préavis.
2. **Points individuels sur la carte** — recommandé :
   - points pour les **quincailleries** (adresse commerciale), les **missions en cours** (lieu du chantier) et les **livreurs en course** (dernière position connue) ;
   - **clients et artisans regroupés par zone**, sans point individuel : la position d'un domicile n'est pas nécessaire au pilotage et un compte par zone suffit (minimisation des données personnelles, Règle d'or 22).
   L'alternative est d'afficher aussi un point par artisan.

## Périmètre

- Inclus : backoffice `/admin/*` (session), onglet « Cartographie & Territoires » (service, carte, tableau, export).
- Exclu : durée de session de l'application mobile (jetons Sanctum) et de l'espace fournisseur du site vitrine ; fond de carte en tuiles (la carte reste le tracé SVG existant, aucune intégration cartographique nouvelle — Règle d'or 44) ; carte de supervision de la flotte livreur (Règle d'or 61), inchangée.

## Étapes

### Lot A — Fermeture de session après 15 minutes d'inactivité

1. **Configuration** — `config('prosartisan.admin.idle_timeout_minutes')` (15, variable `ADMIN_IDLE_TIMEOUT_MINUTES`), jamais `env()` hors de `config/`.
2. **Middleware `admin.idle`** (`EnforceAdminIdleTimeout`) sur le groupe `auth` + `admin.only` et sur `stop-impersonating` : le serveur est seul juge.
   - Il lit `session('admin_last_activity_at')`. Au-delà du délai : déconnexion, session invalidée, jeton CSRF régénéré, ligne d'audit `admin.session.expired_idle` (Règle d'or 17).
   - Réponse selon l'appelant : redirection vers `/admin/login` avec le message « Votre session a été fermée après 15 minutes d'inactivité. Reconnectez-vous. » ; `Inertia::location` pour une navigation Inertia ; 401 JSON pour un appel `fetch`.
   - Sinon, il met l'horodatage à jour.
   - Une session usurpée expire de la même façon, sans retour automatique au compte de l'administrateur.
3. **Maintien de session** — `POST /admin/session/keep-alive` : met l'horodatage à jour, sans autre effet.
4. **Front** — hook `useIdleLogout` monté dans `AdminShell` :
   - suit l'activité (souris, clavier, toucher, défilement) ;
   - partage l'horodatage entre onglets du navigateur (`localStorage`), pour qu'un onglet actif ne laisse pas un autre fermer la session ;
   - prévient le serveur au plus une fois toutes les 5 minutes quand il y a eu activité sans requête (longue saisie dans un formulaire) ;
   - à 14 minutes, fenêtre d'avertissement accessible (`role="dialog"`, focus, compte à rebours) avec « Rester connecté » ;
   - à 15 minutes, déconnexion et retour à la page de connexion, qui affiche le motif.
5. **Appels `fetch` existants** (`/admin/cartographie/stats`, carte de la flotte) : un 401 renvoie à la page de connexion au lieu d'afficher une erreur de chargement.
6. **Requêtes d'arrière-plan** — règle posée pour l'avenir : un rafraîchissement automatique ne compte pas comme une activité et ne prolonge pas la session.

### Lot B1 — Fiabilisation de l'existant

1. Aligner les identifiants des types entre l'écran et le service (liste fermée, validée côté serveur ; type inconnu → 422).
2. Corriger la recherche et la localisation des missions (`client_address`, commune du carnet d'adresses).
3. Calculer réellement les indicateurs affichés ou les retirer : clients avec mission active, part d'artisans au KYC actif, courses en transit, quincailleries **agréées** (fiche `fournisseurs_agrees` validée) distinguées des comptes fournisseur.
4. Libellés français des statuts (`MissionState::labelFor()`, libellés KYC du backoffice).
5. Zone « Commune non renseignée » : les acteurs sans commune y sont comptés, jamais rangés sous Abidjan. Somme des zones + non renseignés = total national.

### Lot B2 — Carte interactive par type

1. **Matrice par zone** — nouvelle méthode de service qui calcule, en quelques requêtes groupées, le nombre de clients, artisans (dont KYC actif), livreurs, quincailleries (dont agréées), missions (en cours, terminées, en litige) et le volume, par commune puis par district. Elle remplace les 27 synthèses successives et alimente la carte comme le tableau. Mise en cache par `AdminDashboardCache`.
2. **Filtres par type** — pastilles à choix multiple au-dessus de la carte : Clients, Artisans, Livreurs, Quincailleries, Missions, chacune avec son total et sa couleur. La coloration des zones porte sur les types cochés ; « Tout » redonne la vue d'ensemble.
3. **Filtres complémentaires** — statut des missions (en cours, terminées, en litige), statut KYC des acteurs (actif, en attente), période (30 jours, 90 jours, tout).
4. **Lecture d'une zone** — au survol ou à la sélection : décompte complet par type, et non plus le seul total. Dans chaque zone, une pastille par type coché affiche son effectif.
5. **Points** (selon la décision 2) — quincailleries, missions en cours et livreurs en course, dans la vue Grand Abidjan. Si le tracé SVG des communes n'est pas géoréférencé, les points sont regroupés par commune avec leur nombre plutôt que placés à une coordonnée approximative.
6. **Légende** par type et par intensité, et mention explicite d'une zone sans donnée.
7. Les filtres de la carte et ceux du tableau sont **un seul et même état** : filtrer la carte filtre le tableau.

### Lot B3 — Tableau Récapitulatif Territorial

1. **Vue « Synthèse par zone »** (nouvelle, par défaut) — une ligne par district, ou par commune dans la vue Grand Abidjan :
   - colonnes : Clients, Artisans (dont KYC actif), Livreurs, Quincailleries (dont agréées), Missions en cours, Missions terminées, Litiges, Volume, Taux de réalisation ;
   - ligne « Commune non renseignée » et ligne de total ;
   - tri par colonne ; un clic sur une ligne sélectionne la zone sur la carte.
2. **Vue « Détail »** (liste actuelle, corrigée) — colonnes adaptées au type choisi (artisan : métier, score, KYC ; quincaillerie : secteur, agrément ; livreur : course en cours ; mission : statut, montant, client, artisan), statuts en français, tri, pagination côté serveur via `useServerTable` (Règle d'or 19).
3. **Export CSV** des deux vues par `AdminExportService` (capacité `admin.exports`, filtres identiques à l'écran, export audité — Règle d'or 20).
4. Mention explicite quand une vue est vide (Règle d'or 29).

## Règles d'or concernées

- 16, 17, 18 : capacités inchangées (`admin.territory.view`, `admin.exports`), expiration auditée, connexion toujours soumise au throttle et au 2FA.
- 24 : usurpation de session soumise à la même expiration.
- 19, 20 : pagination côté serveur, agrégats indépendants de la page, export en streaming audité.
- 22 : pas de position individuelle de client ou d'artisan sur la carte (décision 2).
- 27, 29, 35 : statuts en français, aucun indicateur inventé, zone vide annoncée.
- 44 : aucune nouvelle intégration cartographique, `env()` jamais hors de `config/`.
- 47, 49 : requêtes portables SQLite / MariaDB, aucun accès base dans les contrôleurs.
- 54, 70, 77 : PRD, règles, manuel (chapitre backoffice) et journal dans le même commit.

## Vérification

- **Lot A** — `AdminIdleTimeoutTest.php` : 16 minutes sans requête → redirection vers la connexion, session fermée, ligne d'audit ; 14 minutes → accès maintenu et délai reparti ; appel JSON → 401 ; `keep-alive` prolonge ; session usurpée expirée ; pages de connexion non concernées. `useIdleLogout.test.tsx` (horloge simulée) : avertissement à 14 minutes, « Rester connecté », déconnexion à 15 minutes, activité dans un autre onglet.
- **Lot B1** — `AdminTerritoryControllerTest.php` : chaque filtre de type ne renvoie que son type (test échouant avant correctif), recherche de mission par adresse, type inconnu refusé, acteurs sans commune comptés à part, quincailleries agréées. Suite rejouée sur MariaDB.
- **Lots B2 et B3** — tests du service (matrice : totaux par type et par zone, somme égale au national, filtres de statut et de période), `CartographyPanel.test.tsx` et `IvoryCoastMapSvg.test.tsx` (pastilles de type, décompte au survol, état partagé carte et tableau, tri, sélection d'une zone par le tableau, vue vide), test d'export.
- `./vendor/bin/pest`, `npm test`, `./vendor/bin/pint`, puis contrôle manuel dans le navigateur.

## Écarts

- **Décisions retenues** (03/10/2026) : avertissement une minute avant la fermeture ; points individuels écartés pour les clients et les artisans.
- **Middleware posé sur les groupes `web` et `api`**, pas seulement sur `/admin/*` : la session usurpée navigue hors de `/admin`, et la carte de la flotte appelle `/api/v1` avec le cookie de session. Les jetons Bearer mobiles, sans session, ne sont pas concernés.
- **Cookie « Se souvenir de moi »** (non prévu au plan) : sans traitement, il recréait une session après l'expiration du cookie de session. Une session ainsi recréée est traitée comme expirée.
- **Avertissement** : une fois affiché, seul « Rester connecté » (ou Échap) prolonge la session ; bouger la souris ne le referme pas.
- **Second défaut de colonne trouvé par MariaDB** : outre `location_address`, la recherche de missions visait `category`, absente elle aussi (colonne réelle `gemini_category`). SQLite ne le signalait pas.
- **Mission située dans une seule zone** : l'ancien calcul comptait une mission dans chaque zone liée (adresse, commune du client, commune de l'artisan), si bien que les zones ne s'additionnaient pas. Ordre retenu : adresse du chantier, puis commune du client, puis commune de l'artisan.
- **Rattachement calculé en PHP et listes filtrées par identifiants**, au lieu des filtres `LIKE` par zone. À surveiller si le nombre de missions devient très grand : l'instantané parcourt toutes les missions (mis en cache 60 secondes).
- **Points sur la carte non réalisés** : le tracé SVG des districts et des communes est stylisé, pas géoréférencé. Placer une quincaillerie ou un chantier à sa coordonnée aurait été approximatif. Les quincailleries agréées, missions en cours et livreurs en course apparaissent en effectifs par zone (pastilles par type et décompte au survol).
- **Tableau détaillé** : il garde l'appel JSON existant (`/admin/cartographie/stats`), qui renvoie en une fois la synthèse, la matrice et la liste, plutôt que `useServerTable`. La pagination reste côté serveur. Les filtres ne sont pas mémorisés d'une visite à l'autre.
- **Synthèse triée dans le navigateur** : elle compte au plus 15 lignes.
- **Cache** : la création d'un utilisateur ne purge pas l'instantané ; un nouvel inscrit apparaît au plus 60 secondes plus tard.
- **Contrôle manuel dans le navigateur** : non effectué dans cette session (connexion au backoffice avec 2FA requise).
