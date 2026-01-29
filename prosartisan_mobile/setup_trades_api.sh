#!/bin/bash

echo "🚀 Configuration de l'API des métiers pour ProSartisan Mobile"

# Vérifier si nous sommes dans le bon répertoire
if [ ! -f "pubspec.yaml" ]; then
    echo "❌ Erreur: Ce script doit être exécuté depuis le répertoire prosartisan_mobile"
    exit 1
fi

echo "📦 Installation des dépendances..."
flutter pub get

echo "🔧 Génération des fichiers JSON..."
flutter packages pub run build_runner build --delete-conflicting-outputs

echo "🧪 Vérification de la compilation..."
flutter analyze

echo "✅ Configuration terminée!"
echo ""
echo "📱 Pour tester l'API des métiers:"
echo "1. Assurez-vous que le backend Laravel est démarré"
echo "2. Lancez l'application mobile: flutter run"
echo "3. Naviguez vers 'Design System Demo'"
echo "4. Cliquez sur 'Tester la récupération des métiers'"
echo ""
echo "🔗 Endpoint utilisé: /api/v1/reference/trades"