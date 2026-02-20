# Guide de Test - Application Mobile ProsArtisan

## 📋 Vue d'Ensemble

Ce guide vous permet de tester toutes les fonctionnalités de l'application mobile avec des données de test réalistes.

## 🎯 Données de Test Disponibles

### 👥 Comptes Utilisateurs

#### Clients (5 comptes)
| Nom | Email | Téléphone | Mot de passe |
|-----|-------|-----------|--------------|
| Kouassi Yao | kouassi.yao@email.ci | +2250701234567 | password123 |
| Koné Aminata | kone.aminata@email.ci | +2250702345678 | password123 |
| Traoré Seydou | traore.seydou@email.ci | +2250703456789 | password123 |
| Bamba Marie | bamba.marie@email.ci | +2250704567890 | password123 |
| Ouattara Ibrahim | ouattara.ibrahim@email.ci | +2250705678901 | password123 |

#### Artisans (10 comptes)

| Nom | Email | Métier | Zone | Expérience | Score | Avis |
|-----|-------|--------|------|------------|-------|------|
| Koné Adama | kone.adama@artisan.ci | Maçon | Cocody | 5 ans | - | 0 |
| Yao Jean-Claude | yao.jeanclaude@artisan.ci | Électricien | Plateau | 8 ans | - | 0 |
| Touré Mamadou | toure.mamadou@artisan.ci | Plombier | Marcory | 6 ans | - | 0 |
| Diallo Abdoulaye | diallo.abdoulaye@artisan.ci | Menuisier | Adjamé | 10 ans | - | 0 |
| **Coulibaly Moussa** | coulibaly.moussa@artisan.ci | Peintre | Treichville | 4 ans | **100** | **1 projet** |
| Bah Ibrahima | bah.ibrahima@artisan.ci | Carreleur | Yopougon | 7 ans | - | 0 |
| Fofana Lassina | fofana.lassina@artisan.ci | Soudeur | Abobo | 9 ans | - | 0 |
| Sanogo Souleymane | sanogo.souleymane@artisan.ci | Mécanicien | Koumassi | 12 ans | - | 0 |
| **Doumbia Bakary** | doumbia.bakary@artisan.ci | Climaticien | Cocody II | 5 ans | **99** | **1 projet** |
| Ouédraogo Rasmané | ouedraogo.rasmane@artisan.ci | Jardinier | Riviera | 3 ans | - | 0 |

**Note:** Les artisans en gras ont des projets complétés et des avis.

#### Fournisseurs (3 comptes)
| Nom | Email | Téléphone |
|-----|-------|-----------|
| Quincaillerie Yopougon | quincaillerie.yopougon@vendor.ci | +2250721234567 |
| Matériaux du Centre | materiaux.centre@vendor.ci | +2250722345678 |
| Bâti Pro Abidjan | batipro.abidjan@vendor.ci | +2250723456789 |

### 📍 Coordonnées GPS des Artisans

Pour tester la recherche par proximité, voici les coordonnées:

| Artisan | Latitude | Longitude | Zone |
|---------|----------|-----------|------|
| Koné Adama | 5.3364 | -4.0267 | Cocody |
| Yao Jean-Claude | 5.3333 | -4.0333 | Plateau |
| Touré Mamadou | 5.3500 | -4.0100 | Marcory |
| Diallo Abdoulaye | 5.3200 | -4.0500 | Adjamé |
| Coulibaly Moussa | 5.3400 | -4.0200 | Treichville |
| Bah Ibrahima | 5.3450 | -4.0350 | Yopougon |
| Fofana Lassina | 5.3300 | -4.0400 | Abobo |
| Sanogo Souleymane | 5.3250 | -4.0250 | Koumassi |
| Doumbia Bakary | 5.3380 | -4.0280 | Cocody II |
| Ouédraogo Rasmané | 5.3420 | -4.0320 | Riviera |

**Centre d'Abidjan:** Latitude: 5.3364, Longitude: -4.0267

## 🧪 Scénarios de Test

### 1. Test de Connexion

#### Test 1.1: Connexion Client
```
Email: kouassi.yao@email.ci
Mot de passe: password123
```
**Résultat attendu:** Connexion réussie, redirection vers l'écran d'accueil client

#### Test 1.2: Connexion Artisan
```
Email: coulibaly.moussa@artisan.ci
Mot de passe: password123
```
**Résultat attendu:** Connexion réussie, redirection vers le tableau de bord artisan

