import 'dart:async';
import 'package:flutter/material.dart' hide TextStyle, Icon;
import 'package:flutter/material.dart' as material show TextStyle, Icon;
import 'package:get/get.dart';
import 'package:yandex_maps_mapkit/mapkit.dart';
import 'package:yandex_maps_mapkit/image.dart' as mapkit_image;
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../core/constants/app_constants.dart';
import '../../../../core/utils/mapkit_helper.dart';
import '../../../../core/utils/marker_icon_generator.dart';
import '../../../../core/widgets/flutter_map_widget.dart';
import '../../../../shared/controllers/search_controller.dart'
    as artisan_search;
import '../../../../shared/models/artisan_search_model.dart';
import 'artisan_profile_screen.dart';
import 'search_filter_screen.dart';

class MapSearchScreen extends StatefulWidget {
  const MapSearchScreen({super.key});

  @override
  State<MapSearchScreen> createState() => _MapSearchScreenState();
}

class _MapSearchScreenState extends State<MapSearchScreen> {
  final _searchController = Get.find<artisan_search.ArtisanSearchController>();
  MapWindow? _mapWindow;
  MapObjectCollection? _placemarkCollection;
  final _placemarkTapListeners = <MapObjectTapListener>[];
  bool _showListView = false;

  @override
  void initState() {
    super.initState();
    _initializeMap();
  }

  Future<void> _initializeMap() async {
    // Wait a bit for the map to be ready
    await Future.delayed(const Duration(milliseconds: 500));
    await _loadArtisans();
  }

  Future<void> _loadArtisans() async {
    await _searchController.searchArtisans();
    _updatePlacemarks();
  }

  Future<void> _updatePlacemarks() async {
    if (_placemarkCollection == null) return;

    // Clear existing placemarks
    _placemarkCollection!.clear();
    _placemarkTapListeners.clear();

    // Add user position marker if available
    final userPosition = _searchController.currentPosition.value;
    if (userPosition != null) {
      try {
        // Generate user marker icon
        final userIconBytes = await MarkerIconGenerator.createUserMarker();

        _placemarkCollection!.addPlacemark()
          ..geometry = MapKitHelper.createPoint(
            userPosition.latitude,
            userPosition.longitude,
          )
          ..setIcon(
            mapkit_image.ImageProvider.fromImageProvider(
              MemoryImage(userIconBytes),
            ),
          )
          ..opacity = 1.0;

        debugPrint('User position marker created');
      } catch (e) {
        debugPrint('Error creating user position marker: $e');
        // Fallback: create simple marker
        _placemarkCollection!.addPlacemark()
          ..geometry = MapKitHelper.createPoint(
            userPosition.latitude,
            userPosition.longitude,
          )
          ..opacity = 1.0;
      }
    }

    // Add artisan markers
    int markersAdded = 0;
    for (final artisan in _searchController.searchResults) {
      if (artisan.latitude != null && artisan.longitude != null) {
        try {
          final isNearby = artisan.distance != null && artisan.distance! < 2000;

          // Generate artisan marker icon
          final artisanIconBytes =
              await MarkerIconGenerator.createArtisanMarker(isNearby: isNearby);

          final artisanPlacemark = _placemarkCollection!.addPlacemark()
            ..geometry = MapKitHelper.createPoint(
              artisan.latitude!,
              artisan.longitude!,
            )
            ..setIcon(
              mapkit_image.ImageProvider.fromImageProvider(
                MemoryImage(artisanIconBytes),
              ),
            )
            ..opacity = 1.0;

          // Create tap listener for this placemark
          final tapListener = _ArtisanPlacemarkTapListener(
            onTap: () => _showArtisanBottomSheet(artisan),
          );

          artisanPlacemark.addTapListener(tapListener);
          _placemarkTapListeners.add(tapListener);
          markersAdded++;

          debugPrint(
            'Added ${isNearby ? 'nearby' : 'regular'} artisan marker: ${artisan.name}',
          );
        } catch (e) {
          debugPrint('Error creating artisan marker for ${artisan.name}: $e');
          // Fallback: create simple marker
          try {
            final artisanPlacemark = _placemarkCollection!.addPlacemark()
              ..geometry = MapKitHelper.createPoint(
                artisan.latitude!,
                artisan.longitude!,
              )
              ..opacity = 1.0;

            final tapListener = _ArtisanPlacemarkTapListener(
              onTap: () => _showArtisanBottomSheet(artisan),
            );

            artisanPlacemark.addTapListener(tapListener);
            _placemarkTapListeners.add(tapListener);
            markersAdded++;
          } catch (fallbackError) {
            debugPrint('Fallback marker also failed: $fallbackError');
          }
        }
      }
    }

    debugPrint('Added $markersAdded artisan markers to map');
    setState(() {});
  }

