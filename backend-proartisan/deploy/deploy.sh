#!/bin/bash
# ==============================================================================
# Script de déploiement en production — ProsArtisan
# Usage : bash deploy.sh
# ==============================================================================

set -e

echo "🚀 Début du déploiement de ProsArtisan..."

# 1. Activation du mode maintenance
php artisan down || true

# 2. Récupération des dernières modifications
echo "📥 Git Pull..."
git pull origin main

# 3. Dépendances PHP & Composer
echo "📦 Installation des dépendances Composer..."
composer install --no-dev --optimize-autoloader --no-interaction

# 4. Dépendances Frontend & Build Vite
echo "🎨 Compilation des assets Frontend (Inertia/Vite)..."
npm ci --prefer-offline --no-audit
npm run build

# 5. Migrations de base de données
echo "🗄️ Exécution des migrations MySQL..."
php artisan migrate --force

# 6. Optimisation des caches Laravel
echo "⚡ Mise en cache de la configuration et des routes..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 7. Redémarrage propre des Workers Supervisor
echo "🔄 Redémarrage gracieux des Workers..."
php artisan queue:restart

# Si supervisorctl est disponible, vérification du statut
if command -v supervisorctl &> /dev/null; then
    sudo supervisorctl status
fi

# 8. Désactivation du mode maintenance
php artisan up

echo "=========================================================="
echo "🎉 Déploiement ProsArtisan terminé avec succès !"
echo "=========================================================="
