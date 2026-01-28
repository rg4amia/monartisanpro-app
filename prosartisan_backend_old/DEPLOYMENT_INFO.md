# Informations de Déploiement ProSartisan

## 📋 Informations Serveur

| Paramètre | Valeur |
|-----------|--------|
| **Provider** | Hostinger |
| **Host IP** | 145.79.14.250 |
| **SSH Port** | 65002 |
| **Username** | u398732316 |
| **Domain** | prosartisan.net |
| **Panel** | https://hpanel.hostinger.com/ |

## 📁 Structure des Dossiers

```
/home/u398732316/
├── domains/
│   └── prosartisan.net/
│       └── public_html/                              ← Document Root (accessible via web)
│           ├── .htaccess                             ← Redirige vers Laravel
│           └── monartisanpro-app/
│               └── prosartisan_backend/              ← Application Laravel
│                   ├── app/
│                   ├── bootstrap/
│                   ├── config/
│                   ├── database/
│                   ├── public/                       ← Point d'entrée réel
│                   │   ├── index.php
│                   │   └── build/                    ← Assets compilés
│                   ├── resources/
│                   ├── routes/
│                   ├── storage/                      ← Logs et fichiers
│                   ├── vendor/
│                   ├── .env                          ← Configuration production
│                   └── artisan
├── backups/                                          ← Backups automatiques
│   └── backup-YYYYMMDD-HHMMSS/
├── .env.prod                                         ← Template .env production
└── .env.backup                                       ← Backup temporaire .env
```

## 🔐 Authentification SSH

### Clé SSH Locale
```bash
~/.ssh/hostinger_deploy      # Clé privée (NE JAMAIS PARTAGER)
~/.ssh/hostinger_deploy.pub  # Clé publique (ajoutée à Hostinger)
```

### Commande de Connexion
```bash
ssh -i ~/.ssh/hostinger_deploy -p 65002 u398732316@145.79.14.250
```

## 🚀 Workflow de Déploiement

### Déclencheurs
- Push sur la branche `master`
- Déclenchement manuel via GitHub Actions

### Étapes du Déploiement
1. **Checkout code** - Récupère le code source
2. **Setup Node.js** - Configure Node.js 20
3. **Setup PHP** - Configure PHP 8.2
4. **Install PHP dependencies** - `composer install --no-dev --optimize-autoloader`
5. **Install Node dependencies** - `npm ci`
6. **Build assets** - `npm run build`
7. **Verify build** - Vérifie que `public/build` existe
8. **Create archive** - Crée `deploy.tar.gz` (sans node_modules, tests, etc.)
9. **Deploy to Hostinger** - Backup de l'installation existante
10. **Upload archive** - Upload via SCP
11. **Extract and configure** - Extraction, permissions, migrations, caches, .htaccess

### Durée Estimée
- Build local : ~3-5 minutes
- Upload : ~1-2 minutes
- Configuration serveur : ~2-3 minutes
- **Total : ~6-10 minutes**

## 🔧 Configuration Laravel

### Variables d'Environnement Importantes

```env
# Application
APP_NAME="ProSartisan"
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://prosartisan.net

# Database (PostgreSQL)
DB_CONNECTION=pgsql
DB_HOST=...
DB_PORT=5432
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

# Cache & Sessions
CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=error
```

### Permissions Requises
```bash
storage/                    → 775
storage/logs/              → 775
bootstrap/cache/           → 775
```

## 🌐 URLs de l'Application

| Type | URL |
|------|-----|
| **Site Principal** | https://prosartisan.net |
| **API Base** | https://prosartisan.net/api/v1 |
| **API Docs** | https://prosartisan.net/api/v1/docs |
| **API Spec** | https://prosartisan.net/api/v1/docs/spec |
| **Backoffice** | https://prosartisan.net/backoffice |

## 📊 Monitoring

