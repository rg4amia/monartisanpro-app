# 🏗️ ProsArtisan — Contexte Projet Complet pour l'Éditeur de Code

## Vue d'ensemble

**ProsArtisan** est une plateforme mobile/web qui connecte **clients**, **artisans** et **fournisseurs (quincailleries)** en Côte d'Ivoire. Elle gère l'ensemble du cycle de vie d'une mission artisanale : de la mise en relation jusqu'au paiement sécurisé, avec un système de score de confiance et un accès au micro-crédit.

- **Stack technique** : Laravel (PHP) + PostgreSQL + PostGIS + API Gemini (IA) + Mobile Money (Wave / Orange Money) + Flutter (app mobile)
- **Marché cible** : Côte d'Ivoire (langue : français, devise : FCFA)
- **Utilisateurs** : Client, Artisan, Fournisseur, Référent de zone, Administrateur

---

## 👥 Acteurs du système

| Acteur | Rôle |
|---|---|
| **Client** | Passe des commandes, valide les jalons via OTP, note l'artisan |
| **Artisan** | Reçoit des missions, génère des J-Codes pour les matériaux, soumet les jalons |
| **Fournisseur** | Quincaillerie partenaire agréée, scanne les J-Codes pour livrer les matériaux |
| **Référent de zone** | Valide physiquement les missions > 2 000 000 FCFA et arbitre les litiges |
| **Administrateur** | Valide les KYC, approuve les partenaires, gère les litiges et les blocages |

---

## 🔄 Flux métier complet (6 phases)

### Phase 0 — Onboarding & Confiance
- Inscription par numéro de téléphone + sélection de rôle (Client / Artisan / Fournisseur)
- Vérification KYC : CNI + Selfie Liveness
- Validation admin → statut profil = `actif`
- Enregistrement des quincailleries partenaires (validé par Admin)

### Phase 1 — Diagnostic & Matching
- Le client décrit son besoin (texte + photos)
- **API Gemini** analyse et retourne : catégorie de travaux, niveau d'urgence, estimation en FCFA
- **PostGIS** recherche les artisans disponibles dans un rayon ≤ 2 km (position floutée à 50 m)
- Le client consulte les profils + **Score N'Zassa**, sélectionne un artisan
- L'artisan reçoit une notification, établit un **devis** (main d'œuvre vs matériaux, jalons, durée)
- Le client accepte ou négocie le devis

### Phase 2 — Séquestre & Flux Financiers
- Le client paie l'acompte via **Wave ou Orange Money**
- SMS de confirmation → statut mission = `Financée`
- **Fragmentation automatique du séquestre** en deux wallets :
  - `Wallet A — Matériaux` (ex : 65%) → bloqué, destiné au fournisseur
  - `Wallet B — Main d'œuvre` (ex : 35%) → libéré progressivement par jalons
- Notification à l'artisan : mission financée, démarrage autorisé

### Phase 3 — Jeton Matériel (J-Code) & Anti-Fraude
- L'artisan génère un **J-Code** (format `PA-XXXX` + QR Code) via app ou USSD
- Il se rend chez une quincaillerie partenaire proche
- Le fournisseur scanne le QR ou saisit le code USSD
- **Vérification GPS** : distance < 100 m obligatoire pour valider la transaction
  - Si suspect → blocage automatique + alerte Admin
- Le fournisseur livre les matériaux → paiement J+1 garanti
- L'artisan uploade une **photo géolocalisée** des matériaux sur le chantier
- Le client reçoit une notification de confirmation

### Phase 4 — Travaux & Libération par Jalons
- L'artisan réalise les travaux par étape, soumet chaque jalon avec photos géolocalisées
- Le système envoie un **OTP par SMS** au client pour validation
- Si montant mission > **2 000 000 FCFA** → visite du Référent de zone requise
- Validation → libération du jalon : virement instantané sur Mobile Money de l'artisan
- Cycle répété jusqu'au dernier jalon

### Phase 5 — Clôture & Score N'Zassa
- L'artisan soumet la **Fiche d'Intervention** (check-list + récapitulatif)
- Le client signe digitalement (doigt ou OTP SMS)
- Mission = `terminée`
- Le client attribue une note (1 à 5 étoiles)
- Calcul du **Score N'Zassa** :
  - Fiabilité : 40%
  - Intégrité : 30%
  - Qualité : 20%
  - Réactivité : 10%
- Score archivé pour audit bancaire
- Si Score > 70/100 → accès **micro-crédit d'urgence** (déblocage < 2h)
- Génération d'un **Rapport PDF de solvabilité** pour les microfinances partenaires

### Flux parallèle — Gestion des Litiges
- Client ou Artisan peut déclencher un signalement à tout moment
- L'Admin instruit le dossier (logs + photos + chat)
- Décision d'arbitrage :
  - Client a raison → remboursement client
  - Artisan a raison → paiement artisan
  - Incertain → gel des fonds + visite Référent de zone

---

## 🗄️ Modèle de données principal (entités clés)

```
users (id, phone, role: client|artisan|fournisseur|referent|admin, kyc_status, score_nzassa, wallet_a, wallet_b, latitude, longitude)

missions (id, client_id, artisan_id, status: en_attente|financee|en_cours|terminee|litige, montant_total, montant_materiaux, montant_mo, created_at)

devis (id, mission_id, artisan_id, lignes_json, jalons_json, statut: brouillon|soumis|accepte|refuse)

jalons (id, mission_id, ordre, description, montant, statut: en_attente|soumis|valide|paye, otp_code, photos_json)

jcodes (id, mission_id, artisan_id, fournisseur_id, code, qr_url, montant, statut: actif|utilise|expire, latitude_scan, longitude_scan)

transactions (id, type: acompte|liberation_jalon|paiement_fournisseur|remboursement, montant, wallet_source, wallet_dest, provider: wave|orange_money, statut, created_at)

litiges (id, mission_id, declencheur_id, type: client|artisan, statut: ouvert|en_cours|resolu, decision, created_at)

evaluations (id, mission_id, evaluateur_id, evalue_id, note, commentaire, created_at)

fournisseurs_agreees (id, user_id, nom_boutique, latitude, longitude, statut: en_attente|agree|suspendu)
```

---

## 🔧 Conventions de code

- **Framework** : Laravel 10+ avec architecture MVC + Services + Jobs pour les opérations asynchrones
- **Base de données** : PostgreSQL avec extension PostGIS pour les requêtes géospatiales
- **Paiements** : Intégration Wave CI et Orange Money CI via leurs APIs respectives
- **IA** : Google Gemini API pour l'analyse des besoins et estimations
- **Notifications** : SMS (ex: Twilio ou infobip) + Push notifications
- **USSD** : Support du canal USSD pour les J-Codes (artisans sans smartphone)
- **Sécurité** : OTP pour validation des jalons, GPS check pour les J-Codes, KYC obligatoire
- **Devise** : FCFA (Franc CFA), montants en entiers (pas de décimales)
- **Langue** : Français (toutes les interfaces, messages, notifications)

---

## 📎 Fichier de référence

Le diagramme de flux complet du projet est disponible dans `prosartisan_flux.mmd` (format Mermaid). Il décrit visuellement l'ensemble des phases ci-dessus avec les acteurs, décisions et transitions d'état.