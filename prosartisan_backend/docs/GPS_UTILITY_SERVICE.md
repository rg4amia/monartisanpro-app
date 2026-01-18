# GPS Utility Service Documentation

## Overview

The GPS Utility Service provides location-based functionality with automatic fallback to OTP SMS verification when GPS is unavailable or inaccurate. This service implements Requirements 10.2, 10.4, and 10.5 from the ProSartisan platform specification.

## Features

### 1. Haversine Distance Calculation (Requirement 10.2)
- Calculates accurate distances between GPS coordinates using the Haversine formula
- Returns distance in meters
- Handles edge cases and coordinate validation

### 2. GPS Accuracy Validation (Requirement 10.4)
- Validates GPS coordinates accuracy within 10 meters
- Rejects coordinates with insufficient accuracy
- Used for proximity verification in Jeton and Jalon validations

### 3. GPS Fallback to OTP (Requirement 10.5)
- Automatically falls back to OTP SMS verification when GPS is unavailable
- Supports multiple contexts (jeton_validation, jalon_proof, location_validation)
- Implements secure OTP generation and verification with expiration

## Service Interface

### Domain Service Interface
```php
App\Domain\Shared\Services\GPSUtilityService
```

### Implementation
```php
App\Infrastructure\Services\GPS\GPSUtilityService
```

## Key Methods

### calculateDistance()
```php
public function calculateDistance(GPS_Coordinates $from, GPS_Coordinates $to): float
```
Calculates distance between two GPS coordinates using Haversine formula.

### validateGPSAccuracy()
```php
public function validateGPSAccuracy(GPS_Coordinates $coordinates): bool
```
Validates if GPS accuracy is acceptable (< 10m).

### verifyProximity()
```php
public function verifyProximity(
    GPS_Coordinates $location1, 
    GPS_Coordinates $location2, 
    float $maxDistanceMeters
): array
```
Verifies if two locations are within specified distance with accuracy checks.

### performLocationValidation()
```php
public function performLocationValidation(
    ?GPS_Coordinates $userLocation,
    GPS_Coordinates $requiredLocation,
    PhoneNumber $userPhone,
    float $maxDistanceMeters,
    string $context = 'location_validation'
): array
```
Main validation method that combines GPS validation with automatic OTP fallback.

### generateGPSFallbackOTP()
```php
public function generateGPSFallbackOTP(PhoneNumber $phoneNumber, string $context = 'gps_fallback'): array
```
Generates and sends OTP for GPS fallback verification.

### verifyGPSFallbackOTP()
```php
public function verifyGPSFallbackOTP(PhoneNumber $phoneNumber, string $code, string $context = 'gps_fallback'): array
```
Verifies OTP code for GPS fallback.

## API Endpoints

The service is exposed through REST API endpoints:

### POST /api/v1/gps/validate-proximity
Validates proximity between user location and required location with automatic GPS fallback.

**Request:**
```json
{
    "user_latitude": 5.3600,
    "user_longitude": -4.0083,
    "user_accuracy": 5.0,
    "required_latitude": 5.3605,
    "required_longitude": -4.0088,
    "phone_number": "+22501234567890",
    "max_distance": 100,
    "context": "jeton_validation"
}
```

**Response (GPS Success):**
```json
{
    "success": true,
    "data": {
        "validation_method": "GPS",
        "success": true,
        "message": "Location validated using GPS",
        "distance": 67.8
    }
}
```

**Response (OTP Fallback):**
```json
{
    "success": true,
    "data": {
        "validation_method": "OTP_FALLBACK",
        "success": false,
        "reason": "GPS_UNAVAILABLE",
        "message": "GPS unavailable. OTP sent for verification.",
        "next_step": "VERIFY_OTP",
        "otp_expires_at": "2026-01-18 15:30:00"
    }
}
```

### POST /api/v1/gps/verify-otp
Verifies OTP code for GPS fallback.

**Request:**
```json
{
    "phone_number": "+22501234567890",
    "otp_code": "123456",
    "context": "jeton_validation"
}
```

