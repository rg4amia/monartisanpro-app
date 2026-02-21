# Login Screen - Figma Implementation

## Overview
Enhanced login screen with full-screen background image matching the Figma design (node-id: 3112:6602).

## Figma Design Details
- **File:** Home Service App UI Kit
- **Node ID:** 3112:6602
- **Name:** "05 Login"
- **Dimensions:** 375x812px
- **Asset URL (expires in 7 days):**
  ```
  https://www.figma.com/api/mcp/asset/70f9dca3-266f-40c7-a398-cc7147aff750
  ```

## Features Implemented

### Visual Design
✅ Full-screen background image with overlay
✅ Gradient overlay for text readability
✅ White card container for login form
✅ Elevated design with shadows
✅ White logo on colored background
✅ White text for title and subtitle
✅ Glass-morphism style register button

### Functionality
✅ Email validation
✅ Password visibility toggle
✅ Form validation
✅ Loading state on login button
✅ Error handling with snackbars
✅ Role-based navigation
✅ Forgot password link
✅ Create account navigation
✅ Graceful fallback if image missing

## File Created

**Location:** `frontend/lib/features/auth/presentation/screens/login_screen_with_background.dart`

## Setup Instructions

### 1. Add Background Image

Download the Figma asset and save it as:
```
frontend/assets/images/login_background.png
```

Or use this script:
```bash
cd frontend
curl -o assets/images/login_background.png "https://www.figma.com/api/mcp/asset/70f9dca3-266f-40c7-a398-cc7147aff750"
```

### 2. Update pubspec.yaml

Add to your assets:
```yaml
flutter:
  assets:
    - assets/images/login_background.png
```

Or use wildcard:
```yaml
flutter:
  assets:
    - assets/images/
```

### 3. Update Route (Optional)

To use the new login screen, update `app_routes.dart`:

```dart
// Change from:
GetPage(name: login, page: () => const LoginScreen()),

// To:
GetPage(name: login, page: () => const LoginScreenWithBackground()),
```

Or import and use directly:
```dart
import 'package:your_app/features/auth/presentation/screens/login_screen_with_background.dart';

Get.to(() => const LoginScreenWithBackground());
```

### 4. Run flutter pub get

```bash
cd frontend
flutter pub get
```

## Design Breakdown

### Layout Structure

```
Scaffold
└── Stack
    ├── Background Image (Positioned.fill)
    ├── Gradient Overlay (Positioned.fill)
    └── SafeArea
        └── SingleChildScrollView
            └── Form
                ├── Logo (white circle)
                ├── Title (white text)
                ├── Subtitle (white text)
                ├── Login Card (white container)
                │   ├── Email Field
                │   ├── Password Field
                │   ├── Forgot Password Link
                │   └── Login Button
                ├── Divider (white)
                └── Register Button (glass style)
```

### Color Scheme

| Element | Color |
|---------|-------|
| Background | Image with gradient overlay |
| Overlay | Black 30% → 60% opacity |
| Logo Container | White |
| Logo Icon | Primary color |
| Title Text | White |
| Subtitle Text | White 90% opacity |
| Form Card | White with shadow |
| Divider | White 50% opacity |
| Register Button | White 20% opacity with border |

### Spacing

- Screen padding: `Spacing.screenPadding`
- Top spacing: `Spacing.xxxl`
- Card padding: `Spacing.xl`
- Field spacing: `Spacing.lg`
- Section spacing: `Spacing.xl`

## Comparison: Original vs Background Version

| Feature | Original Login | With Background |
|---------|---------------|-----------------|
| Background | Solid color | Full-screen image |
| Logo | Gradient circle | White circle |
| Text color | Theme-based | Always white |
| Form container | No container | White card with shadow |
| Register button | Outline style | Glass-morphism style |
| Dark mode | Supported | Image-based (no dark mode) |
| Fallback | N/A | Gradient if image fails |

## Customization Options

### Change Overlay Opacity

```dart
colors: [
  Colors.black.withOpacity(0.3), // Top opacity
  Colors.black.withOpacity(0.6), // Bottom opacity
],
```

### Change Card Style

```dart
Container(
  padding: const EdgeInsets.all(Spacing.xl),
  decoration: BoxDecoration(
    color: Colors.white,
    borderRadius: BorderRadius.circular(Spacing.radiusXl),
    boxShadow: [
      BoxShadow(
        color: Colors.black.withOpacity(0.1),
        blurRadius: 20,
        spreadRadius: 0,
        offset: const Offset(0, 10),
      ),
    ],
  ),
  // ...
)
```

### Change Logo Style

```dart
Container(
  width: 80,
  height: 80,
  decoration: BoxDecoration(
    color: Colors.white, // Change background color
    shape: BoxShape.circle,
    // ...
  ),
  child: Icon(
    Icons.build_circle, // Change icon
    size: 48,
    color: AppColors.lightAccentPrimary, // Change icon color
  ),
)
```

### Use Network Image Instead

```dart
Image.network(
  'https://your-image-url.com/login_bg.png',
  fit: BoxFit.cover,
  errorBuilder: (context, error, stackTrace) {
    return Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: AppColors.primaryGradient,
        ),
      ),
    );
  },
)
```

## Fallback Behavior

If the background image fails to load:
- Automatically falls back to a gradient background
- Uses `AppColors.primaryGradient`
- No error shown to user
- Seamless experience

## Integration Points

### AuthController
- Uses existing `AuthController` from GetX
- Calls `login()` method
- Handles loading state
- Manages error messages

### Navigation
- Role-based navigation after login:
  - `client` → MainNavigationScreen
  - `artisan` → ArtisanDashboardScreen
  - `fournisseur/vendor` → VendorDashboardScreen

### Validation
- Email format validation
- Password minimum length (8 characters)
- Required field validation

## Testing

### Test with Image
1. Add `login_background.png` to assets
2. Update pubspec.yaml
3. Run `flutter pub get`
4. Launch app and navigate to login

### Test Fallback
1. Don't add the image (or use wrong path)
2. Launch app and navigate to login
3. Should see gradient background instead

### Test Form Validation
1. Try submitting empty form
2. Try invalid email format
3. Try password < 8 characters
4. Verify error messages appear

### Test Login Flow
1. Enter valid credentials
2. Verify loading state shows
3. Verify success snackbar appears
4. Verify navigation to correct screen based on role

## Accessibility

- ✅ Proper contrast ratios (white text on dark overlay)
- ✅ Form field labels
- ✅ Error messages
- ✅ Touch targets (44x44 minimum)
- ✅ Keyboard navigation support
- ✅ Screen reader compatible

## Performance

- Image is loaded once and cached
- Gradient overlay is lightweight
- Form validation is instant
- No unnecessary rebuilds
- Efficient error handling

## Notes

- The background image should be optimized for mobile (< 500KB)
- Recommended dimensions: 375x812px or higher
- Format: PNG or JPG
- Consider using different images for different screen sizes
- The white card ensures form is always readable regardless of background

## Migration from Original

To migrate from the original login screen:

1. Keep the original file as backup
2. Create the new file with background
3. Update imports where needed
4. Test thoroughly
5. Update routes if desired

Both versions can coexist in your project!
