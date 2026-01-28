# ProSartisan Backend 🏗️

Backend Laravel pour la plateforme ProSartisan - API REST et Backoffice React.

## 🚀 Déploiement Rapide

**✅ Connexion SSH vérifiée** - Prêt pour le déploiement!

### Démarrage Rapide (10 minutes)

1. **Configurez les secrets GitHub** → [QUICK_DEPLOY_GUIDE.md](QUICK_DEPLOY_GUIDE.md)
2. **Préparez le serveur** (créez la structure de dossiers)
3. **Créez le .env de production** sur le serveur
4. **Poussez sur master** ou déclenchez le workflow manuellement
5. **Vérifiez** que https://prosartisan.net fonctionne

📖 **Guide complet** : [QUICK_DEPLOY_GUIDE.md](QUICK_DEPLOY_GUIDE.md)

## 📚 Documentation de Déploiement

| Document | Description |
|----------|-------------|
| [QUICK_DEPLOY_GUIDE.md](QUICK_DEPLOY_GUIDE.md) | 🎯 **Guide de déploiement pas à pas (COMMENCEZ ICI)** |
| [SSH_CONFIG_VERIFIED.md](SSH_CONFIG_VERIFIED.md) | ✅ Configuration SSH vérifiée et testée |
| [DEPLOYMENT_INFO.md](DEPLOYMENT_INFO.md) | 📋 Informations complètes sur le déploiement |
| [HOSTINGER_SETUP_GUIDE.md](HOSTINGER_SETUP_GUIDE.md) | 🔧 Configuration détaillée Hostinger |
| [GITHUB_SECRETS_SETUP.md](GITHUB_SECRETS_SETUP.md) | 🔐 Configuration des secrets GitHub |
| [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) | 📦 Guide de déploiement général |

## 🔧 Configuration Serveur

```
Host: 145.79.14.250
Port: 65002
Username: u398732316
Domain: https://prosartisan.net
Panel: https://hpanel.hostinger.com/
```

## 🏗️ Architecture

Backend Laravel suivant les principes **DDD (Domain-Driven Design)** avec :

- **API REST** pour l'application mobile Flutter
- **Backoffice React** avec Inertia.js
- **PostgreSQL** avec extension PostGIS pour la géolocalisation
- **Authentification OTP** par SMS
- **Paiements Mobile Money** (MTN, Orange, Wave)
- **Système de réputation N'Zassa**
- **Gestion des litiges** avec médiation et arbitrage
- **Séquestre de paiement** avec validation par jetons matériels

### Domaines Métier

```
app/Domain/
├── Identity/        # Gestion des utilisateurs et authentification
├── Marketplace/     # Missions, devis, recherche d'artisans
├── Financial/       # Paiements, séquestres, jetons matériels
├── Worksite/        # Chantiers, jalons, preuves photo
├── Reputation/      # Scores N'Zassa, avis, historique
├── Dispute/         # Litiges, médiation, arbitrage
└── Shared/          # Services et value objects partagés
```

## 🛠️ Stack Technique

- **Framework** : Laravel 11
- **PHP** : 8.2+
- **Database** : PostgreSQL 15+ avec PostGIS
- **Frontend** : React 18 + Inertia.js + Vite
- **Cache** : File (production), Redis (optionnel)
- **Queue** : Sync (production), Redis (optionnel)
- **Storage** : Local filesystem
- **Testing** : Pest PHP

## 📦 Installation Locale

### Prérequis

- PHP 8.2+
- Composer
- Node.js 20+
- PostgreSQL 15+ avec PostGIS
- Git

### Installation

```bash
# Cloner le repository
git clone <repository-url>
cd prosartisan_backend

# Installer les dépendances PHP
composer install

# Installer les dépendances Node
npm install

# Copier le fichier .env
cp .env.example .env

# Générer la clé d'application
php artisan key:generate

# Configurer la base de données dans .env
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=prosartisan
# DB_USERNAME=postgres
# DB_PASSWORD=

# Lancer les migrations
php artisan migrate

# Lancer les seeders
php artisan db:seed

# Compiler les assets
npm run build

# Lancer le serveur de développement
php artisan serve
```

L'application sera accessible sur http://localhost:8000

## 🧪 Tests

```bash
# Lancer tous les tests
php artisan test

# Lancer les tests avec couverture
php artisan test --coverage

# Lancer un test spécifique
php artisan test --filter=AuthenticationTest

# Lancer les tests d'un dossier
php artisan test tests/Feature/Auth
```

