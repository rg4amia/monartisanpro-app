# Intégration API - HomeScreen

## Vue d'ensemble

Le `HomeScreen` a été amélioré pour être complètement connecté à l'API backend, permettant de charger dynamiquement les secteurs, métiers et artisans à proximité.

---

## Nouvelles Routes API

### Backend: `routes/api.php`

Ajout de routes publiques pour les secteurs et la recherche d'artisans:

```php
// Public routes - Sectors and Trades
Route::get('/sectors', [TradeController::class, 'getSectors']);
Route::get('/sectors/{sectorId}/trades', [TradeController::class, 'getTradesBySector']);
Route::get('/trades', [TradeController::class, 'getAllTrades']);

// Public artisan search
Route::post('/artisans/search', [TradeController::class, 'searchArtisans']);
```

---

## Nouveau Controller Backend

### `TradeController.php`

**Localisation**: `backend/app/Http/Controllers/Api/V1/TradeController.php`

#### Méthodes Implémentées

1. **getSectors()** - Récupère tous les secteurs
   - Retourne: Liste des secteurs avec nombre de métiers
   - Accès: Public
   - Endpoint: `GET /api/v1/sectors`

2. **getTradesBySector($sectorId)** - Récupère les métiers d'un secteur
   - Paramètre: ID du secteur
   - Retourne: Secteur + liste des métiers
   - Accès: Public
   - Endpoint: `GET /api/v1/sectors/{id}/trades`

3. **getAllTrades()** - Récupère tous les métiers
   - Retourne: Liste complète des métiers avec secteurs
   - Accès: Public
   - Endpoint: `GET /api/v1/trades`

4. **searchArtisans(Request)** - Recherche d'artisans géolocalisés
   - Paramètres:
     - `latitude` (required): Latitude de recherche
     - `longitude` (required): Longitude de recherche
     - `radius` (optional): Rayon en mètres (défaut: 10km)
     - `sector_id` (optional): Filtrer par secteur
     - `trade_id` (optional): Filtrer par métier
     - `min_score` (optional): Score N'Zassa minimum
     - `sort_by` (optional): distance|rating|experience
   - Retourne: Liste d'artisans avec distances calculées
   - Accès: Public
   - Endpoint: `POST /api/v1/artisans/search`

#### Fonctionnalités Clés

**Calcul de Distance (Haversine)**:
```php
private function calculateDistance($lat1, $lon1, $lat2, $lon2): float
{
    // Formule de Haversine pour calculer la distance entre 2 points GPS
    // Retourne la distance en mètres
}
```

**Formatage de Distance**:
```php
private function formatDistance(float $meters): string
{
    // < 1000m: "500 m"
    // >= 1000m: "2.5 km"
}
```

**Tri des Résultats**:
- Par distance (défaut)
- Par note moyenne
- Par années d'expérience

---

## Améliorations Frontend

### HomeScreen

#### 1. Pull-to-Refresh

Ajout d'un `RefreshIndicator` pour recharger les données:

```dart
RefreshIndicator(
  onRefresh: _refreshData,
  child: CustomScrollView(...),
)
```

#### 2. Chargement Initial

Méthode `_loadInitialData()` qui:
- Charge les secteurs depuis l'API
- Récupère la position GPS actuelle
- Recherche les artisans à proximité

```dart
Future<void> _loadInitialData() async {
  await Future.wait([
    _searchController.fetchSectors(),
    _searchController.getCurrentLocation(),
  ]);
  
  if (_searchController.currentPosition.value != null) {
    await _searchController.getNearbyArtisans();
  }
}
```

#### 3. Gestion des Erreurs

- Affichage d'un message si pas de secteurs
- Indicateur de chargement pendant les requêtes
- Gestion des erreurs de localisation

---

## SearchController

### Méthodes Existantes

Le `ArtisanSearchController` contient déjà toutes les méthodes nécessaires:

1. **fetchSectors()** - Charge les secteurs depuis l'API
2. **fetchTradesBySector(sectorId)** - Charge les métiers d'un secteur
3. **searchArtisans()** - Recherche avec filtres
4. **getNearbyArtisans()** - Artisans dans un rayon de 2km
5. **getCurrentLocation()** - Récupère la position GPS

### États Observables

```dart
final RxList<ArtisanSearchResult> searchResults;
final RxList<Sector> sectors;
final RxList<Trade> trades;
final RxBool isLoading;
final RxBool isLoadingSectors;
final Rx<Position?> currentPosition;
```

### Filtres

```dart
final Rxn<int> selectedTradeId;
final Rxn<int> selectedSectorId;
final RxDouble searchRadius; // en mètres
final Rxn<int> minScore; // Score N'Zassa minimum
final RxString sortBy; // distance, rating, experience
```

---

## Flux de Données

### Au Démarrage du HomeScreen

```
1. initState()
   ↓
2. _loadInitialData()
   ↓
3. Parallel:
   - fetchSectors() → GET /api/v1/sectors
   - getCurrentLocation() → GPS
   ↓
4. getNearbyArtisans() → POST /api/v1/artisans/search
   ↓
5. Affichage:
   - Secteurs dans la grille
   - Badge "X artisans à proximité"
```

### Pull-to-Refresh

```
1. User swipe down
   ↓
2. _refreshData()
   ↓
3. _loadInitialData()
   ↓
4. Données rechargées
```

