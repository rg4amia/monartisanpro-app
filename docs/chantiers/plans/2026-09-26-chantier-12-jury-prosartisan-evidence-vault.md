# Plan — Chantier 12 : Jury ProsArtisan (Arbitrage par les Pairs) & Evidence Vault (Coffre-Fort des Preuves SHA-256)

| Champ | Valeur |
| --- | --- |
| Statut | livré |
| Créé le | 2026-09-26 |
| Auteur | Équipe Produit & Ingénierie ProsArtisan |
| Analyses liées | [Analyse Backlog Scrum](../../produit/analyse-backlog.md) (Évolutions 6 & 7 — Epic 11) |
| Commits | `faf6fd8f`, `141b0546`, `930d2d0c` |

---

## 🎯 Objectif du Chantier 12

1. **Evidence Vault (Coffre-Fort des Preuves Numériques)** :
   - Garantir l'intégrité juridique et cryptographique infalsifiable (empreinte SHA-256, horodatage, empreinte d'appareil `device_fingerprint`, IP) de toute preuve téléversée sur la plateforme (photos de diagnostic initial, jalons de chantier et dossiers de litige).
   - Commande d'audit automatique `php artisan vault:verify-integrity` et endpoint de délivrance de certificat de preuve numérique scellée.

2. **Jury ProsArtisan (Arbitrage Décentralisé par les Pairs)** :
   - Pour les litiges de conformité technique ou de malfaçon, convocation automatique ou déclenchée par l'admin d'un collège de **3 jurés artisans certifiés** de la même spécialité / corps d'état avec **Score ProsArtisan $\ge 800$**.
   - Anonymisation bilatérale des dossiers (les jurés ne connaissent pas l'identité des parties ; les parties ignorent l'identité des jurés).
   - Vote motivé avec avis technique (`CONFORME`, `NON_CONFORME`, `RESPONSABILITE_PARTAGEE`), ratio de répartition financière recommandé, et consensus majoritaire $2/3$.
   - Indemnisation financière garantie de chaque juré (5 000 FCFA crédités sur son portefeuille main d'œuvre avec écriture dans le Grand Livre en partie double).
   - Espace juré dédié sur l'API et interface d'instruction dans le backoffice administrateur.

---

## 📐 Découpage en Lots

### 🔹 Lot A : Evidence Vault Universel & Audit Cryptographique SHA-256
- Migration MariaDB étendant `evidence_vault` : `mission_id`, `jalon_id`, `file_path`, `file_size`, `mime_type`, `device_fingerprint`, `sha256_hash`, `is_tampered`.
- Service [`EvidenceVaultService`](app/Services/EvidenceVaultService.php) :
  - `seal(UploadedFile $file, User $uploader, array $context): EvidenceVault`
  - `verifyIntegrity(EvidenceVault $entry): bool`
  - `verifyAll(): array`
- Scellement automatique dans le pipeline de soumission de preuves de jalon ([`JalonService`](app/Services/JalonService.php)) et de litiges ([`LitigeService`](app/Services/LitigeService.php)).
- Commande `php artisan vault:verify-integrity`.
- Endpoint `GET /api/v1/evidence-vault/{id}/certificate`.

### 🔹 Lot B : Moteur du Jury ProsArtisan & Arbitrage par les Pairs
- Migration MariaDB complétant `jury_reviews` : `status` (`assigned`, `voted`, `expired`), `verdict` (`CONFORME`, `NON_CONFORME`, `RESPONSABILITE_PARTAGEE`), `split_artisan_percentage`, `technical_comment`, `compensation_paid`, `expires_at`.
- Évolution de [`LitigeService`](app/Services/LitigeService.php) :
  - `assignJury(Litige $litige, int $deadlineHours = 48): Collection` (sélection stricte de 3 artisans Score $\ge 800$, même métier, indépendants).
  - `submitJuryVote(Litige $litige, User $jure, array $voteData): JuryReview` (indemnisation 5 000 FCFA via `DoubleEntryLedgerService`).
  - `evaluateJuryConsensus(Litige $litige): ?array` (consensus 2/3 ou transmission pour arbitrage admin).
  - Commande planifiée `prosartisan:expire-jury-reviews` pour remplacer les jurés inactifs après 48h.

### 🔹 Lot C : API Jurés & Intégration Backoffice Admin
- Contrôleur [`JuryController`](app/Http/Controllers/Api/V1/JuryController.php) :
  - `GET /api/v1/jury/dossiers` : dossiers soumis à l'artisan juré connecté.
  - `GET /api/v1/jury/dossiers/{id}` : dossier technique anonymisé avec pièces scellées.
  - `POST /api/v1/jury/dossiers/{id}/vote` : vote motivé du juré.
- Backoffice Administrateur (`LitigesPanel.tsx`) :
  - Section « Jury ProsArtisan » sur la fiche litige : collège des jurés, votes reçus, consensus dégagé, bouton de convocation du jury.

---

## ⚖️ Règles Métier & Contraintes
- **Règle d'or 1 & 9** : Les jurés doivent avoir `kyc_status = actif` et un `score_prosartisan >= 800` calculé depuis le ledger.
- **Règle d'or 7** : Montants FCFA en `BIGINT` stricts (indemnité 5 000 FCFA).
- **Règle d'or 5** : Missions $> 2\,000\,000$ FCFA : l'avis du Jury est un éclairage technique consultatif pour le Référent de zone.
- **Grand Livre** : Toute indemnisation juré donne lieu à une double écriture équilibrée dans `double_entry_ledger_entries`.

---

## Écarts constatés (26/09/2026)

- **Remplacement des jurés jamais exécuté** : la commande `jury:expire-overdue` existait sans être planifiée ; un juré qui ne votait pas bloquait le litige indéfiniment. Planifiée toutes les heures dans `routes/console.php`, garde de non-régression `tests/Feature/ScheduledCommandsTest.php`.
- **Anonymisation contournée** : le juré instruisait le dossier depuis la fiche litige des parties (`GET /litiges/{id}` l'admettait), qui expose noms et téléphones du client et de l'artisan — l'app les affichait dans le bloc « Vous êtes juré ». La fiche est désormais réservée aux parties et à l'admin (403 pour un juré), `myJuryReview` est retiré de `LitigeResource`, et le juré dispose d'un **espace juré** mobile (réglages artisan › « Espace juré », notification `jury_assignment`) branché sur les dossiers anonymisés `GET /jury/dossiers`, `GET /jury/dossiers/{id}`, `POST /jury/dossiers/{id}/vote` : trois avis, part de l'artisan pour un avis partagé, avis technique, preuves avec leur certification SHA-256.
- **Vote « responsabilité partagée » impossible** : `jury_reviews.verdict` était un ENUM à deux valeurs ; ce vote, proposé et validé par l'API, finissait en erreur 500. Colonne élargie par la migration `2026_09_26_180000_widen_jury_reviews_verdict_column`.
- **Dossier juré incomplet** : `JuryController` renvoyait le côté ayant ouvert le litige comme « motif » et omettait la description ; il expose désormais `motif`, `description` et `ouvert_par`.
- Tests : `JuryDossierAnonymityTest.php` (en échec avant correctif), `jury_controllers_test.dart`.
- **Sélection des jurés hors règle** : `assignJury` et `replaceExpiredJuror` se rabattaient, faute de candidats, sur d'autres métiers puis sur des artisans de score quelconque, et jugeaient le score sur la colonne stockée. Désormais seuls siègent des artisans au KYC actif, du même métier, étrangers aux deux parties, au score **recalculé depuis le ledger** au moins égal au seuil `settings.jury_min_score` (800 par défaut, **réglable dans le backoffice**, onglet Paramètres, pour élargir le vivier). Décision produit : faute de trois jurés éligibles, aucun jury n'est convoqué, le litige passe en `jury_indisponible`, les administrateurs sont alertés et l'arbitrage leur revient (422 sur l'API, message d'erreur au backoffice) ; un juré expiré sans remplaçant éligible déclenche aussi une alerte. Tests : `JurySelectionRulesTest.php`.
