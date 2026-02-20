# 🧪 Guide de Test - Bottom Navigation Bar

## 🚀 Préparation

### 1. Hot Restart
```bash
# Dans le terminal Flutter, appuyez sur:
R  # (majuscule) pour vider le cache et recharger
```

### 2. Compte de Test
```
Email: kouassi.yao@email.ci
Mot de passe: password123
Rôle: Client
```

---

## ✅ Tests de Base

### Test 1: Affichage Initial
**Objectif**: Vérifier que la bottom bar s'affiche correctement

**Étapes**:
1. Se connecter avec le compte client
2. Observer l'écran d'accueil

**Résultat attendu**:
```
┌─────────────────────────────────┐
│ 🏠 ProsArtisan          🔔  👤  │
│                                 │
│ Bonjour Kouassi! 👋             │
│                                 │
│ [Catégories affichées]          │
│                                 │
└─────────────────────────────────┘
┌─────┬─────┬─────┬─────┬─────┐
│ 🏠  │ 🔍  │ 📋  │ 💬  │ 👤  │
│Accue│Rech.│Proj.│Mess.│Prof.│ ← Bottom Bar
│ il  │     │     │     │     │
└─────┴─────┴─────┴─────┴─────┘
```

**Vérifications**:
- [ ] Bottom bar visible en bas de l'écran
- [ ] 5 onglets affichés
- [ ] Onglet "Accueil" actif (bleu)
- [ ] Autres onglets inactifs (gris)
- [ ] Labels lisibles
- [ ] Icônes correctes

---

### Test 2: Navigation Entre Onglets
**Objectif**: Vérifier que la navigation fonctionne

**Étapes**:
1. Depuis l'accueil, taper sur "Recherche"
2. Observer le changement
3. Taper sur "Projets"
4. Taper sur "Profil"
5. Revenir sur "Accueil"

