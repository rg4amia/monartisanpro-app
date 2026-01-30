# Mobile Registration with Sector and Trade - Test Guide

## Overview
The mobile app registration flow for artisans now includes sector and trade selection. This document outlines how to test the complete flow.

## Current Implementation Status ✅

### Backend (Complete)
- ✅ Sectors and Trades models with relationships
- ✅ Reference data API endpoints
- ✅ Registration validation for trade_category
- ✅ AuthController handles artisan registration with trades

### Frontend (Complete)
- ✅ Sector and Trade models
- ✅ TradeController for state management
- ✅ TradeRepository for API calls
- ✅ Registration UI with sector/trade dropdowns
- ✅ Form validation for required fields
- ✅ Integration with auth flow

## Test Scenarios

### 1. Artisan Registration Flow
1. Open registration page
2. Select "ARTISAN" user type
3. Fill in email, phone, password fields
4. **Sector Selection**: Dropdown should load sectors from backend
5. **Trade Selection**: After selecting sector, trades dropdown should populate
6. Submit registration with selected trade
7. Verify backend receives `trade_category` parameter

### 2. Client Registration Flow
1. Select "CLIENT" user type
2. Fill in basic fields (no sector/trade required)
3. Submit registration
4. Should work without trade information

### 3. Fournisseur Registration Flow
1. Select "FOURNISSEUR" user type
2. Fill in business name and other fields
3. Submit registration
4. Should work without trade information

## API Endpoints Used

### Reference Data
- `GET /api/v1/reference/sectors` - Load sectors
- `GET /api/v1/reference/sectors/{sectorId}/trades` - Load trades by sector

### Registration
- `POST /api/v1/auth/register` - Register user with trade_category

## Key Files

### Frontend
- `lib/features/auth/presentation/pages/register_page.dart` - Registration UI
- `lib/shared/controllers/trade_controller.dart` - Sector/trade management
- `lib/shared/data/repositories/trade_repository.dart` - API calls
- `lib/features/auth/domain/usecases/register_usecase.dart` - Registration logic

### Backend
- `app/Http/Controllers/Api/V1/Auth/AuthController.php` - Registration handler
- `app/Http/Controllers/Api/V1/ReferenceDataController.php` - Reference data
- `app/Http/Requests/Auth/RegisterRequest.php` - Validation rules
- `app/Models/Sector.php` and `app/Models/Trade.php` - Data models

## Expected Behavior

### For Artisans
1. Sector dropdown loads automatically when ARTISAN is selected
2. Trade dropdown appears after sector selection
3. Both sector and trade are required for form submission
4. Backend receives and validates `trade_category` parameter

### For Other User Types
1. Sector/trade fields are hidden
2. Registration works without trade information
3. Backend handles different user types appropriately

## Error Handling
- Network errors show retry options
- Validation errors display appropriate messages
- Loading states prevent multiple submissions
- Form validation prevents submission with missing required fields

## Notes
- TradeController is initialized globally in main.dart
- All API endpoints are properly configured in ApiConstants
- Backend validation ensures trade_category exists in database
- Frontend caches sector/trade data for better UX