### 2. Test de l'Écran d'Accueil

#### Test 2.1: Affichage des Catégories
**Étapes:**
1. Se connecter en tant que client
2. Observer l'écran d'accueil

**Résultat attendu:**
- ✅ 12 catégories de secteurs affichées en grille 3x3
- ✅ Icônes et couleurs différentes pour chaque secteur
- ✅ Noms des secteurs visibles

**Catégories à vérifier:**
1. MÉCANIQUE & AUTOMOBILE (Indigo)
2. ÉLECTRICITÉ & ÉNERGIE (Orange)
3. PLOMBERIE & FLUIDES (Bleu clair)
4. BÂTIMENT & TRAVAUX PUBLICS (Bleu)
5. MENUISERIE & BOIS (Marron)
6. MÉTALLURGIE & SOUDURE (Gris)
7. ARTISANAT & MÉTIERS CRÉATIFS (Rose)
8. NUMÉRIQUE & TECHNIQUE (Violet)
9. FROID, CLIMATISATION (Cyan)
10. SERVICES & MÉTIERS (Vert)
11. SÉCURITÉ & INSTALLATION (Rouge)
12. ASSAINISSEMENT & EAU (Teal)

#### Test 2.2: Carte Promotionnelle "Artisans à Proximité"
**Étapes:**
1. Autoriser l'accès à la localisation
2. Observer la carte promotionnelle

**Résultat attendu:**
- ✅ Carte affichée avec gradient bleu
- ✅ Nombre d'artisans à proximité (<2km)
- ✅ Icône de localisation dorée
- ✅ Texte "À moins de 2km de vous"

**Note:** Le nombre dépend de votre position GPS simulée

### 3. Test de Recherche d'Artisans

#### Test 3.1: Recherche par Secteur
**Étapes:**
1. Cliquer sur "BÂTIMENT & TRAVAUX PUBLICS"
2. Observer les résultats

**Résultat attendu:**
- ✅ Filtrage appliqué au secteur BTP
- ✅ Affichage de Koné Adama (Maçon) et Bah Ibrahima (Carreleur)
- ✅ Informations visibles: nom, métier, zone, distance

#### Test 3.2: Recherche par Proximité
**Étapes:**
1. Cliquer sur "Voir sur la carte"
2. Observer la carte avec marqueurs

**Résultat attendu:**
- ✅ Carte Yandex affichée
- ✅ 10 marqueurs pour les 10 artisans
- ✅ Marqueurs dorés pour artisans <2km
- ✅ Marqueurs bleus pour artisans >2km

#### Test 3.3: Recherche avec Filtres
**Étapes:**
1. Cliquer sur l'icône de filtre (tune)
2. Sélectionner "MÉCANIQUE & AUTOMOBILE"
3. Appliquer le filtre

**Résultat attendu:**
- ✅ 4 artisans affichés (Jean-Claude, Moussa, Souleymane, Bakary)
- ✅ Tous du secteur automobile

#### Test 3.4: Tri par Note
**Étapes:**
1. Ouvrir les filtres
2. Sélectionner "Tri par: Note"
3. Appliquer

**Résultat attendu:**
- ✅ Coulibaly Moussa (5⭐) en premier
- ✅ Doumbia Bakary (4⭐) en deuxième
- ✅ Autres artisans sans note après

### 4. Test du Profil Artisan

#### Test 4.1: Profil avec Avis (Coulibaly Moussa)
**Étapes:**
1. Rechercher "Peintre" ou secteur "MÉCANIQUE & AUTOMOBILE"
2. Cliquer sur "Coulibaly Moussa"

**Résultat attendu:**
- ✅ Avatar et nom affichés
- ✅ Métier: Peintre Automobile
- ✅ Note: 5.0 ⭐
- ✅ Projets complétés: 1
- ✅ Score N'Zassa: 100
- ✅ Expérience: 4 ans
- ✅ Zone: Treichville, Abidjan
- ✅ Badge "Disponible pour de nouveaux projets"
- ✅ Boutons "Demander un devis" et "Envoyer un message"

#### Test 4.2: Profil sans Avis (Koné Adama)
**Étapes:**
1. Rechercher "Maçon"
2. Cliquer sur "Koné Adama"

