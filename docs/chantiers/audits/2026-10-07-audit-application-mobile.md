# Audit — Application mobile (Flutter)

| Champ | Valeur |
| --- | --- |
| Réalisé le | 2026-10-07 |
| Auteur | Claude Code (Opus 5.5) |
| Plan vérifié | aucun — première revue du code de l'application |
| Commit audité | `da83928d` |

## Périmètre

**Vérifié**, sur `frontend_flutter/` (379 fichiers Dart, 82 000 lignes, 78 fichiers de test) :

- l'analyse statique (`flutter analyze`) et la suite de tests (`flutter test`), relancées localement ;
- la configuration Android : manifeste source et manifeste fusionné de la dernière compilation, `build.gradle.kts`, signature, permissions ;
- les secrets : fichiers suivis par git, valeurs injectées à la compilation, et leur présence réelle dans le dernier APK compilé (`prosartisan-1.0.2-build3.apk`), par recherche des valeurs dans les fichiers binaires, sans les afficher ;
- le stockage local : jeton, préférences, caches Hive, file d'attente hors connexion ;
- la session : connexion, déconnexion, session expirée (401), suppression de compte, rattachement de l'appareil aux notifications ;
- le réseau : adresse de l'API, journalisation des requêtes, flux temps réel ;
- la vue web de l'Assistant IA, les liens ouverts hors de l'application, le lien profond `prosartisan://` ;
- le respect des Règles d'or contrôlables par lecture : 26 (GPS borné), 28 (lecture des réponses de l'API), 29 (données fictives), 75 (caches), 79 (identité push) ;
- l'effet du Chantier 41 (fichiers privés, liens signés) sur les parcours de l'application.

**Non vérifié** :

- aucun appareil : rien n'a été exécuté sur un téléphone, ni en mode avion, ni avec un vrai SMS ;
- iOS : le projet est conçu pour Android en premier, le dossier `ios/` n'a pas été relu ;
- la logique métier écran par écran : cet audit porte sur la sécurité, la robustesse et la conformité aux règles, pas sur l'exactitude de chaque parcours ;
- l'APK inspecté date du 05/10/2026 : il précède les derniers commits ;
- les vulnérabilités connues des paquets Dart : `flutter pub outdated` dit seulement ce qui est en retard, pas ce qui est vulnérable.

## Résultats

