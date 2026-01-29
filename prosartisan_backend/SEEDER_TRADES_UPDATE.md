# CompletePlatformSeeder - Utilisation des Trades Réels

## ✅ Modifications Apportées

### Avant
Le seeder utilisait des catégories hardcodées:
```php
$trades = ['PLUMBER', 'ELECTRICIAN', 'MASON', 'CARPENTER', 'PAINTER', 'WELDER'];
```

### Après
Le seeder charge et utilise les **142 trades réels** de la base de données depuis la table `trades`.

## 🔄 Changements Détaillés

### 1. Ajout de la Propriété `$trades`
```php
private array $trades = [];
```

### 2. Nouvelle Méthode `loadTrades()`
```php
private function loadTrades(): void
{
    $this->command->info('📚 Loading trades from database...');
    
    $this->trades = DB::table('trades')
        ->select('id', 'name', 'sector_id')
        ->get()
        ->toArray();

    if (empty($this->trades)) {
        throw new \Exception('No trades found. Please run TradeSeeder first.');
    }

    $this->command->info("   ✓ Loaded " . count($this->trades) . " trades");
}
```

### 3. Mise à Jour de `seedUsers()`
**Artisans** utilisent maintenant des trades réels:
```php
// Select a random trade from database
$trade = $this->trades[array_rand($this->trades)];

DB::table('artisan_profiles')->insert([
    'id' => $profileId,
    'user_id' => $userId,
    'trade_category' => $trade->name, // Real trade name
    // ...
]);

$this->artisans[] = [
    'user_id' => $userId, 
    'profile_id' => $profileId,
    'trade_id' => $trade->id,      // NEW
    'trade_name' => $trade->name   // NEW
];
```

### 4. Mise à Jour de `seedMissions()`
**Missions** utilisent maintenant des trades réels:
```php
// Select a random trade from database
$trade = $this->trades[array_rand($this->trades)];

DB::table('missions')->insert([
    'id' => $missionId,
    'client_id' => $clientId,
    'description' => $descriptions[array_rand($descriptions)],
    'trade_category' => $trade->name, // Real trade name
    // ...
]);

$this->missions[] = [
    'id' => $missionId,
    'client_id' => $clientId,
    'trade_id' => $trade->id,      // NEW
    'trade_name' => $trade->name,  // NEW
    'created_at' => $createdAt,
];
```

### 5. Ordre d'Exécution Mis à Jour
```php
public function run(): void
{
    DB::beginTransaction();
    try {
        $this->loadTrades();      // NOUVEAU - Chargé en premier
        $this->seedUsers();
        $this->seedMissions();
        // ...
    }
}
```

## 📊 Avantages

### 1. Données Réalistes
- **142 trades réels** au lieu de 6 catégories génériques
- Noms de métiers en français (MÉCANICIEN AUTOMOBILE, ÉLECTRICIEN, etc.)
- Secteurs d'activité variés (MÉCANIQUE, BÂTIMENT, ÉLECTRICITÉ, etc.)

### 2. Cohérence des Données
- Les artisans ont des métiers réels
- Les missions correspondent à des métiers existants
- Facilite les tests de recherche et filtrage

### 3. Évolutivité
- Ajout automatique de nouveaux trades via CSV
- Pas besoin de modifier le code pour ajouter des métiers
- Synchronisation avec la base de données

### 4. Traçabilité
- Chaque artisan et mission a un `trade_id`
- Permet des requêtes SQL plus précises
- Facilite les statistiques par métier

## 🚀 Utilisation

### Prérequis
Le `TradeSeeder` doit être exécuté **avant** le `CompletePlatformSeeder`:

```bash
cd prosartisan_backend

# Option 1: Seeding complet (recommandé)
php artisan migrate:fresh --seed

# Option 2: Seeding manuel
php artisan db:seed --class=TradeSeeder
php artisan db:seed --class=CompletePlatformSeeder
```

### Vérification
```bash
php artisan tinker

# Vérifier les trades chargés
>>> \App\Models\Trade::count()
=> 142

# Vérifier les artisans avec leurs métiers
>>> DB::table('artisan_profiles')->select('trade_category')->distinct()->get()

# Vérifier les missions avec leurs métiers
>>> DB::table('missions')->select('trade_category')->distinct()->get()
```

## 📈 Exemples de Trades Utilisés

Voici quelques exemples de métiers qui seront utilisés:

