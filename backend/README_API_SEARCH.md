# 🔍 Guide de Test - API Recherche d'Artisans

## 📋 Vue d'ensemble

Ce guide explique comment tester l'API de recherche d'artisans avec les données du `TestDataSeeder`.

## 🎯 Données de Test Disponibles

### Artisans (10)
Basés à différentes zones d'Abidjan avec coordonnées GPS réelles :

| Nom | Métier | Zone | Coordonnées |
|-----|--------|------|-------------|
| Koné Adama | Maçon | Cocody | 5.3364, -4.0267 |
| Yao Jean-Claude | Électricien | Plateau | 5.3333, -4.0333 |
| Touré Mamadou | Plombier | Marcory | 5.3500, -4.0100 |
| Diallo Abdoulaye | Menuisier | Adjamé | 5.3200, -4.0500 |
| Coulibaly Moussa | Peintre | Treichville | 5.3400, -4.0200 |
| Bah Ibrahima | Carreleur | Yopougon | 5.3450, -4.0350 |
| Fofana Lassina | Soudeur | Abobo | 5.3300, -4.0400 |
| Sanogo Souleymane | Mécanicien Auto | Koumassi | 5.3250, -4.0250 |
| Doumbia Bakary | Climaticien | Cocody II | 5.3380, -4.0280 |
| Ouédraogo Rasmané | Jardinier | Riviera | 5.3420, -4.0320 |

## 🚀 Méthodes de Test

### Méthode 1 : Script Tinker (Recommandé)

```bash
cd backend
php artisan tinker < tinker_search_artisans.php
```

**Ce script affiche :**
- Liste complète des artisans avec coordonnées
- Exemples de requêtes de recherche
- Simulations de recherche par distance
- Commandes cURL prêtes à l'emploi
- Exemple de réponse JSON

### Méthode 2 : Script Bash avec cURL

```bash
cd backend
./test_api_search.sh
```

**Ce script teste :**
- Recherche dans différents rayons (5km, 10km)
- Recherche par métier spécifique
- Recherche par secteur
- Tri par distance
- Filtrage par score minimum
- Recherche depuis différentes zones

### Méthode 3 : Commandes cURL Manuelles

#### Recherche de base (tous les artisans dans 5km)
```bash
curl -X GET 'https://prosartisan.net/api/v1/artisans/search' \
  -G \
  --data-urlencode 'latitude=5.3364' \
  --data-urlencode 'longitude=-4.0267' \
  --data-urlencode 'radius=5000' \
  -H "Accept: application/json" | jq '.'
```

#### Recherche par métier (Électriciens)
```bash
curl -X GET 'https://prosartisan.net/api/v1/artisans/search' \
  -G \
  --data-urlencode 'latitude=5.3364' \
  --data-urlencode 'longitude=-4.0267' \
  --data-urlencode 'radius=10000' \
  --data-urlencode 'trade_id=2' \
  -H "Accept: application/json" | jq '.data[] | {name: .name, trade: .profile.trade_name}'
```

#### Recherche par secteur (Bâtiment)
```bash
curl -X GET 'https://prosartisan.net/api/v1/artisans/search' \
  -G \
  --data-urlencode 'latitude=5.3364' \
  --data-urlencode 'longitude=-4.0267' \
  --data-urlencode 'radius=10000' \
  --data-urlencode 'sector_id=1' \
  -H "Accept: application/json" | jq '.data[] | {name: .name, sector: .profile.sector_name}'
```

#### Recherche avec score minimum
```bash
curl -X GET 'https://prosartisan.net/api/v1/artisans/search' \
  -G \
  --data-urlencode 'latitude=5.3364' \
  --data-urlencode 'longitude=-4.0267' \
  --data-urlencode 'radius=10000' \
  --data-urlencode 'min_score=70' \
  -H "Accept: application/json" | jq '.data[] | {name: .name, score: .score.total_score}'
```

#### Tri par distance (3 plus proches)
```bash
curl -X GET 'https://prosartisan.net/api/v1/artisans/search' \
  -G \
  --data-urlencode 'latitude=5.3364' \
  --data-urlencode 'longitude=-4.0267' \
  --data-urlencode 'radius=10000' \
  --data-urlencode 'sort_by=distance' \
  --data-urlencode 'limit=3' \
  -H "Accept: application/json" | jq '.data[] | {name: .name, distance: .distance}'
```

