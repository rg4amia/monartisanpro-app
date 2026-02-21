#!/bin/bash

# Script pour tester l'API de recherche d'artisans
# Usage: ./test_api_search.sh

# Couleurs pour l'affichage
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
BASE_URL="https://prosartisan.net/api/v1"
# Pour local: BASE_URL="http://localhost:8000/api/v1"

# Point de référence: Cocody, Abidjan
LAT=5.3364
LNG=-4.0267

echo -e "${BLUE}🔍 Test de l'API de recherche d'artisans${NC}"
echo "=========================================="
echo ""

# Test 1: Recherche tous les artisans (rayon 5km)
echo -e "${GREEN}Test 1: Tous les artisans dans un rayon de 5km${NC}"
echo "URL: ${BASE_URL}/artisans/search"
echo "Params: latitude=${LAT}, longitude=${LNG}, radius=5000"
echo ""

curl -s -X POST "${BASE_URL}/artisans/search" \
  -G \
  --data-urlencode "latitude=${LAT}" \
  --data-urlencode "longitude=${LNG}" \
  --data-urlencode "radius=5000" \
  -H "Accept: application/json" | jq '.'

echo ""
echo "---"
echo ""

# Test 2: Recherche avec rayon 10km
echo -e "${GREEN}Test 2: Tous les artisans dans un rayon de 10km${NC}"
echo "URL: ${BASE_URL}/artisans/search"
echo "Params: latitude=${LAT}, longitude=${LNG}, radius=10000"
echo ""

curl -s -X GET "${BASE_URL}/artisans/search" \
  -G \
  --data-urlencode "latitude=${LAT}" \
  --data-urlencode "longitude=${LNG}" \
  --data-urlencode "radius=10000" \
  -H "Accept: application/json" | jq '.data | length'

echo " artisan(s) trouvé(s)"
echo ""
echo "---"
echo ""

# Test 3: Recherche par métier (Électricien)
echo -e "${GREEN}Test 3: Recherche d'électriciens${NC}"
echo "URL: ${BASE_URL}/artisans/search"
echo "Params: latitude=${LAT}, longitude=${LNG}, radius=10000, trade_id=2"
echo ""

curl -s -X GET "${BASE_URL}/artisans/search" \
  -G \
  --data-urlencode "latitude=${LAT}" \
  --data-urlencode "longitude=${LNG}" \
  --data-urlencode "radius=10000" \
  --data-urlencode "trade_id=2" \
  -H "Accept: application/json" | jq '.data[] | {name: .name, trade: .profile.trade_name, distance: .distance}'

echo ""
echo "---"
echo ""

# Test 4: Recherche par secteur (Bâtiment)
echo -e "${GREEN}Test 4: Recherche dans le secteur Bâtiment${NC}"
echo "URL: ${BASE_URL}/artisans/search"
echo "Params: latitude=${LAT}, longitude=${LNG}, radius=10000, sector_id=1"
echo ""

curl -s -X GET "${BASE_URL}/artisans/search" \
  -G \
  --data-urlencode "latitude=${LAT}" \
  --data-urlencode "longitude=${LNG}" \
  --data-urlencode "radius=10000" \
  --data-urlencode "sector_id=1" \
  -H "Accept: application/json" | jq '.data[] | {name: .name, trade: .profile.trade_name, sector: .profile.sector_name}'

echo ""
echo "---"
echo ""

# Test 5: Recherche avec tri par distance
echo -e "${GREEN}Test 5: Tri par distance (les 3 plus proches)${NC}"
echo "URL: ${BASE_URL}/artisans/search"
echo "Params: latitude=${LAT}, longitude=${LNG}, radius=10000, sort_by=distance, limit=3"
echo ""

curl -s -X GET "${BASE_URL}/artisans/search" \
  -G \
  --data-urlencode "latitude=${LAT}" \
  --data-urlencode "longitude=${LNG}" \
  --data-urlencode "radius=10000" \
  --data-urlencode "sort_by=distance" \
  --data-urlencode "limit=3" \
  -H "Accept: application/json" | jq '.data[] | {name: .name, distance: .distance, zone: .profile.zone_name}'

echo ""
echo "---"
echo ""

# Test 6: Recherche avec score minimum
echo -e "${GREEN}Test 6: Artisans avec score minimum 70${NC}"
echo "URL: ${BASE_URL}/artisans/search"
echo "Params: latitude=${LAT}, longitude=${LNG}, radius=10000, min_score=70"
echo ""

curl -s -X GET "${BASE_URL}/artisans/search" \
  -G \
  --data-urlencode "latitude=${LAT}" \
  --data-urlencode "longitude=${LNG}" \
  --data-urlencode "radius=10000" \
  --data-urlencode "min_score=70" \
  -H "Accept: application/json" | jq '.data[] | {name: .name, score: .score.total_score}'

echo ""
echo "---"
echo ""

# Test 7: Différentes zones d'Abidjan
echo -e "${YELLOW}Test 7: Recherche depuis différentes zones${NC}"
echo ""

declare -A zones=(
    ["Plateau"]="5.3333,-4.0333"
    ["Marcory"]="5.3500,-4.0100"
    ["Adjamé"]="5.3200,-4.0500"
    ["Yopougon"]="5.3450,-4.0350"
)

for zone in "${!zones[@]}"; do
    IFS=',' read -r lat lng <<< "${zones[$zone]}"
    echo -e "${BLUE}Zone: ${zone}${NC} (${lat}, ${lng})"

    count=$(curl -s -X GET "${BASE_URL}/artisans/search" \
      -G \
      --data-urlencode "latitude=${lat}" \
      --data-urlencode "longitude=${lng}" \
      --data-urlencode "radius=5000" \
      -H "Accept: application/json" | jq '.data | length')

    echo "  → ${count} artisan(s) dans un rayon de 5km"
    echo ""
done

echo ""
echo -e "${GREEN}✅ Tests terminés!${NC}"
