# Guide: Accès aux Projets et Suivi des Commandes - Client

## 🎯 Vue d'Ensemble

Ce guide explique comment un client peut accéder à ses projets et suivre ses commandes dans l'application ProsArtisan selon l'implémentation actuelle du frontend.

---

## 📱 Méthodes d'Accès aux Projets

### Méthode 1: Depuis le Dashboard Artisan (Temporaire)
**Écran actuel**: `ArtisanDashboardScreen`

**Navigation**:
```
Dashboard Artisan → Bouton "Voir tout" → ProjectListScreen
```

**Note**: Cette navigation est actuellement implémentée dans le dashboard artisan. Il faudra ajouter un accès similaire pour les clients.

### Méthode 2: Navigation Directe (À Implémenter)
**Recommandation**: Ajouter un bouton dans `HomeScreen` pour les clients

**Navigation suggérée**:
```dart
// Dans HomeScreen, ajouter:
ElevatedButton.icon(
  onPressed: () => Get.to(() => const ProjectListScreen()),
  icon: const Icon(Icons.work_outline),
  label: const Text('Mes projets'),
)
```

### Méthode 3: Bottom Navigation Bar (À Implémenter)
**Recommandation**: Créer une navigation principale avec onglets

**Structure suggérée**:
```
┌─────────────────────────────────┐
│                                 │
│         Contenu Écran           │
│                                 │
└─────────────────────────────────┘
┌─────┬─────┬─────┬─────┬─────┐
│ 🏠  │ 🔍  │ 📋  │ 💬  │ 👤  │
│Home │Rech.│Proj.│Chat │Prof.│
└─────┴─────┴─────┴─────┴─────┘
```

---

## 📋 Écran: Liste des Projets

### Accès
**Écran**: `ProjectListScreen`
**Fichier**: `frontend/lib/features/projects/presentation/screens/project_list_screen.dart`

### Interface

#### 1. Barre d'Application
```
┌─────────────────────────────────┐
│ ← Mes projets                   │
└─────────────────────────────────┘
```

#### 2. Onglets de Filtrage
```
┌─────────────────────────────────┐
│ Tous │ En attente │ En cours │ Terminés │
└─────────────────────────────────┘
```

**Filtres disponibles**:
- **Tous**: Affiche tous les projets
- **En attente** (`pending`): Projets créés, en attente de devis
- **En cours** (`in_progress`): Projets avec devis accepté, travaux en cours
- **Terminés** (`completed`): Projets complétés

#### 3. Liste des Projets

Chaque carte de projet affiche:

```
┌─────────────────────────────────┐
│ Titre du projet        [Badge]  │
│                                 │
│ Description du projet...        │
│                                 │
│ 📍 Adresse du chantier          │
│ 📅 Date de création             │
│                                 │
│ 📄 X devis reçu(s)              │
└─────────────────────────────────┘
```

