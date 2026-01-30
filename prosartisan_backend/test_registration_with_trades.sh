#!/bin/bash

# Test script for registration with dynamic trades
BASE_URL="https://prosartisan.net/api/v1"

echo "Testing Registration with Dynamic Trades..."
echo "=========================================="

echo ""
echo "1. First, let's get available sectors:"
echo "------------------------------------"
curl -s -H "Accept: application/json" "$BASE_URL/reference/sectors" | jq '.data[] | {id, name}'

echo ""
echo "2. Get trades for sector 1 (assuming it exists):"
echo "-----------------------------------------------"
TRADES_RESPONSE=$(curl -s -H "Accept: application/json" "$BASE_URL/reference/sectors/1/trades")
echo "$TRADES_RESPONSE" | jq '.data[] | {code, name}'

# Extract first trade code for testing
TRADE_CODE=$(echo "$TRADES_RESPONSE" | jq -r '.data[0].code // "PLUMBER"')
echo ""
echo "Using trade code: $TRADE_CODE"

echo ""
echo "3. Test artisan registration with dynamic trade:"
echo "----------------------------------------------"
curl -s -X POST \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{
    \"email\": \"test-artisan-$(date +%s)@example.com\",
    \"password\": \"password123\",
    \"password_confirmation\": \"password123\",
    \"user_type\": \"ARTISAN\",
    \"phone_number\": \"+2250712345678\",
    \"trade_category\": \"$TRADE_CODE\"
  }" \
  "$BASE_URL/auth/register" | jq '.'

echo ""
echo "4. Test client registration (should work without trade):"
echo "-------------------------------------------------------"
curl -s -X POST \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{
    \"email\": \"test-client-$(date +%s)@example.com\",
    \"password\": \"password123\",
    \"password_confirmation\": \"password123\",
    \"user_type\": \"CLIENT\",
    \"phone_number\": \"+2250712345679\"
  }" \
  "$BASE_URL/auth/register" | jq '.'

echo ""
echo "5. Test fournisseur registration:"
echo "--------------------------------"
curl -s -X POST \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{
    \"email\": \"test-fournisseur-$(date +%s)@example.com\",
    \"password\": \"password123\",
    \"password_confirmation\": \"password123\",
    \"user_type\": \"FOURNISSEUR\",
    \"phone_number\": \"+2250712345680\",
    \"business_name\": \"Test Materials Supply\"
  }" \
  "$BASE_URL/auth/register" | jq '.'

echo ""
echo "Testing completed!"
