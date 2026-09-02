# Analyse du Backlog Scrum Restructuré — ProsArtisan

## Similitudes avec l'implémentation actuelle & Évolutions recommandées

---

## 1. Vue d'ensemble du Backlog (12 Epics)

| Epic | Intitulé | Statut dans le code actuel |
| ------ | ---------- | --------------------------- |
| **Epic 1** | Ingénierie Financière & Séquestre (Escrow) | ✅ Implémenté (wallet_materiaux / wallet_mo) |
| **Epic 2** | Modèle Logistique & Tarification Dynamique (Delivery) | ⚠️ Partiellement (J-Code uniquement, pas de livreur) |
| **Epic 3** | Smart Token Matériel (J-Code Anti-Détournement) | ✅ Implémenté (PA-XXXX, QR, USSD) |
| **Epic 4** | Suivi de Chantier & Preuves Sociales (Jalons) | ✅ Implémenté (OTP jalons, photos géolocalisées) |
| **Epic 5** | Architecture Anti-Fraude & Télémétrie | ⚠️ Partiel (GPS J-Code, pas de Device Fingerprinting) |
| **Epic 6** | Score Social ProsArtisan (Formule mathématique) | ⚠️ Partiel (4 composantes, pas d'Event Sourcing) |
| **Epic 7** | Onboarding KYC/KYB Automatisé | ⚠️ Partiel (photo CNI, pas de Liveness detection IA) |
| **Epic 8** | Topologie Micro-Services & Bus d'événements | ❌ Non implémenté (architecture monolithique Laravel) |
| **Epic 9** | Machine à États du Projet (FSM immuable) | ⚠️ Partiel (statuts enum, pas de pattern State) |
| **Epic 10** | MCD Financier (Ledger / Event Sourcing) | ❌ Non implémenté (pas de Ledger_Entry dédié) |
| **Epic 11** | FSM du Litige (Arbitrage structuré) | ⚠️ Partiel (states basiques, pas de Jury ProsArtisan) |
| **Epic 12** | Modèle Mathématique ProsArtisan avancé | ❌ Non implémenté (formule simple, pas d'accumulateur) |

---

## 2. Similitudes Fortes — Ce qui converge ✅

### 2.1 Séquestre & Fragmentation des fonds (Epic 1 ↔ Code)

Le backlog décrit exactement ce qui est implémenté :

- Dépôt via Wave CI / Orange Money → `wallet_materiaux` (65%) + `wallet_mo` (35%)
- Ratio fixé à l'acceptation du devis, **immuable** (règle d'or dans CLAUDE.md)
- Notification OTP à la confirmation de paiement
- Bascule automatique du statut mission vers `financee`

**Convergence quasi-parfaite** sur ce domaine.

---

### 2.2 Smart Token Matériel / J-Code (Epic 3 ↔ Code)

| Backlog | Code actuel |
| --------- | ------------- |
| Code alphanumérique `PA-XXXX` | ✅ `jcodes` table, format `PA-XXXX` |
| QR Code + USSD | ✅ `qr_url` + `ussd_code` |
| Vérification GPS < 100m fournisseur | ✅ `ST_Distance_Sphere` < 100m |
| Virement J+1 fournisseur | ✅ Prévu dans `WalletService` |
| Péremption 7 jours | ✅ `expires_at` sur jcodes |
| Consommation partielle du jeton | ❌ Non implémenté |

---

### 2.3 Suivi de Chantier & Jalons OTP (Epic 4 ↔ Code)

- Photos géolocalisées horodatées → `photos_json` dans jalons
- OTP 4 chiffres SMS pour validation client → `otp_code` + `otp_expires_at`
- Libération `wallet_mo` après OTP validé → `JalonService`
- Seuil Référent 2 000 000 FCFA pour intervention physique

**Identique dans les deux documents.**

---

### 2.4 Score ProsArtisan & Système de Réputation (Epic 6/12 ↔ Code)

- 4 composantes : Fiabilité (40%), Intégrité (30%), Qualité (20%), Réactivité (10%)
- Score 0-100 dans le code → **0-1000 dans le backlog** (divergence)
- Score > 70 (code) / > 700 (backlog) → accès micro-crédit
- Badges "Maître Artisan" prévus dans les deux

---

### 2.5 Gestion des Litiges (Epic 11 ↔ Code)

- Déclenchement par client ou artisan
- Gel des fonds en mode DISPUTED
- Arbitrage par Admin
- Décision : remboursement / paiement artisan / gel

**Convergence structurelle**, mais le backlog va bien plus loin (voir évolutions).

---

## 3. Évolutions & Enrichissements Recommandés 🚀

### 🔴 PRIORITÉ HAUTE — Impact métier critique

#### Évolution 1 : Machine à États Formelle avec Pattern State (Epic 9)

**Backlog** : Interdire `$projet->status = 'COMPLETED'` direct via ORM. Chaque transition doit être une classe métier avec Guards.

**Code actuel** : Transitions d'état faites directement dans les controllers.

**Action** : Installer `spatie/laravel-model-states` et créer des classes de transition :

```
app/States/Mission/
  DraftState.php
  PendingFundingState.php
  FundedLockedState.php
  InProgressState.php
  PendingApprovalState.php
  CompletedState.php
  DisputedState.php
```

Chaque classe vérifie ses **Guards** avant de committer. Un développeur qui bypasse cette règle est bloqué en PR.

**Nouveaux statuts à ajouter** : `PENDING_FUNDING`, `FUNDED_LOCKED`, `PENDING_APPROVAL` (plus granulaires que l'actuel `en_attente/financee/en_cours/terminee/litige`).

---

#### Évolution 2 : Ledger Financier (Event Sourcing) — Epic 10

**Backlog** : Le solde d'un portefeuille n'est jamais une colonne écrasée. C'est la **somme des lignes** d'une table `Ledger_Entry`.

**Code actuel** : `wallet_materiaux` et `wallet_mo` sont des colonnes directement modifiables sur `users`.

**Risque actuel** : Un bug peut écraser un solde sans trace. Un admin corrompu peut modifier le montant.

**Action** : Créer une table `score_ledger_entries` (déjà prévue dans Epic 12) ET une table `wallet_ledger_entries` :

```sql
CREATE TABLE wallet_ledger_entries (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  wallet_owner_id BIGINT UNSIGNED NOT NULL,
  wallet_type     ENUM('materiaux','mo') NOT NULL,
  sens            ENUM('CREDIT','DEBIT') NOT NULL,
  montant         BIGINT NOT NULL,
  type_operation  ENUM('DEPOSIT_WAVE','TOKEN_REDEEM','JALON_RELEASE','FEE_CUT','REFUND','REVERSE_ENTRY') NOT NULL,
  cle_idempotence VARCHAR(100) NOT NULL,
  reference_ext   VARCHAR(100) NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY idx_idempotence (type_operation, cle_idempotence)
);
```

La **clé d'idempotence** empêche les doubles débits si Wave renvoie deux fois le même webhook.

---

#### Évolution 3 : CRON Force-Pass "72h" pour jalons bloquants (Epic 9)

**Backlog** : Si le client ne valide pas un jalon en 72h, un CRON passe automatiquement `PENDING_APPROVAL → COMPLETED` et libère les fonds.

**Code actuel** : Libération uniquement sur OTP client — risque de blocage malveillant.

**Action** : Job Laravel à créer :

```php
// app/Console/Commands/AutoReleaseJalonsCommand.php
// Schedule : toutes les heures
// Logique : SELECT jalons WHERE statut='soumis' AND updated_at < NOW() - 72h
//           → Passe en 'valide' + libère wallet_mo
```

---

#### Évolution 4 : Score ProsArtisan — Accumulateur d'Événements (Epic 12)

**Backlog** : Formule mathématique complète :
$$S(t) = \min(1000, \max(0, S_{base} + \sum_{k}(\omega_k \cdot E_k \cdot C_k) - \Delta(t)))$$

**Code actuel** : Calcul simplifié, score 0-100, pas d'Event Sourcing.

**Evolutions concrètes** :

1. **Migrer score 0-100 → 0-1000** (score_base = 300 pour nouvel artisan)
2. Créer table `score_ledger_entries` (événements pondérés)
3. Implémenter **Indice de Crédibilité Ck** du client évaluateur :
   - Nouveau client (0 chantier) : Ck = 0.1
   - Client KYC + > 3 chantiers : Ck = 1.0
   - Client institutionnel B2B : Ck = 1.5
4. Implémenter **Dégradation Temporelle Δ(t)** ("La Rouille") :
   - Inactivité > 60 jours → -5 points/semaine
5. Poids asymétriques (sanction >> récompense) :
   - Chantier réussi sans litige : +5 pts
   - Fraude (Jury ProsArtisan) : -150 pts
   - Abandon de chantier : -300 pts (quasi-blacklist)
   - Jalon à l'heure : +2 pts, Retard > 48h : -15 pts

---

### 🟡 PRIORITÉ MOYENNE — Robustesse & Sécurité

#### Évolution 5 : Circuit Breaker sur les APIs Mobile Money (Epic 7)

**Backlog** : Si Orange Money / Wave est indisponible, basculer en **mode dégradé** au lieu d'accumuler des requêtes fantômes.

**Action** : Utiliser le package `ackintosh/ganesha` ou implémenter manuellement avec Redis :

- Compteur d'erreurs en cache Redis par provider (wave/orange)
- Seuil : > 5 erreurs en 60s → Circuit ouvert
- Message utilisateur : _"Transactions momentanément suspendues par l'opérateur"_
- Demi-ouverture automatique après 30s pour retry

---

#### Évolution 6 : Coffre-Fort des Preuves (Evidence Vault) — Epic 11

**Backlog** : Chaque photo litige est hashée en SHA-256 et stockée avec son empreinte pour prouver l'intégrité en cas de contestation légale.

**Code actuel** : Photos stockées sans hash cryptographique.

**Action** :

```php
// Dans LitigeService::storeEvidence()
$fileHash = hash_file('sha256', $uploadedFile->getRealPath());
// Stocker dans table evidence_vault :
// { litige_id, file_url, sha256_hash, uploaded_by, uploaded_at, ip_address }
```

---

#### Évolution 7 : Jury ProsArtisan (Arbitrage par les Pairs) — Epic 11

**Backlog** : Pour les litiges de malfaçon, envoyer anonymement les photos à **3 artisans de la même spécialité** avec Score ProsArtisan > 800. Consensus 2/3 → verdict automatique.

**Code actuel** : Arbitrage uniquement par Admin.

**Valeur** : Décentralise le jugement technique, réduit la charge admin, renforce la crédibilité du verdict.

**Tables à créer** :

```sql
CREATE TABLE jury_reviews (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  litige_id    BIGINT UNSIGNED NOT NULL,
  juré_id      BIGINT UNSIGNED NOT NULL,
  verdict      ENUM('CONFORME','NON_CONFORME') NOT NULL,
  voted_at     TIMESTAMP NOT NULL,
  compensation BIGINT DEFAULT 0
);
```

---

#### Évolution 8 : Filtre Anti-Palabre LLM Niveau 1 — Epic 11

**Backlog** : Agent LLM qui transcrit la note vocale du plaignant, extrait les faits bruts, supprime l'émotionnel, et propose une résolution standard automatique.

**Code actuel** : Panel LLM admin existe (`LlmAdminController`) mais orienté données artisans, pas médiation litiges.

**Action** : Étendre `LlmAdminController` avec un endpoint `POST /api/llm/mediation` qui :

1. Reçoit la transcription vocale + catégorie du litige
2. Appelle Gemini API avec un prompt spécialisé "médiateur"
3. Retourne une proposition de résolution standardisée

---

#### Évolution 9 : Device Fingerprinting (IMEI Binding) — Epic 7

**Backlog** : Lier le compte artisan à l'IMEI de son smartphone. Changement suspect = alerte + gel du Score.

**Code actuel** : Authentification Sanctum tokens sans vérification device.

**Action** : Ajouter colonne `device_fingerprint` sur `users`, envoyer l'IMEI/device_id depuis Flutter au login, comparer à chaque session.

---

### 🟢 PRIORITÉ BASSE — Enrichissements futurs (Roadmap)

#### Évolution 10 : Computer Vision sur les Photos de Jalons — Epic 7

**Backlog** : Un modèle de vision analyse les photos uploadées pour vérifier la cohérence avec le devis (ex: devis = 2 tonnes ciment, photo = seau vide → blocage automatique).

**Valeur** : Réduction de 40% des litiges frauduleux.

**Tech** : Google Vision API ou Gemini multimodal.

---

#### Évolution 11 : Programme de Parrainage sous Caution — Epic 7

**Backlog** : Un "Maître Artisan" peut coopter un apprenti. Si l'apprenti fraude, le parrain perd des points.

**Valeur** : Délègue le contrôle qualité à la communauté.

**Table à créer** : `parrainage (parrain_id, filleul_id, score_caution, created_at)`

---

#### Évolution 12 : Tarification Dynamique de la Livraison (Surge Pricing) — Epic 2

**Backlog** : Moteur de prix en temps réel intégrant distance, durée, classe de véhicule (Moto/Voiture/Cargo) et coefficient de surge selon la demande locale.

**Code actuel** : Livraison non modélisée en dehors du J-Code fournisseur.

**Note** : Cela implique un acteur supplémentaire "Livreur" non présent dans le schéma actuel.

---

## 4. Tableau de Priorisation

| # | Évolution | Impact Métier | Effort | Sprint cible |
| --- | ----------- | --------------- | -------- | -------------- |
| 1 | Machine à États Formelle (Pattern State) | ⭐⭐⭐⭐⭐ | 3j | Sprint 1 |
| 2 | Ledger Financier (Event Sourcing) | ⭐⭐⭐⭐⭐ | 5j | Sprint 1 |
| 3 | CRON Force-Pass 72h Jalons | ⭐⭐⭐⭐ | 1j | Sprint 1 |
| 4 | ProsArtisan Accumulateur + Ck + Δ(t) | ⭐⭐⭐⭐⭐ | 4j | Sprint 2 |
| 5 | Circuit Breaker Mobile Money | ⭐⭐⭐⭐ | 2j | Sprint 2 |
| 6 | Evidence Vault (SHA-256 hashing) | ⭐⭐⭐⭐ | 1j | Sprint 2 |
| 7 | Jury ProsArtisan (Arbitrage pairs) | ⭐⭐⭐⭐ | 4j | Sprint 3 |
| 8 | LLM Médiateur Litiges (Niveau 1) | ⭐⭐⭐ | 3j | Sprint 3 |
| 9 | Device Fingerprinting (IMEI) | ⭐⭐⭐ | 2j | Sprint 3 |
| 10 | Computer Vision Photos Jalons | ⭐⭐⭐ | 5j | Sprint 4 |
| 11 | Programme Parrainage sous Caution | ⭐⭐ | 3j | Sprint 4 |
| 12 | Surge Pricing Livraison + Acteur Livreur | ⭐⭐ | 10j | Sprint 5+ |

---

## 5. Conclusion

> Le backlog Scrum ProsArtisan est **architecturalement cohérent** avec le code existant sur les domaines fondamentaux (séquestre, J-Code, jalons OTP, KYC, Score ProsArtisan). La vision est solide et le code actuel en implémente environ **55%**.

### Les 3 priorités absolues pour la robustesse

1. **Ledger financier immuable** — protège juridiquement et comptablement la plateforme
2. **Machine à États formelle** — élimine les bugs de transition d'état et les contournements dev
3. **Formule ProsArtisan avancée (Ck + Δ(t))** — différencie ProsArtisan de tous ses concurrents locaux

### Le différenciateur concurrentiel majeur

Le **Jury ProsArtisan** (arbitrage par les pairs) est une idée brillante et unique. Aucune plateforme similaire en Afrique de l'Ouest n'a ce mécanisme. Son implémentation doit être inscrite en Sprint 3 maximum.
