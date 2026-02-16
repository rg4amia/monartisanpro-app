import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../core/constants/app_constants.dart';
import '../../../../shared/controllers/search_controller.dart' as artisan_search;
import 'artisan_profile_screen.dart';
import 'search_filter_screen.dart';

class MapSearchScreen extends StatefulWidget {
  const MapSearchScreen({super.key});

  @override
  State<MapSearchScreen> createState() => _MapSearchScreenState();
}

class _MapSearchScreenState extends State<MapSearchScreen> {
  final _searchController = Get.find<artisan_search.ArtisanSearchController>();
  GoogleMapController? _mapController;
  final Set<Marker> _markers = {};
  bool _showListView = false;

  @override
  void initState() {
    super.initState();
    _loadArtisans();
  }

  Future<void> _loadArtisans() async {
    await _searchController.searchArtisans();
    _updateMarkers();
  }

  void _updateMarkers() {
    _markers.clear();
    for (var artisan in _searchController.searchResults) {
      if (artisan.fuzzyLocation != null) {
        _markers.add(
          Marker(
            markerId: MarkerId(artisan.id.toString()),
            position: LatLng(
              artisan.fuzzyLocation!.latitude,
              artisan.fuzzyLocation!.longitude,
            ),
            icon: artisan.isNearby
                ? BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueYellow)
                : BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueBlue),
            infoWindow: InfoWindow(
              title: artisan.name,
              snippet: '${artisan.tradeName} - ${artisan.distanceText}',
              onTap: () {
                Get.to(() => ArtisanProfileScreen(artisanId: artisan.id));
              },
            ),
          ),
        );
      }
    }
    setState(() {});
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
              },
              onCameraMove: (position) {
                // TODO: Update search based on visible map region
              },
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
                          final position = _searchController.currentPosition.value;
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
                                Obx(() => Text(
                                      _showListView
                                          ? 'Vue Carte'
                                          : '${_searchController.searchResults.length} artisans',
                                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                            color: Theme.of(context).colorScheme.primary,
                                            fontWeight: FontWeight.w600,
                                          ),
                                    )),
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
                            final artisan = _searchController.searchResults[index];
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
                                Get.to(() => ArtisanProfileScreen(
                                      artisanId: artisan.id,
                                    ));
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
    _mapController?.dispose();
    super.dispose();
  }
}
