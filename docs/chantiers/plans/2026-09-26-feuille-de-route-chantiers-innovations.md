# Feuille de Route & Plan d'Action des Chantiers — ProsArtisan

| Champ | Valeur |
| --- | --- |
| Statut | **Proposé & Prêt pour ordonnancement** |
| Date de création | 2026-09-26 |
| Auteur | Équipe Produit & Ingénierie ProsArtisan |
| Périmètre | Backend Laravel 12, App Mobile Flutter, Services IA Gemini, Schéma MariaDB |
| Références | [PRD.md](../../../PRD.md), [CLAUDE.md](../../../CLAUDE.md), [CHRONOLOGIE.md](../CHRONOLOGIE.md) |

---

## 🎯 Vue d'Ensemble & Ordonnancement

Pour assurer un suivi de mise en œuvre strict et sans régression sur l'écosystème ProsArtisan, les propositions sont regroupées en **4 chantiers cohérents**, découpés en lots indépendants :

```mermaid
flowchart TD
    subgraph Chantier 6 : Expérience Terrain & Économie Data
        C6A[Lot 6A : Voice-to-Quote Nouchi & BTP Ivoirien]
        C6B[Lot 6B : Mode Data Saver & Compression WebP 80%]
    end

    subgraph Chantier 7 : Fintech Approvisionnement
        C7A[Lot 7A : J-Code Multi-Comptoirs - Schéma & Débits Partiels]
        C7B[Lot 7B : J-Code Multi-Comptoirs - Expérience Mobile & Scan]
    end

    subgraph Chantier 8 : Télémétrie & Anti-Fraude
        C8A[Lot 8A : WebSockets Reverb - Tracking & Événements Live]
        C8B[Lot 8B : Moteur Anti-Collusion & Device Fingerprinting]
    end

    subgraph Chantier 9 : Rigueur Bancaire & Résolution de Litiges
        C9A[Lot 9A : Grand Livre en Partie Double - Double-Entry Ledger]
        C9B[Lot 9B : Télé-Expertise Litiges par Vision Gemini]
        C9C[Lot 9C : Passeport de Solvabilité & Micro-Assurance Chantier]
    end

    Chantier 6 --> Chantier 7
    Chantier 7 --> Chantier 8
    Chantier 8 --> Chantier 9
```

---

## 🛠️ Chantier 6 — Expérience Terrain Artisan & Économie de Données Mobiles

### 1. Fiche Signalétique
* **Code :** `CHANTIER-06`
* **Priorité :** 🔴 Haute (Adoption immédiate sur le terrain)
* **Effort estimé :** 4 jours-homme (2 lots)
* **Composants touchés :** `GeminiService.php`, `VoiceQuoteTest.php`, `frontend_flutter/lib/modules/missions/widgets/devis_creation/`, `media_compressor_service.dart`.

### 2. Objectif
Faciliter l'expression des devis par les artisans moins à l'aise avec la rédaction formelle via un modèle de langage calibré sur le lexique ivoirien du BTP, tout en réduisant de 70% la facture data internet des utilisateurs lors de l'envoi de preuves photos/vidéos.

### 3. Découpage en Lots

#### 🔹 Lot 6A : Voice-to-Quote en Nouchi & Glossaire BTP Ivoirien (2 jours)
* **Backend :**
  * Enrichissement du prompt système de `GeminiService::transcribeAndStructureQuote()` avec le lexique ivoirien :
    * Calibrage des termes : « fers de 12 », « paquet de pointes 70 », « tuyau pression », « chape bouchonnée », « djassa », « pot de peinture à l'eau », etc.
    * Détection des unités courantes : sacs de ciment 50kg, barres, mètres linéaires, rouleaux de fil de fer, journées d'ouvriers.
  * Extraction structurée au format JSON strict avec séparation des coûts de matériaux et de main-d'œuvre.
* **Mobile :**
  * Support du retour immédiat dans l'écran de création de devis avec pastilles d'alerte pour les éléments nécessitant confirmation artisan.
* **Règles d'or :** Règle 7 (montants FCFA entiers `BIGINT`), Règle 8 (validation JSON stricte).

#### 🔹 Lot 6B : Mode « Data Saver » & Compression Intelligente WebP (2 jours)
* **Mobile (Flutter) :**
  * Création d'un service de compression d'images avant envoi (`ImageProcessingService`) :
    * Conversion automatique en format WebP (qualité 80%).
    * Préservation impérative des métadonnées EXIF géospatiales (lat/lng) et de l'horodatage pour la validation anti-fraude.
    * Compression adaptative des vidéos de preuves (résolution 720p, débit optimisé H.264/AAC).
  * Ajout d'un commutateur dans les Paramètres : « Mode économie de données mobiles » (activé par défaut).
* **Vérification :** Réduction de 2-4 Mo à < 350 Ko par photo transmise.

---

## 💳 Chantier 7 — Le J-Code Multi-Comptoirs (Consommation Fractionnée)

