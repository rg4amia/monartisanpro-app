# Plan — Chantier 15 : Annuaire artisans, disponibilité validée et présence pilotée

| Champ | Valeur |
| --- | --- |
| Statut | livré (2026-09-29) |
| Créé le | 2026-09-29 |
| Mis à jour le | 2026-09-29 |
| Auteur | Claude Code |
| Analyses liées | — (constat ci-dessous) |
| Commits | — |

## Objectif

1. Publier sur l'annuaire du site vitrine (`/artisans`) la **disponibilité** de chaque artisan : statut, jours et horaires habituels, interventions de nuit.
2. Soumettre toute disponibilité déclarée par l'artisan à la **validation d'un administrateur avant publication**.
3. Permettre aux administrateurs d'**activer ou de désactiver** la présence d'un artisan dans l'annuaire.

## Constat (29/09/2026)

- L'annuaire affiche tout artisan au KYC actif, sans aucun moyen de retirer une fiche hormis la suspension du compte.
- `VitrineController::artisans` interroge la base directement, contrairement à la Règle d'or 49. Son filtre par commune vise une colonne `communes.nom` qui n'existe pas (la colonne est `name`) : toute recherche par commune échoue.
- Les cartes n'affichent ni métier ni commune : les relations chargées ne sont pas aplaties et le front lit des clés (`trade`, `city`) que l'API ne renvoie pas. D'où « Artisan » et « Abidjan » par défaut.
- En cas d'erreur réseau, le front affiche quatre artisans fictifs (`MOCK_ARTISANS`), indiscernables de vrais comptes, contrairement à la Règle d'or 29.
- La seule notion de disponibilité existante est l'interrupteur « Interventions de nuit » du profil mobile (`artisan_profiles.intervient_la_nuit`), sans validation ni affichage public.

## Décisions (arbitrées le 29/09/2026)

1. **Contenu de la disponibilité** :
   - un **statut** : Disponible, Occupé jusqu'au…, ou En congé jusqu'au… ;
   - les **jours et plages horaires** habituels ;
   - les **interventions de nuit**.
2. **Saisie** : l'**artisan** déclare sa disponibilité dans l'application. Elle reste « en attente », et la dernière version validée reste publiée jusqu'à la décision. L'**administrateur** peut aussi la saisir ou la corriger depuis le backoffice : sa version est publiée directement.

## Conception

- **Table `artisan_availabilities`** : une ligne par version, jamais réécrite après décision.
  - Colonnes : `status` (`disponible` / `occupe` / `conge`), `until_date`, `schedule_json` (liste de plages `{day: 1..7, start: "HH:MM", end: "HH:MM"}`), `night_work`.
  - Statut de revue `review_status` : `en_attente` → `validee`, `refusee` (motif obligatoire) ou `remplacee`, quand une nouvelle déclaration arrive avant la décision.
  - Traçabilité : `submitted_by`, `reviewed_by`, `reviewed_at`, `rejection_reason`.
  - **Version publiée** : la dernière `validee`.
  - **Statut affiché** : un statut « Occupé » ou « En congé » dont la date de retour est passée s'affiche « Disponible », sans nouvelle validation.
- **Présence dans l'annuaire** : colonnes `users.directory_hidden_at`, `directory_hidden_by` et `directory_hidden_reason`.
  - Actif par défaut : l'annuaire garde les artisans déjà publiés.
  - Désactiver exige un motif et réactiver efface les trois colonnes. Les deux actions sont auditées.
  - La désactivation ne touche ni le compte ni les missions : seule la fiche publique disparaît.
