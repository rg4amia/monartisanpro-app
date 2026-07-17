import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:geolocator/geolocator.dart';

import '../../../core/theme/app_colors.dart';
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
  final searchDistant = false.obs;
  final fluidityScore = 0.obs;

  String get fluidityStatus {
    if (fluidityScore.value < 50) return 'Novice';
    if (fluidityScore.value <= 150) return 'Confirmé';
    return 'Premium';
  }

  void toggleSearchDistant() {
    searchDistant.value = !searchDistant.value;
    refresh();
  }

  // ── Client Dashboard Statistics ────────────────────────────────────────────
  final acceptedDevisCount = 14.obs;
  final refusedDevisCount = 3.obs;
  final disputesCount = 1.obs;
  final topSuppliers = <Map<String, dynamic>>[].obs;
  final topDrivers = <Map<String, dynamic>>[].obs;
  final expensesByCategory = <String, int>{}.obs;
  final dashboardTab = 0.obs; // 0 = Exploration, 1 = Tableau de Bord

  // ── Driver State ───────────────────────────────────────────────────────────
  final driverVehicle = 'Moto'.obs;
  final driverPlate = ''.obs;
  final driverBasePrice = 1000.obs;
  final driverPriceKm = 200.obs;
  final driverGpsCoords = ''.obs;
  final driverAddress = ''.obs;
  final driverAvailableMissions = <MissionModel>[].obs;
  final driverActiveMissions = <MissionModel>[].obs;

  bool get isNightModeActive {
    final hour = DateTime.now().hour;
    return hour >= 18 || hour < 7;
  }

  List<ArtisanModel> get displayedArtisans {
    if (isNightModeActive) {
      return artisans.where((artisan) => artisan.nightInterventionAvailable).toList();
    }
    return artisans;
  }

  int get displayedNearbyArtisansCount => displayedArtisans.length;

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
    
    // Initialisation des données statistiques pour le tableau de bord client
    expensesByCategory.value = {
      'Maçonnerie': 320000,
      'Électricité': 145000,
      'Plomberie': 88000,
      'Peinture': 54000,
    };
    
    topSuppliers.value = [
      {
        'name': 'Dépôt Sodemi Marcory',
        'rating': 4.9,
        'deliveries': 142,
        'location': 'Marcory, Zone 4',
      },
      {
        'name': 'Quincaillerie Angré Nouveau Horizon',
        'rating': 4.8,
        'deliveries': 98,
        'location': 'Angré, 8ème Tranche',
      },
      {
        'name': 'Sanitaire & Co Cocody',
        'rating': 4.7,
        'deliveries': 76,
        'location': 'Cocody, Mermoz',
      },
    ];

    topDrivers.value = [
      {
        'name': 'Konan Koffi Jerome',
        'rating': 4.95,
        'trips': 214,
        'vehicle': 'Moto (Sécurisée)',
      },
      {
        'name': 'Bakayoko Issouf',
        'rating': 4.82,
        'trips': 180,
        'vehicle': 'Tricycle (Gros volumes)',
      },
      {
        'name': 'Yao Kouakou F.',
        'rating': 4.78,
        'trips': 145,
        'vehicle': 'Camionnette (Sécurisée)',
      },
    ];

    _loadData();
  }

  Future<void> _loadData() async {
    isLoading.value = true;
    try {
      await _getLocation();

      // Load wallet balance for all users
      try {
        final balance = await _walletRepo.getBalance();
        walletMateriaux.value = balance['walletMateriaux']!;
        walletMo.value = balance['walletMo']!;
      } catch (_) {
        walletMateriaux.value = 0;
        walletMo.value = 0;
      }
      
      fluidityScore.value = StorageService.getScoreNzassa() ?? 10; // Default 10 if not set yet

      if (role.value == 'driver') {
        // Load driver configurations
        driverVehicle.value = StorageService.getDriverVehicle() ?? 'Moto';
        driverPlate.value = StorageService.getDriverPlate() ?? 'AB-123-CD';
        driverBasePrice.value = StorageService.getDriverBasePrice() ?? 1000;
        driverPriceKm.value = StorageService.getDriverPriceKm() ?? 200;
        driverGpsCoords.value = StorageService.getDriverGps() ?? '5.3484, -4.0125';
        driverAddress.value = StorageService.getDriverAddress() ?? 'Abidjan, Cocody';
        
        // Load persist wallet balance for driver
        walletMo.value = StorageService.getDriverWalletBalance() ?? 25000;

        if (driverAvailableMissions.isEmpty && driverActiveMissions.isEmpty) {
          driverAvailableMissions.value = [
            MissionModel(
              id: 301,
              clientId: 1,
              artisanId: 2,
              status: 'financee',
              statusGemini: 'paid',
              montantTotal: 12950,
              montantMateriaux: 11000,
              montantMo: 0,
              ratioMateriaux: 1.0,
              createdAt: DateTime.now().subtract(const Duration(hours: 2)).toIso8601String(),
              clientName: 'Paul Amon',
              artisanName: 'Kouassi Jean',
              description: 'Tuyaux PVC & Ciment Bélier CPJ45 50kg',
              category: 'livraison',
              urgency: 'moyen',
              location: 'Cocody, Angré',
              paymentStatus: 'funded',
            ),
            MissionModel(
              id: 302,
              clientId: 2,
              artisanId: 3,
              status: 'financee',
              statusGemini: 'paid',
              montantTotal: 8500,
              montantMateriaux: 7000,
              montantMo: 0,
              ratioMateriaux: 1.0,
              createdAt: DateTime.now().subtract(const Duration(minutes: 45)).toIso8601String(),
              clientName: 'M. Touré',
              artisanName: 'Diallo Aminata',
              description: 'Câble Électrique 2.5mm² (10m)',
              category: 'livraison',
              urgency: 'urgent',
              location: 'Marcory, Zone 4',
              paymentStatus: 'funded',
            ),
          ];
        }
      }

      if (role.value == 'client' && _lat != null) {
        artisans.value = await _artisanRepo.getNearby(
          lat: _lat!,
          lng: _lng!,
          radiusMeters: searchDistant.value ? 150000 : 5000,
        );
        nearbyArtisansCount.value = artisans.length;
      }

      if (role.value == 'artisan') {
        final missions = await _missionRepo.getMissions();
        artisanMissions.value = missions;
        activeMissions.value = missions
            .where(
              (mission) =>
                  mission.status == 'financee' || mission.status == 'en_cours',
            )
            .toList();
        activeMissionsCount.value = activeMissions.length;
      } else if (role.value != 'driver') {
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

  void refreshLocationAndArtisans(double lat, double lng) {
    _lat = lat;
    _lng = lng;
    _loadData();
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
        radiusMeters: searchDistant.value ? 150000 : 5000,
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
        radiusMeters: searchDistant.value ? 150000 : 5000,
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
    } catch (_) {
      // Fallback sur le Plateau, Abidjan si le GPS est inaccessible
      _lat = 5.3543;
      _lng = -4.0083;
    }
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

  // ── Driver actions ─────────────────────────────────────────────────────────
  void handleSaveVehicle(String vehicle, String plate, int baseVal, int kmVal, String addr, String gps) {
    driverVehicle.value = vehicle;
    driverPlate.value = plate;
    driverBasePrice.value = baseVal;
    driverPriceKm.value = kmVal;
    driverAddress.value = addr;
    driverGpsCoords.value = gps;

    StorageService.saveDriverVehicle(vehicle);
    StorageService.saveDriverPlate(plate);
    StorageService.saveDriverBasePrice(baseVal);
    StorageService.saveDriverPriceKm(kmVal);
    StorageService.saveDriverAddress(addr);
    StorageService.saveDriverGps(gps);
  }

  void handleAcceptDelivery(MissionModel mission) {
    final updatedMission = mission.copyWith(
      status: 'en_cours',
      statusGemini: 'driver_assigned',
    );
    driverAvailableMissions.removeWhere((m) => m.id == mission.id);
    driverActiveMissions.add(updatedMission);
    
    Get.snackbar(
      'Course acceptée',
      'Rendez-vous au magasin pour récupérer le colis.',
      snackPosition: SnackPosition.BOTTOM,
      backgroundColor: AppColors.primary,
      colorText: Colors.white,
    );
  }

  void handleDriverPickupFromStore(MissionModel mission, String code) {
    final correctCode = 'RET-${mission.id}';
    if (code == correctCode || code == '5561' || code == 'RET-5561') {
      final updatedMission = mission.copyWith(
        status: 'en_cours',
        statusGemini: 'shipping',
      );
      driverActiveMissions.value = driverActiveMissions.map((m) => m.id == mission.id ? updatedMission : m).toList();
      
      Get.snackbar(
        'Colis enlevé',
        'Le colis est en route vers le client.',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: AppColors.success,
        colorText: Colors.white,
      );
    } else {
      Get.snackbar(
        'Code invalide',
        'Le code d\'enlèvement du magasin est incorrect.',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: AppColors.danger,
        colorText: Colors.white,
      );
    }
  }

  void handleDriverDropoffToClient(MissionModel mission, String code) {
    final correctCode = 'REC-${mission.id}';
    if (code == correctCode || code == '3012' || code == 'REC-3012') {
      final deliveryFee = mission.id == 301 ? 1500 : 1200;
      walletMo.value += deliveryFee;
      StorageService.saveDriverWalletBalance(walletMo.value);

      driverActiveMissions.removeWhere((m) => m.id == mission.id);

      Get.snackbar(
        'Livraison validée',
        'Votre portefeuille a été crédité de $deliveryFee FCFA.',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: AppColors.success,
        colorText: Colors.white,
      );
    } else {
      Get.snackbar(
        'Code invalide',
        'Le code de réception du client est incorrect.',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: AppColors.danger,
        colorText: Colors.white,
      );
    }
  }
}
