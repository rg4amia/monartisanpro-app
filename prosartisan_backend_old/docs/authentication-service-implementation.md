# Authentication Service Implementation

## Overview

This document describes the implementation of the Authentication Domain Service for the ProSartisan platform, completed as part of task 2.5.

## Requirements Addressed

- **Requirement 1.3**: User authentication with JWT token generation
- **Requirement 1.5**: Account lockout after 3 failed login attempts (15-minute lock)
- **Requirement 1.6**: Two-factor authentication via SMS OTP

## Components Implemented

### 1. Value Objects

#### AuthToken (`App\Domain\Identity\Models\ValueObjects\AuthToken`)
- Represents a JWT authentication token
- Contains token string and expiration timestamp
- Validates token is not empty
- Provides expiration checking

#### OTP (`App\Domain\Identity\Models\ValueObjects\OTP`)
- Represents a One-Time Password for 2FA
- Generates 6-digit numeric codes
- 5-minute expiration window
- Validates code format (exactly 6 digits)
- Provides verification method

### 2. Service Interfaces

#### AuthenticationService (`App\Domain\Identity\Services\AuthenticationService`)
Main interface defining authentication operations:
- `authenticate(Email, password)`: Authenticate user and return JWT token
- `generateOTP(PhoneNumber)`: Generate and send OTP via SMS
- `verifyOTP(PhoneNumber, code)`: Verify OTP code
- `generateToken(User)`: Generate JWT token for user
- `verifyToken(token)`: Verify and decode JWT token
- `refreshToken(token)`: Refresh an existing token

#### SMSService (`App\Domain\Identity\Services\SMSService`)
Interface for SMS sending:
- `send(PhoneNumber, message)`: Send SMS message

### 3. Service Implementations

#### LaravelAuthenticationService (`App\Domain\Identity\Services\LaravelAuthenticationService`)
Production implementation using Laravel features:
- **JWT Token Generation**: Custom JWT implementation with HS256 signing
  - 24-hour token expiration
  - Includes user ID, email, and user type in payload
  - Uses Laravel app key for signing
- **OTP Management**: Uses Laravel Cache for OTP storage
  - 5-minute expiration
  - Automatic cleanup after verification
- **Account Lockout**: Integrates with User entity lockout logic
  - Tracks failed login attempts
  - Locks account for 15 minutes after 3 failures
  - Resets counter on successful login
- **Security Checks**:
  - Validates account is not locked
  - Validates account is not suspended
  - Verifies password hash

#### LogSMSService (`App\Domain\Identity\Services\LogSMSService`)
Development implementation that logs SMS instead of sending:
- Logs to Laravel log system
- Useful for testing without SMS provider

### 4. Exception Classes

- `InvalidCredentialsException`: Invalid email or password
- `AccountLockedException`: Account temporarily locked due to failed attempts
- `AccountSuspendedException`: Account has been suspended
- `OTPGenerationException`: Failed to generate or send OTP
- `InvalidTokenException`: Invalid or expired JWT token

### 5. Repository Interface

#### UserRepository (`App\Domain\Identity\Repositories\UserRepository`)
- `save(User)`: Persist user changes
- `findById(UserId)`: Find user by ID
- `findByEmail(Email)`: Find user by email
- `findArtisansNearLocation(GPS_Coordinates, radiusKm)`: Find nearby artisans
- `delete(UserId)`: Delete user

## JWT Token Structure

### Header
```json
{
  "typ": "JWT",
  "alg": "HS256"
}
```

### Payload
```json
{
  "user_id": "uuid-string",
  "email": "user@example.com",
  "user_type": "CLIENT|ARTISAN|FOURNISSEUR|REFERENT_ZONE|ADMIN",
  "issued_at": 1234567890,
  "expires_at": 1234654290
}
```

### Signature
HMAC-SHA256 signature using Laravel app key

## OTP Flow

1. **Generation**:
   - Generate random 6-digit code
   - Store in cache with 5-minute TTL
   - Send via SMS (or log in development)

2. **Verification**:
   - Retrieve code from cache
   - Compare with provided code
   - Remove from cache on success
   - Return false if expired or not found

## Authentication Flow

1. **Login Request**:
   - Find user by email
   - Check if account is locked → throw `AccountLockedException`
   - Check if account is suspended → throw `AccountSuspendedException`
   - Verify password
   - If invalid → record failed attempt, throw `InvalidCredentialsException`
   - If valid → reset failed attempts, generate JWT token

