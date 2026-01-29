# Guide de Création de Compte Administrateur

## 🎯 Méthodes de Création

Il existe **3 méthodes** pour créer un compte administrateur dans ProsArtisan:

### 1. Via le Seeder (Recommandé pour le développement)

#### Seeding Complet
Crée l'admin + toutes les données de test:
```bash
cd prosartisan_backend
php artisan migrate:fresh --seed
```

#### Seeder Admin Uniquement
Crée uniquement le compte admin:
```bash
cd prosartisan_backend
php artisan db:seed --class=AdminSeeder
```

Ou avec le script:
```bash
cd prosartisan_backend
./create_admin.sh
```

**Credentials par défaut:**
- Email: `admin@prosartisan.sn`
- Password: `Admin@2026`

### 2. Via la Commande Artisan (Recommandé pour la production)

#### Mode Interactif
```bash
php artisan admin:create
```

Le système vous demandera:
- Email de l'admin
- Nom de l'admin
- Numéro de téléphone
- Mot de passe (avec confirmation)

#### Mode Non-Interactif
```bash
php artisan admin:create \
  --email=admin@prosartisan.sn \
  --name="Administrateur ProsArtisan" \
  --phone="+221 77 000 00 00" \
  --password="VotreMotDePasseSecurise"
```

### 3. Via Tinker (Pour les tests rapides)

```bash
php artisan tinker
```

Puis exécutez:
```php
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

DB::table('users')->insert([
    'id' => Str::uuid()->toString(),
    'name' => 'Admin',
    'email' => 'admin@prosartisan.sn',
    'phone' => '+221 77 000 00 00',
    'password' => Hash::make('Admin@2026'),
    'role' => 'ADMIN',
    'email_verified_at' => now(),
    'created_at' => now(),
    'updated_at' => now(),
]);
```

## 🔐 Sécurité du Mot de Passe

### Exigences Minimales
- **Longueur:** Minimum 8 caractères
- **Recommandé:** 12+ caractères avec majuscules, minuscules, chiffres et symboles

### Exemples de Mots de Passe Forts
```
Admin@2026!Secure
ProsArtisan#2026
Adm1n!Str0ng#Pass
```

### ⚠️ À ÉVITER
- `password`, `admin`, `123456`
- Mots du dictionnaire
- Informations personnelles
- Mots de passe réutilisés

## 👥 Rôles Disponibles

Le système ProsArtisan supporte plusieurs rôles:

| Rôle | Description | Permissions |
|------|-------------|-------------|
| `ADMIN` | Administrateur système | Accès complet au backoffice |
| `REFERENT_ZONE` | Référent de zone | Médiation, arbitrage |
| `ARTISAN` | Artisan | Profil artisan, missions |
| `CLIENT` | Client | Poster missions, valider travaux |
| `FOURNISSEUR` | Fournisseur | Vendre matériaux, valider jetons |

## 📊 Vérification du Compte Admin

### Via Tinker
```bash
php artisan tinker
>>> \App\Models\User::where('role', 'ADMIN')->get()
```

### Via SQL
```sql
SELECT id, name, email, role, created_at 
FROM users 
WHERE role = 'ADMIN';
```

### Via la Commande
```bash
php artisan tinker --execute="
    \App\Models\User::where('role', 'ADMIN')->get()->each(function(\$u) {
        echo \$u->name . ' (' . \$u->email . ')' . PHP_EOL;
    });
"
```

## 🔄 Mise à Jour d'un Utilisateur Existant vers Admin

### Via Tinker
```bash
php artisan tinker
>>> DB::table('users')->where('email', 'user@example.com')->update(['role' => 'ADMIN'])
```

### Via la Commande
```bash
php artisan admin:create --email=existing@user.com
# Répondez "yes" quand demandé si vous voulez mettre à jour l'utilisateur
```

### Via SQL
```sql
UPDATE users 
SET role = 'ADMIN', updated_at = NOW() 
WHERE email = 'user@example.com';
```

## 🗑️ Suppression d'un Compte Admin

### Via Tinker
```bash
php artisan tinker
>>> DB::table('users')->where('email', 'admin@prosartisan.sn')->delete()
```

### Via SQL
```sql
DELETE FROM users WHERE email = 'admin@prosartisan.sn';
```

## 🔑 Réinitialisation du Mot de Passe Admin

