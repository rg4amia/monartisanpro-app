# Guide de Configuration Hostinger pour ProSartisan

## Problème
Le serveur Hostinger exécute les fichiers depuis `/home/u398732316/domains/prosartisan.net/public_html/`, mais l'application Laravel se trouve dans `/home/u398732316/domains/prosartisan.net/public_html/monartisanpro-app/prosartisan_backend/`.

## Solutions

### Solution 1: Configuration .htaccess (Automatique via CI/CD)
Le CI/CD crée automatiquement un fichier `.htaccess` à la racine qui redirige tout le trafic vers le dossier `public` de Laravel.

### Solution 2: Configuration Manuelle via cPanel

Si vous préférez configurer manuellement :

1. **Connectez-vous à cPanel Hostinger**

2. **Option A: Modifier le Document Root**
   - Allez dans "Domains" → "prosartisan.net"
   - Cliquez sur "Manage"
   - Changez le "Document Root" de:
     ```
     /public_html
     ```
     vers:
     ```
     /public_html/monartisanpro-app/prosartisan_backend/public
     ```
   - Sauvegardez

3. **Option B: Créer un .htaccess à la racine**
   - Allez dans "File Manager"
   - Naviguez vers `/public_html/`
   - Créez/éditez le fichier `.htaccess`
   - Ajoutez ce contenu:
     ```apache
     <IfModule mod_rewrite.c>
         RewriteEngine On
         
         # Redirect all requests to Laravel public folder
         RewriteCond %{REQUEST_URI} !^/monartisanpro-app/prosartisan_backend/public
         RewriteRule ^(.*)$ /monartisanpro-app/prosartisan_backend/public/$1 [L]
     </IfModule>
     ```

### Solution 3: Symlink (Alternative)
```bash
# Via SSH
cd /home/u398732316/domains/prosartisan.net/public_html
rm -rf index.php  # Supprimer l'ancien index si existe
ln -s monartisanpro-app/prosartisan_backend/public/* .
```

## Vérification

Après configuration, vérifiez que:
1. `https://prosartisan.net` charge l'application Laravel
2. Les assets sont accessibles: `https://prosartisan.net/build/assets/...`
3. Les routes API fonctionnent: `https://prosartisan.net/api/v1/...`

## Structure des Dossiers

```
/home/u398732316/
├── domains/
│   └── prosartisan.net/
│       └── public_html/                    ← Document Root du serveur
│           ├── .htaccess                   ← Redirige vers Laravel
│           └── monartisanpro-app/
│               └── prosartisan_backend/    ← Application Laravel
│                   ├── app/
│                   ├── public/             ← Vrai point d'entrée Laravel
│                   │   └── index.php
│                   ├── storage/
│                   └── .env
```

## Permissions Requises

```bash
# Depuis SSH
cd /home/u398732316/domains/prosartisan.net/public_html/monartisanpro-app/prosartisan_backend

# Storage et cache
chmod -R 775 storage
chmod -R 775 bootstrap/cache

# Fichiers
find storage -type f -exec chmod 664 {} \;
find storage -type d -exec chmod 775 {} \;
```

## Variables d'Environnement (.env)

Assurez-vous que le fichier `.env` contient:

```env
APP_URL=https://prosartisan.net
APP_ENV=production
APP_DEBUG=false

# Database
DB_CONNECTION=pgsql
DB_HOST=your-hostinger-db-host
DB_PORT=5432
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

## Dépannage

### Erreur 500
- Vérifiez les permissions de `storage/` et `bootstrap/cache/`
- Vérifiez les logs: `storage/logs/laravel.log`
- Exécutez: `php artisan config:clear`

### Assets non chargés
- Vérifiez que le `.htaccess` redirige correctement
- Vérifiez que `public/build/` existe
- Exécutez: `npm run build` localement puis redéployez

### Routes API 404
- Vérifiez le `.htaccess` dans `public/`
- Exécutez: `php artisan route:cache`
- Vérifiez `APP_URL` dans `.env`

## Commandes Utiles

```bash
# Via SSH sur le serveur
cd /home/u398732316/domains/prosartisan.net/public_html/monartisanpro-app/prosartisan_backend

# Vider les caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Reconstruire les caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Migrations
php artisan migrate --force

# Vérifier la configuration
php artisan about
```
