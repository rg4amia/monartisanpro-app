# Plan — Chantier 16 : Liens Google Play et App Store pilotés depuis le backoffice

| Champ | Valeur |
| --- | --- |
| Statut | livré (2026-09-29) |
| Créé le | 2026-09-29 |
| Mis à jour le | 2026-09-29 |
| Auteur | Claude Code |
| Analyses liées | — (constat ci-dessous) |
| Commits | — |

## Objectif

1. Gérer depuis le backoffice les liens de téléchargement de l'application mobile vers **Google Play** et l'**App Store** : les créer, les valider, les désactiver.
2. Quand au moins un lien est validé, afficher sur la page d'accueil du site vitrine une section « Téléchargez l'application », plus soignée que le visuel de référence fourni (téléphone, texte d'appel, badges des magasins).

## Constat (29/09/2026)

- Aucun lien vers les magasins d'applications n'existe, ni dans les réglages de la vitrine (`vitrine_settings`), ni dans le code du site.
- Les réglages de la vitrine sont un magasin clé-valeur sans cycle de validation : un lien saisi y serait publié immédiatement, sans relecture.

## Décisions

1. **Cycle d'un lien** : `brouillon` (créé, invisible) → `publie` (validé, affiché sur le site) → `desactive` (retiré du site, conservé pour l'historique).
   - Un brouillon ou un lien désactivé se modifie, se valide ou se supprime.
   - Un lien publié ne se modifie pas : on le désactive, ou l'on crée puis valide un nouveau lien qui le remplace. Le site n'affiche ainsi jamais une adresse non relue.
   - **Un seul lien publié par magasin** : valider un lien désactive automatiquement le précédent lien publié du même magasin.
2. **Adresse contrôlée par le serveur** : `https://play.google.com/store/apps/details?id=…` pour Google Play, `https://apps.apple.com/…/app/…/id…` pour l'App Store. Toute autre adresse est refusée, à la création comme à la validation.
3. **Capacité** : `admin.vitrine.manage`, déjà attribuée au contenu du site vitrine ; aucune nouvelle capacité.
4. **Audit** (Règle d'or 17) : `app_store_link.created` / `.updated` / `.published` / `.disabled` / `.deleted`, avec l'adresse avant et après.
5. **Site vitrine** : section affichée seulement si l'API renvoie au moins un lien publié ; sans réponse de l'API, rien n'est affiché (jamais de lien par défaut, Règle d'or 29). Seuls les badges des magasins publiés apparaissent.

## Conception

- Table `app_store_links` : `platform` (`android` / `ios`), `url`, `status`, `created_by`, `published_by`, `published_at`, `disabled_by`, `disabled_at`, horodatages.
- `AppStoreLinkService` : règles métier (validation d'adresse, publication exclusive par magasin, liens publics) ; `Admin\AppStoreLinkAdminService` : données de l'onglet et audit ; contrôleur `Admin\AppStoreLinkAdminController`.
- Routes backoffice `/admin/applications-mobiles` (onglet « Applications mobiles », section Plateforme) ; route publique `GET /api/v1/vitrine/app-links` (`throttle:public`).
- Vitrine : composant `AppDownloadSection` (maquette de téléphone en CSS aux couleurs de ProsArtisan, badges officiels redessinés en SVG), inséré sur la page d'accueil avant le bandeau d'appel final.

## Tests

- Pest `AppStoreLinkTest` : cycle complet, refus d'adresse invalide, exclusivité par magasin, lien publié non modifiable, route publique ne renvoyant que les liens publiés, capacité exigée, audit.
- Vitest backoffice `AppStoreLinksPanel.test.tsx` ; Vitest vitrine `AppDownloadSection.test.tsx`.
