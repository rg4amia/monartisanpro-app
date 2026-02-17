import 'dart:async';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:google_maps_cluster_manager/google_maps_cluster_manager.dart'
    as cluster_manager;
import 'package:get_storage/get_storage.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../core/constants/app_constants.dart';
import '../../../../shared/controllers/search_controller.dart'
    as artisan_search;
import '../../../../shared/models/artisan_search_model.dart';
import 'artisan_profile_screen.dart';
import 'search_filter_screen.dart';

class ArtisanClusterItem with cluster_manager.ClusterItem {
  final ArtisanSearchResult artisan;

  ArtisanClusterItem(this.artisan);

  @override
  LatLng get location =>
      LatLng(artisan.fuzzyLocation!.latitude, artisan.fuzzyLocation!.longitude);

  @override
  String get geohash => '';
}

class MapSearchScreen extends StatefulWidget {
  const MapSearchScreen({super.key});

  @override
  State<MapSearchScreen> createState() => _MapSearchScreenState();
}

class _MapSearchScreenState extends State<MapSearchScreen> {
  final _searchController = Get.find<artisan_search.ArtisanSearchController>();
  final _storage = GetStorage();
  GoogleMapController? _mapController;
  cluster_manager.ClusterManager? _clusterManager;
  Set<Marker> _markers = {};
  bool _showListView = false;
  Timer? _debounceTimer;
  double _currentZoom = AppConstants.defaultMapZoom;

  // Cache settings
  static const String _cacheKey = 'map_search_cache';
  static const Duration _cacheDuration = Duration(minutes: 5);

  @override
  void initState() {
    super.initState();
    _initializeClusterManager();
    _loadArtisans();
  }

  void _initializeClusterManager() {
    _clusterManager = cluster_manager.ClusterManager<ArtisanClusterItem>(
      [],
      _updateMarkers,
      markerBuilder: _markerBuilder,
      levels: const [1, 3, 5, 8, 11, 14, 16, 18, 20], // Optimized zoom levels
      extraPercent: 0.3, // Larger cluster tolerance for better grouping
      stopClusteringZoom: 17.0, // Stop clustering at street level
    );
  }

  Future<void> _loadArtisans() async {
    // Check cache first
    final cached = _getCachedResults();
    if (cached != null) {
      _clusterManager!.setItems(cached);
      return;
    }

    // Load from API
    await _searchController.searchArtisans();

    final items = _searchController.searchResults
        .where((a) => a.fuzzyLocation != null)
        .map((a) => ArtisanClusterItem(a))
        .toList();

    _clusterManager!.setItems(items);
    _cacheResults(items);
  }

  List<ArtisanClusterItem>? _getCachedResults() {
    final cacheData = _storage.read(_cacheKey);
    if (cacheData == null) return null;

    final timestamp = DateTime.parse(cacheData['timestamp'] as String);
    if (DateTime.now().difference(timestamp) > _cacheDuration) {
      _storage.remove(_cacheKey);
      return null;
    }

    // Return cached items (simplified - in production, deserialize properly)
    return _searchController.searchResults
        .where((a) => a.fuzzyLocation != null)
        .map((a) => ArtisanClusterItem(a))
        .toList();
  }

  void _cacheResults(List<ArtisanClusterItem> items) {
    _storage.write(_cacheKey, {
      'timestamp': DateTime.now().toIso8601String(),
      'count': items.length,
    });
  }

