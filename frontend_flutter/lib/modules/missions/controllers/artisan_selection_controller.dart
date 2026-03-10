import 'dart:math';

import 'package:get/get.dart';
import 'package:geolocator/geolocator.dart';

import '../../../app/routes/app_routes.dart';
import '../../../data/models/artisan_model.dart';

class ArtisanSelectionController extends GetxController {
  // État de l'interface
  final isLoading = false.obs;
  final isMapView = true.obs;
  
  // Données de la mission
  final selectedCategory = ''.obs;
  final selectedCategoryId = 0.obs;
  final selectedTradeId = 0.obs;
  final clientLatitude = 0.0.obs;
  final clientLongitude = 0.0.obs;
  final missionDescription = ''.obs;
  
  // Liste des artisans
  final artisans = <ArtisanModel>[].obs;
  
  @override
  void onInit() {
    super.onInit();
    _loadNearbyArtisans();
  }
  
  /// Initialise avec les données de la mission
  void initializeWithMissionData(Map<String, dynamic> data) {
    selectedCategory.value = data['category'] ?? '';
    selectedCategoryId.value = data['categoryId'] ?? 0;
    selectedTradeId.value = data['tradeId'] ?? 0;
    clientLatitude.value = data['latitude'] ?? 0.0;
    clientLongitude.value = data['longitude'] ?? 0.0;
    missionDescription.value = data['description'] ?? '';
    
    // Recharger les artisans avec les nouveaux critères
    _loadNearbyArtisans();
  }
  
  /// Bascule entre vue carte et vue liste
  void setMapView(bool isMap) {
    isMapView.value = isMap;
  }
  
  /// Charge les artisans proches selon les critères
  Future<void> _loadNearbyArtisans() async {
    try {
      isLoading.value = true;
      
      // Simuler des données d'artisans pour le développement
      // En production, remplacer par un appel API réel
      await Future.delayed(const Duration(milliseconds: 800));
      
      final mockArtisans = _generateMockArtisans();
      artisans.assignAll(mockArtisans);
      
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de charger les artisans: $e',
        snackPosition: SnackPosition.BOTTOM,
      );
    } finally {
      isLoading.value = false;
    }
  }
  
  /// Actualise la liste des artisans
  Future<void> refreshArtisans() async {
    await _loadNearbyArtisans();
  }
  
  /// Sélectionne un artisan et navigue vers la création de devis
  void selectArtisan(ArtisanModel artisan) {
    Get.toNamed(
      Routes.quoteBuilder,
      arguments: {
        'artisan': artisan,
        'category': selectedCategory.value,
        'categoryId': selectedCategoryId.value,
        'tradeId': selectedTradeId.value,
        'description': missionDescription.value,
        'latitude': clientLatitude.value,
        'longitude': clientLongitude.value,
      },
    );
  }
  
  /// Génère des données d'artisans fictives pour le développement
  List<ArtisanModel> _generateMockArtisans() {
    final random = Random();
    final baseLatitude = clientLatitude.value != 0.0 ? clientLatitude.value : 5.3484;
    final baseLongitude = clientLongitude.value != 0.0 ? clientLongitude.value : -4.0169;
    
    final names = [
      'Kouassi Jean-Baptiste',
      'Adjoua Marie-Claire', 
      'Yao Emmanuel',
      'Akissi Fatou',
      'Koné Mamadou',
      'Bamba Salimata',
      'Ouattara Ibrahim',
      'Diabaté Aminata'
    ];
    
    final trades = [
      'Plombier Expert',
      'Électricien Certifié',
      'Maçon Professionnel',
      'Peintre Décorateur',
      'Carreleur Spécialisé',
      'Menuisier Ébéniste'
    ];
    
    final communes = [
      'Cocody',
      'Plateau',
      'Adjamé',
      'Yopougon',
      'Marcory',
      'Treichville',
      'Abobo',
      'Koumassi'
    ];
    
    return List.generate(12, (index) {
      // Générer une position dans un rayon de 5km
      final distance = random.nextDouble() * 5000; // 0-5km en mètres
      final bearing = random.nextDouble() * 2 * pi;
      
      final lat = baseLatitude + (distance * cos(bearing)) / 111320;
      final lng = baseLongitude + (distance * sin(bearing)) / (111320 * cos(baseLatitude * pi / 180));
      
      final distanceKm = distance / 1000;
      final isGolden = index < 3; // Les 3 premiers sont des artisans d'élite
      
      return ArtisanModel(
        id: index + 1,
        name: names[index % names.length],
        trade: trades[index % trades.length],
        phone: '+225 0${random.nextInt(9)}${random.nextInt(10)}${random.nextInt(10)}${random.nextInt(10)}${random.nextInt(10)}${random.nextInt(10)}${random.nextInt(10)}${random.nextInt(10)}',
        scoreNzassa: isGolden ? 85 + random.nextInt(15) : 60 + random.nextInt(25),
        rating: 3.5 + random.nextDouble() * 1.5,
        isGoldenMarker: isGolden,
        experienceYears: 2 + random.nextInt(18), // 2 à 20 ans d'expérience
        location: {
          'lat': lat,
          'lng': lng,
        },
        distance: '${distanceKm.toStringAsFixed(1)} km',
        commune: communes[index % communes.length],
        photo: null, // Pas de photos pour les données fictives
        isAvailable: random.nextBool() || isGolden, // Les artisans d'élite sont toujours disponibles
        completedMissions: random.nextInt(50) + (isGolden ? 50 : 0),
        joinedDate: DateTime.now().subtract(Duration(days: random.nextInt(365 * 2))),
      );
    })..sort((a, b) {
      // Trier par score N'Zassa décroissant, puis par distance croissante
      if (a.scoreNzassa != b.scoreNzassa) {
        return b.scoreNzassa.compareTo(a.scoreNzassa);
      }
      final distanceA = double.tryParse(a.distance?.replaceAll(' km', '') ?? '0') ?? 0;
      final distanceB = double.tryParse(b.distance?.replaceAll(' km', '') ?? '0') ?? 0;
      return distanceA.compareTo(distanceB);
    });
  }
  
  /// Calcule la distance entre deux points GPS (formule haversine)
  double _calculateDistance(double lat1, double lon1, double lat2, double lon2) {
    const double earthRadius = 6371000; // Rayon de la Terre en mètres
    
    final double dLat = _degreesToRadians(lat2 - lat1);
    final double dLon = _degreesToRadians(lon2 - lon1);
    
    final double a = sin(dLat / 2) * sin(dLat / 2) +
        cos(_degreesToRadians(lat1)) * cos(_degreesToRadians(lat2)) *
        sin(dLon / 2) * sin(dLon / 2);
    
    final double c = 2 * atan2(sqrt(a), sqrt(1 - a));
    
    return earthRadius * c;
  }
  
  double _degreesToRadians(double degrees) {
    return degrees * pi / 180;
  }
}