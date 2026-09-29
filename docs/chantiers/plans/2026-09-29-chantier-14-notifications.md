# Plan — Chantier 14 : Notifications fiables et messages pilotés depuis le backoffice

| Champ | Valeur |
| --- | --- |
| Statut | en cours (lot A livré le 2026-09-29) |
| Créé le | 2026-09-29 |
| Mis à jour le | 2026-09-29 |
| Auteur | Claude Code |
| Analyses liées | — (diagnostic reporté ci-dessous, § « Constat ») |
| Commits | — |

## Objectif

Rendre les notifications (in-app, push OneSignal, SMS) **fiables** et **pilotables** :

1. corriger les pannes constatées, dont une fuite de notifications d'un compte vers le suivant sur un téléphone partagé ;
2. permettre aux administrateurs de **gérer les textes des messages push et SMS** depuis le backoffice (consulter, modifier, prévisualiser, tester, désactiver un canal, revenir au texte d'origine), sans nouvelle publication de l'application ni déploiement ;
3. permettre aux administrateurs de **créer des envois ponctuels** (campagnes push et/ou SMS vers un public ciblé), de les modifier tant qu'ils ne sont pas partis, de les programmer, de les annuler et de les supprimer ;
4. maîtriser le coût SMS, aujourd'hui subi (chaque notification de type `payment` part aussi en SMS).

## Constat (diagnostic du 29/09/2026)

### Pannes

| # | Problème | Emplacement | Effet |
| --- | --- | --- | --- |
| P1 | Service injecté en optionnel (`?NotificationService … = null`, résolu à `null`) **et** appel de `sendToUser()`, méthode inexistante sur `NotificationService` ; erreur avalée par un `catch` vide | `MissionChatController.php:19` et `:153` | Aucune notification de message de chantier n'est jamais envoyée |
| P2 | Le `type` n'est pas ajouté aux données du push | `NotificationService::send()` | Le tap sur une notification push n'ouvre aucun écran (le routage mobile lit `data['type']`) ; seule la liste in-app fonctionne |
| P3 | Aucun `OneSignal.logout()` à la déconnexion | `auth_repository.dart` (`logout`) | L'appareil continue de recevoir les notifications (paiements, litiges) du compte précédent |
| P4 | SMS systématique pour `payment`, `litige`, `fraud_alert`, `otp` ; `sendAdmin` envoie push **et** SMS à chaque administrateur | `NotificationService::send()` / `sendAdmin()` | ≈ 35 points d'émission `payment` déclenchent un SMS, y compris « Course terminée » |

### Fragilités

- Envois synchrones dans la requête HTTP (production en `QUEUE_CONNECTION=sync`) : jusqu'à 3 s par appel OneSignal, plus l'appel SMS, pour chaque destinataire.
- Double initialisation OneSignal sur mobile (`main.dart` et `NotificationService` avec un App ID écrit en dur).
- Préférence « notifications désactivées » purement locale : masque seulement l'affichage au premier plan.
- Onglets mobiles désalignés des types émis (`jalon`/`jcode` attendus, `validation`/`materials` émis) ; badge « non lues » calculé sur les 30 éléments chargés au lieu de `meta.unread` ; pas de pagination.
- `NotificationController` interroge la base directement (Règle d'or 49) ; `sendPush()` mort ; table jamais purgée ; aucun test Pest ni Flutter.
- Textes des 86 notifications écrits en dur dans 23 fichiers, sous 21 types grossiers (`payment` couvre des situations sans rapport) : impossible de corriger une formulation sans déploiement.

## Périmètre

- **Inclus** : backend (service, catalogue, modèles, campagnes, journal des envois), backoffice (onglet Notifications), mobile (déconnexion, routage, onglets, badge, préférences), OTP compris mais verrouillé (voir lot C).
- **Exclu** : WhatsApp comme canal de notification (le code OTP WhatsApp existant n'est pas touché) ; multilinguisme (français uniquement, Règle d'or 35) ; e-mail transactionnel.

## Principe de conception des messages éditables

**Le code décide *quand* un message part ; le backoffice décide *ce qu'il dit* et *par quels canaux*.**

- **Catalogue d'événements dans le code** (`app/Notifications/Catalog/NotificationCatalog.php`) : chaque situation reçoit une clé stable et précise (`commande.livree.client`, `commande.livree.livreur`, `devis.refuse.artisan`, `chat.nouveau_message`, `auth.otp`…) avec : libellé français, domaine (Commandes, Missions, Paiements, Litiges, Recrutement, KYC, Sécurité, Administration), rôle destinataire, **variables autorisées** (nom, description, exemple), variables **obligatoires**, textes par défaut (titre push, corps push, SMS), canaux par défaut, canaux **verrouillés**.
- **Surcharges en base** (`notification_templates`) : une ligne n'existe que si un administrateur a modifié l'événement. « Supprimer » un modèle = **revenir au texte d'origine** du code. On ne peut pas « créer » un modèle d'événement : un texte que le code ne déclenche jamais ne partirait jamais, ce serait trompeur. La création libre relève des **campagnes** (lot D).
- **Rendu** : remplacement simple de `{variable}` (aucune évaluation de code, aucun Blade). Une variable inconnue est refusée à l'enregistrement ; une variable manquante au moment de l'envoi fait retomber sur le texte d'origine avec une ligne de log — jamais de « {montant} » littéral envoyé à un client (Règle d'or 29).
- **Nouvel appel** : `NotificationService::notify(User $user, string $event, array $vars, array $data = [])`. L'ancien `send()` reste disponible pendant la migration des 86 points d'émission, puis disparaît.

## Étapes

### Lot A — Corrections urgentes (petit périmètre, à livrer d'abord)

1. **P1 chat** : injection obligatoire de `NotificationService`, appel via `send()` avec `type` `chat_message` ; plus de `catch` muet (log d'erreur).
2. **P2 type dans le push** : `NotificationService::send()` fusionne `type` (et l'identifiant de la notification en base) dans les données OneSignal.
3. **P3 déconnexion** : `OneSignal.logout()` dans `AuthRepository.logout()` ; `OneSignal.login()` uniquement après authentification réussie.
4. **Double initialisation** : une seule initialisation dans `main.dart` (App ID de `EnvConfig`), `NotificationService` mobile ne garde que les écouteurs.
5. Tests : Pest `MissionChatNotificationTest` (échoue avant correctif), Pest sur la présence du `type` dans la charge OneSignal (faux HTTP), Flutter sur l'appel de déconnexion OneSignal.

### Lot B — Fondations : catalogue, envoi différé, journal des envois

1. **Catalogue** `NotificationCatalog` (voir principe ci-dessus) : inventaire des 86 points d'émission, chacun rattaché à une clé d'événement précise et à ses variables.
2. **Table `notification_templates`** : `event_key` (unique), `push_title`, `push_body`, `sms_body` (nullable = texte d'origine), `channel_in_app`, `channel_push`, `channel_sms` (booléens nullable = défaut du catalogue), `updated_by`, horodatages. Migration idempotente (Règle d'or 55).
3. **`NotificationTemplateService`** : résolution (surcharge sinon défaut), rendu, validation (variables inconnues, obligatoires, longueur SMS), cache court invalidé à l'enregistrement.
4. **Envoi après la réponse** : l'enregistrement in-app reste immédiat ; push et SMS partent via `dispatchAfterResponse` (aucun worker requis, cf. `CLAUDE.md` § File d'attente).
5. **Journal des envois `notification_deliveries`** (append-only) : notification, canal, fournisseur, statut (`envoye`/`echoue`/`ignore` + motif : canal désactivé, OneSignal non configuré, préférence utilisateur), réponse fournisseur tronquée. Les échecs alimentent `/admin/observability` (Règle d'or 23).
6. **Migration des 86 points d'émission** vers `notify()`, domaine par domaine (un commit par domaine), en reprenant **mot pour mot** les textes actuels comme textes par défaut : aucun changement visible à ce stade.
7. **SMS par défaut** : fixés événement par événement dans le catalogue selon la décision n° 1 (§ « Décisions métier ») — plus de règle globale par type. `sendAdmin` cesse d'envoyer un SMS à chaque administrateur hors fraude et courses bloquées.

### Lot C — Backoffice : gestion des messages push et SMS

Sous l'onglet « Notifications & Alertes » existant, nouveaux sous-onglets **Modèles de messages**, **Campagnes** (lot D) et **Journal des envois**, à côté de « Alertes » et « Historique ».

1. **Liste des événements** groupés par domaine, recherche, filtre par rôle destinataire et par canal actif ; badge « Modifié » / « Texte d'origine ».
2. **Édition** (modale) : titre et corps push, texte SMS, interrupteurs de canaux (in-app, push, SMS) ; palette de variables cliquables avec description et exemple ; **compteur SMS** (caractères, encodage GSM-7 ou Unicode, nombre de segments : 160 / 70 caractères par segment, 153 / 67 au-delà).
3. **Aperçu** avec des valeurs d'exemple : rendu notification Android et rendu SMS.
4. **Envoi de test** à l'administrateur connecté (push et/ou SMS vers son propre numéro), avec limite de débit.
5. **Revenir au texte d'origine** (suppression de la surcharge) via `useConfirm()`.
6. **Événements verrouillés** : l'OTP (`auth.otp`) garde le SMS obligatoire, `{code}` obligatoire, route SMS `otp` (Règle d'or 39) ; les alertes de fraude et de sécurité ne peuvent pas perdre tous leurs canaux.
7. **Audit** avant/après de chaque modification, test et retour à l'origine (`notification_template.updated`, `.reset`, `.test_sent`, Règle d'or 17).
8. **Capacité** `admin.notifications.manage` (catalogue d'`AdminPermissionService`, routes `can:`, Règle d'or 16).

### Lot D — Backoffice : campagnes (création d'envois ponctuels)

1. **Table `notification_campaigns`** : titre, textes push et SMS, canaux, **nature** (`service` ou `promotionnel`), ciblage (rôles, communes, statut KYC, liste d'utilisateurs choisis), écran d'ouverture dans l'app (notifications, accueil, lien d'une communication existante), statut (`brouillon` → `programmee` → `en_cours` → `envoyee` / `annulee`), date programmée, auteur, compteurs (destinataires, envoyés, échecs).
2. **CRUD complet** : créer, modifier et supprimer tant que le statut est `brouillon` ou `programmee` ; dupliquer ; annuler une campagne programmée. Une campagne envoyée n'est ni modifiable ni supprimable (elle reste dans l'historique et le journal des envois).
3. **Avant l'envoi** : aperçu, envoi de test à soi-même, **nombre de destinataires** et **nombre de SMS** (destinataires × segments) calculés côté serveur, confirmation `useConfirm()` qui les affiche.
4. **Envoi** par lots paginés (`->lazy()`), déclenché par une commande planifiée chaque minute (`notifications:send-campaigns`, dans `routes/console.php`, garde `ScheduledCommandsTest`) — un envoi de masse ne tient pas dans une requête HTTP. Idempotence : un destinataire déjà servi n'est jamais relancé.
5. **Garde-fous** : plafond de destinataires par campagne (réglage), exclusion des comptes suspendus, anonymisés et supprimés ; les campagnes `promotionnel` sont **push uniquement** (décision n° 2) et ne visent que les utilisateurs ayant accepté les messages promotionnels (lot E) ; les SMS des campagnes `service` passent par la route SMS `plain`. Aucun coût affiché (décision n° 3).
6. **Capacité distincte** `admin.notifications.broadcast` : rédiger un modèle et envoyer un SMS à des milliers de personnes ne relèvent pas du même niveau de confiance. Tout est audité (`notification_campaign.created`, `.updated`, `.sent`, `.cancelled`, `.deleted`).
7. Mobile : type `campaign` routé vers l'écran ciblé ; affichage dans la liste in-app comme toute notification.
8. **Lien avec « Communications »** : les annonces in-app existantes restent dans leur onglet ; une campagne peut pointer vers une communication publiée (« En savoir plus »), sans dupliquer son contenu.