### 1. Fiche Signalétique
* **Code :** `CHANTIER-07`
* **Priorité :** 🔴 Haute (Flexibilité logistique et satisfaction fournisseurs)
* **Effort estimé :** 5 jours-homme (2 lots)
* **Composants touchés :** `jcodes`, nouvelle table `jcode_redemptions`, `JCodeService.php`, `SupplierCashoutService.php`, écrans mobile J-Code.

### 2. Objectif
Permettre à un artisan de s'approvisionner dans plusieurs quincailleries spécialisées avec un seul J-Code rattaché à son enveloppe `wallet_materiaux`, sans bloquer la totalité du montant chez un unique commerçant.

### 3. Découpage en Lots

#### 🔹 Lot 7A : Schéma & Moteur de Débits Partiels Backend (3 jours)
* **Base de données :**
  * Migration `create_jcode_redemptions_table` :
    * `id`, `jcode_id`, `fournisseur_id`, `montant_debite` (FCFA `BIGINT`), `position_scan` (`POINT` sans SRID), `scanned_at`, `recu_photo_url`.
  * Ajout sur `jcodes` : `solde_restant` (`BIGINT`), `statut` étendu (`actif`, `partiellement_utilise`, `epuise`, `expire`).
* **Backend (`JCodeService.php`) :**
  * Méthode `redeemPartial(JCode $jcode, int $montant, User $fournisseur, Point $position)` :
    * Vérification GPS stricte : le fournisseur doit être à $< 100\text{ m}$ de sa boutique enregistrée (`ST_Distance_Sphere`).
    * Vérification solde : `$montant <= $jcode->solde_restant`.
    * Débit de l'enveloppe et transfert de `$montant` vers le compte à payer du fournisseur (éligible au cash-out J+1).
    * Mise à jour du statut : `partiellement_utilise` si solde $> 0$, sinon `epuise`.
* **Règles d'or :** Règle 2 (ratio immuable conservé), Règle 3 (blocage GPS < 100 m strict sans dérogation).

#### 🔹 Lot 7B : Interface Mobile Quincaillerie & Artisan (2 jours)
* **Application Mobile :**
  * Écran Fournisseur : après scan du QR, affichage du montant maximal disponible et saisie du montant exact de la facturette/bordereau de retrait.
  * Prise de photo du bon de livraison papier émargé par l'artisan.
  * Écran Artisan : jauge dynamique du solde J-Code en temps réel avec historique des quincailleries débitées.

---

## ⚡ Chantier 8 — Télémétrie Temps Réel & Moteur Anti-Collusion

### 1. Fiche Signalétique
* **Code :** `CHANTIER-08`
* **Priorité :** 🟡 Moyenne / Sécurité critique
* **Effort estimé :** 6 jours-homme (2 lots)
* **Composants touchés :** Laravel Reverb, `AntiBotService.php`, `FraudDetectionService.php`, `DeliveryTrackingService.php`, Flutter WebSocket client.

### 2. Objectif
Éliminer le polling HTTP coûteux en batterie pour le suivi de course livreur et les jalons, tout en armant le système contre les fraudes de complicité (faux chantiers artisan-client).

### 3. Découpage en Lots

#### 🔹 Lot 8A : WebSockets Natifs avec Laravel Reverb (3 jours)
* **Backend :**
  * Configuration de **Laravel Reverb** sur le port dédié avec authentification Sanctum sur les canaux privés.
  * Événements diffusés :
    * `DeliveryPositionUpdated` : coordonnées temps réel du livreur (canaux privés client & artisan).
    * `JalonStatusChanged` : notification instantanée de soumission ou validation d'OTP.
    * `JCodeRedeemed` : mise à jour instantanée du solde matériel.
* **Mobile :**
  * Intégration d'un client WebSocket résilient (`web_socket_channel` / Pusher client) avec reconnexion automatique exponentielle et bascule automatique en polling si le socket est interrompu.

#### 🔹 Lot 8B : Moteur Anti-Collusion & Device Fingerprinting (3 jours)
* **Backend (`FraudDetectionService.php`) :**
  * Collecte et comparaison de l'empreinte d'appareil lors des étapes clés (inscription, acceptation devis, paiement, scan J-Code, validation OTP) :
    * Identifiant d'installation d'application, modèle matériel, adresse IP publique, subnet réseau.
  * **Règles de suspicion :**
    * Alerte ROUGE : Client et Artisan partagent le même identifiant d'appareil ou la même IP lors du cycle création $\rightarrow$ paiement devis.
    * Alerte ORANGE : Validation de l'OTP client émise depuis une position GPS identique au mètre près à celle de l'artisan sans délai de latence.
  * Action automatique : Gel conservatoire de la libération des fonds et notification prioritaire au Référent de zone.

---

## 🏛️ Chantier 9 — Rigueur Bancaire & Résolution Structurée des Litiges

