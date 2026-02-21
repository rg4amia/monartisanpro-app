# Onboarding Screen Setup Guide

## Overview
The onboarding screen has been updated to use full-screen background images with overlay content, matching the Figma design (node-id: 2901:7162).

## Features Implemented
✅ Full-screen image backgrounds for each onboarding slide
✅ Page indicators (dots) showing current position
✅ Skip button (top right)
✅ Next/Get Started button with smooth transitions
✅ Gradient overlay for better text readability
✅ Responsive layout with SafeArea
✅ Integration with existing PreferencesManager
✅ Auto-navigation to LoginScreen after completion

## Required Assets

You need to add 4 images to your project:

### 1. Create the assets directory structure
```bash
mkdir -p frontend/assets/images
```

### 2. Add your images
Place these images in `frontend/assets/images/`:

**Onboarding Screens:**
- `onboarding_1.png` - First screen (Find the ideal artisan)
- `onboarding_2.png` - Second screen (Secure payment)
- `onboarding_3.png` - Third screen (Trust and transparency)

**Login Screen:**
- `login_background.png` - Login screen background

**Recommended image specifications:**
- Dimensions: 375x812 pixels (iPhone X/11/12 size)
- Format: PNG or JPG
- Size: < 500KB per image (optimize for mobile)

### 3. Update pubspec.yaml
Add the assets to your `frontend/pubspec.yaml`:

```yaml
flutter:
  assets:
    - assets/images/onboarding_1.png
    - assets/images/onboarding_2.png
    - assets/images/onboarding_3.png
    - assets/images/login_background.png
```

Or use a wildcard:
```yaml
flutter:
  assets:
    - assets/images/
```

### 4. Run flutter pub get
```bash
cd frontend
flutter pub get
```

## Temporary Figma Asset URL
The Figma design provides a temporary image URL (expires in 7 days):
```
https://www.figma.com/api/mcp/asset/1e07477e-19f7-46de-9a4c-5cd5462661cc
```

You can download this image and use it as a reference or placeholder.

## Using Network Images (Alternative)
If you want to use network images instead of local assets, modify the `_buildPage` method:

```dart
Widget _buildPage(OnboardingPage page) {
  return Container(
    width: double.infinity,
    height: double.infinity,
    decoration: BoxDecoration(
      image: DecorationImage(
        image: NetworkImage(page.image), // Changed from AssetImage
        fit: BoxFit.cover,
      ),
    ),
  );
}
```

Then update the image paths in the `_pages` list to use URLs.

## Customization Options

### Change Colors
The overlay gradient and button colors can be customized in the `build` method:

```dart
// Overlay gradient
gradient: LinearGradient(
  begin: Alignment.topCenter,
  end: Alignment.bottomCenter,
  colors: [
    Colors.transparent,
    Colors.black.withOpacity(0.7), // Adjust opacity here
  ],
),

// Button colors
backgroundColor: Colors.white,
foregroundColor: AppColors.lightAccentPrimary,
```

### Change Text Content
Update the `_pages` list in `_OnboardingScreenState`:

```dart
final List<OnboardingPage> _pages = [
  OnboardingPage(
    image: 'assets/images/onboarding_1.png',
    title: 'Your Title Here',
    description: 'Your description here',
  ),
  // Add more pages...
];
```

### Add More Slides
Simply add more `OnboardingPage` objects to the `_pages` list. The indicators and navigation will automatically adjust.

## Testing

1. Clear app data to reset onboarding flag:
```dart
await PreferencesManager().clearAll();
```

2. Or manually delete the onboarding key from secure storage

3. Restart the app - you should see the onboarding flow

## File Structure
```
frontend/
├── assets/
│   └── images/
│       ├── onboarding_1.png
│       ├── onboarding_2.png
│       └── onboarding_3.png
├── lib/
│   └── features/
│       └── auth/
│           └── presentation/
│               └── screens/
│                   └── onboarding_screen.dart
└── pubspec.yaml
```

## Navigation Flow
```
SplashScreen
    ↓
    ├─→ (First time user) → OnboardingScreen → LoginScreen
    └─→ (Returning user) → LoginScreen or Home (if authenticated)
```

## Notes
- The screen uses `Stack` for layering content over images
- `SafeArea` ensures content doesn't overlap with system UI
- Page indicators animate smoothly on page change
- Skip button allows users to bypass onboarding
- Last page shows "Commencer" (Get Started) instead of "Suivant" (Next)
