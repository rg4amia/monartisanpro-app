import 'dart:async';
import 'package:flutter/material.dart' hide TextStyle, Icon;
import 'package:flutter/material.dart' as material show TextStyle, Icon;
import 'package:get/get.dart';
import 'package:yandex_maps_mapkit/mapkit.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../core/constants/app_constants.dart';
import '../../../../core/utils/mapkit_helper.dart';
import '../../../../core/utils/map_markers_helper.dart';
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

  Future<void> _updatePlacemarks() async {
    if (_placemarkCollection == null) return;

    // Clear existing placemarks
    _placemarkCollection!.clear();
    _placemarkTapListeners.clear();

    // Add user position marker if available
    final userPosition = _searchController.currentPosition.value;
    if (userPosition != null) {
      try {
        _placemarkCollection!.addPlacemark()
          ..geometry = MapKitHelper.createPoint(
            userPosition.latitude,
            userPosition.longitude,
          )
          ..opacity = 1.0;

        debugPrint('User position marker created');
      } catch (e) {
        debugPrint('Error creating user position marker: $e');
      }
    }

    // Add artisan markers
    int markersAdded = 0;
    for (final artisan in _searchController.searchResults) {
      if (artisan.latitude != null && artisan.longitude != null) {
        try {
          final isNearby = artisan.distance != null && artisan.distance! < 2000;

          final artisanPlacemark = _placemarkCollection!.addPlacemark()
            ..geometry = MapKitHelper.createPoint(
              artisan.latitude!,
              artisan.longitude!,
            )
            ..opacity = 1.0;

          // Create tap listener for this placemark
          final tapListener = _ArtisanPlacemarkTapListener(
            onTap: () => _showArtisanBottomSheet(artisan),
          );

          artisanPlacemark.addTapListener(tapListener);
          _placemarkTapListeners.add(tapListener);
          markersAdded++;
        } catch (e) {
          debugPrint('Error creating artisan marker for ${artisan.name}: $e');
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

            // Stats Row
            if (artisan.projectsCompleted != null ||
                artisan.experienceYears != null)
              Row(
                children: [
                  if (artisan.projectsCompleted != null)
                    Expanded(
                      child: _buildStatCard(
                        context,
                        icon: Icons.check_circle_outline,
                        label: 'Projets',
                        value: artisan.projectsCompleted.toString(),
                      ),
                    ),
                  if (artisan.projectsCompleted != null &&
                      artisan.experienceYears != null)
                    const SizedBox(width: Spacing.md),
                  if (artisan.experienceYears != null)
                    Expanded(
                      child: _buildStatCard(
                        context,
                        icon: Icons.work_outline,
                        label: 'Expérience',
                        value: '${artisan.experienceYears} ans',
                      ),
                    ),
                ],
              ),
            if (artisan.projectsCompleted != null ||
                artisan.experienceYears != null)
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

  Widget _buildStatCard(
    BuildContext context, {
    required IconData icon,
    required String label,
    required String value,
  }) {
    return Container(
      padding: const EdgeInsets.all(Spacing.md),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(Spacing.radiusMd),
        border: Border.all(
          color: AppColors.lightTextTertiary.withValues(alpha: 0.2),
        ),
      ),
      child: Column(
        children: [
          material.Icon(icon, size: 24, color: AppColors.lightAccentPrimary),
          const SizedBox(height: Spacing.sm),
          Text(
            value,
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
          ),
          Text(
            label,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: AppColors.lightTextSecondary,
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Obx(() {
        final position = _searchController.currentPosition.value;
        final errorMessage = _searchController.errorMessage.value;

        // Debug info
        debugPrint('MapSearchScreen - Position: $position');
        debugPrint('MapSearchScreen - Error: $errorMessage');
        debugPrint('MapSearchScreen - Map Ready: $_mapReady');

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
                        debugPrint('MapSearchScreen - Map Created!');
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
                          debugPrint(
                            'MapSearchScreen - Camera moved to position',
                          );

                          // Load artisans after map is ready
                          _updatePlacemarks();
                        } catch (e) {
                          debugPrint(
                            'MapSearchScreen - Error moving camera: $e',
                          );
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
                        color: Colors.white.withValues(alpha: 0.8),
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
                              final position =
                                  _searchController.currentPosition.value;
                              if (position != null && _mapWindow != null) {
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

            // List View Overlay
            if (_showListView)
              Positioned(
                left: 0,
                right: 0,
                bottom: 0,
                top: MediaQuery.of(context).size.height * 0.25,
                child: Container(
                  decoration: BoxDecoration(
                    color: Theme.of(context).scaffoldBackgroundColor,
                    borderRadius: const BorderRadius.only(
                      topLeft: Radius.circular(Spacing.radiusXl),
                      topRight: Radius.circular(Spacing.radiusXl),
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.2),
                        blurRadius: 20,
                        offset: const Offset(0, -4),
                      ),
                    ],
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

                      // Header
                      Padding(
                        padding: const EdgeInsets.symmetric(
                          horizontal: Spacing.lg,
                        ),
                        child: Row(
                          children: [
                            Text(
                              'Artisans disponibles',
                              style: Theme.of(context).textTheme.titleLarge
                                  ?.copyWith(fontWeight: FontWeight.bold),
                            ),
                            const Spacer(),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: Spacing.md,
                                vertical: Spacing.sm,
                              ),
                              decoration: BoxDecoration(
                                color: AppColors.lightAccentPrimary.withValues(
                                  alpha: 0.1,
                                ),
                                borderRadius: BorderRadius.circular(
                                  Spacing.radiusMd,
                                ),
                              ),
                              child: Text(
                                '${_searchController.searchResults.length}',
                                style: Theme.of(context).textTheme.titleMedium
                                    ?.copyWith(
                                      color: AppColors.lightAccentPrimary,
                                      fontWeight: FontWeight.bold,
                                    ),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: Spacing.md),

                      // List
                      Expanded(
                        child: () {
                          final results = _searchController.searchResults;
                          if (results.isEmpty) {
                            return Center(
                              child: Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  material.Icon(
                                    Icons.search_off,
                                    size: 64,
                                    color: AppColors.lightTextTertiary,
                                  ),
                                  const SizedBox(height: Spacing.lg),
                                  Text(
                                    'Aucun artisan dans cette zone',
                                    style: Theme.of(context)
                                        .textTheme
                                        .titleMedium
                                        ?.copyWith(
                                          color: AppColors.lightTextSecondary,
                                        ),
                                  ),
                                ],
                              ),
                            );
                          }

                          return ListView.separated(
                            padding: const EdgeInsets.all(Spacing.lg),
                            itemCount: results.length,
                            separatorBuilder: (context, index) =>
                                const SizedBox(height: Spacing.md),
                            itemBuilder: (context, index) {
                              final artisan = results[index];
                              return Container(
                                decoration: BoxDecoration(
                                  color: Theme.of(context).cardColor,
                                  borderRadius: BorderRadius.circular(
                                    Spacing.radiusMd,
                                  ),
                                  boxShadow: [
                                    BoxShadow(
                                      color: Colors.black.withValues(
                                        alpha: 0.05,
                                      ),
                                      blurRadius: 10,
                                      offset: const Offset(0, 2),
                                    ),
                                  ],
                                ),
                                child: Material(
                                  color: Colors.transparent,
                                  child: InkWell(
                                    borderRadius: BorderRadius.circular(
                                      Spacing.radiusMd,
                                    ),
                                    onTap: () {
                                      Get.to(
                                        () => ArtisanProfileScreen(
                                          artisanId: artisan.id,
                                        ),
                                      );
                                    },
                                    child: Padding(
                                      padding: const EdgeInsets.all(
                                        Spacing.base,
                                      ),
                                      child: Row(
                                        children: [
                                          // Avatar with badge
                                          Stack(
                                            children: [
                                              CircleAvatar(
                                                radius: 28,
                                                backgroundColor: AppColors
                                                    .lightAccentPrimary
                                                    .withValues(alpha: 0.1),
                                                backgroundImage:
                                                    artisan.avatar != null
                                                    ? NetworkImage(
                                                        artisan.avatar!,
                                                      )
                                                    : null,
                                                child: artisan.avatar == null
                                                    ? material.Icon(
                                                        Icons.person,
                                                        color: AppColors
                                                            .lightAccentPrimary,
                                                      )
                                                    : null,
                                              ),
                                              if (artisan.isNearby)
                                                Positioned(
                                                  right: 0,
                                                  bottom: 0,
                                                  child: Container(
                                                    padding:
                                                        const EdgeInsets.all(3),
                                                    decoration: BoxDecoration(
                                                      color: AppColors
                                                          .goldenMarker,
                                                      shape: BoxShape.circle,
                                                      border: Border.all(
                                                        color: Theme.of(
                                                          context,
                                                        ).cardColor,
                                                        width: 2,
                                                      ),
                                                    ),
                                                    child: const material.Icon(
                                                      Icons.location_on,
                                                      color: Colors.white,
                                                      size: 10,
                                                    ),
                                                  ),
                                                ),
                                            ],
                                          ),
                                          const SizedBox(width: Spacing.md),
                                          Expanded(
                                            child: Column(
                                              crossAxisAlignment:
                                                  CrossAxisAlignment.start,
                                              children: [
                                                Text(
                                                  artisan.name,
                                                  style: Theme.of(context)
                                                      .textTheme
                                                      .titleMedium
                                                      ?.copyWith(
                                                        fontWeight:
                                                            FontWeight.w600,
                                                      ),
                                                  maxLines: 1,
                                                  overflow:
                                                      TextOverflow.ellipsis,
                                                ),
                                                const SizedBox(height: 4),
                                                Text(
                                                  artisan.tradeName ??
                                                      'Artisan',
                                                  style: Theme.of(context)
                                                      .textTheme
                                                      .bodyMedium
                                                      ?.copyWith(
                                                        color: AppColors
                                                            .lightTextSecondary,
                                                      ),
                                                ),
                                                const SizedBox(height: 4),
                                                Row(
                                                  children: [
                                                    material.Icon(
                                                      Icons.location_on,
                                                      size: 14,
                                                      color: artisan.isNearby
                                                          ? AppColors
                                                                .goldenMarker
                                                          : AppColors
                                                                .lightTextSecondary,
                                                    ),
                                                    const SizedBox(width: 4),
                                                    Text(
                                                      artisan.formattedDistance,
                                                      style: Theme.of(context)
                                                          .textTheme
                                                          .bodySmall
                                                          ?.copyWith(
                                                            color: AppColors
                                                                .lightTextSecondary,
                                                          ),
                                                    ),
                                                    if (artisan.averageRating !=
                                                        null) ...[
                                                      const SizedBox(
                                                        width: Spacing.md,
                                                      ),
                                                      const material.Icon(
                                                        Icons.star,
                                                        size: 14,
                                                        color: AppColors
                                                            .starRating,
                                                      ),
                                                      const SizedBox(width: 4),
                                                      Text(
                                                        artisan.averageRating!
                                                            .toStringAsFixed(1),
                                                        style: Theme.of(context)
                                                            .textTheme
                                                            .bodySmall
                                                            ?.copyWith(
                                                              fontWeight:
                                                                  FontWeight
                                                                      .w600,
                                                            ),
                                                      ),
                                                    ],
                                                  ],
                                                ),
                                              ],
                                            ),
                                          ),
                                          material.Icon(
                                            Icons.chevron_right,
                                            color: AppColors.lightTextTertiary,
                                          ),
                                        ],
                                      ),
                                    ),
                                  ),
                                ),
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
