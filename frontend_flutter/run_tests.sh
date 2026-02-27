#!/bin/bash

# Script pour exécuter les tests Flutter ProsArtisan
# Backend: http://backend-proartisan.test/

set -e

echo "🚀 ProsArtisan - Tests Flutter"
echo "================================"
echo ""

# Couleurs
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Vérifier Flutter
if ! command -v flutter &> /dev/null; then
    echo -e "${RED}❌ Flutter n'est pas installé${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Flutter installé${NC}"

# Vérifier le backend Herd
echo ""
echo "🔍 Vérification du backend Herd..."
if curl -s -f -o /dev/null http://backend-proartisan.test 2>/dev/null; then
    echo -e "${GREEN}✅ Backend Herd accessible${NC}"
else
    echo -e "${YELLOW}⚠️  Backend Herd non accessible${NC}"
    echo "   Les tests d'intégration pourraient échouer"
fi

# Installation des dépendances
echo ""
echo "📦 Installation des dépendances..."
flutter pub get > /dev/null 2>&1
echo -e "${GREEN}✅ Dépendances installées${NC}"

# Exécuter les tests
echo ""
echo "🧪 Exécution des tests unitaires..."
echo "================================"
echo ""

if flutter test test/unit_tests_simple.dart; then
    echo ""
    echo "================================"
    echo -e "${GREEN}✅ Tous les tests sont passés!${NC}"
    echo "================================"
    echo ""
    echo "📊 Résumé:"
    echo "   - Tests unitaires: ✅"
    echo "   - Modèles de données: ✅"
    echo "   - Logique métier: ✅"
    echo "   - Configuration API: ✅"
    echo ""
    echo "Pour exécuter les tests d'intégration:"
    echo "   flutter test test/data/repositories/"
    echo "   flutter test test/integration/"
    echo ""
else
    echo ""
    echo "================================"
    echo -e "${RED}❌ Certains tests ont échoué${NC}"
    echo "================================"
    exit 1
fi
