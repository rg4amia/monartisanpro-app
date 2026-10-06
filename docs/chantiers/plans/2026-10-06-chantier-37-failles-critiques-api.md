# Plan — Chantier 37 : routes SMS, codes promo et fichiers de la messagerie de chantier

| Champ | Valeur |
| --- | --- |
| Statut | livré (contrôle sur le serveur à faire ; note vocale à vérifier sur appareil) |
| Créé le | 2026-10-06 |
| Mis à jour le | 2026-10-06 |
| Auteur | Claude Code (Opus 5.5) |
| Analyses liées | `../audits/2026-10-06-audit-securite-api-et-deploiement.md` (constats 2, 3 et 4) |
| Commits | — |

## Objectif

Fermer les trois failles critiques de l'audit du 06/10/2026 qui restaient ouvertes à tout compte connecté :

- envoyer un SMS au texte libre sous le nom de ProsArtisan, et lire les SMS envoyés (codes compris) ;
- créer, modifier ou supprimer un code promo ;
- publier n'importe quel fichier, sous l'extension de son choix, par la messagerie de chantier.

## Périmètre

- Inclus : `routes/api.php`, `SmsController`, `PromoCodeController`, `MissionChatController`, `public/.htaccess`.
- Exclu : les autres constats de l'audit (5 à 19), les fichiers déjà présents sur le serveur, l'application mobile (aucun changement nécessaire).

## Étapes

1. **Routes SMS** — `POST /api/v1/sms/send`, `GET /api/v1/sms` et `GET /api/v1/sms/{uid}` sont supprimées avec `SmsController`. Rien ne les appelait : ni l'application mobile, ni le backoffice, ni la vitrine. L'envoi de SMS passe par `NotificationService` et, pour l'administrateur, par l'onglet « Messages push & SMS ». La route `POST /api/v1/sms/incoming` de la passerelle n'est pas touchée.
2. **Codes promo** — la ressource `promo-codes` de l'API et sa route `toggle` sont supprimées, avec les méthodes correspondantes de `PromoCodeController`, qui ne garde que `verify`. La gestion reste dans le backoffice (`/admin/promo-codes`, capacité `admin.promo.manage`). L'application mobile n'appelle que `POST /promo-codes/verify`.
3. **Messagerie de chantier** — `MissionChatController::acceptedMedia` n'admet que des photos (JPEG, PNG, WEBP, HEIC) et des notes vocales (M4A, AAC, MP3, WAV, OGG), d'après le type détecté dans le contenu. L'extension d'enregistrement vient de ce type, jamais du nom transmis. Une note vocale au contenu non reconnu est admise sur son extension, prise dans la liste fermée des formats audio. Tout autre fichier est refusé (422, message en français), sans rien enregistrer.
4. **Aucun script sous `/storage`** — `public/.htaccess` refuse (403) toute adresse de `/storage` finissant par une extension de script (`php`, `phtml`, `phar`…). La règle couvre aussi les fichiers déjà déposés.

## Règles d'or concernées

- 36 : une route d'administration de l'API porte la même capacité que son équivalent du backoffice — ou n'existe pas.
- 35 : message de refus en français.
- 49 : la validation du fichier reste dans le contrôleur, comme le reste de l'envoi d'un message ; le déplacement de la messagerie dans un service est hors périmètre.

## Vérification

- `Chantier37CriticalApiFixesTest.php` (8 tests) : 6 échouaient avant correctif — un client créait un code promo, atteignait l'envoi de SMS, et un fichier `.php` était enregistré sous `chat/{mission}/chat_….php`.
- Suites voisines relancées : `NotificationDeliveryTest`, `BackofficeCrudTest`, `OfflineValidationTest`, `OrderPaymentCollectionTest`.
- **Sur le serveur, après déploiement** :
  - `curl -I https://prosartisan.net/storage/essai.php` répond 403 ;
  - chercher les fichiers qui ne sont ni des photos ni des notes vocales dans `storage/app/public/chat/` et les examiner avant de les supprimer : `find storage/app/public/chat -type f ! -iname '*.jpg' ! -iname '*.jpeg' ! -iname '*.png' ! -iname '*.webp' ! -iname '*.heic' ! -iname '*.m4a' ! -iname '*.aac' ! -iname '*.mp3' ! -iname '*.wav' ! -iname '*.ogg'`.
- **Sur appareil** : envoyer une photo et une note vocale dans une discussion de chantier.

## Écarts

- Les fichiers déjà déposés sur le serveur ne sont ni listés ni supprimés par ce chantier : seul un contrôle sur le serveur dit s'il y en a.
- Une image GIF ou BMP choisie dans la galerie est désormais refusée.
- La photo d'un message n'est toujours pas analysée par le filtre anti-contournement, qui ne lit que le texte (constat 2 de l'audit, second volet) : à traiter à part.
