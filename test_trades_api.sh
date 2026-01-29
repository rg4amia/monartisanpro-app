#!/bin/bash

echo "🧪 Test de l'API des métiers ProSartisan"

# Configuration
BACKEND_URL="http://localhost:8000"
API_ENDPOINT="/api/v1/reference/trades"

echo "📡 Test de l'endpoint: ${BACKEND_URL}${API_ENDPOINT}"

# Test de l'API
response=$(curl -s -w "\n%{http_code}" "${BACKEND_URL}${API_ENDPOINT}" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json")

# Séparer le body et le status code
http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | head -n -1)

echo "📊 Status Code: $http_code"

if [ "$http_code" = "200" ]; then
    echo "✅ API accessible!"
    
    # Compter les secteurs et métiers
    sectors_count=$(echo "$body" | jq '.data | length' 2>/dev/null || echo "N/A")
    
    if [ "$sectors_count" != "N/A" ] && [ "$sectors_count" -gt 0 ]; then
        echo "📈 Nombre de secteurs: $sectors_count"
        
        # Compter le total des métiers
        trades_count=$(echo "$body" | jq '[.data[].trades | length] | add' 2>/dev/null || echo "N/A")
        echo "🔧 Nombre total de métiers: $trades_count"
        
        # Afficher quelques exemples
        echo ""
        echo "📋 Exemples de secteurs et métiers:"
        echo "$body" | jq -r '.data[0:3][] | "- \(.name) (\(.code)): \(.trades | length) métiers"' 2>/dev/null || echo "Impossible d'analyser les données"
        
    else
        echo "⚠️  Aucune donnée trouvée. Vérifiez que les seeders ont été exécutés."
    fi
    
else
    echo "❌ Erreur API (Code: $http_code)"
    echo "📄 Réponse:"
    echo "$body"
    
    if [ "$http_code" = "000" ]; then
        echo ""
        echo "💡 Suggestions:"
        echo "1. Vérifiez que le serveur Laravel est démarré"
        echo "2. Lancez: cd prosartisan_backend && php artisan serve"
        echo "3. Vérifiez l'URL: $BACKEND_URL"
    fi
fi

echo ""
echo "🔗 Pour tester manuellement:"
echo "curl -H 'Accept: application/json' ${BACKEND_URL}${API_ENDPOINT}"