| Point contrôlé | État | Détail |
| --- | --- | --- |
| Analyse statique | ✅ conforme | `flutter analyze` : aucun problème |
| Tests | ✅ conforme | 531 réussis, 4 ignorés |
| Secrets dans le dépôt | ✅ conforme | `env.json` et `key.properties` ignorés par git, aucun fichier de clé suivi |
| Secrets dans l'APK | ✅ conforme | seuls la clé MapKit (restreinte au nom de paquet) et l'identifiant OneSignal y figurent ; les clés Yandex du serveur et le jeton Telegram en sont absents |
| Jeton de session | ✅ conforme | `flutter_secure_storage`, jamais dans les préférences |
| Caches locaux | ✅ conforme | boîtes Hive chiffrées, clé gardée dans le stockage sécurisé |
| Trafic en clair | ✅ conforme | `usesCleartextTraffic="false"` ; les adresses `http://` de développement sont absentes de l'APK |
| Journal des requêtes | ✅ conforme | `PrettyDioLogger` seulement hors version de publication, absent de l'APK |
| Réduction et obscurcissement du code | ✅ conforme | `isMinifyEnabled`, `isShrinkResources` |
| Déconnexion volontaire | ✅ conforme | appareil détaché, préférences effacées, caches vidés |
| GPS borné (Règle d'or 26) | ✅ conforme | garde `geolocation_time_limit_test.dart` au vert |
| Données fictives (Règle d'or 29) | ✅ conforme | aucune relevée |
| Session expirée et suppression de compte | ❌ non conforme | anomalie 1 |
| File d'attente hors connexion | ❌ non conforme | anomalies 2 et 3 |
| Jeton dans les journaux | ❌ non conforme | anomalie 4 |
| Adresses de médias reçues du serveur | ❌ non conforme | anomalie 5 |
| Lecture des réponses de l'API (Règle d'or 28) | ⚠️ partiel | anomalie 6 |
| Sauvegarde Android | ⚠️ partiel | anomalie 7 |
| Lien profond et garde des écrans | ⚠️ partiel | anomalie 8 |
| Vue web de l'Assistant | ⚠️ partiel | anomalie 9 |
| Signature de l'APK de publication | ⚠️ partiel | anomalie 10 |
| Couverture de tests | ⚠️ partiel | anomalie 12 |

## Anomalies

### Élevées

**1. Une session expirée ou un compte supprimé laisse les données du compte sur le téléphone.**
- Sur une réponse 401, `ApiClient` (`_AuthInterceptor.onError`) efface le jeton et détache l'appareil des notifications, puis renvoie à la connexion. Il n'appelle ni `StorageService.clearAll()` ni `CacheStore.wipeAll()` : nom, téléphone, rôle, statut KYC et tous les caches (missions, étapes, adresses, bons matériels, commandes) restent en place.
- `SettingsController.deleteAccount` efface les préférences, pas les caches.
- Seule la déconnexion volontaire (`AuthRepository.logout`) fait le nettoyage complet, avec ce commentaire : « ne jamais exposer les données d'un compte au compte suivant sur le même appareil ».
- Les caches ne portent pas l'identifiant du compte. Sur un téléphone partagé — cas que la Règle d'or 79 prend déjà en compte — le compte qui se connecte ensuite peut se voir servir, en repli hors connexion, les données du compte précédent.

Correctif : une seule fonction de fin de session (jeton, préférences, caches, file d'attente, identité push), appelée par les trois voies.

**2. La file d'attente hors connexion n'appartient à aucun compte.**
`SyncService` enregistre la requête (méthode, adresse, corps) sans l'identifiant de l'utilisateur, la garde trois jours, et la rejoue avec le jeton du moment (l'en-tête `Authorization` est posé par l'intercepteur au rejeu). Elle n'est vidée ni à la déconnexion, ni sur un 401. Une action mise en file par un compte — validation d'un retrait ou d'une livraison, soumission d'une étape, envoi de photos d'étape — est donc rejouée sous le compte suivant.
Le serveur refusera le plus souvent (propriété vérifiée), mais le refus s'affiche alors au mauvais utilisateur, par le bandeau des actions non abouties, avec le libellé de l'action d'un autre. Et l'action du premier compte est perdue sans qu'il le sache.

Correctif : inscrire l'identifiant du compte sur chaque requête en file, ne rejouer que celles du compte connecté, vider la file à toute fin de session.

**3. La file d'attente garde en clair les codes de retrait et de réception.**
Les boîtes Hive de la file (`_queueBox`, `_failuresBox`) sont ouvertes sans chiffrement, alors que tous les caches le sont (`HiveCipherProvider`). Le corps d'une validation mise en file contient le code à quatre chiffres (`{'code': code}`), que la Règle d'or 38 tient pour secret.

Correctif : ouvrir ces deux boîtes avec le même chiffrement que les caches.

**4. Le jeton de session est écrit dans le journal du téléphone à chaque ouverture de l'Assistant IA.**
`IaAssistantScreen._initWebView` appelle `debugPrint('[IaAssistant] Chargement WebView → $assistantUrl …')`. Cette adresse porte le jeton Sanctum dans son fragment (`#token=…`). `debugPrint` écrit aussi dans une version de publication : rien dans `main.dart` ne le neutralise. Le jeton se lit donc par `adb logcat` et figure dans tout rapport de bogue du téléphone.
Dans la même veine, `error_handler.dart` écrit l'adresse et le corps de la réponse de chaque erreur réseau, et l'application compte 60 `debugPrint`.

Correctif : ne jamais journaliser l'adresse de l'Assistant ; neutraliser `debugPrint` en version de publication (`debugPrint = (String? message, {int? wrapWidth}) {};` sous `kReleaseMode`).

**5. L'application ouvre hors d'elle-même une adresse qu'un autre utilisateur a choisie.**
Côté serveur, `CreateMissionRequest` valide les photos d'une demande par `'photos.*' => ['string']` : n'importe quelle chaîne passe. Côté application, `openTrackingMedia` et `mission_info_card.dart` ouvrent dans une application externe toute adresse dont le chemin finit par une extension vidéo (`launchUrl(…, LaunchMode.externalApplication)`).
Un client peut donc joindre à sa demande `https://un-site.example/x.mp4` : l'artisan qui touche la « vidéo » est envoyé sur ce site. C'est une voie d'hameçonnage, et un contournement du filtre anti-contournement — l'adresse peut mener à une page portant un numéro ou à une messagerie.
Depuis le Chantier 41, une adresse *privée* n'entre que signée ; une adresse étrangère, elle, passe toujours telle quelle.

Correctif, côté serveur : n'accepter dans `photos` que des adresses de fichiers de la plateforme (fichier privé signé, ou ancienne adresse `/storage/` du domaine). Côté application : n'ouvrir que les adresses du domaine de l'API.

### Moyennes

**6. Réponses de l'API encore lues par transtypage direct (Règle d'or 28).**
74 occurrences de `res.data … as Map` ou `as List`, et 32 `json['x'] as String` dans les modèles et dépôts. Les plus touchés : `order_repository.dart` (11), `jcode_repository.dart` (7), `evaluation_repository.dart` (7), `devis_repository.dart` (6), `auth_repository.dart` (6), `supplier_cashout_model.dart` (9), `address_model.dart` (5). `AuthRepository.me()` enchaîne trois transtypages : une réponse inattendue fait échouer l'ouverture de session par une erreur de type. Les lecteurs partagés (`json_readers.dart`) sont déjà utilisés par 55 fichiers ; il reste à finir.

**7. Sauvegarde Android non désactivée.**
Le manifeste ne pose ni `android:allowBackup="false"` ni règles d'extraction : la valeur par défaut autorise la sauvegarde. Les préférences (nom, téléphone, rôle, en clair dans `get_storage`) et la file d'attente non chiffrée partent dans la sauvegarde du téléphone. Les caches chiffrés y partent aussi, mais leur clé ne se restaure pas, ce que `CacheStore` rattrape en recréant la boîte.

**8. Lien profond ouvert à toute adresse, écrans sans garde.**
Le filtre d'intention accepte tout lien `prosartisan://`, sans hôte ni chemin imposé, et rien dans `lib/` ne le traite : c'est le routage par défaut de Flutter qui le reçoit. Aucune route de `app_pages.dart` ne porte de garde (`middlewares`) : un lien venu d'une page web peut ouvrir directement un écran nommé, sans passer par l'écran de démarrage. Les appels à l'API restent protégés par le jeton ; l'effet est un écran en erreur, pas une fuite. À resserrer : hôte imposé, et garde de session sur les routes.

**9. Vue web de l'Assistant sans restriction de navigation.**
JavaScript est activé sans restriction, le jeton est remis à la page, et le `NavigationDelegate` ne limite pas la navigation au domaine de l'API. Une page de l'Assistant qui contiendrait un lien externe l'ouvrirait dans cette même vue. Le contenu vient du backoffice (fiches validées) : le risque est faible, la restriction coûte une ligne.

**10. Un APK de publication peut être signé avec la clé de débogage.**
Sans `android/key.properties`, `build.gradle.kts` signe la version de publication avec la clé de débogage. L'outil de la Règle d'or 115 ne refuse ce cas que pour le bundle Google Play, pas pour l'APK. Un APK ainsi signé, distribué hors magasin, ne pourra pas être mis à jour par une version signée correctement.

**11. Serveur d'itinéraire de démonstration compilé dans l'application.**
`router.project-osrm.org` figure dans l'APK (planificateur de tournée du livreur). Déjà relevé par la Règle d'or 113 côté serveur : sans garantie de service.

### Faibles

**12. Paquets en retard** : 52 dépendances ont une version plus récente compatible (`flutter pub upgrade`), 10 demandent de relever une contrainte de `pubspec.yaml`. Trois paquets indirects, tous des outils de génération de code, sont abandonnés par leurs auteurs (`build_resolvers`, `build_runner_core`, `js`) : aucun n'est embarqué dans l'application.

**12 bis. Modules sans test** : `artisans`, `chat`, `clients`, `main_tab`, `onboarding`, `stock`. La discussion de chantier, touchée par les Chantiers 37 et 41, n'a aucun test côté application.

**13. Appels réseau dans un écran** : `order_checkout_screen.dart` (1 217 lignes) appelle l'API et lit les réponses lui-même, hors de la couche des dépôts.

**14. Messages techniques affichés** : `services_controller.dart` affiche « Échec du chargement des services : $e » ; `settings_controller.dart` affiche `e.toString()` (quatre endroits).

**15. Usage du fichier non annoncé** : l'application n'envoie pas `usage` à `POST /upload`. Le serveur se fie au rôle (Chantier 41) ; une prochaine version devrait l'envoyer.

**16. Permissions à revoir** : `READ_EXTERNAL_STORAGE` et `WRITE_EXTERNAL_STORAGE` ne servent plus à partir d'Android 13 avec le sélecteur de photos ; `READ_CONTACTS` ne sert qu'aux écrans de parrainage et pèse dans la fiche de confidentialité de Google Play.

**17. `debug_helper.dart`** affiche les vingt premiers caractères du jeton. Il n'est appelé nulle part (deux lignes commentées) : à supprimer.

## Effet du Chantier 41 sur l'application

- Les photos d'étape soumises depuis la file d'attente restent acceptées, même rejouées après l'expiration du lien : leur adresse est déjà enregistrée sur l'étape.
- Une demande de mission dont les photos ont été envoyées plus de deux heures avant la validation perd ces photos, sans message. Le cas est rare (l'envoi et la validation se suivent), mais muet.
- Les images mises en cache par l'application le sont par adresse : un lien signé changeant à chaque réponse, une même photo est retéléchargée à chaque rechargement de liste. À corriger par une clé de cache tirée du chemin, sans la signature (`cacheKey`), utilisée aujourd'hui à deux endroits seulement.

## Seconde passe (07/10/2026)

Relecture du même commit. Les anomalies 1 à 5 ont été recontrôlées dans le code et se confirment ; `flutter analyze` et `flutter test` ont été relancés (aucun problème ; 531 réussis, 4 ignorés). La passe a porté sur ce que la première n'avait pas lu : les replis des dépôts, le planificateur de tournée du livreur, le dossier `ios/`. Elle ajoute six constats.

### Seconde passe — élevée

**18. Une panne réseau s'affiche au livreur comme « aucune course » (Règles d'or 29 et 75).**
Dans `order_repository.dart`, `getAvailableDeliveries`, `getDeliveryBatches`, `getActiveDeliveryTour` et `getMyOrders` attrapent toute erreur (`catch (_) {}`) et renvoient une liste vide ou `null`. `HomeController._loadDriverMissions` vide alors la liste des courses disponibles (`driverAvailableMissions.clear()`). Un livreur sans réseau, ou dont la session a expiré, voit donc un écran sans course ni tournée, sans message ni « Réessayer ». `getOrderTracking` (suivi de livraison du client) et les trois lectures d'`evaluation_repository.dart` (`getMissionActors`, `getOrderActors`, `getMyEvaluations`) font de même.
La première passe notait la Règle d'or 29 conforme : elle n'avait cherché que les données fictives, pas les pannes rendues comme une absence.

Correctif : laisser l'erreur remonter, et l'annoncer dans le contrôleur. Seul l'envoi de la position du livreur peut rester silencieux.

### Seconde passe — moyennes

**19. Le planificateur de tournée place la boutique et le client à des points tirés au hasard.**
Quand la course ne porte pas les coordonnées du fournisseur ou du client, `delivery_route_planner_screen.dart` (`_initCoordinates`) les fabrique par `Random(mission.id)` autour de la position du livreur. L'écran affiche bien « Positions approximatives », mais ce même message invite à « utiliser le bouton GPS », et ce bouton (`_launchExternalNavigation`) guide vers ces points inventés. Depuis la Règle d'or 109, le serveur connaît la destination d'une livraison : sans coordonnées, l'écran doit le dire et ne proposer aucun guidage.

**20. Liens vers Google Maps (Règle d'or 44).**
`delivery_route_planner_screen.dart` (guidage du livreur) et `counterparty_card.dart` (position du client vue par l'artisan) ouvrent `https://www.google.com/maps/…`. Ce ne sont pas des appels d'API, mais la règle écarte toute intégration Google Maps. À remplacer par un lien Yandex Maps, ou par le lien neutre `geo:`, qui laisse le téléphone choisir.

**21. iOS : l'enregistrement d'une note vocale ferme l'application.**
`ios/Runner/Info.plist` ne déclare pas `NSMicrophoneUsageDescription`, alors que le paquet `record` sert à la dictée de devis et à la candidature vocale : iOS arrête l'application au premier accès au micro. Les mentions de l'appareil photo et de la photothèque sont en anglais et ne parlent que de « photos de profil » (Règle d'or 35). Sans effet tant que seule la version Android est publiée.

### Seconde passe — faibles

**22. Rôle `driver` encore envoyé par l'écran de connexion.**
`login_screen.dart` pose `role = 'driver'` pour le profil Livreur, et une dizaine d'écrans testent les deux valeurs (`'driver' || 'livreur'`). Le serveur ramène `driver` à `livreur` (Règles d'or 63 et 98) ; le rôle a été supprimé du reste du code (Règle d'or 106). À aligner sur `livreur`, en gardant la lecture tolérante.

**23. Journal d'erreurs vers Telegram prévu dans l'application.**
`telegram_logger.dart` enverrait les erreurs réseau à Telegram si un jeton de bot était fourni à la compilation (`TELEGRAM_BOT_TOKEN`). Le jeton est absent de l'APK inspecté, donc rien ne part. Mais le mécanisme ne peut fonctionner qu'en embarquant ce jeton dans l'application, où il se lit. À retirer, avec `telegram_test_screen.dart`, que rien n'appelle ; un journal d'erreurs passe par le serveur.

### Contrôlé sans constat

- Minuteries : dans chaque fichier qui en crée, on compte au moins autant d'arrêts (`cancel()`) que de créations. C'est un décompte, pas une lecture de chaque cycle de vie.
- Montants : aucun champ de montant déclaré en `double` dans `lib/data/models/`.

## Suites

1. **Chantier 42 — livré le 07/10/2026** : anomalies 1, 2, 3, 4, 5 (serveur et application) et 18. Plan et écarts : `../plans/2026-10-07-chantier-42-fin-de-session-et-file-hors-connexion.md`. La file hors connexion n'est pas vidée à la fin de session comme le proposait l'anomalie 2 : elle est rattachée à son auteur, pour ne pas perdre une livraison validée hors connexion.
2. **Chantier 43 — livré le 07/10/2026** : anomalies 6 à 11, 12 bis, 13 à 17 et 19 à 23. Plan et écarts : `../plans/2026-10-07-chantier-43-suites-audit-application-mobile.md`.
   **Restent ouvertes** : l'anomalie 12 (paquets en retard — `pub.dev` était injoignable depuis le poste le 07/10/2026) et la clé de cache des images.
3. Les corrections de l'application demandent une nouvelle version publiée (`dart run tool/build_apk.dart`) ; le volet serveur de l'anomalie 5 protège dès son déploiement les versions installées.
4. À compléter : contrôle sur appareil, revue du reste du dossier `ios/`.
