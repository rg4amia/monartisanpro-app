# Parcours Client - ProsArtisan

## Vue d'ensemble
Ce document retrace le parcours complet d'un client dans l'application mobile ProsArtisan, de la découverte de l'application jusqu'à la finalisation d'un projet avec un artisan.

---

## 📱 Phase 1: Découverte et Inscription

### 1.1 Onboarding (Premier lancement)
**Écran**: `OnboardingScreen`

Le client découvre l'application à travers 3 écrans de présentation:

1. **Trouvez l'artisan idéal**
   - Recherche géolocalisée
   - Profils vérifiés avec KYC
   - Score de réputation N'Zassa

2. **Paiement sécurisé**
   - Escrow automatique
   - Jetons matériel avec validation GPS
   - Libération progressive

3. **Confiance et transparence**
   - Évaluations vérifiées
   - Médiation en cas de litige
   - Historique de transactions

**Actions possibles**:
- Passer l'onboarding
- Continuer vers la connexion

---

### 1.2 Connexion
**Écran**: `LoginScreen`

Si le client a déjà un compte:
- Saisie email et mot de passe
- Connexion sécurisée
- Redirection vers l'écran d'accueil

**Navigation**: → `HomeScreen`

---

### 1.3 Inscription
**Écran**: `RoleSelectionScreen` → `RegisterScreen`

Pour un nouveau client:

1. **Sélection du rôle**: Client
2. **Informations requises**:
   - Nom complet
   - Numéro de téléphone (+225)
   - Email
   - Mot de passe (min 8 caractères)
   - Confirmation du mot de passe

3. **Vérification OTP**:
   - Réception d'un code SMS
   - Validation du numéro de téléphone

**Navigation**: → `OtpVerificationScreen` → `HomeScreen`

---

## 🏠 Phase 2: Découverte des Artisans

### 2.1 Écran d'accueil
**Écran**: `HomeScreen`

Le client arrive sur l'écran d'accueil personnalisé:

**Éléments affichés**:
- Message de bienvenue personnalisé
- Barre de recherche principale
- Bouton "Voir sur la carte"
- Badge "X artisans à proximité" (si disponibles dans un rayon de 2km)
- Grille de catégories de métiers:
  - Bâtiment (BAT)
  - Électricité (ELEC)
  - Plomberie (PLOMB)
  - Menuiserie (MENU)
  - Peinture (PEIN)
  - Jardinage (JAR)
  - Automobile (AUTO)
  - Technologie (TECH)
  - Aménagement (AMEN)
  - Nettoyage (CLEAN)
  - Sécurité (SECU)
  - Artisanat (ART)

**Actions possibles**:
- Cliquer sur la barre de recherche → `SearchFilterScreen`
- Cliquer sur "Voir sur la carte" → `MapSearchScreen`
- Sélectionner une catégorie → `SearchFilterScreen` (avec filtre pré-appliqué)
- Accéder aux notifications
- Voir son profil

---

### 2.2 Recherche avec filtres
**Écran**: `SearchFilterScreen`

Le client affine sa recherche avec plusieurs critères:

**Filtres disponibles**:
1. **Secteur d'activité** (obligatoire)
   - Sélection dans une liste déroulante

2. **Métier spécifique** (optionnel)
   - Apparaît après sélection du secteur
   - Liste des métiers du secteur choisi

3. **Rayon de recherche**
   - Slider de 1 km à 50 km
   - Valeur par défaut basée sur la position

4. **Score N'Zassa minimum** (optionnel)
   - Pas de filtre
   - 50+ points
   - 70+ points
   - 85+ points

5. **Tri des résultats**
   - Par distance (défaut)
   - Par note
   - Par expérience

**Actions**:
- Réinitialiser les filtres
- Lancer la recherche → `SearchResultsScreen`

---

### 2.3 Résultats de recherche
**Écran**: `SearchResultsScreen`

Affichage de la liste des artisans correspondant aux critères:

**Informations par artisan**:
- Photo de profil
- Nom complet
- Métier et années d'expérience
- Note moyenne et nombre d'avis
- Distance par rapport au client
- Badge "Proche" si < 2km
- Badge "Disponible" si accepte de nouveaux projets