- **`ArtisanDirectoryService`** : la requête publique en sort du contrôleur (Règle d'or 49).
  - Filtres : métier, commune (corrigé), score minimum et « disponible maintenant ».
  - Critères cumulés : KYC actif, compte actif, non anonymisé, non retiré de l'annuaire.
  - Sortie aplatie : nom, métier, commune, score, photo de profil, disponibilité publiée.
  - Aucun téléphone ni pièce KYC n'est exposé.
- **API mobile** (artisan, `auth:sanctum`) :
  - `GET /api/v1/artisan/availability` renvoie la version publiée, la déclaration en attente et le dernier refus avec son motif ;
  - `POST /api/v1/artisan/availability` dépose une déclaration.
- **Backoffice** : onglet **Annuaire artisans** (`/admin/annuaire-artisans`, capacité `admin.directory.manage`).
  - Liste paginée côté serveur (Règle d'or 19) avec filtres : présence, disponibilité en attente, recherche.
  - File des disponibilités à valider, avec l'avant/après.
  - Actions : valider, refuser (motif ≥ 5 caractères, via `useConfirm`), saisir la disponibilité, activer ou désactiver dans l'annuaire.
  - Tout est audité (Règle d'or 17) : `directory.availability.approved` / `.rejected` / `.admin_set`, `directory.artisan.hidden` / `.shown`.
- **Notifications** : quatre événements du catalogue du Chantier 14, en push seul.
  - Disponibilité validée : `annuaire.disponibilite_validee.artisan`.
  - Disponibilité refusée, avec son motif : `annuaire.disponibilite_refusee.artisan`.
  - Fiche retirée de l'annuaire : `annuaire.retire.artisan`.
  - Fiche rétablie : `annuaire.reactive.artisan`.
- **Vitrine** :
  - chaque carte affiche un badge de disponibilité, les jours et horaires et la mention « Intervient la nuit » ;
  - un filtre « Disponibles maintenant » est ajouté ;
  - métier et commune sont réels ;
  - en cas d'erreur, un message d'erreur remplace les artisans fictifs.
- **Mobile** : le profil artisan gagne une section **Ma disponibilité** : statut, date de retour, jours et horaires, nuit. Elle indique ce qui est publié, en attente ou refusé (avec son motif).

## Lots

- **Lot A — Backend** : migration idempotente, modèles, `ArtisanDirectoryService`, `ArtisanAvailabilityService`, API mobile et vitrine, service et contrôleur admin, capacité, événements, tests Pest.
- **Lot B — Backoffice** : onglet, panneau, tests Vitest.
- **Lot C — Vitrine** : cartes, filtre, erreur explicite, tests Vitest.
- **Lot D — Mobile** : section « Ma disponibilité », dépôt et lecture, tests Flutter.
- **Manuel d'utilisation** : chapitres artisan et backoffice.

## Règles d'or concernées

6 (aucune position exacte exposée), 16 (capacité fine), 17 (audit), 19 (listes paginées), 29 (ni données inventées ni cadre muet), 35 (français), 36 (propriété : un artisan ne dépose que sa propre disponibilité), 47 (portabilité SQLite / MariaDB), 49 (couche service), 54, 55 (migration idempotente), 70, 77.

## Vérification

- **Pest** :
  - une déclaration reste invisible avant validation ;
  - un refus exige un motif ;
  - une saisie admin est publiée directement ;
  - une nouvelle déclaration remplace l'attente ;
  - un artisan désactivé disparaît de l'annuaire et de sa fiche ;
  - le filtre par commune fonctionne ;
  - la capacité est exigée ;
  - un non-artisan est refusé ;
  - l'audit et les notifications sont émis ;
  - suite complète sur SQLite et MariaDB 11.8.
- **Vitest** : panneau du backoffice, cartes et filtre de la vitrine.
- **Flutter** : lecture et dépôt de la disponibilité.

## Écarts (29/09/2026)

- **Livré en un seul passage** : les lots A à D et le manuel.
- **Photo de la carte** : la vitrine utilisait le champ `kyc_selfie_path`, jamais renvoyé par l'API, et affichait donc une photo de banque d'images, identique pour tous les artisans. Elle affiche désormais la photo professionnelle du profil (`artisan_profiles.photo_url`) ou, à défaut, les initiales. La photo du compte (`users.photo_path`), stockée sur le disque privé, n'est pas exposée.
- **Artisans fictifs** : la vitrine se rabattait sur des artisans fictifs (`MOCK_ARTISANS`) quand l'API ne répondait pas. Ce repli est supprimé de l'annuaire, qui annonce désormais l'erreur avec un bouton « Réessayer », et de la section « mieux notés » de l'accueil, qui reste alors masquée. L'« artisan du mois » de démonstration n'est pas touché (hors périmètre).
- **Interventions de nuit** : la déclaration porte sa propre case, validée avec le reste et affichée sur la vitrine. L'interrupteur existant du profil (`artisan_profiles.intervient_la_nuit`), utilisé par la mise en relation dans l'application, reste inchangé et indépendant.
- **Tri de l'onglet** : les artisans dont une disponibilité attend une décision passent en tête, ce qui tient lieu de file de validation. Aucun compteur n'a été ajouté dans la navigation.
- **Fiche publique** : `GET /vitrine/artisans/{id}` renvoie désormais une fiche aplatie (métier, commune, disponibilité) au lieu du modèle brut avec ses relations. Aucun écran de la vitrine ne lisait cette route.
- **Hors périmètre, constaté** : `VitrineController::artisanDuMois` filtre encore les missions sur le statut français `terminee`, contrairement à la Règle d'or 27. Le taux de réussite affiché y vaut donc toujours 0 : correction à planifier.
