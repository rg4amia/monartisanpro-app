# Correction des Relations Backend

## Problème Identifié

Erreur lors de la recherche d'artisans:
```
"success": false,
"message": "Failed to search artisans",
"error": "Call to undefined relationship [artisanScore] on model [App\Models\User]."
```

## Corrections Appliquées

### 1. Modèle User (`backend/app/Models/User.php`)

**Ajout des relations manquantes:**

```php
/**
 * Get the artisan score for the user.
 */
public function artisanScore(): HasOne
{
    return $this->hasOne(ArtisanScore::class, 'artisan_id');
}

/**
 * Get all reviews for the artisan.
 */
public function reviews(): HasMany
{
    return $this->hasMany(Review::class, 'artisan_id');
}

/**
 * Get all projects where user is the artisan.
 */
public function artisanProjects(): HasMany
{
    return $this->hasMany(Project::class, 'artisan_id');
}

/**
 * Get all projects where user is the client.
 */
public function clientProjects(): HasMany
{
    return $this->hasMany(Project::class, 'client_id');
}
```

**Points clés:**
- ✅ `artisanScore` utilise `'artisan_id'` comme clé étrangère (pas `user_id`)
- ✅ `reviews` utilise `'artisan_id'` pour les avis reçus par l'artisan
- ✅ Distinction entre `artisanProjects` (projets où l'utilisateur est artisan) et `clientProjects` (projets où l'utilisateur est client)

### 2. Modèle ArtisanProfile (`backend/app/Models/ArtisanProfile.php`)

**Ajout d'accesseurs pour latitude/longitude:**

```php
/**
 * Get the latitude from the location point.
 */
public function getLatitudeAttribute(): ?float
{
    return $this->location?->latitude;
}

/**
 * Get the longitude from the location point.
 */
public function getLongitudeAttribute(): ?float
{
    return $this->location?->longitude;
}
```

**Raison:**
- Le modèle utilise un champ `location` de type `Point` (spatial)
- Le contrôleur a besoin d'accéder à `$profile->latitude` et `$profile->longitude`
- Les accesseurs extraient automatiquement ces valeurs du Point

### 3. TradeController (`backend/app/Http/Controllers/Api/V1/TradeController.php`)

**Ajout des agrégations dans la requête:**

```php
$query = \App\Models\User::query()
    ->where('role', 'artisan')
    ->where('status', 'active')
    ->with(['artisanProfile.trade.sector', 'artisanScore'])
    ->withCount([
        'reviews',
        'artisanProjects as completed_projects_count' => function ($q) {
            $q->where('status', 'completed');
        },
    ])
    ->withAvg('reviews', 'rating')
    ->whereHas('artisanProfile', function ($q) use ($validated) {
        // ... filtres
    });
```

**Changements:**
- ✅ `withCount('reviews')` - Compte le nombre d'avis
- ✅ `withCount('artisanProjects as completed_projects_count')` - Compte les projets complétés
- ✅ `withAvg('reviews', 'rating')` - Calcule la note moyenne

## Structure des Tables

### artisan_scores
```sql
- id
- artisan_id (FK vers users.id)
- total_score
- ...
```

### reviews
```sql
- id
- artisan_id (FK vers users.id)
- client_id (FK vers users.id)
- rating
- ...
```

### projects
```sql
- id
- client_id (FK vers users.id)
- artisan_id (FK vers users.id)
- status
- ...
```

### artisan_profiles
```sql
- id
- user_id (FK vers users.id)
- trade_id (FK vers trades.id)
- location (POINT - spatial)
- zone_name
- bio
- experience_years
- available
- ...
```

## Réponse API Attendue

```json
{
  "success": true,
  "data": [
    {
      "id": 7,
      "name": "Koné Adama",
      "email": "kone.adama@example.com",
      "phone": "+225XXXXXXXX",
      "avatar": "https://...",
      "trade_id": 5,
      "trade_name": "Électricien",
      "sector_name": "ÉLECTRICITÉ & ÉNERGIE",
      "zone_name": "Cocody",
      "bio": "Électricien professionnel...",
      "experience_years": 10,
      "available": true,
      "distance": 1500,
      "distance_text": "1.5 km",
      "is_nearby": true,
      "latitude": 5.3599517,
      "longitude": -4.0082563,
      "nzassa_score": 85,
      "average_rating": 4.5,
      "reviews_count": 23,
      "projects_completed": 45
    }
  ],
  "meta": {
    "total": 15,
    "radius": 10000,
    "nearby_count": 3
  }
}
```

## Test des Relations

### Vérifier qu'un artisan a un score:
```bash
php artisan tinker
$user = User::where('role', 'artisan')->with('artisanScore')->first();
$user->artisanScore; // Devrait retourner un ArtisanScore ou null
```

### Vérifier les agrégations:
```bash
php artisan tinker
$user = User::where('role', 'artisan')
    ->withCount('reviews')
    ->withAvg('reviews', 'rating')
    ->first();
echo $user->reviews_count;
echo $user->reviews_avg_rating;
```

### Vérifier latitude/longitude:
```bash
php artisan tinker
$profile = ArtisanProfile::first();
echo $profile->latitude;  // Devrait extraire du Point
echo $profile->longitude; // Devrait extraire du Point
```

## Gestion des Cas Null

Le code gère correctement les cas où:
- Un artisan n'a pas encore de score: `$artisan->artisanScore->total_score ?? null`
- Un artisan n'a pas d'avis: `$artisan->reviews_avg_rating ?? null`
- Un artisan n'a pas de projets complétés: `$artisan->completed_projects_count ?? 0`

## Prochaines Étapes

### Si les artisans n'ont pas de scores:
Créer un seeder ou une commande pour initialiser les scores:

```php
// database/seeders/ArtisanScoreSeeder.php
public function run()
{
    $artisans = User::where('role', 'artisan')->get();
    
    foreach ($artisans as $artisan) {
        ArtisanScore::updateOrCreate(
            ['artisan_id' => $artisan->id],
            [
                'total_score' => 50, // Score initial
                'quality_score' => 50,
                'reliability_score' => 50,
                'communication_score' => 50,
            ]
        );
    }
}
```

### Si les artisans n'ont pas de coordonnées:
Mettre à jour les profils avec des coordonnées:

```php
// Exemple pour Abidjan
$profile = ArtisanProfile::find(1);
$profile->location = new Point(5.3599517, -4.0082563);
$profile->save();
```

## Dépannage

### Erreur: "Unknown column 'artisan_scores.user_id'"
**Solution:** La relation doit spécifier `'artisan_id'`:
```php
return $this->hasOne(ArtisanScore::class, 'artisan_id');
```

### Erreur: "Undefined property: latitude"
**Solution:** Ajouter les accesseurs dans ArtisanProfile:
```php
public function getLatitudeAttribute(): ?float
{
    return $this->location?->latitude;
}
```

### Aucun résultat retourné
**Vérifications:**
1. Les artisans ont-ils des profils avec coordonnées?
2. Les artisans sont-ils actifs (`status = 'active'`)?
3. Le rayon de recherche est-il suffisant?

## Conclusion

Toutes les relations nécessaires sont maintenant définies dans le modèle User. L'API de recherche peut charger efficacement:
- Le profil artisan avec métier et secteur
- Le score N'Zassa
- Les statistiques d'avis (moyenne et nombre)
- Les projets complétés

L'application frontend peut maintenant afficher toutes ces informations correctement.