**Compteur**: "X artisan(s) trouvé(s)"

**Action**: Cliquer sur un artisan → `ArtisanProfileScreen`

---

### 2.4 Recherche sur carte
**Écran**: `MapSearchScreen`

Vue cartographique alternative:
- Carte interactive avec Google Maps
- Marqueurs pour chaque artisan
- Marqueur doré pour les artisans proches (< 2km)
- Position du client en temps réel
- Filtres accessibles depuis la carte

**Actions**:
- Cliquer sur un marqueur → Aperçu rapide
- Ouvrir le profil complet → `ArtisanProfileScreen`
- Ajuster les filtres → `SearchFilterScreen`

---

## 👤 Phase 3: Consultation du Profil Artisan

### 3.1 Profil détaillé
**Écran**: `ArtisanProfileScreen`

Le client consulte le profil complet d'un artisan:

**Informations affichées**:

1. **En-tête**:
   - Photo de profil
   - Nom et métier
   - Bannière avec dégradé

2. **Statistiques clés**:
   - Note moyenne (/5)
   - Nombre de projets complétés
   - Score N'Zassa

3. **Expérience**:
   - Années d'expérience dans le métier

4. **À propos**:
   - Biographie de l'artisan

5. **Zone d'intervention**:
   - Localisation
   - Distance par rapport au client
   - Badge "Proche" si applicable

6. **Disponibilité**:
   - Badge vert: "Disponible pour de nouveaux projets"
   - Badge orange: "Non disponible actuellement"

**Actions principales**:
- **Demander un devis** → Création de projet
- **Envoyer un message** → Chat (à venir)

---

## 📋 Phase 4: Création de Projet

### 4.1 Nouveau projet
**Écran**: `CreateProjectScreen`

Le client crée un projet pour recevoir des devis:

**Informations requises**:

1. **Titre du projet** (min 5 caractères)
   - Ex: "Rénovation de salle de bain"

2. **Type de métier** (optionnel)
   - Sélection dans la liste des métiers

3. **Description détaillée** (min 20 caractères)
   - Description complète des travaux

4. **Adresse du projet**
   - Saisie manuelle
   - Bouton "Utiliser ma position"
   - Géolocalisation automatique

5. **Position GPS**
   - Latitude et longitude enregistrées
   - Badge de confirmation "Position enregistrée"

**Bannière d'information**:
"Décrivez votre projet pour recevoir des devis"

**Note finale**:
"Après la création, les artisans pourront vous envoyer des devis."

**Action**: Créer le projet → Retour avec confirmation

---

## 📊 Phase 5: Gestion du Projet

### 5.1 Détails du projet
**Écran**: `ProjectDetailsScreen`

Le client suit l'évolution de son projet:

**Informations affichées**:

1. **En-tête**:
   - Titre du projet
   - Bannière avec icône construction

2. **Statut du projet**:
   - Badge coloré selon l'état:
     - 🟡 Pending (En attente)
     - 🔵 In Progress (En cours)
     - 🟢 Completed (Terminé)
     - 🔴 Cancelled (Annulé)
     - 🔴 Disputed (Litige)

3. **Description**:
   - Détails complets du projet

4. **Localisation**:
   - Adresse du chantier
   - Icône de localisation

5. **Informations client**:
   - Nom et téléphone

6. **Artisan assigné** (si accepté):
   - Nom et téléphone de l'artisan

7. **Section Devis**:
   - Compteur: "Devis (X)"
   - Liste des devis reçus
   - État de chaque devis

**Actions selon le statut**:

**Si projet en attente**:
- Consulter les devis reçus
- Annuler le projet

**Si aucun devis**:
- Message: "Aucun devis reçu"
- Attendre les propositions des artisans

---

### 5.2 Réception et consultation des devis
**Écran**: `ProjectDetailsScreen` (section devis)

Chaque devis affiché contient:
- Nom de l'artisan
- Montant total
- Statut du devis:
  - 🔵 Sent (Envoyé)
  - 🟢 Accepted (Accepté)
  - 🔴 Rejected (Rejeté)
  - 🔴 Expired (Expiré)

**Action**: Cliquer sur "Voir" → `QuoteReviewScreen`

---

