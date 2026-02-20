# Guide de Test - Filtre Métiers par Secteur

## 🎯 Objectif
Tester le filtre de recherche qui affiche les métiers en fonction du secteur d'activité sélectionné.

## 🚀 Préparation

### 1. Hot Restart Flutter
```bash
# Dans le terminal Flutter, appuyez sur:
R  # (majuscule) pour vider le cache
```

### 2. Se Connecter
```
Email: kouassi.yao@email.ci
Mot de passe: password123
```

## 📋 Scénarios de Test

### Test 1: Affichage Initial ✅
**Étapes:**
1. Aller sur l'écran d'accueil
2. Cliquer sur la barre de recherche OU cliquer sur une catégorie

**Résultat attendu:**
- ✅ Écran de filtre s'ouvre
- ✅ Dropdown "Secteur d'activité" visible
- ✅ Section "Métier" masquée (car aucun secteur sélectionné)
- ✅ Autres filtres visibles (rayon, score, tri)

---

### Test 2: Sélection d'un Secteur ✅
**Étapes:**
1. Ouvrir le dropdown "Secteur d'activité"
2. Sélectionner "BÂTIMENT & TRAVAUX PUBLICS"

**Résultat attendu:**
- ✅ Indicateur de chargement "Chargement des métiers..." s'affiche brièvement
- ✅ Section "Métier" devient visible
- ✅ Dropdown "Métier" affiche "Tous les métiers du secteur"
- ✅ Liste des métiers disponibles:
  - Tous les métiers du secteur
  - Maçon
  - Carreleur

---

### Test 3: Recherche par Secteur (sans métier spécifique) ✅
**Étapes:**
1. Sélectionner "BÂTIMENT & TRAVAUX PUBLICS"
2. Laisser "Tous les métiers du secteur" sélectionné
3. Cliquer sur "Rechercher"

**Résultat attendu:**
- ✅ Recherche lancée
- ✅ Résultats affichent TOUS les artisans du secteur BTP
- ✅ Devrait afficher: Koné Adama (Maçon) et Bah Ibrahima (Carreleur)

---

### Test 4: Recherche par Métier Spécifique ✅
**Étapes:**
1. Sélectionner "BÂTIMENT & TRAVAUX PUBLICS"
2. Sélectionner "Maçon" dans le dropdown métier
3. Cliquer sur "Rechercher"

**Résultat attendu:**
- ✅ Recherche lancée
- ✅ Résultats affichent UNIQUEMENT les maçons
- ✅ Devrait afficher: Koné Adama (Maçon)
- ✅ Bah Ibrahima (Carreleur) ne doit PAS apparaître

---

### Test 5: Changement de Secteur (Réinitialisation Automatique) ✅
**Étapes:**
1. Sélectionner "BÂTIMENT & TRAVAUX PUBLICS"
2. Sélectionner "Maçon"
3. Changer le secteur pour "ÉLECTRICITÉ & ÉNERGIE"

**Résultat attendu:**
- ✅ Indicateur de chargement s'affiche
- ✅ Le métier "Maçon" est automatiquement désélectionné
- ✅ Dropdown métier revient à "Tous les métiers du secteur"
- ✅ Nouveaux métiers du secteur électricité sont chargés:
  - Tous les métiers du secteur
  - Électricien Bâtiment

---

### Test 6: Recherche Électricité ✅
**Étapes:**
1. Sélectionner "ÉLECTRICITÉ & ÉNERGIE"
2. Laisser "Tous les métiers du secteur"
3. Cliquer sur "Rechercher"

**Résultat attendu:**
- ✅ Résultats affichent: Yao Jean-Claude (Électricien)

---

### Test 7: Réinitialisation des Filtres ✅
**Étapes:**
1. Sélectionner un secteur et un métier
2. Modifier le rayon de recherche
3. Cliquer sur "Réinitialiser"

**Résultat attendu:**
- ✅ Secteur revient à "Tous les secteurs"
- ✅ Section métier disparaît
- ✅ Rayon revient à 10 km
- ✅ Score revient à "Pas de filtre"
- ✅ Tri revient à "Distance"

---

### Test 8: Tous les Secteurs avec Métiers ✅

#### MÉCANIQUE & AUTOMOBILE
**Métiers attendus:**
- Mécanicien Auto
- Peintre Automobile
- Électricien Auto

**Artisans:**
- Sanogo Souleymane (Mécanicien)
- Coulibaly Moussa (Peintre Auto)
- Yao Jean-Claude (Électricien Auto)
- Doumbia Bakary (Climaticien)

#### PLOMBERIE & FLUIDES
**Métiers attendus:**
- Plombier

**Artisans:**
- Touré Mamadou (Plombier)

#### MENUISERIE & BOIS
**Métiers attendus:**
- Menuisier

**Artisans:**
- Diallo Abdoulaye (Menuisier)

#### MÉTALLURGIE & SOUDURE
**Métiers attendus:**
- Soudeur

**Artisans:**
- Fofana Lassina (Soudeur)

#### SERVICES & MÉTIERS
**Métiers attendus:**
- Jardinier

**Artisans:**
- Ouédraogo Rasmané (Jardinier)

---