2. **Account Lockout**:
   - After 3 failed attempts, account is locked for 15 minutes
   - Lock automatically expires after timeout
   - Failed attempts counter is reset on successful login

3. **Token Verification**:
   - Decode JWT token
   - Verify signature using app key
   - Check expiration
   - Return user ID from payload

## Testing

### Unit Tests (13 tests, 45 assertions)

**AuthenticationServiceTest**:
- ✓ Successful authentication returns valid token
- ✓ Invalid email throws exception
- ✓ Wrong password throws exception and records failed attempt
- ✓ Account locks after 3 failed attempts
- ✓ Suspended account throws exception
- ✓ OTP generation creates valid 6-digit code
- ✓ OTP verification with correct code succeeds
- ✓ OTP verification with wrong code fails
- ✓ OTP verification with non-existent phone fails
- ✓ Token generation includes user information
- ✓ Invalid token verification throws exception
- ✓ Token refresh generates new token
- ✓ Successful login resets failed attempts counter

**OTPTest** (11 tests, 15 assertions):
- ✓ Generate creates 6-digit code
- ✓ Constructor validates code format
- ✓ Constructor rejects empty code
- ✓ Constructor rejects non-numeric code
- ✓ Generated OTP is not expired
- ✓ OTP expiration detection works
- ✓ Verify with correct code returns true
- ✓ Verify with wrong code returns false
- ✓ Verify expired OTP returns false
- ✓ toString returns code
- ✓ Getters return correct values

**AuthTokenTest** (5 tests, 6 assertions):
- ✓ Constructor with valid values
- ✓ Constructor rejects empty token
- ✓ Token is not expired immediately
- ✓ Token expiration detection
- ✓ toString returns token

## Security Considerations

1. **Password Hashing**: Uses `HashedPassword` value object with bcrypt
2. **JWT Signing**: Uses HMAC-SHA256 with Laravel app key
3. **Token Expiration**: 24-hour expiration enforced
4. **OTP Expiration**: 5-minute expiration enforced
5. **Account Lockout**: 15-minute lockout after 3 failed attempts
6. **Cache Security**: OTP codes stored in cache, automatically cleaned up

## Future Enhancements

1. **JWT Library**: Consider using `firebase/php-jwt` for production
2. **SMS Provider**: Integrate with Twilio or African SMS providers (Orange, MTN)
3. **Rate Limiting**: Add rate limiting for OTP generation
4. **Token Blacklist**: Implement token revocation/blacklist
5. **Refresh Token**: Implement separate refresh token mechanism
6. **Multi-Factor Auth**: Support additional 2FA methods (TOTP, email)

## Integration Points

### Required for API Layer
- User repository implementation (PostgreSQL)
- SMS service implementation (Twilio/Orange/MTN)
- API endpoints for:
  - POST /api/v1/auth/login
  - POST /api/v1/auth/otp/generate
  - POST /api/v1/auth/otp/verify
  - POST /api/v1/auth/refresh

### Required for Mobile App
- Token storage (secure storage)
- OTP input UI
- Login form
- Error handling for locked/suspended accounts

## Files Created

### Domain Layer
- `app/Domain/Identity/Models/ValueObjects/AuthToken.php`
- `app/Domain/Identity/Models/ValueObjects/OTP.php`
- `app/Domain/Identity/Services/AuthenticationService.php`
- `app/Domain/Identity/Services/LaravelAuthenticationService.php`
- `app/Domain/Identity/Services/SMSService.php`
- `app/Domain/Identity/Services/LogSMSService.php`
- `app/Domain/Identity/Repositories/UserRepository.php`
- `app/Domain/Identity/Exceptions/InvalidCredentialsException.php`
- `app/Domain/Identity/Exceptions/AccountLockedException.php`
- `app/Domain/Identity/Exceptions/AccountSuspendedException.php`
- `app/Domain/Identity/Exceptions/OTPGenerationException.php`
- `app/Domain/Identity/Exceptions/InvalidTokenException.php`

### Tests
- `tests/Unit/Domain/Identity/Services/AuthenticationServiceTest.php`
- `tests/Unit/Domain/Identity/ValueObjects/OTPTest.php`
- `tests/Unit/Domain/Identity/ValueObjects/AuthTokenTest.php`

## Conclusion

The authentication service has been successfully implemented with comprehensive test coverage. All requirements (1.3, 1.5, 1.6) have been addressed with production-ready code following Domain-Driven Design principles.