### 5.3 Examen détaillé d'un devis
**Écran**: `QuoteReviewScreen`

Le client analyse le devis en détail:

**Informations complètes**:

1. **Artisan**:
   - Photo, nom et téléphone

2. **Montant total**:
   - Affiché en grand dans un encadré avec dégradé

3. **Répartition graphique**:
   - Graphique circulaire (Pie Chart)
   - Pourcentage matériaux vs main d'œuvre
   - Légende avec montants détaillés

4. **Liste des matériaux**:
   - Description de chaque item
   - Quantité × Prix unitaire
   - Total par ligne

5. **Liste main d'œuvre**:
   - Description des prestations
   - Quantité × Prix unitaire
   - Total par ligne

6. **Validité du devis**:
   - Badge avec compte à rebours
   - 🟢 "Valide encore X jours"
   - 🟡 Alerte si expire dans ≤ 3 jours
   - 🔴 "Ce devis a expiré" si dépassé

7. **Notes** (optionnel):
   - Commentaires de l'artisan

**Actions possibles**:

**Si devis valide et envoyé**:
- ✅ **Accepter le devis**
  - Confirmation par dialogue
  - Engagement de paiement
  - → `PaymentScreen`

- ❌ **Rejeter le devis**
  - Dialogue avec raison optionnelle
  - Notification à l'artisan

**Si devis expiré**:
- Message: "Contactez l'artisan pour un nouveau devis"

---

## 💳 Phase 6: Paiement et Escrow

### 6.1 Paiement sécurisé
**Écran**: `PaymentScreen`

Après acceptation du devis, le client procède au paiement:

**Informations affichées**:
- Récapitulatif du projet
- Montant total à payer
- Détails de l'escrow

**Méthodes de paiement**:
- CinetPay (Mobile Money)
- Carte bancaire
- Autres moyens locaux

**Processus**:
1. Sélection du moyen de paiement
2. Saisie des informations
3. Validation du paiement
4. Confirmation

**Sécurité Escrow**:
- Fonds bloqués dans un portefeuille séquestre
- Libération progressive selon les jalons
- Protection client et artisan

**Résultat**:
- ✅ Paiement réussi → Projet passe en "In Progress"
- ❌ Paiement échoué → Devis reste accepté, paiement à refaire

---

## 🔨 Phase 7: Suivi du Projet en Cours

### 7.1 Projet en cours
**Écran**: `ProjectDetailsScreen` (statut: In Progress)

Le client suit l'avancement des travaux:

**Éléments de suivi**:

1. **Jalons (Milestones)**:
   - Liste des étapes du projet
   - Statut de chaque jalon:
     - ⏳ Pending (En attente)
     - ✅ Completed (Terminé)
     - 💰 Paid (Payé)

2. **Messagerie projet**:
   - Communication avec l'artisan
   - Partage de photos
   - Mises à jour en temps réel

3. **Validation des jalons**:
   - L'artisan marque un jalon comme terminé
   - Le client valide la complétion
   - Libération automatique du paiement correspondant

4. **Jetons matériel** (si applicable):
   - Validation GPS des achats de matériaux
   - Traçabilité des dépenses
   - Preuve de livraison

**Actions**:
- Valider un jalon terminé
- Envoyer des messages → `ChatConversationScreen`
- Signaler un problème → `CreateDisputeScreen`

---

### 7.2 Communication
**Écran**: `ChatConversationScreen`

Chat en temps réel avec l'artisan:
- Messages texte
- Partage de photos
- Notifications push
- Historique complet

---

## ⚠️ Phase 8: Gestion des Litiges (Optionnel)

### 8.1 Création d'un litige
**Écran**: `CreateDisputeScreen`

Si problème pendant le projet:

**Informations requises**:
- Projet concerné
- Type de litige
- Description détaillée
- Photos/preuves (optionnel)

**Action**: Soumettre le litige

---

### 8.2 Suivi du litige
**Écran**: `DisputeDetailsScreen`

Le client suit la résolution:

**Informations**:
- Statut du litige:
  - 🟡 Open (Ouvert)
  - 🔵 Under Review (En examen)
  - 🟢 Resolved (Résolu)
  - 🔴 Closed (Fermé)