## 🎨 Code Style

```bash
# Vérifier le style de code
./vendor/bin/pint --test

# Corriger automatiquement le style
./vendor/bin/pint
```

## 📖 Documentation API

Une fois l'application lancée, la documentation API est accessible sur :

- **Swagger UI** : http://localhost:8000/api/v1/docs
- **OpenAPI Spec** : http://localhost:8000/api/v1/docs/spec

En production : https://prosartisan.net/api/v1/docs

## 🔐 Authentification

L'API utilise un système d'authentification par **OTP (One-Time Password)** :

1. L'utilisateur s'inscrit avec son numéro de téléphone
2. Un code OTP est envoyé par SMS
3. L'utilisateur valide le code OTP
4. Un token JWT est généré pour les requêtes suivantes

### Endpoints Principaux

```
POST /api/v1/auth/register          # Inscription
POST /api/v1/auth/login             # Connexion (envoie OTP)
POST /api/v1/auth/verify-otp        # Vérification OTP
POST /api/v1/auth/refresh           # Rafraîchir le token
POST /api/v1/auth/logout            # Déconnexion
```

## 🚀 Déploiement

Le déploiement est automatisé via **GitHub Actions** :

1. Push sur la branche `master`
2. Le workflow CI/CD se lance automatiquement
3. Build des assets (npm run build)
4. Création d'une archive optimisée
5. Déploiement sur Hostinger via SSH
6. Configuration automatique (permissions, migrations, caches)

**Durée** : ~6-10 minutes

Voir [QUICK_DEPLOY_GUIDE.md](QUICK_DEPLOY_GUIDE.md) pour les détails.

## 📊 Monitoring

### Logs

```bash
# Logs Laravel
tail -f storage/logs/laravel.log

# Logs sur le serveur de production
ssh -i ~/.ssh/hostinger_deploy -p 65002 u398732316@145.79.14.250
tail -f /home/u398732316/domains/prosartisan.net/public_html/monartisanpro-app/prosartisan_backend/storage/logs/laravel.log
```

### Commandes Artisan Utiles

```bash
# Vider les caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Reconstruire les caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Voir les routes
php artisan route:list

# Voir la configuration
php artisan about

# Analyser les performances de la base de données
php artisan db:analyze

# Surveiller la santé du système
php artisan system:monitor
```

## 🔄 Workflow de Développement

1. Créer une branche feature : `git checkout -b feature/ma-fonctionnalite`
2. Développer et tester localement
3. Vérifier le code style : `./vendor/bin/pint`
4. Lancer les tests : `php artisan test`
5. Commit et push : `git push origin feature/ma-fonctionnalite`
6. Créer une Pull Request vers `master`
7. Après merge, le déploiement automatique se lance

## 📝 Structure du Projet

```
prosartisan_backend/
├── app/
│   ├── Application/          # Use Cases et DTOs
│   ├── Domain/              # Logique métier (DDD)
│   ├── Infrastructure/      # Implémentations concrètes
│   ├── Http/               # Controllers, Middleware, Requests
│   └── Providers/          # Service Providers
├── bootstrap/
├── config/                 # Configuration
├── database/
│   ├── migrations/        # Migrations de base de données
│   └── seeders/          # Seeders
├── docs/                  # Documentation technique
├── public/               # Point d'entrée web
├── resources/
│   ├── js/              # React components (Backoffice)
│   ├── css/             # Styles
│   ├── views/           # Blade templates
│   └── lang/            # Traductions (FR/EN)
├── routes/
│   ├── api.php          # Routes API
│   ├── web.php          # Routes web
│   └── backoffice.php   # Routes backoffice
├── storage/             # Logs, cache, uploads
├── tests/
│   ├── Feature/        # Tests d'intégration
│   └── Unit/          # Tests unitaires
└── vendor/            # Dépendances Composer
```

## 🤝 Contribution

1. Fork le projet
2. Créer une branche feature
3. Commit les changements
4. Push vers la branche
5. Ouvrir une Pull Request

## 📄 Licence

Ce projet est propriétaire. Tous droits réservés.

## 📞 Support

Pour toute question ou problème :
- Consultez la documentation dans le dossier `docs/`
- Vérifiez les guides de déploiement
- Contactez l'équipe de développement

---

**Dernière mise à jour** : 28 janvier 2026
**Version** : 1.0.0
**Status** : ✅ Production Ready