### Test 9: Combinaison de Filtres ✅
**Étapes:**
1. Sélectionner "MÉCANIQUE & AUTOMOBILE"
2. Sélectionner "Peintre Automobile"
3. Rayon: 5 km
4. Score minimum: 50+ points
5. Tri: Note
6. Cliquer sur "Rechercher"

**Résultat attendu:**
- ✅ Résultats filtrés par tous les critères
- ✅ Devrait afficher: Coulibaly Moussa (Score: 100, Note: 5⭐)
- ✅ Trié par note (meilleure note en premier)

---

### Test 10: Aucun Résultat ✅
**Étapes:**
1. Sélectionner un secteur
2. Rayon: 1 km (très petit)
3. Score minimum: 85+ points
4. Cliquer sur "Rechercher"

**Résultat attendu:**
- ✅ Message "Aucun artisan trouvé avec ces critères"
- ✅ Snackbar orange/warning s'affiche
- ✅ Pas de crash

---

## 🎨 Vérifications Visuelles

### Design System
- [ ] Couleurs dark theme appliquées
- [ ] Background: #252B48 (darkCard)
- [ ] Texte blanc/gris clair
- [ ] Bordures subtiles
- [ ] Border radius: 12px

### Indicateurs de Chargement
- [ ] Spinner bleu (#5B7FFF) pendant le chargement
- [ ] Texte "Chargement des métiers..." visible
- [ ] Animation fluide

### Messages Informatifs
- [ ] Icône info (ℹ️) pour "Aucun métier disponible"
- [ ] Texte gris secondaire
- [ ] Bien aligné et lisible

### Dropdowns
- [ ] Icônes appropriées (category, work)
- [ ] Placeholder clair
- [ ] Options bien formatées
- [ ] Scroll si beaucoup d'options

---

## 🐛 Cas Limites à Tester

### Cas 1: Secteur sans Métiers
Si un secteur n'a pas de métiers dans la base de données:
- ✅ Message "Aucun métier disponible pour ce secteur"
- ✅ Pas de crash
- ✅ Recherche possible quand même (par secteur)

### Cas 2: Connexion Lente
Si le réseau est lent:
- ✅ Indicateur de chargement visible plus longtemps
- ✅ Interface reste responsive
- ✅ Pas de double-clic possible sur "Rechercher"

### Cas 3: Erreur Réseau
Si pas de connexion:
- ✅ Message d'erreur approprié
- ✅ Possibilité de réessayer
- ✅ Pas de crash

---

## ✅ Checklist Complète

### Interface
- [ ] Dropdown secteur fonctionne
- [ ] Dropdown métier apparaît/disparaît correctement
- [ ] Indicateurs de chargement s'affichent
- [ ] Messages informatifs clairs
- [ ] Boutons "Réinitialiser" et "Rechercher" fonctionnent

### Logique
- [ ] Métiers chargés automatiquement après sélection secteur
- [ ] Métier réinitialisé quand on change de secteur
- [ ] Recherche par secteur seul fonctionne
- [ ] Recherche par métier spécifique fonctionne
- [ ] Réinitialisation efface tout

### Résultats
- [ ] Résultats corrects pour chaque secteur
- [ ] Résultats corrects pour chaque métier
- [ ] Filtres combinés fonctionnent
- [ ] Tri appliqué correctement
- [ ] Distance calculée correctement

### Performance
- [ ] Chargement rapide (<2 secondes)
- [ ] Pas de lag lors du changement de secteur
- [ ] Scroll fluide
- [ ] Pas de crash

---

## 📊 Résultats Attendus par Secteur

| Secteur | Nb Métiers | Nb Artisans |
|---------|-----------|-------------|
| MÉCANIQUE & AUTOMOBILE | 3 | 4 |
| ÉLECTRICITÉ & ÉNERGIE | 1 | 1 |
| PLOMBERIE & FLUIDES | 1 | 1 |
| BÂTIMENT & TRAVAUX PUBLICS | 2 | 2 |
| MENUISERIE & BOIS | 1 | 1 |
| MÉTALLURGIE & SOUDURE | 1 | 1 |
| FROID, CLIMATISATION | 1 | 1 |
| SERVICES & MÉTIERS | 1 | 1 |

**Total:** 11 métiers, 10 artisans

---

## 🎯 Critères de Succès

✅ **SUCCÈS** si:
1. Tous les secteurs affichent leurs métiers
2. Changement de secteur réinitialise le métier
3. Recherche par secteur retourne tous les artisans du secteur
4. Recherche par métier retourne uniquement ce métier
5. Réinitialisation efface tous les filtres
6. Aucun crash ou erreur
7. Interface fluide et responsive
8. Design system respecté

❌ **ÉCHEC** si:
1. Métiers ne se chargent pas
2. Métier reste sélectionné après changement de secteur
3. Résultats incorrects
4. Crash ou erreur
5. Interface bloquée

---

## 📝 Rapport de Test

Après avoir effectué tous les tests, remplir:

**Date:** _______________
**Testeur:** _______________
**Version:** _______________

**Tests Réussis:** ___ / 10
**Tests Échoués:** ___ / 10

**Problèmes Rencontrés:**
- 
- 
- 

**Commentaires:**
- 
- 
- 

---

**Bon test! 🚀**