- Historique des messages
- Décisions de médiation
- Actions requises

**Médiation**:
- Échange avec médiateur
- Propositions de résolution
- Accord final

---

## ✅ Phase 9: Finalisation du Projet

### 9.1 Complétion du projet
**Écran**: `ProjectDetailsScreen` (statut: Completed)

Quand tous les jalons sont validés:

**Actions finales**:

1. **Évaluation de l'artisan**:
   - Note sur 5 étoiles
   - Commentaire détaillé
   - Critères spécifiques:
     - Qualité du travail
     - Respect des délais
     - Communication
     - Propreté du chantier

2. **Impact sur le score N'Zassa**:
   - L'évaluation affecte la réputation de l'artisan
   - Contribution au système de confiance

3. **Historique**:
   - Projet archivé dans l'historique
   - Accès aux détails et factures
   - Possibilité de recontacter l'artisan

---

## 📱 Fonctionnalités Transversales

### Navigation principale
Le client a accès en permanence à:

1. **Barre de navigation inférieure** (si implémentée):
   - 🏠 Accueil
   - 🔍 Recherche
   - 📋 Mes projets
   - 💬 Messages
   - 👤 Profil

2. **Menu profil**:
   - Informations personnelles
   - Historique des projets
   - Paramètres
   - Déconnexion

3. **Notifications**:
   - Nouveaux devis reçus
   - Messages artisans
   - Mises à jour projet
   - Rappels de paiement

---

## 🎯 Points Clés du Parcours

### Avantages pour le client:

1. **Sécurité**:
   - Paiement escrow
   - Artisans vérifiés (KYC)
   - Système de médiation

2. **Transparence**:
   - Devis détaillés
   - Score N'Zassa visible
   - Avis vérifiés

3. **Contrôle**:
   - Validation par jalons
   - Communication directe
   - Suivi en temps réel

4. **Simplicité**:
   - Interface intuitive
   - Recherche géolocalisée
   - Processus guidé

---

## 📊 Résumé du Flux

```
Onboarding → Inscription/Connexion
    ↓
Accueil (Découverte des catégories)
    ↓
Recherche (Filtres ou Carte)
    ↓
Résultats de recherche
    ↓
Profil artisan
    ↓
Création de projet
    ↓
Réception de devis
    ↓
Examen et acceptation du devis
    ↓
Paiement escrow
    ↓
Suivi du projet (Jalons + Chat)
    ↓
[Optionnel: Gestion de litige]
    ↓
Validation finale
    ↓
Évaluation de l'artisan
    ↓
Projet terminé
```

---

## 🔄 Parcours Alternatifs

### Scénario 1: Recherche sans inscription
- Consultation des catégories
- Recherche d'artisans
- Consultation de profils
- **Blocage**: Demande de devis nécessite une inscription

### Scénario 2: Projet sans artisan spécifique
- Création de projet direct
- Publication ouverte
- Réception de devis multiples
- Comparaison et sélection

### Scénario 3: Annulation de projet
- Projet en attente
- Aucun devis accepté
- Annulation possible
- Aucun frais

---

## 📝 Notes Techniques

### Écrans principaux:
- `OnboardingScreen`: Introduction
- `LoginScreen`: Authentification
- `RegisterScreen`: Inscription
- `HomeScreen`: Accueil client
- `SearchFilterScreen`: Filtres de recherche
- `SearchResultsScreen`: Liste d'artisans
- `MapSearchScreen`: Vue carte
- `ArtisanProfileScreen`: Profil détaillé
- `CreateProjectScreen`: Nouveau projet
- `ProjectDetailsScreen`: Suivi projet
- `QuoteReviewScreen`: Examen devis
- `PaymentScreen`: Paiement escrow
- `ChatConversationScreen`: Messagerie
- `CreateDisputeScreen`: Signalement litige
- `DisputeDetailsScreen`: Suivi litige

### Technologies:
- Flutter/Dart
- GetX (State Management & Navigation)
- Google Maps
- CinetPay (Paiement)
- Firebase (Notifications)
- Laravel Backend (API)

---

**Document créé le**: 18 février 2026
**Version**: 1.0
**Projet**: ProsArtisan - Plateforme de mise en relation clients-artisans
