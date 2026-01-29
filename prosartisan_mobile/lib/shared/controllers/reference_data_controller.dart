import 'package:get/get.dart';
import '../models/sector.dart';
import '../models/trade.dart';
import '../data/repositories/reference_data_repository.dart';

class ReferenceDataController extends GetxController {
  final ReferenceDataRepository _repository =
      Get.find<ReferenceDataRepository>();

  // Observable lists
  final RxList<Sector> _sectors = <Sector>[].obs;
  final RxList<Trade> _allTrades = <Trade>[].obs;
  final RxList<Trade> _filteredTrades = <Trade>[].obs;

  // Loading states
  final RxBool _isLoadingSectors = false.obs;
  final RxBool _isLoadingTrades = false.obs;

  // Error states
  final RxString _errorMessage = ''.obs;

  // Getters
  List<Sector> get sectors => _sectors;
  List<Trade> get allTrades => _allTrades;
  List<Trade> get filteredTrades => _filteredTrades;
  bool get isLoadingSectors => _isLoadingSectors.value;
  bool get isLoadingTrades => _isLoadingTrades.value;
  String get errorMessage => _errorMessage.value;
  bool get hasError => _errorMessage.value.isNotEmpty;

  @override
  void onInit() {
    super.onInit();
    loadSectorsWithTrades();
  }

  /// Charge tous les secteurs avec leurs métiers
  Future<void> loadSectorsWithTrades() async {
    try {
      _isLoadingSectors.value = true;
      _errorMessage.value = '';

      final sectors = await _repository.getSectorsWithTrades();
      _sectors.assignAll(sectors);

      // Extraire tous les métiers
      final List<Trade> allTrades = [];
      for (final sector in sectors) {
        if (sector.trades != null) {
          allTrades.addAll(sector.trades!);
        }
      }
      _allTrades.assignAll(allTrades);
      _filteredTrades.assignAll(allTrades);
    } catch (e) {
      _errorMessage.value = e.toString();
      Get.snackbar(
        'Erreur',
        'Impossible de charger les secteurs et métiers: $e',
        snackPosition: SnackPosition.BOTTOM,
      );
    } finally {
      _isLoadingSectors.value = false;
    }
  }

  /// Charge tous les métiers
  Future<void> loadAllTrades() async {
    try {
      _isLoadingTrades.value = true;
      _errorMessage.value = '';

      final trades = await _repository.getAllTrades();
      _allTrades.assignAll(trades);
      _filteredTrades.assignAll(trades);
    } catch (e) {
      _errorMessage.value = e.toString();
      Get.snackbar(
        'Erreur',
        'Impossible de charger les métiers: $e',
        snackPosition: SnackPosition.BOTTOM,
      );
    } finally {
      _isLoadingTrades.value = false;
    }
  }

  /// Filtre les métiers par secteur
  void filterTradesBySector(int? sectorId) {
    if (sectorId == null) {
      _filteredTrades.assignAll(_allTrades);
    } else {
      final filteredTrades = _allTrades
          .where((trade) => trade.sectorId == sectorId)
          .toList();
      _filteredTrades.assignAll(filteredTrades);
    }
  }

  /// Recherche des métiers par nom
  void searchTrades(String query) {
    if (query.isEmpty) {
      _filteredTrades.assignAll(_allTrades);
    } else {
      final searchResults = _allTrades
          .where(
            (trade) =>
                trade.name.toLowerCase().contains(query.toLowerCase()) ||
                trade.code.toLowerCase().contains(query.toLowerCase()),
          )
          .toList();
      _filteredTrades.assignAll(searchResults);
    }
  }

  /// Récupère un métier par son ID
  Trade? getTradeById(int id) {
    try {
      return _allTrades.firstWhere((trade) => trade.id == id);
    } catch (e) {
      return null;
    }
  }

  /// Récupère un secteur par son ID
  Sector? getSectorById(int id) {
    try {
      return _sectors.firstWhere((sector) => sector.id == id);
    } catch (e) {
      return null;
    }
  }

  /// Récupère les métiers d'un secteur spécifique
  List<Trade> getTradesBySectorId(int sectorId) {
    return _allTrades.where((trade) => trade.sectorId == sectorId).toList();
  }

  /// Rafraîchit les données
  Future<void> refresh() async {
    await loadSectorsWithTrades();
  }

  /// Efface les erreurs
  void clearError() {
    _errorMessage.value = '';
  }
}
