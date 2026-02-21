import 'package:get/get.dart';
import 'package:geolocator/geolocator.dart';
import '../../core/network/search_service.dart';
import '../../core/network/trade_service.dart';
import '../models/artisan_search_model.dart';
import '../models/trade_model.dart';

class ArtisanSearchController extends GetxController {
  final SearchService _searchService = SearchService();
  final TradeService _tradeService = TradeService();

  // Search results
  final RxList<ArtisanSearchResult> searchResults = <ArtisanSearchResult>[].obs;
  final RxList<Sector> sectors = <Sector>[].obs;
  final RxList<Trade> trades = <Trade>[].obs;

  // Loading states
  final RxBool isLoading = false.obs;
  final RxBool isLoadingSectors = false.obs;
  final RxString errorMessage = ''.obs;

  // Search filters
  final Rx<Position?> currentPosition = Rx<Position?>(null);
  final Rxn<int> selectedTradeId = Rxn<int>();
  final Rxn<int> selectedSectorId = Rxn<int>();
  final RxDouble searchRadius = 10000.0.obs; // 10km default
  final Rxn<int> minScore = Rxn<int>();
  final RxString sortBy = 'distance'.obs; // distance, rating, experience

  @override
  void onInit() {
    super.onInit();
    fetchSectors();
    fetchAllTrades();
    getCurrentLocation();
  }

  /// Get current location
  Future<void> getCurrentLocation() async {
    try {
      // Check permission
      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
        if (permission == LocationPermission.denied) {
          errorMessage.value = 'Location permission denied';
          return;
        }
      }

      if (permission == LocationPermission.deniedForever) {
        errorMessage.value = 'Location permissions are permanently denied';
        return;
      }

      final position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
      );

      currentPosition.value = position;
    } catch (e) {
      errorMessage.value = 'Failed to get location: ${e.toString()}';
    }
  }

  /// Fetch all sectors
  Future<void> fetchSectors() async {
    try {
      isLoadingSectors.value = true;
      errorMessage.value = '';

      final response = await _tradeService.getSectors();

      if (response.success && response.data != null) {
        sectors.value = response.data!;
      } else {
        errorMessage.value = response.message ?? 'Failed to fetch sectors';
      }
    } catch (e) {
      errorMessage.value = 'Network error: ${e.toString()}';
    } finally {
      isLoadingSectors.value = false;
    }
  }

  /// Fetch all trades
  Future<void> fetchAllTrades() async {
    try {
      isLoading.value = true;
      errorMessage.value = '';

      final response = await _tradeService.getAllTrades();

      if (response.success && response.data != null) {
        trades.value = response.data!;
      } else {
        errorMessage.value = response.message ?? 'Failed to fetch trades';
      }
    } catch (e) {
      errorMessage.value = 'Network error: ${e.toString()}';
    } finally {
      isLoading.value = false;
    }
  }

  /// Fetch trades by sector
  Future<void> fetchTradesBySector(int sectorId) async {
    try {
      isLoading.value = true;
      errorMessage.value = '';

      final response = await _tradeService.getTradesBySector(sectorId);

      if (response.success && response.data != null) {
        trades.value = response.data!;
      } else {
        errorMessage.value = response.message ?? 'Failed to fetch trades';
      }
    } catch (e) {
      errorMessage.value = 'Network error: ${e.toString()}';
    } finally {
      isLoading.value = false;
    }
  }

  /// Search artisans
  Future<void> searchArtisans() async {
    if (currentPosition.value == null) {
      errorMessage.value = 'Location not available';
      return;
    }

    try {
      isLoading.value = true;
      errorMessage.value = '';

      final response = await _searchService.searchArtisans(
        latitude: currentPosition.value!.latitude,
        longitude: currentPosition.value!.longitude,
        tradeId: selectedTradeId.value,
        sectorId: selectedSectorId.value,
        radius: searchRadius.value,
        minScore: minScore.value,
        sortBy: sortBy.value,
      );

      if (response.success && response.data != null) {
        searchResults.value = response.data!;
      } else {
        errorMessage.value = response.message ?? 'No artisans found';
        searchResults.value = [];
      }
    } catch (e) {
      errorMessage.value = 'Network error: ${e.toString()}';
      searchResults.value = [];
    } finally {
      isLoading.value = false;
    }
  }

  /// Get nearby artisans (<2km)
  Future<void> getNearbyArtisans() async {
    if (currentPosition.value == null) {
      errorMessage.value = 'Location not available';
      return;
    }

    try {
      isLoading.value = true;
      errorMessage.value = '';

      final response = await _searchService.getNearbyArtisans(
        latitude: currentPosition.value!.latitude,
        longitude: currentPosition.value!.longitude,
        tradeId: selectedTradeId.value,
      );

      if (response.success && response.data != null) {
        searchResults.value = response.data!;
      } else {
        errorMessage.value = response.message ?? 'No nearby artisans found';
        searchResults.value = [];
      }
    } catch (e) {
      errorMessage.value = 'Network error: ${e.toString()}';
      searchResults.value = [];
    } finally {
      isLoading.value = false;
    }
  }

  /// Set trade filter
  void setTradeFilter(int? tradeId) {
    selectedTradeId.value = tradeId;
    if (tradeId != null) {
      searchArtisans();
    }
  }

  /// Set sector filter
  void setSectorFilter(int? sectorId) {
    selectedSectorId.value = sectorId;
    // Reset trade filter when sector changes
    selectedTradeId.value = null;
    trades.clear();

    if (sectorId != null) {
      fetchTradesBySector(sectorId);
    }
  }

  /// Set search radius
  void setSearchRadius(double radius) {
    searchRadius.value = radius;
  }

  /// Set minimum score filter
  void setMinScore(int? score) {
    minScore.value = score;
  }

  /// Set sort order
  void setSortBy(String sort) {
    sortBy.value = sort;
    searchArtisans();
  }

  /// Clear filters
  void clearFilters() {
    selectedTradeId.value = null;
    selectedSectorId.value = null;
    minScore.value = null;
    searchRadius.value = 10000.0;
    sortBy.value = 'distance';
    trades.clear();
    searchResults.clear();
  }

  /// Get nearby artisans count
  int get nearbyArtisansCount {
    return searchResults.where((artisan) => artisan.isNearby).length;
  }
}
