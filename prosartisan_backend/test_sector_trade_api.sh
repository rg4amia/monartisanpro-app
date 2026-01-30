#!/bin/bash

# Test script for sector and trade API endpoints
BASE_URL="https://prosartisan.net/api/v1"

echo "Testing Sector and Trade API endpoints..."
echo "=========================================="

echo ""
echo "1. Testing /reference/sectors endpoint:"
echo "--------------------------------------"
curl -s -H "Accept: application/json" "$BASE_URL/reference/sectors" | jq '.'

echo ""
echo "2. Testing /reference/sectors/1/trades endpoint:"
echo "-----------------------------------------------"
curl -s -H "Accept: application/json" "$BASE_URL/reference/sectors/1/trades" | jq '.'

echo ""
echo "3. Testing /reference/trades/all endpoint:"
echo "-----------------------------------------"
curl -s -H "Accept: application/json" "$BASE_URL/reference/trades/all" | jq '.'

echo ""
echo "4. Testing original /reference/trades endpoint (sectors with trades):"
echo "--------------------------------------------------------------------"
curl -s -H "Accept: application/json" "$BASE_URL/reference/trades" | jq '.'

echo ""
echo "Testing completed!"
