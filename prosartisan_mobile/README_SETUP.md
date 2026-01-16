# ProSartisan Mobile Setup Guide

## Prerequisites

- Flutter SDK 3.24.0 or higher
- Dart SDK 3.10.4 or higher
- Android Studio (for Android development)
- Xcode (for iOS development, macOS only)
- VS Code or Android Studio with Flutter plugins

## Installation Steps

### 1. Install Flutter

#### macOS
```bash
# Using Homebrew
brew install --cask flutter

# Or download from flutter.dev and add to PATH
export PATH="$PATH:`pwd`/flutter/bin"
```

#### Linux
```bash
# Download Flutter SDK
wget https://storage.googleapis.com/flutter_infra_release/releases/stable/linux/flutter_linux_3.24.0-stable.tar.xz

# Extract and add to PATH
tar xf flutter_linux_3.24.0-stable.tar.xz
export PATH="$PATH:`pwd`/flutter/bin"
```

#### Windows
Download Flutter SDK from [flutter.dev](https://flutter.dev) and follow installation instructions.

### 2. Verify Flutter Installation

```bash
flutter doctor
```

Fix any issues reported by `flutter doctor`.

### 3. Setup Mobile Project

```bash
cd prosartisan_mobile

# Get dependencies
flutter pub get

# Run code generation (if needed)
flutter pub run build_runner build --delete-conflicting-outputs
```

### 4. Configure Firebase (for notifications)

1. Create a Firebase project at [console.firebase.google.com](https://console.firebase.google.com)
2. Add Android app with package name: `com.prosartisan.mobile`
3. Add iOS app with bundle ID: `com.prosartisan.mobile`
4. Download `google-services.json` (Android) and place in `android/app/`
5. Download `GoogleService-Info.plist` (iOS) and place in `ios/Runner/`

### 5. Configure Google Maps API

#### Android
1. Get API key from [Google Cloud Console](https://console.cloud.google.com)
2. Add to `android/app/src/main/AndroidManifest.xml`:
```xml
<meta-data
    android:name="com.google.android.geo.API_KEY"
    android:value="YOUR_API_KEY_HERE"/>
```

#### iOS
1. Add to `ios/Runner/AppDelegate.swift`:
```swift
import GoogleMaps

GMSServices.provideAPIKey("YOUR_API_KEY_HERE")
```

### 6. Run the App

```bash
# List available devices
flutter devices

# Run on connected device
flutter run

# Run in debug mode
flutter run --debug

# Run in release mode
flutter run --release

# Run on specific device
flutter run -d <device_id>
```

## Testing

### Run All Tests
```bash
flutter test
```

### Run Specific Test Files
```bash
# Unit tests
flutter test test/unit

# Widget tests
flutter test test/widget

# Integration tests
flutter test test/integration
```

### Run Tests with Coverage
```bash
flutter test --coverage
```

### View Coverage Report
```bash
# Install lcov (macOS)
brew install lcov

# Generate HTML report
genhtml coverage/lcov.info -o coverage/html

# Open in browser
open coverage/html/index.html
```

## Code Quality

### Format Code
```bash
dart format .
```

### Analyze Code
```bash
flutter analyze
```

### Fix Common Issues
```bash
dart fix --apply
```

## Project Structure

```
prosartisan_mobile/
├── lib/
│   ├── core/                    # Core functionality
│   │   ├── config/              # App configuration
│   │   ├── constants/           # Constants and enums
│   │   ├── domain/              # Shared domain models
│   │   │   └── value_objects/   # Shared value objects
│   │   ├── routes/              # Navigation routes
│   │   ├── services/            # Core services
│   │   │   ├── api/             # API client
│   │   │   ├── location/        # GPS services
│   │   │   ├── notification/    # Push notifications
│   │   │   ├── payment/         # Mobile money integration
│   │   │   └── storage/         # Local storage
│   │   └── utils/               # Utility functions
│   ├── features/                # Feature modules
│   │   ├── auth/                # Authentication
│   │   ├── marketplace/         # Mission and artisan search
│   │   ├── mission/             # Mission management
│   │   ├── payment/             # Payment and escrow
│   │   ├── profile/             # User profiles
│   │   ├── reputation/          # Reputation scores
│   │   └── worksite/            # Worksite tracking
│   ├── shared/                  # Shared UI components
│   │   ├── bindings/            # GetX bindings
│   │   ├── controllers/         # Shared controllers
│   │   ├── models/              # Shared models
│   │   └── widgets/             # Reusable widgets
│   └── main.dart                # App entry point
├── test/
│   ├── unit/                    # Unit tests
│   ├── widget/                  # Widget tests
│   └── integration/             # Integration tests
├── assets/                      # Static assets
│   ├── fonts/                   # Custom fonts
│   ├── icons/                   # App icons
│   ├── images/                  # Images
│   └── lottie/                  # Lottie animations
└── pubspec.yaml                 # Dependencies
```

## Build for Production

### Android APK
```bash
flutter build apk --release
```

### Android App Bundle (for Play Store)
```bash
flutter build appbundle --release
```

### iOS
```bash
flutter build ios --release
```

## Environment Configuration

Create environment-specific configuration files:

```dart
// lib/core/config/environment.dart
class Environment {
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://localhost:8000/api/v1',
  );
  
  static const bool isProduction = bool.fromEnvironment('PRODUCTION');
}
```

Run with environment variables:
```bash
flutter run --dart-define=API_BASE_URL=https://api.prosartisan.ci/api/v1 --dart-define=PRODUCTION=true
```

## Troubleshooting

### Pod Install Issues (iOS)
```bash
cd ios
pod deintegrate
pod install
cd ..
flutter clean
flutter pub get
```

### Gradle Build Issues (Android)
```bash
cd android
./gradlew clean
cd ..
flutter clean
flutter pub get
```

### Clear Flutter Cache
```bash
flutter clean
flutter pub cache repair
flutter pub get
```

### Update Dependencies
```bash
flutter pub upgrade
```

## Development Tips

1. **Hot Reload**: Press `r` in terminal while app is running
2. **Hot Restart**: Press `R` in terminal while app is running
3. **DevTools**: Run `flutter pub global activate devtools` then `flutter pub global run devtools`
4. **Performance**: Use `flutter run --profile` for performance profiling
5. **Debugging**: Use VS Code or Android Studio debugger with breakpoints

## Next Steps

1. Review the [Design Document](../.kiro/specs/prosartisan-platform-implementation/design.md)
2. Check the [Task List](../.kiro/specs/prosartisan-platform-implementation/tasks.md)
3. Implement features following the feature-based architecture
4. Write tests alongside implementation
5. Test on real devices regularly

## Additional Resources

- [Flutter Documentation](https://flutter.dev/docs)
- [Dart Documentation](https://dart.dev/guides)
- [GetX State Management](https://pub.dev/packages/get)
- [Firebase for Flutter](https://firebase.flutter.dev/)
- [Google Maps Flutter](https://pub.dev/packages/google_maps_flutter)
