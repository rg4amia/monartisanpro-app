# Correction du Schéma de la Table Users

## ⚠️ Problème Identifié

Les seeders utilisent des noms de colonnes qui ne correspondent pas à la migration de la table `users`.

### Structure Réelle (Migration)
```php
Schema::create('users', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('email', 255)->unique();
    $table->string('password_hash', 255);           // ❌ Pas 'password'
    $table->string('user_type', 50);                // ❌ Pas 'role'
    $table->string('account_status', 50)->default('PENDING');
    $table->string('phone_number', 20)->nullable(); // ❌ Pas 'phone'
    $table->integer('failed_login_attempts')->default(0);
    $table->timestamp('locked_until')->nullable();
    $table->timestamps();
});
```

### Colonnes Manquantes
- ❌ `name` - N'existe pas dans la migration
- ❌ `email_verified_at` - N'existe pas dans la migration

## 🔧 Corrections Nécessaires

### Mapping des Colonnes

| Seeder (Ancien) | Migration (Correct) |
|-----------------|---------------------|
| `name` | ❌ N'existe pas |
| `email` | ✅ `email` |
| `password` | ❌ `password_hash` |
| `role` | ❌ `user_type` |
| `phone` | ❌ `phone_number` |
| `email_verified_at` | ❌ N'existe pas |
| `account_status` | ✅ Utiliser `ACTIVE` |

## ✅ Fichiers Corrigés

1. ✅ `AdminSeeder.php` - Corrigé
2. ✅ `CreateAdminCommand.php` - Corrigé
3. ⚠️ `CompletePlatformSeeder.php` - À corriger

## 🚀 Solution Recommandée

### Option 1: Mettre à Jour la Migration (Recommandé)

Modifier la migration pour correspondre aux conventions Laravel:

```php
Schema::create('users', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name')->nullable();              // AJOUTÉ
    $table->string('email', 255)->unique();
    $table->string('password');                      // RENOMMÉ
    $table->string('role', 50);                      // RENOMMÉ
    $table->string('account_status', 50)->default('PENDING');
    $table->string('phone', 20)->nullable();         // RENOMMÉ
    $table->timestamp('email_verified_at')->nullable(); // AJOUTÉ
    $table->integer('failed_login_attempts')->default(0);
    $table->timestamp('locked_until')->nullable();
    $table->rememberToken();                         // AJOUTÉ
    $table->timestamps();
});
```

### Option 2: Mettre à Jour Tous les Seeders

Modifier tous les seeders pour utiliser:
- `password_hash` au lieu de `password`
- `user_type` au lieu de `role`
- `phone_number` au lieu de `phone`
- Supprimer `name`
- Supprimer `email_verified_at`

## 📝 Commandes de Correction

### Si vous choisissez l'Option 1 (Modifier la migration):

```bash
# 1. Créer une nouvelle migration
php artisan make:migration update_users_table_structure

# 2. Dans la migration, ajouter:
public function up()
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('name')->nullable()->after('id');
        $table->renameColumn('password_hash', 'password');
        $table->renameColumn('user_type', 'role');
        $table->renameColumn('phone_number', 'phone');
        $table->timestamp('email_verified_at')->nullable()->after('password');
        $table->rememberToken();
    });
}

# 3. Exécuter la migration
php artisan migrate

# 4. Puis exécuter les seeders
php artisan db:seed
```

### Si vous choisissez l'Option 2 (Garder la structure actuelle):

Les fichiers suivants doivent être mis à jour:
- `CompletePlatformSeeder.php` - Toutes les insertions users
- Tous les autres seeders qui créent des users

## 🔍 Vérification

Après correction, vérifier la structure:

```bash
php artisan tinker
>>> Schema::getColumnListing('users')
```

Devrait retourner:
```php
[
    "id",
    "email",
    "password_hash" ou "password",
    "user_type" ou "role",
    "account_status",
    "phone_number" ou "phone",
    ...
]
```

## ⚡ Quick Fix (Temporaire)

Pour tester rapidement, vous pouvez créer l'admin manuellement:

```bash
php artisan tinker
>>> DB::table('users')->insert([
    'id' => Str::uuid()->toString(),
    'email' => 'admin@prosartisan.sn',
    'password_hash' => Hash::make('Admin@2026'),
    'user_type' => 'ADMIN',
    'account_status' => 'ACTIVE',
    'phone_number' => '+221 77 000 00 00',
    'created_at' => now(),
    'updated_at' => now(),
]);
```

## 📌 Recommandation Finale

**Je recommande l'Option 1** (modifier la migration) car:
- ✅ Suit les conventions Laravel standard
- ✅ Compatible avec les packages Laravel (Auth, Sanctum, etc.)
- ✅ Plus facile à maintenir
- ✅ Meilleure compatibilité avec la documentation Laravel

---

**Status:** AdminSeeder et CreateAdminCommand corrigés ✅

**À faire:** Décider entre Option 1 ou Option 2 pour CompletePlatformSeeder
