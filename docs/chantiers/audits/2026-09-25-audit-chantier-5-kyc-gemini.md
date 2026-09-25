# Audit — Réalisation du Chantier 5 (KYC automatisé avec Gemini Vision)

| Champ | Valeur |
| --- | --- |
| Réalisé le | 2026-09-25 |
| Auteur | Claude Code |
| Plan vérifié | `../plans/2026-09-25-chantier-5-kyc-gemini.md` |
| Commit audité | `25a97550`, puis la reprise des étapes 5 et 6 |

## Périmètre

Comparaison, étape par étape, du plan avec le code livré (backend Laravel, backoffice Inertia, application Flutter), et relecture de sécurité du premier jet trouvé dans l'arbre de travail. Non couvert : essai réel contre l'API Gemini (aucune clé de production utilisée) et exécution locale de la suite MariaDB (couverte par le job CI `tests-mariadb`).

## Résultats

| Étape du plan | État | Détail |
| --- | --- | --- |
| 1. Migration et modèle | ✅ conforme | Colonnes prévues, plus `ocr_document_number` indexé (migration idempotente, Règle d'or 55) |
| 2. `GeminiService` (OCR, biométrie) | ✅ conforme, avec écart | Échec fermé au lieu d'un résultat simulé (voir Anomalies, n° 1) |
| 3. `KycService::processAiVerification` | ✅ conforme | Seuil 85 et contrôle des anomalies, plus les garde-fous listés ci-dessous |
| 4. API et routes | ✅ conforme | `upload-cni`, `upload-selfie`, `status`, `verify-ai` ; les trois routes d'envoi sous `throttle:ai` |
| 5. Backoffice | ✅ conforme après reprise | Premier passage partiel (texte sans badge). Reprise : badges « IA favorable / Revue conseillée / Risque élevé », détail dépliable avec les motifs `kyc_ai_blockers`, badge « Auto-validé » dans la liste des utilisateurs |
| 6. Mobile | ✅ conforme après reprise | Premier passage partiel (statut mémorisé seulement). Reprise : indicateur de vérification, dialogue de félicitations `KycVerifiedDialog` |
| 7. Tests et documentation | ✅ conforme | `KycAiVerificationTest` (14 tests), tests backoffice et Flutter ; PRD, AGENTS.md (règle 105), CLAUDE.md (Règle d'or 69) |

## Anomalies

1. **Critique — activation de tout compte sans clé Gemini.** Le premier jet renvoyait un résultat simulé « visages concordants à 94 % » dès que la clé manquait (ou valait `PLACEHOLDER_KEY`), y compris en production. Corrigé : `callKycVision` renvoie `null` et les analyses portent `analysis_available = false`. Test : `test_without_a_gemini_key_no_account_is_ever_activated`.
2. **Haute — comparaison faciale périmée.** Une concordance établie avec une CNI restait valable après le remplacement de cette CNI par celle d'un tiers. Corrigé : le selfie mémorise la pièce comparée (`ai_analysis.cni_document_id`) et tout nouvel envoi relance la comparaison. Test : `test_a_replaced_identity_document_is_compared_to_the_selfie_again`.
3. **Haute — même pièce pour plusieurs comptes.** Corrigé par `ocr_document_number`. Test : `test_an_identity_document_already_used_by_another_account_is_not_approved`.
4. **Moyenne — réactivation par l'IA d'un dossier rejeté par un admin, et fournisseurs auto-approuvés sans revue CNMCI.** Corrigé : statut `en_attente` exigé, rôles éligibles configurables.
5. **Moyenne — injection de dépendance optionnelle dans `KycService`** (contraire à la Règle d'or 49). Corrigé.
6. **Faible — étiquette « Vérifié » affichée sur mobile avant toute réponse du serveur** (Règle d'or 29). Corrigé.
7. **Bloquante en CI — espaces avant `<?php` dans `KycController.php`** dans le commit `25a97550` : erreur fatale « Namespace declaration statement has to be the very first statement ». Les jobs `tests` et `tests-mariadb` ont échoué et bloqué le déploiement (la production n'a pas été touchée). Corrigé dans le commit suivant.

## Suites

- Vérifier le premier appel réel à Gemini en production (clé configurée) et les journaux `ai_usage_logs` (`kyc_ocr_cni`, `kyc_facial_match`).
- Point hors périmètre relevé : `AuthRepository.kycStatus()` (mobile) lit `kycStatus` à la racine de la réponse alors que l'API renvoie `data.kyc_status` ; à vérifier.
