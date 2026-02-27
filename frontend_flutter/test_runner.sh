#!/bin/bash

# Script pour exécuter tous les tests unitaires Flutter avec le backend Herd
# URL Backend: http://backend-proartisan.test/

echo "🚀 ProsArtisan - Exécution des tests unitaires"
echo "================================================"
echo "Backend URL: http://backend-proartisan.test/"
echo ""

# Vérifier que Flutter est installé
if ! command -v flutter &> /dev/null; then
    echo "❌ Flutter n'est pas installé ou n'est pas dans le PATH"
    exit 1
fi

# Vérifier que le backend Herd est accessible
echo "🔍 Vérification de la connexion au backend Herd..."
if curl -s -o /dev/null -w "%{http_code}" http://backend-proartisan.test/api/v1/sectors | grep -q "200\|401"; then
    echo "✅ Backend Herd accessible"
else
    echo "⚠️  Backend Herd non accessible - certains tests pourraient échouer"
    echo "   Assurez-vous que Laravel Herd est démarré"
fi

echo ""
echo "📦 Installation des dépendances..."
cd frontend_flutter
flutter pub get

echo ""
echo "🧪 Exécution des tests unitaires..."
echo "================================================"

# Exécuter tous les tests
flutter test --coverage

# Vérifier le résultat
if [ $? -eq 0 ]; then
    echo ""
    echo "================================================"
    echo "✅ Tous les tests sont passés avec succès!"
    echo "================================================"
    
    # Afficher le rapport de couverture si disponible
    if [ -f "coverage/lcov.info" ]; then
        echo ""
        echo "📊 Rapport de couverture généré: coverage/lcov.info"
        echo "   Pour visualiser: genhtml coverage/lcov.info -o coverage/html"
    fi
else
    echo ""
    echo "================================================"
    echo "❌ Certains tests ont échoué"
    echo "================================================"
    exit 1
fi
