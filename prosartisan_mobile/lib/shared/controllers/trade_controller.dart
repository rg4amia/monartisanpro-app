import 'package:get/get.dart';
import '../models/sector.dart';
import '../models/trade.dart';
import '../data/repositories/trade_repository.dart';

/// Controller for managing trade and sector data
class TradeController extends GetxController {
  final TradeRepository _tradeRepository = TradeRepository();

  final RxList<Sector> sectors = <Sector>[].obs;
  final RxList<Trade> trades = <Trade>[].obs;
  final RxList<Trade> tradesForSelectedSector = <Trade>[].obs;
  final RxBool isLoadingSectors = false.obs;
  final RxBool isLoadingTrades = false.obs;
  final RxString errorMessage = ''.obs;
  final RxInt selectedSectorId = 0.obs;

  @override
  void onInit() {
    super.onInit();
    loadSectors();
  }

  /// Load all sectors
  Future<void> loadSectors() async {
    try {
      isLoadingSectors.value = true;
      errorMessage.value = '';

      sectors.value = await _tradeRepository.getSectors();
    } catch (e) {
      errorMessage.value = e.toString().replaceAll('Exception: ', '');
    } finally {
      isLoadingSectors.value = false;
    }
  }

  /// Load trades for a specific sector
  Future<void> loadTradesBySector(int sectorId) async {
    try {
      isLoadingTrades.value = true;
      errorMessage.value = '';
      selectedSectorId.value = sectorId;

      tradesForSelectedSector.value = await _tradeRepository.getTradesBySector(
        sectorId,
      );
    } catch (e) {
      errorMessage.value = e.toString().replaceAll('Exception: ', '');
      tradesForSelectedSector.clear();
    } finally {
      isLoadingTrades.value = false;
    }
  }

  /// Load all trades (for backward compatibility)
  Future<void> loadTrades() async {
    try {
      isLoadingTrades.value = true;
      errorMessage.value = '';

      trades.value = await _tradeRepository.getAllTrades();
    } catch (e) {
      errorMessage.value = e.toString().replaceAll('Exception: ', '');
    } finally {
      isLoadingTrades.value = false;
    }
  }

  /// Get trades for a specific sector (from loaded data)
  List<Trade> getTradesBySector(int sectorId) {
    return trades.where((trade) => trade.sectorId == sectorId).toList();
  }

  /// Refresh sectors data
  Future<void> refreshSectors() async {
    await loadSectors();
  }

  /// Refresh trades data for current sector
  Future<void> refreshTrades() async {
    if (selectedSectorId.value > 0) {
      await loadTradesBySector(selectedSectorId.value);
    } else {
      await loadTrades();
    }
  }

  /// Clear selected sector and trades
  void clearSelection() {
    selectedSectorId.value = 0;
    tradesForSelectedSector.clear();
  }
}
