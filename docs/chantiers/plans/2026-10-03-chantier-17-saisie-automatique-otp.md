# Plan — Chantier 17 : saisie automatique du code OTP reçu par SMS

| Champ | Valeur |
| --- | --- |
| Statut | livré (option A ; contrôle sur appareil réel à faire) |
| Créé le | 2026-10-03 |
| Mis à jour le | 2026-10-03 |
| Auteur | Claude (à la demande d'Inza Bamba) |
| Analyses liées | — |
| Commits | — |

## Objectif

Sur l'écran « Vérification OTP » de l'application mobile, les 4 cases se remplissent seules à la réception du SMS. L'utilisateur n'a plus à quitter l'application ni à recopier le code : il appuie lui-même sur « Vérifier le code ». La vérification n'est jamais lancée automatiquement.

## État des lieux

- Écran : `frontend_flutter/lib/modules/auth/views/otp_verification_screen.dart` — 4 champs d'un caractère, `AuthController.otp`, bouton actif à 4 chiffres. Aucune lecture du SMS, et un code collé est tronqué à son premier chiffre (`LengthLimitingTextInputFormatter(1)`).
- SMS : événement `auth.otp` du catalogue, texte modifiable depuis le backoffice, expéditeur alphanumérique « ProsArtisan », envoyé par SMS Pro Africa ou Orange (Règles d'or 39, 78, 81).
- Aucun paquet de lecture de SMS dans `pubspec.yaml`, aucune permission SMS dans le manifeste. `minSdk = 26`.

## Décision à valider : le mode de lecture du SMS

Les deux modes passent par Google Play Services et **n'exigent aucune permission `READ_SMS` / `RECEIVE_SMS`** (Google Play refuse ces permissions pour cet usage).

| | Option A — SMS User Consent (recommandée) | Option B — SMS Retriever |
| --- | --- | --- |
| Geste de l'utilisateur | Un appui sur « Autoriser » dans la fenêtre système qui affiche le SMS reçu | Aucun |
| Texte du SMS | Inchangé | Doit se terminer par une empreinte de 11 caractères propre à l'application |
| Backend | Aucun changement | `send-otp` reçoit l'empreinte, la valide et l'ajoute au SMS ; le catalogue et l'éditeur du backoffice doivent la préserver |
| Fragilité | Faible | L'empreinte dépend de la clé de signature : elle diffère entre debug, release et la clé Google Play. Une empreinte erronée désactive le remplissage sans aucun message |
| Autres canaux | Sans effet | L'empreinte apparaîtrait aussi dans les OTP WhatsApp et ceux de l'espace fournisseur du site, sauf traitement séparé |

Recommandation : **option A**. Elle se livre sans toucher au backend ni au texte du SMS, et ne peut pas tomber en panne à un changement de clé de signature. L'option B reste possible plus tard, dans un lot séparé, si l'appui sur « Autoriser » gêne à l'usage.

## Périmètre

- Inclus : écran de vérification OTP de la connexion et de l'inscription, Android ; suggestion du code par le clavier (iOS et Android) ; collage d'un code complet.
- Exclu : vérification automatique sans appui sur le bouton ; autres saisies de code (OTP d'étape de chantier, changement de numéro, espace fournisseur du site vitrine) — le composant sera réutilisable, leur raccordement fera l'objet d'un lot distinct ; option B.

## Étapes

1. **Dépendance** — ajouter `smart_auth` à `pubspec.yaml` (version et compatibilité Flutter à vérifier à l'ajout). Aucune permission ajoutée au manifeste.
2. **Extraction du code** — `lib/core/utils/otp_code_extractor.dart` : fonction pure `extractOtpCode(String sms, {int length = 4})` qui renvoie la première suite d'exactement 4 chiffres, non accolée à d'autres chiffres, sinon `null`. Le texte du SMS étant modifiable au backoffice, l'extraction ne dépend d'aucune formulation.
3. **Écoute du SMS** — `lib/core/services/otp_sms_listener.dart` : interface `OtpSmsListener` (`Future<String?> waitForCode()`, `Future<void> cancel()`) et implémentation `SmartAuthOtpSmsListener` (User Consent API). Tout échec (Play Services absent, délai de 5 minutes dépassé, refus de l'utilisateur, plateforme non Android) renvoie `null` : la saisie manuelle reste disponible, sans message d'erreur. Enregistrement dans le binding du module `auth`.
4. **Écran OTP** — dans `otp_verification_screen.dart` :
   - démarrer l'écoute à l'ouverture, la relancer après « Renvoyer le code », l'arrêter dans `dispose` et au retour arrière ;
   - à la réception, remplir les 4 cases, mettre à jour `AuthController.otp`, retirer le focus, vibration légère et mention « Code rempli automatiquement. Appuyez sur Vérifier le code. » ; **ne jamais appeler `verifyOtp`** ;
   - ignorer un code reçu pendant une vérification en cours ;
   - accepter le collage d'un code complet et la suggestion du clavier : `AutofillGroup` et `AutofillHints.oneTimeCode`, répartition des 4 chiffres dans les cases quand un champ en reçoit plusieurs.
5. **Documentation** — nouvelle Règle d'or 86 dans `CLAUDE.md` et `AGENTS.md`, entrée du `PRD.md`, manuel d'utilisation (chapitres des 4 espaces mobiles : connexion), statut du plan et `CHRONOLOGIE.md`, dans le même commit que le code (Règles d'or 54, 70, 77).

## Règles d'or concernées

- 39 : le code reste comparé côté serveur, la voie d'envoi `otp` est inchangée.
- 58 et 59 : le défi anti-robot précède toujours l'envoi du SMS ; rien n'est contourné.
- 29 : un remplissage impossible ne s'annonce par aucune erreur inventée, la saisie manuelle reste telle quelle.
- 35 : libellés en français.
- 81 : le texte `auth.otp` reste librement modifiable (option A).
- 54, 70, 77 : documentation dans le même commit.

## Vérification

- `test/core/otp_code_extractor_test.dart` : texte d'origine du catalogue, texte modifié, code en début ou fin de message, suite de 6 chiffres ignorée, « 5 minutes » jamais pris pour un code, absence de code.
- `test/modules/auth/otp_verification_screen_test.dart`, avec un faux `OtpSmsListener` : les 4 cases se remplissent et le bouton s'active ; `verifyOtp` n'est pas appelé avant l'appui ; un écouteur qui renvoie `null` laisse l'écran intact ; « Renvoyer le code » vide les cases et relance l'écoute ; collage de « 1234 » réparti dans les 4 cases ; l'écoute est annulée à la fermeture.
- `flutter analyze` et `flutter test`.
- Contrôle manuel sur un appareil Android réel (APK release avec `--dart-define-from-file=env.json`), SMS réel de chaque passerelle (SMS Pro Africa, Orange) : fenêtre « Autoriser », cases remplies, bouton à appuyer ; refus de la fenêtre ; code renvoyé ; appareil sans Play Services si disponible.

## Points d'attention

- L'API User Consent ignore un SMS dont l'expéditeur figure dans les contacts du téléphone : un utilisateur ayant enregistré « ProsArtisan » comme contact saisira le code à la main.
- Le comportement ne se vérifie pas sur émulateur sans Play Services ni par les tests automatisés : le contrôle manuel sur appareil est indispensable avant livraison.

## Écarts

- **Option retenue** : A (SMS User Consent), validée le 03/10/2026.
- **Écoute portée par `AuthController`, pas par l'écran** : le SMS est envoyé depuis l'écran de connexion, avant l'ouverture de l'écran OTP. Une écoute démarrée à l'ouverture de l'écran aurait manqué un SMS déjà arrivé. `sendOtp` la lance donc avant l'appel réseau, et l'écran reporte le code reçu (`receivedSmsCode`), y compris s'il est arrivé avant son ouverture. « Renvoyer le code » relance l'écoute par le même chemin.
- **Pas d'enregistrement dans le binding** : l'écouteur est un paramètre du constructeur d'`AuthController` (implémentation `smart_auth` par défaut, faux écouteur dans les tests).
- **Code reçu pendant une vérification en cours** : il est reporté dans les cases au lieu d'être ignoré ; l'ignorer l'aurait perdu sans possibilité de le relire.
- **Collage** : les cases acceptent jusqu'à 5 caractères pour qu'un code collé dans une case déjà remplie soit lu en entier (les 4 derniers chiffres font foi).
- **Contrôle manuel sur appareil réel** : non effectué dans cette session. À faire avant mise en production, avec un SMS réel de chaque passerelle (SMS Pro Africa, Orange).