### Logs Laravel
```bash
tail -f /home/u398732316/domains/prosartisan.net/public_html/monartisanpro-app/prosartisan_backend/storage/logs/laravel.log
```

### Charge Serveur
```bash
uptime
# Dernière mesure : 9.67, 10.32, 10.29 (élevé)
```

### Espace Disque
```bash
df -h /home/u398732316/domains/prosartisan.net/public_html/
```

## 🔄 Commandes de Maintenance

### Déploiement Manuel
```bash
# Sur votre machine locale
git add .
git commit -m "Deploy changes"
git push origin master
```

### Rollback (en cas de problème)
```bash
# Sur le serveur
cd /home/u398732316/backups
ls -lt  # Voir les backups disponibles

# Restaurer un backup
cp -r backup-YYYYMMDD-HHMMSS/* /home/u398732316/domains/prosartisan.net/public_html/monartisanpro-app/prosartisan_backend/
```

### Vider les Caches
```bash
cd /home/u398732316/domains/prosartisan.net/public_html/monartisanpro-app/prosartisan_backend
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Reconstruire les Caches
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Migrations
```bash
# Voir le statut
php artisan migrate:status

# Lancer les migrations
php artisan migrate --force

# Rollback (attention!)
php artisan migrate:rollback --force
```

## 🔒 Sécurité

### Secrets GitHub (Repository Settings)
- `HOST` - IP du serveur
- `PORT` - Port SSH
- `USERNAME` - Nom d'utilisateur SSH
- `SSH_PRIVATE_KEY` - Clé privée SSH complète
- `SSH_PASSPHRASE` - Passphrase de la clé (optionnel)

### Fichiers Sensibles (NE JAMAIS COMMITER)
- `.env` - Configuration production
- `~/.ssh/hostinger_deploy` - Clé privée SSH
- `storage/logs/*.log` - Logs pouvant contenir des données sensibles

### Bonnes Pratiques
- ✅ Toujours utiliser HTTPS
- ✅ APP_DEBUG=false en production
- ✅ Logs en mode ERROR uniquement
- ✅ Backups automatiques avant chaque déploiement
- ✅ Clé SSH avec passphrase
- ✅ Secrets GitHub pour les credentials

## 📞 Support

### Hostinger Support
- Panel : https://hpanel.hostinger.com/
- Support : Via le panel Hostinger

### Documentation
- `QUICK_DEPLOY_GUIDE.md` - Guide de déploiement rapide
- `SSH_CONFIG_VERIFIED.md` - Configuration SSH vérifiée
- `HOSTINGER_SETUP_GUIDE.md` - Guide de configuration Hostinger
- `GITHUB_SECRETS_SETUP.md` - Configuration des secrets GitHub

## 📝 Notes

- **Dernière connexion réussie** : 28 janvier 2026, 19:21 UTC
- **Charge serveur** : Élevée (9.67, 10.32, 10.29) - À surveiller
- **Tentatives échouées** : 2 depuis la dernière connexion réussie
- **Version PHP** : À vérifier sur le serveur
- **Version Composer** : À vérifier sur le serveur

## ✅ Checklist de Déploiement

### Avant le Premier Déploiement
- [ ] Secrets GitHub configurés
- [ ] Clé SSH ajoutée à Hostinger
- [ ] Structure de dossiers créée sur le serveur
- [ ] Fichier .env.prod créé
- [ ] Base de données PostgreSQL configurée

### Après Chaque Déploiement
- [ ] Vérifier que le site est accessible
- [ ] Vérifier les logs Laravel (pas d'erreurs)
- [ ] Tester les routes API principales
- [ ] Vérifier que les assets se chargent
- [ ] Tester une fonctionnalité critique

### Maintenance Régulière
- [ ] Surveiller l'espace disque
- [ ] Surveiller la charge serveur
- [ ] Nettoyer les vieux backups (garder les 10 derniers)
- [ ] Nettoyer les vieux logs (rotation automatique)
- [ ] Vérifier les mises à jour de sécurité