**Résultat attendu**:
- [ ] Écran change instantanément
- [ ] Icône devient "filled" (pleine) pour l'onglet actif
- [ ] Couleur bleue (#5B7FFF) pour l'actif
- [ ] Pas de lag ou freeze
- [ ] Transition fluide

**Timing**: Chaque changement < 100ms

---

### Test 3: Préservation de l'État
**Objectif**: Vérifier que l'état est préservé

**Étapes**:
1. Aller sur "Recherche"
2. Sélectionner "BÂTIMENT & TRAVAUX PUBLICS"
3. Sélectionner "Maçon"
4. Aller sur "Accueil"
5. Revenir sur "Recherche"

**Résultat attendu**:
- [ ] Secteur "BÂTIMENT & TRAVAUX PUBLICS" toujours sélectionné
- [ ] Métier "Maçon" toujours sélectionné
- [ ] Pas de réinitialisation des filtres
- [ ] Scroll position maintenue

---

### Test 4: Onglet Projets
**Objectif**: Vérifier l'accès aux projets

**Étapes**:
1. Taper sur l'onglet "Projets" (📋)
2. Observer l'écran

**Résultat attendu**:
```
┌─────────────────────────────────┐
│ ← Mes projets                   │
├─────────────────────────────────┤
│ Tous │ En attente │ En cours │ Terminés │
├─────────────────────────────────┤
│                                 │
│ [Liste des projets]             │
│                                 │
│                      ┌────────┐ │
│                      │ + Nouveau│
│                      │  projet │ │
│                      └────────┘ │
└─────────────────────────────────┘
┌─────┬─────┬─────┬─────┬─────┐
│ 🏠  │ 🔍  │ 📋  │ 💬  │ 👤  │
│     │     │Proj.│     │     │ ← Actif
└─────┴─────┴─────┴─────┴─────┘
```

**Vérifications**:
- [ ] Écran "Mes projets" s'affiche
- [ ] Onglets de filtrage visibles
- [ ] Bottom bar reste visible
- [ ] Onglet "Projets" actif (bleu)

---

### Test 5: Onglet Profil
**Objectif**: Vérifier l'écran de profil

**Étapes**:
1. Taper sur l'onglet "Profil" (👤)
2. Observer l'écran

**Résultat attendu**:
```
┌─────────────────────────────────┐
│         [Gradient Bleu]         │
│                                 │
│            👤                   │
│                                 │
│       Kouassi Yao               │
│         [Client]                │
│                                 │
├─────────────────────────────────┤
│ Mon compte                      │
│ ┌─────────────────────────────┐ │
│ │ 👤 Informations personnelles│ │
│ │ ✉️  Email                   │ │
│ │ 📱 Téléphone                │ │
│ └─────────────────────────────┘ │
│                                 │
│ Paramètres                      │
│ Support                         │
│                                 │
│ [Déconnexion]                   │
└─────────────────────────────────┘
┌─────┬─────┬─────┬─────┬─────┐
│ 🏠  │ 🔍  │ 📋  │ 💬  │ 👤  │
│     │     │     │     │Prof.│ ← Actif
└─────┴─────┴─────┴─────┴─────┘
```

**Vérifications**:
- [ ] Avatar affiché (ou icône par défaut)
- [ ] Nom "Kouassi Yao" visible
- [ ] Badge "Client" affiché
- [ ] Sections "Mon compte", "Paramètres", "Support"
- [ ] Bouton "Déconnexion" visible
- [ ] Bottom bar reste visible

---

### Test 6: Onglet Messages (Placeholder)
**Objectif**: Vérifier le placeholder

**Étapes**:
1. Taper sur l'onglet "Messages" (💬)
2. Observer l'écran

**Résultat attendu**:
```
┌─────────────────────────────────┐
│                                 │
│                                 │
│           💬                    │
│                                 │
│         Messages                │
│                                 │
│   Fonctionnalité à venir        │
│                                 │
│                                 │
└─────────────────────────────────┘
┌─────┬─────┬─────┬─────┬─────┐
│ 🏠  │ 🔍  │ 📋  │ 💬  │ 👤  │
│     │     │     │Mess.│     │ ← Actif
└─────┴─────┴─────┴─────┴─────┘
```

**Vérifications**:
- [ ] Écran placeholder affiché
- [ ] Icône message visible
- [ ] Texte "Fonctionnalité à venir"
- [ ] Bottom bar reste visible

---

## 🔄 Tests de Navigation Profonde

### Test 7: Navigation dans Projets
**Objectif**: Vérifier que la bottom bar reste visible

**Étapes**:
1. Aller sur "Projets"
2. Créer un nouveau projet (ou cliquer sur un existant)
3. Observer l'écran de détails
4. Revenir en arrière

**Résultat attendu**:
- [ ] Bottom bar visible sur l'écran de liste
- [ ] Bottom bar visible sur l'écran de détails
- [ ] Retour arrière fonctionne
- [ ] Onglet "Projets" reste actif

---

### Test 8: Navigation dans Recherche
**Objectif**: Vérifier la navigation profonde

**Étapes**:
1. Aller sur "Recherche"
2. Sélectionner un secteur
3. Lancer la recherche
4. Cliquer sur un artisan
5. Observer le profil
6. Revenir en arrière

**Résultat attendu**:
- [ ] Bottom bar visible sur tous les écrans
- [ ] Navigation fluide
- [ ] Retour arrière fonctionne
- [ ] État préservé

---

## 🎨 Tests Visuels

### Test 9: Design System
**Objectif**: Vérifier l'application du design system

**Vérifications**:

**Couleurs**:
- [ ] Fond bottom bar: #2A3354 (dark card)
- [ ] Onglet actif: #5B7FFF (accent primary)
- [ ] Onglets inactifs: #7A8AA8 (text tertiary)

**Typographie**:
- [ ] Labels: 12px
- [ ] Actif: Semibold (600)
- [ ] Inactif: Medium (500)

**Espacements**:
- [ ] Border radius: 16px (top corners)
- [ ] Hauteur: ~64px
- [ ] Espacement entre items: Uniforme

**Icônes**:
- [ ] Actif: Filled (pleine)
- [ ] Inactif: Outlined (contour)
- [ ] Taille: 24px

---

### Test 10: Responsive
**Objectif**: Vérifier sur différentes tailles

**Tailles à tester**:
- [ ] Petit écran (320px width)
- [ ] Moyen écran (375px width)
- [ ] Grand écran (428px width)

**Vérifications**:
- [ ] Labels toujours lisibles
- [ ] Icônes bien espacées
- [ ] Pas de débordement
- [ ] Touch targets ≥ 44px

---

## ⚡ Tests de Performance

### Test 11: Rapidité de Navigation
**Objectif**: Mesurer la performance

**Étapes**:
1. Taper rapidement sur tous les onglets
2. Observer les transitions

**Résultat attendu**:
- [ ] Changement instantané (< 100ms)
- [ ] Pas de lag
- [ ] Pas de freeze
- [ ] 60 FPS constant

---

### Test 12: Mémoire
**Objectif**: Vérifier l'utilisation mémoire

**Étapes**:
1. Naviguer entre tous les onglets plusieurs fois
2. Observer la consommation mémoire

**Résultat attendu**:
- [ ] Pas de fuite mémoire
- [ ] Consommation stable
- [ ] Pas de crash

---

## 🔐 Tests de Sécurité

### Test 13: Déconnexion
**Objectif**: Vérifier la déconnexion

**Étapes**:
1. Aller sur "Profil"
2. Cliquer sur "Déconnexion"
3. Confirmer dans le dialogue
4. Observer la redirection

**Résultat attendu**:
- [ ] Dialogue de confirmation s'affiche
- [ ] Bouton "Annuler" fonctionne
- [ ] Bouton "Déconnexion" fonctionne
- [ ] Redirection vers LoginScreen
- [ ] Session nettoyée
- [ ] Impossible de revenir en arrière

---

## 🐛 Tests d'Erreurs

### Test 14: Connexion Perdue
**Objectif**: Vérifier le comportement sans connexion

**Étapes**:
1. Désactiver le WiFi/données
2. Naviguer entre onglets
3. Essayer de charger des données

**Résultat attendu**:
- [ ] Navigation locale fonctionne
- [ ] Messages d'erreur appropriés
- [ ] Pas de crash
- [ ] Possibilité de réessayer

---

## 📊 Checklist Complète

### Fonctionnalités
- [ ] 5 onglets affichés
- [ ] Navigation fonctionne
- [ ] État préservé
- [ ] Icônes changent (outlined/filled)
- [ ] Couleurs correctes
- [ ] Labels lisibles

### Design
- [ ] Border radius 16px
- [ ] Couleurs du design system
- [ ] Typographie correcte
- [ ] Espacements uniformes
- [ ] Ombre subtile

### Performance
- [ ] Transitions < 100ms
- [ ] 60 FPS constant
- [ ] Pas de lag
- [ ] Mémoire stable

### Accessibilité
- [ ] Touch targets ≥ 44px
- [ ] Contraste suffisant
- [ ] Labels descriptifs
- [ ] Navigation au clavier (web)

### Sécurité
- [ ] Déconnexion fonctionne
- [ ] Session nettoyée
- [ ] Pas de retour arrière après logout

---

## 🎯 Critères de Succès

✅ **SUCCÈS** si:
1. Tous les onglets fonctionnent
2. Navigation fluide et rapide
3. État préservé entre onglets
4. Design system respecté
5. Aucun crash ou erreur
6. Performance optimale (60 FPS)

❌ **ÉCHEC** si:
1. Bottom bar ne s'affiche pas
2. Navigation ne fonctionne pas
3. État perdu entre onglets
4. Couleurs incorrectes
5. Crash ou freeze
6. Performance dégradée

---

## 📝 Rapport de Test

**Date**: _______________
**Testeur**: _______________
**Version**: 1.0.0

**Tests Réussis**: ___ / 14
**Tests Échoués**: ___ / 14

**Problèmes Rencontrés**:
- 
- 
- 

**Commentaires**:
- 
- 
- 

**Recommandations**:
- 
- 
- 

---

**Bon test! La Bottom Navigation Bar devrait fonctionner parfaitement! 🚀**
