import 'package:get/get.dart';
import 'package:geolocator/geolocator.dart';

import '../../../core/storage/storage_service.dart';
import '../../../data/models/artisan_model.dart';
import '../../../data/models/mission_model.dart';
import '../../../data/repositories/artisan_repository.dart';
import '../../../data/repositories/mission_repository.dart';

class HomeController extends GetxController {
  final ArtisanRepository _artisanRepo = ArtisanRepository();
  final MissionRepository _missionRepo = MissionRepository();

  final artisans = <ArtisanModel>[].obs;
  final activeMissions = <MissionModel>[].obs;
  final isLoading = false.obs;
  final isMapLoading = false.obs;
  final role = Rx<String?>(null);
  final userName = ''.obs;
  final walletMateriaux = 0.obs;
  final walletMo = 0.obs;
  final selectedCategory = Rx<String?>(null);

  double? _lat;
  double? _lng;

  double? get userLat => _lat;
  double? get userLng => _lng;

  @override
  void onInit() {
    super.onInit();
    role.value = StorageService.getRole();
    userName.value = StorageService.getName() ?? '';
    _loadData();
  }

  Future<void> _loadData() async {
    isLoading.value = true;
    try {
      await _getLocation();
      if (role.value == 'client' && _lat != null) {
        artisans.value = await _artisanRepo.getNearby(
          lat: _lat!,
          lng: _lng!,
        );
      }
      final missions = await _missionRepo.getMissions(status: 'en_cours');
      activeMissions.value = missions;
    } catch (_) {
      // keep empty state
    } finally {
      isLoading.value = false;
    }
  }

  /// Recherche par catégorie — utilisé par carte et chips home
  Future<void> searchByCategory(String? category) async {
    selectedCategory.value = category;
    isMapLoading.value = true;
    try {
      await _getLocation();
      if (_lat == null) return;
      artisans.value = await _artisanRepo.getNearby(
        lat: _lat!,
        lng: _lng!,
        sectorId: category,
      );
    } catch (_) {
      // garder l'état précédent
    } finally {
      isMapLoading.value = false;
    }
  }

  @override
  Future<void> refresh() => _loadData();

  Future<void> _getLocation() async {
    try {
      final perm = await Geolocator.checkPermission();
      if (perm == LocationPermission.denied) {
        await Geolocator.requestPermission();
      }
      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 5),
        ),
      );
      _lat = pos.latitude;
      _lng = pos.longitude;
    } catch (_) {}
  }
}
