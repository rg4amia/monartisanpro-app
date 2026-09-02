# Bugfix Requirements Document

> **Statut (2 septembre 2026)** : les 7 incohérences décrites ici ont été traitées. Ce document
> est conservé comme référence d'analyse. Le vocabulaire a été aligné sur la marque actuelle
> (« Score N'Zassa » → « Score ProsArtisan », colonne `score_prosartisan`) et sur l'échelle
> 0–1000 (seuil micro-crédit = 700, lu depuis `config('prosartisan.score_prosartisan.*')`).
> Se référer à `PRD.md` et `CLAUDE.md` pour les règles en vigueur.

## Introduction

ProsArtisan présente sept incohérences critiques entre le frontend Flutter et le backend Laravel 11 qui bloquent ou dégradent les flux métier principaux. Ces bugs couvrent : (1) un désalignement de signature dans le flux acceptation devis / fragmentation du séquestre, (2) des références à un rôle `driver` et un domaine `orders` inexistants, (3) un scan J-Code qui ne supporte pas le format `PA-XXXX` affiché à l'utilisateur, (4) un désalignement de la structure JSON du Score ProsArtisan entre Flutter et le backend, (5) un écran micro-crédit non branché sur l'API réelle, (6) une notation qui n'envoie pas les 4 sous-scores ProsArtisan, et (7) une validation géographique du référent non implémentée.

---

## Bug Analysis

### Current Behavior (Defect)

**Bug 1 — Flux acceptation devis / fragmentation séquestre incohérent**

1.1 WHEN le client accepte un devis via `POST /devis/{id}/accept`, THEN le système échoue silencieusement car `DevisService::accept()` appelle `WalletService::fragmentEscrow()` avec 3 arguments (`$mission`, `$montantTotal`, `$ratioMat`) alors que la signature réelle en exige 6 (`$mission`, `$client`, `$artisan`, `$montantTotal`, `$ratioMat`, `$paiementTransaction`).

1.2 WHEN Flutter appelle `POST /devis/{id}/accept` via `DevisRepository::acceptDevis()`, THEN le système ne déclenche aucun paiement préalable car Flutter appelle directement l'endpoint d'acceptation sans passer par `POST /payments/initiate` pour créer la `Transaction` requise par `fragmentEscrow()`.

1.3 WHEN `fragmentEscrow()` est appelé sans `$client` ni `$paiementTransaction`, THEN le système ne peut pas créditer les wallets de l'artisan ni enregistrer la transaction de référence, laissant la mission dans un état `financee` sans séquestre réel.

**Bug 2 — Rôle "driver" et domaine "orders" inexistants**

1.4 WHEN un import de données (ex : `gemini-V18032026-1438.txt`) référence le rôle `driver` ou des entités `orders` avec statuts `delivery`/`pickup`, THEN le système rejette ou ignore ces données car la table `users` n'accepte que les rôles `client`, `artisan`, `fournisseur`, `referent`, `admin` et aucune table `orders` n'existe en base.

1.5 WHEN un utilisateur tente de s'inscrire ou de se connecter avec le rôle `driver`, THEN le système retourne une erreur de validation car `login_screen.dart` et `register_screen.dart` n'exposent que `client`, `artisan`, `fournisseur` et le backend rejette tout autre valeur d'enum.

**Bug 3 — Scan J-Code : format PA-XXXX non supporté**

1.6 WHEN le fournisseur scanne un QR Code contenant la valeur `PA-XXXX` (format affiché dans l'UI de `scanner_screen.dart`), THEN le système ignore le scan car `onDetect` tente `int.tryParse(raw)` et retourne `null` pour toute valeur non entière, sans jamais appeler `_doScan()`.

1.7 WHEN le fournisseur saisit manuellement un code au format `PA-XXXX` dans le champ de saisie, THEN le système affiche "Code invalide" car la validation ne fait que `int.tryParse(code)` et le TODO `// Lookup by code string` n'est pas implémenté.

1.8 WHEN le backend reçoit `POST /jcodes/{jcode}/scan` via route model binding sur `JCode $jcode`, THEN le binding attend un identifiant entier (ID de base de données) alors que le QR Code et l'USSD exposent le code alphanumérique `PA-XXXX`, rendant la résolution impossible sans lookup préalable.

**Bug 4 — Score ProsArtisan : structure JSON désalignée**

1.9 WHEN Flutter charge le score via `ScoreController::loadScore()`, THEN le système retourne `0` pour tous les sous-scores car le contrôleur lit `data['scoreProsArtisan']`, `data['fiabilite']`, `data['integrite']`, `data['qualite']`, `data['reactivite']` en top-level alors que `ScoreService::getScoreDetail()` retourne `score_prosartisan` (snake_case) et les sous-scores dans un objet imbriqué `breakdown`.

1.10 WHEN Flutter évalue l'éligibilité micro-crédit en repli via `score.value >= kMicroCreditScoreThreshold`, THEN le système DOIT utiliser exactement la même valeur que le backend (`config('prosartisan.score_prosartisan.credit_threshold')` = 700, échelle 0–1000) — l'ancien seuil en dur `70` (échelle 0–100) est obsolète.

**Bug 5 — Écran micro-crédit non branché**

1.11 WHEN un artisan éligible soumet une demande de micro-crédit depuis `MicroCreditScreen`, THEN le système simule un délai de 2 secondes (`Future.delayed`) sans jamais appeler l'API car `MicroCreditController::apply()` ne fait aucun appel HTTP et `MicroCreditService` n'est exposé par aucun contrôleur HTTP ni aucune route dans `api.php`.

1.12 WHEN `MicroCreditScreen` s'affiche, THEN le plafond affiché est toujours `150 000 FCFA` (valeur mockée en dur) indépendamment du score réel de l'artisan, car aucun appel à `GET /artisans/{id}/score` ou à un endpoint dédié n'est effectué.

**Bug 6 — Notation : sous-scores ProsArtisan non envoyés**

1.13 WHEN un client soumet une évaluation via `RatingController::submit()`, THEN le système enregistre uniquement `note` et `commentaire` sans les 4 sous-scores `fiabilite`, `integrite`, `qualite`, `reactivite`, laissant ces colonnes à `NULL` en base.

1.14 WHEN les colonnes `fiabilite`, `integrite`, `qualite`, `reactivite` sont `NULL` dans la table `evaluations`, THEN `ScoreService::recalculate()` utilise la valeur de fallback `3` pour chaque sous-score manquant, produisant un Score ProsArtisan inexact qui ne reflète pas l'évaluation réelle du client.

**Bug 7 — Validation référent : contrôle géographique absent**

1.15 WHEN un référent valide une mission via `POST /missions/{mission}/referent-validate`, THEN le système libère les jalons et marque la mission comme validée sans vérifier que le référent se trouve physiquement dans la zone géographique de la mission, car le contrôle `ST_Distance_Sphere` est marqué `// TODO` dans `ReferentController::validateMission()`.

---

### Expected Behavior (Correct)

**Bug 1 — Flux acceptation devis / fragmentation séquestre**

2.1 WHEN le client accepte un devis, THEN le système SHALL d'abord initier un paiement via `POST /payments/initiate` (Wave ou Orange Money) pour créer une `Transaction` confirmée, puis appeler `DevisService::accept()` avec cette transaction, qui appellera `WalletService::fragmentEscrow()` avec les 6 arguments requis : `$mission`, `$client`, `$artisan`, `$montantTotal`, `$ratioMat`, `$paiementTransaction`.

2.2 WHEN Flutter déclenche l'acceptation d'un devis, THEN le système SHALL enchaîner les deux étapes dans l'ordre : (1) `POST /payments/initiate` → obtenir la `Transaction`, (2) `POST /devis/{id}/accept` avec la référence de transaction, garantissant que le séquestre est réellement financé avant d'être fragmenté.

2.3 WHEN `fragmentEscrow()` est appelé avec les 6 arguments corrects, THEN le système SHALL créditer `wallet_materiaux` et `wallet_mo` de l'artisan, mettre à jour la mission en statut `financee`, et enregistrer la transaction de référence.

**Bug 2 — Rôle "driver" et domaine "orders"**

2.4 WHEN des données d'import référencent le rôle `driver` ou des entités `orders`, THEN le système SHALL rejeter explicitement ces données avec un message d'erreur clair indiquant que ces entités n'existent pas dans ProsArtisan, sans corrompre la base de données.

2.5 WHEN un utilisateur tente de s'inscrire avec un rôle non supporté, THEN le système SHALL retourner une erreur de validation `422` avec le message "Rôle non reconnu. Les rôles disponibles sont : client, artisan, fournisseur."

**Bug 3 — Scan J-Code : format PA-XXXX**

2.6 WHEN le fournisseur scanne un QR Code contenant `PA-XXXX`, THEN le système SHALL parser la valeur alphanumérique, résoudre le J-Code correspondant via un lookup `GET /jcodes?code=PA-XXXX` ou en adaptant le route model binding pour accepter le code en plus de l'ID, puis appeler `_doScan()` avec le J-Code résolu.

2.7 WHEN le fournisseur saisit manuellement `PA-XXXX`, THEN le système SHALL reconnaître le format `PA-[A-Z0-9]{4}`, résoudre le J-Code par son code alphanumérique et déclencher le scan avec les coordonnées GPS.

2.8 WHEN le backend reçoit `POST /jcodes/{identifier}/scan`, THEN le système SHALL résoudre `{identifier}` aussi bien comme un ID entier que comme un code `PA-XXXX` alphanumérique, en utilisant un route model binding personnalisé ou un middleware de résolution.

**Bug 4 — Score ProsArtisan : structure JSON**

2.9 WHEN Flutter charge le score d'un artisan, THEN le système SHALL lire `data['score_prosartisan']` (snake_case) pour le score global et `data['breakdown']['fiabilite']`, `data['breakdown']['integrite']`, `data['breakdown']['qualite']`, `data['breakdown']['reactivite']` pour les sous-scores, conformément à la réponse de `ScoreService::getScoreDetail()`.

2.10 WHEN Flutter évalue l'éligibilité micro-crédit, THEN le système SHALL utiliser `score.value >= kMicroCreditScoreThreshold` (constante = 700, échelle 0–1000) pour rester cohérent avec le seuil backend `config('prosartisan.score_prosartisan.credit_threshold')` = 700 utilisé par `ScoreService::isEligibleCredit()`.

**Bug 5 — Écran micro-crédit**

2.11 WHEN un artisan éligible soumet une demande de micro-crédit, THEN le système SHALL appeler un endpoint HTTP réel (ex : `POST /micro-credit/apply`) qui délègue à `MicroCreditService::applyForCredit()`, persistant la demande en base via `CreditApplication` et notifiant l'artisan.

2.12 WHEN `MicroCreditScreen` s'affiche, THEN le système SHALL charger le plafond réel depuis l'API (calculé par `MicroCreditService::checkEligibility()` basé sur le score ProsArtisan) et l'afficher à l'artisan.

**Bug 6 — Notation : sous-scores ProsArtisan**

2.13 WHEN un client soumet une évaluation, THEN le système SHALL envoyer les 4 sous-scores `fiabilite`, `integrite`, `qualite`, `reactivite` (entiers 1–5) en plus de `note` et `commentaire`, conformément aux champs acceptés par `CreateEvaluationRequest`.

2.14 WHEN tous les sous-scores sont fournis, THEN `ScoreService::recalculateFromLedger()` SHALL calculer le Score ProsArtisan (échelle 0–1000) en sommant les piliers (Fiabilité 400 / Intégrité 300 / Qualité 200 / Réactivité 100), pondérés par le facteur de maturité $\min(1, n/10)$ et le grand livre `score_ledger_entries`, sans recourir aux valeurs de fallback.

**Bug 7 — Validation référent**

2.15 WHEN un référent soumet une validation de mission, THEN le système SHALL vérifier via `ST_Distance_Sphere` que la position GPS soumise est à moins de 500 m de la position GPS de la mission (`client_latitude`, `client_longitude`), et rejeter la validation avec une erreur `422` si la distance est dépassée.

---

### Unchanged Behavior (Regression Prevention)

3.1 WHEN un artisan génère un J-Code avec un ID entier valide, THEN le système SHALL CONTINUE TO résoudre le J-Code par son ID et traiter le scan normalement.

3.2 WHEN un client ou un artisan s'inscrit avec un rôle valide (`client`, `artisan`, `fournisseur`), THEN le système SHALL CONTINUE TO créer le compte et déclencher le flux KYC sans modification.

3.3 WHEN un fournisseur scanne un J-Code valide avec une position GPS à moins de 100 m de sa boutique, THEN le système SHALL CONTINUE TO valider le scan, décrémenter le stock et planifier le paiement J+1.

3.4 WHEN un fournisseur scanne un J-Code valide avec une position GPS à plus de 100 m de sa boutique, THEN le système SHALL CONTINUE TO bloquer la transaction et envoyer une alerte admin.

3.5 WHEN un artisan soumet un jalon avec photos et que le client valide via OTP, THEN le système SHALL CONTINUE TO libérer le `wallet_mo` correspondant vers le Mobile Money de l'artisan.

3.6 WHEN un artisan a un score ProsArtisan calculé, THEN le système SHALL CONTINUE TO afficher ce score dans `score_screen.dart` et l'utiliser pour le tri dans la recherche d'artisans.

3.7 WHEN un référent valide une mission avec une position GPS dans la zone autorisée, THEN le système SHALL CONTINUE TO libérer les jalons en attente et notifier l'artisan.

3.8 WHEN un client soumet une évaluation avec uniquement `note` et `commentaire` (sans sous-scores), THEN le système SHALL CONTINUE TO accepter l'évaluation, les sous-scores restant `NULL` et le calcul du score utilisant les valeurs de fallback.

3.9 WHEN `MicroCreditService::checkEligibility()` retourne `eligible: false`, THEN le système SHALL CONTINUE TO retourner le score actuel et le score requis (700) sans créer de `CreditApplication`.

3.10 WHEN le flux de paiement Wave ou Orange Money échoue lors de l'acceptation d'un devis, THEN le système SHALL CONTINUE TO ne pas fragmenter le séquestre et laisser le devis en statut `soumis`.

---

## Bug Condition Pseudocode

```pascal
// Bug 1 — Désalignement fragmentEscrow
FUNCTION isBugCondition_B1(X)
  INPUT: X = appel DevisService::accept()
  RETURN X.walletService.fragmentEscrow.argCount != 6
END FUNCTION

// Bug 3 — Scan J-Code format PA-XXXX
FUNCTION isBugCondition_B3(X)
  INPUT: X = valeur scannée ou saisie manuellement
  RETURN X matches /^PA-[A-Z0-9]{4}$/ AND int.tryParse(X) == null
END FUNCTION

// Bug 4 — Score JSON désaligné
FUNCTION isBugCondition_B4(X)
  INPUT: X = réponse JSON de GET /artisans/{id}/score
  RETURN X.top_level.contains('scoreProsArtisan') == false
         OR X.top_level.contains('fiabilite') == false
END FUNCTION

// Bug 6 — Notation sans sous-scores
FUNCTION isBugCondition_B6(X)
  INPUT: X = payload POST /evaluations depuis Flutter
  RETURN NOT X.contains('fiabilite')
         OR NOT X.contains('integrite')
         OR NOT X.contains('qualite')
         OR NOT X.contains('reactivite')
END FUNCTION

// Propriété Fix Checking — Bug 1
FOR ALL X WHERE isBugCondition_B1(X) DO
  result ← DevisService::accept'(X)
  ASSERT result.mission.status == 'financee'
  AND result.artisan.wallet_materiaux > 0
  AND result.artisan.wallet_mo > 0
END FOR

// Propriété Preservation Checking
FOR ALL X WHERE NOT isBugCondition_B3(X) DO  // X est un entier
  ASSERT JcodeRepository::scanJcode'(X) == JcodeRepository::scanJcode(X)
END FOR
```
