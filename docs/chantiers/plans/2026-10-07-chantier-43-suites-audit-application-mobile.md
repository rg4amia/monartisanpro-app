# Plan — Chantier 43 : suites de l'audit de l'application mobile (anomalies 6 à 17 et 19 à 23)

| Champ | Valeur |
| --- | --- |
| Statut | livré (nouvelle version de l'application à publier ; contrôle sur appareil à faire) |
| Créé le | 2026-10-07 |
| Mis à jour le | 2026-10-07 |
| Auteur | Claude Code (Opus 5.5) |
| Analyses liées | `../audits/2026-10-07-audit-application-mobile.md` |
| Commits | `edf9bd20` |

## Objectif

Clore les anomalies moyennes et faibles de l'audit de l'application mobile, après les six élevées du Chantier 42.

## Périmètre

- Inclus : anomalies 6 à 17 et 19 à 23 ; une route serveur pour l'itinéraire d'une course.
- Exclu : la clé de cache des images (remarque de la section « Effet du Chantier 41 »), le reste du dossier `ios/`.

## Étapes

1. **Lecture des réponses (6)** — plus aucun transtypage direct dans `lib/data/` : 31 fichiers repris avec `readMap`, `readString`, `readInt`…, et deux lecteurs ajoutés, `requireMap` et `requireDataMap`, qui lèvent une `FormatException` quand l'objet attendu manque. `QueuedRequest.tryParse` remplace `fromJson` : une requête en file abîmée est écartée au lieu d'interrompre tout le rejeu.
2. **Sauvegarde Android (7)** — `allowBackup="false"`, `fullBackupContent="false"` et règles d'extraction (`res/xml/data_extraction_rules.xml`) : ni sauvegarde sur le compte Google, ni transfert d'un téléphone à l'autre.
3. **Lien profond et garde des écrans (8)** — le filtre `prosartisan://` n'accepte que l'hôte `payment-result` ; le routage automatique de Flutter d'après l'adresse reçue est coupé (`flutter_deeplinking_enabled`) ; `SessionGuard` renvoie à la connexion toute route non publique ouverte sans compte.
4. **Vue web de l'Assistant (9)** — la navigation reste sur le domaine de l'API.
5. **Signature (10)** — `tool/build_apk.dart` refuse de générer un APK sans `android/key.properties`, sauf `--debug-signing` pour un essai local.
6. **Itinéraire du livreur (11, 19)** — nouvelle route `GET /api/v1/orders/{order}/route` (`DeliveryTrackingService::driverRoute`) : le serveur rend le tracé et les deux extrémités d'une étape. Le planificateur de tournée ne contacte plus le serveur public OSRM et ne fabrique plus de position : sans destination connue, aucun tracé et aucun guidage.
7. **Cartes externes (20)** — `openInMaps` : lien neutre `geo:`, puis Yandex Maps ; les deux liens Google Maps disparaissent.
8. **Paquets (12)** — voir « Vérification ».
9. **Tests des modules sans test (12 bis)** — discussion de chantier, artisans, parrainage client, onglets, stock, écran de démarrage.
10. **Appels réseau dans un écran (13)** — l'estimation de course et la vérification du code promo passent par `OrderRepository` (`estimateDelivery`, `verifyPromoCode`).
11. **Messages techniques (14)** — `services_controller.dart` et `settings_controller.dart` n'affichent plus le texte d'une exception.
12. **Usage du fichier (15)** — `usage=mission` ou `usage=catalogue` envoyé à `POST /upload`.
13. **Permissions (16)** — `READ_EXTERNAL_STORAGE` limité à Android 12 et antérieurs.
14. **Code mort (17, 23)** — `debug_helper.dart`, `telegram_logger.dart`, `telegram_test_screen.dart`, `app_logger.dart` supprimés ; `ErrorHandler` n'envoie plus rien hors du téléphone.
15. **iOS (21)** — mention du micro ajoutée, mentions de l'appareil photo et des photos en français.
16. **Rôle (22)** — l'écran de connexion envoie `livreur`.

## Règles d'or concernées

- 28 : lecture défensive des réponses. 29 : aucune position inventée. 35 : textes en français. 36 : itinéraire réservé au livreur de la course. 44 : Yandex, jamais Google Maps. 63 : rôle `livreur`. 109 : destination figée sur la commande. 113 et 114 : services d'itinéraire, suivis côté serveur. 115 : outil de génération.

## Vérification

- Flutter : `flutter analyze` ne signale rien ; suite complète : 599 réussis, 4 ignorés. Nouveaux fichiers : `chantier43_guards_test.dart`, `driver_route_and_checkout_test.dart`, `untested_modules_test.dart`, `splash_screen_test.dart` (47 tests).
- Pest (SQLite) : 1 411 réussis, 2 ignorés ; `Chantier43DriverRouteTest.php` (7 tests). Non rejoué sur MariaDB : la route ajoutée n'écrit rien et ne porte aucune requête propre.
- Android : un APK de débogage se compile avec le nouveau manifeste, et le manifeste fusionné porte bien `allowBackup="false"`, les règles d'extraction, l'hôte `payment-result` et le routage par lien coupé.
- Non vérifié : rien n'a été exécuté sur un téléphone. À contrôler sur appareil : carte et guidage d'une course (tracé du serveur, adresse sans position), retour d'un paiement Wave par le lien `prosartisan://payment-result`, ouverture d'un écran après reconnexion, Assistant IA, parrainage (sélection d'un contact).
- iOS : `Info.plist` relu, aucune compilation iOS.

## Écarts

- **Anomalie 12 non traitée** : `flutter pub upgrade` n'a pas abouti, `pub.dev` étant injoignable depuis le poste le 07/10/2026. `pubspec.lock` est inchangé. À relancer, puis à vérifier par l'analyse, les tests et une compilation.
- **Anomalie 11 : plus loin que l'audit.** Plutôt que de retirer l'adresse du serveur public OSRM de l'application, l'itinéraire est demandé au serveur de ProsArtisan, qui est déjà surveillé (Règle d'or 114) et se règle sans republier l'application. Le serveur, lui, s'appuie toujours sur le serveur public OSRM tant qu'aucune instance dédiée n'est branchée (`OSRM_BASE_URL`, Règle d'or 113).
- **Anomalie 16 : `READ_CONTACTS` conservée.** Le parrainage relit le numéro du contact choisi dans le répertoire ; sans la permission, l'application se ferme. Elle reste à déclarer dans la fiche de confidentialité de Google Play.
- **Anomalie 8 : la garde renvoie à la connexion, pas à l'écran de démarrage**, pour ne jamais boucler si un téléphone portait un jeton sans identifiant de compte.
- **Anomalie 12 bis : la discussion de chantier est testée par son dépôt et son modèle**, pas par son contrôleur, qui dépend de l'enregistreur audio et du flux temps réel du téléphone.
- **Anomalie 6 : deux exceptions**, dans `mission_repository.dart` (chemin d'un fichier local que l'application écrit elle-même) ; les écrans (`lib/modules/`) n'ont pas été repris, hors litiges et récapitulatif de commande.
- **Version installée et nouvelle route** : une version de l'application antérieure n'appelle pas `GET /orders/{order}/route` et garde son comportement ; la nouvelle version a besoin du serveur déployé, sans quoi la carte se rabat sur une ligne droite annoncée comme estimation.