**Résultat attendu:**
- ✅ Informations de base affichées
- ✅ Note: N/A
- ✅ Projets: 0
- ✅ Score: N/A
- ✅ Expérience: 5 ans

### 5. Test de la Carte Interactive

#### Test 5.1: Navigation sur la Carte
**Étapes:**
1. Aller sur "Voir sur la carte"
2. Zoomer/dézoomer
3. Déplacer la carte

**Résultat attendu:**
- ✅ Carte fluide et réactive
- ✅ Marqueurs visibles à tous les niveaux de zoom
- ✅ Bouton "Ma position" fonctionnel

#### Test 5.2: Clic sur Marqueur
**Étapes:**
1. Sur la carte, cliquer sur un marqueur
2. Observer le bottom sheet

**Résultat attendu:**
- ✅ Bottom sheet s'affiche
- ✅ Photo, nom, métier visibles
- ✅ Distance affichée
- ✅ Bouton "Voir le profil" fonctionnel

#### Test 5.3: Vue Liste
**Étapes:**
1. Sur la carte, cliquer sur le bouton "X artisans"
2. Observer la liste

**Résultat attendu:**
- ✅ Liste overlay s'affiche
- ✅ Tous les artisans listés
- ✅ Scroll fonctionnel
- ✅ Clic sur un artisan ouvre son profil

### 6. Test des Projets Complétés

#### Test 6.1: Projet de Peinture (Coulibaly Moussa)
**Données du projet:**
- Client: Kouassi Yao
- Artisan: Coulibaly Moussa
- Titre: Peinture complète maison
- Montant: 1,200,000 XOF
- Statut: Complété
- Avis: 5⭐

**Vérifications:**
- ✅ 3 jalons validés
- ✅ Token matériel utilisé (500,000 XOF)
- ✅ Paiements main-d'œuvre effectués (700,000 XOF)
- ✅ Avis client positif

#### Test 6.2: Projet de Climatisation (Doumbia Bakary)
**Données du projet:**
- Client: Koné Aminata
- Artisan: Doumbia Bakary
- Titre: Installation climatisation
- Montant: 1,800,000 XOF
- Statut: Complété
- Avis: 4⭐

**Vérifications:**
- ✅ 1 jalon validé
- ✅ Token matériel utilisé (1,200,000 XOF)
- ✅ Paiement main-d'œuvre effectué (600,000 XOF)
- ✅ Avis client avec commentaire

### 7. Test des Devis

#### Test 7.1: Devis Installation Électrique
**Projet:** Installation électrique complète
**Artisan:** Yao Jean-Claude
**Montant:** 850,000 XOF
**Statut:** Envoyé

**Détails:**
- Matériel: 400,000 XOF (47%)
- Main-d'œuvre: 450,000 XOF (53%)
- 4 lignes de devis

#### Test 7.2: Devis Réparation Plomberie
**Projet:** Réparation plomberie
**Artisan:** Touré Mamadou
**Montant:** 125,000 XOF
**Statut:** Envoyé

#### Test 7.3: Devis Meubles
**Projet:** Fabrication de meubles sur mesure
**Artisan:** Diallo Abdoulaye
**Montant:** 550,000 XOF
**Statut:** Envoyé

## 🔍 Critères de Validation

### Écran d'Accueil
- [ ] Les 12 catégories s'affichent correctement
- [ ] Les icônes et couleurs sont distinctes
- [ ] La carte promotionnelle affiche le bon nombre d'artisans
- [ ] Le pull-to-refresh fonctionne
- [ ] La barre de recherche est cliquable

### Recherche d'Artisans
- [ ] La recherche par secteur fonctionne
- [ ] La recherche par proximité fonctionne
- [ ] Les filtres s'appliquent correctement
- [ ] Le tri fonctionne (distance, note, expérience)
- [ ] Les artisans avec score/avis sont mis en avant

### Carte Interactive
- [ ] La carte se charge correctement
- [ ] Les marqueurs s'affichent
- [ ] Les marqueurs dorés (<2km) sont distincts
- [ ] Le clic sur marqueur ouvre le bottom sheet
- [ ] La vue liste fonctionne
- [ ] Le bouton "Ma position" recentre la carte

### Profil Artisan
- [ ] Toutes les informations s'affichent
- [ ] Les statistiques sont correctes
- [ ] Les badges (score, disponibilité) s'affichent
- [ ] Les boutons d'action fonctionnent
- [ ] La distance est calculée correctement