## 📍 Points de Test par Zone

### Cocody (Centre)
```
Latitude: 5.3364
Longitude: -4.0267
```

### Plateau
```
Latitude: 5.3333
Longitude: -4.0333
```

### Marcory
```
Latitude: 5.3500
Longitude: -4.0100
```

### Adjamé
```
Latitude: 5.3200
Longitude: -4.0500
```

### Yopougon
```
Latitude: 5.3450
Longitude: -4.0350
```

## 📱 Paramètres de l'API

### Paramètres Requis
- `latitude` (float) : Latitude du point de recherche
- `longitude` (float) : Longitude du point de recherche

### Paramètres Optionnels
- `radius` (int) : Rayon de recherche en mètres (défaut: 5000)
- `sector_id` (int) : ID du secteur d'activité
- `trade_id` (int) : ID du métier spécifique
- `min_score` (int) : Score N'Zassa minimum (0-100)
- `sort_by` (string) : Tri (distance, rating, experience)
- `limit` (int) : Nombre maximum de résultats
- `available_only` (bool) : Uniquement les artisans disponibles

## 📊 Format de Réponse

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Koné Adama",
      "phone": "+2250711234567",
      "email": "kone.adama@artisan.ci",
      "avatar": null,
      "profile": {
        "trade_id": 1,
        "trade_name": "Maçon",
        "sector_id": 1,
        "sector_name": "Bâtiment et Construction",
        "bio": "Artisan professionnel avec 5 ans d'expérience...",
        "experience_years": 5,
        "zone_name": "Cocody, Abidjan",
        "available": true,
        "latitude": 5.3364,
        "longitude": -4.0267
      },
      "distance": 123.45,
      "score": {
        "total_score": 85,
        "reliability_score": 90,
        "quality_score": 88,
        "communication_score": 82
      },
      "stats": {
        "completed_projects": 5,
        "average_rating": 4.5,
        "total_reviews": 3
      }
    }
  ],
  "meta": {
    "total": 10,
    "search_params": {
      "latitude": 5.3364,
      "longitude": -4.0267,
      "radius": 5000
    }
  }
}
```

## 🧪 Tests Recommandés

### Test 1 : Recherche de base
- **Objectif** : Vérifier que l'API retourne des artisans
- **Rayon** : 5km depuis Cocody
- **Résultat attendu** : 3-5 artisans

### Test 2 : Recherche étendue
- **Objectif** : Obtenir plus de résultats
- **Rayon** : 10km depuis Cocody
- **Résultat attendu** : 8-10 artisans

### Test 3 : Recherche par métier
- **Objectif** : Filtrer par métier spécifique
- **Métier** : Électricien
- **Résultat attendu** : 1 artisan (Jean-Claude)

### Test 4 : Recherche par secteur
- **Objectif** : Tous les artisans d'un secteur
- **Secteur** : Bâtiment
- **Résultat attendu** : 6-8 artisans

### Test 5 : Tri par distance
- **Objectif** : Vérifier l'ordre des résultats
- **Tri** : distance
- **Résultat attendu** : Artisans triés du plus proche au plus loin

### Test 6 : Filtrage par score
- **Objectif** : Artisans avec bonne réputation
- **Score min** : 70
- **Résultat attendu** : Artisans avec projets complétés et avis

## 🔧 Dépannage

### Aucun résultat retourné
1. Vérifier que le seeder a été exécuté : `php artisan db:seed --class=TestDataSeeder`
2. Vérifier les coordonnées GPS (format : latitude, longitude)
3. Augmenter le rayon de recherche

### Erreur de distance
- Vérifier que MySQL/MariaDB supporte les fonctions spatiales
- Vérifier que la colonne `location` est de type `POINT`

### Erreur 404
- Vérifier l'URL de base (local vs production)
- Vérifier que les routes API sont enregistrées

## 📝 Notes

- Les coordonnées sont basées sur des zones réelles d'Abidjan
- Les distances sont calculées en mètres (formule haversine)
- Le score N'Zassa est calculé automatiquement après les projets complétés
- Certains artisans ont des projets complétés avec avis (score > 0)

## 🎓 Pour Flutter

Utilisez ces coordonnées dans votre `ArtisanSearchController` :

```dart
// Point de référence par défaut (Cocody)
final defaultLatitude = 5.3364;
final defaultLongitude = -4.0267;
final defaultRadius = 5000.0; // 5km
```
