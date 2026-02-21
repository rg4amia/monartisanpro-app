#!/bin/bash

# Script to download assets from Figma
# Note: The Figma asset URLs expire in 7 days

# Create assets directory if it doesn't exist
mkdir -p assets/images

echo "Downloading assets from Figma..."
echo ""

# Download the onboarding image
echo "📥 Downloading onboarding_1.png..."
curl -o assets/images/onboarding_1.png "https://www.figma.com/api/mcp/asset/1e07477e-19f7-46de-9a4c-5cd5462661cc"
echo "✅ Downloaded onboarding_1.png"
echo ""

# Download the login background
echo "📥 Downloading login_background.png..."
curl -o assets/images/login_background.png "https://www.figma.com/api/mcp/asset/70f9dca3-266f-40c7-a398-cc7147aff750"
echo "✅ Downloaded login_background.png"
echo ""

echo "⚠️  Note: You still need to add 2 more onboarding images:"
echo "   - assets/images/onboarding_2.png"
echo "   - assets/images/onboarding_3.png"
echo ""
echo "📝 Don't forget to update pubspec.yaml with:"
echo "   flutter:"
echo "     assets:"
echo "       - assets/images/"
echo ""
echo "Then run: flutter pub get"