### Performance
- [ ] Temps de chargement <3 secondes
- [ ] Scroll fluide
- [ ] Pas de crash
- [ ] Gestion des erreurs réseau
- [ ] Messages d'erreur clairs

## 🐛 Problèmes Connus et Solutions

### Problème: Aucun artisan affiché
**Cause:** Position GPS non disponible
**Solution:** 
1. Vérifier les permissions de localisation
2. Utiliser un émulateur avec GPS simulé
3. Coordonnées de test: 5.3364, -4.0267 (Abidjan)

### Problème: Erreur 404 sur recherche
**Cause:** Cache Flutter
**Solution:** 
```bash
# Hot Restart (R majuscule dans le terminal)
R
```

### Problème: Catégories vides
**Cause:** Seeder non exécuté
**Solution:**
```bash
cd backend
php artisan db:seed --class=SectorsTradesSeeder
php artisan db:seed --class=TestDataSeeder
```

### Problème: Scores N'Zassa manquants
**Cause:** Scores calculés uniquement pour artisans avec projets complétés
**Solution:** Normal - seuls Moussa et Bakary ont des scores

## 📊 Données Attendues

### Statistiques Globales
- **Total artisans:** 10
- **Artisans avec score:** 2 (Moussa: 100, Bakary: 99)
- **Artisans avec avis:** 2
- **Projets complétés:** 2
- **Projets en attente:** 5
- **Devis envoyés:** 3

### Répartition par Secteur
- MÉCANIQUE & AUTOMOBILE: 4 artisans
- BÂTIMENT & TRAVAUX PUBLICS: 2 artisans
- PLOMBERIE & FLUIDES: 1 artisan
- MENUISERIE & BOIS: 1 artisan
- MÉTALLURGIE & SOUDURE: 1 artisan
- SERVICES & MÉTIERS: 1 artisan

## 🎯 Checklist de Test Complète

### Phase 1: Authentification
- [ ] Connexion client réussie
- [ ] Connexion artisan réussie
- [ ] Déconnexion fonctionne
- [ ] Session persistante

### Phase 2: Navigation
- [ ] Écran d'accueil charge
- [ ] Navigation vers recherche
- [ ] Navigation vers carte
- [ ] Navigation vers profil artisan
- [ ] Retour arrière fonctionne

### Phase 3: Recherche
- [ ] Recherche par secteur
- [ ] Recherche par proximité
- [ ] Filtres multiples
- [ ] Tri par critères
- [ ] Résultats corrects

### Phase 4: Affichage
- [ ] Cartes artisans
- [ ] Profils détaillés
- [ ] Statistiques
- [ ] Badges et scores
- [ ] Images/avatars

### Phase 5: Interactions
- [ ] Clic sur catégorie
- [ ] Clic sur artisan
- [ ] Clic sur marqueur carte
- [ ] Pull-to-refresh
- [ ] Boutons d'action

## 📝 Notes pour le Développement

### Améliorations Suggérées
1. Ajouter plus d'artisans avec avis
2. Créer des photos de profil réalistes
3. Ajouter des photos de projets complétés
4. Implémenter le cache local
5. Ajouter des animations de chargement

### Données Manquantes
- Photos de profil (utilise icône par défaut)
- Photos de projets
- Certifications artisans
- Historique de prix

## 🚀 Commandes Utiles

### Réinitialiser les Données
```bash
cd backend
php artisan migrate:fresh --seed
php artisan db:seed --class=TestDataSeeder
```

### Vérifier les Artisans
```bash
php artisan tinker
User::where('role', 'artisan')->count();
```

### Tester l'API
```bash
curl -X POST http://localhost:8000/api/v1/artisans/search \
  -H "Content-Type: application/json" \
  -d '{"latitude": 5.3364, "longitude": -4.0267, "radius": 10000}'
```

## ✅ Conclusion

Avec ces données de test, vous pouvez valider:
- ✅ Affichage des catégories depuis la BD
- ✅ Recherche d'artisans par localisation
- ✅ Filtrage par secteur/métier
- ✅ Affichage des profils avec statistiques
- ✅ Système de notation et avis
- ✅ Score N'Zassa
- ✅ Projets complétés avec workflow complet

**Bon test! 🎉**
