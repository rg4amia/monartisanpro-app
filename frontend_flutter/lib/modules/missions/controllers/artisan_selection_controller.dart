import 'package:geolocator/geolocator.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/utils/error_handler.dart';
import '../../../data/models/artisan_model.dart';
import '../../../data/repositories/artisan_repository.dart';
import 'missions_controller.dart';

class ArtisanSelectionController extends GetxController {
  final ArtisanRepository _artisanRepository = ArtisanRepository();

  final isLoading = false.obs;
  final isMapView = true.obs;
  final selectedCategory = ''.obs;
  final selectedCategoryId = 0.obs;
  final selectedTradeId = 0.obs;
  final clientLatitude = 0.0.obs;
  final clientLongitude = 0.0.obs;
  final missionDescription = ''.obs;
  final locationAddress = ''.obs;
  final locationDetail = ''.obs;
  final nightIntervention = false.obs;
  final artisans = <ArtisanModel>[].obs;
  final searchDistant = false.obs;
  final photos = <XFile>[].obs;
  final video = Rx<XFile?>(null);

  void toggleSearchDistant() {
    searchDistant.value = !searchDistant.value;
    refreshArtisans();
  }

  bool _initializedFromArgs = false;

  @override
  void onInit() {
    super.onInit();
    if (Get.arguments == null) {
      _loadNearbyArtisans();
    }
  }

  void initializeWithMissionData(Map<String, dynamic> data) {
    final nextCategory = (data['category'] ?? '').toString();
    final nextCategoryId = _parseInt(data['categoryId']);
    final nextTradeId = _parseInt(data['tradeId']);
    final nextLatitude = _parseDouble(data['latitude']);
    final nextLongitude = _parseDouble(data['longitude']);
    final nextDescription = (data['description'] ?? '').toString();
    final nextLocationAddress = (data['locationAddress'] ?? '').toString();
    final nextLocationDetail = (data['locationDetail'] ?? '').toString();
    final nextNightIntervention = _parseBool(data['nightIntervention']);
    final nextPhotos = data['photos'] as List<XFile>? ?? [];
    final nextVideo = data['video'] as XFile?;

    final hasSamePayload = _initializedFromArgs &&
        selectedCategory.value == nextCategory &&
        selectedCategoryId.value == nextCategoryId &&
        selectedTradeId.value == nextTradeId &&
        clientLatitude.value == nextLatitude &&
        clientLongitude.value == nextLongitude &&
        missionDescription.value == nextDescription &&
        locationAddress.value == nextLocationAddress &&
        locationDetail.value == nextLocationDetail &&
        nightIntervention.value == nextNightIntervention;

    if (hasSamePayload) {
      return;
    }

    _initializedFromArgs = true;
    selectedCategory.value = nextCategory;
    selectedCategoryId.value = nextCategoryId;
    selectedTradeId.value = nextTradeId;
    clientLatitude.value = nextLatitude;
    clientLongitude.value = nextLongitude;
    missionDescription.value = nextDescription;
    locationAddress.value = nextLocationAddress;
    locationDetail.value = nextLocationDetail;
    nightIntervention.value = nextNightIntervention;
    photos.value = nextPhotos;
    video.value = nextVideo;

    _loadNearbyArtisans();
  }

  void setMapView(bool isMap) {
    isMapView.value = isMap;
  }

  Future<void> refreshArtisans() async {
    await _loadNearbyArtisans();
  }

  Future<void> selectArtisan(ArtisanModel artisan) async {
    final missionsController = Get.find<MissionsController>();

    isLoading.value = true;
    try {
      // 1. Upload files first (if any)
      final List<String> uploadedUrls = [];
      for (final photo in photos) {
        final url = await missionsController.uploadFile(photo.path);
        uploadedUrls.add(url);
      }
      if (video.value != null) {
        final url = await missionsController.uploadFile(video.value!.path);
        uploadedUrls.add(url);
      }

      // 2. Call createMission with the list of URLs
      final mission = await missionsController.createMission(
        artisanId: artisan.id,
        description: missionDescription.value,
        category: selectedCategory.value.isNotEmpty
            ? selectedCategory.value
            : (artisan.trade ?? 'Travaux generaux'),
        urgency: 'moyen',
        sectorId:
            selectedCategoryId.value > 0 ? selectedCategoryId.value : null,
        tradeId: selectedTradeId.value > 0 ? selectedTradeId.value : null,
        lat: clientLatitude.value != 0.0 ? clientLatitude.value : null,
        lng: clientLongitude.value != 0.0 ? clientLongitude.value : null,
        location:
            locationAddress.value.isNotEmpty ? locationAddress.value : null,
        photos: uploadedUrls.isNotEmpty ? uploadedUrls : null,
      );

      if (mission != null) {
        // Redirection vers le suivi de mission avec la mission créée
        Get.offNamed(Routes.missionTracking, arguments: mission);
      } else {
        // Si mission est null, c'est qu'une erreur s'est produite
        // Le message d'erreur a déjà été affiché par le controller
        Get.back(); // Retour à l'écran précédent
      }
    } catch (e) {
      // Erreur inattendue non gérée par le controller
      Get.snackbar(
        'Erreur',
        'Une erreur inattendue s\'est produite',
        snackPosition: SnackPosition.TOP,
      );
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> _loadNearbyArtisans() async {
    isLoading.value = true;

    try {
      await _ensureLocation();

      if (clientLatitude.value == 0.0 || clientLongitude.value == 0.0) {
        artisans.clear();
        return;
      }

      final results = await _artisanRepository.getNearby(
        lat: clientLatitude.value,
        lng: clientLongitude.value,
        radiusMeters: searchDistant.value ? 150000 : 5000,
        sectorId: selectedCategoryId.value > 0
            ? selectedCategoryId.value.toString()
            : null,
        tradeId:
            selectedTradeId.value > 0 ? selectedTradeId.value.toString() : null,
        interventionNuit: nightIntervention.value,
      );

      artisans.assignAll(results);
    } catch (e) {
      artisans.clear();
      Get.snackbar(
        'Erreur',
        'Impossible de charger les artisans: ${ErrorHandler.getErrorMessage(e)}',
        snackPosition: SnackPosition.BOTTOM,
      );
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> _ensureLocation() async {
    if (clientLatitude.value != 0.0 && clientLongitude.value != 0.0) {
      return;
    }

    try {
      final permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        await Geolocator.requestPermission();
      }

      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 5),
        ),
      );

      clientLatitude.value = position.latitude;
      clientLongitude.value = position.longitude;
    } catch (_) {
      // Fallback sur le Plateau, Abidjan si le GPS est inaccessible
      clientLatitude.value = 5.3543;
      clientLongitude.value = -4.0083;
    }
  }

  int _parseInt(dynamic value) {
    if (value is int) return value;
    if (value is double) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  double _parseDouble(dynamic value) {
    if (value is double) return value;
    if (value is int) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? 0.0;
  }

  bool _parseBool(dynamic value) {
    if (value is bool) return value;
    if (value is int) return value == 1;
    if (value is String) {
      final normalized = value.trim().toLowerCase();
      return normalized == '1' || normalized == 'true' || normalized == 'oui';
    }
    return false;
  }
}
