# Plan — Chantier 42 : fin de session, file hors connexion, pannes annoncées, adresses de fichiers

| Champ | Valeur |
| --- | --- |
| Statut | livré (nouvelle version de l'application à publier ; contrôle sur appareil à faire) |
| Créé le | 2026-10-07 |
| Mis à jour le | 2026-10-07 |
| Auteur | Claude Code (Opus 5.5) |
| Analyses liées | `../audits/2026-10-07-audit-application-mobile.md` (anomalies 1, 2, 3, 4, 5 et 18) |
| Commits | — |

## Objectif

Corriger les six anomalies élevées de l'audit de l'application mobile : ce qui reste sur le téléphone quand un compte s'en va, ce que la file hors connexion rejoue et pour qui, ce qui sort dans le journal du téléphone, les pannes affichées comme une absence, et les adresses de fichiers choisies par un autre utilisateur.

## Périmètre

- Inclus : application mobile (fin de session, file hors connexion, journal, lectures du livreur et des évaluations, ouverture des vidéos) ; serveur (validation des adresses de photos d'une demande de mission et d'une étape).
- Exclu : anomalies moyennes et faibles de l'audit (6 à 17, 19 à 23), qui restent ouvertes.

## Étapes

1. **Fin de session unique** — `SessionEnd.wipeLocalData()` (`lib/core/services/session_end.dart`), appelée par la déconnexion (`AuthRepository.logout`), la session expirée (401, `ApiClient`) et la suppression de compte (`SettingsController.deleteAccount`). Elle efface le jeton et l'identité du compte (`StorageService.clearSession`), détache l'appareil des notifications, vide tous les caches et retire de l'écran la file hors connexion.
2. **Caches vidés même non ouverts** — `CacheStore.wipeAll()` passe par `CacheStore.knownBoxNames` : le registre ne connaissait que les caches utilisés depuis l'ouverture de l'application, et le cache des missions (`MissionCacheService`) n'était jamais vidé.
3. **Réglages de l'appareil conservés** — accueil déjà vu, son, économie de données et empreinte de l'appareil ne sont plus effacés avec le compte.
4. **File hors connexion rattachée à un compte** — chaque requête en file et chaque annonce d'échec porte `owner_id`. Seul son auteur la rejoue et la voit (`belongsToAccount`). Le compteur `pendingCount` ne compte que les requêtes du compte connecté.
5. **File hors connexion chiffrée** — boîtes `offline_sync_queue_v2` et `offline_sync_failures_v2`, ouvertes avec le chiffrement des caches. Les anciennes boîtes en clair sont reprises pour le compte connecté à la mise à jour, puis supprimées du disque.
6. **Journal du téléphone** — `debugPrint` neutralisé en version de publication (`main.dart`) ; l'adresse de l'Assistant, qui porte le jeton, n'est plus journalisée (trois endroits de `ia_assistant_screen.dart`).
7. **Pannes annoncées** — `OrderRepository.getAvailableDeliveries`, `getDeliveryBatches`, `getActiveDeliveryTour`, `getMyOrders`, `getOrderTracking` et les trois lectures d'`EvaluationRepository` laissent remonter l'erreur. L'accueil du livreur affiche « Impossible de charger vos courses » avec « Réessayer » (`HomeController.driverMissionsLoadFailed`) et garde les dernières courses connues ; les évaluations d'une mission et « Mes avis » annoncent l'échec.
8. **Adresses de fichiers, serveur** — règle `App\Rules\PlatformFileUrl` sur `photos.*` (`CreateMissionRequest`) et `photos.*.url` (`SubmitJalonRequest`) : fichier privé ou ancienne adresse `/storage/`, sur le domaine de l'API.
9. **Adresses de fichiers, application** — `openPlatformVideo` (`lib/core/utils/platform_media.dart`) n'ouvre hors de l'application qu'une adresse du domaine de l'API.

## Règles d'or concernées

- 29 et 75 : une panne n'est jamais affichée comme une absence.
- 36 : une action n'est rejouée que par le compte qui l'a faite.
- 38 : les codes de retrait et de réception ne sont pas gardés en clair.
- 71 : l'empreinte de l'appareil reste la même d'un compte à l'autre.
- 79 : toute fin de session détache l'appareil des notifications.
- 112 : une action hors connexion non aboutie reste annoncée, à son auteur.

## Vérification

- Flutter : `sync_queue_ownership_test.dart` (8 tests), `session_end_test.dart` (4), `platform_media_test.dart` (4), `driver_missions_load_failure_test.dart` (5). `flutter analyze` : aucun problème. Suite complète : 552 réussis, 4 ignorés.
- Pest : `Chantier42PlatformFileUrlTest.php` (4 tests).
- Non vérifié : rien n'a été exécuté sur un téléphone. À contrôler sur appareil : reprise de la file d'une version précédente à la mise à jour, rejeu après reconnexion du même compte, absence de rejeu sous un autre compte, écran du livreur en mode avion.

## Écarts

- **La file n'est pas vidée à la fin de session**, contrairement au correctif proposé par l'audit. Une session expirée pendant un rejeu aurait fait perdre, sans un mot, une livraison validée hors connexion. Les requêtes restent, chiffrées, pour leur auteur ; elles expirent au bout de trois jours et ne sont supprimées qu'avec le compte.
- **Une action en file sans auteur n'est jamais rejouée.** À la mise à jour, les requêtes des versions précédentes sont attribuées au compte connecté ; si personne ne l'est, elles sont abandonnées.
- **La règle serveur couvre aussi les photos d'étape**, que l'audit ne citait pas : même défaut, dans l'autre sens (l'artisan vers le client). Les jeux d'essai de quatre tests existants, qui postaient des adresses `example.com`, ont été adaptés.
- **Trois journaux de l'adresse de l'Assistant** au lieu d'un : le test de garde a trouvé le troisième.
- **Empreinte de l'appareil** : la déconnexion l'effaçait, si bien qu'elle changeait à chaque compte. Elle est désormais conservée.
