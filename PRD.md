# Document de Spécifications Fonctionnelles (PRD) — ProsArtisan

## 1. Vision & Objectifs
**ProsArtisan** est une plateforme marketplace mettant en relation :
* Des **Clients** ayant des besoins de travaux (diagnostic et chantiers).
* Des **Artisans** qualifiés et certifiés.
* Des **Fournisseurs (Quincailleries)** pour la fourniture des matériaux.
* Des **Livreurs (Drivers)** pour le transport de marchandises et matériaux.
* Des **Référents de zone** pour l'arbitrage et le contrôle des chantiers d'envergure.

---

## 2. Parcours Utilisateur de A à Z : Analyse & Retours d'Expérience

### 🎬 Phase 0 : Inscription, Authentification & KYC
#### Workflow
1. L'utilisateur télécharge l'application et s'inscrit en fournissant son numéro de téléphone (`+225` + 10 chiffres).
2. Un code OTP à 4 chiffres lui est envoyé par SMS ou WhatsApp.
3. Il sélectionne son rôle : `client`, `artisan`, `fournisseur`, ou `driver` (livreur).
4. Pour pouvoir effectuer la moindre transaction, il doit soumettre son KYC (CNI + selfie).
5. L'administrateur valide ou rejette le dossier $\rightarrow$ `kyc_status = actif`.

#### 🔍 Retours Observés
* **Points Forts :**
  * Sécurité irréprochable : l'obligation du statut KYC actif bloque l'accès aux opérations financières de manière étanche.
  * Flexibilité du canal OTP (SMS/WhatsApp).
* **Points Faibles :**
  * Friction élevée à l'onboarding.

---

### 🔍 Phase 1 : Diagnostic & Matching Géospatial
#### Workflow
1. Le client décrit son problème. L'API **Gemini** analyse la demande, classe la catégorie, évalue l'urgence, et propose une estimation de prix.
2. Le système recherche les artisans actifs dans un rayon $\le$ 2 km à l'aide de requêtes spatiales MySQL (`ST_Distance_Sphere`).
3. La position GPS exacte de l'artisan est floutée d'environ 50 mètres pour préserver sa vie privée.
4. Tri par **Score de Réputation N'Zassa** (enregistré sous la colonne `score_prosartisan` en BDD).

#### 🔍 Retours Observés
* **Points Forts :**
  * Floutage efficace et diagnostic intelligent.
* **Points Faibles :**
  * Rayon fixe de 2 km trop restrictif en dehors des zones urbaines denses.

---

### 💵 Phase 2 : Devis & Séquestre (Escrow)
#### Workflow
1. L'artisan formule une proposition de devis (lignes matériaux, lignes MO, jalons).
2. Le client accepte le devis et paie l'acompte total via Wave ou Orange Money.
3. Les fonds sont fragmentés et bloqués :
   * `wallet_materiaux` : Réservé exclusivement à la quincaillerie fournisseur.
   * `wallet_mo` : Débloqué jalon par jalon pour l'artisan.
4. Le ratio matériaux/MO est figé à l'acceptation et devient **strictement immuable**.

---

### 🚚 Flux Logistique E-commerce & Livreur (Livreurs / Drivers)
Le système gère également un flux de livraison de matériaux en 3 étapes :
#### Workflow
1. **Recherche de Livreur (Radar de courses) :**
   * Dès que le fournisseur marque la commande comme prête (`status = prepared`), le statut passe à `searching_driver`.
   * Les livreurs dans un rayon spatial de 10 km reçoivent une notification. Si aucune réponse n'est obtenue locale, le radar est étendu à toutes les zones.
2. **Acceptation de la course par le Livreur :**
   * Le livreur accepte la course via son application.
   * Le système calcule dynamiquement le prix de livraison selon une formule combinant la distance Google Maps et le temps estimé :
     $$\text{Frais} = ((\text{Distance (km)} \times 150) + (\text{Durée (min)} \times 50)) \times \text{Multiplicateur Véhicule} \times \text{Multiplicateur Surge}$$
     *(Minimum forfaitaire de 1000 FCFA).*
   * La part de livraison payée par le client est consignée en séquestre (`escrow_order_` + ID).
   * L'état passe à `driver_assigned`. Le livreur reçoit l'adresse de la boutique et le code de retrait (`pickup_code`).
