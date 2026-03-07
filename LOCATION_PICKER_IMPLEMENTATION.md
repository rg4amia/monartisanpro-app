# Location Picker with Yandex Maps Implementation

## Overview
Implemented a location picker screen using Yandex Maps that allows users to select their mission location by tapping on the map.

## Implementation

### 1. New Files Created

#### Location Picker Screen
- `frontend_flutter/lib/modules/missions/views/location_picker_screen.dart`
  - Full-screen Yandex Maps interface
  - Center marker for selected location
  - Current location detection
  - Address display (coordinates)
  - Confirm and cancel actions

### 2. Updated Files

#### Routes
- `frontend_flutter/lib/app/routes/app_routes.dart`
  - Added `locationPicker` route constant

- `frontend_flutter/lib/app/routes/app_pages.dart`
  - Added location picker route configuration

#### Mission Request Screen
- `frontend_flutter/lib/modules/missions/views/mission_request_screen.dart`
  - Added latitude and longitude state variables
  - Integrated location picker navigation
  - Updates location display with selected coordinates
  - Stores lat/lng for API submission

## Features

### Location Picker Screen
- **Interactive Map**: Tap anywhere to select location
- **Center Marker**: Visual indicator of selected point
- **Current Location**: Button to use device GPS
- **Address Display**: Shows coordinates of selected location
- **Smooth Animations**: Camera movements with easing
- **Confirm/Cancel**: Bottom panel with action buttons

### User Experience
1. User taps "Change" on location card
2. Location picker opens with current location
3. User can:
   - Tap anywhere on map to select location
   - Use "Ma position" button for GPS location
   - Pan and zoom the map
4. Selected location shown with center marker
5. Coordinates displayed in bottom panel
6. User confirms selection
7. Returns to mission request with location data

### Design
- Clean, modern UI matching app design
- Gradient top bar with back button
- Center pin marker (indigo color)
- Bottom panel with location info
- White buttons with proper spacing
- Loading states for GPS

## Technical Details

### Map Integration
- Uses Yandex Maps MapKit
- Custom tap listener for location selection
- Camera positioning with animations
- Default location: Abidjan (5.3484, -4.0169)

### Location Data
- Stores latitude and longitude
- Returns data to mission request screen
- Format: `{ latitude, longitude, address }`

### Permissions
- Requires location permissions (already configured)
- Graceful fallback to default location
- Loading indicator during GPS fetch

## Future Enhancements (Optional)
1. **Reverse Geocoding**: Convert coordinates to readable addresses
2. **Search**: Add search bar for location lookup
3. **Recent Locations**: Save frequently used locations
4. **Map Styles**: Different map themes
5. **Radius Selector**: Allow users to set service radius
6. **Nearby Landmarks**: Show points of interest
7. **Address Autocomplete**: Suggest addresses as user types

## API Integration Notes
When submitting mission request, include:
```dart
{
  'latitude': _latitude.value,
  'longitude': _longitude.value,
  'location_address': _location.value,
  // ... other mission data
}
```

## Testing Checklist
- [ ] Location picker opens from mission request
- [ ] Map loads with current location
- [ ] Tap on map updates selected location
- [ ] "Ma position" button works
- [ ] Coordinates display correctly
- [ ] Confirm returns data to mission request
- [ ] Back button cancels selection
- [ ] Loading states show properly
- [ ] Works without location permissions
