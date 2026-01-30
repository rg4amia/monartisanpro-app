# Sector-Based Trade Registration Update

## Overview
Updated the registration flow to implement a sector-first, then trade selection approach for artisan registration. Users now select a sector first, then choose from trades specific to that sector.

## Backend Changes

### 1. API Controller Updates
**File:** `prosartisan_backend/app/Http/Controllers/Api/V1/ReferenceDataController.php`

Added new methods:
- `sectors()` - Returns all sectors only
- `tradesBySector($sectorId)` - Returns trades for a specific sector
- `trades()` - Returns all trades with sector information

### 2. API Routes Updates
**File:** `prosartisan_backend/routes/api.php`

Added new routes:
```php
Route::get('/sectors', [ReferenceDataController::class, 'sectors']);
Route::get('/sectors/{sectorId}/trades', [ReferenceDataController::class, 'tradesBySector']);
Route::get('/trades/all', [ReferenceDataController::class, 'trades']);
```

### 3. Database Models
The existing models already had the correct relationships:
- `Sector` model has `hasMany(Trade::class)` relationship
- `Trade` model has `belongsTo(Sector::class)` relationship

## Frontend Changes

### 1. API Constants Updates
**File:** `prosartisan_mobile/lib/core/constants/api_constants.dart`

Added new endpoint constants:
```dart
static const String sectors = '/reference/sectors';
static const String tradesBySector = '/reference/sectors/{sectorId}/trades';
static const String allTrades = '/reference/trades/all';
```

### 2. Repository Updates
**File:** `prosartisan_mobile/lib/shared/data/repositories/trade_repository.dart`

Added new methods:
- `getSectors()` - Fetch all sectors
- `getTradesBySector(int sectorId)` - Fetch trades for specific sector
- `getAllTrades()` - Fetch all trades with sector info

### 3. Controller Updates
**File:** `prosartisan_mobile/lib/shared/controllers/trade_controller.dart`

Enhanced with:
- Separate loading states for sectors and trades
- `loadSectors()` method
- `loadTradesBySector(int sectorId)` method
- `tradesForSelectedSector` observable list
- `selectedSectorId` tracking

### 4. Registration Page Updates
**File:** `prosartisan_mobile/lib/features/auth/presentation/pages/register_page.dart`

Major UI changes:
- Added `_selectedSectorId` state variable
- Implemented two-step selection process:
  1. First dropdown: Select sector
  2. Second dropdown: Select trade (only appears after sector selection)
- Added proper loading states and error handling for both steps
- Reset logic when switching user types or sectors

## User Experience Flow

### For Artisan Registration:
1. User selects "ARTISAN" user type
2. **Sector Selection**: Dropdown shows all available sectors
   - Loading indicator while fetching sectors
   - Error handling with retry option
3. **Trade Selection**: After selecting a sector, second dropdown appears
   - Shows only trades for the selected sector
   - Loading indicator while fetching trades
   - Error handling with retry option
4. User completes registration with selected trade

### Benefits:
- **Better Organization**: Trades are now organized by sector
- **Reduced Cognitive Load**: Users see fewer options at each step
- **Improved Performance**: Only loads trades for selected sector
- **Better UX**: Clear progression from general (sector) to specific (trade)
- **Scalability**: Easy to add new sectors and trades without overwhelming UI

## API Endpoints

### New Endpoints:
1. `GET /api/v1/reference/sectors` - Get all sectors
2. `GET /api/v1/reference/sectors/{id}/trades` - Get trades for specific sector
3. `GET /api/v1/reference/trades/all` - Get all trades with sector info

### Existing Endpoints (unchanged):
- `GET /api/v1/reference/trades` - Get sectors with nested trades

## Testing

A test script has been created at `prosartisan_backend/test_sector_trade_api.sh` to verify all endpoints work correctly.

## Backward Compatibility

All existing API endpoints remain functional, ensuring backward compatibility with any other parts of the system that might be using them.

## Future Enhancements

1. **Caching**: Implement caching for sectors and trades data
2. **Search**: Add search functionality within sectors/trades
3. **Favorites**: Allow users to mark frequently used trades
4. **Hierarchical Display**: Show sector names alongside trade names in final selection