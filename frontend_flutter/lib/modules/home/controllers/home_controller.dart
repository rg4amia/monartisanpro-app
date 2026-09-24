import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:get/get.dart';

import '../../../core/storage/storage_service.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/artisan_model.dart';
import '../../../data/models/communication_model.dart';
import '../../../data/models/mission_model.dart';
import '../../../data/repositories/artisan_repository.dart';
import '../../../data/repositories/auth_repository.dart';
import '../../../data/repositories/communication_repository.dart';
import '../../../data/repositories/mission_repository.dart';
import '../../../data/repositories/order_repository.dart';
import '../../../data/repositories/user_repository.dart';
import '../../../data/repositories/wallet_repository.dart';

class HomeController extends GetxController {
  final AuthRepository _authRepo = AuthRepository();
  final ArtisanRepository _artisanRepo = ArtisanRepository();
  final MissionRepository _missionRepo = MissionRepository();
  final WalletRepository _walletRepo = WalletRepository();
  final UserRepository _userRepo = UserRepository();
  final CommunicationRepository _communicationRepo = CommunicationRepository();
  final OrderRepository _orderRepo = OrderRepository();

  final artisans = <ArtisanModel>[].obs;
  final artisanMissions = <MissionModel>[].obs;
  final activeMissions = <MissionModel>[].obs;
  final announcements = <CommunicationModel>[].obs;
  final tips = <CommunicationModel>[].obs;

  /// Messages vocaux et vidéos diffusés par l'administration au rôle courant.
  /// Le filtrage par cible est fait côté serveur.
  final voiceBroadcasts = <CommunicationModel>[].obs;
  final videoBroadcasts = <CommunicationModel>[].obs;
  final isLoading = false.obs;
  final hasError = false.obs;
  final isMapLoading = false.obs;

  /// Message affiché par-dessus la carte quand la recherche d'artisans échoue.
  /// Sans lui, un échec réseau se traduisait par une carte vide et silencieuse,
  /// indiscernable d'un chargement qui n'aboutit pas.
  final mapErrorMsg = ''.obs;
  final role = Rx<String?>(null);
  final userName = ''.obs;
  final paymentPhone = ''.obs;
  final preferredPaymentProvider = 'wave'.obs;
  final isSavingPaymentPhone = false.obs;
  final activeMissionsCount = 0.obs;
  final nearbyArtisansCount = 0.obs;
  final walletMateriaux = 0.obs;
  final walletMo = 0.obs;
  final selectedCategory = Rx<String?>(null);
  final searchDistant = false.obs;
  final fluidityScore = 0.obs;
  final scoreFiabilite = 0.94.obs;
  final scoreIntegrite = 0.98.obs;
  final scoreQualite = 0.88.obs;
  final scoreReactivite = 0.92.obs;

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
      return artisans
          .where((artisan) => artisan.nightInterventionAvailable)
          .toList();
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

