#!/bin/bash

# Script de vérification de la configuration du serveur Hostinger
# Usage: ./verify_server_setup.sh

echo "🔍 Vérification de la configuration du serveur Hostinger..."
echo ""

# Couleurs pour l'affichage
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Variables
DEPLOY_PATH="/home/u398732316/domains/prosartisan.net/public_html/monartisanpro-app/prosartisan_backend"
PUBLIC_HTML="/home/u398732316/domains/prosartisan.net/public_html"

echo "📁 Vérification de la structure des dossiers..."

# Vérifier public_html
if [ -d "$PUBLIC_HTML" ]; then
    echo -e "${GREEN}✓${NC} $PUBLIC_HTML existe"
else
    echo -e "${RED}✗${NC} $PUBLIC_HTML n'existe pas"
fi

# Vérifier monartisanpro-app
if [ -d "$PUBLIC_HTML/monartisanpro-app" ]; then
    echo -e "${GREEN}✓${NC} $PUBLIC_HTML/monartisanpro-app existe"
else
    echo -e "${YELLOW}⚠${NC} $PUBLIC_HTML/monartisanpro-app n'existe pas - création..."
    mkdir -p "$PUBLIC_HTML/monartisanpro-app"
fi

# Vérifier prosartisan_backend
if [ -d "$DEPLOY_PATH" ]; then
    echo -e "${GREEN}✓${NC} $DEPLOY_PATH existe"
else
    echo -e "${YELLOW}⚠${NC} $DEPLOY_PATH n'existe pas - création..."
    mkdir -p "$DEPLOY_PATH"
fi

echo ""
echo "🔧 Vérification des outils..."

# Vérifier PHP
if command -v php &> /dev/null; then
    PHP_VERSION=$(php -v | head -n 1)
    echo -e "${GREEN}✓${NC} PHP installé: $PHP_VERSION"
else
    echo -e "${RED}✗${NC} PHP non trouvé"
fi

# Vérifier Composer
if command -v composer &> /dev/null; then
    COMPOSER_VERSION=$(composer --version | head -n 1)
    echo -e "${GREEN}✓${NC} Composer installé: $COMPOSER_VERSION"
else
    echo -e "${RED}✗${NC} Composer non trouvé"
fi

# Vérifier Node
if command -v node &> /dev/null; then
    NODE_VERSION=$(node -v)
    echo -e "${GREEN}✓${NC} Node.js installé: $NODE_VERSION"
else
    echo -e "${YELLOW}⚠${NC} Node.js non trouvé (pas critique pour le déploiement)"
fi

echo ""
echo "💾 Vérification de l'espace disque..."
df -h "$PUBLIC_HTML" | tail -n 1

echo ""
echo "📊 Charge du serveur..."
uptime

echo ""
echo "🔐 Vérification des permissions..."

# Vérifier les permissions de public_html
PERMS=$(stat -c "%a" "$PUBLIC_HTML" 2>/dev/null || stat -f "%A" "$PUBLIC_HTML" 2>/dev/null)
echo "Permissions de $PUBLIC_HTML: $PERMS"

if [ -d "$DEPLOY_PATH" ]; then
    # Vérifier storage si existe
    if [ -d "$DEPLOY_PATH/storage" ]; then
        STORAGE_PERMS=$(stat -c "%a" "$DEPLOY_PATH/storage" 2>/dev/null || stat -f "%A" "$DEPLOY_PATH/storage" 2>/dev/null)
        echo "Permissions de $DEPLOY_PATH/storage: $STORAGE_PERMS"

        if [ "$STORAGE_PERMS" -ge "755" ]; then
            echo -e "${GREEN}✓${NC} Permissions storage OK"
        else
            echo -e "${YELLOW}⚠${NC} Permissions storage à ajuster"
            echo "Exécutez: chmod -R 775 $DEPLOY_PATH/storage"
        fi
    fi

    # Vérifier bootstrap/cache si existe
    if [ -d "$DEPLOY_PATH/bootstrap/cache" ]; then
        CACHE_PERMS=$(stat -c "%a" "$DEPLOY_PATH/bootstrap/cache" 2>/dev/null || stat -f "%A" "$DEPLOY_PATH/bootstrap/cache" 2>/dev/null)
        echo "Permissions de $DEPLOY_PATH/bootstrap/cache: $CACHE_PERMS"

        if [ "$CACHE_PERMS" -ge "755" ]; then
            echo -e "${GREEN}✓${NC} Permissions cache OK"
        else
            echo -e "${YELLOW}⚠${NC} Permissions cache à ajuster"
            echo "Exécutez: chmod -R 775 $DEPLOY_PATH/bootstrap/cache"
        fi
    fi