3. **Récupération chez le Fournisseur (Verify Pickup) :**
   * Le livreur se rend chez le fournisseur et présente le `pickup_code`.
   * Le fournisseur valide le code. Les fonds des matériaux sont immédiatement transférés du séquestre vers le portefeuille réel du fournisseur.
   * L'état de la commande passe à `driver_picked_up`. Le livreur reçoit l'adresse de livraison du client et le client reçoit le code secret de livraison (`reception_code`).
4. **Remise au Client final (Verify Delivery) :**
   * Le livreur remet les colis au client.
   * Le client fournit son `reception_code` secret au livreur.
   * Le livreur saisit le code dans l'application. Les frais de livraison sont alors libérés du séquestre vers le portefeuille réel du livreur.
   * L'état passe à `delivered`.

#### 🔍 Retours Observés
* **Points Forts :**
  * Calcul de tarification dynamique intelligent (ajusté selon la classe de véhicule : moto, voiture, cargo).
  * Double contrôle à double clé (`pickup_code` pour le fournisseur et `reception_code` pour le livreur) évitant tout détournement de marchandise ou fraude à la livraison.
* **Points Faibles / Risques :**
  * Pas de gestion automatisée de réaffectation de livreur si celui-ci a un contretemps en chemin après acceptation.

---

### 📦 Phase 3 : Achat des Matériaux & Anti-Fraude J-Code
*(Pour les prestations chantiers de l'Artisan)*
* L'artisan génère un **J-Code** unique (`PA-XXXX` + QR Code + code USSD).
* Le fournisseur scanne le code. **Vérification GPS obligatoire :** la distance entre la boutique et le scan doit être $< 100$ mètres.
* Les fonds du `wallet_materiaux` de l'artisan sont transférés au fournisseur.

---

### 🏗️ Phase 4 : Exécution des Jalons & Validation OTP
* L'artisan soumet les jalons terminés avec photos géolocalisées.
* Analyse de vision (Gemini Vision) obligatoire.
* Le client valide par OTP $\rightarrow$ libération de la MO.
* Si le montant de la mission $> 2 000 000$ FCFA $\rightarrow$ passage physique obligatoire d'un Référent de zone avant paiement.

---

### ⭐️ Phase 5 : Clôture & Score N'Zassa
* Le client note l'artisan selon la pondération (Fiabilité 40%, Intégrité 30%, Qualité 20%, Réactivité 10%).
* Le **Score N'Zassa** est mis à jour dans la colonne de base de données **`score_prosartisan`** (échelle 0 à 1000).
* Si le score $> 700$, l'artisan est éligible aux micro-crédits d'urgence.

---

## 3. Matrice de Synthèse : Forces vs Faiblesses

| Étape du flux | Points Forts (Forces) | Points Faibles (Faiblesses) |
| --- | --- | --- |
| **Onboarding & KYC** | • Blocage KYC actif robuste.<br>• OTP multi-canal. | • Forte friction d'entrée. |
| **Matching & Géo** | • Floutage GPS artisan.<br>• Qualification intelligente pannes. | • Rayon de 2km trop rigide. |
| **Séquestre** | • Ratios fixes et immuables. | • Pas de modification possible du devis. |
| **Livreurs & Logistique** | • Radar étendu automatique.<br>• Formule dynamique Maps.<br>• Double code de sécurité (Pickup/Reception). | • Pas de réaffectation automatique du livreur. |
| **J-Code & Anti-Fraude** | • Clôture GPS boutique < 100m. | • Signal GPS en intérieur capricieux. |
| **Jalons & Libération** | • Validation progressive OTP.<br>• Contrôle physique > 2M FCFA. | • Risque de blocage client injoignable. |
| **Score N'Zassa** | • Facteur clé d'accès au micro-crédit. | • Stored under `score_prosartisan` column. |

---

## 4. Spécifications Techniques & Règles Métier Critiques

### Règles d'Or (Immuables)
1. **KYC Actif Obligatoire :** `kyc_status = 'actif'` requis.
2. **Immuabilité du Ratio :** Ratio de fragmentation figé dès l'acceptation.
3. **Géorepérage J-Code :** Distance scan-boutique $> 100\text{m}$ $\rightarrow$ Blocage automatique.
4. **Validation de Livraison par Codes :** Aucun transfert de fonds logistique ou matériel sans validation des codes respectifs (`pickup_code` et `reception_code`).
5. **Score N'Zassa (`score_prosartisan`) :** Archivage complet dans un Ledger d'événements pour audits bancaires et micro-crédits.
6. **FCFA Entier :** Toutes les colonnes financières en `BIGINT`.

### Formule mathématique du Score N'Zassa
Le score d'un artisan $S(t)$ est calculé sur une échelle de 0 à 1000 :
$$S(t) = \min\left(1000, \max\left(0, S_{base} + \sum_{k} (\omega_k \cdot E_k \cdot C_k) - \Delta(t)\right)\right)$$
* $S_{base}$ : Score de départ (0 par défaut pour tout nouvel artisan non évalué).
* $E_k$ : Valeur de l'événement $k$ (ex: $+10$ pour jalon à l'heure, $-300$ pour abandon de chantier).
* $\omega_k$ : Coefficient de pondération selon la catégorie (Fiabilité, Intégrité, Qualité, Réactivité).
* $C_k$ : Facteur de crédibilité du client évaluateur (0.1 pour un nouveau client à 1.5 pour un partenaire B2B).
* $\Delta(t)$ : Pénalité d'inactivité ("La Rouille") s'appliquant après 60 jours sans chantier.

