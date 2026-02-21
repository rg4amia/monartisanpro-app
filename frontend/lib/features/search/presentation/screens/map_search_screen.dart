import 'dart:async';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:yandex_mapkit/yandex_mapkit.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../core/constants/app_constants.dart';
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
  YandexMapController? _mapController;
  final List<PlacemarkMapObject> _placemarks = [];
  bool _showListView = false;

  @override
  void initState() {
    super.initState();
    _loadArtisans();
  }

  Future<void> _loadArtisans() async {
    await _searchController.searchArtisans();
    _updatePlacemarks();
  }

  void _updatePlacemarks() {
    _placemarks.clear();

    for (final artisan in _searchController.searchResults) {
      // Use actual coordinates if available
      if (artisan.latitude != null && artisan.longitude != null) {
        _placemarks.add(
          PlacemarkMapObject(
            mapId: MapObjectId('artisan_${artisan.id}'),
            point: Point(
              latitude: artisan.latitude!,
              longitude: artisan.longitude!,
            ),
            // Using default Yandex marker (will be customized later with PNG assets)
            opacity: 1.0,
            onTap: (placemark, point) => _showArtisanBottomSheet(artisan),
          ),
        );
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
                      ? const Icon(Icons.person)
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
                            Icon(
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
                          const Icon(Icons.location_on, size: 14),
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
        
        return Stack(
          children: [
            // Yandex Map
            if (position == null)
              const Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    CircularProgressIndicator(),
                    SizedBox(height: Spacing.lg),
                    Text('Chargement de la carte...'),
                  ],
                ),
              )
            else
              YandexMap(
                mapObjects: _placemarks,
                onMapCreated: (controller) {
                  _mapController = controller;

                  // Move camera to user location
                  _mapController!.moveCamera(
                    CameraUpdate.newCameraPosition(
                      CameraPosition(
                        target: Point(
                          latitude: position.latitude,
                          longitude: position.longitude,
                        ),
                        zoom: AppConstants.defaultMapZoom,
                      ),
                    ),
                  );
                },
                onCameraPositionChanged: (cameraPosition, reason, finished) {
                  // Optional: reload markers based on visible region
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
                        icon: const Icon(Icons.arrow_back),
                        onPressed: () => Get.back(),
                      ),
                      Expanded(
                        child: Builder(
                          builder: (context) {
                            final tradeId = _searchController.selectedTradeId.value;
                            final trade = _searchController.trades.firstWhereOrNull(
                              (t) => t.id == tradeId,
                            );
                            return Text(
                              trade?.name ?? 'Tous les artisans',
                              style: Theme.of(context).textTheme.titleMedium,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            );
                          },
                        ),
                      ),
                      IconButton(
                        icon: const Icon(Icons.tune),
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
                            if (position != null && _mapController != null) {
                              _mapController!.moveCamera(
                                CameraUpdate.newCameraPosition(
                                  CameraPosition(
                                    target: Point(
                                      latitude: position.latitude,
                                      longitude: position.longitude,
                                    ),
                                    zoom: AppConstants.defaultMapZoom,
                                  ),
                                ),
                                animation: const MapAnimation(
                                  type: MapAnimationType.smooth,
                                  duration: 1.0,
                                ),
                              );
                            }
                          },
                          child: Icon(
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
                            borderRadius: BorderRadius.circular(Spacing.radiusLg),
                            onTap: () {
                              setState(() => _showListView = !_showListView);
                            },
                            child: Padding(
                              padding: const EdgeInsets.all(Spacing.base),
                              child: Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(
                                    _showListView ? Icons.map : Icons.list,
                                    color: Theme.of(context).colorScheme.primary,
                                  ),
                                  const SizedBox(width: Spacing.sm),
                                  Builder(
                                    builder: (context) {
                                      final resultsCount = _searchController.searchResults.length;
                                      return Text(
                                        _showListView
                                            ? 'Vue Carte'
                                            : '$resultsCount artisans',
                                        style: Theme.of(context)
                                            .textTheme
                                            .titleMedium
                                            ?.copyWith(
                                              color: Theme.of(
                                                context,
                                              ).colorScheme.primary,
                                              fontWeight: FontWeight.w600,
                                            ),
                                      );
                                    },
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
                        margin: const EdgeInsets.symmetric(vertical: Spacing.md),
                        width: 40,
                        height: 4,
                        decoration: BoxDecoration(
                          color: AppColors.lightTextTertiary,
                          borderRadius: BorderRadius.circular(2),
                        ),
                      ),

                      // List
                      Expanded(
                        child: Builder(
                          builder: (context) {
                            final results = _searchController.searchResults;
                            if (results.isEmpty) {
                              return const Center(
                                child: Text('Aucun artisan dans cette zone'),
                              );
                            }

                            return ListView.separated(
                              padding: const EdgeInsets.all(Spacing.base),
                              itemCount: results.length,
                              separatorBuilder: (context, index) => const Divider(),
                              itemBuilder: (context, index) {
                                final artisan = results[index];
                                return ListTile(
                                  leading: CircleAvatar(
                                    backgroundImage: artisan.avatar != null
                                        ? NetworkImage(artisan.avatar!)
                                        : null,
                                    child: artisan.avatar == null
                                        ? const Icon(Icons.person)
                                        : null,
                                  ),
                                  title: Row(
                                    children: [
                                      Expanded(child: Text(artisan.name)),
                                      if (artisan.isNearby)
                                        Icon(
                                          Icons.location_on,
                                          size: 16,
                                          color: AppColors.goldenMarker,
                                        ),
                                    ],
                                  ),
                                  subtitle: Text(
                                    '${artisan.tradeName ?? 'Artisan'} - ${artisan.formattedDistance}',
                                  ),
                                  trailing: const Icon(Icons.chevron_right),
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
                          },
                        ),
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