### Via Tinker
```bash
php artisan tinker
>>> use Illuminate\Support\Facades\Hash;
>>> DB::table('users')
    ->where('email', 'admin@prosartisan.sn')
    ->update(['password' => Hash::make('NouveauMotDePasse')]);
```

### Via SQL (avec hash bcrypt)
```bash
# Générer le hash
php artisan tinker --execute="echo Hash::make('NouveauMotDePasse');"

# Puis en SQL
UPDATE users 
SET password = '$2y$12$...' 
WHERE email = 'admin@prosartisan.sn';
```

## 📝 Bonnes Pratiques

### Pour le Développement
1. ✅ Utilisez le seeder avec credentials par défaut
2. ✅ Documentez les credentials dans le README
3. ✅ Utilisez des mots de passe simples mais identifiables

### Pour la Production
1. ✅ Créez l'admin via la commande interactive
2. ✅ Utilisez un mot de passe fort et unique
3. ✅ Stockez les credentials dans un gestionnaire de mots de passe
4. ✅ Changez le mot de passe après la première connexion
5. ✅ Activez l'authentification à deux facteurs (si disponible)
6. ✅ Limitez le nombre de comptes admin
7. ✅ Auditez régulièrement les accès admin

## 🚀 Exemples d'Utilisation

### Scénario 1: Nouveau Projet (Développement)
```bash
# Setup complet avec admin
cd prosartisan_backend
php artisan migrate:fresh --seed

# Login avec:
# Email: admin@prosartisan.sn
# Password: Admin@2026
```

### Scénario 2: Production Initiale
```bash
# Migrations uniquement
php artisan migrate

# Créer admin de manière sécurisée
php artisan admin:create
# Suivre les instructions interactives
```

### Scénario 3: Ajouter un Admin Supplémentaire
```bash
php artisan admin:create \
  --email=admin2@prosartisan.sn \
  --name="Admin Secondaire" \
  --phone="+221 77 111 11 11" \
  --password="SecurePass@2026"
```

### Scénario 4: Promouvoir un Utilisateur Existant
```bash
php artisan admin:create --email=existing@user.com
# Répondre "yes" pour mettre à jour vers admin
```

## 🔍 Dépannage

### Erreur: "User already exists"
**Solution:** Utilisez l'option de mise à jour ou supprimez l'utilisateur existant

### Erreur: "Invalid email address"
**Solution:** Vérifiez le format de l'email (doit contenir @)

### Erreur: "Password must be at least 8 characters"
**Solution:** Utilisez un mot de passe plus long

### Erreur: "Passwords do not match"
**Solution:** Assurez-vous que les deux mots de passe sont identiques

### Admin ne peut pas se connecter
**Vérifications:**
1. Le rôle est bien `ADMIN` (pas `admin` en minuscules)
2. L'email est correct
3. Le mot de passe est correct
4. Le compte est vérifié (`email_verified_at` n'est pas NULL)

## 📚 Fichiers Associés

- `database/seeders/AdminSeeder.php` - Seeder pour créer l'admin
- `app/Console/Commands/CreateAdminCommand.php` - Commande Artisan
- `create_admin.sh` - Script shell pour création rapide
- `database/seeders/DatabaseSeeder.php` - Seeder principal

## 🔗 Commandes Utiles

```bash
# Créer admin (interactif)
php artisan admin:create

# Créer admin (non-interactif)
php artisan admin:create --email=admin@example.com --password=SecurePass

# Lister tous les admins
php artisan tinker --execute="User::where('role', 'ADMIN')->get()"

# Compter les admins
php artisan tinker --execute="echo User::where('role', 'ADMIN')->count()"

# Seeder admin uniquement
php artisan db:seed --class=AdminSeeder

# Seeding complet (avec admin)
php artisan migrate:fresh --seed
```

## ⚠️ Avertissements de Sécurité

1. **Ne jamais** commiter les credentials admin dans Git
2. **Ne jamais** partager les mots de passe admin par email/chat
3. **Toujours** utiliser HTTPS en production
4. **Toujours** changer le mot de passe par défaut
5. **Toujours** limiter les tentatives de connexion
6. **Toujours** logger les actions admin
7. **Toujours** utiliser des mots de passe forts en production

---

**Dernière mise à jour:** 29 janvier 2026

**Version:** 1.0.0

**Support:** Pour toute question, consultez la documentation complète
