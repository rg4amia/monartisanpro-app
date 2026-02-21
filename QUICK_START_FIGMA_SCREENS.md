# Quick Start: Figma Screens Implementation

Get your Figma-designed screens up and running in 5 minutes!

## 🚀 Quick Setup (Without Images)

If you want to test immediately without downloading images:

### 1. Update Routes
Edit `frontend/lib/core/routes/app_routes.dart`:

```dart
import '../../features/auth/presentation/screens/onboarding_screen_with_fallback.dart';
import '../../features/auth/presentation/screens/login_screen_with_background.dart';

static List<GetPage> routes = [
  GetPage(
    name: onboarding, 
    page: () => const OnboardingScreenWithFallback(), // Uses gradients
  ),
  GetPage(
    name: login, 
    page: () => const LoginScreenWithBackground(), // Has fallback
  ),
  // ... other routes
];
```

### 2. Test
```bash
cd frontend
flutter run
```

That's it! The screens will use gradient fallbacks and work perfectly.

## 📸 Full Setup (With Images)

For the complete Figma design experience:

### 1. Download Assets
```bash
cd frontend
bash scripts/download_figma_assets.sh
```

This downloads:
- `onboarding_1.png`
- `login_background.png`

### 2. Add Missing Images
You still need to manually add:
- `onboarding_2.png` (second onboarding screen)
- `onboarding_3.png` (third onboarding screen)

Place them in `frontend/assets/images/`

### 3. Update pubspec.yaml
Add this to `frontend/pubspec.yaml`:

```yaml
flutter:
  assets:
    - assets/images/
```

### 4. Get Dependencies
```bash
flutter pub get
```

### 5. Update Routes
```dart
import '../../features/auth/presentation/screens/onboarding_screen.dart';
import '../../features/auth/presentation/screens/login_screen_with_background.dart';

static List<GetPage> routes = [
  GetPage(
    name: onboarding, 
    page: () => const OnboardingScreen(), // Uses images
  ),
  GetPage(
    name: login, 
    page: () => const LoginScreenWithBackground(),
  ),
];
```

### 6. Run
```bash
flutter run
```

## 🎨 What You Get

### Onboarding Screen
- 3 full-screen pages with images/gradients
- Animated page indicators
- Skip button
- Next/Get Started button
- Smooth transitions

### Login Screen
- Full-screen background image
- White card for form
- Email/password validation
- Loading states
- Role-based navigation

## 🔄 Switch Between Versions

### Use Gradients (No Images)
```dart
OnboardingScreenWithFallback()
```

### Use Images
```dart
OnboardingScreen()
```

Both login versions support fallback automatically!

## 📱 Test the Flow

1. **Clear app data** to see onboarding:
```dart
await PreferencesManager().clearAll();
```

2. **Launch app** → See onboarding
3. **Complete onboarding** → See login
4. **Login** → Navigate to home

## ⚡ Pro Tips

- Start with fallback versions (no setup needed)
- Add images later when ready
- Both versions can coexist
- Fallback is perfect for development

## 🐛 Troubleshooting

### Images Not Showing?
- Check file paths in pubspec.yaml
- Run `flutter pub get`
- Verify images are in `assets/images/`
- Check file names match exactly

### Onboarding Shows Every Time?
```dart
// Clear the flag
await PreferencesManager().clearAll();
```

### Build Errors?
```bash
flutter clean
flutter pub get
flutter run
```

## 📚 Full Documentation

For detailed information, see:
- `FIGMA_IMPLEMENTATION_SUMMARY.md` - Complete overview
- `ONBOARDING_SETUP_GUIDE.md` - Onboarding details
- `LOGIN_SCREEN_FIGMA_IMPLEMENTATION.md` - Login details

## ✅ You're Done!

Your Figma-designed screens are now integrated and ready to use!
