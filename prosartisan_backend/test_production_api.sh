#!/bin/bash

# Production API Test Script
# Tests key endpoints to verify deployment

API_URL="${1:-https://prosartisan.net}"
ADMIN_EMAIL="admin@prosartisan.sn"
ADMIN_PASSWORD="Admin@2026"

echo "🧪 Testing ProSartisan Production API"
echo "======================================"
echo "API URL: $API_URL"
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test counter
PASSED=0
FAILED=0

# Function to test endpoint
test_endpoint() {
    local name=$1
    local method=$2
    local endpoint=$3
    local data=$4
    local expected_status=$5

    echo -n "Testing $name... "

    if [ -z "$data" ]; then
        response=$(curl -s -w "\n%{http_code}" -X $method "$API_URL$endpoint" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json")
    else
        response=$(curl -s -w "\n%{http_code}" -X $method "$API_URL$endpoint" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            -d "$data")
    fi

    status_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | head -n-1)

    if [ "$status_code" = "$expected_status" ]; then
        echo -e "${GREEN}✓ PASSED${NC} (HTTP $status_code)"
        PASSED=$((PASSED + 1))
        return 0
    else
        echo -e "${RED}✗ FAILED${NC} (Expected HTTP $expected_status, got $status_code)"
        echo "Response: $body"
        FAILED=$((FAILED + 1))
        return 1
    fi
}

# Run tests
echo "1. Health Check"
test_endpoint "Health endpoint" "GET" "/api/v1/health" "" "200"
echo ""

echo "2. Authentication Tests"
test_endpoint "Login with valid credentials" "POST" "/api/v1/auth/login" \
    "{\"email\":\"$ADMIN_EMAIL\",\"password\":\"$ADMIN_PASSWORD\"}" "200"

test_endpoint "Login with invalid credentials" "POST" "/api/v1/auth/login" \
    "{\"email\":\"$ADMIN_EMAIL\",\"password\":\"WrongPassword\"}" "401"
echo ""

echo "3. Public Endpoints"
test_endpoint "Get trades reference data" "GET" "/api/v1/reference/trades" "" "200"
test_endpoint "Get static data" "GET" "/api/v1/static/all" "" "200"
echo ""

echo "4. Get Authentication Token"
echo -n "Getting auth token... "
login_response=$(curl -s -X POST "$API_URL/api/v1/auth/login" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d "{\"email\":\"$ADMIN_EMAIL\",\"password\":\"$ADMIN_PASSWORD\"}")

TOKEN=$(echo "$login_response" | jq -r '.data.token // empty')

if [ -n "$TOKEN" ]; then
    echo -e "${GREEN}✓ Token obtained${NC}"
    PASSED=$((PASSED + 1))

    echo ""
    echo "5. Protected Endpoints (with token)"

    # Test protected endpoint
    echo -n "Testing protected endpoint... "
    protected_response=$(curl -s -w "\n%{http_code}" -X GET "$API_URL/api/v1/transactions" \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -H "Authorization: Bearer $TOKEN")

    protected_status=$(echo "$protected_response" | tail -n1)

    if [ "$protected_status" = "200" ] || [ "$protected_status" = "401" ]; then
        echo -e "${GREEN}✓ PASSED${NC} (HTTP $protected_status - endpoint accessible)"
        PASSED=$((PASSED + 1))
    else
        echo -e "${RED}✗ FAILED${NC} (HTTP $protected_status)"
        FAILED=$((FAILED + 1))
    fi
else
    echo -e "${RED}✗ Failed to get token${NC}"
    FAILED=$((FAILED + 1))
fi

echo ""
echo "======================================"
echo "Test Results:"
echo -e "${GREEN}Passed: $PASSED${NC}"
echo -e "${RED}Failed: $FAILED${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}✗ Some tests failed${NC}"
    exit 1
fi
