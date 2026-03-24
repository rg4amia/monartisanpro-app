import 'package:get/get.dart';
import 'package:geolocator/geolocator.dart';

import '../../../core/storage/storage_service.dart';
import '../../../data/models/artisan_model.dart';
import '../../../data/models/mission_model.dart';
import '../../../data/repositories/artisan_repository.dart';
import '../../../data/repositories/mission_repository.dart';
import '../../../data/repositories/wallet_repository.dart';

class HomeController extends GetxController {
  final ArtisanRepository _artisanRepo = ArtisanRepository();
  final MissionRepository _missionRepo = MissionRepository();
  final WalletRepository _walletRepo = WalletRepository();

  final artisans = <ArtisanModel>[].obs;
  final artisanMissions = <MissionModel>[].obs;
  final activeMissions = <MissionModel>[].obs;
  final isLoading = false.obs;
  final isMapLoading = false.obs;
  final role = Rx<String?>(null);
  final userName = ''.obs;
  final activeMissionsCount = 0.obs;
  final nearbyArtisansCount = 0.obs;
  final walletMateriaux = 0.obs;
  final walletMo = 0.obs;
  final selectedCategory = Rx<String?>(null);

  double? _lat;
  double? _lng;

  double? get userLat => _lat;
  double? get userLng => _lng;
  int get pendingMissionCount =>
      artisanMissions.where((mission) => mission.status == 'en_attente').length;
  int get fundedMissionCount =>
      artisanMissions.where((mission) => mission.status == 'financee').length;
  int get ongoingMissionCount =>
      artisanMissions.where((mission) => mission.status == 'en_cours').length;
  int get disputedMissionCount =>
      artisanMissions.where((mission) => mission.status == 'litige').length;

  List<MissionModel> get prioritizedArtisanMissions {
    final missions = artisanMissions.toList();
    missions.sort((a, b) {
      final aRank = _statusRank(a.status);
      final bRank = _statusRank(b.status);
      if (aRank != bRank) {
        return aRank.compareTo(bRank);
      }
      return b.id.compareTo(a.id);
    });
    return missions;
  }

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

      // Load wallet balance for all users
      final balance = await _walletRepo.getBalance();
      walletMateriaux.value = balance['walletMateriaux']!;
      walletMo.value = balance['walletMo']!;

      if (role.value == 'client' && _lat != null) {
        artisans.value = await _artisanRepo.getNearby(
          lat: _lat!,
          lng: _lng!,
        );
        nearbyArtisansCount.value = artisans.length;
      }

      if (role.value == 'artisan') {
        final missions = await _missionRepo.getMissions();
        artisanMissions.value = missions;
        activeMissions.value = missions
            .where(
              (mission) =>
                  mission.status == 'financee' ||
                  mission.status == 'en_cours',
            )
            .toList();
        activeMissionsCount.value = activeMissions.length;
      } else {
        final missions = await _missionRepo.getMissions(status: 'en_cours');
        activeMissions.value = missions;
        activeMissionsCount.value = missions.length;
      }
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
      nearbyArtisansCount.value = artisans.length;
    } catch (_) {
      // garder l'état précédent
    } finally {
      isMapLoading.value = false;
    }
  }

  /// Recherche d'artisans par texte (nom ou métier)
  Future<void> searchArtisans(String query) async {
    if (query.trim().isEmpty) {
      await searchByCategory(selectedCategory.value);
      return;
    }
    
    isMapLoading.value = true;
    try {
      await _getLocation();
      if (_lat == null) return;
      
      final results = await _artisanRepo.getNearby(
        lat: _lat!,
        lng: _lng!,
        sectorId: selectedCategory.value,
      );
      
      // Filtrer localement par nom ou métier
      final filtered = results.where((artisan) {
        final name = artisan.name?.toLowerCase() ?? '';
        final trade = artisan.trade?.toLowerCase() ?? '';
        final searchLower = query.toLowerCase();
        return name.contains(searchLower) || trade.contains(searchLower);
      }).toList();
      
      artisans.value = filtered;
      nearbyArtisansCount.value = filtered.length;
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

  int _statusRank(String status) {
    switch (status) {
      case 'en_attente':
        return 0;
      case 'financee':
        return 1;
      case 'en_cours':
        return 2;
      case 'litige':
        return 3;
      case 'terminee':
        return 4;
      default:
        return 5;
    }
  }
}