### POST /api/v1/gps/calculate-distance
Calculates distance between two GPS coordinates.

### POST /api/v1/gps/generate-otp
Manually generates OTP for GPS fallback.

## Usage Examples

### Basic Distance Calculation
```php
$service = app(GPSUtilityService::class);
$abidjan = new GPS_Coordinates(5.3600, -4.0083);
$yamoussoukro = new GPS_Coordinates(6.8276, -5.2893);

$distance = $service->calculateDistance($abidjan, $yamoussoukro);
// Returns ~230,000 meters (230 km)
```

### Proximity Validation with GPS
```php
$userLocation = new GPS_Coordinates(5.3600, -4.0083, 5.0);
$requiredLocation = new GPS_Coordinates(5.3605, -4.0088, 5.0);

$result = $service->verifyProximity($userLocation, $requiredLocation, 100.0);

if ($result['success']) {
    // User is within 100m of required location
    echo "Distance: " . $result['distance'] . " meters";
}
```

### Complete Location Validation with Fallback
```php
$userLocation = null; // GPS unavailable
$requiredLocation = new GPS_Coordinates(5.3600, -4.0083);
$userPhone = PhoneNumber::fromString('+22501234567890');

$result = $service->performLocationValidation(
    $userLocation,
    $requiredLocation,
    $userPhone,
    100.0,
    'jeton_validation'
);

if ($result['validation_method'] === 'OTP_FALLBACK') {
    // OTP has been sent, user needs to verify
    echo "OTP sent, expires at: " . $result['otp_expires_at'];
}
```

### OTP Verification
```php
$result = $service->verifyGPSFallbackOTP(
    PhoneNumber::fromString('+22501234567890'),
    '123456',
    'jeton_validation'
);

if ($result['success']) {
    // OTP verified successfully
    echo "Location validated via OTP";
}
```

## Integration Points

### Jeton Validation
The service is used in Jeton validation to ensure artisan and supplier are within 100m of each other:

```php
// In JetonController
$result = $this->gpsUtilityService->performLocationValidation(
    $artisanLocation,
    $supplierLocation,
    $artisanPhone,
    100.0,
    'jeton_validation'
);
```

### Jalon Proof Validation
Used to verify artisan is at the worksite when submitting milestone proofs:

```php
// In JalonController
$result = $this->gpsUtilityService->performLocationValidation(
    $artisanLocation,
    $worksiteLocation,
    $artisanPhone,
    50.0,
    'jalon_proof'
);
```

## Error Handling

The service returns structured error responses:

- `GPS_ACCURACY_INSUFFICIENT`: GPS accuracy is > 10m
- `DISTANCE_EXCEEDED`: Locations are too far apart
- `GPS_UNAVAILABLE`: GPS not available, OTP fallback initiated
- `OTP_EXPIRED`: OTP has expired
- `OTP_INVALID`: Wrong OTP code provided
- `OTP_NOT_FOUND`: No OTP found for phone number
- `SMS_DELIVERY_FAILED`: Failed to send OTP via SMS

## Testing

Comprehensive unit tests are available at:
```
tests/Unit/Infrastructure/Services/GPS/GPSUtilityServiceTest.php
```

Tests cover:
- Haversine distance calculations
- GPS accuracy validation
- Proximity verification
- OTP generation and verification
- Complete location validation workflows
- Error scenarios and edge cases

## Configuration

The service uses the following configuration:
- OTP expiry: 5 minutes
- GPS accuracy threshold: 10 meters
- Cache prefix: `gps_fallback_otp_`

## Dependencies

- `App\Infrastructure\Services\SMS\SMSService`: For sending OTP messages
- `Illuminate\Support\Facades\Cache`: For OTP storage
- `Illuminate\Support\Facades\Log`: For logging

## Security Considerations

- OTP codes are 6-digit random numbers
- OTPs are stored in cache with expiration
- OTPs are deleted after successful verification
- All GPS validations are logged for audit purposes
- Phone numbers are validated before OTP generation
