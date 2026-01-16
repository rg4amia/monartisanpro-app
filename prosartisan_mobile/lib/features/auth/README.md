# Authentication Feature

This module implements the authentication functionality for the ProSartisan mobile application, including user registration, login, KYC verification, and OTP verification.

## Architecture

The authentication feature follows Clean Architecture principles with three main layers:

### Domain Layer (`domain/`)
- **Entities**: Core business objects (`User`, `AuthResult`)
- **Repositories**: Abstract interfaces defining data operations
- **Use Cases**: Business logic for authentication operations

### Data Layer (`data/`)
- **Models**: JSON serializable data models
- **Data Sources**: Remote API communication
- **Repository Implementation**: Concrete implementation of repository interfaces

### Presentation Layer (`presentation/`)
- **Pages**: UI screens for authentication flows
- **Controllers**: GetX state management controllers
- **Bindings**: Dependency injection configuration

## Features Implemented

### 1. Login Screen (`login_page.dart`)
- Email and password authentication
- Form validation
- Error handling with user-friendly messages
- Navigation to registration
- **Validates Requirements**: 1.1, 1.3

### 2. Registration Screen (`register_page.dart`)
- User type selection (Client, Artisan, Fournisseur)
- Email, password, and phone number input
- Trade category selection for artisans
- Business name input for fournisseurs
- Password confirmation validation
- Automatic navigation to KYC for artisans/fournisseurs
- **Validates Requirements**: 1.1, 1.2

### 3. KYC Upload Screen (`kyc_upload_page.dart`)
- ID type selection (CNI or Passport)
- ID number input
- Camera integration for document capture
- Gallery selection option
- ID document upload
- Selfie capture and upload
- **Validates Requirements**: 1.2

### 4. OTP Verification Screen (`otp_verification_page.dart`)
- 6-digit OTP input
- Auto-focus between input fields
- Automatic verification on completion
- Resend OTP with countdown timer (60 seconds)
- **Validates Requirements**: 1.6

## API Integration

The authentication feature integrates with the following backend endpoints:

- `POST /api/v1/auth/register` - User registration
- `POST /api/v1/auth/login` - User login
- `POST /api/v1/auth/otp/generate` - Generate OTP
- `POST /api/v1/auth/otp/verify` - Verify OTP
- `POST /api/v1/users/{id}/kyc` - Upload KYC documents

## State Management

The feature uses GetX for state management with three main controllers:

### AuthController
- Manages authentication state
- Handles login and registration
- Stores current user information
- Manages authentication token

### OtpController
- Manages OTP generation and verification
- Handles resend countdown
- Validates OTP codes

### KycController
- Manages KYC document upload
- Handles image selection from camera/gallery
- Validates required documents

## Dependencies

- `get`: State management and navigation
- `dio`: HTTP client for API calls
- `flutter_secure_storage`: Secure token storage
- `image_picker`: Camera and gallery integration

## Usage

### Initialize Authentication
```dart
// In main.dart
void main() {
  runApp(const ProSartisanApp());
}
```

### Navigate to Login
```dart
Get.toNamed(AppRoutes.login);
```

### Check Authentication Status
```dart
final authController = Get.find<AuthController>();
if (authController.isAuthenticated.value) {
  // User is logged in
}
```

### Logout
```dart
final authController = Get.find<AuthController>();
await authController.logout();
```

## Error Handling

The feature implements comprehensive error handling:

- Network errors with user-friendly messages
- Validation errors for form inputs
- API errors with specific error messages
- Account lockout after 3 failed attempts (handled by backend)

## Localization

All user-facing strings are defined in `core/constants/app_strings.dart` in French, following Requirement 18.1.

## Security

- Passwords are never stored locally
- Authentication tokens are stored securely using `flutter_secure_storage`
- JWT tokens are automatically added to API requests
- Tokens are cleared on logout or 401 errors

## Testing

To test the authentication flow:

1. Run the app: `flutter run`
2. Register a new user with different user types
3. Upload KYC documents (for artisans/fournisseurs)
4. Verify OTP (if phone number provided)
5. Login with registered credentials

## Future Enhancements

- Biometric authentication (fingerprint/face ID)
- Social login (Google, Facebook)
- Password reset functionality
- Email verification
- Multi-language support (English)
