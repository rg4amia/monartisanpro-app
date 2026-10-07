# Plan — Chantier 44 : renvoi du code de connexion, envoi refusé annoncé

| Champ | Valeur |
| --- | --- |
| Statut | livré (nouvelle version de l'application à publier ; contrôle sur appareil à faire) |
| Créé le | 2026-10-07 |
| Mis à jour le | 2026-10-07 |
| Auteur | Claude Code (Opus 5.5) |
| Analyses liées | — (signalement du 07/10/2026 : capture de l'écran « Vérification OTP ») |
| Commits | `0869e068` |

## Objectif

Un utilisateur n'a pas reçu son code par SMS ; « Renvoyer le code » a affiché « Ce défi de sécurité a déjà été validé ou rejoué. ». Deux défauts distincts : le renvoi ne pouvait jamais aboutir, et le serveur annonçait « code envoyé » même quand le fournisseur de SMS avait refusé l'envoi.

## Périmètre

- Inclus : application mobile (renvoi du code), serveur (`POST /auth/send-otp`).
- Exclu : la cause du SMS non reçu ce jour-là, qui se lit dans les journaux de production ; les autres envois de code (étape de chantier, changement de numéro, numéro de paiement).

## Étapes

1. **Défi abandonné après usage** — `AuthController._discardChallenge` (application) remet à zéro le jeton, la question et la réponse après tout envoi, réussi ou non. Le serveur n'accepte un défi qu'une fois (`AntiBotService::check`, nonce réservé par `Cache::add`).
2. **Renvoi avec un défi neuf** — `OtpVerificationScreen._handleResend` charge un défi, pose le calcul, puis renvoie. Sans défi chargé : « Impossible de préparer le renvoi du code. Vérifiez votre connexion. ».
3. **Dialogue partagé** — `showSecurityChallengeDialog` (`lib/modules/auth/widgets/security_challenge_dialog.dart`), extrait de l'écran de connexion.
4. **Envoi refusé annoncé** — `OtpService::sendOtpOrFail` lève `OtpDeliveryException` quand aucun canal n'a accepté le message ; `AuthController::sendOtp` répond 503, `error_code: OTP_DELIVERY_FAILED`, message en français. Le code émis est brûlé.
5. **Réponse sans statut** — la lecture du statut d'un fournisseur tolère son absence ; elle provoquait une erreur 500.

## Règles d'or concernées

- 29 : une panne n'est pas annoncée comme un succès. 35 : message en français, sans le motif brut du fournisseur. 58 et 108 : défi anti-robot à usage unique. 78 : deux fournisseurs de SMS. 86 : l'écoute du SMS démarre avant chaque envoi.

## Vérification

- Flutter : `flutter analyze` ne signale rien ; suite complète : 601 réussis, 4 ignorés, dont deux tests ajoutés à `auth_controller_test.dart`.
- Pest : `OtpDeliveryFailureTest.php` (6 tests) ; suite complète rejouée sur SQLite.
- Non vérifié : rien n'a été exécuté sur un téléphone ; aucun SMS réel n'a été envoyé. Les journaux de production n'ont pas été lus.

## Écarts

- **Cause du SMS non reçu non établie.** À lire en production : `[OTP] Failed to send SMS` dans `storage/logs/laravel.log` à l'heure de l'essai, et `php artisan sms:switch-provider --status`.
- **Refus explicite seulement** : un canal n'est tenu pour défaillant que sur `status: error`. La forme exacte des réponses de production n'ayant pas été relevée, une réponse inattendue laisse passer plutôt que de fermer la connexion à tous.
- **Limite** : un SMS accepté par le fournisseur puis retardé ou perdu par l'opérateur reste annoncé « envoyé ».
- **Versions installées** : elles affichent le message du serveur sur un envoi refusé, mais leur bouton « Renvoyer le code » reste refusé jusqu'à la mise à jour ; revenir à l'écran de connexion et ressaisir le numéro fonctionne.
