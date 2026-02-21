# Map Markers Assets

This directory contains custom marker icons for the map view.

## Required Markers

### 1. user_position.png
- **Size**: 96x96px (48dp @2x)
- **Design**: Blue circle with white person icon
- **Colors**: 
  - Background: #4F46E5 (AppColors.lightAccentPrimary)
  - Border: White, 3px
  - Icon: White person icon
  - Shadow: Blue glow

### 2. artisan_nearby.png
- **Size**: 80x88px (40x44dp @2x) - includes pointer
- **Design**: Golden pin marker with construction icon
- **Colors**:
  - Pin: #FBBF24 (AppColors.goldenMarker)
  - Icon background: White circle
  - Icon: Golden construction/tools icon
  - Border: White, 2px

### 3. artisan_regular.png
- **Size**: 80x88px (40x44dp @2x) - includes pointer
- **Design**: Blue pin marker with construction icon
- **Colors**:
  - Pin: #5B7FFF (AppColors.blueMarker)
  - Icon background: White circle
  - Icon: Blue construction/tools icon
  - Border: White, 2px

### 4. cluster.png
- **Size**: 96x96px (48dp @2x)
- **Design**: Red circle with number placeholder
- **Colors**:
  - Background: Gradient from #EF4444 to #DC2626
  - Border: White, 3px
  - Text: White, bold
  - Shadow: Red glow

## Design Guidelines

1. **Export at 2x and 3x resolutions** for different screen densities
2. **Use PNG format** with transparency
3. **Include drop shadows** for better visibility on maps
4. **Maintain consistent visual style** across all markers
5. **Test on both light and dark map styles**

## Alternative: Using Default Markers

If custom marker images are not available, the app will fall back to default Yandex MapKit markers. The custom markers provide a better user experience with:
- Clear visual distinction between user and artisan locations
- Golden markers for nearby artisans (<2km)
- Consistent branding with app colors

## Creating Markers

You can create these markers using:
- **Figma/Sketch**: Design tools with export to PNG
- **Adobe Illustrator**: Vector graphics exported to PNG
- **Online tools**: Flaticon, Icons8, or custom icon generators
- **Code**: Flutter CustomPainter to generate programmatically

## Implementation Note

The markers are referenced in `MapMarkersHelper` class:
```dart
MapMarkersHelper.getUserPositionMarkerAsset()
MapMarkersHelper.getArtisanMarkerAsset(isNearby: true/false)
MapMarkersHelper.getClusterMarkerAsset()
```
