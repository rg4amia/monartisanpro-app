# Database Seeders

## Vue d'ensemble

Ce dossier contient les seeders pour peupler la base de données avec des données de test.

## Seeders disponibles

### 1. SectorsTradesSeeder
**Obligatoire** - Doit être exécuté en premier.

Crée les secteurs et métiers de base:
- Secteur Bâtiment (Maçon, Électricien, Plombier, etc.)
- Secteur Automobile (Mécanicien, Carrossier, etc.)
- Secteur Services (Jardinier, Agent de sécurité, etc.)

```bash
php artisan db:seed --class=SectorsTradesSeeder
```

### 2. TestDataSeeder
**Petit jeu de données** - Pour les tests de développement.

Crée:
- 5 clients
- 10 artisans (différents métiers)
- 3 fournisseurs
- 5 projets (différents statuts)
- Plusieurs devis
- 2 projets complets avec workflow (milestones, tokens, reviews)

```bash
php artisan db:seed --class=TestDataSeeder
```

### 3. BigDataSeeder ⭐ NOUVEAU
**Grand jeu de données** - Pour les tests de performance et démonstrations.

Crée:
- **100 clients**
- **200 artisans** (répartis dans tous les métiers)
- **20 fournisseurs**
- **500 projets** (différents statuts)
- **1000+ devis** (1 à 5 devis par projet)
- **100 projets complets** avec workflow complet
- **2000+ messages** entre clients et artisans

```bash
php artisan db:seed --class=BigDataSeeder
```

## Utilisation

### Première installation

```bash
# 1. Créer la base de données
php artisan migrate:fresh

# 2. Créer les secteurs et métiers (OBLIGATOIRE)
php artisan db:seed --class=SectorsTradesSeeder

# 3. Choisir un seeder de données
# Option A: Petit jeu de données
php artisan db:seed --class=TestDataSeeder

# Option B: Grand jeu de données
php artisan db:seed --class=BigDataSeeder
```

### Tout en une commande

```bash
# Avec TestDataSeeder
php artisan migrate:fresh --seed --seeder=SectorsTradesSeeder && php artisan db:seed --class=TestDataSeeder

# Avec BigDataSeeder
php artisan migrate:fresh --seed --seeder=SectorsTradesSeeder && php artisan db:seed --class=BigDataSeeder
```

### Réinitialiser uniquement les données

```bash
# Supprimer toutes les données sauf sectors/trades
php artisan db:seed --class=TestDataSeeder --force

# Ou avec BigDataSeeder
php artisan db:seed --class=BigDataSeeder --force
```

## Détails BigDataSeeder

### Répartition des données

#### Utilisateurs (320 total)
- 100 clients (31%)
- 200 artisans (62.5%)
- 20 fournisseurs (6.5%)

#### Projets (600+ total)
- 500 projets en cours/attente (83%)
  - pending: ~70
  - awaiting_quotes: ~210
  - quoted: ~70
  - payment_pending: ~70
  - in_progress: ~80
- 100 projets terminés (17%)

#### Devis (~1000+)
- 70% des projets reçoivent des devis
- 1 à 5 devis par projet
- Statuts variés: sent, accepted, rejected

#### Messages (~2000+)
- 60% des projets ont des conversations
- 2 à 15 messages par conversation
- Messages entre clients et artisans

#### Workflow complet (100 projets)
- Escrow wallets
- Material tokens (utilisés)
- Token redemptions
- Milestones (1 à 4 par projet)
- Labor payments
- Reviews (80% des projets)
- Scores artisans calculés

### Zones géographiques

Les données sont réparties dans 20 zones d'Abidjan:
- Cocody (Angré, Riviera, Deux Plateaux)
- Plateau
- Marcory (Zone 4, Biétry)
- Adjamé
- Treichville
- Yopougon (Niangon, Sicogi)
- Abobo (Gare, PK18)
- Koumassi
- Port-Bouët
- Bingerville
- Anyama
- Songon
- Attécoubé

### Performances

Temps d'exécution estimé:
- TestDataSeeder: ~5-10 secondes
- BigDataSeeder: ~2-5 minutes

Espace disque requis:
- TestDataSeeder: ~1 MB
- BigDataSeeder: ~50-100 MB

## Données de connexion

Tous les utilisateurs créés ont le même mot de passe:
```
password123
```

### Exemples de comptes

#### Clients (TestDataSeeder)
- kouassi.yao@email.ci
- kone.aminata@email.ci
- traore.seydou@email.ci

#### Artisans (TestDataSeeder)
- kone.adama@artisan.ci (Maçon)
- yao.jeanclaude@artisan.ci (Électricien)
- toure.mamadou@artisan.ci (Plombier)

#### Fournisseurs (TestDataSeeder)
- quincaillerie.yopougon@vendor.ci
- materiaux.centre@vendor.ci

## Conseils

### Pour le développement
Utilisez `TestDataSeeder` - plus rapide et suffisant pour tester les fonctionnalités.

### Pour les démonstrations
Utilisez `BigDataSeeder` - données réalistes et volumineuses pour impressionner.

### Pour les tests de performance
Utilisez `BigDataSeeder` - permet de tester:
- Pagination
- Recherche avec beaucoup de résultats
- Filtres complexes
- Performance des requêtes
- Chargement des listes

## Nettoyage

Pour supprimer toutes les données:

```bash
php artisan migrate:fresh
```

Pour réinitialiser avec de nouvelles données:

```bash
php artisan migrate:fresh && php artisan db:seed --class=SectorsTradesSeeder && php artisan db:seed --class=BigDataSeeder
```

## Troubleshooting

### Erreur "No sectors found"
Vous devez d'abord exécuter `SectorsTradesSeeder`:
```bash
php artisan db:seed --class=SectorsTradesSeeder
```

### Timeout lors du seeding
Augmentez le timeout PHP dans `php.ini`:
```ini
max_execution_time = 300
```

### Mémoire insuffisante
Augmentez la limite mémoire:
```ini
memory_limit = 512M
```

Ou utilisez `TestDataSeeder` à la place.