### Lot E — Confort utilisateur et dette

1. **Préférences serveur** par utilisateur (`notification_preferences`) : push et SMS par domaine ; les événements de sécurité et financiers critiques (OTP, fraude, libération de fonds) ne sont pas désactivables. Mobile : l'écran de réglages lit et écrit ces préférences au lieu d'un simple indicateur local ; accord « offres et nouveautés » (push promotionnel) explicite, désactivé par défaut.
2. Mobile : badge sur `meta.unread`, pagination de la liste, onglets alignés sur les domaines du catalogue (exposé par l'API) au lieu d'une liste de types écrite en dur.
3. `NotificationController` → requêtes déplacées dans `NotificationService` (Règle d'or 49).
4. Suppression de `sendPush()` ; purge planifiée (quotidienne, `routes/console.php`) des notifications lues depuis plus de 12 mois (décision n° 4).

## Règles d'or concernées

8 et 39 (SMS OTP, route `otp`), 16 (capacités fines), 17 (audit), 19 (listes paginées côté serveur), 23 (observabilité), 29 (jamais de texte ou de variable inventés), 35 (français), 36 (propriété des ressources, tests reproduisant la panne), 49 (couche service, injection obligatoire), 55 (migrations idempotentes), 47 (portabilité MariaDB/SQLite), 54 (PRD, `AGENTS.md`, `CLAUDE.md` à chaque commit), 77 (manuel d'utilisation mis à jour pour le backoffice et les réglages mobiles), 70 (statut de ce plan).

## Vérification

- **Pest** : correctifs du lot A (échec avant, succès après) ; rendu et validation des modèles (variable inconnue refusée, obligatoire manquante, repli sur texte d'origine) ; OTP verrouillé ; canaux désactivés → `ignore` au journal ; campagnes (ciblage, exclusions, idempotence, plafond, statuts, capacité `broadcast`) ; routes backoffice sous `can:` ; suite complète SQLite **et** MariaDB 11.8.
- **Vitest** : panneaux Modèles, Campagnes et Journal (`router` mocké, confirmations via `findByRole('dialog')`, compteur SMS).
- **Flutter** : déconnexion OneSignal, routage par type (y compris `campaign`, `chat_message`), badge, préférences (harnais `runControllerAction`).
- **Manuel** : parcours d'édition d'un message et de création d'une campagne sur un appareil réel (réception push, tap, SMS de test).

## Décisions métier (arbitrées le 29/09/2026)

1. **SMS par défaut** : SMS réservé à l'OTP, aux paiements **reçus** par l'utilisateur (libération d'étape, versement Mobile Money, remboursement, gains de course crédités), aux alertes de fraude visant l'utilisateur et à l'ouverture d'un litige ; push seul pour tout le reste. Administrateurs : push seul, SMS réservé aux alertes de fraude et aux courses bloquées (`delivery_stalled`, livreur en transit sans signal). Ces valeurs sont les défauts du catalogue ; le backoffice peut ensuite les ajuster événement par événement (hors canaux verrouillés).
2. **Campagnes promotionnelles : push uniquement.** Une campagne de nature `promotionnel` ne peut pas activer le canal SMS (refus côté serveur, interrupteur absent de l'interface). Le SMS de campagne reste possible pour les campagnes `service` (information de service : maintenance, changement de conditions…).
3. **Aucun coût affiché** : pas de prix unitaire du SMS dans les paramètres ; la confirmation d'envoi affiche le nombre de destinataires et le nombre de SMS (destinataires × segments), jamais un montant.
4. **Conservation** : les notifications **lues** sont purgées après **12 mois** ; les non lues ne sont jamais purgées.

## Écarts

### Lot A (29/09/2026)

- **Doublon de push KYC** (découvert en cours de lot) : `AdminService::reviewKyc` appelait OneSignal directement en plus de `NotificationService::send()` ; l'utilisateur recevait deux push aux textes différents. Appel direct et dépendance `OneSignalService` supprimés (`test_la_revue_kyc_n_envoie_qu_un_seul_push`).
- **Détachement OneSignal élargi** : outre la déconnexion, la suppression de compte (`SettingsController.deleteAccount`) et la session révoquée ou expirée (401 dans `ApiClient`) détachent l'appareil. Attachement et détachement passent par `PushIdentity` (`lib/core/services/push_identity.dart`), qui absorbe les erreurs du plugin ; plus aucun appel direct à `OneSignal.login` dans les contrôleurs.
- `NotificationService::sendPush()` (code mort) supprimé dès le lot A au lieu du lot E.
- Le push porte aussi `notification_id`, en vue du marquage comme lue au toucher (lot E).
- Manuel d'utilisation : section « Notifications » ajoutée (chapitre commun).