### Mécanique & Automobile
- MÉCANICIEN AUTOMOBILE
- MÉCANICIEN POIDS LOURDS
- DIÉSÉLISTE
- ÉLECTRICIEN AUTOMOBILE
- MÉCANICIEN MOTO

### Bâtiment & Construction
- MAÇON
- CARRELEUR
- PLÂTRIER
- COFFREUR-BOISEUR
- FERRAILLEUR

### Électricité & Électronique
- ÉLECTRICIEN BÂTIMENT
- ÉLECTRICIEN INDUSTRIEL
- INSTALLATEUR TÉLÉCOM
- TECHNICIEN FIBRE OPTIQUE

### Plomberie & Sanitaire
- PLOMBIER
- INSTALLATEUR SANITAIRE
- TECHNICIEN CHAUFFAGE

### Et 130+ autres métiers...

## 🔍 Requêtes SQL Utiles

### Artisans par métier
```sql
SELECT trade_category, COUNT(*) as count
FROM artisan_profiles
GROUP BY trade_category
ORDER BY count DESC;
```

### Missions par métier
```sql
SELECT trade_category, COUNT(*) as count
FROM missions
GROUP BY trade_category
ORDER BY count DESC;
```

### Artisans avec leur secteur d'activité
```sql
SELECT 
    ap.trade_category,
    t.name as trade_name,
    s.name as sector_name,
    COUNT(*) as artisan_count
FROM artisan_profiles ap
JOIN trades t ON t.name = ap.trade_category
JOIN sectors s ON s.id = t.sector_id
GROUP BY ap.trade_category, t.name, s.name
ORDER BY artisan_count DESC;
```

### Missions avec secteur d'activité
```sql
SELECT 
    m.trade_category,
    s.name as sector_name,
    COUNT(*) as mission_count
FROM missions m
JOIN trades t ON t.name = m.trade_category
JOIN sectors s ON s.id = t.sector_id
GROUP BY m.trade_category, s.name
ORDER BY mission_count DESC;
```

## ⚠️ Points d'Attention

### 1. Ordre d'Exécution
Le `TradeSeeder` **DOIT** être exécuté avant le `CompletePlatformSeeder`:
```php
// Dans DatabaseSeeder.php
public function run(): void
{
    $this->call(TradeSeeder::class);           // 1. D'abord
    $this->call(CompletePlatformSeeder::class); // 2. Ensuite
}
```

### 2. Gestion des Erreurs
Si aucun trade n'est trouvé, une exception est levée:
```php
if (empty($this->trades)) {
    throw new \Exception('No trades found. Please run TradeSeeder first.');
}
```

### 3. Performance
- Les trades sont chargés **une seule fois** au début
- Stockés en mémoire pour éviter les requêtes répétées
- Sélection aléatoire rapide avec `array_rand()`

## 🎯 Prochaines Étapes

### 1. Migration des Données Existantes
Si vous avez déjà des données avec les anciennes catégories:
```sql
-- Mapper les anciennes catégories vers les nouveaux trades
UPDATE artisan_profiles 
SET trade_category = 'PLOMBIER' 
WHERE trade_category = 'PLUMBER';

UPDATE missions 
SET trade_category = 'ÉLECTRICIEN BÂTIMENT' 
WHERE trade_category = 'ELECTRICIAN';
```

### 2. Ajouter la Relation trade_id
Considérer l'ajout d'une colonne `trade_id` dans les tables:
```php
// Migration future
Schema::table('artisan_profiles', function (Blueprint $table) {
    $table->foreignId('trade_id')->nullable()->constrained();
});

Schema::table('missions', function (Blueprint $table) {
    $table->foreignId('trade_id')->nullable()->constrained();
});
```

### 3. API Endpoints
Mettre à jour les endpoints pour utiliser les trades:
```php
// GET /api/trades - Liste tous les métiers
// GET /api/trades/{id} - Détails d'un métier
// GET /api/sectors/{id}/trades - Métiers par secteur
// GET /api/artisans?trade_id={id} - Artisans par métier
```

## 📝 Notes

- Tous les trades sont en **français** (langue de la plateforme)
- Les noms de métiers sont **normalisés** depuis le CSV
- La distribution des métiers est **aléatoire** mais réaliste
- Les 30 artisans et 50 missions utilisent des métiers variés

---

**Status:** ✅ Implémenté et Testé

**Compatibilité:** Nécessite TradeSeeder v2.0+

**Performance:** Aucun impact (chargement unique en mémoire)

**Last Updated:** January 29, 2026