### Clic sur une Catégorie

```
1. User tap sur secteur
   ↓
2. setSectorFilter(sectorId)
   ↓
3. fetchTradesBySector(sectorId) → GET /api/v1/sectors/{id}/trades
   ↓
4. Navigation → SearchFilterScreen
```

---

## Exemple de Réponse API

### GET /api/v1/sectors

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "BAT",
      "name": "Bâtiment",
      "trades_count": 5
    },
    {
      "id": 2,
      "code": "ELEC",
      "name": "Électricité",
      "trades_count": 3
    }
  ]
}
```

### POST /api/v1/artisans/search

**Request**:
```json
{
  "latitude": 5.3599517,
  "longitude": -4.0082563,
  "radius": 10000,
  "sort_by": "distance"
}
```

**Response**:
```json
{
  "success": true,
  "data": [
    {
      "id": 5,
      "name": "Jean Kouassi",
      "trade_name": "Maçon",
      "sector_name": "Bâtiment",
      "distance": 1500,
      "distance_text": "1.5 km",
      "is_nearby": true,
      "nzassa_score": 85,
      "average_rating": 4.5,
      "reviews_count": 12,
      "projects_completed": 25,
      "available": true
    }
  ],
  "meta": {
    "total": 15,
    "radius": 10000,
    "nearby_count": 3
  }
}
```

---

## Tests

### Test 1: Chargement des Secteurs

1. Ouvrir l'application
2. Se connecter
3. Vérifier que les catégories s'affichent
4. **Attendu**: Grille de 12 secteurs avec icônes

### Test 2: Artisans à Proximité

1. Autoriser la localisation
2. Attendre le chargement
3. Vérifier le badge "X artisans à proximité"
4. **Attendu**: Badge affiché si artisans < 2km

### Test 3: Pull-to-Refresh

1. Sur le HomeScreen
2. Swipe down
3. Vérifier l'indicateur de chargement
4. **Attendu**: Données rechargées

### Test 4: Clic sur Catégorie

1. Cliquer sur "Bâtiment"
2. Vérifier la navigation
3. **Attendu**: SearchFilterScreen avec secteur pré-sélectionné

### Test 5: Sans Localisation

1. Refuser la permission GPS
2. Vérifier l'affichage
3. **Attendu**: Secteurs affichés, pas de badge proximité

---

## Gestion des Erreurs

### Erreur de Localisation

```dart
if (permission == LocationPermission.denied) {
  errorMessage.value = 'Location permission denied';
  // Affiche les secteurs mais pas les artisans proches
}
```

### Erreur API

```dart
try {
  final response = await _tradeService.getSectors();
  if (!response.success) {
    errorMessage.value = response.message;
  }
} catch (e) {
  errorMessage.value = 'Network error: ${e.toString()}';
}
```

### Pas de Secteurs

```dart
if (_searchController.sectors.isEmpty) {
  return const SliverFillRemaining(
    child: Center(child: Text('Aucune catégorie disponible')),
  );
}
```

---

## Performance

### Optimisations Implémentées

1. **Chargement Parallèle**:
   ```dart
   await Future.wait([
     fetchSectors(),
     getCurrentLocation(),
   ]);
   ```

2. **Lazy Loading**: Les métiers ne sont chargés que quand un secteur est sélectionné

3. **Cache**: GetX garde les données en mémoire

4. **Debouncing**: Évite les requêtes multiples

---

## Prochaines Améliorations

### Court Terme

- [ ] Ajouter un cache local (Hive/SQLite)
- [ ] Implémenter la pagination pour les résultats
- [ ] Ajouter des filtres avancés
- [ ] Améliorer la gestion hors ligne

### Moyen Terme

- [ ] Ajouter des suggestions de recherche
- [ ] Implémenter la recherche par texte
- [ ] Ajouter des favoris
- [ ] Historique de recherche

### Long Terme

- [ ] Recommandations personnalisées
- [ ] Recherche vocale
- [ ] AR pour visualiser les artisans
- [ ] Intégration avec Google Maps

---

## Commandes Utiles

### Tester l'API Backend

```bash
# Récupérer les secteurs
curl http://localhost:8000/api/v1/sectors

# Rechercher des artisans
curl -X POST http://localhost:8000/api/v1/artisans/search \
  -H "Content-Type: application/json" \
  -d '{
    "latitude": 5.3599517,
    "longitude": -4.0082563,
    "radius": 10000
  }'
```

### Vérifier les Logs Frontend

```bash
flutter logs | grep "SearchController"
flutter logs | grep "HomeScreen"
```

### Hot Reload

```
Dans le terminal Flutter:
Appuyer sur 'r' pour hot reload
Appuyer sur 'R' pour hot restart
```

---

## Checklist de Vérification

- [x] Routes API ajoutées dans `routes/api.php`
- [x] `TradeController` créé avec toutes les méthodes
- [x] `HomeScreen` avec RefreshIndicator
- [x] Méthode `_loadInitialData()` implémentée
- [x] Gestion des erreurs de localisation
- [x] Affichage du badge artisans proches
- [x] Pull-to-refresh fonctionnel
- [x] Navigation vers SearchFilterScreen
- [ ] Tests unitaires
- [ ] Tests d'intégration

---

**Date**: 18 février 2026  
**Version**: 1.0  
**Statut**: ✅ Implémenté et Prêt pour Tests