### 1. Fiche Signalétique
* **Code :** `CHANTIER-09`
* **Priorité :** 🔴 Haute (Éligibilité institutionnelle, banques & litiges volumineux)
* **Effort estimé :** 7 jours-homme (3 lots)
* **Composants touchés :** `ledger_entries`, `WalletService.php`, `LitigeService.php`, `MicroCreditService.php`, Admin backoffice Inertia.

### 2. Objectif
Mettre en place une comptabilité en partie double infalsifiable (Double-Entry Ledger) pour les audits bancaires et la BCEAO, intégrer la télé-expertise assistée par vision IA pour réduire de 80% les déplacements physiques de litiges, et formaliser le passeport de solvabilité bancaire.

### 3. Découpage en Lots

#### 🔹 Lot 9A : Grand Livre en Partie Double (Double-Entry Ledger Pur) (3 jours)
* **Architecture financière :**
  * Création de la table `ledger_entries` immuable (append-only) :
    * `id`, `transaction_group_id` (UUID), `account_source`, `account_destination`, `amount` (`BIGINT`), `currency` (`XOF`), `entry_type`, `created_at`.
  * Principe : tout mouvement financier s'écrit par une paire stricte :
    * Exemple Séquestre : Débit `client_escrow` $\leftrightarrow$ Crédit `platform_escrow_mo` + `platform_escrow_materials`.
    * Exemple Jalon : Débit `platform_escrow_mo` $\leftrightarrow$ Crédit `artisan_cashable_wallet`.
  * Règle de vérification : $\sum(\text{Débits}) - \sum(\text{Crédits}) = 0$ garanti par transaction de base de données.
  * Commande Artisan : `php artisan ledger:verify-integrity` pour audit automatique quotidien.

#### 🔹 Lot 9B : Télé-Expertise Litiges par Vision Gemini (2 jours)
* **Backend & IA (`LitigeService.php`, `GeminiService.php`) :**
  * Lors de l'ouverture d'un litige, soumission obligatoire d'une vidéo/série de photos commentées par chaque partie.
  * Pipeline Gemini Vision :
    * Comparaison différentielle entre les photos du diagnostic initial, les devis acceptés et l'état final litigieux.
    * Détection objective : pourcentage d'achèvement réel, malfaçons apparentes, non-conformité par rapport aux lignes matériaux payées.
    * Génération d'une proposition d'arbitrage financière recommandée à l'administrateur (ex: 65% débloqué artisan / 35% remboursé client).

#### 🔹 Lot 9C : Passeport de Solvabilité & Micro-Assurance Chantier (2 jours)
* **Fonctionnalités :**
  * Génération d'un rapport de solvabilité PDF crypté avec signature HMAC et QR code de vérification pour les institutions financières partenaires (Advans, Baobab, Cofina).
  * Intégration de l'option « Garantie Chantier Sérénité » : retenue de 1.5% affectée à un fonds de garantie pour indemnisation express en cas d'abandon ou malfaçon majeure.

---

## 📅 Calendrier de Déploiement & Jalons

| Chantier | Lots | Durée estimée | Prérequis | Livrables clés |
| :--- | :--- | :---: | :--- | :--- |
| **Chantier 6** | 6A (Nouchi) + 6B (Data Saver) | **Semaine 1** (4 j) | Aucun | Voice-to-Quote enrichi, app mobile économe |
| **Chantier 7** | 7A (Backend) + 7B (Mobile) | **Semaine 2** (5 j) | Chantier 6 | Débits partiels J-Code, flux multi-quincailleries |
| **Chantier 8** | 8A (Reverb) + 8B (Anti-Collusion) | **Semaine 3** (6 j) | Chantier 7 | WebSockets temps réel, alertes fraude matériel |
| **Chantier 9** | 9A (Ledger) + 9B (Vision) + 9C (Assurance) | **Semaine 4** (7 j) | Chantier 8 | Grand livre en partie double, arbitrage IA, PDF bancaire |

---

## ✅ Matrice de Conformité aux Règles d'Or

| Règle d'Or | Respect dans ce Plan |
| :--- | :--- |
| **1. KYC Obligatoire** | Le déblocage des fonds multi-quincailleries et des arbitrages requiert un statut KYC actif. |
| **2. Ratio de Séquestre Immuable** | Les débits partiels du J-Code (Chantier 7) s'opèrent strictement dans l'enveloppe fermée `wallet_materiaux`. |
| **3. Contrôle GPS $< 100\text{ m}$** | Chaque décompte partiel de J-Code valide impérativement la distance magasin par `ST_Distance_Sphere`. |
| **5. Seuil Référent $2\,000\,000\text{ FCFA}$** | L'arbitrage par IA (Chantier 9B) sert d'outil d'aide à la décision ; la signature physique du référent reste requise pour les chantiers $> 2\text{ M FCFA}$. |
| **7. Montants FCFA Entiers** | Toutes les transactions du Double-Entry Ledger sont stockées en `BIGINT` stricts sans décimales. |
| **9. Zéro Initial Absolu** | Aucun point de score n'est attribué hors évaluations ou missions réelles validées. |