  Future<Marker> _markerBuilder(dynamic cluster) async {
    final typedCluster = cluster as cluster_manager.Cluster<ArtisanClusterItem>;
    if (typedCluster.isMultiple) {
      // Cluster marker
      return Marker(
        markerId: MarkerId(typedCluster.getId()),
        position: typedCluster.location,
        icon: await _getClusterBitmap(
          typedCluster.count,
          _getClusterColor(typedCluster.count),
        ),
        onTap: () {
          // Zoom in to expand cluster
          _mapController?.animateCamera(
            CameraUpdate.newLatLngZoom(typedCluster.location, _currentZoom + 2),
          );
        },
      );
    } else {
      // Single artisan marker (lazy load details on tap)
      final artisan = typedCluster.items.first.artisan;
      return Marker(
        markerId: MarkerId(artisan.id.toString()),
        position: typedCluster.location,
        icon: artisan.isNearby
            ? BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueYellow)
            : BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueBlue),
        onTap: () => _showArtisanBottomSheet(artisan),
      );
    }
  }

  Color _getClusterColor(int count) {
    if (count > 50) return Colors.red;
    if (count > 20) return Colors.orange;
    if (count > 10) return AppColors.lightAccentPrimary;
    return Colors.blue;
  }

  Future<BitmapDescriptor> _getClusterBitmap(int count, Color color) async {
    // In production, use custom cluster icons with count text
    // For now, use default with different hues
    final hue = color == Colors.red
        ? BitmapDescriptor.hueRed
        : color == Colors.orange
        ? BitmapDescriptor.hueOrange
        : BitmapDescriptor.hueAzure;

    return BitmapDescriptor.defaultMarkerWithHue(hue);
  }

  void _updateMarkers(Set<Marker> markers) {
    setState(() {
      _markers = markers;
    });
  }

  void _onCameraMove(CameraPosition position) {
    _currentZoom = position.zoom;

    // Debounce camera movement to avoid excessive API calls
    _debounceTimer?.cancel();
    _debounceTimer = Timer(const Duration(milliseconds: 500), () {
      _clusterManager!.onCameraMove(position);
      // Optionally trigger new search based on visible bounds
      // _searchInVisibleRegion(position);
    });
  }

  void _onCameraIdle() {
    _clusterManager!.updateMap();
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
                        artisan.tradeName,
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                      Row(
                        children: [
                          const Icon(Icons.location_on, size: 14),
                          const SizedBox(width: 4),
                          Text(artisan.distanceText),
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
      body: Stack(
        children: [
          // Google Map
          Obx(() {
            final position = _searchController.currentPosition.value;
            if (position == null) {
              return const Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    CircularProgressIndicator(),
                    SizedBox(height: Spacing.lg),
                    Text('Chargement de la carte...'),
                  ],
                ),
              );
            }

            return GoogleMap(
              initialCameraPosition: CameraPosition(
                target: LatLng(position.latitude, position.longitude),
                zoom: AppConstants.defaultMapZoom,
              ),
              markers: _markers,
              myLocationEnabled: true,
              myLocationButtonEnabled: false,
              zoomControlsEnabled: false,
              mapToolbarEnabled: false,
              onMapCreated: (controller) {
                _mapController = controller;
                _clusterManager?.setMapId(controller.mapId);
              },
              onCameraMove: _onCameraMove,
              onCameraIdle: _onCameraIdle,
            );
          }),

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
                      child: Obx(() {
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
                      }),
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
                            _mapController!.animateCamera(
                              CameraUpdate.newLatLng(
                                LatLng(position.latitude, position.longitude),
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
                                Obx(
                                  () => Text(
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
                      child: Obx(() {
                        if (_searchController.searchResults.isEmpty) {
                          return const Center(
                            child: Text('Aucun artisan dans cette zone'),
                          );
                        }

                        return ListView.separated(
                          padding: const EdgeInsets.all(Spacing.base),
                          itemCount: _searchController.searchResults.length,
                          separatorBuilder: (context, index) => const Divider(),
                          itemBuilder: (context, index) {
                            final artisan =
                                _searchController.searchResults[index];
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
                                '${artisan.tradeName} - ${artisan.distanceText}',
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
                      }),
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }

  @override
  void dispose() {
    _debounceTimer?.cancel();
    _mapController?.dispose();
    super.dispose();
  }
}