  void _showArtisanBottomSheet(ArtisanSearchResult artisan) {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (context) => Container(
        decoration: BoxDecoration(
          color: Theme.of(context).scaffoldBackgroundColor,
          borderRadius: const BorderRadius.vertical(
            top: Radius.circular(Spacing.radiusXl),
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.2),
              blurRadius: 20,
              offset: const Offset(0, -4),
            ),
          ],
        ),
        padding: const EdgeInsets.all(Spacing.xl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Handle
            Center(
              child: Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: AppColors.lightTextTertiary,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
            const SizedBox(height: Spacing.lg),

            // Artisan Info Card
            Container(
              padding: const EdgeInsets.all(Spacing.base),
              decoration: BoxDecoration(
                color: Theme.of(context).cardColor,
                borderRadius: BorderRadius.circular(Spacing.radiusMd),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.05),
                    blurRadius: 10,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Row(
                children: [
                  // Avatar with status indicator
                  Stack(
                    children: [
                      CircleAvatar(
                        radius: 32,
                        backgroundColor: AppColors.lightAccentPrimary
                            .withValues(alpha: 0.1),
                        backgroundImage: artisan.avatar != null
                            ? NetworkImage(artisan.avatar!)
                            : null,
                        child: artisan.avatar == null
                            ? material.Icon(
                                Icons.person,
                                size: 32,
                                color: AppColors.lightAccentPrimary,
                              )
                            : null,
                      ),
                      if (artisan.isNearby)
                        Positioned(
                          right: 0,
                          bottom: 0,
                          child: Container(
                            padding: const EdgeInsets.all(4),
                            decoration: BoxDecoration(
                              color: AppColors.goldenMarker,
                              shape: BoxShape.circle,
                              border: Border.all(color: Colors.white, width: 2),
                            ),
                            child: const material.Icon(
                              Icons.location_on,
                              color: Colors.white,
                              size: 12,
                            ),
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(width: Spacing.base),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          artisan.name,
                          style: Theme.of(context).textTheme.titleLarge
                              ?.copyWith(fontWeight: FontWeight.w600),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        const SizedBox(height: 4),
                        Row(
                          children: [
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 8,
                                vertical: 4,
                              ),
                              decoration: BoxDecoration(
                                color: AppColors.lightAccentSecondary
                                    .withValues(alpha: 0.1),
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: Text(
                                artisan.tradeName ?? 'Artisan',
                                style: Theme.of(context).textTheme.bodySmall
                                    ?.copyWith(
                                      color: AppColors.lightAccentSecondary,
                                      fontWeight: FontWeight.w600,
                                    ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Row(
                          children: [
                            material.Icon(
                              Icons.location_on,
                              size: 16,
                              color: artisan.isNearby
                                  ? AppColors.goldenMarker
                                  : AppColors.lightTextSecondary,
                            ),
                            const SizedBox(width: 4),
                            Text(
                              artisan.formattedDistance,
                              style: Theme.of(context).textTheme.bodyMedium
                                  ?.copyWith(
                                    color: AppColors.lightTextSecondary,
                                  ),
                            ),
                            if (artisan.averageRating != null) ...[
                              const SizedBox(width: Spacing.md),
                              const material.Icon(
                                Icons.star,
                                size: 16,
                                color: AppColors.starRating,
                              ),
                              const SizedBox(width: 4),
                              Text(
                                artisan.averageRating!.toStringAsFixed(1),
                                style: Theme.of(context).textTheme.bodyMedium
                                    ?.copyWith(fontWeight: FontWeight.w600),
                              ),
                            ],
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: Spacing.lg),

            // View Profile Button
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () {
                  Get.back();
                  Get.to(() => ArtisanProfileScreen(artisanId: artisan.id));
                },
                style: ElevatedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: Spacing.base),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(Spacing.radiusMd),
                  ),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: const [
                    material.Icon(Icons.person_outline),
                    SizedBox(width: Spacing.sm),
                    Text('Voir le profil complet'),
                  ],
                ),
              ),
            ),
            SizedBox(height: MediaQuery.of(context).padding.bottom),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Obx(() {
        final position = _searchController.currentPosition.value;
        final errorMessage = _searchController.errorMessage.value;

        return Stack(
          children: [
            // Yandex Map
            if (position == null)
              Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    if (errorMessage.isEmpty) ...[
                      const CircularProgressIndicator(),
                      const SizedBox(height: Spacing.lg),
                      const Text('Chargement de la carte...'),
                    ] else ...[
                      const material.Icon(
                        Icons.location_off,
                        size: 64,
                        color: Colors.grey,
                      ),
                      const SizedBox(height: Spacing.lg),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 32),
                        child: Text(
                          errorMessage,
                          textAlign: TextAlign.center,
                          style: const material.TextStyle(color: Colors.grey),
                        ),
                      ),
                      const SizedBox(height: Spacing.lg),
                      ElevatedButton.icon(
                        onPressed: () => _searchController.getCurrentLocation(),
                        icon: const material.Icon(Icons.refresh),
                        label: const Text('Réessayer'),
                      ),
                    ],
                  ],
                ),
              )
            else
              FlutterMapWidget(
                onMapCreated: (mapWindow) {
                  debugPrint('Map Created!');
                  _mapWindow = mapWindow;

                  // Create placemark collection
                  _placemarkCollection = mapWindow.map.mapObjects
                      .addCollection();

                  // Move camera to user location
                  mapWindow.map.move(
                    MapKitHelper.createCameraPosition(
                      latitude: position.latitude,
                      longitude: position.longitude,
                      zoom: AppConstants.defaultMapZoom,
                    ),
                    animation: MapKitHelper.createSmoothAnimation(),
                  );

                  // Load artisans after map is ready
                  _updatePlacemarks();
                },
                onMapDispose: () {
                  _placemarkCollection = null;
                  _mapWindow = null;
                },
              ),

            // Top Search Bar
            Positioned(
              top: 0,
              left: 0,
              right: 0,
              child: SafeArea(
                child: Container(
                  margin: const EdgeInsets.all(Spacing.base),
                  decoration: BoxDecoration(
                    color: Theme.of(context).cardColor,
                    borderRadius: BorderRadius.circular(Spacing.radiusLg),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.15),
                        blurRadius: 20,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Row(
                    children: [
                      IconButton(
                        icon: const material.Icon(Icons.arrow_back),
                        onPressed: () => Get.back(),
                      ),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              () {
                                final tradeId =
                                    _searchController.selectedTradeId.value;
                                final trade = _searchController.trades
                                    .firstWhereOrNull((t) => t.id == tradeId);
                                return trade?.name ?? 'Tous les artisans';
                              }(),
                              style: Theme.of(context).textTheme.titleMedium
                                  ?.copyWith(fontWeight: FontWeight.w600),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                            if (_searchController.searchResults.isNotEmpty)
                              Text(
                                '${_searchController.searchResults.length} artisan${_searchController.searchResults.length > 1 ? 's' : ''} trouvé${_searchController.searchResults.length > 1 ? 's' : ''}',
                                style: Theme.of(context).textTheme.bodySmall
                                    ?.copyWith(
                                      color: AppColors.lightTextSecondary,
                                    ),
                              ),
                          ],
                        ),
                      ),
                      Container(
                        margin: const EdgeInsets.only(right: Spacing.sm),
                        decoration: BoxDecoration(
                          color: AppColors.lightAccentPrimary.withValues(
                            alpha: 0.1,
                          ),
                          borderRadius: BorderRadius.circular(Spacing.radiusMd),
                        ),
                        child: IconButton(
                          icon: material.Icon(
                            Icons.tune,
                            color: AppColors.lightAccentPrimary,
                          ),
                          onPressed: () async {
                            await Get.to(() => const SearchFilterScreen());
                            _loadArtisans();
                          },
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),

            // Bottom Controls with Legend
            Positioned(
              bottom: 0,
              left: 0,
              right: 0,
              child: SafeArea(
                child: Padding(
                  padding: const EdgeInsets.all(Spacing.base),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      // Legend
                      if (_searchController.searchResults.isNotEmpty)
                        Container(
                          margin: const EdgeInsets.only(bottom: Spacing.md),
                          padding: const EdgeInsets.symmetric(
                            horizontal: Spacing.base,
                            vertical: Spacing.sm,
                          ),
                          decoration: BoxDecoration(
                            color: Theme.of(
                              context,
                            ).cardColor.withOpacity(0.95),
                            borderRadius: BorderRadius.circular(
                              Spacing.radiusMd,
                            ),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(alpha: 0.1),
                                blurRadius: 10,
                                offset: const Offset(0, 2),
                              ),
                            ],
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Container(
                                width: 12,
                                height: 12,
                                decoration: BoxDecoration(
                                  color: AppColors.goldenMarker,
                                  shape: BoxShape.circle,
                                ),
                              ),
                              const SizedBox(width: Spacing.sm),
                              Text(
                                '< 2km',
                                style: Theme.of(context).textTheme.bodySmall,
                              ),
                              const SizedBox(width: Spacing.base),
                              Container(
                                width: 12,
                                height: 12,
                                decoration: BoxDecoration(
                                  color: AppColors.blueMarker,
                                  shape: BoxShape.circle,
                                ),
                              ),
                              const SizedBox(width: Spacing.sm),
                              Text(
                                '> 2km',
                                style: Theme.of(context).textTheme.bodySmall,
                              ),
                            ],
                          ),
                        ),

                      // My Location Button
                      Align(
                        alignment: Alignment.centerRight,
                        child: Container(
                          decoration: BoxDecoration(
                            color: Theme.of(context).cardColor,
                            shape: BoxShape.circle,
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(alpha: 0.15),
                                blurRadius: 10,
                                offset: const Offset(0, 2),
                              ),
                            ],
                          ),
                          child: IconButton(
                            icon: material.Icon(
                              Icons.my_location,
                              color: AppColors.lightAccentPrimary,
                            ),
                            onPressed: () async {
                              if (_mapWindow != null && position != null) {
                                _mapWindow!.map.move(
                                  MapKitHelper.createCameraPosition(
                                    latitude: position.latitude,
                                    longitude: position.longitude,
                                    zoom: AppConstants.defaultMapZoom,
                                  ),
                                  animation:
                                      MapKitHelper.createSmoothAnimation(),
                                );
                              }
                            },
                          ),
                        ),
                      ),
                      const SizedBox(height: Spacing.md),

                      // Toggle View Button
                      Container(
                        width: double.infinity,
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            colors: AppColors.primaryGradient,
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                          ),
                          borderRadius: BorderRadius.circular(Spacing.radiusLg),
                          boxShadow: [
                            BoxShadow(
                              color: AppColors.lightAccentPrimary.withValues(
                                alpha: 0.3,
                              ),
                              blurRadius: 15,
                              offset: const Offset(0, 4),
                            ),
                          ],
                        ),
                        child: Material(
                          color: Colors.transparent,
                          child: InkWell(
                            borderRadius: BorderRadius.circular(
                              Spacing.radiusLg,
                            ),
                            onTap: () {
                              setState(() => _showListView = !_showListView);
                            },
                            child: Padding(
                              padding: const EdgeInsets.symmetric(
                                vertical: Spacing.base,
                                horizontal: Spacing.lg,
                              ),
                              child: Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  material.Icon(
                                    _showListView ? Icons.map : Icons.list,
                                    color: Colors.white,
                                    size: 24,
                                  ),
                                  const SizedBox(width: Spacing.md),
                                  Text(
                                    _showListView
                                        ? 'Voir la carte'
                                        : '${_searchController.searchResults.length} artisan${_searchController.searchResults.length > 1 ? 's' : ''}',
                                    style: Theme.of(context)
                                        .textTheme
                                        .titleMedium
                                        ?.copyWith(
                                          color: Colors.white,
                                          fontWeight: FontWeight.w600,
                                        ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
        );
      }),
    );
  }

  @override
  void dispose() {
    super.dispose();
  }
}

/// Custom tap listener for placemark objects
class _ArtisanPlacemarkTapListener implements MapObjectTapListener {
  final VoidCallback onTap;

  _ArtisanPlacemarkTapListener({required this.onTap});

  @override
  bool onMapObjectTap(MapObject mapObject, Point point) {
    onTap();
    return true;
  }
}
