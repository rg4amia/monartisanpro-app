# Backend AuthController Update for Dynamic Trade System

## Overview
Updated the backend authentication system to support the new dynamic sector-based trade selection system. The changes ensure compatibility with the frontend's sector-first, trade-second registration flow.

## Changes Made

### 1. RegisterRequest Validation Updates
**File:** `prosartisan_backend/app/Http/Requests/Auth/RegisterRequest.php`

**Changes:**
- Updated `trade_category` validation from hardcoded values to database validation
- Changed from `Rule::in(['PLUMBER', 'ELECTRICIAN', 'MASON'])` to `'exists:trades,code'`
- Made location data optional for artisans and fournisseurs (can be collected later)
- Updated validation messages to reflect new dynamic system

**Before:**
```php
'trade_category' => ['required', 'string', Rule::in(['PLUMBER', 'ELECTRICIAN', 'MASON'])],
'location' => ['required', 'array'],
```

**After:**
```php
'trade_category' => ['required', 'string', 'exists:trades,code'],
'location' => ['nullable', 'array'],
```

### 2. TradeCategory Value Object Enhancement
**File:** `prosartisan_backend/app/Domain/Identity/Models/ValueObjects/TradeCategory.php`

**Changes:**
- Added database validation for trade codes
- Maintained backward compatibility with existing tests
- Added support for dynamic trade creation from database
- Enhanced with proper error handling

**Key Features:**
- `fromString()` method now validates against database trades
- Legacy methods (`PLUMBER()`, `ELECTRICIAN()`, `MASON()`) maintained for tests
- `fromTrade()` method for creating from Trade model
- `getLabel()` method returns localized trade names

### 3. AuthController Updates
**File:** `prosartisan_backend/app/Http/Controllers/Api/V1/Auth/AuthController.php`

**Changes:**
- Updated method calls to use correct static factory methods
- Made location data optional with sensible defaults
- Fixed AuthResource constructor calls
- Enhanced error handling

**Key Updates:**
- `Client::createClient()` instead of `Client::create()`
- `Artisan::createArtisan()` instead of `Artisan::create()`
- `Fournisseur::createFournisseur()` instead of `Fournisseur::create()`
- Default location coordinates for users who don't provide location initially

### 4. Location Data Handling
**Changes:**
- Made location optional during registration
- Added default coordinates (Abidjan: 5.3600, -4.0083) when location not provided
- Set large accuracy value (1000.0) to indicate imprecise location
- Users can update location later through profile updates

## API Changes

### Registration Endpoint Updates
**Endpoint:** `POST /api/v1/auth/register`

**For Artisans:**
```json
{
  "email": "artisan@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "user_type": "ARTISAN",
  "phone_number": "+2250712345678",
  "trade_category": "TRADE_CODE_FROM_DATABASE",
  "location": {  // Optional
    "latitude": 5.3600,
    "longitude": -4.0083,
    "accuracy": 10.0
  }
}
```

**For Fournisseurs:**
```json
{
  "email": "fournisseur@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "user_type": "FOURNISSEUR",
  "phone_number": "+2250712345678",
  "business_name": "My Supply Business",
  "shop_location": {  // Optional
    "latitude": 5.3600,
    "longitude": -4.0083,
    "accuracy": 10.0
  }
}
```

## Validation Rules

### Trade Category Validation
- **Rule:** `exists:trades,code`
- **Purpose:** Ensures the trade code exists in the database
- **Error Message:** "Le métier sélectionné n'existe pas."

### Location Validation
- **Artisan Location:** Optional during registration
- **Fournisseur Shop Location:** Optional during registration
- **Default Behavior:** Uses Abidjan coordinates with low accuracy if not provided

## Backward Compatibility

### Test Compatibility
- All existing tests continue to work
- Legacy `TradeCategory::PLUMBER()`, `TradeCategory::ELECTRICIAN()`, `TradeCategory::MASON()` methods maintained
- Existing domain logic unchanged

### API Compatibility
- All existing API endpoints remain functional
- New validation rules are more permissive (optional location)
- Existing clients can continue to work without changes

## Error Handling

### Trade Validation Errors
```json
{
  "error": "VALIDATION_ERROR",
  "message": "Le métier sélectionné n'existe pas.",
  "status_code": 400
}
```

### Registration Success Response
```json
{
  "message": "Inscription réussie",
  "data": {
    "token": "jwt_token_here",
    "user": {
      "id": "user_id",
      "email": "user@example.com",
      "user_type": "ARTISAN",
      "trade_category": "TRADE_CODE",
      // ... other user data
    }
  }
}
```

## Testing

### Test Scripts Created
1. `test_sector_trade_api.sh` - Tests sector and trade API endpoints
2. `test_registration_with_trades.sh` - Tests registration with dynamic trades

### Test Coverage
- Sector listing
- Trades by sector
- Artisan registration with dynamic trades
- Client registration (no trade required)
- Fournisseur registration with business name
- Error handling for invalid trades

## Migration Path

### For Existing Data
1. Existing users with hardcoded trade categories will continue to work
2. New registrations will use database trade codes
3. Location data can be collected through profile updates

### For Frontend Integration
1. Frontend now receives dynamic trade lists from API
2. Registration flow supports sector-first selection
3. Location collection can be deferred to post-registration

## Future Enhancements

### Planned Improvements
1. **Location Collection:** Add dedicated location collection flow
2. **Profile Updates:** Enhanced profile update endpoints for location
3. **Trade Management:** Admin interface for managing trades and sectors
4. **Validation Enhancement:** More sophisticated trade validation rules

### Scalability Considerations
1. **Caching:** Add caching for frequently accessed trade data
2. **Performance:** Optimize database queries for trade lookups
3. **Internationalization:** Support for multiple languages in trade names

## Security Considerations

### Data Validation
- All trade codes validated against database
- Phone number format validation maintained
- Email uniqueness enforced
- Password strength requirements unchanged

### Privacy
- Default location coordinates don't reveal user's actual location
- Location accuracy indicates precision level
- Users can update location when ready

This update successfully bridges the gap between the new dynamic frontend trade selection and the existing backend domain architecture while maintaining full backward compatibility.