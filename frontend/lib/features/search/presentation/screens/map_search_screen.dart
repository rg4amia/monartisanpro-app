import 'dart:async';
import 'package:flutter/material.dart' hide TextStyle, Icon;
import 'package:flutter/material.dart' as material show TextStyle, Icon;
import 'package:get/get.dart';
import 'package:yandex_maps_mapkit/mapkit.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../core/constants/app_constants.dart';
import '../../../../core/utils/mapkit_helper.dart';
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
  bool _mapReady = false;
  bool _mapCreated = false;

  @override
  void initState() {
    super.initState();
    _initializeMap();
  }

  Future<void> _initializeMap() async {
    // Wait a bit for the map to be ready
    await Future.delayed(const Duration(milliseconds: 500));
    await _loadArtisans();
    if (mounted) {
      setState(() => _mapReady = true);
    }
  }

  Future<void> _loadArtisans() async {
    await _searchController.searchArtisans();
    _updatePlacemarks();
  }

  void _updatePlacemarks() {
    if (_placemarkCollection == null) return;

    // Clear existing placemarks
    _placemarkCollection!.clear();
    _placemarkTapListeners.clear();

    for (final artisan in _searchController.searchResults) {
      // Use actual coordinates if available
      if (artisan.latitude != null && artisan.longitude != null) {
        final placemark = _placemarkCollection!.addPlacemark()
          ..geometry = MapKitHelper.createPoint(
            artisan.latitude!,
            artisan.longitude!,
          )
          ..opacity = 1.0;

        // Create tap listener for this placemark
        final tapListener = _ArtisanPlacemarkTapListener(
          onTap: () => _showArtisanBottomSheet(artisan),
        );

        placemark.addTapListener(tapListener);
        _placemarkTapListeners.add(tapListener);
      }
    }

    setState(() {});
  }

  void _showArtisanBottomSheet(ArtisanSearchResult artisan) {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (context) => Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(
            top: Radius.circular(Spacing.radiusLg),
          ),
        ),
        padding: const EdgeInsets.all(Spacing.lg),
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
            const SizedBox(height: Spacing.md),

            // Artisan Info
            Row(
              children: [
                CircleAvatar(
                  radius: 30,
                  backgroundImage: artisan.avatar != null
                      ? NetworkImage(artisan.avatar!)
                      : null,
                  child: artisan.avatar == null
                      ? const material.Icon(Icons.person)
                      : null,
                ),
                const SizedBox(width: Spacing.md),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              artisan.name,
                              style: Theme.of(context).textTheme.titleLarge,
                            ),
                          ),
                          if (artisan.isNearby)
                            material.Icon(
                              Icons.location_on,
                              color: AppColors.goldenMarker,
                              size: 20,
                            ),
                        ],
                      ),
                      Text(
                        artisan.tradeName ?? 'Artisan',
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                      Row(
                        children: [
                          const material.Icon(Icons.location_on, size: 14),
                          const SizedBox(width: 4),
                          Text(artisan.formattedDistance),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
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
                child: const Text('Voir le profil'),
              ),
            ),
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

        // Debug print
        print('MapSearchScreen - Position: $position');
        print('MapSearchScreen - Error: $errorMessage');
        print('MapSearchScreen - Map Ready: $_mapReady');

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
                      const SizedBox(height: Spacing.md),
                      const Text(
                        'Obtention de votre position...',
                        style: material.TextStyle(
                          fontSize: 12,
                          color: Colors.grey,
                        ),
                      ),
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
              Container(
                color: Colors.grey[200],
                child: Stack(
                  children: [
                    FlutterMapWidget(
                      onMapCreated: (mapWindow) {
                        print('MapSearchScreen - Map Created!');
                        setState(() => _mapCreated = true);
                        _mapWindow = mapWindow;

                        // Create placemark collection
                        _placemarkCollection = mapWindow.map.mapObjects
                            .addCollection();

                        // Move camera to user location
                        try {
                          mapWindow.map.move(
                            MapKitHelper.createCameraPosition(
                              latitude: position.latitude,
                              longitude: position.longitude,
                              zoom: AppConstants.defaultMapZoom,
                            ),
                            animation: MapKitHelper.createSmoothAnimation(),
                          );
                          print('MapSearchScreen - Camera moved to position');

                          // Load artisans after map is ready
                          _updatePlacemarks();
                        } catch (e) {
                          print('MapSearchScreen - Error moving camera: $e');
                        }
                      },
                      onMapDispose: () {
                        _placemarkCollection = null;
                        _mapWindow = null;
                      },
                    ),
                    // Debug overlay
                    Positioned(
                      top: 100,
                      left: 16,
                      child: Container(
                        padding: const EdgeInsets.all(8),
                        color: Colors.white.withOpacity(0.8),
                        child: Text(
                          'Lat: ${position.latitude.toStringAsFixed(4)}\n'
                          'Lng: ${position.longitude.toStringAsFixed(4)}\n'
                          'Map Ready: $_mapReady\n'
                          'Map Created: $_mapCreated',
                          style: const material.TextStyle(fontSize: 10),
                        ),
                      ),
                    ),
                    // Loading indicator if map not created
                    if (!_mapCreated)
                      const Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            CircularProgressIndicator(),
                            SizedBox(height: 16),
                            Text('Initialisation de la carte...'),
                          ],
                        ),
                      ),
                  ],
                ),
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
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(Spacing.radiusLg),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.2),
                        blurRadius: 10,
                        offset: const Offset(0, 2),
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
                        child: Text(
                          () {
                            final tradeId =
                                _searchController.selectedTradeId.value;
                            final trade = _searchController.trades
                                .firstWhereOrNull((t) => t.id == tradeId);
                            return trade?.name ?? 'Tous les artisans';
                          }(),
                          style: Theme.of(context).textTheme.titleMedium,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      IconButton(
                        icon: const material.Icon(Icons.tune),
                        onPressed: () async {
                          await Get.to(() => const SearchFilterScreen());
                          _loadArtisans();
                        },
                      ),
                    ],
                  ),
                ),
              ),
            ),

            // Bottom Controls
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
                      // My Location Button
                      Align(
                        alignment: Alignment.centerRight,
                        child: FloatingActionButton(
                          mini: true,
                          backgroundColor: Colors.white,
                          onPressed: () async {
                            final position =
                                _searchController.currentPosition.value;
                            if (position != null && _mapWindow != null) {
                              _mapWindow!.map.move(
                                MapKitHelper.createCameraPosition(
                                  latitude: position.latitude,
                                  longitude: position.longitude,
                                  zoom: AppConstants.defaultMapZoom,
                                ),
                                animation: MapKitHelper.createSmoothAnimation(),
                              );
                            }
                          },
                          child: material.Icon(
                            Icons.my_location,
                            color: Theme.of(context).colorScheme.primary,
                          ),
                        ),
                      ),
                      const SizedBox(height: Spacing.md),

                      // Toggle View Button
                      Container(
                        width: double.infinity,
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(Spacing.radiusLg),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.2),
                              blurRadius: 10,
                              offset: const Offset(0, 2),
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
                              padding: const EdgeInsets.all(Spacing.base),
                              child: Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  material.Icon(
                                    _showListView ? Icons.map : Icons.list,
                                    color: Theme.of(
                                      context,
                                    ).colorScheme.primary,
                                  ),
                                  const SizedBox(width: Spacing.sm),
                                  Text(
                                    _showListView
                                        ? 'Vue Carte'
                                        : '${_searchController.searchResults.length} artisans',
                                    style: Theme.of(context)
                                        .textTheme
                                        .titleMedium
                                        ?.copyWith(
                                          color: Theme.of(
                                            context,
                                          ).colorScheme.primary,
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

            // List View Overlay
            if (_showListView)
              Positioned(
                left: 0,
                right: 0,
                bottom: 0,
                top: MediaQuery.of(context).size.height * 0.3,
                child: Container(
                  decoration: const BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.only(
                      topLeft: Radius.circular(Spacing.radiusLg),
                      topRight: Radius.circular(Spacing.radiusLg),
                    ),
                  ),
                  child: Column(
                    children: [
                      // Handle
                      Container(
                        margin: const EdgeInsets.symmetric(
                          vertical: Spacing.md,
                        ),
                        width: 40,
                        height: 4,
                        decoration: BoxDecoration(
                          color: AppColors.lightTextTertiary,
                          borderRadius: BorderRadius.circular(2),
                        ),
                      ),

                      // List
                      Expanded(
                        child: () {
                          final results = _searchController.searchResults;
                          if (results.isEmpty) {
                            return const Center(
                              child: Text('Aucun artisan dans cette zone'),
                            );
                          }

                          return ListView.separated(
                            padding: const EdgeInsets.all(Spacing.base),
                            itemCount: results.length,
                            separatorBuilder: (context, index) =>
                                const Divider(),
                            itemBuilder: (context, index) {
                              final artisan = results[index];
                              return ListTile(
                                leading: CircleAvatar(
                                  backgroundImage: artisan.avatar != null
                                      ? NetworkImage(artisan.avatar!)
                                      : null,
                                  child: artisan.avatar == null
                                      ? const material.Icon(Icons.person)
                                      : null,
                                ),
                                title: Row(
                                  children: [
                                    Expanded(child: Text(artisan.name)),
                                    if (artisan.isNearby)
                                      material.Icon(
                                        Icons.location_on,
                                        size: 16,
                                        color: AppColors.goldenMarker,
                                      ),
                                  ],
                                ),
                                subtitle: Text(
                                  '${artisan.tradeName ?? 'Artisan'} - ${artisan.formattedDistance}',
                                ),
                                trailing: const material.Icon(
                                  Icons.chevron_right,
                                ),
                                onTap: () {
                                  Get.to(
                                    () => ArtisanProfileScreen(
                                      artisanId: artisan.id,
                                    ),
                                  );
                                },
                              );
                            },
                          );
                        }(),
                      ),
                    ],
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