    // Aucune donnée de démonstration ici : ces trois collections sont
    // renseignées par `GET /dashboard`. Les préremplir affichait au client des
    // dépenses et des classements inventés, indiscernables des siens, et
    // masquait toute panne de chargement — le tableau de bord paraissait
    // fonctionner alors qu'il ne montrait rien de réel.
    _loadData();
  }

  /// Nombre max de tentatives de chargement avant d'afficher l'erreur.
  static const int _maxRetries = 3;
  Future<void> _loadData({bool forceRefresh = false}) async {
    isLoading.value = true;
    hasError.value = false;

    for (var attempt = 1; attempt <= _maxRetries; attempt++) {
      try {
        await _loadDataCore(forceRefresh: forceRefresh);
        hasError.value = false;
        isLoading.value = false;
        return;
      } catch (e) {
        debugPrint(
          '[HomeController] Tentative $attempt/$_maxRetries échouée : $e',
        );
        if (attempt < _maxRetries) {
          await Future.delayed(Duration(seconds: 2 * attempt));
        } else {
          hasError.value = true;
          isLoading.value = false;
        }
      }
    }
  }

  /// Logique principale de chargement — extraite pour le retry.
  ///
  /// [forceRefresh] court-circuite le cache Hive des communications : sans
  /// lui, le geste de tirer-pour-rafraîchir retombait sur `cacheFirst` et
  /// pouvait laisser une communication tout juste publiée invisible jusqu'à
  /// dix minutes, le temps que le TTL local expire de lui-même.
  Future<void> _loadDataCore({bool forceRefresh = false}) async {
    await _getLocation();

    // Load user profile payment settings
    try {
      final me = await _authRepo.me();
      paymentPhone.value = me.paymentPhone ?? '';
      if (me.preferredPaymentProvider != null &&
          me.preferredPaymentProvider!.isNotEmpty) {
        preferredPaymentProvider.value = me.preferredPaymentProvider!;
      }
    } catch (_) {}

    // Load wallet balance for all users
    try {
      final balance = await _walletRepo.getBalance();
      walletMateriaux.value = balance['walletMateriaux']!;
      walletMo.value = balance['walletMo']!;
    } catch (_) {
      walletMateriaux.value = 0;
      walletMo.value = 0;
    }

    fluidityScore.value =
        StorageService.getScoreProsArtisan() ?? 0; // Default 0 if not set yet

    try {
      final rawResponse = await _userRepo.getDashboardStats();
      final dashboardData = _asMap(rawResponse['data']) ?? const {};

      // `??` conserve l'enchaînement des alias par rôle ; `_asInt` absorbe un
      // compteur renvoyé en chaîne de caractères.
      acceptedDevisCount.value = _asInt(
        dashboardData['accepted_devis_count'] ??
            dashboardData['completed_deliveries'],
      );
      refusedDevisCount.value = _asInt(
        dashboardData['refused_devis_count'] ??
            dashboardData['pending_deliveries'],
      );
      disputesCount.value = _asInt(dashboardData['disputes_count']);

      // Chaque bloc est indépendant et transtypé défensivement : un champ à la
      // forme inattendue ne doit pas interrompre la lecture des suivants.
      // `expenses_by_category` arrivait en `[]` (et non `{}`) pour un client
      // sans mission, ce qui faisait perdre au passage le classement des
      // fournisseurs, celui des livreurs et le rafraîchissement du score.
      // La clé présente fait autorité, même vide : le client qui n'a encore
      // aucune dépense doit voir un tableau de bord vide, pas un reliquat.
      if (dashboardData.containsKey('expenses_by_category')) {
        final expenses = _asMap(dashboardData['expenses_by_category']) ?? {};
        expensesByCategory.value =
            expenses.map((key, value) => MapEntry(key, _asInt(value)));
      }

      if (dashboardData.containsKey('top_suppliers')) {
        topSuppliers.value = _asMapList(dashboardData['top_suppliers']);
      }

      if (dashboardData.containsKey('top_drivers')) {
        topDrivers.value = _asMapList(dashboardData['top_drivers']);
      }

      // If it's supplier stats
      final s = _asMap(dashboardData['stats']);
      if (s != null) {
        acceptedDevisCount.value = _asInt(s['total_orders']);
        refusedDevisCount.value = _asInt(s['pending_orders']);
        disputesCount.value = _asInt(s['catalog_count']);
      }

      // If it returns score_prosartisan from backend, update it
      if (dashboardData.containsKey('score_prosartisan') &&
          dashboardData['score_prosartisan'] != null) {
        fluidityScore.value = _asInt(dashboardData['score_prosartisan']);
      }
    } catch (e) {
      debugPrint('Error fetching dashboard stats: $e');
    }

    if (role.value == 'driver' || role.value == 'livreur') {
      // Load driver configurations
      driverVehicle.value = StorageService.getDriverVehicle() ?? 'Moto';
      driverPlate.value = StorageService.getDriverPlate() ?? 'AB-123-CD';
      driverBasePrice.value = StorageService.getDriverBasePrice() ?? 1000;
      driverPriceKm.value = StorageService.getDriverPriceKm() ?? 200;
      driverGpsCoords.value =
          StorageService.getDriverGps() ?? '5.3484, -4.0125';
      driverAddress.value =
          StorageService.getDriverAddress() ?? 'Abidjan, Cocody';

      // Load persist wallet balance for driver
      walletMo.value = StorageService.getDriverWalletBalance() ?? 25000;

      await _loadDriverMissions();
    }

    if (role.value == 'client' && _lat != null) {
      artisans.value = await _artisanRepo.getNearby(
        lat: _lat!,
        lng: _lng!,
        radiusMeters: searchDistant.value ? 50000 : null,
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

      // Charger le score de l'artisan en temps réel
      try {
        final userId = StorageService.getUserId();
        if (userId != null) {
          final res = await _artisanRepo.getScore(userId);
          final data = (res['data'] as Map<String, dynamic>?) ?? res;
          final breakdown = data['breakdown'] is Map
              ? Map<String, dynamic>.from(data['breakdown'] as Map)
              : const <String, dynamic>{};

          scoreFiabilite.value = _normalizeCriterion(
            breakdown['fiabilite'] ?? breakdown['fiabilité'] ?? 0,
          );
          scoreIntegrite.value = _normalizeCriterion(
            breakdown['integrite'] ?? breakdown['intégrité'] ?? 0,
          );
          scoreQualite.value = _normalizeCriterion(
            breakdown['qualite'] ?? breakdown['qualité'] ?? 0,
          );
          scoreReactivite.value = _normalizeCriterion(
            breakdown['reactivite'] ?? breakdown['réactivité'] ?? 0,
          );

          final dynScore =
              data['score_prosartisan'] ?? data['scoreProsArtisan'];
          if (dynScore != null) {
            fluidityScore.value = _asInt(dynScore);
          }
        }
      } catch (e) {
        debugPrint(
          '[HomeController] Error fetching real artisan score detail: $e',
        );
      }
    } else if (role.value != 'driver') {
      final missions = await _missionRepo.getMissions(status: 'en_cours');
      activeMissions.value = missions;
      activeMissionsCount.value = missions.length;
    }

    // Load active communications (announcements, tips, voice & video)
    try {
      final commsMap = await _communicationRepo.getActiveCommunications(
        forceRefresh: forceRefresh,
      );
      announcements.value = commsMap['annonces'] ?? [];
      tips.value = commsMap['le_saviez_vous'] ?? [];
      voiceBroadcasts.value = commsMap['audio'] ?? [];
      videoBroadcasts.value = commsMap['video'] ?? [];
    } catch (e) {
      debugPrint('[HomeController] Error fetching active communications: $e');
      announcements.clear();
      tips.clear();
      voiceBroadcasts.clear();
      videoBroadcasts.clear();
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
    mapErrorMsg.value = '';
    try {
      await _getLocation();
      if (_lat == null) return;
      artisans.value = await _artisanRepo.getNearby(
        lat: _lat!,
        lng: _lng!,
        sectorId: category,
        radiusMeters: searchDistant.value ? 50000 : null,
      );
      nearbyArtisansCount.value = artisans.length;
    } catch (_) {
      // On garde les artisans déjà affichés, mais on le dit : une carte vide
      // sans explication est lue comme un chargement qui n'aboutit pas.
      mapErrorMsg.value =
          'Impossible de charger les artisans. Vérifiez votre connexion.';
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
    mapErrorMsg.value = '';
    try {
      await _getLocation();
      if (_lat == null) return;

      final results = await _artisanRepo.getNearby(
        lat: _lat!,
        lng: _lng!,
        sectorId: selectedCategory.value,
        radiusMeters: searchDistant.value ? 50000 : null,
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
      mapErrorMsg.value =
          'Impossible de charger les artisans. Vérifiez votre connexion.';
    } finally {
      isMapLoading.value = false;
    }
  }

  /// Tirer-pour-rafraîchir : geste explicite de l'utilisateur, il doit
  /// interroger le serveur plutôt que resservir un cache jusqu'à dix minutes
  /// périmé (annonces, messages vocaux, vidéos).
  @override
  Future<void> refresh() => _loadData(forceRefresh: true);

  Future<void> _getLocation() async {
    try {
      final perm = await Geolocator.checkPermission();
      if (perm == LocationPermission.denied) {
        // La boîte de dialogue système peut être fermée sans réponse (retour
        // arrière) : le Future reste alors en attente. Non borné, il gèlerait
        // `isMapLoading` à vrai et la carte tournerait sans fin.
        await Geolocator.requestPermission()
            .timeout(const Duration(seconds: 30));
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
  void handleSaveVehicle(
    String vehicle,
    String plate,
    int baseVal,
    int kmVal,
    String addr,
    String gps,
  ) {
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

    final userId = StorageService.getUserId();
    if (userId != null && gps.isNotEmpty) {
      try {
        final parts = gps.split(',');
        if (parts.length == 2) {
          final lat = double.tryParse(parts[0].trim());
          final lng = double.tryParse(parts[1].trim());
          if (lat != null && lng != null) {
            _userRepo.updateLocation(userId: userId, lat: lat, lng: lng);
          }
        }
      } catch (e) {
        debugPrint('Erreur lors de la synchronisation GPS: $e');
      }
    }
  }

  Future<void> _loadDriverMissions() async {
    try {
      final availableData = await _orderRepo.getAvailableDeliveries();
      final myOrdersData = await _orderRepo.getMyOrders();

      if (availableData.isNotEmpty) {
        driverAvailableMissions.value = availableData.map((order) {
          final itemsList = (order['items'] as List?) ?? [];
          final descItems = itemsList.map((it) {
            final itMap = _asMap(it);
            final pName = _asMap(itMap?['product'])?['name'] ?? 'Article';
            final qty = itMap?['quantity'] ?? 1;
            return '$pName x$qty';
          }).join(', ');

          final supplier = _asMap(order['supplier']);
          final supplierName =
              _asMap(supplier?['fournisseur_agree'])?['nom_boutique'] ??
                  supplier?['name'] ??
                  'Quincaillerie Partenaire';
          final clientName = _asMap(order['client'])?['name'] ?? 'Client';
          final (supLat, supLng) = _coordsFrom(
            _asMap(_asMap(supplier?['fournisseur_agree'])?['coordinates']) ??
                _asMap(supplier?['coordinates']),
          );
          final (cliLat, cliLng) =
              _coordsFrom(_asMap(_asMap(order['client'])?['coordinates']));
          final deliveryCost =
              (order['delivery_cost'] as num?)?.toInt() ?? 1500;
          final totalAmount = (order['total_amount'] as num?)?.toInt() ?? 0;

          return MissionModel(
            id: order['id'],
            clientId: order['client_id'] ?? 0,
            artisanId: order['supplier_id'] ?? 0,
            status: 'financee',
            statusGemini: order['status'] ?? 'searching_driver',
            montantTotal: totalAmount,
            montantMateriaux: (order['subtotal'] as num?)?.toInt() ?? 0,
            montantMo: deliveryCost,
            ratioMateriaux: 1.0,
            createdAt: order['created_at'] ?? DateTime.now().toIso8601String(),
            clientName: clientName,
            artisanName: supplierName,
            description: descItems.isNotEmpty
                ? descItems
                : 'Commande d\'articles #${order['id']}',
            category: 'livraison',
            urgency: 'moyen',
            location: 'Abidjan',
            paymentStatus: 'funded',
            supplierLatitude: supLat,
            supplierLongitude: supLng,
            clientLatitude: cliLat,
            clientLongitude: cliLng,
          );
        }).toList();
      } else {
        driverAvailableMissions.clear();
      }

      if (myOrdersData.isNotEmpty) {
        final activeList = myOrdersData.where((order) {
          final s = order['status'];
          return s == 'driver_assigned' ||
              s == 'driver_picked_up' ||
              s == 'shipping';
        }).map((order) {
          final itemsList = (order['items'] as List?) ?? [];
          final descItems = itemsList.map((it) {
            final itMap = _asMap(it);
            final pName = _asMap(itMap?['product'])?['name'] ?? 'Article';
            final qty = itMap?['quantity'] ?? 1;
            return '$pName x$qty';
          }).join(', ');

          final supplier = _asMap(order['supplier']);
          final supplierName =
              _asMap(supplier?['fournisseur_agree'])?['nom_boutique'] ??
                  supplier?['name'] ??
                  'Quincaillerie Partenaire';
          final clientName = _asMap(order['client'])?['name'] ?? 'Client';
          final (supLat, supLng) = _coordsFrom(
            _asMap(_asMap(supplier?['fournisseur_agree'])?['coordinates']) ??
                _asMap(supplier?['coordinates']),
          );
          final (cliLat, cliLng) =
              _coordsFrom(_asMap(_asMap(order['client'])?['coordinates']));
          final deliveryCost =
              (order['delivery_cost'] as num?)?.toInt() ?? 1500;
          final totalAmount = (order['total_amount'] as num?)?.toInt() ?? 0;

          return MissionModel(
            id: order['id'],
            clientId: order['client_id'] ?? 0,
            artisanId: order['supplier_id'] ?? 0,
            status: 'en_cours',
            statusGemini: order['status'] ?? 'driver_assigned',
            montantTotal: totalAmount,
            montantMateriaux: (order['subtotal'] as num?)?.toInt() ?? 0,
            montantMo: deliveryCost,
            ratioMateriaux: 1.0,
            createdAt: order['created_at'] ?? DateTime.now().toIso8601String(),
            clientName: clientName,
            artisanName: supplierName,
            description: descItems.isNotEmpty
                ? descItems
                : 'Commande d\'articles #${order['id']}',
            category: 'livraison',
            urgency: 'moyen',
            location: 'Abidjan',
            paymentStatus: 'funded',
            supplierLatitude: supLat,
            supplierLongitude: supLng,
            clientLatitude: cliLat,
            clientLongitude: cliLng,
          );
        }).toList();

        driverActiveMissions.value = activeList;
      } else {
        driverActiveMissions.clear();
      }
    } catch (e) {
      debugPrint('Erreur chargement livraisons: $e');
    }
  }

  Future<void> handleAcceptDelivery(MissionModel mission) async {
    try {
      final res = await _orderRepo.acceptDelivery(mission.id);
      if (res['success'] == true) {
        driverAvailableMissions.removeWhere((m) => m.id == mission.id);
        final updatedMission = mission.copyWith(
          status: 'en_cours',
          statusGemini: 'driver_assigned',
        );
        driverActiveMissions.add(updatedMission);

        Get.snackbar(
          'Course acceptée !',
          'Rendez-vous au magasin pour récupérer le colis.',
          snackPosition: SnackPosition.BOTTOM,
          backgroundColor: AppColors.primary,
          colorText: Colors.white,
        );
        await _loadDriverMissions();
      } else {
        Get.snackbar(
          'Information',
          res['message'] ?? 'Course acceptée.',
          snackPosition: SnackPosition.BOTTOM,
          backgroundColor: AppColors.primary,
          colorText: Colors.white,
        );
        await _loadDriverMissions();
      }
    } catch (e) {
      // Fallback local si simulation
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
  }

  /// Retourne `true` uniquement si le backend a validé le code de retrait.
  Future<bool> handleDriverPickupFromStore(
    MissionModel mission,
    String code,
  ) async {
    try {
      final res = await _orderRepo.verifyPickup(mission.id, code.trim());
      if (res['success'] == true) {
        final updatedMission = mission.copyWith(
          status: 'en_cours',
          statusGemini: 'shipping',
        );
        driverActiveMissions.value = driverActiveMissions
            .map((m) => m.id == mission.id ? updatedMission : m)
            .toList();

        // Hors réseau, la validation est conservée et rejouée plus tard : le
        // code a bien été obtenu au comptoir, la preuve n'est pas perdue.
        final queued = res['queued'] == true;

        Get.snackbar(
          queued ? 'Enregistré hors connexion' : 'Colis enlevé avec succès',
          queued
              ? (res['message'] as String? ??
                  'Transmission dès le retour du réseau.')
              : 'Le colis est en route vers le client.',
          snackPosition: SnackPosition.BOTTOM,
          backgroundColor: queued ? AppColors.warning : AppColors.success,
          colorText: Colors.white,
          duration: Duration(seconds: queued ? 6 : 3),
        );
        if (!queued) await _loadDriverMissions();
        return true;
      }

      Get.snackbar(
        'Code invalide',
        res['message'] ?? 'Le code d\'enlèvement du magasin est incorrect.',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: AppColors.danger,
        colorText: Colors.white,
      );
      return false;
    } catch (e) {
      // Aucune validation locale : le code de retrait doit être vérifié par le
      // backend (règle d'or #8). En cas d'échec réseau, on n'avance pas l'état.
      Get.snackbar(
        'Validation impossible',
        'Le code de retrait n\'a pas pu être vérifié. Vérifiez votre connexion et réessayez.',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: AppColors.danger,
        colorText: Colors.white,
      );
      return false;
    }
  }

  /// Retourne `true` uniquement si le backend a validé le code de réception.
  Future<bool> handleDriverDropoffToClient(
    MissionModel mission,
    String code,
  ) async {
    try {
      final res = await _orderRepo.verifyDelivery(mission.id, code.trim());
      if (res['success'] == true) {
        if (res['queued'] == true) {
          // Validation conservée pour rejeu : les fonds ne sont pas encore
          // libérés, on ne crédite donc surtout pas le portefeuille. La course
          // reste affichée tant que le backend ne l'a pas confirmée.
          Get.snackbar(
            'Enregistré hors connexion',
            res['message'] as String? ??
                'La livraison sera confirmée dès le retour du réseau.',
            snackPosition: SnackPosition.BOTTOM,
            backgroundColor: AppColors.warning,
            colorText: Colors.white,
            duration: const Duration(seconds: 6),
          );

          return true;
        }

        final deliveryFee = mission.montantMo > 0 ? mission.montantMo : 1500;
        walletMo.value += deliveryFee;
        StorageService.saveDriverWalletBalance(walletMo.value);

        driverActiveMissions.removeWhere((m) => m.id == mission.id);

        Get.snackbar(
          'Livraison validée & Terminée',
          'Votre portefeuille a été crédité de ${Formatters.fcfa(deliveryFee)}.',
          snackPosition: SnackPosition.BOTTOM,
          backgroundColor: AppColors.success,
          colorText: Colors.white,
        );
        await _loadDriverMissions();
        return true;
      }

      Get.snackbar(
        'Code invalide',
        res['message'] ?? 'Le code de réception du client est incorrect.',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: AppColors.danger,
        colorText: Colors.white,
      );
      return false;
    } catch (e) {
      // Aucune validation locale : le code de réception (OTP) doit être vérifié
      // par le backend (règle d'or #8). Pas de crédit portefeuille hors-ligne.
      Get.snackbar(
        'Validation impossible',
        'Le code de réception n\'a pas pu être vérifié. Vérifiez votre connexion et réessayez.',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: AppColors.danger,
        colorText: Colors.white,
      );
      return false;
    }
  }

  /// Signale un temps d'attente (retrait ou livraison) sur une course en
  /// cours. Le backend est seul juge de l'autorisation (livreur assigné à la
  /// commande, ou admin) : en cas de refus (403) ou de saisie invalide (422),
  /// son message exact est restitué au livreur plutôt qu'un message générique.
  Future<bool> handleDriverWaitingSurge(
    MissionModel mission,
    int waitingMinutes,
  ) async {
    try {
      final res = await _orderRepo.applyWaitingSurge(
        mission.id,
        waitingMinutes,
      );
      if (res['success'] == true) {
        Get.snackbar(
          'Frais d\'attente appliqués',
          res['message'] as String? ??
              'Frais d\'attente majorés appliqués.',
          snackPosition: SnackPosition.BOTTOM,
          backgroundColor: AppColors.success,
          colorText: Colors.white,
        );
        await _loadDriverMissions();
        return true;
      }

      Get.snackbar(
        'Échec du signalement',
        res['message'] as String? ??
            'Le temps d\'attente n\'a pas pu être signalé.',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: AppColors.danger,
        colorText: Colors.white,
      );
      return false;
    } on DioException catch (e) {
      final data = e.response?.data;
      final message = data is Map ? data['message']?.toString() : null;
      Get.snackbar(
        'Échec du signalement',
        message ??
            'Le temps d\'attente n\'a pas pu être signalé. Vérifiez votre connexion et réessayez.',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: AppColors.danger,
        colorText: Colors.white,
      );
      return false;
    } catch (_) {
      Get.snackbar(
        'Échec du signalement',
        'Le temps d\'attente n\'a pas pu être signalé. Vérifiez votre connexion et réessayez.',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: AppColors.danger,
        colorText: Colors.white,
      );
      return false;
    }
  }

  int _asInt(dynamic value) {
    if (value == null) return 0;
    if (value is int) return value;
    if (value is double) return value.toInt();
    return int.tryParse(value.toString()) ?? 0;
  }

  /// Extrait `lat`/`lng` d'un objet `coordinates` (`{lat, lng}`) sérialisé par
  /// l'API (User + FournisseurAgree l'exposent via `$appends`).
  static (double?, double?) _coordsFrom(Map<String, dynamic>? coordinates) {
    if (coordinates == null) return (null, null);
    final lat = double.tryParse(
      (coordinates['lat'] ?? coordinates['latitude'])?.toString() ?? '',
    );
    final lng = double.tryParse(
      (coordinates['lng'] ?? coordinates['longitude'])?.toString() ?? '',
    );
    return (lat, lng);
  }

  /// Cast défensif d'une valeur JSON dynamique en `Map` typée (ou `null`).
  static Map<String, dynamic>? _asMap(dynamic value) =>
      value is Map ? Map<String, dynamic>.from(value) : null;

  /// Cast défensif d'une valeur JSON dynamique en liste d'objets typés.
  ///
  /// Renvoie une liste vide plutôt que de lever : les entrées qui ne sont pas
  /// des objets sont ignorées, une seule ligne malformée ne devant pas priver
  /// l'écran de tout le classement.
  static List<Map<String, dynamic>> _asMapList(dynamic value) => value is List
      ? value
          .map(_asMap)
          .whereType<Map<String, dynamic>>()
          .toList(growable: false)
      : const [];

  double _normalizeCriterion(dynamic value) {
    double parsed;

    if (value is num) {
      parsed = value.toDouble();
    } else {
      parsed = double.tryParse(value?.toString() ?? '') ?? 0;
    }

    if (parsed <= 5) {
      return (parsed / 5).clamp(0.0, 1.0);
    }

    return (parsed / 100).clamp(0.0, 1.0);
  }

  Future<bool> updatePaymentPhone({
    required String newPaymentPhone,
    required String provider,
  }) async {
    final userId = StorageService.getUserId();
    if (userId == null) return false;
    isSavingPaymentPhone.value = true;
    try {
      await _userRepo.updateProfile(
        userId: userId,
        paymentPhone: newPaymentPhone.trim(),
        preferredPaymentProvider: provider,
      );
      paymentPhone.value = newPaymentPhone.trim();
      preferredPaymentProvider.value = provider;
      return true;
    } catch (_) {
      return false;
    } finally {
      isSavingPaymentPhone.value = false;
    }
  }
}
