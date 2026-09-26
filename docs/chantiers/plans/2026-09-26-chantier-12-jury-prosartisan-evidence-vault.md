# Plan — Chantier 12 : Jury ProsArtisan (Arbitrage par les Pairs) & Evidence Vault (Coffre-Fort des Preuves SHA-256)

| Champ | Valeur |
| --- | --- |
| Statut | en cours |
| Créé le | 2026-09-26 |
| Auteur | Équipe Produit & Ingénierie ProsArtisan |
| Analyses liées | [Analyse Backlog Scrum](../../produit/analyse-backlog.md) (Évolutions 6 & 7 — Epic 11) |
| Commits | — |

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