fi

echo ""
echo "📝 Vérification du .env..."

if [ -f "$DEPLOY_PATH/.env" ]; then
    echo -e "${GREEN}✓${NC} Fichier .env existe"

    # Vérifier les variables importantes
    if grep -q "APP_KEY=" "$DEPLOY_PATH/.env" && ! grep -q "APP_KEY=$" "$DEPLOY_PATH/.env"; then
        echo -e "${GREEN}✓${NC} APP_KEY est défini"
    else
        echo -e "${RED}✗${NC} APP_KEY n'est pas défini"
        echo "Exécutez: cd $DEPLOY_PATH && php artisan key:generate"
    fi

    if grep -q "APP_ENV=production" "$DEPLOY_PATH/.env"; then
        echo -e "${GREEN}✓${NC} APP_ENV=production"
    else
        echo -e "${YELLOW}⚠${NC} APP_ENV n'est pas en production"
    fi

    if grep -q "APP_DEBUG=false" "$DEPLOY_PATH/.env"; then
        echo -e "${GREEN}✓${NC} APP_DEBUG=false"
    else
        echo -e "${YELLOW}⚠${NC} APP_DEBUG devrait être false en production"
    fi
else
    echo -e "${RED}✗${NC} Fichier .env n'existe pas"
    echo "Créez un fichier .env basé sur .env.example"
fi

echo ""
echo "🌐 Vérification du .htaccess racine..."

if [ -f "$PUBLIC_HTML/.htaccess" ]; then
    echo -e "${GREEN}✓${NC} .htaccess racine existe"

    if grep -q "monartisanpro-app/prosartisan_backend/public" "$PUBLIC_HTML/.htaccess"; then
        echo -e "${GREEN}✓${NC} .htaccess redirige vers Laravel"
    else
        echo -e "${YELLOW}⚠${NC} .htaccess ne redirige pas vers Laravel"
    fi
else
    echo -e "${YELLOW}⚠${NC} .htaccess racine n'existe pas"
    echo "Le CI/CD le créera automatiquement lors du déploiement"
fi

echo ""
echo "📦 Vérification de l'application Laravel..."

if [ -f "$DEPLOY_PATH/artisan" ]; then
    echo -e "${GREEN}✓${NC} Application Laravel détectée"

    # Vérifier les dossiers Laravel essentiels
    for dir in app bootstrap config database public resources routes storage vendor; do
        if [ -d "$DEPLOY_PATH/$dir" ]; then
            echo -e "${GREEN}✓${NC} Dossier $dir existe"
        else
            echo -e "${RED}✗${NC} Dossier $dir manquant"
        fi
    done
else
    echo -e "${YELLOW}⚠${NC} Application Laravel non déployée"
    echo "Lancez le déploiement via GitHub Actions"
fi

echo ""
echo "✅ Vérification terminée!"
echo ""
echo "📋 Résumé des actions recommandées:"
echo "1. Configurez les secrets GitHub (voir SSH_CONFIG_VERIFIED.md)"
echo "2. Créez/vérifiez le fichier .env sur le serveur"
echo "3. Lancez le déploiement via GitHub Actions"
echo "4. Vérifiez que https://prosartisan.net fonctionne"
