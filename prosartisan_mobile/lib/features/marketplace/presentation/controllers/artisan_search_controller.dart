import 'package:get/get.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:geolocator/geolocator.dart';
import 'package:prosartisan_mobile/core/domain/value_objects/gps_coordinates.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/artisan.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/mission.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/usecases/search_artisans_usecase.dart';

class ArtisanSearchController extends GetxController {
  final SearchArtisansUseCase _searchArtisansUseCase;

  ArtisanSearchController(this._searchArtisansUseCase);

  // Observable state
  final _isLoading = false.obs;
  final _artisans = <Artisan>[].obs;
  final _selectedCategory = Rx<TradeCategory?>(null);
  final _currentLocation = Rx<GPSCoordinates?>(null);
  final _searchRadius = 5.0.obs; // Default 5km radius
  final _markers = <Marker>[].obs;
  final _clusters = <String, List<Artisan>>{}.obs;

  GoogleMapController? _mapController;

  // Getters
  bool get isLoading => _isLoading.value;
  List<Artisan> get artisans => _artisans;
  TradeCategory? get selectedCategory => _selectedCategory.value;
  GPSCoordinates? get currentLocation => _currentLocation.value;
  double get searchRadius => _searchRadius.value;
  Set<Marker> get markers => _markers.toSet();
  Map<String, List<Artisan>> get clusters => _clusters;

  @override
  void onInit() {
    super.onInit();
    _getCurrentLocation();
  }

  Future<void> _getCurrentLocation() async {
    try {
      _isLoading.value = true;

      // Check location permissions
      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
        if (permission == LocationPermission.denied) {
          Get.snackbar('Erreur', 'Permission de localisation refusée');
          return;
        }
      }

      if (permission == LocationPermission.deniedForever) {
        Get.snackbar(
          'Erreur',
          'Permission de localisation refusée définitivement',
        );
        return;
      }

      // Get current position
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
        ),
      );

      _currentLocation.value = GPSCoordinates(
        latitude: position.latitude,
        longitude: position.longitude,
        accuracy: position.accuracy,
      );

      // Search for artisans at current location
      await searchArtisans();
    } catch (e) {
      Get.snackbar('Erreur', 'Impossible d\'obtenir la localisation: $e');
    } finally {
      _isLoading.value = false;
    }
  }

  Future<void> searchArtisans({
    GPSCoordinates? location,
    TradeCategory? category,
    double? radius,
  }) async {
    try {
      _isLoading.value = true;

      final searchLocation = location ?? _currentLocation.value;
      if (searchLocation == null) {
        Get.snackbar('Erreur', 'Localisation non disponible');
        return;
      }

      final searchCategory = category ?? _selectedCategory.value;
      final searchRadius = radius ?? _searchRadius.value;

      final results = await _searchArtisansUseCase.execute(
        location: searchLocation,
        radiusKm: searchRadius,
        category: searchCategory,
      );

      _artisans.value = results;
      _updateMapMarkers();
    } catch (e) {
      Get.snackbar('Erreur', 'Erreur lors de la recherche: $e');
    } finally {
      _isLoading.value = false;
    }
  }

  void setCategory(TradeCategory? category) {
    _selectedCategory.value = category;
    searchArtisans();
  }

  void setSearchRadius(double radius) {
    _searchRadius.value = radius;
    searchArtisans();
  }

  void _updateMapMarkers() {
    if (_currentLocation.value == null) return;

    final newMarkers = <Marker>[];
    final newClusters = <String, List<Artisan>>{};

    // Group artisans by proximity for clustering
    for (final artisan in _artisans) {
      final clusterKey = _getClusterKey(artisan.location);

      if (!newClusters.containsKey(clusterKey)) {
        newClusters[clusterKey] = [];
      }
      newClusters[clusterKey]!.add(artisan);
    }

    // Create markers for clusters or individual artisans
    for (final entry in newClusters.entries) {
      final artisansInCluster = entry.value;

      if (artisansInCluster.length == 1) {
        // Single artisan marker
        final artisan = artisansInCluster.first;
        final isGolden = artisan.isWithinGoldenRange(_currentLocation.value!);

        newMarkers.add(
          Marker(
            markerId: MarkerId(artisan.id),
            position: LatLng(
              artisan.location.latitude,
              artisan.location.longitude,
            ),
            icon: isGolden
                ? BitmapDescriptor.defaultMarkerWithHue(
                    BitmapDescriptor.hueYellow,
                  )
                : BitmapDescriptor.defaultMarkerWithHue(
                    BitmapDescriptor.hueBlue,
                  ),
            infoWindow: InfoWindow(
              title: artisan.businessName ?? artisan.email,
              snippet:
                  '${artisan.category.displayName} • Score: ${artisan.nzassaScore.toInt()}',
            ),
            onTap: () => _onArtisanMarkerTapped(artisan),
          ),
        );
      } else {
        // Cluster marker
        final centerArtisan = artisansInCluster.first;
        final hasGoldenArtisan = artisansInCluster.any(
          (a) => a.isWithinGoldenRange(_currentLocation.value!),
        );

        newMarkers.add(
          Marker(
            markerId: MarkerId('cluster_${entry.key}'),
            position: LatLng(
              centerArtisan.location.latitude,
              centerArtisan.location.longitude,
            ),
            icon: hasGoldenArtisan
                ? BitmapDescriptor.defaultMarkerWithHue(
                    BitmapDescriptor.hueOrange,
                  )
                : BitmapDescriptor.defaultMarkerWithHue(
                    BitmapDescriptor.hueRed,
                  ),
            infoWindow: InfoWindow(
              title: '${artisansInCluster.length} artisans',
              snippet: 'Cliquez pour voir la liste',
            ),
            onTap: () => _onClusterMarkerTapped(artisansInCluster),
          ),
        );
      }
    }

    _markers.value = newMarkers;
    _clusters.value = newClusters;
  }

  String _getClusterKey(GPSCoordinates location) {
    // Simple clustering by rounding coordinates to create grid
    final latRounded = (location.latitude * 1000).round() / 1000;
    final lngRounded = (location.longitude * 1000).round() / 1000;
    return '${latRounded}_$lngRounded';
  }

  void _onArtisanMarkerTapped(Artisan artisan) {
    // This will be handled by the UI layer
    // The UI can listen to this event and show appropriate bottom sheet
  }

  void _onClusterMarkerTapped(List<Artisan> artisans) {
    // This will be handled by the UI layer
    // The UI can listen to this event and show appropriate bottom sheet
  }

  void onMapCreated(GoogleMapController controller) {
    _mapController = controller;
  }

  void animateToLocation(GPSCoordinates location) {
    _mapController?.animateCamera(
      CameraUpdate.newLatLng(LatLng(location.latitude, location.longitude)),
    );
  }

  @override
  void onClose() {
    _mapController?.dispose();
    super.onClose();
  }
}
