#!/bin/bash

echo "Testing API Login..."
echo "==================="

# Test with a seeded client account
echo "Testing with client1@prosartisan.sn..."
curl -X POST "https://prosartisan.net/api/v1/auth/login" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"client1@prosartisan.sn","password":"password"}' \
  -w "\nHTTP Status: %{http_code}\n" \
  -s

echo -e "\n\n"

# Test with a seeded artisan account
echo "Testing with artisan1@prosartisan.sn..."
curl -X POST "https://prosartisan.net/api/v1/auth/login" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"artisan1@prosartisan.sn","password":"password"}' \
  -w "\nHTTP Status: %{http_code}\n" \
  -s

echo -e "\n\n"

# Test with invalid credentials
echo "Testing with invalid credentials..."
curl -X POST "https://prosartisan.net/api/v1/auth/login" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"invalid@example.com","password":"wrongpassword"}' \
  -w "\nHTTP Status: %{http_code}\n" \
  -s

echo -e "\n\nTest completed!"