**Informations affichées**:
- Titre du projet
- Badge de statut (coloré selon l'état)
- Description (2 lignes max)
- Adresse du chantier
- Date de création (format relatif: "Aujourd'hui", "Hier", "Il y a X jours")
- Nombre de devis reçus (si applicable)

**Badges de Statut**:
- 🟡 **En attente** (fond jaune clair)
- 🔵 **En cours** (fond bleu clair)
- 🟢 **Terminé** (fond vert clair)
- 🔴 **Annulé** (fond rouge clair)

#### 4. Actions

**Pull-to-Refresh**:
- Glisser vers le bas pour actualiser la liste

**Bouton Flottant** (Floating Action Button):
```
┌─────────────────────────────────┐
│                                 │
│                                 │
│                      ┌────────┐ │
│                      │ + Nouveau│
│                      │  projet │ │
│                      └────────┘ │
└─────────────────────────────────┘
```

**Action**: Créer un nouveau projet → `CreateProjectScreen`

#### 5. État Vide

Si aucun projet:
```
┌─────────────────────────────────┐
│                                 │
│           📋                    │
│                                 │
│      Aucun projet               │
│                                 │
│  Créez votre premier projet     │
│                                 │
│    [Créer un projet]            │
│                                 │
└─────────────────────────────────┘
```

---

## 📊 Écran: Détails du Projet

### Accès
**Depuis**: `ProjectListScreen` → Clic sur une carte de projet
**Écran**: `ProjectDetailsScreen`
**Fichier**: `frontend/lib/features/projects/presentation/screens/project_details_screen.dart`

### Interface

#### 1. En-tête avec Image
```
┌─────────────────────────────────┐
│ ←                               │
│                                 │
│         🏗️                      │
│                                 │
│    Titre du Projet              │
└─────────────────────────────────┘
```

**Caractéristiques**:
- Dégradé de couleur primaire
- Icône construction en arrière-plan
- Titre du projet en overlay
- Hauteur expansible (200px)

#### 2. Badge de Statut
```
┌─────────────────────────────────┐
│  ┌──────────────┐               │
│  │ 🟡 EN ATTENTE │               │
│  └──────────────┘               │
└─────────────────────────────────┘
```

**Statuts possibles**:
- 🟡 **EN ATTENTE** (Pending)
- 🔵 **EN COURS** (In Progress)
- 🟢 **TERMINÉ** (Completed)
- 🔴 **ANNULÉ** (Cancelled)
- 🔴 **LITIGE** (Disputed)

#### 3. Section Description
```
┌─────────────────────────────────┐
│ 📄 Description                  │
│                                 │
│ Texte complet de la description │
│ du projet avec tous les détails │
│ fournis par le client...        │
└─────────────────────────────────┘
```

#### 4. Section Localisation
```
┌─────────────────────────────────┐
│ 📍 Localisation                 │
│                                 │
│ ┌─────────────────────────────┐ │
│ │ 📌  Adresse complète du     │ │
│ │     chantier                │ │
│ └─────────────────────────────┘ │
└─────────────────────────────────┘
```

#### 5. Section Client (Mes Informations)
```
┌─────────────────────────────────┐
│ 👤 Mes informations             │
│                                 │
│ ┌─────────────────────────────┐ │
│ │ 👤  Nom du Client           │ │
│ │     +225 XX XX XX XX        │ │
│ └─────────────────────────────┘ │
└─────────────────────────────────┘
```

#### 6. Section Artisan (Si Assigné)
```
┌─────────────────────────────────┐
│ 🔨 Artisan                      │
│                                 │
│ ┌─────────────────────────────┐ │
│ │ 👤  Nom de l'Artisan        │ │
│ │     +225 XX XX XX XX        │ │
│ └─────────────────────────────┘ │
└─────────────────────────────────┘
```

**Note**: Cette section apparaît uniquement si un devis a été accepté et un artisan assigné.

#### 7. Section Devis
```
┌─────────────────────────────────┐
│ 📋 Devis (3)                    │
│                                 │
│ ┌─────────────────────────────┐ │
│ │ Nom Artisan 1      [Envoyé] │ │
│ │                             │ │
│ │ Total: 1,500,000 XOF        │ │
│ │                    [Voir]   │ │
│ └─────────────────────────────┘ │
│                                 │
│ ┌─────────────────────────────┐ │
│ │ Nom Artisan 2    [Accepté]  │ │
│ │                             │ │
│ │ Total: 1,200,000 XOF        │ │
│ │                    [Voir]   │ │
│ └─────────────────────────────┘ │
│                                 │
│ ┌─────────────────────────────┐ │
│ │ Nom Artisan 3     [Rejeté]  │ │
│ │                             │ │
│ │ Total: 1,800,000 XOF        │ │
│ │                    [Voir]   │ │
│ └─────────────────────────────┘ │
└─────────────────────────────────┘
```

**Informations par devis**:
- Nom de l'artisan
- Statut du devis (badge coloré)
- Montant total formaté
- Bouton "Voir" pour les détails

**Statuts de devis**:
- 🔵 **Envoyé** (Sent)
- 🟢 **Accepté** (Accepted)
- 🔴 **Rejeté** (Rejected)
- 🔴 **Expiré** (Expired)

**État vide**:
```
┌─────────────────────────────────┐
│ 📋 Devis (0)                    │
│                                 │
│ ┌─────────────────────────────┐ │
│ │         📭                  │ │
│ │                             │ │
│ │   Aucun devis reçu          │ │
│ │                             │ │
│ └─────────────────────────────┘ │
└─────────────────────────────────┘
```

#### 8. Actions (Selon le Statut)

**Si projet EN ATTENTE**:
```
┌─────────────────────────────────┐
│ [❌ Annuler le projet]          │
└─────────────────────────────────┘
```

**Action**: Annuler le projet
- Dialogue de confirmation
- Annulation définitive
- Retour à la liste

---

## 📄 Écran: Examen d'un Devis

### Accès
**Depuis**: `ProjectDetailsScreen` → Clic sur "Voir" d'un devis
**Écran**: `QuoteReviewScreen`
**Fichier**: `frontend/lib/features/projects/presentation/screens/quote_review_screen.dart`

### Interface

#### 1. En-tête Artisan
```
┌─────────────────────────────────┐
│ ←  Devis de [Nom Artisan]       │
└─────────────────────────────────┘
```

#### 2. Informations Artisan
```
┌─────────────────────────────────┐
│ ┌─────────────────────────────┐ │
│ │ 👤  Nom de l'Artisan        │ │
│ │     +225 XX XX XX XX        │ │
│ └─────────────────────────────┘ │
└─────────────────────────────────┘
```

#### 3. Montant Total (Mis en Évidence)
```
┌─────────────────────────────────┐
│ ╔═══════════════════════════╗   │
│ ║                           ║   │
│ ║    MONTANT TOTAL          ║   │
│ ║                           ║   │
│ ║   1,500,000 XOF           ║   │
│ ║                           ║   │
│ ╚═══════════════════════════╝   │
└─────────────────────────────────┘
```

**Style**: Encadré avec dégradé de couleur primaire

#### 4. Répartition Graphique
```
┌─────────────────────────────────┐
│ Répartition des coûts           │
│                                 │
│        ╱───╲                    │
│       │  🥧 │  Graphique        │
│        ╲───╱   Circulaire       │
│                                 │
│ ■ Matériaux: 900,000 XOF (60%)  │
│ ■ Main d'œuvre: 600,000 XOF (40%)│
└─────────────────────────────────┘
```

**Composant**: Pie Chart (Graphique circulaire)
- Visualisation des proportions
- Légende avec montants et pourcentages

#### 5. Détails Matériaux
```
┌─────────────────────────────────┐
│ 🛠️ Matériaux                    │
│                                 │
│ Ciment (10 sacs)                │
│ 10 × 5,000 XOF = 50,000 XOF     │
│                                 │
│ Briques (500 unités)            │
│ 500 × 200 XOF = 100,000 XOF     │
│                                 │
│ Fer à béton (50 kg)             │
│ 50 × 1,500 XOF = 75,000 XOF     │
│                                 │
│ ...                             │
│                                 │
│ Sous-total: 900,000 XOF         │
└─────────────────────────────────┘
```

**Format par ligne**:
- Description de l'item
- Quantité × Prix unitaire = Total

#### 6. Détails Main d'Œuvre
```
┌─────────────────────────────────┐
│ 👷 Main d'œuvre                 │
│                                 │
│ Maçonnerie                      │
│ 5 jours × 20,000 XOF = 100,000  │
│                                 │
│ Pose de carrelage               │
│ 3 jours × 25,000 XOF = 75,000   │
│                                 │
│ Finitions                       │
│ 2 jours × 15,000 XOF = 30,000   │
│                                 │
│ ...                             │
│                                 │
│ Sous-total: 600,000 XOF         │
└─────────────────────────────────┘
```

#### 7. Validité du Devis
```
┌─────────────────────────────────┐
│ ⏰ Validité                     │
│                                 │
│ 🟢 Valide encore 12 jours       │
└─────────────────────────────────┘
```

**États possibles**:
- 🟢 **Valide encore X jours** (> 3 jours)
- 🟡 **Expire dans X jours** (≤ 3 jours)
- 🔴 **Ce devis a expiré** (expiré)

#### 8. Notes (Optionnel)
```
┌─────────────────────────────────┐
│ 📝 Notes                        │
│                                 │
│ Commentaires et remarques de    │
│ l'artisan concernant le devis...│
└─────────────────────────────────┘
```

#### 9. Actions (Selon le Statut)

**Si devis ENVOYÉ et VALIDE**:
```
┌─────────────────────────────────┐
│ [✅ Accepter le devis]          │
│                                 │
│ [❌ Rejeter le devis]           │
└─────────────────────────────────┘
```

**Action Accepter**:
1. Dialogue de confirmation
2. Explication de l'engagement
3. Redirection vers paiement → `PaymentScreen`

**Action Rejeter**:
1. Dialogue de confirmation
2. Raison optionnelle
3. Notification à l'artisan

**Si devis EXPIRÉ**:
```
┌─────────────────────────────────┐
│ ⚠️ Ce devis a expiré            │
│                                 │
│ Contactez l'artisan pour un     │
│ nouveau devis                   │
└─────────────────────────────────┘
```

**Si devis ACCEPTÉ**:
```
┌─────────────────────────────────┐
│ ✅ Devis accepté                │
│                                 │
│ [Voir le projet]                │
└─────────────────────────────────┘
```

**Si devis REJETÉ**:
```
┌─────────────────────────────────┐
│ ❌ Devis rejeté                 │
└─────────────────────────────────┘
```

---

## 🔄 Flux Complet de Suivi

### Étape 1: Création du Projet
```
HomeScreen → [Recherche Artisan] → ArtisanProfileScreen
                                           ↓
                                  [Demander un devis]
                                           ↓
                                  CreateProjectScreen
                                           ↓
                                  [Créer le projet]
                                           ↓
                              Projet créé (statut: pending)
```

### Étape 2: Réception des Devis
```
Notification: "Nouveau devis reçu"
         ↓
ProjectListScreen → [Clic sur projet]
         ↓
ProjectDetailsScreen
         ↓
Section Devis → [Voir]
         ↓
QuoteReviewScreen
```

### Étape 3: Acceptation et Paiement
```
QuoteReviewScreen → [Accepter le devis]
         ↓
Dialogue de confirmation
         ↓
PaymentScreen
         ↓
[Paiement CinetPay]
         ↓
Projet passe en "in_progress"
```

### Étape 4: Suivi des Travaux
```
ProjectDetailsScreen (in_progress)
         ↓
- Jalons (Milestones)
- Messages avec artisan
- Validation des étapes
- Libération progressive des paiements
```

### Étape 5: Finalisation
```
Tous les jalons validés
         ↓
Projet passe en "completed"
         ↓
CreateReviewScreen
         ↓
[Évaluer l'artisan]
         ↓
Projet archivé
```

---

## 📊 États du Projet et Actions Disponibles

### Tableau Récapitulatif

| Statut | Badge | Actions Client | Écrans Accessibles |
|--------|-------|----------------|-------------------|
| **Pending** | 🟡 En attente | - Consulter devis<br>- Accepter/Rejeter devis<br>- Annuler projet | - ProjectDetailsScreen<br>- QuoteReviewScreen |
| **In Progress** | 🔵 En cours | - Valider jalons<br>- Envoyer messages<br>- Signaler litige | - ProjectDetailsScreen<br>- MilestoneTrackingScreen<br>- ChatConversationScreen<br>- CreateDisputeScreen |
| **Completed** | 🟢 Terminé | - Évaluer artisan<br>- Consulter historique | - ProjectDetailsScreen<br>- CreateReviewScreen |
| **Cancelled** | 🔴 Annulé | - Consulter détails | - ProjectDetailsScreen |
| **Disputed** | 🔴 Litige | - Suivre litige<br>- Communiquer avec médiateur | - ProjectDetailsScreen<br>- DisputeDetailsScreen |

---

## 🔔 Notifications

### Types de Notifications

1. **Nouveau devis reçu**
   - Titre: "Nouveau devis pour [Projet]"
   - Action: Ouvre `QuoteReviewScreen`

2. **Devis accepté par artisan**
   - Titre: "Votre devis a été accepté"
   - Action: Ouvre `ProjectDetailsScreen`

3. **Jalon terminé**
   - Titre: "Jalon terminé - Validation requise"
   - Action: Ouvre `MilestoneTrackingScreen`

4. **Nouveau message**
   - Titre: "Nouveau message de [Artisan]"
   - Action: Ouvre `ChatConversationScreen`

5. **Projet terminé**
   - Titre: "Projet terminé - Évaluez l'artisan"
   - Action: Ouvre `CreateReviewScreen`

---

## 🎯 Recommandations d'Amélioration

### 1. Ajouter un Accès Direct depuis HomeScreen

**Code suggéré** (à ajouter dans `home_screen.dart`):

```dart
// Dans la section après la carte promotionnelle
Container(
  margin: const EdgeInsets.symmetric(
    horizontal: Spacing.base,
    vertical: Spacing.base,
  ),
  child: ElevatedButton.icon(
    onPressed: () => Get.to(() => const ProjectListScreen()),
    icon: const Icon(Icons.work_outline),
    label: const Text('Mes projets'),
    style: ElevatedButton.styleFrom(
      padding: const EdgeInsets.all(Spacing.base),
      backgroundColor: AppColors.darkAccentPrimary,
    ),
  ),
)
```

### 2. Implémenter une Bottom Navigation Bar

**Structure suggérée**:

```dart
class MainNavigationScreen extends StatefulWidget {
  @override
  State<MainNavigationScreen> createState() => _MainNavigationScreenState();
}

class _MainNavigationScreenState extends State<MainNavigationScreen> {
  int _currentIndex = 0;
  
  final List<Widget> _screens = [
    const HomeScreen(),
    const SearchFilterScreen(),
    const ProjectListScreen(),
    const ChatListScreen(),
    const ProfileScreen(),
  ];
  
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: _screens[_currentIndex],
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _currentIndex,
        onTap: (index) => setState(() => _currentIndex = index),
        items: const [
          BottomNavigationBarItem(
            icon: Icon(Icons.home),
            label: 'Accueil',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.search),
            label: 'Recherche',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.work_outline),
            label: 'Projets',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.chat_bubble_outline),
            label: 'Messages',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.person_outline),
            label: 'Profil',
          ),
        ],
      ),
    );
  }
}
```

### 3. Ajouter un Badge de Notification

**Sur l'icône Projets**:
- Afficher le nombre de nouveaux devis reçus
- Badge rouge avec compteur

### 4. Améliorer les Filtres de Projet

**Ajouter des filtres supplémentaires**:
- Par date (plus récent, plus ancien)
- Par montant
- Par artisan
- Recherche par titre

---

## 📝 Résumé

### Accès Actuel aux Projets

✅ **Disponible**:
- `ProjectListScreen` avec onglets de filtrage
- `ProjectDetailsScreen` avec toutes les informations
- `QuoteReviewScreen` pour examiner les devis
- Système de statuts et badges

❌ **Manquant**:
- Accès direct depuis `HomeScreen` pour les clients
- Bottom Navigation Bar
- Notifications push configurées
- Badge de compteur de nouveaux devis

### Navigation Recommandée

**Pour les clients**:
```
HomeScreen → [Bouton "Mes projets"] → ProjectListScreen
                                            ↓
                                   [Clic sur projet]
                                            ↓
                                   ProjectDetailsScreen
                                            ↓
                                   [Voir devis]
                                            ↓
                                   QuoteReviewScreen
```

**Avec Bottom Nav** (recommandé):
```
Bottom Nav → [Onglet Projets] → ProjectListScreen
```

---

## 🚀 Prochaines Étapes

1. **Ajouter le bouton "Mes projets" dans HomeScreen**
2. **Implémenter la Bottom Navigation Bar**
3. **Configurer les notifications push**
4. **Ajouter les badges de compteur**
5. **Tester le flux complet client**

---

**Document créé le**: 20 février 2026
**Version**: 1.0
**Projet**: ProsArtisan - Guide d'accès aux projets client