---

## 5. Liste des Besoins Produits Prioritaires (Backlog)

### 🚚 Logistique & Livreurs
1. **Réaffectation Automatique de Livreur (Driver Fallback) :** Si un livreur accepte une course mais reste immobile pendant plus de 15 minutes ou s'éloigne du fournisseur, la course doit lui être retirée automatiquement et remise dans le radar.
2. **Consommation Partielle du J-Code :** Permettre à l'artisan d'utiliser son J-Code chez plusieurs fournisseurs agréés si le premier n'a pas la totalité du stock disponible (débit partiel du séquestre matériel).
3. **Mode Hors-Ligne pour les Livreurs :** Permettre au livreur de valider la récupération ou la livraison via des protocoles USSD ou SMS cryptés dans les zones blanches à faible connectivité internet.

### 🛡️ Anti-Fraude, Sécurité & Finance (Ledger)
4. **Ledger Financier Immuable (Double-Entry Ledger) :** Bannir la modification directe de la colonne `wallet_balance` en BDD. Tout mouvement d'argent doit être calculé dynamiquement à partir d'une table de transactions historiques immuable (`wallet_ledger_entries`) dotée de clés d'idempotence uniques pour éviter les doubles débits.
5. **Circuit Breaker sur les APIs de Paiement :** Si Wave CI ou Orange Money CI subit une panne, le système doit basculer en mode dégradé, suspendre l'initiation de nouveaux paiements mobiles et afficher un message clair à l'utilisateur.
6. **Device Fingerprinting (Empreinte Appareil) :** Lier le compte artisan/client à l'identifiant matériel unique du téléphone (Device UUID) pour empêcher les artisans bannis de recréer instantanément un compte sur le même appareil.

### 🏗️ Gestion de Chantier & Jalons
7. **Bypass de Sécurité / Auto-Release 72h :** Si un jalon soumis reste sans réponse pendant 72h, il est validé automatiquement par le système, libérant ainsi les fonds de main-d'œuvre pour protéger la trésorerie de l'artisan.
8. **Ajustements de Devis en cours de Mission (Avenants) :** Permettre la création d'avenants au devis initial (matériel supplémentaire imprévu) validés par le client, réajustant le séquestre sans avoir à annuler toute la mission.

### ⭐️ Système de Réputation (Score N'Zassa)
9. **Indice de Crédibilité de l'Évaluateur ($C_k$) :** Pondérer la note laissée à l'artisan selon le profil du client (les avis des clients récurrents pèsent plus lourd pour éviter le dénigrement ou les faux avis).
10. **Dégradation Temporelle ("La Rouille" $\Delta(t)$) :** Diminuer progressivement le Score N'Zassa si l'artisan reste inactif pendant plus de 60 jours afin de valoriser les profils actifs.

